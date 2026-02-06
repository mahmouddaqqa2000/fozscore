<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

$settings = get_site_settings($pdo);
$token = $settings['telegram_bot_token'];
$favicon = $settings['favicon'];

$chats = [];
$error = '';

if ($token) {
    $url = "https://api.telegram.org/bot$token/getUpdates";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($httpCode === 200 && isset($data['ok']) && $data['ok']) {
        foreach ($data['result'] as $update) {
            $chat = null;
            if (isset($update['message']['chat'])) {
                $chat = $update['message']['chat'];
            } elseif (isset($update['my_chat_member']['chat'])) {
                $chat = $update['my_chat_member']['chat'];
            } elseif (isset($update['channel_post']['chat'])) {
                $chat = $update['channel_post']['chat'];
            }
            
            if ($chat) {
                // نستخدم ID كمفتاح لمنع التكرار
                $chats[$chat['id']] = [
                    'id' => $chat['id'],
                    'type' => $chat['type'],
                    'title' => $chat['title'] ?? $chat['first_name'] ?? 'غير معروف'
                ];
            }
        }
    } else {
        $error = "فشل الاتصال بـ API تيليجرام. تأكد من صحة التوكن في الإعدادات.<br>رد تيليجرام: " . htmlspecialchars($response);
    }
} else {
    $error = "يرجى حفظ توكن البوت في صفحة الإعدادات أولاً.";
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>جلب معرف المجموعة - FozScore</title>
    <?php if ($favicon): ?><link rel="icon" href="<?php echo htmlspecialchars($favicon); ?>"><?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Tajawal', sans-serif; background: #f8fafc; padding: 2rem; direction: rtl; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        h2 { margin-top: 0; color: #1e293b; }
        .chat-item { background: #f1f5f9; padding: 15px; border-radius: 8px; margin-bottom: 10px; border: 1px solid #e2e8f0; }
        .chat-title { font-weight: bold; font-size: 1.1rem; color: #2563eb; }
        .chat-id { font-family: monospace; background: #e2e8f0; padding: 2px 6px; border-radius: 4px; margin-top: 5px; display: inline-block; direction: ltr; }
        .alert { padding: 15px; background: #fee2e2; color: #991b1b; border-radius: 8px; margin-bottom: 20px; }
        .btn { display: inline-block; padding: 10px 20px; background: #2563eb; color: white; text-decoration: none; border-radius: 6px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>🤖 المجموعات المكتشفة</h2>
        <p style="color: #64748b; font-size: 0.9rem;">ملاحظة: لكي تظهر المجموعة هنا، يجب أن يكون البوت مضافاً إليها، ويجب إرسال رسالة جديدة فيها.</p>
        
        <?php if ($error): ?>
            <div class="alert"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (empty($chats) && empty($error)): ?>
            <div style="text-align: center; padding: 20px; color: #64748b;">لم يتم العثور على مجموعات حديثة.<br>أرسل رسالة في مجموعتك ثم قم بتحديث هذه الصفحة.</div>
        <?php else: ?>
            <?php foreach ($chats as $chat): ?>
                <div class="chat-item">
                    <div class="chat-title"><?php echo htmlspecialchars($chat['title']); ?> (<?php echo htmlspecialchars($chat['type']); ?>)</div>
                    <div class="chat-id"><?php echo $chat['id']; ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <a href="bot_dashboard.php" class="btn">العودة للوحة التحكم</a>
    </div>
</body>
</html>