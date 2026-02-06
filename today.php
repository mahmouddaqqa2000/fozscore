<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

$today = date('Y-m-d');
$stmt = $pdo->prepare('SELECT * FROM matches WHERE match_date = ? ORDER BY match_time ASC');
$stmt->execute([$today]);
$matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// تجميع حسب البطولة مع ترتيب المباشر أولاً
$grouped = [];
$live_championships = [];

foreach ($matches as $m) {
    $champ = !empty($m['championship']) ? $m['championship'] : 'مباريات متنوعة';
    $grouped[$champ][] = $m;

    $status = get_match_status($m);
    if ($status['key'] === 'live') {
        $live_championships[$champ] = true;
    }
}

$live_groups = [];
$other_groups = [];

foreach ($grouped as $champ => $matches_in_group) {
    usort($matches_in_group, function($a, $b) {
        $statusA = get_match_status($a);
        $statusB = get_match_status($b);
        $isLiveA = ($statusA['key'] === 'live');
        $isLiveB = ($statusB['key'] === 'live');
        
        if ($isLiveA && !$isLiveB) return -1;
        if (!$isLiveA && $isLiveB) return 1;
        return 0; 
    });
    
    if (isset($live_championships[$champ])) {
        $live_groups[$champ] = $matches_in_group;
    } else {
        $other_groups[$champ] = $matches_in_group;
    }
}

$grouped = $live_groups + $other_groups;
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>مباريات اليوم</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1e293b;
            --secondary: #2563eb;
            --bg: #f8fafc;
            --card: #ffffff;
            --text: #0f172a;
            --text-light: #64748b;
            --border: #e2e8f0;
            --accent: #ef4444;
        }
        body {
            font-family: 'Tajawal', sans-serif;
            background-color: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 0;
        }
        .page-title { font-size: 1.8rem; margin: 1.5rem 0; text-align: center; color: var(--primary); font-weight: 800; }
        
        /* Buttons */
        .day-buttons { display: flex; gap: 8px; justify-content: center; margin-bottom: 2rem; background: #fff; padding: 8px; border-radius: 50px; width: fit-content; margin-left: auto; margin-right: auto; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: 1px solid var(--border); }
        .day-button { color: var(--text-light); padding: 8px 24px; border-radius: 25px; text-decoration: none; font-weight: 600; transition: all 0.2s ease; font-size: 0.95rem; }
        .day-button:hover { background: #f1f5f9; color: var(--secondary); }
        .day-button.active { background: var(--secondary); color: #fff; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2); }
        
        .container {
            max-width: 850px;
            margin: 0 auto 3rem;
            padding: 0 1rem;
        }
        
        /* Championship Group */
        .championship-group {
            margin-bottom: 2rem;
        }
        .championship-header {
            background-color: transparent;
            color: var(--primary);
            padding: 10px 5px;
            font-size: 1.1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            margin-bottom: 0.8rem;
            border-bottom: 2px solid var(--border);
        }
        .championship-header .league-name { margin-inline-start: 10px; }
        
        /* Match Card */
        .match-card {
            background: var(--card);
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.5);
        }
        .match-item {
            border-bottom: 1px solid var(--border);
            transition: background-color 0.2s;
            position: relative;
        }
        .match-item:last-child {
            border-bottom: none;
        }
        .match-item:hover {
            background-color: #f8fafc;
        }
        .match-link {
            display: flex;
            align-items: center;
            padding: 1.2rem 1.5rem;
            text-decoration: none;
            color: inherit;
            gap: 1rem;
        }
        
        /* Match Meta (Time/Venue) */
        .match-meta {
            display: flex;
            flex-direction: column;
            align-items: center;
            min-width: 90px;
            font-size: 0.85rem;
            color: var(--text-light);
            border-inline-end: 1px solid var(--border);
            padding-inline-end: 1rem;
        }
        .match-venue {
            font-size: 0.75rem;
            margin-top: 4px;
            color: #94a3b8;
            text-align: center;
        }
        
        /* Match Info (Teams vs) */
        .match-info {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
        }
        .team {
            flex: 1;
            font-weight: 700;
            font-size: 1.05rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .team.home { justify-content: flex-end; text-align: left; }
        .team.away { justify-content: flex-start; text-align: right; }
        
        .score-box {
            background: var(--primary);
            color: #fff;
            padding: 6px 14px;
            border-radius: 12px;
            font-weight: 700;
            min-width: 70px;
            text-align: center;
            font-size: 1.1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .score-box.time {
            background: #e2e8f0;
            color: var(--text);
            box-shadow: none;
            font-size: 0.95rem;
        }
        .match-time-muted {
            display: block;
            font-size: 0.78rem;
            color: #7a8696;
            margin-top: 6px;
            text-align: center;
        }
        
        .no-matches {
            text-align: center;
            padding: 3rem;
            color: var(--text-light);
            background: var(--card);
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        
        /* site-hero */
        .site-hero {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            color: #fff;
            padding: 30px 20px;
            border-radius: 20px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            margin-top: 1.5rem; /* added spacing under navigation */
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .site-hero::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: radial-gradient(circle at top right, rgba(255,255,255,0.1), transparent);
        }
        .site-hero h2 { margin: 0; font-size: 1.8rem; font-weight: 800; letter-spacing: -0.5px; position: relative; }
        .site-hero p { margin: 10px 0 0; opacity: 0.8; font-size: 1rem; position: relative; }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .match-link { flex-direction: column; gap: 15px; padding: 1rem; }
            .match-meta { 
                flex-direction: row; 
                width: 100%; 
                justify-content: center; 
                gap: 10px; 
                border-inline-end: none; 
                border-bottom: 1px solid var(--border); 
                padding-inline-end: 0; 
                padding-bottom: 10px; 
                margin-bottom: 5px;
            }
            .match-info { width: 100%; justify-content: space-between; gap: 10px; }
            .team { font-size: 0.95rem; flex-direction: column; gap: 5px; }
            .team.home { justify-content: center; text-align: center; order: 1; }
            .team.away { justify-content: center; text-align: center; order: 3; }
            .score-box { order: 2; min-width: 60px; font-size: 1rem; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>
    <div class="container">
        <div class="site-hero">
            <h2>FozScore - مباريات اليوم</h2>
            <p>تابع أحدث المباريات والنتائج والملخصات مباشرة</p>
        </div>
        <h2 class="page-title">جدول مباريات</h2>
        <div class="day-buttons">
            <a class="day-button" href="yesterday.php">الامس</a>
            <a class="day-button active" href="index.php">اليوم</a>
            <a class="day-button" href="tomorrow.php">غدا</a>
        </div>
        <?php if (empty($matches)): ?>
            <div class="match-card no-matches">لا توجد مباريات لليوم.</div>
        <?php else: ?>
            <?php foreach ($grouped as $championship => $championship_matches): ?>
                <div class="championship-group">
                    <div class="championship-header">
                        <?php echo league_logo_html($championship, 28); ?>
                        <span class="league-name"><?php echo htmlspecialchars($championship); ?></span>
                    </div>
                    <div class="match-card">
                        <?php foreach ($championship_matches as $m): ?>
                            <div class="match-item">
                                <a href="view_match.php?id=<?php echo $m['id']; ?>" class="match-link">
                                    <div class="match-meta">
                                         <?php if (!empty($m['venue'])): ?>
                                             <div class="match-venue"><?php echo htmlspecialchars($m['venue']); ?></div>
                                         <?php endif; ?>
                                     </div>
                                     <div class="match-info">
                                         <div class="team home"><?php echo team_logo_html($m['team_home'], 32); ?> <?php echo htmlspecialchars($m['team_home']); ?></div>
                                        <?php if ($m['score_home'] !== null && $m['score_away'] !== null): ?>
                                            <div>
                                                <div class="score-box"><?php echo (int)$m['score_home'] . ' - ' . (int)$m['score_away']; ?></div>
                                                <span class="match-time-muted"><?php echo format_time_ar($m['match_time']); ?></span>
                                            </div>
                                        <?php else: ?>
                                            <div class="score-box time"><?php echo format_time_ar($m['match_time']); ?></div>
                                        <?php endif; ?>
                                         <div class="team away"><?php echo team_logo_html($m['team_away'], 32); ?> <?php echo htmlspecialchars($m['team_away']); ?></div>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
