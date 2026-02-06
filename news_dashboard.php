<?php
session_start();
require_once __DIR__ . '/db.php';

// حماية الصفحة
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// منطق حذف جميع الأخبار
if (isset($_GET['action']) && $_GET['action'] === 'delete_all') {
    $pdo->exec("DELETE FROM news");
    $pdo->exec("DELETE FROM sqlite_sequence WHERE name='news'"); // تصفير العداد
    $_SESSION['success_message'] = 'تم حذف جميع الأخبار بنجاح.';
    header('Location: news_dashboard.php');
    exit;
}

// منطق حذف خبر واحد
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("DELETE FROM news WHERE id = ?");
    $stmt->execute([(int)$_GET['id']]);
    $_SESSION['success_message'] = 'تم حذف الخبر بنجاح.';
    header('Location: news_dashboard.php');
    exit;
}

// جلب الأخبار
$stmt = $pdo->query("SELECT * FROM news ORDER BY id DESC");
$news_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>إدارة الأخبار - FozScore</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1e293b;
            --secondary: #2563eb;
            --bg: #f8fafc;
            --card: #ffffff;
            --text: #0f172a;
            --border: #e2e8f0;
            --accent: #ef4444;
        }
        body { font-family: 'Tajawal', sans-serif; background-color: var(--bg); color: var(--text); margin: 0; padding: 0; }
        
        .navbar { background-color: var(--primary); color: #fff; padding: 1rem 1.5rem; display: flex; justify-content: space-between; align-items: center; }
        .navbar .brand { font-size: 1.5rem; font-weight: 800; text-decoration: none; color: #fff; }
        .navbar .nav-links a { color: #cbd5e1; text-decoration: none; margin-inline-start: 15px; font-weight: 500; }
        .navbar .nav-links a:hover { color: #fff; }

        .container { max-width: 1000px; margin: 2rem auto; padding: 0 1rem; }
        
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 15px; }
        .page-header h1 { margin: 0; color: var(--primary); }
        
        .btn { padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 700; color: white; display: inline-block; transition: transform 0.2s; }
        .btn:hover { transform: translateY(-2px); }
        .btn-danger { background-color: var(--accent); }
        .btn-primary { background-color: var(--secondary); }
        
        .alert { padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem; background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0; display: flex; justify-content: space-between; }
        .close-alert { cursor: pointer; }

        .news-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
        .news-card { background: var(--card); border-radius: 12px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border: 1px solid var(--border); display: flex; flex-direction: column; }
        .news-img { width: 100%; height: 180px; object-fit: cover; }
        .news-body { padding: 15px; flex: 1; display: flex; flex-direction: column; }
        .news-title { font-size: 1.1rem; font-weight: 700; margin: 0 0 10px 0; color: var(--primary); }
        .news-meta { font-size: 0.85rem; color: #64748b; margin-bottom: 10px; }
        .news-actions { margin-top: auto; padding-top: 15px; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; gap: 10px; }
        
        .btn-sm { padding: 5px 12px; font-size: 0.9rem; border-radius: 6px; }
        .empty-state { text-align: center; padding: 3rem; color: #64748b; grid-column: 1 / -1; }
    </style>
</head>
<body>
    <div class="navbar">
        <a class="brand" href="dashboard.php">لوحة التحكم</a>
        <div class="nav-links">
            <a href="dashboard.php">المباريات</a>
            <a href="bot_dashboard.php">🤖 البوت</a>
            <a href="index.php">عرض الموقع</a>
            <a href="logout.php">خروج</a>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h1>إدارة الأخبار (<?php echo count($news_list); ?>)</h1>
            <div style="display:flex; gap:10px;">
                <a href="bot_dashboard.php" class="btn btn-primary">جلب أخبار جديدة (البوت)</a>
                <?php if (count($news_list) > 0): ?>
                    <a href="news_dashboard.php?action=delete_all" class="btn btn-danger" onclick="return confirm('تحذير: هل أنت متأكد من حذف جميع الأخبار؟ لا يمكن التراجع عن هذا الإجراء.');">حذف جميع الأخبار</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert">
                <span><?php echo htmlspecialchars($_SESSION['success_message']); ?></span>
                <span class="close-alert" onclick="this.parentElement.style.display='none';">&times;</span>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <div class="news-grid">
            <?php if (empty($news_list)): ?>
                <div class="empty-state">
                    <h3>لا توجد أخبار حالياً</h3>
                    <p>يمكنك استخدام البوت لسحب آخر الأخبار الرياضية.</p>
                </div>
            <?php else: ?>
                <?php foreach ($news_list as $news): ?>
                    <div class="news-card">
                        <?php if ($news['image_url']): ?>
                            <img src="<?php echo htmlspecialchars($news['image_url']); ?>" alt="صورة" class="news-img">
                        <?php else: ?>
                            <div style="height:180px; background:#f1f5f9; display:flex; align-items:center; justify-content:center; color:#94a3b8;">لا توجد صورة</div>
                        <?php endif; ?>
                        <div class="news-body">
                            <div class="news-title"><?php echo htmlspecialchars($news['title']); ?></div>
                            <div class="news-meta">📅 <?php echo htmlspecialchars($news['created_at']); ?></div>
                            <div class="news-actions">
                                <a href="<?php echo $news['source_url'] ?? '#'; ?>" target="_blank" class="btn btn-primary btn-sm" style="background:#64748b;">المصدر</a>
                                <a href="news_dashboard.php?action=delete&id=<?php echo $news['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('حذف هذا الخبر؟');">حذف</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>