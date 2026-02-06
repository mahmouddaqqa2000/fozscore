<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

$settings = get_site_settings($pdo);
$favicon = $settings['favicon'];
$site_name = $settings['site_name'];

// إنشاء جدول الرسائل إذا لم يكن موجوداً
$pdo->exec("CREATE TABLE IF NOT EXISTS messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT,
    email TEXT,
    subject TEXT,
    message TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message_text = trim($_POST['message'] ?? '');

    if ($name && $email && $message_text) {
        $stmt = $pdo->prepare("INSERT INTO messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$name, $email, $subject, $message_text])) {
            $msg = '<div class="alert alert-success">تم إرسال رسالتك بنجاح! شكراً لتواصلك معنا.</div>';
        } else {
            $msg = '<div class="alert alert-danger">حدث خطأ أثناء الإرسال. حاول مرة أخرى.</div>';
        }
    } else {
        $msg = '<div class="alert alert-danger">يرجى ملء جميع الحقول المطلوبة.</div>';
    }
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>اتصل بنا - <?php echo htmlspecialchars($site_name); ?></title>
    <?php if ($favicon): ?><link rel="icon" href="<?php echo htmlspecialchars($favicon); ?>"><?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #1e293b; --secondary: #2563eb; --bg: #f8fafc; --card: #ffffff; --text: #0f172a; --border: #e2e8f0; }
        body { font-family: 'Tajawal', sans-serif; background:var(--bg); margin:0; color:var(--text); }
        .container { max-width:800px; margin:3rem auto; padding:0 1rem; }
        .page-title { text-align:center; font-size:2rem; color:var(--primary); margin-bottom:1rem; font-weight: 800; }
        .contact-card { background: var(--card); padding: 2rem; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid var(--border); }
        .form-group { margin-bottom: 1.5rem; }
        .form-label { display: block; margin-bottom: 0.5rem; font-weight: 700; }
        .form-input { width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 8px; font-family: inherit; box-sizing: border-box; }
        .form-input:focus { border-color: var(--secondary); outline: none; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
        textarea.form-input { resize: vertical; min-height: 150px; }
        .btn-submit { background: var(--secondary); color: white; border: none; padding: 12px 30px; border-radius: 8px; font-weight: 700; cursor: pointer; width: 100%; font-size: 1rem; transition: background 0.2s; }
        .btn-submit:hover { background: #1d4ed8; }
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; text-align: center; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-danger { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>
    <div class="container">
        <h1 class="page-title">اتصل بنا</h1>
        <p style="text-align: center; color: #64748b; margin-bottom: 2rem;">لديك استفسار أو اقتراح؟ يسعدنا سماع رأيك.</p>
        
        <?php echo $msg; ?>
        
        <div class="contact-card">
            <form method="post">
                <div class="form-group">
                    <label class="form-label">الاسم</label>
                    <input type="text" name="name" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">البريد الإلكتروني</label>
                    <input type="email" name="email" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">الموضوع</label>
                    <input type="text" name="subject" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">الرسالة</label>
                    <textarea name="message" class="form-input" required></textarea>
                </div>
                <button type="submit" class="btn-submit">إرسال الرسالة</button>
            </form>
        </div>
    </div>
    <?php include __DIR__ . '/footer.php'; ?>
</body>
</html>