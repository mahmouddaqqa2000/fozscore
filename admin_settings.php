<?php
require_once __DIR__ . '/auth_check.php'; // حماية الصفحة بكلمة مرور

$configFile = __DIR__ . '/config.php';
$config = require $configFile;
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_username = trim($_POST['new_username'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // التحقق من كلمة المرور الحالية
    if ($current_password !== $config['admin_pass']) {
        $error_message = 'كلمة المرور الحالية غير صحيحة.';
    } elseif (empty($new_username) || empty($new_password)) {
        $error_message = 'يجب ملء اسم المستخدم وكلمة المرور الجديدة.';
    } elseif ($new_password !== $confirm_password) {
        $error_message = 'كلمتا المرور الجديدتان غير متطابقتين.';
    } else {
        // بناء محتوى ملف الإعدادات الجديد
        $new_config_content = "<?php\n";
        $new_config_content .= "// config.php - إعدادات لوحة التحكم\n";
        $new_config_content .= "return [\n";
        $new_config_content .= "    'admin_user' => '" . addslashes($new_username) . "',\n";
        $new_config_content .= "    'admin_pass' => '" . addslashes($new_password) . "'\n";
        $new_config_content .= "];\n";

        // كتابة الملف
        if (is_writable($configFile) && file_put_contents($configFile, $new_config_content) !== false) {
            $success_message = 'تم تحديث اسم المستخدم وكلمة المرور بنجاح. سيتم تسجيل خروجك الآن لتسجيل الدخول بالبيانات الجديدة.';
            // تسجيل الخروج بعد 3 ثوانٍ
            header('Refresh: 3; url=logout.php');
        } else {
            $error_message = 'فشل تحديث ملف الإعدادات. تأكد من أن السيرفر لديه صلاحيات الكتابة على الملف (config.php).';
        }
    }
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>إعدادات الحساب - لوحة التحكم</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Tajawal', sans-serif; background-color: #f8fafc; margin: 0; }
        .container { max-width: 800px; margin: 2rem auto; padding: 2rem; background: #fff; border-radius: 16px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        h2 { color: #1e293b; margin-top: 0; margin-bottom: 1.5rem; }
        .message { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; }
        .success { background: #dcfce7; color: #166534; }
        .error { background: #fee2e2; color: #991b1b; }
        form { display: flex; flex-direction: column; gap: 1.5rem; }
        label { font-weight: 600; color: #475569; }
        input { padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 1rem; width: 95%; }
        button { background: #2563eb; color: #fff; padding: 12px 20px; border: none; border-radius: 8px; font-weight: 700; font-size: 1rem; cursor: pointer; transition: background 0.2s; align-self: flex-start; }
        .back-link { display: inline-block; margin-top: 1rem; color: #2563eb; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/admin_header.php'; ?>
    <div class="container">
        <h2>تغيير اسم المستخدم وكلمة المرور</h2>
        <?php if ($success_message): ?>
            <div class="message success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="message error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <?php if (empty($success_message)): ?>
        <form method="POST" action="admin_settings.php">
            <div>
                <label for="current_password">كلمة المرور الحالية</label>
                <input type="password" id="current_password" name="current_password" required>
            </div>
            <hr style="border:none; border-top:1px solid #e2e8f0;">
            <div>
                <label for="new_username">اسم المستخدم الجديد</label>
                <input type="text" id="new_username" name="new_username" value="<?php echo htmlspecialchars($config['admin_user']); ?>" required>
            </div>
            <div>
                <label for="new_password">كلمة المرور الجديدة</label>
                <input type="password" id="new_password" name="new_password" required>
            </div>
            <div>
                <label for="confirm_password">تأكيد كلمة المرور الجديدة</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit">تحديث البيانات</button>
        </form>
        <?php endif; ?>
        <a href="bot_dashboard.php" class="back-link">&larr; العودة إلى لوحة التحكم</a>
    </div>
</body>
</html>