<?php
// scraper_fotmob.php - سحب المباريات من FotMob

require_once __DIR__ . '/db.php';
header('Content-Type: text/html; charset=utf-8');
set_time_limit(0);

// دالة لجلب الوقت الحقيقي من الإنترنت (Google) لتجاوز خطأ توقيت السيرفر
function get_network_time() {
    // محاولة 1: استخدام WorldTimeAPI (أكثر دقة)
    $ch = curl_init("http://worldtimeapi.org/api/timezone/Etc/UTC");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    $json = curl_exec($ch);
    $data = json_decode($json, true);
    if (isset($data['unixtime'])) {
        return $data['unixtime'];
    }

    // محاولة 2: استخدام Google Headers (HTTP)
    $ch = curl_init("http://www.google.com/");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_NOBODY, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    if ($response && preg_match('/^Date: (.+)$/mi', $response, $matches)) {
        return strtotime($matches[1]);
    }

    // محاولة 3: استخدام Google Headers (HTTPS)
    $ch = curl_init("https://www.google.com/");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_NOBODY, 1); // طلب الترويسة فقط
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    if ($response && preg_match('/^Date: (.+)$/mi', $response, $matches)) {
        return strtotime($matches[1]);
    }
    return time(); // العودة لتوقيت السيرفر في حال الفشل
}

$base_timestamp = get_network_time();

// إصلاح: إذا كان التاريخ المستجلب في المستقبل البعيد (أكثر من سنة)، نعود لتوقيت السيرفر المحلي
// إذا كان توقيت السيرفر أيضاً خطأ، نستخدم تاريخاً ثابتاً (مثلاً 2024) كحل أخير لتجنب 404
if (date('Y', $base_timestamp) > 2025) {
    $base_timestamp = time(); // محاولة العودة لتوقيت السيرفر
    if (date('Y', $base_timestamp) > 2025) {
        // إذا كان السيرفر أيضاً في المستقبل، نستخدم تاريخ اليوم "الافتراضي" لتجنب الخطأ
        // هذا مجرد إجراء احترازي، الأفضل ضبط وقت السيرفر
        $base_timestamp = strtotime('2024-01-01'); 
        echo "<div style='background:#fee2e2;color:#991b1b;padding:10px;margin-bottom:10px;'>⚠️ <strong>تنبيه:</strong> توقيت السيرفر غير صحيح (عام " . date('Y') . "). يرجى ضبط وقت السيرفر أو استخدام 'سحب تاريخ محدد'.</div>";
    }
}

// دعم التشغيل عبر سطر الأوامر (Cron Job)
if (php_sapi_name() === 'cli') {
    parse_str(implode('&', array_slice($argv, 1)), $_GET);
}

// إعدادات التاريخ
$mode = $_GET['mode'] ?? 'today';

if (isset($_GET['date'])) {
    $dateStr = date('Ymd', strtotime($_GET['date']));
} else {
    $offset = ($mode === 'yesterday') ? '-1 day' : (($mode === 'tomorrow') ? '+1 day' : 'now');
    $dateStr = date('Ymd', strtotime($offset, $base_timestamp));
}

$apiUrl = "https://www.fotmob.com/api/matches?date=$dateStr";

echo "جاري الاتصال بـ FotMob (التاريخ: $dateStr) باستخدام Puppeteer...\n";

// استخدام Puppeteer لجلب البيانات
$nodeScript = __DIR__ . '/scraper_fotmob_matches.js';
if (file_exists($nodeScript)) {
    $cmd = "node " . escapeshellarg($nodeScript) . " " . escapeshellarg($apiUrl) . " 2>&1";
    $response = shell_exec($cmd);
} else {
    die("خطأ: ملف Node.js غير موجود ($nodeScript)");
}

$data = json_decode($response, true);

if (!isset($data['leagues'])) {
    // طباعة جزء من الاستجابة للمساعدة في التشخيص
    die("لم يتم العثور على بيانات المباريات (JSON غير صالح).<br><strong>الرابط:</strong> $apiUrl<br><strong>المخرجات:</strong> " . htmlspecialchars(substr($response, 0, 1000)) . "...\n");
}

$count = 0;
$updated = 0;

// قائمة معرفات البطولات التي تريد جلبها فقط (IDs)
// يمكنك إضافة أو حذف المعرفات حسب رغبتك
$wanted_leagues = [
    47,  // الدوري الإنجليزي الممتاز
    87,  // الدوري الإسباني
    55,  // الدوري الإيطالي
    54,  // الدوري الألماني
    53,  // الدوري الفرنسي
    42,  // دوري أبطال أوروبا
    153, // دوري روشن السعودي
];

foreach ($data['leagues'] as $league) {
    // فلتر: تخطي البطولات التي ليست في القائمة
    if (!in_array($league['id'], $wanted_leagues)) {
        continue;
    }

    $championship = $league['name'] ?? 'مباريات متنوعة';
    $leagueLogo = "https://images.fotmob.com/image_resources/logo/leaguelogo/{$league['id']}.png";
    
    // تخطي البطولات غير المهمة إذا أردت (اختياري)
    // if ($league['isCup'] && ...) continue;

    foreach ($league['matches'] as $match) {
        $homeName = $match['home']['name'];
        $awayName = $match['away']['name'];
        $homeId = $match['home']['id'];
        $awayId = $match['away']['id'];
        $matchId = $match['id'];
        $sourceUrl = "https://www.fotmob.com/match/" . $matchId;
        
        // روابط الصور من FotMob
        $homeLogo = "https://images.fotmob.com/image_resources/logo/teamlogo/{$homeId}.png";
        $awayLogo = "https://images.fotmob.com/image_resources/logo/teamlogo/{$awayId}.png";

        // الوقت والتاريخ
        $utcTime = $match['status']['utcTime']; // 2023-10-27T19:00:00.000Z
        $dt = new DateTime($utcTime);
        $dt->setTimezone(new DateTimeZone('Asia/Riyadh')); // تحويل لتوقيت السعودية
        
        $matchDate = $dt->format('Y-m-d');
        $matchTime = $dt->format('H:i');

        // النتيجة والحالة
        $scoreHome = null;
        $scoreAway = null;
        
        // التحقق مما إذا كانت المباراة قد بدأت أو انتهت لجلب النتيجة
        if ($match['status']['started'] || $match['status']['finished']) {
            // FotMob يعطي النتيجة كسلسلة نصية "1 - 0"
            $scoreStr = $match['status']['scoreStr'] ?? '';
            $scores = explode(' - ', $scoreStr);
            if (count($scores) === 2) {
                $scoreHome = (int)$scores[0];
                $scoreAway = (int)$scores[1];
            }
        }

        // التحقق من وجود المباراة في قاعدة البيانات
        $stmt = $pdo->prepare("SELECT id FROM matches WHERE match_date = ? AND team_home = ? AND team_away = ?");
        $stmt->execute([$matchDate, $homeName, $awayName]);
        $existing = $stmt->fetch();

        if ($existing) {
            // تحديث النتيجة والصور إذا كانت موجودة
            $update = $pdo->prepare("UPDATE matches SET score_home = ?, score_away = ?, team_home_logo = ?, team_away_logo = ?, match_time = ?, championship = ?, championship_logo = ?, source_url = ? WHERE id = ?");
            $update->execute([$scoreHome, $scoreAway, $homeLogo, $awayLogo, $matchTime, $championship, $leagueLogo, $sourceUrl, $existing['id']]);
            $updated++;
        } else {
            // إدراج مباراة جديدة
            $insert = $pdo->prepare("INSERT INTO matches (match_date, match_time, team_home, team_away, score_home, score_away, championship, team_home_logo, team_away_logo, championship_logo, source_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $insert->execute([$matchDate, $matchTime, $homeName, $awayName, $scoreHome, $scoreAway, $championship, $homeLogo, $awayLogo, $leagueLogo, $sourceUrl]);
            $count++;
        }
    }
}

echo "تم الانتهاء!\n";
echo "تمت إضافة: $count مباراة.\n";
echo "تم تحديث: $updated مباراة.\n";
echo '<br><br><a href="bot_dashboard.php" style="padding:10px; background:#2563eb; color:white; text-decoration:none; border-radius:5px;">العودة للوحة التحكم</a>';
?>