<?php
/**
 * scraper_all.php - سحب شامل لجميع المباريات وشعارات الدوريات من مركز المباريات
 * تم تحسينه للعمل على الاستضافة مع سحب شعار الدوري
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Africa/Cairo');

// زيادة وقت التنفيذ لأن الصفحة تحتوي على مباريات كثيرة
set_time_limit(300);
ini_set('memory_limit', '256M');
// إجبار السيرفر على إرسال المخرجات فوراً
if (function_exists('apache_setenv')) @apache_setenv('no-gzip', 1);
@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);
for ($i = 0; $i < ob_get_level(); $i++) { ob_end_flush(); }
ob_implicit_flush(1);

// دالة لجلب الوقت الحقيقي
function get_network_time() {
    $ch = curl_init("http://www.google.com/");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_NOBODY, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    if ($response && preg_match('/^Date: (.+)$/mi', $response, $matches)) {
        return strtotime($matches[1]);
    }
    return time();
}

$base_timestamp = get_network_time();

$mode = $_GET['mode'] ?? 'all';

// تحديد الأيام بناءً على الوضع المختار
if ($mode === 'today') {
    $dates = [date('m/d/Y', $base_timestamp)];
    echo "<h3>بدء تحديث مباريات اليوم...</h3>";
} elseif ($mode === 'yesterday') {
    $dates = [date('m/d/Y', strtotime('-1 day', $base_timestamp))];
    echo "<h3>بدء تحديث مباريات الأمس...</h3>";
} else {
    $dates = [
        date('m/d/Y', strtotime('-1 day', $base_timestamp)),
        date('m/d/Y', $base_timestamp),
        date('m/d/Y', strtotime('+1 day', $base_timestamp))
    ];
    echo "<h3>بدء التحديث الشامل (جميع المباريات + شعارات الدوريات)...</h3>";
}
flush();

foreach ($dates as $dateStr) {
    echo "<hr>";
    echo "جاري سحب مباريات تاريخ: <strong>$dateStr</strong>... <br>";
    flush();
    
    $url = "https://www.yallakora.com/match-center/?date=$dateStr";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    // محاكاة متصفح حقيقي لتجنب الحظر
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
        'Accept-Language: ar,en-US;q=0.9,en;q=0.8',
        'Cache-Control: max-age=0',
        'Connection: keep-alive',
        'Upgrade-Insecure-Requests: 1',
        'Sec-Fetch-Dest: document',
        'Sec-Fetch-Mode: navigate',
        'Sec-Fetch-Site: none',
        'Sec-Fetch-User: ?1'
    ]);
    curl_setopt($ch, CURLOPT_REFERER, 'https://www.yallakora.com/');
    curl_setopt($ch, CURLOPT_ENCODING, ''); // فك ضغط GZIP (مهم جداً)
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4); // إجبار IPv4 (حل مشكلة التعليق)
    
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // curl_close($ch); // معطل لتجنب التحذير

    if (!$html || $httpCode !== 200) {
        echo "<span style='color:red'>فشل الاتصال بالموقع ($httpCode).</span><br>";
        continue;
    }

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);

    // البحث عن البطولات (كل بطولة في div class="matchCard")
    $leagues = $xpath->query("//div[contains(@class, 'matchCard')]");

    if ($leagues->length === 0) {
        echo "لم يتم العثور على مباريات لهذا اليوم.<br>";
        continue;
    }

    $count_added = 0;
    $count_updated = 0;

    foreach ($leagues as $leagueNode) {
        // 1. استخراج اسم البطولة وشعارها
        $championship = trim($xpath->query(".//div[contains(@class, 'title')]//h2", $leagueNode)->item(0)->nodeValue ?? 'مباريات متنوعة');
        $leagueLogo = $xpath->query(".//div[contains(@class, 'title')]//img", $leagueNode)->item(0)?->getAttribute('src');
        
        // المباريات داخل هذه البطولة
        $matches = $xpath->query(".//div[contains(@class, 'item')]", $leagueNode);

        foreach ($matches as $matchNode) {
            // 2. استخراج بيانات الفريقين
            $teamHome = trim($xpath->query(".//div[contains(@class, 'teamA')]//p", $matchNode)->item(0)->nodeValue ?? '');
            $teamAway = trim($xpath->query(".//div[contains(@class, 'teamB')]//p", $matchNode)->item(0)->nodeValue ?? '');
            $homeLogo = $xpath->query(".//div[contains(@class, 'teamA')]//img", $matchNode)->item(0)?->getAttribute('src');
            $awayLogo = $xpath->query(".//div[contains(@class, 'teamB')]//img", $matchNode)->item(0)?->getAttribute('src');
            
            // 3. الوقت والنتيجة والقناة
            $matchTimeStr = trim($xpath->query(".//div[contains(@class, 'MResult')]//span[contains(@class, 'time')]", $matchNode)->item(0)->nodeValue ?? '');
            $scoreStr = trim($xpath->query(".//div[contains(@class, 'MResult')]//div[contains(@class, 'score')]", $matchNode)->item(0)->textContent ?? '');
            
            // محاولة بديلة لاستخراج النتيجة إذا كانت الطريقة الأولى فارغة (مهم جداً للمباريات المنتهية)
            if (empty($scoreStr)) {
                $scoreSpans = $xpath->query(".//div[contains(@class, 'MResult')]//span[contains(@class, 'score')]", $matchNode);
                if ($scoreSpans->length >= 2) {
                    $scoreStr = trim($scoreSpans->item(0)->textContent) . ' - ' . trim($scoreSpans->item(1)->textContent);
                }
            }
            
            $channel = trim($xpath->query(".//div[contains(@class, 'channel')]", $matchNode)->item(0)->nodeValue ?? '');
            
            // رابط المباراة
            $matchLink = $xpath->query(".//a", $matchNode)->item(0)?->getAttribute('href');
            $sourceUrl = ($matchLink) ? ((strpos($matchLink, 'http') === 0) ? $matchLink : "https://www.yallakora.com" . $matchLink) : null;

            if (empty($teamHome) || empty($teamAway)) continue;

            // تحليل النتيجة
            $scoreHome = null;
            $scoreAway = null;
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

            // ---------------------------------------------------------
            // سحب الإحصائيات (جديد)
            // ---------------------------------------------------------
            $matchStats = null;
            // نسحب الإحصائيات فقط إذا كانت المباراة جارية أو منتهية (يوجد نتيجة) ولدينا رابط
            if ($sourceUrl && ($scoreHome !== null || $scoreAway !== null)) {
                $ch_details = curl_init($sourceUrl);
                curl_setopt($ch_details, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch_details, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36');
                curl_setopt($ch_details, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch_details, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch_details, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($ch_details, CURLOPT_HTTPHEADER, [
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                    'Accept-Language: ar,en-US;q=0.9,en;q=0.8',
                    'Cache-Control: max-age=0',
                    'Connection: keep-alive',
                    'Upgrade-Insecure-Requests: 1',
                    'Sec-Fetch-Dest: document',
                    'Sec-Fetch-Mode: navigate'
                ]);
                curl_setopt($ch_details, CURLOPT_REFERER, 'https://www.yallakora.com/');
                curl_setopt($ch_details, CURLOPT_ENCODING, '');
                curl_setopt($ch_details, CURLOPT_CONNECTTIMEOUT, 10);
                curl_setopt($ch_details, CURLOPT_TIMEOUT, 20);
                $html_details = curl_exec($ch_details);
                // curl_close($ch_details); // Deprecated in PHP 8.5+

                if ($html_details) {
                    $dom_details = new DOMDocument();
                    @$dom_details->loadHTML('<?xml encoding="UTF-8">' . $html_details);
                    $xpath_details = new DOMXPath($dom_details);

                    $statsNodes = $xpath_details->query("//div[contains(@class, 'statsDiv')]//ul//li");
                    $statsArray = [];
                    foreach ($statsNodes as $node) {
                        $label = trim($xpath_details->query(".//div[contains(@class, 'desc')]", $node)->item(0)->nodeValue ?? '');
                        $homeVal = trim($xpath_details->query(".//div[contains(@class, 'teamA')]", $node)->item(0)->nodeValue ?? '');
                        $awayVal = trim($xpath_details->query(".//div[contains(@class, 'teamB')]", $node)->item(0)->nodeValue ?? '');
                        
                        if ($label !== '') {
                            $statsArray[] = ['label' => $label, 'home' => $homeVal, 'away' => $awayVal];
                        }
                    }
                    if (!empty($statsArray)) {
                        $matchStats = json_encode($statsArray, JSON_UNESCAPED_UNICODE);
                    }
                }
                // انتظار بسيط لتخفيف الحمل
                usleep(100000); 
            }
            // ---------------------------------------------------------

            $matchDateDB = date('Y-m-d', strtotime($dateStr));

            // 4. الحفظ في قاعدة البيانات
            $stmt = $pdo->prepare("SELECT id FROM matches WHERE match_date = ? AND team_home = ? AND team_away = ?");
            $stmt->execute([$matchDateDB, $teamHome, $teamAway]);
            $existing = $stmt->fetch();

            if ($existing) {
                // تحديث البيانات (بما في ذلك شعار البطولة)
                if ($scoreHome !== null) {
                    $update = $pdo->prepare("UPDATE matches SET score_home = ?, score_away = ?, match_time = ?, championship = ?, championship_logo = ?, team_home_logo = ?, team_away_logo = ?, channel = ?, source_url = ?, match_stats = COALESCE(?, match_stats) WHERE id = ?");
                    $update->execute([$scoreHome, $scoreAway, $matchTimeStr, $championship, $leagueLogo, $homeLogo, $awayLogo, $channel, $sourceUrl, $matchStats, $existing['id']]);
                } else {
                    $update = $pdo->prepare("UPDATE matches SET match_time = ?, championship = ?, championship_logo = ?, team_home_logo = ?, team_away_logo = ?, channel = ?, source_url = ? WHERE id = ?");
                    $update->execute([$matchTimeStr, $championship, $leagueLogo, $homeLogo, $awayLogo, $channel, $sourceUrl, $existing['id']]);
                }
                $count_updated++;
            } else {
                // إضافة مباراة جديدة
                $insert = $pdo->prepare("INSERT INTO matches (match_date, match_time, team_home, team_away, score_home, score_away, championship, championship_logo, team_home_logo, team_away_logo, channel, source_url, match_stats) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $insert->execute([$matchDateDB, $matchTimeStr, $teamHome, $teamAway, $scoreHome, $scoreAway, $championship, $leagueLogo, $homeLogo, $awayLogo, $channel, $sourceUrl, $matchStats]);
                $count_added++;
            }
        }
    }
    echo "النتيجة: تم إضافة $count_added | تم تحديث $count_updated<br>";
    flush();
    
    // انتظار بسيط لتخفيف الحمل
    usleep(500000); 
}

echo "<br><br><a href='bot_dashboard.php' style='text-decoration:none; color:blue;'>العودة للوحة التحكم</a>";
?>