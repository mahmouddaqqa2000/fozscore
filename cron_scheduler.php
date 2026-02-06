<?php
// cron_scheduler.php - المجدول الذكي لتحديث النتائج
// يتم تشغيله كل دقيقة عبر Cron Job
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php'; // لاستخدام دالة إرسال تيليجرام

// ضبط التوقيت (مهم جداً أن يطابق توقيت المباريات في الموقع)
date_default_timezone_set('Africa/Cairo'); 
set_time_limit(300); // 5 دقائق كحد أقصى

$now = time();
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

// جلب إعدادات الموقع (مهم للروابط والإعدادات الأخرى)
$settings = get_site_settings($pdo);

echo "--- Cron Scheduler Started at " . date('Y-m-d H:i:s') . " ---\n";

// ============================================================
// 1. تحديث مباريات الأمس (إذا كانت هناك مباريات بدون نتيجة)
// ============================================================
$stmt = $pdo->prepare("SELECT COUNT(*) FROM matches WHERE match_date = ? AND (score_home IS NULL OR score_away IS NULL)");
$stmt->execute([$yesterday]);
$missing_scores_yesterday = $stmt->fetchColumn();

if ($missing_scores_yesterday > 0) {
    echo "Found $missing_scores_yesterday matches from yesterday without scores. Updating YESTERDAY ($yesterday)...\n";
    perform_scrape($pdo, $yesterday, $settings);
}

// ============================================================
// 2. تحديث مباريات اليوم (فقط إذا كانت هناك مباريات جارية)
// ============================================================
// جلب مباريات اليوم
$stmt = $pdo->prepare("SELECT * FROM matches WHERE match_date = ?");
$stmt->execute([$today]);
$today_matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ملف لتخزين الإشعارات المرسلة لتجنب التكرار
$sent_file = __DIR__ . '/sent_notifications_' . date('Y-m-d') . '.json';
$sent_notifications = file_exists($sent_file) ? json_decode(file_get_contents($sent_file), true) : [];
if (!is_array($sent_notifications)) $sent_notifications = [];

$should_update_today = false;

foreach ($today_matches as $match) {
    if (empty($match['match_time'])) continue;
    
    // تنظيف الوقت وتحويله
    $timeStr = str_replace(['ص', 'م'], ['AM', 'PM'], $match['match_time']);
    $matchTimestamp = strtotime("$today $timeStr");
    
    if ($matchTimestamp === false) continue;

    $match_url = rtrim($settings['site_url'], '/') . '/view_match.php?id=' . $match['id'];

    // إرسال إشعار بداية المباراة (إذا حان وقتها ولم يرسل من قبل)
    // نتحقق مما إذا كان الوقت الحالي قد تجاوز وقت المباراة بحد أقصى 5 دقائق
    // تم زيادة النافذة إلى 30 دقيقة (1800 ثانية) لضمان عدم تفويت الإشعار حتى لو تأخر الكرون
    if ($now >= $matchTimestamp && $now <= ($matchTimestamp + 1800) && !isset($sent_notifications[$match['id']]['start'])) {
        $msg = "🔔 <b>بداية المباراة الآن</b>\n\n";
        $msg .= "⚽ {$match['team_home']} 🆚 {$match['team_away']}\n";
        if (!empty($match['championship'])) $msg .= "🏆 <i>{$match['championship']}</i>\n\n";
        $msg .= "<a href=\"$match_url\">تابع المباراة مباشرة</a>";
        
        send_telegram_msg($pdo, $msg);
        
        $sent_notifications[$match['id']]['start'] = true;
        file_put_contents($sent_file, json_encode($sent_notifications));
        echo "Sent start notification for {$match['team_home']} vs {$match['team_away']}\n";
    } else {
        // Debug info (اختياري: لمعرفة سبب عدم الإرسال)
        // echo "Skipped start notification for {$match['team_home']} vs {$match['team_away']}: " . (isset($sent_notifications[$match['id']]['start']) ? "Already sent" : "Time mismatch") . "\n";
    }

    // إرسال إشعار نهاية المباراة (إذا انتهت ولديها نتيجة ولم يرسل من قبل)
    $status = get_match_status($match);
    if ($status['key'] === 'finished' && isset($match['score_home']) && !isset($sent_notifications[$match['id']]['finished'])) {
        $msg = "🏁 <b>نهاية المباراة</b>\n\n";
        $msg .= "{$match['team_home']} <b>{$match['score_home']} - {$match['score_away']}</b> {$match['team_away']}\n";
        if (!empty($match['championship'])) $msg .= "🏆 <i>{$match['championship']}</i>\n\n";
        $msg .= "<a href=\"$match_url\">عرض التفاصيل والإحصائيات</a>";
        send_telegram_msg($pdo, $msg);

        $sent_notifications[$match['id']]['finished'] = true;
        file_put_contents($sent_file, json_encode($sent_notifications));
        echo "Sent finish notification for {$match['team_home']} vs {$match['team_away']}\n";
    }

    // الشرط: الوقت الحالي أكبر من وقت المباراة بـ 0 دقيقة وأقل من وقت المباراة بـ 150 دقيقة (ساعتين ونصف)
    // أو الوقت الحالي قبل المباراة بـ 10 دقائق (للتأكد من التحديث عند البداية)
    if ($now >= ($matchTimestamp - 600) && $now <= ($matchTimestamp + 150 * 60)) {
        $should_update_today = true;
        echo "Active Match Found: {$match['team_home']} vs {$match['team_away']} ($timeStr)\n";
        // break; // تم إزالة break لضمان فحص جميع المباريات للإشعارات
    }
}

if ($should_update_today) {
    echo "Triggering update for TODAY ($today)...\n";
    perform_scrape($pdo, $today, $settings);
} else {
    echo "No active matches right now. Sleeping...\n";
}

// ============================================================
// دالة السحب والتحديث (مدمجة لضمان السرعة وعدم الاعتماد على ملفات خارجية)
// ============================================================
function perform_scrape($pdo, $dateStr, $settings) {
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
        // استخراج اسم البطولة للإشعار
        $championship = trim($xpath->query(".//div[contains(@class, 'title')]//h2", $leagueNode)->item(0)->nodeValue ?? '');
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

            // البحث عن المباراة في قاعدة البيانات للحصول على ID والنتيجة الحالية
            $stmt_find = $pdo->prepare("SELECT id, score_home, score_away, match_time FROM matches WHERE match_date = ? AND team_home = ? AND team_away = ?");
            $stmt_find->execute([$dateStr, $teamHome, $teamAway]);
            $db_match = $stmt_find->fetch(PDO::FETCH_ASSOC);

            if ($db_match && $scoreHome !== null && $scoreAway !== null) {
                // التحقق مما إذا كانت النتيجة قد تغيرت بالفعل
                // استخدام !== للمقارنة الصارمة لأن NULL == 0 في PHP، وهذا يمنع تحديث النتيجة عند بداية المباراة (0-0)
                if ($db_match['score_home'] !== $scoreHome || $db_match['score_away'] !== $scoreAway) {
                    // تحديث النتيجة
                    $stmt_update = $pdo->prepare("UPDATE matches SET score_home = ?, score_away = ? WHERE id = ?");
                    $stmt_update->execute([$scoreHome, $scoreAway, $db_match['id']]);
                
                    $updated_count++;
                    echo "Updated: $teamHome vs $teamAway ($scoreHome-$scoreAway)\n";
                    
                    // إرسال إشعار تيليجرام بالتحديث
                    // لا نرسل إشعار "هدف" إذا كانت النتيجة 0-0 وكانت سابقاً غير موجودة (بداية المباراة)
                    // لأن إشعار البداية يكفي، أو سيتم إرساله في الدورة القادمة
                    $is_start_0_0 = ($db_match['score_home'] === null && $scoreHome === 0 && $scoreAway === 0);
                    
                    // التحقق من أن المباراة جارية حالياً (اليوم + ضمن وقت اللعب)
                    // نتأكد أن التاريخ هو اليوم، وأن الوقت لم يتجاوز 3 ساعات (180 دقيقة) من البداية لضمان أنها مباشرة
                    $is_live_now = false;
                    if ($dateStr === date('Y-m-d') && !empty($db_match['match_time'])) {
                        $clean_time = str_replace(['ص', 'م'], ['AM', 'PM'], $db_match['match_time']);
                        $match_ts = strtotime("$dateStr $clean_time");
                        // نعتبر المباراة مباشرة إذا مر عليها أقل من 180 دقيقة (لشمل الوقت الإضافي)
                        if ($match_ts && time() <= ($match_ts + 180 * 60)) {
                            $is_live_now = true;
                        }
                    }

                    if (!$is_start_0_0 && $is_live_now) {
                        $match_url = rtrim($settings['site_url'], '/') . '/view_match.php?id=' . $db_match['id'];
                        $msg = "⚽ <b>تحديث مباشر (هدف!)</b>\n\n";
                        $msg .= "$teamHome <b>$scoreHome</b> - <b>$scoreAway</b> $teamAway\n";
                        if ($championship) $msg .= "🏆 <i>$championship</i>\n\n";
                        $msg .= "<a href=\"$match_url\">عرض التفاصيل</a>";
                        send_telegram_msg($pdo, $msg);
                    }
                }
            }
        }
    }
    echo "Updated $updated_count matches for $dateStr.\n";
}
?>