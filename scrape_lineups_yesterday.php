<?php
require_once __DIR__ . '/db.php';
header('Content-Type: text/html; charset=utf-8');
set_time_limit(0);

echo "<h3>بدء عملية سحب إحصائيات مباريات الأمس...</h3>";

if (isset($_GET['date'])) {
    $target_date = $_GET['date'];
} else {
    $target_date = date('Y-m-d', strtotime('-1 day'));
}

// جلب مباريات الأمس التي لها رابط مصدر ولكن لا تملك إحصائيات بعد
$stmt = $pdo->prepare("SELECT * FROM matches WHERE match_date = ? AND source_url IS NOT NULL AND (match_stats IS NULL OR match_stats = '')");
$stmt->execute([$target_date]);
$matches_to_check = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($matches_to_check)) {
    echo "لا توجد مباريات للأمس بحاجة لتحديث الإحصائيات (إما أنها محدثة أو لا يوجد رابط مصدر).<br>";
    echo '<br><a href="bot_dashboard.php">العودة</a>';
    exit;
}

echo "تم العثور على " . count($matches_to_check) . " مباراة للتحقق منها...<br><hr>";

$count_updated = 0;

foreach ($matches_to_check as $match) {
    echo "جاري فحص: <strong>" . htmlspecialchars($match['team_home']) . " ضد " . htmlspecialchars($match['team_away']) . "</strong>... ";
    
    $sourceUrl = $match['source_url'];
    
    $ch_details = curl_init($sourceUrl);
    curl_setopt($ch_details, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch_details, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36');
    curl_setopt($ch_details, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch_details, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch_details, CURLOPT_ENCODING, '');
    curl_setopt($ch_details, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch_details, CURLOPT_TIMEOUT, 20);
    $html_details = curl_exec($ch_details);
    $httpCode = curl_getinfo($ch_details, CURLINFO_HTTP_CODE);
    curl_close($ch_details);

    if ($html_details && $httpCode === 200) {
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
            $update = $pdo->prepare("UPDATE matches SET match_stats = ? WHERE id = ?");
            $update->execute([$matchStats, $match['id']]);
            echo "<span style='color:green;'>✔ تم سحب وتحديث الإحصائيات بنجاح!</span><br>";
            $count_updated++;
        } else {
            echo "<span style='color:orange;'>لم يتم العثور على إحصائيات في صفحة المصدر.</span><br>";
        }
    } else {
        echo "<span style='color:red;'>فشل في جلب صفحة تفاصيل المباراة (Code: $httpCode).</span><br>";
    }
    
    usleep(200000); // انتظار 0.2 ثانية بين كل طلب
}

echo "<hr>تم الانتهاء. تم تحديث إحصائيات <strong>$count_updated</strong> مباراة.<br>";
echo '<br><a href="bot_dashboard.php" style="padding:10px; background:#2563eb; color:white; text-decoration:none; border-radius:5px;">العودة للوحة التحكم</a>';

?>