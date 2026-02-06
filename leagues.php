<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

// التأكد من وجود جدول الدوريات
$pdo->exec("CREATE TABLE IF NOT EXISTS leagues (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL UNIQUE,
  logo TEXT,
  external_id INTEGER
)");

// تنظيف الدوريات التي باللغة الإنجليزية (حذفها)
$stmt = $pdo->query("SELECT id, name FROM leagues");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // حذف الدوري إذا كان اسمه يحتوي على حروف إنجليزية (لضمان بقاء العربي فقط)
    if (preg_match('/[a-zA-Z]/', $row['name'])) {
        $pdo->prepare("DELETE FROM leagues WHERE id = ?")->execute([$row['id']]);
    }
}

// جلب الدوريات من الجدول الجديد
// نقوم بترتيب الدوريات بحيث تظهر الدوريات التي تحتوي على مباريات مسجلة أولاً
$stmt = $pdo->query("
    SELECT l.name as championship, l.logo as championship_logo, 
    (SELECT COUNT(*) FROM matches m WHERE m.championship = l.name) as match_count
    FROM leagues l
    ORDER BY match_count DESC, l.name ASC
");
$all_leagues = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>الدوريات</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #1e293b; --secondary: #2563eb; --bg: #f8fafc; --card: #ffffff; --text: #0f172a; --border: #e2e8f0; }
        body { font-family: 'Tajawal', sans-serif; background:var(--bg); margin:0; color:var(--text); }
        .container { max-width:900px; margin:2rem auto; padding:0 1rem; }
        .page-title { text-align:center; font-size:1.8rem; color:var(--primary); margin-bottom:2rem; font-weight: 800; }
        
        .leagues-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px; }
        .league-card { 
            background: var(--card); 
            border: 1px solid var(--border); 
            border-radius: 12px; 
            padding: 20px; 
            text-align: center; 
            text-decoration: none; 
            color: var(--text); 
            transition: transform 0.2s, box-shadow 0.2s; 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            justify-content: center; 
            height: 100%; 
        }
        .league-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); border-color: var(--secondary); }
        .league-name { margin-top: 10px; font-weight: 700; font-size: 0.95rem; }
        
        .empty-state { text-align: center; padding: 3rem; color: #64748b; grid-column: 1 / -1; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>
    <div class="container">
        <h1 class="page-title">الدوريات والبطولات</h1>
        
        <?php if (empty($all_leagues)): ?>
            <div class="empty-state">لا توجد دوريات مسجلة حالياً.</div>
        <?php else: ?>
            <div class="leagues-grid">
                <?php foreach ($all_leagues as $league): ?>
                    <a href="league.php?name=<?php echo urlencode($league['championship']); ?>" class="league-card">
                        <?php echo league_logo_html($league['championship'], 60, $league['championship_logo']); ?>
                        <div class="league-name"><?php echo htmlspecialchars($league['championship']); ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
