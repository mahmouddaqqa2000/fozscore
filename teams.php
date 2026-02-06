<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

$settings = get_site_settings($pdo);
$favicon = $settings['favicon'];

// 1. تنظيف الفرق التي باللغة الإنجليزية (حذفها)
$stmt = $pdo->query("SELECT id, name FROM teams");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // حذف الفريق إذا كان اسمه يحتوي على حروف إنجليزية
    if (preg_match('/[a-zA-Z]/', $row['name'])) {
        $pdo->prepare("DELETE FROM teams WHERE id = ?")->execute([$row['id']]);
    }
}

// 2. ملء الجدول من المباريات (فقط الأسماء العربية)
$stmt = $pdo->query("
    SELECT team_name, MAX(team_logo) as team_logo
    FROM (
        SELECT team_home as team_name, team_home_logo as team_logo FROM matches
        UNION ALL
        SELECT team_away as team_name, team_away_logo as team_logo FROM matches
    )
    WHERE team_name IS NOT NULL AND team_name != ''
    GROUP BY team_name
");
$match_teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmtInsert = $pdo->prepare("INSERT OR IGNORE INTO teams (name, logo) VALUES (?, ?)");

foreach ($match_teams as $mt) {
    // ندرج فقط إذا لم يحتوي الاسم على حروف إنجليزية
    if (!preg_match('/[a-zA-Z]/', $mt['team_name'])) {
        $stmtInsert->execute([$mt['team_name'], $mt['team_logo']]);
    }
}

// جلب الفرق من جدول الفرق الجديد
$stmt = $pdo->query("SELECT name as team_name, logo as team_logo, league_name FROM teams ORDER BY 
    CASE WHEN league_name = 'الدوري الإسباني' THEN 0 ELSE 1 END, 
    name ASC");
$all_teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>الفرق - FozScore</title>
    <?php if ($favicon): ?><link rel="icon" href="<?php echo htmlspecialchars($favicon); ?>"><?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #1e293b; --secondary: #2563eb; --bg: #f8fafc; --card: #ffffff; --text: #0f172a; --border: #e2e8f0; }
        body { font-family: 'Tajawal', sans-serif; background:var(--bg); margin:0; color:var(--text); }
        .container { max-width:900px; margin:2rem auto; padding:0 1rem; }
        .page-title { text-align:center; font-size:1.8rem; color:var(--primary); margin-bottom:2rem; font-weight: 800; }
        
        .teams-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px; }
        .team-card { 
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
        .team-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); border-color: var(--secondary); }
        .team-name { margin-top: 10px; font-weight: 700; font-size: 0.95rem; }
        
        .empty-state { text-align: center; padding: 3rem; color: #64748b; grid-column: 1 / -1; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>
    <div class="container">
        <h1 class="page-title">جميع الفرق</h1>
        <?php if (empty($all_teams)): ?>
            <div class="empty-state">لا توجد فرق مسجلة حالياً.</div>
        <?php else: ?>
            <div class="teams-grid">
                <?php foreach ($all_teams as $team): ?>
                    <a href="#" class="team-card">
                        <?php echo team_logo_html($team['team_name'], 60, $team['team_logo']); ?>
                        <div class="team-name"><?php echo htmlspecialchars($team['team_name']); ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
