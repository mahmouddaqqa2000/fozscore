<?php
// scraper_filgoal.php - سحب المباريات من FilGoal (بديل لـ YallaKora)

require_once __DIR__ . '/db.php';
header('Content-Type: text/html; charset=utf-8');
set_time_limit(0);

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

$mode = $_GET['mode'] ?? 'today';

// تحديد التواريخ بناءً على الوضع
$dates = [];
if ($mode === 'all') {
    $dates = [
        date('Y-m-d', strtotime('-1 day', $base_timestamp)),
        date('Y-m-d', $base_timestamp),
        date('Y-m-d', strtotime('+1 day', $base_timestamp))
    ];
    echo "<h3>بدء التحديث الشامل من FilGoal...</h3>";
} else {
    $offset = ($mode === 'yesterday') ? '-1 day' : (($mode === 'tomorrow') ? '+1 day' : 'now');
    $dates = [date('Y-m-d', strtotime($offset, $base_timestamp))];
    echo "<h3>سحب مباريات FilGoal لتاريخ: " . $dates[0] . "</h3>";
}

foreach ($dates as $dateStr) {
    echo "<hr>جاري معالجة تاريخ: <strong>$dateStr</strong><br>";
    
    // رابط FilGoal (نسخة الويب)
    $url = "https://www.filgoal.com/matches/?date=$dateStr";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
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
    curl_setopt($ch, CURLOPT_ENCODING, ''); // مهم جداً لفك ضغط الاستجابة
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // curl_close($ch); // Removed to avoid deprecated warning

    if (!$html || $httpCode !== 200) {
        echo "فشل الاتصال بالموقع. رمز الحالة: $httpCode<br>";
        continue;
    }

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);

    // البحث عن البطولات (FilGoal يضع كل بطولة في div.mc-block)
    $championships = $xpath->query("//div[contains(@class, 'mc-block')]");

    if ($championships->length === 0) {
        echo "لم يتم العثور على مباريات.<br>";
        continue;
    }

    $count_added = 0;
    $count_updated = 0;

    foreach ($championships as $champNode) {
        // اسم البطولة
        $championship = trim($xpath->query(".//div[contains(@class, 'mc-block-title')]//span", $champNode)->item(0)->textContent ?? 'مباريات متنوعة');
        
        // المباريات
        $matches = $xpath->query(".//div[contains(@class, 'mc-match')]", $champNode);

        foreach ($matches as $matchNode) {
            // الفرق
            $teamHome = trim($xpath->query(".//div[contains(@class, 'f-team')]", $matchNode)->item(0)->textContent ?? '');
            $teamAway = trim($xpath->query(".//div[contains(@class, 's-team')]", $matchNode)->item(0)->textContent ?? '');
            
            // النتيجة
            $scoreHome = null;
            $scoreAway = null;
            $scoreNode = $xpath->query(".//div[contains(@class, 'match-score')]//span[contains(@class, 'score')]", $matchNode);
            
            if ($scoreNode->length >= 2) {
                $scoreHome = (int)trim($scoreNode->item(0)->textContent);
                $scoreAway = (int)trim($scoreNode->item(1)->textContent);
            }

            // الوقت (FilGoal يعرض الوقت أو الحالة في match-status)
            // نحتاج لتنظيف الوقت لأنه قد يحتوي على "انتهت" أو "جارية"
            $statusText = trim($xpath->query(".//div[contains(@class, 'match-status')]//span", $matchNode)->item(0)->textContent ?? '');
            // إذا كان الوقت بصيغة HH:MM
            $matchTime = (preg_match('/\d{2}:\d{2}/', $statusText, $m)) ? $m[0] : '00:00';

            if (empty($teamHome) || empty($teamAway)) continue;

            // التحقق من وجود المباراة
            $stmt = $pdo->prepare("SELECT id FROM matches WHERE match_date = ? AND team_home = ? AND team_away = ?");
            $stmt->execute([$dateStr, $teamHome, $teamAway]);
            $existing = $stmt->fetch();

            if ($existing) {
                if ($scoreHome !== null) {
                    $update = $pdo->prepare("UPDATE matches SET score_home = ?, score_away = ?, championship = ? WHERE id = ?");
                    $update->execute([$scoreHome, $scoreAway, $championship, $existing['id']]);
                    echo "<span style='color:green'>✔ تحديث: $teamHome $scoreHome-$scoreAway $teamAway</span><br>";
                } else {
                    // تحديث البطولة فقط
                    $update = $pdo->prepare("UPDATE matches SET championship = ? WHERE id = ?");
                    $update->execute([$championship, $existing['id']]);
                }
                $count_updated++;
            } else {
                $insert = $pdo->prepare("INSERT INTO matches (match_date, match_time, team_home, team_away, score_home, score_away, championship) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $insert->execute([$dateStr, $matchTime, $teamHome, $teamAway, $scoreHome, $scoreAway, $championship]);
                echo "<span>➕ إضافة: $teamHome vs $teamAway</span><br>";
                $count_added++;
            }
        }
    }
    echo "<br>الخلاصة: تمت إضافة $count_added | تم تحديث $count_updated<br>";
    
    if ($mode === 'all') sleep(2);
}

echo "<hr><strong>تم الانتهاء من العملية.</strong>";
echo '<br><br><a href="bot_dashboard.php" style="padding:10px; background:#2563eb; color:white; text-decoration:none; border-radius:5px;">العودة للوحة التحكم</a>';
?>