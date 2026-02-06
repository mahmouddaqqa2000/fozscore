<?php
// scraper_all.php - سحب مباريات الأمس واليوم والغد من YallaKora وتحديث النتائج

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

header('Content-Type: text/html; charset=utf-8');
set_time_limit(0); // منع توقف السكربت أثناء العمل لفترة طويلة
ob_implicit_flush(true); // إجبار السيرفر على إرسال البيانات للمتصفح فوراً
if (ob_get_level() > 0) ob_end_flush();

// دالة لجلب الوقت الحقيقي من الإنترنت (Google) لتجاوز خطأ توقيت السيرفر
function get_network_time() {
    $ch = curl_init("http://www.google.com/");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_NOBODY, 1); // طلب الترويسة فقط
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    // curl_close($ch);

    if ($response && preg_match('/^Date: (.+)$/mi', $response, $matches)) {
        return strtotime($matches[1]);
    }
    return time(); // العودة لتوقيت السيرفر في حال الفشل
}

$base_timestamp = get_network_time();

// إعداد التواريخ (الأمس، اليوم، الغد)
$dates = [
    date('m/d/Y', strtotime('-1 day', $base_timestamp)),
    date('m/d/Y', $base_timestamp),
    date('m/d/Y', strtotime('+1 day', $base_timestamp))
];

echo "بدء عملية السحب والتحديث الشامل من YallaKora (بتاريخ الشبكة: " . date('Y-m-d', $base_timestamp) . ")...<br>";
flush();

foreach ($dates as $dateStr) {
    echo "<hr>";
    echo "جاري سحب مباريات تاريخ: $dateStr<br>";
    flush();
    
    $url = "https://www.yallakora.com/match-center/?date=$dateStr";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    // محاكاة متصفح حقيقي بشكل كامل
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
        'Accept-Language: ar,en-US;q=0.9,en;q=0.8',
        'Cache-Control: max-age=0',
        'Connection: keep-alive',
        'Upgrade-Insecure-Requests: 1'
    ]);
    curl_setopt($ch, CURLOPT_ENCODING, ''); // فك ضغط الاستجابة (GZIP)
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15); // مهلة اتصال 15 ثانية
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);        // مهلة قراءة 60 ثانية
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4); // إجبار استخدام IPv4 لتجنب مشاكل الاستضافة
    
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // curl_close($ch); // Removed to avoid deprecated warning in PHP 8.x

    // Debug: طباعة أول 500 حرف من الصفحة عند سحب مباريات الأمس فقط
    if ($dateStr == date('m/d/Y', strtotime('-1 day', $base_timestamp))) {
        echo "<div style='direction:ltr;font-size:10px;background:#eee;padding:5px;'>\n";
        echo htmlspecialchars(substr($html, 0, 500));
        echo "</div>\n";
    }

    if (!$html || $httpCode !== 200) {
        echo "فشل الاتصال بالموقع ($dateStr). رمز الحالة: $httpCode<br>";
        continue;
    }

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    // إصلاح مشكلة الترميز لضمان قراءة النصوص والأرقام بشكل صحيح
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);

    // البحث عن البطولات
    $leagues = $xpath->query("//div[contains(@class, 'matchCard')]");

    if ($leagues->length === 0) {
        echo "لم يتم العثور على أي مباريات لهذا التاريخ.<br>";
    }

    $count_added = 0;
    $count_updated = 0;

    foreach ($leagues as $leagueNode) {
        $championship = trim($xpath->query(".//div[contains(@class, 'title')]//h2", $leagueNode)->item(0)->nodeValue ?? 'مباريات متنوعة');
        $imgNode = $xpath->query(".//div[contains(@class, 'title')]//img", $leagueNode)->item(0);
        $leagueLogo = $imgNode ? $imgNode->getAttribute('src') : null;

        $matches = $xpath->query(".//div[contains(@class, 'item')]", $leagueNode);

        foreach ($matches as $matchNode) {
            $teamHome = trim($xpath->query(".//div[contains(@class, 'teamA')]//p", $matchNode)->item(0)->nodeValue ?? '');
            $teamAway = trim($xpath->query(".//div[contains(@class, 'teamB')]//p", $matchNode)->item(0)->nodeValue ?? '');
            $homeLogo = $xpath->query(".//div[contains(@class, 'teamA')]//img", $matchNode)->item(0)->getAttribute('src');
            $awayLogo = $xpath->query(".//div[contains(@class, 'teamB')]//img", $matchNode)->item(0)->getAttribute('src');
            
            $matchTimeStr = trim($xpath->query(".//div[contains(@class, 'MResult')]//span[contains(@class, 'time')]", $matchNode)->item(0)->nodeValue ?? '');
            // استخراج النتائج من span.score
            $scoreSpans = $xpath->query(".//div[contains(@class, 'MResult')]//span[contains(@class, 'score')]", $matchNode);
            $scoreHome = null;
            $scoreAway = null;
            if ($scoreSpans->length >= 2) {
                $scoreHome = (int)trim($scoreSpans->item(0)->nodeValue);
                $scoreAway = (int)trim($scoreSpans->item(1)->nodeValue);
            }
            $scoreStr = $scoreHome !== null && $scoreAway !== null ? $scoreHome . '-' . $scoreAway : '';
            $channel = trim($xpath->query(".//div[contains(@class, 'channel')]", $matchNode)->item(0)->nodeValue ?? '');
            $relativeMatchLink = $xpath->query(".//a", $matchNode)->item(0)?->getAttribute('href');
            $fullMatchUrl = $relativeMatchLink ? ((strpos($relativeMatchLink, 'http') === 0) ? $relativeMatchLink : "https://www.yallakora.com" . $relativeMatchLink) : null;

            if (empty($teamHome) || empty($teamAway)) continue;

            // Debug: طباعة بيانات المباراة عند سحب مباريات الأمس فقط
            if ($dateStr == date('m/d/Y', strtotime('-1 day', $base_timestamp))) {
                echo "<div style='font-size:11px;color:#333;background:#f9f9f9;margin:2px 0;padding:2px;'>";
                echo "Match: $teamHome vs $teamAway | scoreStr: $scoreStr | time: $matchTimeStr";
                // طباعة عنصر النتيجة الخام
                $mResultNode = $xpath->query(".//div[contains(@class, 'MResult')]", $matchNode)->item(0);
                if ($mResultNode) {
                    echo "<pre style='white-space:pre-wrap;background:#eee;border:1px solid #ccc;'>";
                    echo htmlspecialchars($dom->saveHTML($mResultNode));
                    echo "</pre>";
                }
                echo "</div>\n";
            }

            $matchTime = $matchTimeStr;
            $scoreHome = null;
            $scoreAway = null;
            
            // تنظيف وتحليل النتيجة
            $scoreStr = trim(preg_replace('/[^\d\-\–\—]/u', ' ', $scoreStr)); // إبقاء الأرقام والشرطات فقط
            if (!empty($scoreStr)) {
                // محاولة 1: البحث عن نمط "رقم - رقم"
                if (preg_match('/(\d+)\s*[-–—]\s*(\d+)/u', $scoreStr, $matches)) {
                    $scoreHome = (int)$matches[1];
                    $scoreAway = (int)$matches[2];
                } elseif (preg_match_all('/\d+/', $scoreStr, $matches)) {
                    // محاولة 2: البحث عن أي رقمين (احتياطي)
                    if (count($matches[0]) >= 2) {
                        $scoreHome = (int)$matches[0][0];
                        $scoreAway = (int)$matches[0][1];
                    }
                }
            }

            // تحويل التاريخ لصيغة قاعدة البيانات Y-m-d
            $matchDateDB = date('Y-m-d', strtotime($dateStr));

            // التحقق من وجود المباراة
            $stmt = $pdo->prepare("SELECT id, lineup_home FROM matches WHERE match_date = ? AND team_home = ? AND team_away = ?");
            $stmt->execute([$matchDateDB, $teamHome, $teamAway]);
            $existing = $stmt->fetch();

            // جلب التشكيلة إذا كانت مفقودة
            $lineupHome = null;
            $lineupAway = null;
            $coachHome = null;
            $coachAway = null;
            $matchStats = null;
            $matchEvents = null;
            $streamUrl = null;
            
            // تفعيل السحب التلقائي للتشكيلة والإحصائيات إذا كانت ناقصة
            // تم التعديل لسحب الأحداث فقط
            $shouldFetchLineup = $fullMatchUrl && (!$existing || empty($existing['match_events']));
            
            if ($shouldFetchLineup) {
                $details = get_match_details($fullMatchUrl);
                $lineupHome = $details['home'];
                 $lineupAway = $details['away'];
                $coachHome = $details['coach_home'];
                $coachAway = $details['coach_away'];
                $matchStats = $details['stats'];
                $matchEvents = $details['match_events'];
                $streamUrl = $details['stream_url'];
                if ($lineupHome) {
                    echo " <span style='color:blue;font-size:0.8em;'>[تم جلب التشكيلة]</span>";
                }
                usleep(200000); // انتظار بسيط (0.2 ثانية) لتجنب الحظر
            }

            if ($existing) {
                // تحديث النتيجة فقط إذا تم العثور عليها، أو إذا كانت المباراة لم تبدأ بعد (لتصفيرها)
                // هذا يمنع مسح النتيجة إذا فشل البوت في قراءتها لمباراة منتهية
                if ($scoreHome !== null) {
                    $update = $pdo->prepare("UPDATE matches SET score_home = ?, score_away = ?, team_home_logo = ?, team_away_logo = ?, match_time = ?, channel = ?, championship = ?, championship_logo = ?, lineup_home = COALESCE(?, lineup_home), lineup_away = COALESCE(?, lineup_away), coach_home = COALESCE(?, coach_home), coach_away = COALESCE(?, coach_away), match_stats = COALESCE(?, match_stats), match_events = COALESCE(?, match_events), stream_url = COALESCE(?, stream_url), source_url = ? WHERE id = ?");
                    $update->execute([$scoreHome, $scoreAway, $homeLogo, $awayLogo, $matchTime, $channel, $championship, $leagueLogo, $lineupHome, $lineupAway, $coachHome, $coachAway, $matchStats, $matchEvents, $streamUrl, $fullMatchUrl, $existing['id']]);
                } else {
                    // تحديث باقي البيانات دون النتيجة
                    $update = $pdo->prepare("UPDATE matches SET team_home_logo = ?, team_away_logo = ?, match_time = ?, channel = ?, championship = ?, championship_logo = ?, lineup_home = COALESCE(?, lineup_home), lineup_away = COALESCE(?, lineup_away), coach_home = COALESCE(?, coach_home), coach_away = COALESCE(?, coach_away), match_stats = COALESCE(?, match_stats), match_events = COALESCE(?, match_events), stream_url = COALESCE(?, stream_url), source_url = ? WHERE id = ?");
                    $update->execute([$homeLogo, $awayLogo, $matchTime, $channel, $championship, $leagueLogo, $lineupHome, $lineupAway, $coachHome, $coachAway, $matchStats, $matchEvents, $streamUrl, $fullMatchUrl, $existing['id']]);
                }
                
                $count_updated++;
                if ($scoreHome !== null) {
                    echo " <span style='color:green;font-size:0.8em;'>[تم تحديث النتيجة: $scoreHome-$scoreAway]</span>";
                }
            } else {
                $insert = $pdo->prepare("INSERT INTO matches (match_date, match_time, team_home, team_away, score_home, score_away, championship, team_home_logo, team_away_logo, channel, championship_logo, lineup_home, lineup_away, coach_home, coach_away, stream_url, source_url, match_stats, match_events) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $insert->execute([$matchDateDB, $matchTime, $teamHome, $teamAway, $scoreHome, $scoreAway, $championship, $homeLogo, $awayLogo, $channel, $leagueLogo, $lineupHome, $lineupAway, $coachHome, $coachAway, $streamUrl, $fullMatchUrl, $matchStats, $matchEvents]);
                $count_added++;
            }
        }
    }
    echo "النتيجة: تم إضافة $count_added | تم تحديث $count_updated<br>";
    flush();
    
    // انتظار عشوائي بين 2 إلى 5 ثواني ليبدو كأنه إنسان يتصفح
    sleep(rand(2, 5));
}

// سحب الأخبار بعد الانتهاء من المباريات
scrape_yallakora_news($pdo);

echo "<hr>";
echo "تم الانتهاء من جميع العمليات بنجاح!<br>";
echo '<br><br><a href="bot_dashboard.php" style="padding:10px; background:#2563eb; color:white; text-decoration:none; border-radius:5px;">العودة للوحة التحكم</a>';

?>