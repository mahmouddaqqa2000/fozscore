<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

// تحديد تاريخ اليوم
$settings = get_site_settings($pdo);
$site_name = $settings['site_name'];
$favicon = $settings['favicon'];
$today = date('Y-m-d');
// جلب مباريات اليوم فقط
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
$stmt->execute([$today]);
$matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// تجميع المباريات حسب البطولة مع ترتيب المباشر أولاً
$grouped_by_championship = [];
$live_championships = [];

foreach ($matches as $match) {
    $championship = !empty($match['championship']) ? $match['championship'] : 'مباريات متنوعة';
    $grouped_by_championship[$championship][] = $match;
    
    $status = get_match_status($match);
    if ($status['key'] === 'live') {
        $live_championships[$championship] = true;
    }
}

// إعادة ترتيب المجموعات والمباريات
$live_groups = [];
$other_groups = [];

foreach ($grouped_by_championship as $champ => $matches_in_group) {
    // ترتيب المباريات داخل البطولة: مباشر > لم تبدأ > انتهت
    usort($matches_in_group, function($a, $b) {
        $status_map = ['live' => 1, 'not_started' => 2, 'finished' => 3];
        
        $statusA_key = get_match_status($a)['key'];
        $statusB_key = get_match_status($b)['key'];
        
        $weightA = $status_map[$statusA_key] ?? 4;
        $weightB = $status_map[$statusB_key] ?? 4;
        
        if ($weightA === $weightB) {
            // إذا كانت الحالة متشابهة، نرتب حسب التوقيت
            // المباريات التي لم تبدأ: الأقرب أولاً
            if ($statusA_key === 'not_started') {
                return strtotime($a['match_time']) <=> strtotime($b['match_time']);
            }
            // المباريات المنتهية: الأحدث (التي انتهت للتو) أولاً
            if ($statusA_key === 'finished') {
                return strtotime($b['match_time']) <=> strtotime($a['match_time']);
            }
            return 0; // للمباريات المباشرة، لا نغير ترتيبها النسبي
        }
        return $weightA <=> $weightB;
    });
    
    if (isset($live_championships[$champ])) {
        $live_groups[$champ] = $matches_in_group;
    } else {
        $other_groups[$champ] = $matches_in_group;
    }
}

$grouped_by_championship = $live_groups + $other_groups;
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>شاهد مباريات اليوم مباشرة - <?php echo htmlspecialchars($site_name); ?></title>
    <?php if ($favicon): ?><link rel="icon" href="<?php echo htmlspecialchars($favicon); ?>"><?php endif; ?>
    <meta name="description" content="تابع أحدث نتائج مباريات كرة القدم، جداول المباريات، أخبار الرياضة، والبث المباشر لأهم الدوريات العالمية والعربية على FozScore.">
    <meta name="keywords" content="كرة قدم, مباريات اليوم, نتائج مباريات, بث مباشر, أخبار رياضة, الدوري الإنجليزي, الدوري الإسباني, دوري أبطال أوروبا">
    
    <!-- Schema.org Markup for Sports Events -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "ItemList",
      "itemListElement": [
        <?php 
        $schema_items = [];
        $position = 1;
        foreach ($matches as $match) {
            $match_name = $match['team_home'] . ' ضد ' . $match['team_away'];
            $match_url = "http://" . $_SERVER['HTTP_HOST'] . "/view_match.php?id=" . $match['id']; // Adjust domain as needed
            $start_time = $match['match_date'] . 'T' . $match['match_time']; // ISO 8601 format roughly
            
            $schema_items[] = '{
                "@type": "SportsEvent",
                "position": ' . $position++ . ',
                "name": "' . htmlspecialchars($match_name) . '",
                "startDate": "' . $start_time . '",
                "url": "' . $match_url . '",
                "competitor": [{"@type": "SportsTeam", "name": "' . htmlspecialchars($match['team_home']) . '"}, {"@type": "SportsTeam", "name": "' . htmlspecialchars($match['team_away']) . '"}]
            }';
        }
        echo implode(',', $schema_items);
        ?>
      ]
    }
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
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
        .championship-header a { text-decoration: none; color: inherit; transition: opacity 0.2s; }
        .championship-header a:hover { opacity: 0.8; text-decoration: underline; }
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
        .score-box.live {
            background-color: var(--accent);
            color: white;
            box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3);
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

        /* Live Indicator */
        .live-indicator {
            display: inline-block;
            width: 8px; height: 8px;
            background-color: white;
            border-radius: 50%;
            animation: blink 1.5s infinite;
            margin-inline-end: 6px;
        }
        @keyframes blink { 50% { opacity: 0.3; } }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .match-info { width: 100%; justify-content: space-between; gap: 10px; align-items: flex-start; }
            .team { font-size: 0.95rem; flex-direction: column; gap: 5px; }
            .team.away { flex-direction: column-reverse; }
            .team.home { justify-content: center; text-align: center; order: 1; }
            .team.away { justify-content: center; text-align: center; order: 3; }
            .match-center-info { order: 2; min-width: 60px; font-size: 1rem; }
            .score-box { order: 0; }
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
        .news-grid { /* This class is no longer used for grid layout */ }
        .news-card { background: var(--card); border-radius: 12px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border: 1px solid var(--border); text-decoration: none; color: inherit; display: block; transition: transform 0.2s; margin-bottom: 1rem; }
        .news-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        .news-img { width: 100%; height: 180px; object-fit: cover; }
        .news-body { padding: 1rem; }
        .news-title { font-size: 1rem; font-weight: 700; margin: 0 0 0.5rem; line-height: 1.5; color: var(--primary); }
        .news-date { font-size: 0.8rem; color: var(--text-light); }
        .view-all-btn { font-size: 0.9rem; color: var(--secondary); text-decoration: none; }

        /* Dark Mode Support */
        body.dark-mode {
            --primary: #f1f5f9;
            --secondary: #60a5fa;
            --bg: #0f172a;
            --card: #1e293b;
            --text: #f1f5f9;
            --text-light: #94a3b8;
            --border: #334155;
        }
        body.dark-mode .score-box { background: #334155; color: #fff; }
        body.dark-mode .score-box.live { background: var(--accent); }
        body.dark-mode .score-box.time { background: #334155; color: #cbd5e1; }
        body.dark-mode .day-buttons { background: var(--card); border-color: var(--border); }
        body.dark-mode .day-button:hover { background: #334155; }
        body.dark-mode .match-item:hover { background-color: #2d3748; }
        body.dark-mode .championship-header.major-league { background-color: #312e81; color: #e0e7ff; border-bottom-color: #4338ca; }
        body.dark-mode .championship-header.cup { background-color: #451a03; color: #fef3c7; border-bottom-color: #92400e; }
        body.dark-mode .detail-pill { background-color: #334155; border-color: #475569; color: #cbd5e1; }
        body.dark-mode .detail-pill:hover { background-color: #475569; color: #fff; }
        body.dark-mode .site-hero { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); }
        body.dark-mode .news-card { background: var(--card); border-color: var(--border); }
        body.dark-mode .news-title { color: var(--text); }
        body.dark-mode header, body.dark-mode .site-header, body.dark-mode .navbar {
            background-color: #1e293b !important;
            color: #f1f5f9 !important;
            border-bottom: 1px solid #334155;
        }
        body.dark-mode .navbar .brand { color: #ffffff !important; }
        body.dark-mode .navbar a { color: #e2e8f0 !important; }
        body.dark-mode .menu-toggle { color: #ffffff !important; }
        body.dark-mode footer, body.dark-mode .site-footer {
            background-color: #1e293b !important;
            color: #f1f5f9 !important;
            border-top: 1px solid #334155;
        }
        
        /* Toggle Button */
        .theme-toggle { position: fixed; bottom: 20px; left: 20px; width: 50px; height: 50px; border-radius: 50%; background: #1e293b; color: #fff; border: none; font-size: 24px; cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,0.3); z-index: 1000; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center; }
        .theme-toggle:hover { transform: scale(1.1); }
        body.dark-mode .theme-toggle { background: var(--secondary); color: #fff; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>
    <div class="container">
        <div class="site-hero">
            <h2>شاهد مباريات اليوم مباشرة</h2>
            <p>تابع أحدث المباريات والنتائج والملخصات مباشرة</p>
        </div>
        <h2 class="page-title">جدول مباريات اليوم</h2>
        <div class="day-buttons">
            <a class="day-button" href="yesterday.php">الامس</a>
            <a class="day-button active" href="index.php">اليوم</a>
            <a class="day-button" href="tomorrow.php">غدا</a>
        </div>

        <div class="content-grid">
            <div class="main-column" id="matches-container">
                <?php if (empty($matches)): ?>
                    <div class="match-card no-matches">
                        لا توجد مباريات مسجلة حالياً.
                    </div>
                <?php else: ?>
                    <?php foreach ($grouped_by_championship as $championship => $championship_matches): ?>
                        <?php 
                            $major_leagues_keywords = ['أبطال أوروبا', 'الإنجليزي', 'الإسباني', 'الإيطالي', 'الألماني', 'الفرنسي'];
                            $is_major = false;
                            foreach ($major_leagues_keywords as $keyword) {
                                if (strpos($championship, $keyword) !== false) {
                                    $is_major = true;
                                    break;
                                }
                            }
                            $is_cup = strpos($championship, 'كأس') !== false;
                            
                            $header_class = '';
                            if ($is_cup) {
                                $header_class = 'cup';
                            } elseif ($is_major) {
                                $header_class = 'major-league';
                            }
                        ?>
                        <div class="championship-group">
                            <div class="championship-header <?php echo $header_class; ?>">
                                <?php echo league_logo_html($championship, 28, $championship_matches[0]['championship_logo'] ?? null); ?>
                                <a href="league.php?name=<?php echo urlencode($championship); ?>" class="league-name"><?php echo htmlspecialchars($championship); ?></a>
                            </div>
                            <div class="match-card">
                                <?php foreach ($championship_matches as $m): ?>
                                    <div class="match-item">
                                        <a href="view_match.php?id=<?php echo $m['id']; ?>" class="match-link">
                                             <div class="match-info">
                                                 <div class="team home"><?php echo team_logo_html($m['team_home'], 50, $m['team_home_logo'] ?? null); ?> <?php echo htmlspecialchars($m['team_home']); ?></div>
                                                <?php
                                                $status = get_match_status($m);
                                                $is_live = $status['key'] === 'live';
                                                $has_score = isset($m['score_home']) && $m['score_home'] !== null;

                                                if ($is_live || ($status['key'] === 'finished' && $has_score)) { 
                                                    $display_home = $has_score ? (int)$m['score_home'] : 0;
                                                    $display_away = $has_score ? (int)$m['score_away'] : 0;
                                                    ?>
                                            <div class="match-center-info" style="display:flex; flex-direction:column; align-items:center;">
                                                        <div class="score-box <?php echo $is_live ? 'live' : ''; ?>"><?php echo $display_home . ' - ' . $display_away; ?></div>
                                                        <?php if ($is_live): ?>
                                                            <span class="match-time-muted" style="color:#ef4444; font-weight:bold; margin-top:4px;">
                                                                <span class="live-indicator" style="background-color:#ef4444;"></span> جاري الآن
                                                            </span>
                                                        <?php elseif ($status['key'] === 'finished'): ?>
                                                            <span class="match-time-muted" style="margin-top:4px;">انتهت</span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php } else { // Match hasn't started or finished without score ?>
                                            <div class="match-center-info" style="display:flex; flex-direction:column; align-items:center;">
                                                        <?php if ($status['key'] === 'finished'): ?>
                                                            <div class="score-box vs">-- : --</div>
                                                            <span class="match-time-muted" style="margin-top:4px;">انتهت</span>
                                                        <?php else: ?>
                                                            <div class="score-box time"><span style="margin-left:4px; opacity:0.8;">🕒</span><?php echo format_time_ar($m['match_time']); ?></div>
                                                            <span class="match-time-muted" style="margin-top:4px;">لم تبدأ</span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php } ?>
                                                 <div class="team away"><?php echo htmlspecialchars($m['team_away']); ?> <?php echo team_logo_html($m['team_away'], 50, $m['team_away_logo'] ?? null); ?></div>
                                            </div>
                                            
                                            <!-- تفاصيل المباراة في الأسفل -->
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
                        <a href="view_news.php?id=<?php echo $news['id']; ?>" class="news-card">
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
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleBtn = document.createElement('button');
            toggleBtn.innerHTML = '🌙';
            toggleBtn.className = 'theme-toggle';
            toggleBtn.title = 'تبديل الوضع الليلي';
            document.body.appendChild(toggleBtn);
            const currentTheme = localStorage.getItem('theme');
            if (currentTheme === 'dark') {
                document.body.classList.add('dark-mode');
                toggleBtn.innerHTML = '☀️';
            }
            toggleBtn.addEventListener('click', function() {
                document.body.classList.toggle('dark-mode');
                let theme = 'light';
                if (document.body.classList.contains('dark-mode')) { theme = 'dark'; toggleBtn.innerHTML = '☀️'; } 
                else { toggleBtn.innerHTML = '🌙'; }
                localStorage.setItem('theme', theme);
            });
        });

        // تحديث النتائج تلقائياً كل دقيقة (60 ثانية) بدون إعادة تحميل الصفحة
        setInterval(function() {
            // نستخدم new URL لضمان التعامل الصحيح مع المعلمات الموجودة مسبقاً
            const url = new URL(window.location.href);
            // نضيف معلمة فريدة لمنع المتصفح من استخدام النسخة المخبأة (cache busting)
            url.searchParams.set('cache_bust', new Date().getTime());

            fetch(url.href, { cache: 'no-store' }) // إضافة خيار لمنع التخزين المؤقت بشكل صريح
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text();
                })
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newContainer = doc.getElementById('matches-container');
                    const currentContainer = document.getElementById('matches-container');

                    // نحدث المحتوى فقط إذا كان هناك تغيير لتجنب الوميض
                    if (newContainer && currentContainer && newContainer.innerHTML.trim() !== currentContainer.innerHTML.trim()) {
                        currentContainer.innerHTML = newContainer.innerHTML;
                    }
                })
                .catch(err => console.error('Error updating matches:', err));
        }, 60000); // 60 ثانية
    </script>
</body>
</html>
