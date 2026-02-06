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

// حذف رسالة
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ?");
    $stmt->execute([(int)$_GET['delete']]);
    header('Location: admin_messages.php');
    exit;
}

// جلب الرسائل
$pdo->exec("CREATE TABLE IF NOT EXISTS messages (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, email TEXT, subject TEXT, message TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
$stmt = $pdo->query("SELECT * FROM messages ORDER BY created_at DESC");
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>الرسائل الواردة - لوحة التحكم</title>
    <?php if ($favicon): ?><link rel="icon" href="<?php echo htmlspecialchars($favicon); ?>"><?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #1e293b; --secondary: #2563eb; --bg: #f8fafc; --card: #ffffff; --text: #0f172a; --border: #e2e8f0; }
        body { font-family: 'Tajawal', sans-serif; background:var(--bg); margin:0; color:var(--text); }
        .navbar { background-color: var(--primary); color: #fff; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; }
        .navbar .brand { font-size: 1.5rem; font-weight: 800; text-decoration: none; color: #fff; }
        .navbar .nav-links a { color: #cbd5e1; text-decoration: none; margin-left: 15px; font-weight: 500; }
        .navbar .nav-links a:hover { color: #fff; }
        .container { max-width:1000px; margin:2rem auto; padding:0 1rem; }
        .msg-card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 1.5rem; margin-bottom: 1rem; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .msg-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; }
        .msg-sender { font-weight: 700; font-size: 1.1rem; color: var(--primary); }
        .msg-email { font-size: 0.9rem; color: #64748b; display: block; }
        .msg-date { font-size: 0.85rem; color: #94a3b8; }
        .msg-subject { font-weight: 700; margin: 10px 0 5px; color: var(--secondary); }
        .msg-body { line-height: 1.6; white-space: pre-wrap; }
        .btn-delete { background: #fee2e2; color: #991b1b; border: none; padding: 5px 12px; border-radius: 6px; cursor: pointer; text-decoration: none; font-size: 0.9rem; }
        .btn-delete:hover { background: #fecaca; }
        .empty-state { text-align: center; padding: 3rem; color: #64748b; }
    </style>
</head>
<body>
    <div class="navbar">
        <a class="brand" href="dashboard.php">لوحة التحكم</a>
        <div class="nav-links">
            <a href="dashboard.php">المباريات</a>
            <a href="bot_dashboard.php">البوت</a>
            <a href="index.php">الموقع</a>
        </div>
    </div>
    <div class="container">
        <h1 style="margin-bottom: 2rem; color: var(--primary);">📩 الرسائل الواردة (<?php echo count($messages); ?>)</h1>
        
        <?php if (empty($messages)): ?>
            <div class="empty-state">لا توجد رسائل جديدة.</div>
        <?php else: ?>
            <?php foreach ($messages as $msg): ?>
                <div class="msg-card">
                    <div class="msg-header">
                        <div>
                            <div class="msg-sender"><?php echo htmlspecialchars($msg['name']); ?></div>
                            <span class="msg-email"><?php echo htmlspecialchars($msg['email']); ?></span>
                        </div>
                        <div style="text-align: left;">
                            <div class="msg-date"><?php echo $msg['created_at']; ?></div>
                            <a href="?delete=<?php echo $msg['id']; ?>" class="btn-delete" onclick="return confirm('حذف هذه الرسالة؟')">حذف</a>
                        </div>
                    </div>
                    <div class="msg-subject"><?php echo htmlspecialchars($msg['subject']); ?></div>
                    <div class="msg-body"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>