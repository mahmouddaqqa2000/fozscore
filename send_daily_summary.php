<?php
require_once __DIR__ . '/db.php';
header('Content-Type: text/html; charset=utf-8');

// إعدادات تيليجرام
$botToken = '8042622774:AAHsri8itQqddhC_NeuP7EKBSoMcZYzIi64';
$chatId = '1783801547';

// ضبط التوقيت
date_default_timezone_set('Asia/Riyadh');
$today = date('Y-m-d');

// جلب مباريات اليوم
$stmt = $pdo->prepare("SELECT * FROM matches WHERE match_date = ? ORDER BY match_time ASC");
$stmt->execute([$today]);
$matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($matches)) {
    die("<div style='text-align:center; padding:20px; font-family:sans-serif;'>لا توجد مباريات مسجلة في قاعدة البيانات لهذا اليوم ($today).<br>يرجى سحب المباريات أولاً.<br><br><a href='bot_dashboard.php'>العودة</a></div>");
}

// بناء الرسالة
$message = "📅 *ملخص مباريات اليوم* (" . date('d/m/Y') . ")\n\n";

foreach ($matches as $match) {
    // تنسيق الوقت (تحويل 24 ساعة إلى 12 ساعة مع ص/م)
    $timeStr = $match['match_time'];
    $timeDisplay = $timeStr;
    try {
        $dt = new DateTime($timeStr);
        $timeDisplay = $dt->format('g:i');
        $ampm = $dt->format('A') === 'AM' ? 'ص' : 'م';
        $timeDisplay .= " " . $ampm;
    } catch (Exception $e) {}

    // إضافة النتيجة إذا كانت موجودة (للمباريات المنتهية أو الجارية)
    $scoreText = "";
    if (isset($match['score_home']) && $match['score_home'] !== '' && $match['score_home'] !== null) {
        $scoreText = " \n📊 *{$match['score_home']} - {$match['score_away']}*";
    }

    $message .= "⚽ *{$match['team_home']}* 🆚 *{$match['team_away']}*\n";
    $message .= "⏰ $timeDisplay";
    if (!empty($match['championship'])) {
        $message .= " 🏆 {$match['championship']}";
    }
    $message .= $scoreText;
    $message .= "\n➖➖➖➖➖➖➖➖\n";
}

$message .= "\n🤖 _مرسل من بوت FozScore_";

// إرسال الرسالة
$url = "https://api.telegram.org/bot$botToken/sendMessage";
$data = [
    'chat_id' => $chatId,
    'text' => $message,
    'parse_mode' => 'Markdown'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200) {
    echo "<div style='color:green; font-weight:bold; padding:20px; text-align:center; font-family:sans-serif; border:1px solid green; background:#f0fff0; border-radius:8px; margin:20px;'>✅ تم إرسال الملخص بنجاح إلى تيليجرام!</div>";
} else {
    echo "<div style='color:red; font-weight:bold; padding:20px; text-align:center; font-family:sans-serif; border:1px solid red; background:#fff0f0; border-radius:8px; margin:20px;'>❌ فشل الإرسال. رمز الخطأ: $httpCode<br>الرد: $response</div>";
}

echo '<div style="text-align:center;"><a href="bot_dashboard.php" style="padding:10px 20px; background:#2563eb; color:white; text-decoration:none; border-radius:5px; font-family:sans-serif;">العودة للوحة التحكم</a></div>';
?>