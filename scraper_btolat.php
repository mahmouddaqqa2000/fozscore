<?php
require_once __DIR__ . '/db.php';
header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Africa/Cairo'); // ضبط التوقيت للقاهرة لضمان توافق التواريخ
set_time_limit(0);

echo '<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700&display=swap" rel="stylesheet">';
echo '<body style="font-family: \'Tajawal\', sans-serif; direction: rtl; text-align: center; background: #f8fafc; color: #1e293b; padding: 20px;">';
echo "<h3>جاري سحب جدول المباريات من Btolat.com...</h3>";

// دالة لجلب الوقت الحقيقي من الإنترنت لتجاوز خطأ توقيت السيرفر
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

// تحديد التواريخ: اليوم وغداً
$dates_to_scrape = [
    date('Y-m-d', $base_timestamp),
    date('Y-m-d', strtotime('+1 day', $base_timestamp))
];

// تحضير الاستعلامات
$stmtCheck = $pdo->prepare("SELECT id FROM matches WHERE team_home = ? AND team_away = ? AND match_date = ?");
$stmtInsert = $pdo->prepare("INSERT INTO matches (match_date, match_time, team_home, team_away, score_home, score_away, championship, team_home_logo, team_away_logo, championship_logo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmtUpdate = $pdo->prepare("UPDATE matches SET score_home = ?, score_away = ?, match_time = ?, championship = ?, team_home_logo = ?, team_away_logo = ?, championship_logo = ? WHERE id = ?");

$total_added = 0;
$total_updated = 0;
$first_failed_html = null;

foreach ($dates_to_scrape as $current_date) {
    echo "<hr><h4>جاري سحب مباريات تاريخ: $current_date</h4>";
    $url = "https://www.btolat.com/matches-center?date=" . $current_date;

    // 1. جلب محتوى الصفحة
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
        'Accept-Language: ar,en-US;q=0.9,en;q=0.8',
        'Cache-Control: no-cache',
        'Pragma: no-cache'
    ]);
    curl_setopt($ch, CURLOPT_ENCODING, ''); // مهم جداً لفك ضغط الاستجابة
    $html = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if (!$html) {
        echo "<div style='color:red; background: #fee2e2; padding: 10px; border-radius: 8px;'>فشل الاتصال بالموقع ($current_date): $error</div>";
        continue;
    }

    // 2. تحليل HTML
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    $xpath = new DOMXPath($dom);

    // البحث عن كروت المباريات
    $match_cards = $xpath->query("//li[contains(@class, 'fullMatchBox')] | //div[contains(@class, 'matchCard')] | //div[contains(@class, 'liItem')]");

    if ($match_cards->length === 0) {
        echo "<div style='color:orange; margin: 10px 0;'>لم يتم العثور على مباريات باستخدام المحددات الافتراضية. جاري محاولة البحث العام...</div>";
        $match_cards = $xpath->query("//li[contains(@class, 'match')] | //div[contains(@class, 'match')][not(contains(@class, 'matches'))]");
    }

    echo "<p>تم العثور على " . $match_cards->length . " مباراة.</p>";

    $count_added = 0;
    $count_updated = 0;

foreach ($match_cards as $card) {
    // استخراج البيانات - محاولات متعددة للأسماء
    // تم التحديث لدعم a.team1 و div.team1
    $home_queries = [".//*[contains(@class, 'team1')]//h3", ".//*[contains(@class, 'team1')]", ".//*[contains(@class, 'teamA')]", ".//*[contains(@class, 'home')]"];
    $team_home = null;
    foreach ($home_queries as $q) {
        $node = $xpath->query($q, $card)->item(0);
        if ($node) { $team_home = trim($node->textContent); break; }
    }

    $away_queries = [".//*[contains(@class, 'team2')]//h3", ".//*[contains(@class, 'team2')]", ".//*[contains(@class, 'teamB')]", ".//*[contains(@class, 'away')]"];
    $team_away = null;
    foreach ($away_queries as $q) {
        $node = $xpath->query($q, $card)->item(0);
        if ($node) { $team_away = trim($node->textContent); break; }
    }

    // استخراج الشعارات
    $team_home_logo = null;
    $home_img_node = $xpath->query(".//*[contains(@class, 'team1')]//img", $card)->item(0);
    if ($home_img_node) {
        $team_home_logo = $home_img_node->getAttribute('data-original') ?: $home_img_node->getAttribute('src');
    }

    $team_away_logo = null;
    $away_img_node = $xpath->query(".//*[contains(@class, 'team2')]//img", $card)->item(0);
    if ($away_img_node) {
        $team_away_logo = $away_img_node->getAttribute('data-original') ?: $away_img_node->getAttribute('src');
    }

    $time_node = $xpath->query(".//div[contains(@class, 'time')] | .//span[contains(@class, 'matchDate')] | .//div[contains(@class, 'date')]", $card)->item(0);
    $match_time = $time_node ? trim($time_node->textContent) : '';

    $championship = "مباريات اليوم"; // افتراضي
    $championship_logo = null;
    // محاولة العثور على اسم البطولة من العنوان السابق للكارت
    $prev = $card->parentNode->previousSibling;
    while ($prev && $prev->nodeType !== XML_ELEMENT_NODE) { $prev = $prev->previousSibling; }
    if ($prev) {
        $champNode = $xpath->query(".//h2 | .//div[contains(@class, 'legTitle')] | .//div[contains(@class, 'title')]", $prev)->item(0);
        if ($champNode) $championship = trim($champNode->textContent);
        
        $champLogoNode = $xpath->query(".//img", $prev)->item(0);
        if ($champLogoNode) $championship_logo = $champLogoNode->getAttribute('src');
    }

    if ($team_home && $team_away) {
        $team_home = clean_text($team_home);
        $team_away = clean_text($team_away);
        $match_time = clean_text($match_time);

        $score_home = null;
        $score_away = null;

        // محاولة استخراج النتيجة من team1G و team2G
        $s1_node = $xpath->query(".//div[contains(@class, 'team1G')]", $card)->item(0);
        $s2_node = $xpath->query(".//div[contains(@class, 'team2G')]", $card)->item(0);
        
        if ($s1_node && $s2_node) {
            $s1 = clean_text($s1_node->textContent);
            $s2 = clean_text($s2_node->textContent);
            if ($s1 !== '' && $s2 !== '') {
                $score_home = (int)$s1;
                $score_away = (int)$s2;
            }
        } else {
            // محاولة احتياطية
            $score_node = $xpath->query(".//div[contains(@class, 'result')] | .//div[contains(@class, 'score')]", $card)->item(0);
            $score_text = $score_node ? clean_text($score_node->textContent) : '';
            if (strpos($score_text, '-') !== false) {
                $parts = explode('-', $score_text);
                $score_home = (int) trim($parts[0]);
                $score_away = (int) trim($parts[1]);
            }
        }

        $stmtCheck->execute([$team_home, $team_away, $current_date]);
        $existing = $stmtCheck->fetch();

        if ($existing) {
            $stmtUpdate->execute([$score_home, $score_away, $match_time, $championship, $team_home_logo, $team_away_logo, $championship_logo, $existing['id']]);
            $count_updated++;
        } else {
            $stmtInsert->execute([$current_date, $match_time, $team_home, $team_away, $score_home, $score_away, $championship, $team_home_logo, $team_away_logo, $championship_logo]);
            $count_added++;
        }
    } else {
        if (!$first_failed_html) {
            $first_failed_html = $dom->saveHTML($card);
        }
    }
}

    $total_added += $count_added;
    $total_updated += $count_updated;
    
    // فاصل زمني بسيط
    usleep(500000);
}

echo "<div style='margin-top: 20px; padding: 15px; background: #dcfce7; border-radius: 8px; color: #166534;'>";
echo "<strong>تمت العملية بنجاح!</strong><br>الإجمالي: تم إضافة: $total_added | تم تحديث: $total_updated";
echo "</div>";

if ($first_failed_html) {
    echo "<div style='color:red; margin-top:10px; padding:10px; border:1px solid red; background:#fff0f0;'>⚠️ تنبيه: فشل استخراج البيانات من بعض العناصر. إليك عينة من الكود المصدري لأول عنصر فاشل:</div>";
    echo "<textarea style='width:100%;height:200px;direction:ltr;font-family:monospace;margin-top:10px;'>" . htmlspecialchars($first_failed_html) . "</textarea>";
}

echo '<br><br><a href="bot_dashboard.php" style="display: inline-block; padding: 10px 20px; background: #2563eb; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;">العودة للوحة التحكم</a>';

function clean_text($str) {
    return trim(preg_replace('/\s+/', ' ', $str));
}
?>