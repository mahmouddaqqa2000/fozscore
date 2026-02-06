<?php
require_once __DIR__ . '/db.php';
header('Content-Type: text/html; charset=utf-8');
set_time_limit(0);

$type = $_GET['type'] ?? 'full'; // 'full' or 'events'

echo "<h3>بدء عملية سحب " . ($type === 'events' ? "الأحداث فقط" : "الإحصائيات والتشكيلات") . " (أمس، اليوم، غداً)...</h3>";

// تحديد النطاق الزمني
$dates = [
    date('Y-m-d', strtotime('-1 day')),
    date('Y-m-d'),
    date('Y-m-d', strtotime('+1 day'))
];

$placeholders = implode(',', array_fill(0, count($dates), '?'));
// نبحث عن المباريات التي لها رابط مصدر ولكن تنقصها التشكيلة أو الإحصائيات
if ($type === 'events') {
    // في وضع الأحداث، نبحث عن المباريات التي تنقصها الأحداث
    $stmt = $pdo->prepare("SELECT * FROM matches WHERE match_date IN ($placeholders) AND source_url IS NOT NULL AND (match_events IS NULL OR match_events = '')");
} else {
    $stmt = $pdo->prepare("SELECT * FROM matches WHERE match_date IN ($placeholders) AND source_url IS NOT NULL AND (match_stats IS NULL OR match_stats = '' OR lineup_home IS NULL OR lineup_home = '')");
}
$stmt->execute($dates);
$matches_to_check = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($matches_to_check)) {
    echo "لا توجد مباريات بحاجة لتحديث الإحصائيات في الفترة المحددة.<br>";
    echo '<br><a href="bot_dashboard.php" style="padding:10px; background:#2563eb; color:white; text-decoration:none; border-radius:5px;">العودة للوحة التحكم</a>';
    exit;
}

echo "تم العثور على " . count($matches_to_check) . " مباراة للتحقق منها...<br><hr>";

$count_updated = 0;

foreach ($matches_to_check as $match) {
    echo "جاري فحص مباراة: <strong>" . htmlspecialchars($match['team_home']) . " ضد " . htmlspecialchars($match['team_away']) . "</strong> (" . $match['match_date'] . ")... ";

    $details = get_match_details($match['source_url'], $type === 'events' ? 'events_only' : 'full');
    
    $updated = false;
    $update_fields = [];
    $params = [];

    if ($type !== 'events' && !empty($details['home'])) {
        $update_fields[] = "lineup_home = ?";
        $params[] = $details['home'];
        $update_fields[] = "lineup_away = ?";
        $params[] = $details['away'];
        $update_fields[] = "coach_home = COALESCE(?, coach_home)";
        $params[] = $details['coach_home'];
        $update_fields[] = "coach_away = COALESCE(?, coach_away)";
        $params[] = $details['coach_away'];
        $updated = true;
        echo "<span style='color:blue; font-size:0.8em;'>[تشكيلة] </span>";
    }

    if ($type !== 'events' && !empty($details['stats'])) {
        $update_fields[] = "match_stats = ?";
        $params[] = $details['stats'];
        $updated = true;
        echo "<span style='color:purple; font-size:0.8em;'>[إحصائيات] </span>";
    }
    
    if (!empty($details['events'])) {
        $update_fields[] = "match_events = ?";
        $params[] = $details['events'];
        $updated = true;
        echo "<span style='color:orange; font-size:0.8em;'>[أحداث] </span>";
    }

    if ($updated) {
        $sql = "UPDATE matches SET " . implode(', ', $update_fields) . " WHERE id = ?";
        $params[] = $match['id'];
        
        try {
            $update = $pdo->prepare($sql);
            $update->execute($params);
            echo "<span style='color:green;'>✔ تم التحديث!</span>";
            $count_updated++;
        } catch (PDOException $e) {
            echo "<span style='color:red;'>خطأ: " . $e->getMessage() . "</span>";
        }
    } else {
        echo "<span style='color:gray;'>لا توجد بيانات جديدة.</span>";
    }
    echo "<br>";
    
    // إجبار المتصفح على عرض المخرجات فوراً
    if (function_exists('flush')) flush();
    if (function_exists('ob_flush') && ob_get_level() > 0) ob_flush();
    
    usleep(300000); // انتظار 0.3 ثانية
}

echo "<hr>تم الانتهاء. تم تحديث <strong>$count_updated</strong> مباراة.<br>";
echo '<br><a href="bot_dashboard.php" style="padding:10px; background:#2563eb; color:white; text-decoration:none; border-radius:5px;">العودة للوحة التحكم</a>';

// دالة مساعدة لجلب تفاصيل المباراة
function get_match_details($url, $mode = 'full') {
    // =================================================================
    // تم تعطيل هذه الميزة لأنها تتطلب Node.js وهو غير مدعوم على خطة الاستضافة الحالية
    // =================================================================
    return ['home' => null, 'away' => null, 'coach_home' => null, 'coach_away' => null, 'stats' => null, 'events' => null];

    $nodeScript = __DIR__ . '/scraper_lineup.js';
    $html = null;
    $extracted_events = [];

    if (file_exists($nodeScript)) {
        $cmd = "node " . escapeshellarg($nodeScript) . " " . escapeshellarg($url) . " " . escapeshellarg($mode) . " 2>&1";
        $output = shell_exec($cmd);
        
        // محاولة فك تشفير JSON الناتج من Node.js
        $json_output = json_decode($output, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($json_output['html'])) {
            $html = $json_output['html'];
            $extracted_events = $json_output['extracted_events'] ?? [];
        } else {
            // fallback للنسخ القديمة
            $html = $output;
        }
    }

    if (!$html || strlen($html) < 100 || stripos($html, '<html') === false) {
        return ['home' => null, 'away' => null, 'coach_home' => null, 'coach_away' => null, 'stats' => null, 'events' => null];
    }

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);

    $homePlayers = [];
    $awayPlayers = [];

    $extractPlayer = function($node, $xpath) {
        $nameNode = $xpath->query(".//p[contains(@class, 'playerName')]|.//span[contains(@class, 'name')]|.//p[not(contains(@class, 'number'))]", $node)->item(0);
        $name = trim($nameNode->textContent ?? '');
        $num = trim($xpath->query(".//p[contains(@class, 'number')]|.//span[contains(@class, 'number')]", $node)->item(0)->textContent ?? '');
        $img = $xpath->query(".//img", $node)->item(0)?->getAttribute('src');
        if ($name) {
            $playerStr = $name;
            if ($img) $playerStr .= " | " . $img;
            if ($num) $playerStr .= " | " . $num;
            return $playerStr;
        }
        return null;
    };

    // منطق YallaKora
    $queries = [
        ['//div[contains(@class, "formation")]//div[contains(@class, "teamA")]//*[contains(@class, "player")]', '//div[contains(@class, "formation")]//div[contains(@class, "teamB")]//*[contains(@class, "player")]'],
        ['//div[@id="squad"]//div[contains(@class, "teamA")]//div[contains(@class, "player")]', '//div[@id="squad"]//div[contains(@class, "teamB")]//div[contains(@class, "player")]'],
        ['//div[contains(@class, "teamA")]//div[contains(@class, "player")]', '//div[contains(@class, "teamB")]//div[contains(@class, "player")]']
    ];
    foreach ($queries as $q) {
        $homeNodes = $xpath->query($q[0]); $awayNodes = $xpath->query($q[1]);
        if ($homeNodes->length > 0) break;
    }
    foreach ($homeNodes as $node) { $p = $extractPlayer($node, $xpath); if ($p) $homePlayers[] = $p; }
    foreach ($awayNodes as $node) { $p = $extractPlayer($node, $xpath); if ($p) $awayPlayers[] = $p; }

    $coachHome = trim($xpath->query("//div[contains(@class, 'teamA')]//div[contains(@class, 'manager')]//p")->item(0)->textContent ?? '');
    $coachAway = trim($xpath->query("//div[contains(@class, 'teamB')]//div[contains(@class, 'manager')]//p")->item(0)->textContent ?? '');

    // استخراج الإحصائيات
    $stats = [];
    $statsNodes = $xpath->query("//div[contains(@class, 'statsDiv')]//ul//li");
    foreach ($statsNodes as $node) {
        $label = trim($xpath->query(".//div[contains(@class, 'desc')]", $node)->item(0)->textContent ?? '');
        $homeVal = trim($xpath->query(".//div[contains(@class, 'teamA')]", $node)->item(0)->textContent ?? '');
        $awayVal = trim($xpath->query(".//div[contains(@class, 'teamB')]", $node)->item(0)->textContent ?? '');
        if ($label && ($homeVal !== '' || $awayVal !== '')) $stats[] = ['label' => $label, 'home' => $homeVal, 'away' => $awayVal];
    }

    return ['home' => !empty($homePlayers) ? implode("\n", $homePlayers) : null, 'away' => !empty($awayPlayers) ? implode("\n", $awayPlayers) : null, 'coach_home' => $coachHome ?: null, 'coach_away' => $coachAway ?: null, 'stats' => !empty($stats) ? json_encode($stats, JSON_UNESCAPED_UNICODE) : null, 'events' => !empty($extracted_events) ? implode("\n", $extracted_events) : null];
}
?>