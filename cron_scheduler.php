<?php
// cron_scheduler.php - المجدول الذكي لتحديث النتائج
// يتم تشغيله كل دقيقة عبر Cron Job
require_once __DIR__ . '/db.php';

// ضبط التوقيت (مهم جداً أن يطابق توقيت المباريات في الموقع)
date_default_timezone_set('Africa/Cairo'); 
set_time_limit(300); // 5 دقائق كحد أقصى

$now = time();
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

echo "--- Cron Scheduler Started at " . date('Y-m-d H:i:s') . " ---\n";

// ============================================================
// 1. تحديث مباريات الأمس (إذا كانت هناك مباريات بدون نتيجة)
// ============================================================
$stmt = $pdo->prepare("SELECT COUNT(*) FROM matches WHERE match_date = ? AND (score_home IS NULL OR score_away IS NULL)");
$stmt->execute([$yesterday]);
$missing_scores_yesterday = $stmt->fetchColumn();

if ($missing_scores_yesterday > 0) {
    echo "Found $missing_scores_yesterday matches from yesterday without scores. Updating YESTERDAY ($yesterday)...\n";
    perform_scrape($pdo, $yesterday);
}

// ============================================================
// 2. تحديث مباريات اليوم (فقط إذا كانت هناك مباريات جارية)
// ============================================================
// جلب مباريات اليوم
$stmt = $pdo->prepare("SELECT * FROM matches WHERE match_date = ?");
$stmt->execute([$today]);
$today_matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

$should_update_today = false;

foreach ($today_matches as $match) {
    if (empty($match['match_time'])) continue;
    
    // تنظيف الوقت وتحويله
    $timeStr = str_replace(['ص', 'م'], ['AM', 'PM'], $match['match_time']);
    $matchTimestamp = strtotime("$today $timeStr");
    
    if ($matchTimestamp === false) continue;

    // الشرط: الوقت الحالي أكبر من وقت المباراة بـ 0 دقيقة وأقل من وقت المباراة بـ 150 دقيقة (ساعتين ونصف)
    // أو الوقت الحالي قبل المباراة بـ 10 دقائق (للتأكد من التحديث عند البداية)
    if ($now >= ($matchTimestamp - 600) && $now <= ($matchTimestamp + 150 * 60)) {
        $should_update_today = true;
        echo "Active Match Found: {$match['team_home']} vs {$match['team_away']} ($timeStr)\n";
        break; // يكفي مباراة واحدة لتشغيل التحديث
    }
}

if ($should_update_today) {
    echo "Triggering update for TODAY ($today)...\n";
    perform_scrape($pdo, $today);
} else {
    echo "No active matches right now. Sleeping...\n";
}

// ============================================================
// دالة السحب والتحديث (مدمجة لضمان السرعة وعدم الاعتماد على ملفات خارجية)
// ============================================================
function perform_scrape($pdo, $dateStr) {
    $url = "https://www.yallakora.com/match-center/?date=$dateStr";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    // إعدادات الشبكة الهامة للاستضافة
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
        'Accept-Language: ar,en-US;q=0.9,en;q=0.8',
        'Cache-Control: max-age=0',
        'Connection: keep-alive',
        'Upgrade-Insecure-Requests: 1'
    ]);
    curl_setopt($ch, CURLOPT_ENCODING, '');
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (!$html || $httpCode !== 200) {
        echo "Error fetching URL: $url (Code: $httpCode)\n";
        return;
    }

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);

    $leagues = $xpath->query("//div[contains(@class, 'matchCard')]");
    $updated_count = 0;

    foreach ($leagues as $leagueNode) {
        $matches = $xpath->query(".//div[contains(@class, 'item')]", $leagueNode);
        foreach ($matches as $matchNode) {
            $teamHome = trim($xpath->query(".//div[contains(@class, 'teamA')]//p", $matchNode)->item(0)->nodeValue ?? '');
            $teamAway = trim($xpath->query(".//div[contains(@class, 'teamB')]//p", $matchNode)->item(0)->nodeValue ?? '');
            
            // استخراج النتيجة
            $scoreStr = trim($xpath->query(".//div[contains(@class, 'MResult')]//div[contains(@class, 'score')]", $matchNode)->item(0)->textContent ?? '');
            
            // محاولة بديلة لاستخراج النتيجة إذا كانت الطريقة الأولى فارغة (مهم جداً للمباريات المنتهية)
            if (empty($scoreStr)) {
                $scoreSpans = $xpath->query(".//div[contains(@class, 'MResult')]//span[contains(@class, 'score')]", $matchNode);
                if ($scoreSpans->length >= 2) $scoreStr = $scoreSpans->item(0)->textContent . ' - ' . $scoreSpans->item(1)->textContent;
            }
            
            $scoreHome = null;
            $scoreAway = null;
            
            // تنظيف وتحليل النتيجة
            $scoreStr = trim(preg_replace('/[^\d\-\–\—]/u', ' ', $scoreStr));
            if (!empty($scoreStr)) {
                if (preg_match('/(\d+)\s*[-–—]\s*(\d+)/u', $scoreStr, $m)) {
                    $scoreHome = (int)$m[1];
                    $scoreAway = (int)$m[2];
                } elseif (preg_match_all('/\d+/', $scoreStr, $m) && count($m[0]) >= 2) {
                    $scoreHome = (int)$m[0][0];
                    $scoreAway = (int)$m[0][1];
                }
            }

            if ($scoreHome !== null && $scoreAway !== null) {
                // تحديث النتيجة في قاعدة البيانات
                $stmt = $pdo->prepare("UPDATE matches SET score_home = ?, score_away = ? WHERE match_date = ? AND team_home = ? AND team_away = ?");
                $stmt->execute([$scoreHome, $scoreAway, $dateStr, $teamHome, $teamAway]);
                if ($stmt->rowCount() > 0) {
                    $updated_count++;
                    echo "Updated: $teamHome vs $teamAway ($scoreHome-$scoreAway)\n";
                }
            }
        }
    }
    echo "Updated $updated_count matches for $dateStr.\n";
}
?>