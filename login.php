<?php
session_start();
$config = require __DIR__ . '/config.php';

// إذا كان المستخدم مسجلاً دخوله بالفعل، يتم توجيهه للوحة التحكم
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header('Location: bot_dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($username === $config['admin_user'] && $password === $config['admin_pass']) {
        $_SESSION['loggedin'] = true;
        $_SESSION['username'] = $username;
        header('Location: bot_dashboard.php');
        exit;
    } else {
        $error = 'اسم المستخدم أو كلمة المرور غير صحيحة.';
    }
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تسجيل الدخول - لوحة التحكم</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Tajawal', sans-serif; background-color: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .login-container { background: #fff; padding: 2.5rem; border-radius: 16px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); width: 100%; max-width: 400px; text-align: center; }
        h2 { color: #1e293b; margin-top: 0; margin-bottom: 1.5rem; }
        .error { background: #fee2e2; color: #b91c1c; padding: 10px; border-radius: 8px; margin-bottom: 1rem; }
        form { display: flex; flex-direction: column; gap: 1rem; }
        label { text-align: right; font-weight: 600; color: #475569; }
        input { padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 1rem; }
        input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.2); }
        button { background: #2563eb; color: #fff; padding: 12px; border: none; border-radius: 8px; font-weight: 700; font-size: 1rem; cursor: pointer; transition: background 0.2s; }
        button:hover { background: #1d4ed8; }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>لوحة تحكم FozScore</h2>
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST" action="login.php">
            <div>
                <label for="username">اسم المستخدم</label>
                <input type="text" id="username" name="username" required style="width: 95%;">
            </div>
            <div>
                <label for="password">كلمة المرور</label>
                <input type="password" id="password" name="password" required style="width: 95%;">
            </div>
            <button type="submit">تسجيل الدخول</button>
        </form>
    </div>
</body>
</html>