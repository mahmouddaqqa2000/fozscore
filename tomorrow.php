<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

$settings = get_site_settings($pdo);
$site_name = $settings['site_name'];
$favicon = $settings['favicon'];
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$sql = "SELECT * FROM matches WHERE match_date = ? ORDER BY 
        CASE 
            WHEN championship LIKE '%دوري أبطال أوروبا%' THEN 1
            WHEN championship LIKE '%الدوري الإنجليزي%' THEN 2
            WHEN championship LIKE '%الدوري الإسباني%' THEN 3
            WHEN championship LIKE '%الدوري الإيطالي%' THEN 4
            WHEN championship LIKE '%الدوري الألماني%' THEN 5
            WHEN championship LIKE '%الدوري الفرنسي%' THEN 6
            WHEN championship LIKE '%كأس ملك أسبانيا%' THEN 7
            WHEN championship LIKE '%كأس كاراباو%' THEN 8
            WHEN championship LIKE '%كأس إيطاليا%' THEN 9
            ELSE 100
        END ASC, championship ASC, match_time ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$tomorrow]);
$matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

$grouped = [];
foreach ($matches as $m) {
    $champ = !empty($m['championship']) ? $m['championship'] : 'مباريات متنوعة';
    $grouped[$champ][] = $m;
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>جدول مباريات الغد - <?php echo htmlspecialchars($site_name); ?></title>
    <base href="/">
    <?php if ($favicon): ?><link rel="icon" href="<?php echo htmlspecialchars($favicon); ?>"><?php endif; ?>
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
            max-width: 1200px;
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
        .championship-header.major-league {
            background-color: #eef2ff;
            color: #312e81;
            border-bottom: 2px solid #a5b4fc;
            border-radius: 8px;
            padding: 10px;
        }
        .championship-header.cup {
            background-color: #fffbeb;
            color: #b45309;
            border-bottom: 2px solid #fcd34d;
            border-radius: 8px;
            padding: 10px;
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
            gap: 0.8rem;
            flex-direction: column;
        }
        
        /* Match Info (Teams vs) */
        .match-info {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            width: 100%;
        }
        .team {
            flex: 1;
            font-weight: 700;
            font-size: 1.05rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .team.home { justify-content: flex-start; text-align: right; }
        .team.away { justify-content: flex-end; text-align: left; }
        
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

        /* New Bottom Details Style */
        .match-details-bottom {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            width: 100%;
        }
        .detail-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background-color: #f1f5f9;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            color: var(--text-light);
            border: 1px solid var(--border);
            transition: all 0.2s;
        }
        .detail-pill:hover {
            background-color: #e2e8f0;
            color: var(--primary);
            border-color: #cbd5e1;
        }
        .detail-pill img {
            height: 18px; /* Control logo height */
            width: auto;
            vertical-align: middle;
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
            .match-info { width: 100%; justify-content: space-between; gap: 10px; align-items: flex-start; }
            .team { font-size: 0.95rem; flex-direction: column; gap: 5px; }
            .team.away { flex-direction: column-reverse; }
            .team.home { justify-content: center; text-align: center; order: 1; }
            .team.away { justify-content: center; text-align: center; order: 3; }
            .score-box, .match-center-info { order: 2; min-width: 60px; font-size: 1rem; }
            .match-center-info { margin-top: 12px; }
        }
        
        /* Team Logo Hover Effect */
        .team-logo, .team img {
            transition: transform 0.2s ease-in-out;
        }
        .team-logo:hover, .team img:hover { transform: scale(1.1); }
        
        /* Layout Grid for Desktop */
        .content-grid { display: flex; flex-direction: column; gap: 2rem; }
        
        @media (min-width: 992px) {
            .content-grid { display: grid; grid-template-columns: 1fr 350px; align-items: start; gap: 2rem; }
            .sidebar-column { position: sticky; top: 2rem; }
        }

        /* News Section */
        .news-section { /* Styles moved to sidebar context */ }
        .section-title { font-size: 1.5rem; color: var(--primary); font-weight: 800; margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; }
        .news-card { background: var(--card); border-radius: 12px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border: 1px solid var(--border); text-decoration: none; color: inherit; display: block; transition: transform 0.2s; margin-bottom: 1rem; }
        .news-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        .news-img { width: 100%; height: 180px; object-fit: cover; }
        .news-body { padding: 1rem; }
        .news-title { font-size: 1rem; font-weight: 700; margin: 0 0 0.5rem; line-height: 1.5; color: var(--primary); }
        .news-date { font-size: 0.8rem; color: var(--text-light); }
        .view-all-btn { font-size: 0.9rem; color: var(--secondary); text-decoration: none; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>
    <div class="container">
        <div class="site-hero">
            <h2>جدول مباريات الغد</h2>
            <p>تابع أحدث المباريات والنتائج والملخصات مباشرة</p>
        </div>
        <h2 class="page-title">جدول مباريات الغد</h2>
        <div class="day-buttons">
            <a class="day-button" href="مباريات-الامس">الامس</a>
            <a class="day-button" href="./">اليوم</a>
            <a class="day-button active" href="مباريات-الغد">غدا</a>
        </div>
        <div class="content-grid">
            <div class="main-column">
                <?php if (empty($matches)): ?>
                    <div class="match-card no-matches">لا توجد مباريات مجدولة ليوم غد.</div>
                <?php else: ?>
                    <?php foreach ($grouped as $champ => $championship_matches): ?>
                        <?php 
                            $major_leagues_keywords = ['أبطال أوروبا', 'الإنجليزي', 'الإسباني', 'الإيطالي', 'الألماني', 'الفرنسي'];
                            $is_major = false;
                            foreach ($major_leagues_keywords as $keyword) {
                                if (strpos($champ, $keyword) !== false) {
                                    $is_major = true;
                                    break;
                                }
                            }
                            $is_cup = strpos($champ, 'كأس') !== false;

                            $header_class = '';
                            if ($is_cup) {
                                $header_class = 'cup';
                            } elseif ($is_major) {
                                $header_class = 'major-league';
                            }
                        ?>
                        <div class="championship-group">
                            <div class="championship-header <?php echo $header_class; ?>">
                                <?php echo league_logo_html($champ, 28, $championship_matches[0]['championship_logo'] ?? null); ?><span class="league-name"><?php echo htmlspecialchars($champ); ?></span>
                            </div>
                            <div class="match-card">
                                <?php foreach ($championship_matches as $m): ?>
                                    <div class="match-item">
                                        <a href="مباراة/<?php echo $m['id']; ?>-<?php echo slugify($m['team_home'] . '-ضد-' . $m['team_away']); ?>" class="match-link">
                                            <div class="match-info">
                                                <div class="team home"><?php echo team_logo_html($m['team_home'], 50, $m['team_home_logo'] ?? null); ?> <?php echo htmlspecialchars($m['team_home']); ?></div>
                                                <div class="match-center-info" style="display:flex; flex-direction:column; align-items:center;">
                                                    <div class="score-box time"><span style="margin-left:4px; opacity:0.8;">🕒</span><?php echo format_time_ar($m['match_time'], $m['match_date']); ?></div>
                                                    <span class="match-time-muted" style="margin-top:4px;">لم تبدأ</span>
                                                </div>
                                                <div class="team away"><?php echo htmlspecialchars($m['team_away']); ?> <?php echo team_logo_html($m['team_away'], 50, $m['team_away_logo'] ?? null); ?></div>
                                            </div>
                                            
                                            <div class="match-details-bottom">
                                                 <?php if (!empty($m['venue'])): ?>
                                                     <div class="detail-pill">🏟️ <?php echo htmlspecialchars($m['venue']); ?></div>
                                                 <?php endif; ?>
                                                 <?php if (!empty($m['channel'])): ?>
                                                     <div class="detail-pill">
                                                        <?php 
                                                        $logo_url = get_channel_logo_url($m['channel']);
                                                        if ($logo_url): ?>
                                                            <img src="<?php echo $logo_url; ?>" alt="<?php echo htmlspecialchars($m['channel']); ?>" title="<?php echo htmlspecialchars($m['channel']); ?>">
                                                        <?php else: ?>
                                                            📺 <?php echo htmlspecialchars($m['channel']); ?>
                                                        <?php endif; ?>
                                                     </div>
                                                 <?php endif; ?>
                                                 <?php if (!empty($m['commentator'])): ?>
                                                     <div class="detail-pill">🎙️ <?php echo htmlspecialchars($m['commentator']); ?></div>
                                                 <?php endif; ?>
                                            </div>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="sidebar-column">
                <?php
                // جلب آخر 5 أخبار
                $stmt_news = $pdo->query("SELECT * FROM news ORDER BY created_at DESC LIMIT 5");
                $latest_news = $stmt_news->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($latest_news)): 
                ?>
                <div class="news-section">
                    <div class="section-title">
                        <span>آخر الأخبار</span>
                        <a href="news.php" class="view-all-btn">عرض الكل &larr;</a>
                    </div>
                    <?php foreach ($latest_news as $news): ?>
                        <a href="خبر/<?php echo $news['id']; ?>-<?php echo slugify($news['title']); ?>" class="news-card">
                            <?php if ($news['image_url']): ?>
                                <img src="<?php echo htmlspecialchars($news['image_url']); ?>" alt="صورة الخبر" class="news-img">
                            <?php else: ?>
                                <div style="height:180px; background:#f1f5f9; display:flex; align-items:center; justify-content:center; color:#94a3b8;">لا توجد صورة</div>
                            <?php endif; ?>
                            <div class="news-body">
                                <h3 class="news-title"><?php echo htmlspecialchars($news['title']); ?></h3>
                                <div class="news-date"><?php echo date('Y/m/d', strtotime($news['created_at'])); ?></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php include __DIR__ . '/footer.php'; ?>
</body>
</html>