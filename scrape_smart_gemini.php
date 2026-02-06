<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
header('Content-Type: text/html; charset=utf-8');
set_time_limit(0);

$url = $_GET['url'] ?? '';

echo '<html lang="ar" dir="rtl"><head><meta charset="utf-8"><title>السحب الذكي - Gemini</title>';
echo '<style>body{font-family:sans-serif;padding:20px;background:#f1f5f9;color:#333} .card{background:white;padding:20px;border-radius:10px;box-shadow:0 2px 5px rgba(0,0,0,0.05);margin-bottom:20px;} pre{direction:ltr;background:#eee;padding:10px;overflow:auto;}</style></head><body>';

if (empty($url)) {
    die('<div class="card">يرجى إدخال رابط المباراة.<br><a href="bot_dashboard.php">العودة</a></div>');
}

echo "<div class='card'>";
echo "<h3>🤖 جاري التحليل الذكي عبر Gemini AI...</h3>";
echo "<p>الرابط: <a href='$url' target='_blank'>$url</a></p>";

// 1. جلب محتوى الصفحة باستخدام Puppeteer (لضمان تحميل المواقع الديناميكية)
echo "جاري جلب محتوى الصفحة...<br>";
$nodeScript = __DIR__ . '/scraper_lineup.js';
$cmd = "node " . escapeshellarg($nodeScript) . " " . escapeshellarg($url);
$output = shell_exec($cmd);

// محاولة استخراج HTML من مخرجات JSON
$jsonResult = json_decode($output, true);
$html = $jsonResult['html'] ?? $output;

if (!$html || strlen($html) < 100) {
    die("<span style='color:red'>فشل في جلب محتوى الصفحة. تأكد من صحة الرابط.</span></div>");
}

// 2. تنظيف HTML لتقليل حجم النص المرسل لـ Gemini
$dom = new DOMDocument();
libxml_use_internal_errors(true);
@$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
libxml_clear_errors();
$xpath = new DOMXPath($dom);

// إزالة العناصر غير الضرورية
foreach ($xpath->query('//script|//style|//svg|//path|//noscript|//footer|//nav') as $node) {
    $node->parentNode->removeChild($node);
}

$cleanText = $dom->textContent;
// إزالة المسافات الزائدة
$cleanText = preg_replace('/\s+/', ' ', $cleanText);
// أخذ جزء من النص لتجنب تجاوز حدود التوكن (حوالي 30-40 ألف حرف)
$cleanText = substr($cleanText, 0, 40000);

echo "تم تجهيز النص (" . strlen($cleanText) . " حرف). جاري الإرسال لـ Gemini...<br>";

// 3. إعداد البرومبت (Prompt)
$prompt = "
You are a professional football data scraper. Analyze the provided text from a football match webpage.
Extract the following details into a valid JSON object. Translate team names and league names to Arabic if they are in English.

JSON Structure:
{
    \"team_home\": \"Name of home team (Arabic)\",
    \"team_away\": \"Name of away team (Arabic)\",
    \"score_home\": \"Home score (integer or null if not started)\",
    \"score_away\": \"Away score (integer or null if not started)\",
    \"match_time\": \"Match time (e.g. 20:00)\",
    \"championship\": \"League/Championship name (Arabic)\",
    \"status\": \"Status (Live, Finished, Not Started)\",
    \"lineup_home\": [\"Player 1\", \"Player 2\", ...],
    \"lineup_away\": [\"Player 1\", \"Player 2\", ...],
    \"coach_home\": \"Home Coach Name (optional)\",
    \"coach_away\": \"Away Coach Name (optional)\"
}

If specific data is missing, use null. Return ONLY the JSON object, no markdown formatting.
";

// 4. استدعاء Gemini
$response = ask_gemini_json($prompt, $cleanText);

if ($response) {
    // تنظيف الرد في حال احتوى على markdown code blocks (```json ... ```)
    $response = preg_replace('/^```json\s*|\s*```$/s', '', $response);
    $data = json_decode($response, true);
} else {
    $data = null;
}

if ($data) {
    echo "<div style='background:#dcfce7;color:#166534;padding:10px;border-radius:5px;margin-top:10px;'>✅ تم استخراج البيانات بنجاح!</div>";
    
    // عرض البيانات المستخرجة
    echo "<table border='1' style='width:100%;border-collapse:collapse;margin-top:10px;'>";
    foreach ($data as $key => $val) {
        echo "<tr><td style='padding:5px;background:#f8f9fa;'>$key</td><td style='padding:5px;'>";
        if (is_array($val)) echo implode(", ", $val);
        else echo htmlspecialchars((string)$val);
        echo "</td></tr>";
    }
    echo "</table>";

    // 5. حفظ البيانات في قاعدة البيانات
    $teamHome = $data['team_home'] ?? null;
    $teamAway = $data['team_away'] ?? null;

    if ($teamHome && $teamAway) {
        // البحث عن المباراة (بحث مرن)
        $stmt = $pdo->prepare("SELECT * FROM matches WHERE (team_home LIKE ? OR team_away LIKE ?) AND (team_home LIKE ? OR team_away LIKE ?) ORDER BY id DESC LIMIT 1");
        $term1 = '%' . $teamHome . '%';
        $term2 = '%' . $teamAway . '%';
        $stmt->execute([$term1, $term1, $term2, $term2]);
        $match = $stmt->fetch(PDO::FETCH_ASSOC);

        $lineupHomeStr = !empty($data['lineup_home']) ? implode("\n", $data['lineup_home']) : null;
        $lineupAwayStr = !empty($data['lineup_away']) ? implode("\n", $data['lineup_away']) : null;
        $scoreHome = isset($data['score_home']) ? (int)$data['score_home'] : null;
        $scoreAway = isset($data['score_away']) ? (int)$data['score_away'] : null;
        $championship = $data['championship'] ?? null;
        $matchTime = $data['match_time'] ?? null;

        if ($match) {
            echo "<hr><h4>تم العثور على المباراة في قاعدة البيانات (ID: {$match['id']}). جاري التحديث...</h4>";
            
            $sql = "UPDATE matches SET source_url = ?";
            $params = [$url];

            if ($lineupHomeStr) { $sql .= ", lineup_home = ?"; $params[] = $lineupHomeStr; }
            if ($lineupAwayStr) { $sql .= ", lineup_away = ?"; $params[] = $lineupAwayStr; }
            if ($scoreHome !== null) { $sql .= ", score_home = ?"; $params[] = $scoreHome; }
            if ($scoreAway !== null) { $sql .= ", score_away = ?"; $params[] = $scoreAway; }
            if ($championship) { $sql .= ", championship = ?"; $params[] = $championship; }
            
            $sql .= " WHERE id = ?";
            $params[] = $match['id'];

            $stmtUpdate = $pdo->prepare($sql);
            $stmtUpdate->execute($params);
            echo "<span style='color:green;font-weight:bold;'>✔ تم تحديث بيانات المباراة بنجاح.</span>";
        } else {
            echo "<hr><h4>المباراة غير موجودة. جاري إضافتها كجديدة...</h4>";
            $today = date('Y-m-d');
            $stmtInsert = $pdo->prepare("INSERT INTO matches (match_date, match_time, team_home, team_away, score_home, score_away, championship, lineup_home, lineup_away, source_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtInsert->execute([
                $today, 
                $matchTime ?? '00:00', 
                $teamHome, 
                $teamAway, 
                $scoreHome, 
                $scoreAway, 
                $championship ?? 'مباريات متنوعة',
                $lineupHomeStr,
                $lineupAwayStr,
                $url
            ]);
            echo "<span style='color:green;font-weight:bold;'>✔ تم إضافة المباراة الجديدة بنجاح.</span>";
        }
    }
} else {
    echo "<div style='color:red;margin-top:10px;'>❌ لم يتمكن Gemini من استخراج بيانات صالحة. حاول برابط آخر.</div>";
    if (!$response) {
        echo "<div style='color:gray;font-size:0.8em;margin-top:5px;'>لم يتم استلام رد من API. قد يكون النص طويلاً جداً أو المفتاح غير صالح.</div>";
    } else {
        echo "<div style='color:gray;font-size:0.8em;margin-top:5px;'>الرد الخام غير صالح كـ JSON.</div>";
    }
}

echo "</div><br><a href='bot_dashboard.php' style='padding:10px;background:#2563eb;color:white;text-decoration:none;border-radius:5px;'>العودة للوحة التحكم</a></body></html>";
?>