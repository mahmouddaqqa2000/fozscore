<?php
// scraper_yallakora.php - سحب المباريات من YallaKora

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
header('Content-Type: text/html; charset=utf-8');
set_time_limit(0); // منع توقف السكربت بسبب الوقت الطويل

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

// دعم التشغيل عبر سطر الأوامر (Cron Job)
if (php_sapi_name() === 'cli') {
    // تحويل المعاملات مثل mode=today إلى $_GET
    parse_str(implode('&', array_slice($argv, 1)), $_GET);
}

// إعدادات التاريخ
// ملاحظة: تأكد من أن تاريخ السيرفر صحيح. إذا كان التاريخ خطأ، لن تجد مباريات.
$mode = $_GET['mode'] ?? 'tomorrow';

// تحديد ما إذا كنا نريد جلب التفاصيل (التشكيلة والأحداث) لأنها تبطئ العملية بشكل كبير
// الافتراضي: لا يتم جلب التفاصيل لتسريع تحديث النتائج
$fetch_details = isset($_GET['details']) && $_GET['details'] == '1';

if ($mode === 'yesterday') {
    $date = date('m/d/Y', strtotime('-1 day', $base_timestamp));
} elseif ($mode === 'today') {
    $date = date('m/d/Y', $base_timestamp);
} else {
    $date = date('m/d/Y', strtotime('+1 day', $base_timestamp));
}

$url = "https://www.yallakora.com/match-center/?date=$date";

echo "جاري الاتصال بـ YallaKora ($date)...\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36');
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15); // مهلة الاتصال 15 ثانية
curl_setopt($ch, CURLOPT_TIMEOUT, 60);        // مهلة القراءة 60 ثانية
$html = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
// curl_close($ch); // Removed to avoid deprecated warning

if (!$html || $httpCode !== 200) {
    die("فشل الاتصال بالموقع. رمز الحالة: $httpCode - خطأ Curl: " . curl_error($ch) . "\n");
}

$dom = new DOMDocument();
libxml_use_internal_errors(true);
// إصلاح مشكلة الترميز
$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
libxml_clear_errors();

$xpath = new DOMXPath($dom);

// البحث عن البطولات (كل بطولة في div class="matchCard")
$leagues = $xpath->query("//div[contains(@class, 'matchCard')]");

if ($leagues->length === 0) {
    echo "تنبيه: لم يتم العثور على أي مباريات في صفحة المصدر لهذا التاريخ ($date).\n";
}

$count = 0;
$updated = 0;

foreach ($leagues as $leagueNode) {
    // اسم البطولة
    $championship = trim($xpath->query(".//div[contains(@class, 'title')]//h2", $leagueNode)->item(0)->nodeValue ?? 'مباريات متنوعة');
    $leagueLogo = $xpath->query(".//div[contains(@class, 'title')]//img", $leagueNode)->item(0)?->getAttribute('src');
    
    // المباريات داخل البطولة
    $matches = $xpath->query(".//div[contains(@class, 'item')]", $leagueNode);
    
    foreach ($matches as $matchNode) {
        // الفريقين
        $teamHome = trim($xpath->query(".//div[contains(@class, 'teamA')]//p", $matchNode)->item(0)->nodeValue ?? '');
        $teamAway = trim($xpath->query(".//div[contains(@class, 'teamB')]//p", $matchNode)->item(0)->nodeValue ?? '');
        
        // الصور
        $homeLogo = $xpath->query(".//div[contains(@class, 'teamA')]//img", $matchNode)->item(0)->getAttribute('src');
        $awayLogo = $xpath->query(".//div[contains(@class, 'teamB')]//img", $matchNode)->item(0)->getAttribute('src');
        
        // الوقت والنتيجة
        $matchTimeStr = trim($xpath->query(".//div[contains(@class, 'MResult')]//span[contains(@class, 'time')]", $matchNode)->item(0)->nodeValue ?? '');
        $scoreStr = trim($xpath->query(".//div[contains(@class, 'MResult')]//div[contains(@class, 'score')]", $matchNode)->item(0)->textContent ?? '');
        
        // القناة
        $channel = trim($xpath->query(".//div[contains(@class, 'channel')]", $matchNode)->item(0)->nodeValue ?? '');
        
        // رابط تفاصيل المباراة (للخطوة التالية: جلب التشكيلة)
        $matchLink = $xpath->query(".//a", $matchNode)->item(0)?->getAttribute('href');
        $sourceUrl = ($matchLink) ? ((strpos($matchLink, 'http') === 0) ? $matchLink : "https://www.yallakora.com" . $matchLink) : null;

        if (empty($teamHome) || empty($teamAway)) continue;

        // معالجة الوقت
        $matchTime = $matchTimeStr;
        
        // معالجة النتيجة
        $scoreHome = null;
        $scoreAway = null;
        // تنظيف وتحليل النتيجة
        $scoreStr = trim(preg_replace('/[^\d\-\–\—]/u', ' ', $scoreStr));
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

        // التاريخ (نستخدم تاريخ اليوم الذي سحبناه)
        // تحويل التاريخ لصيغة قاعدة البيانات Y-m-d
        $matchDate = date('Y-m-d', strtotime($date));

        // التحقق من وجود المباراة
        $stmt = $pdo->prepare("SELECT id, lineup_home FROM matches WHERE match_date = ? AND team_home = ? AND team_away = ?");
        $stmt->execute([$matchDate, $teamHome, $teamAway]);
        $existing = $stmt->fetch();

        // تجهيز متغيرات التشكيلة
        $lineupHome = null;
        $lineupAway = null;
        $coachHome = null;
        $coachAway = null;
        $streamUrl = null;
        $matchEvents = null;

        // جلب التشكيلة فقط إذا كانت المباراة موجودة ولكن ليس لها تشكيلة، أو إذا كانت جديدة
        // تفعيل السحب التلقائي للتشكيلة والإحصائيات إذا كانت ناقصة
        // تم التعديل لسحب الأحداث فقط
        $shouldFetchLineup = $fetch_details && $sourceUrl && (!$existing || empty($existing['match_events']));
        
        if ($shouldFetchLineup) {
            $details = get_match_details($sourceUrl);
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
            usleep(200000); // انتظار بسيط لتجنب الحظر
        }

        if ($existing) {
            if ($scoreHome !== null) {
                $update = $pdo->prepare("UPDATE matches SET score_home = ?, score_away = ?, team_home_logo = ?, team_away_logo = ?, match_time = ?, channel = ?, championship = ?, championship_logo = ?, source_url = ? WHERE id = ?");
                $update->execute([$scoreHome, $scoreAway, $homeLogo, $awayLogo, $matchTime, $channel, $championship, $leagueLogo, $sourceUrl, $existing['id']]);
            } else {
                $update = $pdo->prepare("UPDATE matches SET team_home_logo = ?, team_away_logo = ?, match_time = ?, channel = ?, championship = ?, championship_logo = ?, source_url = ? WHERE id = ?");
                $update->execute([$homeLogo, $awayLogo, $matchTime, $channel, $championship, $leagueLogo, $sourceUrl, $existing['id']]);
            }
            
            // تحديث التشكيلة إذا تم جلبها
            if ($lineupHome || $lineupAway || $coachHome || $coachAway || $matchStats || $matchEvents) {
                $pdo->prepare("UPDATE matches SET lineup_home = COALESCE(?, lineup_home), lineup_away = COALESCE(?, lineup_away), coach_home = COALESCE(?, coach_home), coach_away = COALESCE(?, coach_away), match_stats = COALESCE(?, match_stats), match_events = COALESCE(?, match_events) WHERE id = ?")->execute([$lineupHome, $lineupAway, $coachHome, $coachAway, $matchStats, $matchEvents, $existing['id']]);
            }
            
            // تحديث رابط البث إذا تم جلبه
            if ($streamUrl) {
                $pdo->prepare("UPDATE matches SET stream_url = ? WHERE id = ?")->execute([$streamUrl, $existing['id']]);
            }
            
            $updated++;
        } else {
            $insert = $pdo->prepare("INSERT INTO matches (match_date, match_time, team_home, team_away, score_home, score_away, championship, team_home_logo, team_away_logo, channel, championship_logo, lineup_home, lineup_away, coach_home, coach_away, stream_url, source_url, match_stats, match_events) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $insert->execute([$matchDate, $matchTime, $teamHome, $teamAway, $scoreHome, $scoreAway, $championship, $homeLogo, $awayLogo, $channel, $leagueLogo, $lineupHome, $lineupAway, $coachHome, $coachAway, $streamUrl, $sourceUrl, $matchStats, $matchEvents]);
            $count++;
        }
    }
}

// سحب الأخبار بعد الانتهاء من المباريات
scrape_yallakora_news($pdo);

echo "تم الانتهاء!\n";
echo "تمت إضافة: $count مباراة.\n";
echo "تم تحديث: $updated مباراة.\n";
echo '<br><br><a href="bot_dashboard.php" style="padding:10px; background:#2563eb; color:white; text-decoration:none; border-radius:5px;">العودة للوحة التحكم</a>';
?>