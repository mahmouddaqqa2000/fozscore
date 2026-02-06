<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php'; // For get_match_status
header('Content-Type: text/html; charset=utf-8');
set_time_limit(0);

echo "<h3>بدء عملية سحب تشكيلات مباريات اليوم الجارية...</h3>";

$today = date('Y-m-d');

// جلب مباريات اليوم التي لا تملك تشكيلة بعد
$stmt = $pdo->prepare("SELECT * FROM matches WHERE match_date = ? AND (lineup_home IS NULL OR lineup_home = '')");
$stmt->execute([$today]);
$matches_to_check = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($matches_to_check)) {
    echo "لا توجد مباريات اليوم بحاجة لتحديث التشكيلة.<br>";
    echo '<br><a href="bot_dashboard.php">العودة</a>';
    exit;
}

echo "تم العثور على " . count($matches_to_check) . " مباراة للتحقق منها...<br><hr>";

$count_updated = 0;

foreach ($matches_to_check as $match) {
    $status = get_match_status($match);
    
    // فقط تحقق من المباريات التي بدأت أو على وشك البدء
    if ($status['key'] === 'not_started') {
        // يمكن إضافة شرط للتحقق إذا كانت المباراة ستبدأ خلال ساعة مثلاً
        $match_datetime = new DateTime($match['match_date'] . ' ' . $match['match_time']);
        $now = new DateTime();
        $diff = $now->diff($match_datetime);
        $minutes_to_start = ($diff->h * 60) + $diff->i;
        if ($diff->invert === 0 && $minutes_to_start > 60) { // invert=0 means future, and more than 60 mins away
             echo "مباراة " . htmlspecialchars($match['team_home']) . " ضد " . htmlspecialchars($match['team_away']) . " لم تبدأ بعد (أكثر من ساعة).<br>";
             continue;
        }
    }

    echo "جاري فحص مباراة: <strong>" . htmlspecialchars($match['team_home']) . " ضد " . htmlspecialchars($match['team_away']) . "</strong>... ";

    if (empty($match['source_url'])) {
        echo "<span style='color:orange;'>خطأ: لا يوجد رابط مصدر للمباراة. يرجى تشغيل 'تحديث شامل' أولاً.</span><br>";
        continue;
    }

    echo "<br><small>الرابط: <a href='" . htmlspecialchars($match['source_url']) . "' target='_blank'>" . htmlspecialchars($match['source_url']) . "</a></small><br>";

    $details = get_match_details($match['source_url']);
    
    if (!empty($details['home'])) {
        $update = $pdo->prepare("UPDATE matches SET lineup_home = ?, lineup_away = ?, coach_home = COALESCE(?, coach_home), coach_away = COALESCE(?, coach_away) WHERE id = ?");
        $update->execute([$details['home'], $details['away'], $details['coach_home'], $details['coach_away'], $match['id']]);
        echo "<span style='color:green;'>✔ تم سحب وتحديث التشكيلة بنجاح!</span><br>";
        $count_updated++;
    } else {
        echo "<span style='color:red;'>لم يتم العثور على تشكيلة في المصدر بعد.</span><br>";
        if (!empty($details['html_preview'])) {
            echo "<textarea style='width:100%;height:100px;font-size:10px;direction:ltr;color:#333;margin-top:5px;'>" . htmlspecialchars($details['html_preview']) . "</textarea><br>";
        }
    }
    
    usleep(300000); // انتظار 0.3 ثانية بين كل طلب
}

echo "<hr>تم الانتهاء. تم تحديث تشكيلة <strong>$count_updated</strong> مباراة.<br>";
echo '<br><a href="bot_dashboard.php" style="padding:10px; background:#2563eb; color:white; text-decoration:none; border-radius:5px;">العودة للوحة التحكم</a>';


?>