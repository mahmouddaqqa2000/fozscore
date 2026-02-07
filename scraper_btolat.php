<?php
require_once __DIR__ . '/db.php';
header('Content-Type: text/html; charset=utf-8');
set_time_limit(0);

echo '<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700&display=swap" rel="stylesheet">';
echo '<body style="font-family: \'Tajawal\', sans-serif; direction: rtl; text-align: center; background: #f8fafc; color: #1e293b; padding: 20px;">';
echo "<h3>جاري سحب جدول المباريات من Btolat.com...</h3>";

$url = "https://www.btolat.com/matches-center";
$today = date('Y-m-d');

// 1. جلب محتوى الصفحة
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$html = curl_exec($ch);
$error = curl_error($ch);
curl_close($ch);

if (!$html) {
    die("<div style='color:red; background: #fee2e2; padding: 10px; border-radius: 8px;'>فشل الاتصال بالموقع: $error</div>");
}

// 2. تحليل HTML
$dom = new DOMDocument();
@$dom->loadHTML($html);
$xpath = new DOMXPath($dom);

// البحث عن كروت المباريات
$match_cards = $xpath->query("//div[contains(@class, 'matchCard')]");

if ($match_cards->length === 0) {
    echo "<div style='color:orange; margin: 10px 0;'>لم يتم العثور على مباريات باستخدام المحددات الافتراضية. جاري محاولة البحث العام...</div>";
    $match_cards = $xpath->query("//div[contains(@class, 'match')]");
}

echo "<p>تم العثور على " . $match_cards->length . " مباراة.</p>";

$count_added = 0;
$count_updated = 0;

// تحضير الاستعلامات
$stmtCheck = $pdo->prepare("SELECT id FROM matches WHERE team_home = ? AND team_away = ? AND match_date = ?");
$stmtInsert = $pdo->prepare("INSERT INTO matches (match_date, match_time, team_home, team_away, score_home, score_away, championship) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmtUpdate = $pdo->prepare("UPDATE matches SET score_home = ?, score_away = ?, match_time = ? WHERE id = ?");

foreach ($match_cards as $card) {
    // استخراج البيانات
    $home_node = $xpath->query(".//div[contains(@class, 'teamA')] | .//div[contains(@class, 'home')]", $card)->item(0);
    $team_home = $home_node ? trim($home_node->textContent) : null;

    $away_node = $xpath->query(".//div[contains(@class, 'teamB')] | .//div[contains(@class, 'away')]", $card)->item(0);
    $team_away = $away_node ? trim($away_node->textContent) : null;

    $score_node = $xpath->query(".//div[contains(@class, 'result')] | .//div[contains(@class, 'score')]", $card)->item(0);
    $score_text = $score_node ? trim($score_node->textContent) : '';

    $time_node = $xpath->query(".//div[contains(@class, 'time')] | .//span[contains(@class, 'matchDate')]", $card)->item(0);
    $match_time = $time_node ? trim($time_node->textContent) : '';

    $championship = "مباريات اليوم"; // افتراضي

    if ($team_home && $team_away) {
        $team_home = clean_text($team_home);
        $team_away = clean_text($team_away);
        $match_time = clean_text($match_time);

        $score_home = null;
        $score_away = null;

        if (strpos($score_text, '-') !== false) {
            $parts = explode('-', $score_text);
            $score_home = (int) trim($parts[0]);
            $score_away = (int) trim($parts[1]);
        }

        $stmtCheck->execute([$team_home, $team_away, $today]);
        $existing = $stmtCheck->fetch();

        if ($existing) {
            $stmtUpdate->execute([$score_home, $score_away, $match_time, $existing['id']]);
            $count_updated++;
        } else {
            $stmtInsert->execute([$today, $match_time, $team_home, $team_away, $score_home, $score_away, $championship]);
            $count_added++;
        }
    }
}

echo "<div style='margin-top: 20px; padding: 15px; background: #dcfce7; border-radius: 8px; color: #166534;'>";
echo "<strong>تمت العملية بنجاح!</strong><br>تم إضافة: $count_added | تم تحديث: $count_updated";
echo "</div>";
echo '<br><br><a href="bot_dashboard.php" style="display: inline-block; padding: 10px 20px; background: #2563eb; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;">العودة للوحة التحكم</a>';

function clean_text($str) {
    return trim(preg_replace('/\s+/', ' ', $str));
}
?>