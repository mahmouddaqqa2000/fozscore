<?php
// scraper_koraarena.php - تجربة سحب مباريات الأمس من Kora Arena

require_once __DIR__ . '/db.php';
header('Content-Type: text/html; charset=utf-8');

// دالة لجلب الوقت الحقيقي (لتفادي مشكلة تاريخ السيرفر)
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

$yesterday_date = date('Y-m-d', strtotime('-1 day', $base_timestamp));

$url = "https://www.kora-arena.com/matches-yesterday/";

echo "<h3>تجربة سحب مباريات الأمس ($yesterday_date) من Kora Arena</h3>";
echo "جاري الاتصال بالموقع...<br>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36');
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$html = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
// curl_close($ch); 

if (!$html || $httpCode !== 200) {
    die("فشل الاتصال بالموقع. رمز الحالة: $httpCode<br>");
}

$dom = new DOMDocument();
libxml_use_internal_errors(true);
$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
libxml_clear_errors();

$xpath = new DOMXPath($dom);

// محاولة العثور على حاوية المباراة (قد تختلف الكلاسات، نستخدم الشائع)
$queries = [
    "//div[contains(@class, 'match-container')]",
    "//div[contains(@class, 'albaflex')]",
    "//div[contains(@class, 'match-card')]",
    "//div[contains(@class, 'item')]",
    "//div[contains(@id, 'matches_day')]//div"
];

$matches = null;
foreach ($queries as $query) {
    $result = $xpath->query($query);
    if ($result->length > 0) {
        $matches = $result;
        echo "تم العثور على مباريات باستخدام: " . htmlspecialchars($query) . "<br>";
        
        // إذا وجدنا عنصراً واحداً فقط وكان هو الحاوية الكبيرة (albaflex)، نبحث بداخله
        if ($result->length === 1 && strpos($query, 'albaflex') !== false) {
             $matches = $xpath->query(".//div[contains(@class, 'match-container')]", $result->item(0));
             echo "تم العثور على " . $matches->length . " مباراة داخل الحاوية.<br>";
        }
        break;
    }
}

if (!$matches || $matches->length === 0) {
    // طباعة جزء من الصفحة للمساعدة في التشخيص
    echo "<div style='background:#f8d7da;padding:10px;margin:10px 0;border-radius:5px;direction:ltr;text-align:left;font-size:12px;color:#721c24;'>";
    echo "<strong>Debug (HTML Preview):</strong><br>" . htmlspecialchars(substr($html, 0, 800)) . "...";
    echo "</div>";
    die("<span style='color:red'>فشل: لم يتم العثور على أي مباريات في الصفحة. قد تكون هيكلية الموقع مختلفة.</span><br>");
}

echo "تم العثور على " . $matches->length . " عنصر مباراة.<br><hr>";

$count_updated = 0;

foreach ($matches as $matchNode) {
    // استخراج الأسماء والنتيجة
    // محاولة استخراج الأسماء من عدة مسارات محتملة
    $teamHome = trim($xpath->query(".//div[contains(@class, 'right-team')]//div[contains(@class, 'team-name')]", $matchNode)->item(0)->textContent ?? 
                     $xpath->query(".//div[contains(@class, 'team-right')]", $matchNode)->item(0)->textContent ?? '');
                     
    $teamAway = trim($xpath->query(".//div[contains(@class, 'left-team')]//div[contains(@class, 'team-name')]", $matchNode)->item(0)->textContent ?? 
                     $xpath->query(".//div[contains(@class, 'team-left')]", $matchNode)->item(0)->textContent ?? '');
                     
    $scoreStr = trim($xpath->query(".//div[contains(@class, 'match-result')]", $matchNode)->item(0)->textContent ?? 
                     $xpath->query(".//div[contains(@class, 'result')]", $matchNode)->item(0)->textContent ?? '');
    
    if (empty($teamHome) || empty($teamAway)) continue;

    echo "<strong>$teamHome</strong> ضد <strong>$teamAway</strong> | النص المسحوب للنتيجة: [$scoreStr] ";

    // تحليل النتيجة
    $scoreHome = null;
    $scoreAway = null;
    $scoreStrClean = trim(preg_replace('/[^\d\-\–\—]/u', ' ', $scoreStr));
    
    if (!empty($scoreStrClean)) {
        if (preg_match_all('/\d+/', $scoreStrClean, $m)) {
            if (count($m[0]) >= 2) {
                $scoreHome = (int)$m[0][0];
                $scoreAway = (int)$m[0][1];
            }
        }
    }

    if ($scoreHome !== null) {
        echo "-> <span style='color:blue'>تم التعرف على النتيجة: $scoreHome - $scoreAway</span>";
        
        // محاولة التحديث في قاعدة البيانات
        $stmt = $pdo->prepare("SELECT id FROM matches WHERE match_date = ? AND team_home = ? AND team_away = ?");
        $stmt->execute([$yesterday_date, $teamHome, $teamAway]);
        $existing = $stmt->fetch();

        if ($existing) {
            $update = $pdo->prepare("UPDATE matches SET score_home = ?, score_away = ? WHERE id = ?");
            $update->execute([$scoreHome, $scoreAway, $existing['id']]);
            $count_updated++;
            echo " -> <span style='color:green'>تم التحديث في الموقع ✅</span>";
        } else {
            echo " -> <span style='color:orange'>المباراة غير موجودة في قاعدة البيانات (اختلاف أسماء؟)</span>";
        }
    } else {
        echo " -> <span style='color:red'>لم يتم استخراج نتيجة صالحة</span>";
    }
    echo "<br>";
}

echo "<hr>تم الانتهاء. تم تحديث $count_updated مباراة.<br>";
echo '<br><a href="bot_dashboard.php" style="padding:10px; background:#2563eb; color:white; text-decoration:none; border-radius:5px;">العودة للوحة التحكم</a>';
?>