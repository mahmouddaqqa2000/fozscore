<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

$settings = get_site_settings($pdo);
$favicon = $settings['favicon'];
$token = $settings['telegram_bot_token'];
$chatId = $settings['telegram_chat_id'];
$site_url = $settings['site_url'];

$message = '';
$status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($token) || empty($chatId)) {
        $message = 'يرجى إعداد توكن البوت ومعرف المجموعة في صفحة الإعدادات أولاً.';
        $status = 'error';
    } else {
        $type = $_POST['type'] ?? 'generic';
        $test_msg = "";

        // بيانات وهمية للتجربة
        $teamHome = "ريال مدريد";
        $teamAway = "برشلونة";
        $championship = "الدوري الإسباني";
        $match_url = rtrim($site_url, '/') . '/index.php'; // رابط تجريبي

        if ($type === 'start') {
            $test_msg = "🔔 <b>بداية المباراة الآن (تجربة)</b>\n\n";
            $test_msg .= "⚽ $teamHome 🆚 $teamAway\n";
            $test_msg .= "🏆 <i>$championship</i>\n\n";
            $test_msg .= "<a href=\"$match_url\">تابع المباراة مباشرة</a>";
        } elseif ($type === 'goal') {
            $test_msg = "⚽ <b>تحديث مباشر (هدف!) (تجربة)</b>\n\n";
            $test_msg .= "$teamHome <b>1</b> - <b>0</b> $teamAway\n";
            $test_msg .= "🏆 <i>$championship</i>\n\n";
            $test_msg .= "<a href=\"$match_url\">عرض التفاصيل</a>";
        } elseif ($type === 'finish') {
            $test_msg = "🏁 <b>نهاية المباراة (تجربة)</b>\n\n";
            $test_msg .= "$teamHome <b>2</b> - <b>1</b> $teamAway\n";
            $test_msg .= "🏆 <i>$championship</i>\n\n";
            $test_msg .= "<a href=\"$match_url\">عرض التفاصيل والإحصائيات</a>";
        } else {
            $test_msg = "🔔 <b>رسالة تجريبية من FozScore</b>\n\nتم ربط البوت بنجاح! ✅\nالوقت: " . date('Y-m-d H:i:s');
        }
        
        $response = send_telegram_msg($pdo, $test_msg);
        $result = json_decode($response, true);
        
        if ($result && isset($result['ok']) && $result['ok']) {
            $message = 'تم إرسال الرسالة التجريبية بنجاح! تحقق من مجموعتك في تيليجرام.';
            $status = 'success';
        } else {
            $error_desc = $result['description'] ?? 'خطأ غير معروف';
            $message = 'فشل الإرسال. رد تيليجرام: ' . $error_desc;
            $status = 'error';
        }
    }
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>اختبار تيليجرام - FozScore</title>
    <?php if ($favicon): ?><link rel="icon" href="<?php echo htmlspecialchars($favicon); ?>"><?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Tajawal', sans-serif; background: #f8fafc; padding: 2rem; direction: rtl; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); text-align: center; }
        h2 { margin-top: 0; color: #1e293b; }
        .status-icon { font-size: 4rem; margin-bottom: 1rem; display: block; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: right; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #991b1b; }
        .btn { display: inline-block; padding: 10px 20px; background: #2563eb; color: white; text-decoration: none; border-radius: 6px; margin-top: 10px; border: none; cursor: pointer; font-size: 1rem; font-family: inherit; }
        .btn:hover { background: #1d4ed8; }
        .btn-secondary { background: #64748b; }
        .settings-info { background: #f1f5f9; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: right; font-size: 0.9rem; }
        .settings-info div { margin-bottom: 5px; }
        .settings-info strong { color: #334155; }
        .test-options { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>✈️ اختبار اتصال تيليجرام</h2>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $status; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="settings-info">
            <div><strong>حالة الإعدادات:</strong></div>
            <div>توكن البوت: <?php echo $token ? '<span style="color:green">موجود ✅</span>' : '<span style="color:red">مفقود ❌</span>'; ?></div>
            <div>معرف المجموعة: <?php echo $chatId ? '<span style="color:green">موجود ✅</span> (' . htmlspecialchars($chatId) . ')' : '<span style="color:red">مفقود ❌</span>'; ?></div>
        </div>

        <form method="post">
            <div class="test-options">
                <button type="submit" name="type" value="generic" class="btn" style="background:#64748b;">رسالة ربط عادية</button>
                <button type="submit" name="type" value="start" class="btn" style="background:#0ea5e9;">🔔 بداية مباراة</button>
                <button type="submit" name="type" value="goal" class="btn" style="background:#22c55e;">⚽ تسجيل هدف</button>
                <button type="submit" name="type" value="finish" class="btn" style="background:#ef4444;">🏁 نهاية مباراة</button>
            </div>
        </form>
        
        <br>
        <a href="bot_dashboard.php" class="btn btn-secondary">العودة للوحة التحكم</a>
        <a href="settings.php" class="btn btn-secondary">تعديل الإعدادات</a>
    </div>
</body>
</html>