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

$apiKey = $settings['twitter_api_key'];
$apiSecret = $settings['twitter_api_secret'];
$accessToken = $settings['twitter_access_token'];
$accessSecret = $settings['twitter_access_token_secret'];

$message = '';
$status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($apiKey) || empty($apiSecret) || empty($accessToken) || empty($accessSecret)) {
        $message = 'يرجى إعداد مفاتيح تويتر في صفحة الإعدادات أولاً.';
        $status = 'error';
    } else {
        $test_msg = "🔔 تجربة النشر التلقائي من FozScore\n\nالوقت: " . date('Y-m-d H:i:s');
        
        $response = send_twitter_tweet($pdo, $test_msg);
        $result = json_decode($response, true);
        
        if ($result && isset($result['data']['id'])) {
            $message = 'تم نشر التغريدة بنجاح! ID: ' . $result['data']['id'];
            $status = 'success';
        } else {
            $error_desc = isset($result['detail']) ? $result['detail'] : (isset($result['title']) ? $result['title'] : 'خطأ غير معروف');
            if (isset($result['errors'])) {
                $error_desc .= ' - ' . json_encode($result['errors'], JSON_UNESCAPED_UNICODE);
            }
            $message = 'فشل النشر. رد تويتر: ' . $error_desc;
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
    <title>اختبار تويتر - FozScore</title>
    <?php if ($favicon): ?><link rel="icon" href="<?php echo htmlspecialchars($favicon); ?>"><?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Tajawal', sans-serif; background: #f8fafc; padding: 2rem; direction: rtl; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); text-align: center; }
        h2 { margin-top: 0; color: #1e293b; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: right; word-break: break-word; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #991b1b; }
        .btn { display: inline-block; padding: 10px 20px; background: #1da1f2; color: white; text-decoration: none; border-radius: 6px; margin-top: 10px; border: none; cursor: pointer; font-size: 1rem; font-family: inherit; }
        .btn:hover { background: #0c85d0; }
        .btn-secondary { background: #64748b; }
        .settings-info { background: #f1f5f9; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: right; font-size: 0.9rem; }
        .settings-info div { margin-bottom: 5px; }
        .settings-info strong { color: #334155; }
    </style>
</head>
<body>
    <div class="container">
        <h2>🐦 اختبار نشر تويتر</h2>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $status; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="settings-info">
            <div><strong>حالة الإعدادات:</strong></div>
            <div>API Key: <?php echo $apiKey ? '<span style="color:green">موجود ✅</span>' : '<span style="color:red">مفقود ❌</span>'; ?></div>
            <div>API Secret: <?php echo $apiSecret ? '<span style="color:green">موجود ✅</span>' : '<span style="color:red">مفقود ❌</span>'; ?></div>
            <div>Access Token: <?php echo $accessToken ? '<span style="color:green">موجود ✅</span>' : '<span style="color:red">مفقود ❌</span>'; ?></div>
            <div>Access Secret: <?php echo $accessSecret ? '<span style="color:green">موجود ✅</span>' : '<span style="color:red">مفقود ❌</span>'; ?></div>
        </div>

        <form method="post">
            <button type="submit" class="btn">نشر تغريدة تجريبية الآن</button>
        </form>
        
        <br>
        <a href="bot_dashboard.php" class="btn btn-secondary">العودة للوحة التحكم</a>
        <a href="settings.php" class="btn btn-secondary">تعديل الإعدادات</a>
    </div>
</body>
</html>