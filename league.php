<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

$settings = get_site_settings($pdo);
$site_name = $settings['site_name'];
$favicon = $settings['favicon'];
$league_name = $_GET['name'] ?? null;

if ($league_name) {
    $today = date('Y-m-d');

    // جلب شعار البطولة من جدول الدوريات (الأفضل)
    $stmt = $pdo->prepare("SELECT logo FROM leagues WHERE name = ? LIMIT 1");
    $stmt->execute([$league_name]);
    $league_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $league_logo = $league_data['logo'] ?? null;

    if (!$league_logo) {
        // محاولة احتياطية من جدول المباريات
        $stmt = $pdo->prepare("SELECT championship_logo FROM matches WHERE championship = ? LIMIT 1");
        $stmt->execute([$league_name]);
        $league_info = $stmt->fetch(PDO::FETCH_ASSOC);
        $league_logo = $league_info['championship_logo'] ?? null;
    }

    // المباريات القادمة (من اليوم وصاعداً)
    $stmt = $pdo->prepare("SELECT * FROM matches WHERE championship = ? AND match_date >= ? ORDER BY match_date ASC, match_time ASC");
    $stmt->execute([$league_name, $today]);
    $upcoming = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // النتائج السابقة (قبل اليوم)
    $stmt = $pdo->prepare("SELECT * FROM matches WHERE championship = ? AND match_date < ? ORDER BY match_date DESC, match_time DESC LIMIT 50");
    $stmt->execute([$league_name, $today]);
    $past = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // جلب جميع البطولات من جدول الدوريات
    $stmt = $pdo->query("
        SELECT l.name as championship, l.logo as championship_logo 
        FROM leagues l
        ORDER BY (SELECT COUNT(*) FROM matches m WHERE m.championship = l.name) DESC, l.name ASC
    ");
    $all_leagues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($all_leagues)) {
        $stmt = $pdo->query("SELECT championship, MAX(championship_logo) as championship_logo FROM matches WHERE championship IS NOT NULL AND championship != '' GROUP BY championship ORDER BY championship ASC");
        $all_leagues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $league_name ? 'جدول مباريات ' . htmlspecialchars($league_name) : 'جميع الدوريات والبطولات'; ?> - <?php echo htmlspecialchars($site_name); ?></title>
    <?php if ($favicon): ?><link rel="icon" href="<?php echo htmlspecialchars($favicon); ?>"><?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #1e293b; --secondary: #2563eb; --bg: #f8fafc; --card: #ffffff; --text: #0f172a; --text-light: #64748b; --border: #e2e8f0; --accent: #ef4444; }
        body { font-family: 'Tajawal', sans-serif; background-color: var(--bg); color: var(--text); margin: 0; padding: 0; }
        .container { max-width: 850px; margin: 0 auto 3rem; padding: 0 1rem; }
        
        /* Header */
        .league-header { text-align: center; padding: 2rem 0; background: white; margin-bottom: 2rem; border-bottom: 1px solid var(--border); }
        .league-title { font-size: 1.8rem; font-weight: 800; color: var(--primary); margin: 10px 0 0; }
        
        /* Section Titles */
        .section-title { font-size: 1.2rem; font-weight: 700; color: var(--primary); margin-bottom: 1rem; border-right: 4px solid var(--secondary); padding-right: 10px; display: flex; align-items: center; }
        
        /* Match Card Styles (Reused) */
        .match-card { background: var(--card); border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 2rem; }
        .match-item { border-bottom: 1px solid var(--border); transition: background 0.2s; }
        .match-item:last-child { border-bottom: none; }
        .match-item:hover { background: #f8fafc; }
        .match-link { display: flex; align-items: center; padding: 1rem; text-decoration: none; color: inherit; gap: 1rem; }
        
        .match-date-col { min-width: 80px; text-align: center; font-size: 0.85rem; color: var(--text-light); border-left: 1px solid var(--border); padding-left: 1rem; }
        .match-date-col .day { font-weight: 700; color: var(--text); display: block; margin-bottom: 4px; }
        
        .match-info { flex: 1; display: flex; justify-content: center; align-items: center; gap: 15px; }
        .team { flex: 1; font-weight: 700; font-size: 1rem; display: flex; align-items: center; gap: 10px; }
        .team.home { justify-content: flex-end; text-align: left; }
        .team.away { justify-content: flex-start; text-align: right; }
        
        .score-box { background: var(--primary); color: #fff; padding: 5px 12px; border-radius: 8px; font-weight: 700; min-width: 60px; text-align: center; }
        .score-box.time { background: #e2e8f0; color: var(--text); }
        .score-box.live { background: var(--accent); }
        
        .no-matches { text-align: center; padding: 2rem; color: var(--text-light); background: white; border-radius: 12px; border: 1px solid var(--border); }
        .back-btn { display: inline-block; margin-top: 10px; color: var(--secondary); text-decoration: none; font-weight: 600; }

        @media (max-width: 768px) {
            .match-link { flex-direction: column; gap: 10px; }
            .match-date-col { border-left: none; border-bottom: 1px solid var(--border); padding-left: 0; padding-bottom: 8px; width: 100%; display: flex; justify-content: center; gap: 10px; }
            .match-date-col .day { margin-bottom: 0; }
            .match-info { width: 100%; }
        }
        
        /* Leagues Grid */
        .leagues-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px; margin-top: 2rem; }
        .league-card-item { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 20px; text-align: center; text-decoration: none; color: var(--text); transition: transform 0.2s, box-shadow 0.2s; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; }
        .league-card-item:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); border-color: var(--secondary); }
        .league-card-name { margin-top: 10px; font-weight: 700; font-size: 0.95rem; }
        .page-title { text-align: center; color: var(--primary); margin-top: 2rem; font-weight: 800; }
        
        /* Team Logo Hover Effect */
        .team-logo, .team img {
            transition: transform 0.2s ease-in-out;
        }
        .team-logo:hover, .team img:hover { transform: scale(1.1); }
    </style>
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>
    
    <?php if ($league_name): ?>
    <div class="league-header">
        <div class="container" style="margin-bottom: 0;">
            <?php echo league_logo_html($league_name, 60, $league_logo); ?>
            <h1 class="league-title"><?php echo htmlspecialchars($league_name); ?></h1>
            <a href="index.php" class="back-btn">← العودة للرئيسية</a>
        </div>
    </div>

    <div class="container">
        <!-- المباريات القادمة -->
        <div class="section-title">المباريات القادمة</div>
        <?php if (empty($upcoming)): ?>
            <div class="no-matches">لا توجد مباريات قادمة مجدولة حالياً.</div>
        <?php else: ?>
            <div class="match-card">
                <?php foreach ($upcoming as $m): ?>
                    <div class="match-item">
                        <a href="view_match.php?id=<?php echo $m['id']; ?>" class="match-link">
                            <div class="match-date-col">
                                <span class="day"><?php echo date('d/m', strtotime($m['match_date'])); ?></span>
                            </div>
                            <div class="match-info">
                                <div class="team home"><?php echo team_logo_html($m['team_home'], 30, $m['team_home_logo'] ?? null); ?> <?php echo htmlspecialchars($m['team_home']); ?></div>
                                <?php
                                $status = get_match_status($m);
                                if ($status['key'] === 'live') {
                                    echo '<div class="score-box live">' . (int)$m['score_home'] . ' - ' . (int)$m['score_away'] . '</div>';
                                } else {
                                    echo '<div class="score-box time"><span style="margin-left:4px; opacity:0.8;">🕒</span>' . format_time_ar($m['match_time']) . '</div>';
                                }
                                ?>
                                <div class="team away"><?php echo htmlspecialchars($m['team_away']); ?> <?php echo team_logo_html($m['team_away'], 30, $m['team_away_logo'] ?? null); ?></div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- النتائج السابقة -->
        <div class="section-title" style="margin-top: 3rem;">آخر النتائج</div>
        <?php if (empty($past)): ?>
            <div class="no-matches">لا توجد نتائج سابقة مسجلة.</div>
        <?php else: ?>
            <div class="match-card">
                <?php foreach ($past as $m): ?>
                    <div class="match-item">
                        <a href="view_match.php?id=<?php echo $m['id']; ?>" class="match-link">
                            <div class="match-date-col">
                                <span class="day"><?php echo date('d/m', strtotime($m['match_date'])); ?></span>
                            </div>
                            <div class="match-info">
                                <div class="team home"><?php echo team_logo_html($m['team_home'], 30, $m['team_home_logo'] ?? null); ?> <?php echo htmlspecialchars($m['team_home']); ?></div>
                                <div class="score-box">
                                    <?php echo ($m['score_home'] !== null) ? (int)$m['score_home'] . ' - ' . (int)$m['score_away'] : '-'; ?>
                                </div>
                                <div class="team away"><?php echo htmlspecialchars($m['team_away']); ?> <?php echo team_logo_html($m['team_away'], 30, $m['team_away_logo'] ?? null); ?></div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="container">
        <h1 class="page-title">جميع الدوريات والبطولات</h1>
        <div class="leagues-grid">
            <?php foreach ($all_leagues as $league): ?>
                <a href="league.php?name=<?php echo urlencode($league['championship']); ?>" class="league-card-item">
                    <?php echo league_logo_html($league['championship'], 60, $league['championship_logo']); ?>
                    <div class="league-card-name"><?php echo htmlspecialchars($league['championship']); ?></div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <?php include __DIR__ . '/footer.php'; ?>
</body>
</html>