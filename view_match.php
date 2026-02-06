<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php'; // Added helper for logos and time formatting

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$id = (int)$_GET['id'];
$stmt = $pdo->prepare('SELECT * FROM matches WHERE id = ?');
$stmt->execute([$id]);
$match = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$match) {
    http_response_code(404);
    $error_message = "المباراة غير موجودة.";
} else {
    // جلب المواجهات المباشرة السابقة
    $h2h_stmt = $pdo->prepare(
        "SELECT * FROM matches 
         WHERE ((team_home = :home AND team_away = :away) OR (team_home = :away AND team_away = :home))
         AND id != :current_id AND score_home IS NOT NULL
         ORDER BY match_date DESC, match_time DESC
         LIMIT 5"
    );
    $h2h_stmt->execute([
        ':home' => $match['team_home'],
        ':away' => $match['team_away'],
        ':current_id' => $id
    ]);
    $h2h_matches = $h2h_stmt->fetchAll(PDO::FETCH_ASSOC);

    $lineup_home = !empty($match['lineup_home']) ? preg_split('/\r\n|\r|\n/', $match['lineup_home']) : [];
    $lineup_away = !empty($match['lineup_away']) ? preg_split('/\r\n|\r|\n/', $match['lineup_away']) : [];
    $bench_home = !empty($match['bench_home']) ? preg_split('/\r\n|\r|\n/', $match['bench_home']) : [];
    $bench_away = !empty($match['bench_away']) ? preg_split('/\r\n|\r|\n/', $match['bench_away']) : [];
    $absent_home = !empty($match['absent_home']) ? preg_split('/\r\n|\r|\n/', $match['absent_home']) : [];
    $absent_away = !empty($match['absent_away']) ? preg_split('/\r\n|\r|\n/', $match['absent_away']) : [];
    $match_news = !empty($match['match_news']) ? preg_split('/\r\n|\r|\n/', $match['match_news']) : [];
    $player_stats_home = !empty($match['player_stats_home']) ? preg_split('/\r\n|\r|\n/', $match['player_stats_home']) : [];
    $player_stats_away = !empty($match['player_stats_away']) ? preg_split('/\r\n|\r|\n/', $match['player_stats_away']) : [];
    
    // جلب آخر 3 أخبار عامة
    $stmt_news = $pdo->query("SELECT * FROM news ORDER BY created_at DESC LIMIT 3");
    $latest_news = $stmt_news->fetchAll(PDO::FETCH_ASSOC);
    
    // جعل تبويب البث المباشر هو النشط افتراضياً إذا كان متوفراً
    $active_tab = 'lineup';
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo isset($match) ? htmlspecialchars($match['team_home']) . ' ضد ' . htmlspecialchars($match['team_away']) : 'تفاصيل المباراة'; ?> - FozScore</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1e293b;
            --secondary: #2563eb;
            --bg: #f8fafc;
            --card: #ffffff;
            --text: #0f172a;
            --text-light: #64748b;
            --border: #e1e4e8;
            --accent: #ef4444;
        }
        body {
            font-family: 'Tajawal', sans-serif;
            background-color: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        /* Match View Card */
        .match-view-card {
            background: var(--card);
            border-radius: 20px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.07), 0 8px 10px -6px rgba(0, 0, 0, 0.07);
            margin-top: 2rem;
            overflow: hidden;
        }
        .match-view-card .championship-header {
            padding: 12px 20px;
            font-size: 1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f8fafc;
            border-bottom: 1px solid var(--border);
            color: var(--primary);
        }
        .match-main-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 2.5rem 2rem;
        }
        .team-display {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
            flex: 1;
        }
        .team-display .team-name {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary);
            text-align: center;
        }
        .score-section {
            text-align: center;
            margin: 0 2rem;
        }
        .final-score {
            font-size: 3.5rem;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: -2px;
        }
        .match-time-large {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--secondary);
        }
        .match-time-large.live-text {
            color: var(--accent);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .match-status {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-light);
            background: #f1f5f9;
            padding: 4px 12px;
            border-radius: 20px;
            margin-top: 0.5rem;
            display: inline-block;
        }
        .match-meta-footer {
            background: #f8fafc;
            border-top: 1px solid var(--border);
            padding: 1rem;
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            color: var(--text-light);
            font-size: 0.9rem;
        }
        .match-meta-footer img {
            height: 18px;
            width: auto;
            vertical-align: middle;
        }

        /* Tabs Styling */
        .tabs {
            display: flex;
            border-bottom: 1px solid var(--border);
            margin-top: 2.5rem;
            background: var(--card);
            border-radius: 12px 12px 0 0;
            overflow: hidden;
        }
        .tab-button {
            flex: 1;
            padding: 1rem;
            cursor: pointer;
            text-align: center;
            font-weight: 700;
            font-size: 1rem;
            background: transparent;
            border: none;
            color: var(--text-light);
            transition: all 0.2s;
            border-bottom: 3px solid transparent;
        }
        .tab-button:hover {
            background: #f8fafc;
            color: var(--primary);
        }
        .tab-button.active {
            color: var(--secondary);
            border-bottom-color: var(--secondary);
        }
        .tab-content {
            display: none;
            padding: 1.5rem;
            background: var(--card);
            border-radius: 0 0 12px 12px;
            border: 1px solid var(--border);
            border-top: none;
        }
        .tab-content.active {
            display: block;
        }

        /* Lineup Styling */
        .football-pitch {
            direction: ltr;
            background-color: #388e3c; /* Realistic grass green */
            background-image:
                radial-gradient(circle at 0 0, transparent 18px, rgba(255,255,255,0.6) 19px, rgba(255,255,255,0.6) 21px, transparent 22px),
                radial-gradient(circle at 100% 0, transparent 18px, rgba(255,255,255,0.6) 19px, rgba(255,255,255,0.6) 21px, transparent 22px),
                radial-gradient(circle at 0 100%, transparent 18px, rgba(255,255,255,0.6) 19px, rgba(255,255,255,0.6) 21px, transparent 22px),
                radial-gradient(circle at 100% 100%, transparent 18px, rgba(255,255,255,0.6) 19px, rgba(255,255,255,0.6) 21px, transparent 22px),
                repeating-linear-gradient(
                    to right,
                    transparent 0, transparent 10%, rgba(0,0,0,0.1) 10%, rgba(0,0,0,0.1) 20%
                );
            border: 2px solid rgba(255, 255, 255, 0.8);
            border-radius: 12px;
            position: relative;
            display: flex;
            overflow: hidden;
            box-shadow: inset 0 0 20px rgba(0,0,0,0.2);
        }
        .football-pitch::before { /* Center line */
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            left: 50%;
            border-left: 2px solid rgba(255, 255, 255, 0.6);
            transform: translateX(-50%);
        }
        .football-pitch::after { /* Center circle */
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 120px;
            height: 120px;
            border: 2px solid rgba(255, 255, 255, 0.6);
            border-radius: 50%;
            transform: translate(-50%, -50%);
        }
        .pitch-half {
            width: 50%;
            display: flex;
            flex-direction: row; /* Horizontal layout for desktop */
            align-items: center;
            justify-content: space-around;
            padding: 1rem 0;
            min-height: 400px;
            position: relative;
        }
        .pitch-half.away-half {
            flex-direction: row-reverse;
        }
        .formation-row {
            display: flex;
            flex-direction: column; /* Players stacked vertically in their line */
            justify-content: space-around;
            align-items: center;
            z-index: 1;
            position: relative;
            height: 100%;
            flex: 1;
        }
        .player {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
            width: 80px;
        }
        .player-shirt {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid rgba(255,255,255,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.4);
        }
        .player-image {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid rgba(255,255,255,0.8);
            object-fit: cover;
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.4);
        }
        .coach-image {
            width: 60px; height: 60px; border-radius: 50%; object-fit: cover;
            border: 2px solid var(--border); margin-bottom: 8px;
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .player-number {
            position: absolute;
            top: -5px;
            right: -8px;
            background: #fff;
            color: #1e293b;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            font-size: 0.75rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.3);
            border: 2px solid var(--border);
            z-index: 5;
        }
        .home-half .player-shirt { background-color: var(--secondary); }
        .away-half .player-shirt { background-color: var(--accent); }
        .player-name {
            font-size: 0.8rem;
            color: #fff;
            background: rgba(0, 0, 0, 0.5);
            padding: 2px 6px;
            border-radius: 4px;
            text-align: center;
            white-space: nowrap;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* Pitch Markings (Penalty Area & Goal Area) */
        .pitch-half::before, .pitch-half::after {
            content: '';
            position: absolute;
            border: 2px solid rgba(255, 255, 255, 0.6);
            z-index: 0;
        }
        
        /* Desktop: Horizontal */
        .home-half::before { /* Penalty Box */
            left: 0; top: 50%; transform: translateY(-50%);
            width: 16%; height: 60%; border-left: none;
        }
        .home-half::after { /* Goal Area */
            left: 0; top: 50%; transform: translateY(-50%);
            width: 6%; height: 30%; border-left: none;
        }
        
        .away-half::before { /* Penalty Box */
            right: 0; top: 50%; transform: translateY(-50%);
            width: 16%; height: 60%; border-right: none;
        }
        .away-half::after { /* Goal Area */
            right: 0; top: 50%; transform: translateY(-50%);
            width: 6%; height: 30%; border-right: none;
        }

        /* H2H Styling */
        .h2h-item { display: flex; justify-content: space-between; align-items: center; padding: 10px; border-radius: 8px; margin-bottom: 8px; background: #f8fafc; }
        .h2h-teams { font-weight: 600; }
        .h2h-score { font-weight: 700; background: var(--primary); color: #fff; padding: 4px 8px; border-radius: 6px; }

        /* News Styling */
        .news-item { padding: 12px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: flex-start; gap: 10px; }
        .news-item:last-child { border-bottom: none; }
        .news-icon { color: var(--secondary); font-size: 1.2rem; margin-top: -2px; }
        .news-text { font-size: 0.95rem; line-height: 1.5; color: var(--text); }

        /* Player Stats Table */
        .player-stats-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .player-stats-table th, .player-stats-table td { padding: 10px; text-align: center; border-bottom: 1px solid var(--border); }
        .player-stats-table th { background: #f8fafc; color: var(--text-light); font-size: 0.9rem; }
        .player-stats-table td:first-child { text-align: right; font-weight: 600; }

        /* Lists Styling (Bench & Absent) */
        .lists-container { display: flex; gap: 2rem; margin-top: 2rem; }
        .list-column { flex: 1; }
        .list-header { font-weight: 800; font-size: 1.1rem; color: var(--primary); margin-bottom: 1rem; text-align: center; border-bottom: 2px solid var(--border); padding-bottom: 0.5rem; }
        .player-list-item { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid #f1f5f9; }
        .player-list-item:last-child { border-bottom: none; }
        .player-list-info { flex: 1; }
        .player-list-name { font-weight: 600; font-size: 0.95rem; }
        .player-list-extra { font-size: 0.8rem; color: var(--accent); margin-top: 2px; }
        .player-list-number { background: #e2e8f0; color: var(--text); width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 700; }
        .player-list-image { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 1px solid var(--border); }
        
        @media (max-width: 768px) {
            .lists-container { flex-direction: column; gap: 1.5rem; }
            .list-column { width: 100%; }
        }

        /* Stats Styling */
        .stat-row { margin-bottom: 1.5rem; }
        .stat-info { display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-weight: 700; font-size: 0.9rem; color: var(--primary); }
        .stat-bar-container { display: flex; height: 10px; background: #e2e8f0; border-radius: 5px; overflow: hidden; }
        .stat-bar-home { background-color: var(--secondary); height: 100%; transition: width 0.5s ease; }
        .stat-bar-away { background-color: var(--accent); height: 100%; transition: width 0.5s ease; }

        /* Stream Section (Standalone) */
        .stream-section {
            background: var(--card);
            border-radius: 20px;
            padding: 1.5rem;
            margin-top: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        .stream-title {
            font-size: 1.2rem; font-weight: 800; color: var(--primary); margin-bottom: 1rem;
            display: flex; align-items: center; gap: 8px;
        }

        /* Stream Styling */
        .stream-container {
            position: relative;
            padding-bottom: 56.25%; /* 16:9 aspect ratio */
            height: 0;
            overflow: hidden;
            border-radius: 12px;
            background: #000;
        }
        .stream-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: 0;
            -webkit-transform: translateZ(0); /* تحسين الأداء على الهواتف */
        }
        .placeholder-text { text-align: center; color: var(--text-light); padding: 2rem 0; }
        
        /* Back Link & Error Message */
        .back-link {
            display: inline-block;
            margin-top: 2rem;
            padding: 10px 25px;
            background-color: var(--primary);
            color: #fff;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.2s;
        }
        .back-link:hover {
            background-color: var(--secondary);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .error-message {
            text-align: center;
            padding: 3rem;
            background: var(--card);
            border-radius: 16px;
            color: var(--text-light);
            font-size: 1.2rem;
            margin-top: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .match-main-info {
                flex-direction: row;
                gap: 0;
                padding: 1.5rem 0.5rem;
            }
            /* Override team logo size for mobile */
            .team-display .team-logo {
                width: 40px !important;
                height: 40px !important;
                font-size: 14px !important;
            }
            .team-display .team-name {
                font-size: 0.8rem;
            }
            .team-display { gap: 0.5rem; }
            .final-score {
                font-size: 1.5rem;
            }
            .match-time-large {
                font-size: 1rem;
            }
            .score-section {
                order: 0;
                margin-bottom: 0;
                margin: 0 0.5rem;
            }
            .match-meta-footer {
                flex-direction: row;
                flex-wrap: wrap;
                gap: 0.8rem;
            }

            /* Responsive Tabs */
            .tabs {
                margin-top: 1.5rem;
            }
            .tab-button {
                padding: 0.8rem 0.2rem;
                font-size: 0.9rem;
            }
            .tab-content {
                padding: 1rem;
            }

            /* Responsive Pitch */
            .football-pitch {
                flex-direction: column; /* Vertical pitch on mobile */
                min-height: 600px;
                background-image:
                    radial-gradient(circle at 0 0, transparent 18px, rgba(255,255,255,0.6) 19px, rgba(255,255,255,0.6) 21px, transparent 22px),
                    radial-gradient(circle at 100% 0, transparent 18px, rgba(255,255,255,0.6) 19px, rgba(255,255,255,0.6) 21px, transparent 22px),
                    radial-gradient(circle at 0 100%, transparent 18px, rgba(255,255,255,0.6) 19px, rgba(255,255,255,0.6) 21px, transparent 22px),
                    radial-gradient(circle at 100% 100%, transparent 18px, rgba(255,255,255,0.6) 19px, rgba(255,255,255,0.6) 21px, transparent 22px),
                    repeating-linear-gradient(
                        to bottom,
                        transparent 0, transparent 5%, rgba(0,0,0,0.1) 5%, rgba(0,0,0,0.1) 10%
                    );
            }
            .football-pitch::before { /* Horizontal center line */
                top: 50%;
                bottom: auto;
                left: 0;
                right: 0;
                width: 100%;
                height: 0;
                border-left: none;
                border-top: 2px solid rgba(255, 255, 255, 0.6);
                transform: translateY(-50%);
            }
            .pitch-half {
                width: 100%;
                height: 50%;
                flex-direction: column;
            }
            .pitch-half.away-half {
                flex-direction: column-reverse;
            }
            .formation-row {
                flex-direction: row; /* Players side-by-side in their line */
                width: 100%;
                height: auto;
            }
            
            /* Mobile: Vertical Pitch Markings */
            .home-half::before {
                top: 0; left: 50%; transform: translateX(-50%);
                width: 60%; height: 16%;
                border: 2px solid rgba(255, 255, 255, 0.6); border-top: none;
            }
            .home-half::after {
                top: 0; left: 50%; transform: translateX(-50%);
                width: 30%; height: 6%;
                border: 2px solid rgba(255, 255, 255, 0.6); border-top: none;
            }
            
            .away-half::before {
                top: auto; bottom: 0; left: 50%; transform: translateX(-50%);
                width: 60%; height: 16%;
                border: 2px solid rgba(255, 255, 255, 0.6); border-bottom: none;
            }
            .away-half::after {
                top: auto; bottom: 0; left: 50%; transform: translateX(-50%);
                width: 30%; height: 6%;
                border: 2px solid rgba(255, 255, 255, 0.6); border-bottom: none;
            }
            
            .football-pitch::after { /* Center circle */
                width: 80px;
                height: 80px;
            }
            .player {
                width: 65px; /* Smaller player container */
                gap: 4px;
            }
            .player-shirt {
                width: 34px;
                height: 34px;
            }
            .player-name {
                font-size: 0.7rem;
            }

            /* Responsive H2H */
            .h2h-item {
                font-size: 0.9rem;
            }
        }

        /* Live Indicator */
        .live-indicator {
            display: inline-block;
            width: 12px; height: 12px;
            background-color: var(--accent);
            border-radius: 50%;
            animation: blink 1.5s infinite;
        }
        @keyframes blink { 50% { opacity: 0.2; } }

        /* Timeline Styling */
        .timeline { position: relative; padding: 20px 0; max-width: 100%; overflow: hidden; }
        .timeline::before { content: ''; position: absolute; top: 0; bottom: 0; left: 50%; width: 2px; background: #e2e8f0; transform: translateX(-50%); }
        .timeline-row { display: flex; align-items: center; margin-bottom: 20px; width: 100%; }
        .timeline-time { 
            width: 45px; height: 45px; background: #fff; border: 2px solid #e2e8f0; color: var(--primary);
            border-radius: 50%; display: flex; align-items: center; justify-content: center; 
            font-weight: 800; font-size: 0.85rem; z-index: 2; flex-shrink: 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .timeline-content { flex: 1; display: flex; align-items: center; }
        .timeline-content.home { justify-content: flex-end; padding-left: 20px; }
        .timeline-content.away { justify-content: flex-start; padding-right: 20px; }
        
        .timeline-card { 
            background: #fff; padding: 10px 15px; border-radius: 10px; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.05); border: 1px solid #f1f5f9;
            position: relative; max-width: 90%; font-size: 0.95rem; line-height: 1.4;
        }
        
        @media (max-width: 600px) {
            .timeline::before { left: auto; right: 22px; transform: none; }
            .timeline-row { margin-bottom: 15px; }
            .timeline-time { margin-left: 15px; margin-right: 0; width: 40px; height: 40px; font-size: 0.75rem; order: 1; }
            .timeline-content.home, .timeline-content.away { padding: 0; padding-right: 10px; justify-content: flex-start; flex: 1; order: 2; }
            .timeline-content:empty { display: none; }
        }

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
        body.dark-mode .match-view-card .championship-header { background: #2d3748; color: var(--text); border-bottom-color: var(--border); }
        body.dark-mode .match-main-info .team-name { color: var(--text); }
        body.dark-mode .final-score { color: var(--text); }
        body.dark-mode .match-status { background: #334155; color: #cbd5e1; }
        body.dark-mode .match-meta-footer { background: #2d3748; border-top-color: var(--border); }
        body.dark-mode .tabs { background: var(--card); border-bottom-color: var(--border); }
        body.dark-mode .tab-button:hover { background: #2d3748; color: var(--text); }
        body.dark-mode .tab-content { background: var(--card); border-color: var(--border); }
        body.dark-mode .h2h-item { background: #2d3748; }
        body.dark-mode .h2h-score { background: #334155; }
        body.dark-mode .player-stats-table th { background: #2d3748; color: var(--text-light); }
        body.dark-mode .player-list-item { border-bottom-color: var(--border); }
        body.dark-mode .player-list-number { background: #334155; color: var(--text); }
        body.dark-mode .stat-bar-container { background: #334155; }
        body.dark-mode .stream-section { background: var(--card); }
        body.dark-mode .timeline::before { background: var(--border); }
        body.dark-mode .timeline-time { background: var(--card); border-color: var(--border); color: var(--text); }
        body.dark-mode .timeline-card { background: var(--card); border-color: var(--border); color: var(--text); }
        body.dark-mode .news-item { border-bottom-color: var(--border); }
        body.dark-mode .news-text { color: var(--text); }
        
        /* News Section in Match View */
        .news-grid-match {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }
        .news-card-match {
            background: var(--card);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border: 1px solid var(--border);
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            transition: transform 0.2s;
        }
        .news-card-match:hover { transform: translateY(-3px); }
        .news-img-match { width: 100%; height: 140px; object-fit: cover; }
        .news-img-placeholder { width: 100%; height: 140px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; color: #94a3b8; font-size: 0.8rem; }
        .news-body-match { padding: 12px; flex: 1; display: flex; flex-direction: column; }
        .news-title-match { font-size: 0.9rem; font-weight: 700; margin: 0 0 5px 0; line-height: 1.4; color: var(--primary); flex: 1; }
        .news-date-match { font-size: 0.75rem; color: var(--text-light); margin-top: auto; }
        
        body.dark-mode .news-card-match { background: var(--card); border-color: var(--border); }
        body.dark-mode .news-title-match { color: var(--text); }
        body.dark-mode .news-img-placeholder { background: #334155; color: #cbd5e1; }
        
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
        <?php if (isset($error_message)): ?>
            <div class="error-message">
                <p><?php echo $error_message; ?></p>
                <a href="index.php" class="back-link">العودة للرئيسية</a>
            </div>
        <?php else: ?>
            <div class="match-view-card">
                <?php if (!empty($match['championship'])): ?>
                <div class="championship-header">
                    <?php echo league_logo_html($match['championship'], 24, $match['championship_logo'] ?? null); ?>
                    <span><?php echo htmlspecialchars($match['championship']); ?></span>
                </div>
                <?php endif; ?>

                <div class="match-main-info">
                    <div class="team-display home">
                        <?php echo team_logo_html($match['team_home'], 80, $match['team_home_logo'] ?? null); ?>
                        <span class="team-name"><?php echo htmlspecialchars($match['team_home']); ?></span>
                    </div>
                    <div class="score-section">
                        <?php
                        $status = get_match_status($match);
                        $is_live = $status['key'] === 'live';
                        $has_score = isset($match['score_home']) && $match['score_home'] !== null;

                        // عرض النتيجة فقط إذا كانت المباراة جارية أو منتهية ولدينا نتيجة
                        if ($is_live || ($status['key'] === 'finished' && $has_score)) { 
                            $display_home = $has_score ? (int)$match['score_home'] : 0;
                            $display_away = $has_score ? (int)$match['score_away'] : 0;
                            ?>
                            <div class="final-score"><?php echo $display_home . ' - ' . $display_away; ?></div>
                            <div class="match-status">
                                <?php if ($is_live): ?>
                                    <span class="live-indicator"></span>
                                    جارية الآن
                                <?php else: ?>
                                    <?php echo $status['text']; ?>
                                <?php endif; ?>
                            </div>
                        <?php } else { // لم تبدأ أو انتهت بانتظار النتيجة ?>
                            <?php $status_text = $status['key'] === 'finished' ? 'انتهت (بانتظار النتيجة)' : $status['text']; ?>
                            <div class="match-time-large"><?php echo format_time_ar($match['match_time']); ?></div>
                            <div class="match-status"><?php echo $status_text; ?></div>
                        <?php } ?>
                    </div>
                    <div class="team-display away">
                        <?php echo team_logo_html($match['team_away'], 80, $match['team_away_logo'] ?? null); ?>
                        <span class="team-name"><?php echo htmlspecialchars($match['team_away']); ?></span>
                    </div>
                </div>

                <div class="match-meta-footer">
                    <?php if (!empty($match['venue'])): ?>
                    <span>🏟️ <?php echo htmlspecialchars($match['venue']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($match['channel'])): ?>
                    <span>
                        <?php 
                        $logo_url = get_channel_logo_url($match['channel']);
                        if ($logo_url): ?>
                            <img src="<?php echo $logo_url; ?>" alt="<?php echo htmlspecialchars($match['channel']); ?>" title="<?php echo htmlspecialchars($match['channel']); ?>">
                        <?php else: ?>
                            📺 <?php echo htmlspecialchars($match['channel']); ?>
                        <?php endif; ?>
                    </span>
                    <?php endif; ?>
                    <?php if (!empty($match['commentator'])): ?>
                    <span>🎙️ <?php echo htmlspecialchars($match['commentator']); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($match['stream_url'])): ?>
            <div class="stream-section">
                <div class="stream-title">
                    <span class="live-indicator" style="background-color: var(--accent);"></span>
                    البث المباشر
                </div>
                <div class="stream-container">
                    <?php 
                    // التحقق مما إذا كان المدخل كود iframe كامل أم مجرد رابط
                    if (strpos($match['stream_url'], '<iframe') !== false) {
                        $embed_code = $match['stream_url'];
                        // تحسين الكود للهواتف بإضافة playsinline إذا لم تكن موجودة
                        if (strpos($embed_code, 'playsinline') === false) {
                            $embed_code = str_replace('<iframe', '<iframe playsinline webkit-playsinline', $embed_code);
                        }
                        echo $embed_code;
                    } else {
                        // عرض الرابط داخل iframe
                        echo '<iframe src="' . htmlspecialchars($match['stream_url']) . '" 
                                frameborder="0" 
                                scrolling="no"
                                playsinline
                                webkit-playsinline
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen" 
                                allowfullscreen></iframe>';
                    }
                    ?>
                </div>
                <p style="font-size: 0.8rem; text-align: center; color: var(--text-light); margin-top: 1rem;">
                    ملاحظة: البث مقدم من طرف ثالث، والموقع غير مسؤول عن جودة البث أو استمراريته.
                </p>
            </div>
            <?php endif; ?>

            <!-- Tabs Navigation -->
            <div class="tabs">
                <button class="tab-button <?php echo $active_tab === 'lineup' ? 'active' : ''; ?>" onclick="openTab(event, 'lineup')">التشكيلة</button>
                <button class="tab-button <?php echo $active_tab === 'h2h' ? 'active' : ''; ?>" onclick="openTab(event, 'h2h')">المواجهات</button>
                <button class="tab-button <?php echo $active_tab === 'stats' ? 'active' : ''; ?>" onclick="openTab(event, 'stats')">الإحصائيات</button>
                <button class="tab-button <?php echo $active_tab === 'player_stats' ? 'active' : ''; ?>" onclick="openTab(event, 'player_stats')">إحصائيات اللاعبين</button>
                <button class="tab-button <?php echo $active_tab === 'standings' ? 'active' : ''; ?>" onclick="openTab(event, 'standings')">المراكز</button>
                <button class="tab-button <?php echo $active_tab === 'events' ? 'active' : ''; ?>" onclick="openTab(event, 'events')">الأحداث</button>
            </div>

            <!-- Tab Content -->
            <div id="lineup" class="tab-content <?php echo $active_tab === 'lineup' ? 'active' : ''; ?>">
                <?php 
                    $structured_lineup_home = parse_lineup_to_formation($lineup_home);
                    $structured_lineup_away = parse_lineup_to_formation($lineup_away);
                ?>
                <?php if (!$structured_lineup_home || !$structured_lineup_away): ?>
                    <p class="placeholder-text">لم يتم الإعلان عن التشكيلة بعد.</p>
                <?php else: ?>
                    <div class="football-pitch">
                        <div class="pitch-half home-half">
                            <?php foreach ($structured_lineup_home as $position => $players): ?>
                                <div class="formation-row <?php echo $position; ?>">
                                    <?php foreach ($players as $player): ?>
                                        <div class="player" title="<?php echo htmlspecialchars($player['name']); ?>">
                                            <div style="position: relative;">
                                                <?php if ($player['image']): ?>
                                                    <img src="<?php echo htmlspecialchars($player['image']); ?>" class="player-image" alt="<?php echo htmlspecialchars($player['name']); ?>">
                                                <?php else: ?>
                                                    <div class="player-shirt"></div>
                                                <?php endif; ?>
                                                <?php if ($player['number']): ?>
                                                    <div class="player-number"><?php echo htmlspecialchars($player['number']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <span class="player-name"><?php echo htmlspecialchars($player['name']); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="pitch-half away-half">
                            <?php foreach ($structured_lineup_away as $position => $players): ?>
                                <div class="formation-row <?php echo $position; ?>">
                                    <?php foreach ($players as $player): ?>
                                        <div class="player" title="<?php echo htmlspecialchars($player['name']); ?>">
                                            <div style="position: relative;">
                                                <?php if ($player['image']): ?>
                                                    <img src="<?php echo htmlspecialchars($player['image']); ?>" class="player-image" alt="<?php echo htmlspecialchars($player['name']); ?>">
                                                <?php else: ?>
                                                    <div class="player-shirt"></div>
                                                <?php endif; ?>
                                                <?php if ($player['number']): ?>
                                                    <div class="player-number"><?php echo htmlspecialchars($player['number']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <span class="player-name"><?php echo htmlspecialchars($player['name']); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Coaches Section -->
                    <div style="display: flex; justify-content: space-around; margin-top: 2rem; border-top: 1px solid var(--border); padding-top: 1.5rem;">
                        <div style="text-align: center;">
                            <?php if (!empty($match['coach_home_image'])): ?>
                                <img src="<?php echo htmlspecialchars($match['coach_home_image']); ?>" class="coach-image" alt="مدرب المستضيف">
                            <?php endif; ?>
                            <div style="font-size: 0.9rem; color: var(--text-light); margin-bottom: 5px;">مدرب <?php echo htmlspecialchars($match['team_home']); ?></div>
                            <div style="font-weight: 800; font-size: 1.1rem;"><?php echo htmlspecialchars($match['coach_home'] ?? 'غير محدد'); ?></div>
                        </div>
                        <div style="text-align: center;">
                            <?php if (!empty($match['coach_away_image'])): ?>
                                <img src="<?php echo htmlspecialchars($match['coach_away_image']); ?>" class="coach-image" alt="مدرب الضيف">
                            <?php endif; ?>
                            <div style="font-size: 0.9rem; color: var(--text-light); margin-bottom: 5px;">مدرب <?php echo htmlspecialchars($match['team_away']); ?></div>
                            <div style="font-weight: 800; font-size: 1.1rem;"><?php echo htmlspecialchars($match['coach_away'] ?? 'غير محدد'); ?></div>
                        </div>
                    </div>

                    <!-- Bench Section -->
                    <?php 
                    $parsed_bench_home = parse_simple_list($bench_home);
                    $parsed_bench_away = parse_simple_list($bench_away);
                    if (!empty($parsed_bench_home) || !empty($parsed_bench_away)): 
                    ?>
                    <div class="lists-container">
                        <div class="list-column">
                            <div class="list-header">مقاعد بدلاء <?php echo htmlspecialchars($match['team_home']); ?></div>
                            <?php foreach ($parsed_bench_home as $p): ?>
                                <div class="player-list-item">
                                    <?php if ($p['number']): ?><div class="player-list-number"><?php echo htmlspecialchars($p['number']); ?></div><?php endif; ?>
                                    <?php if (!empty($p['image'])): ?><img src="<?php echo htmlspecialchars($p['image']); ?>" class="player-list-image" alt="<?php echo htmlspecialchars($p['name']); ?>"><?php endif; ?>
                                    <div class="player-list-info">
                                        <div class="player-list-name"><?php echo htmlspecialchars($p['name']); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="list-column">
                            <div class="list-header">مقاعد بدلاء <?php echo htmlspecialchars($match['team_away']); ?></div>
                            <?php foreach ($parsed_bench_away as $p): ?>
                                <div class="player-list-item">
                                    <?php if ($p['number']): ?><div class="player-list-number"><?php echo htmlspecialchars($p['number']); ?></div><?php endif; ?>
                                    <?php if (!empty($p['image'])): ?><img src="<?php echo htmlspecialchars($p['image']); ?>" class="player-list-image" alt="<?php echo htmlspecialchars($p['name']); ?>"><?php endif; ?>
                                    <div class="player-list-info">
                                        <div class="player-list-name"><?php echo htmlspecialchars($p['name']); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Absent Section -->
                    <?php 
                    $parsed_absent_home = parse_simple_list($absent_home);
                    $parsed_absent_away = parse_simple_list($absent_away);
                    if (!empty($parsed_absent_home) || !empty($parsed_absent_away)): 
                    ?>
                    <div class="lists-container">
                        <div class="list-column">
                            <div class="list-header" style="color: var(--accent);">غيابات <?php echo htmlspecialchars($match['team_home']); ?></div>
                            <?php foreach ($parsed_absent_home as $p): ?>
                                <div class="player-list-item">
                                    <?php if ($p['number']): ?><div class="player-list-number"><?php echo htmlspecialchars($p['number']); ?></div><?php endif; ?>
                                    <?php if (!empty($p['image'])): ?><img src="<?php echo htmlspecialchars($p['image']); ?>" class="player-list-image" alt="<?php echo htmlspecialchars($p['name']); ?>"><?php endif; ?>
                                    <div class="player-list-info">
                                        <div class="player-list-name"><?php echo htmlspecialchars($p['name']); ?></div>
                                        <?php if ($p['extra']): ?><div class="player-list-extra"><?php echo htmlspecialchars($p['extra']); ?></div><?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="list-column">
                            <div class="list-header" style="color: var(--accent);">غيابات <?php echo htmlspecialchars($match['team_away']); ?></div>
                            <?php foreach ($parsed_absent_away as $p): ?>
                                <div class="player-list-item">
                                    <?php if ($p['number']): ?><div class="player-list-number"><?php echo htmlspecialchars($p['number']); ?></div><?php endif; ?>
                                    <?php if (!empty($p['image'])): ?><img src="<?php echo htmlspecialchars($p['image']); ?>" class="player-list-image" alt="<?php echo htmlspecialchars($p['name']); ?>"><?php endif; ?>
                                    <div class="player-list-info">
                                        <div class="player-list-name"><?php echo htmlspecialchars($p['name']); ?></div>
                                        <?php if ($p['extra']): ?><div class="player-list-extra"><?php echo htmlspecialchars($p['extra']); ?></div><?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div id="h2h" class="tab-content <?php echo $active_tab === 'h2h' ? 'active' : ''; ?>">
                <h3>آخر 5 مواجهات مباشرة</h3>
                <?php if (empty($h2h_matches)): ?>
                    <p class="placeholder-text">لا توجد مواجهات سابقة مسجلة بين الفريقين.</p>
                <?php else: ?>
                    <?php foreach ($h2h_matches as $h2h_match): ?>
                        <div class="h2h-item">
                            <span class="h2h-date"><?php echo htmlspecialchars($h2h_match['match_date']); ?></span>
                            <span class="h2h-teams"><?php echo htmlspecialchars($h2h_match['team_home']); ?> - <?php echo htmlspecialchars($h2h_match['team_away']); ?></span>
                            <span class="h2h-score"><?php echo (int)$h2h_match['score_home']; ?> : <?php echo (int)$h2h_match['score_away']; ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div id="stats" class="tab-content <?php echo $active_tab === 'stats' ? 'active' : ''; ?>">
                <?php if (!empty($match['match_stats'])): ?>
                    <?php echo render_match_stats($match['match_stats'], $match['team_home'], $match['team_away'], $match['team_home_logo'] ?? null, $match['team_away_logo'] ?? null); ?>
                <?php elseif ($match['stats_possession_home'] !== null): ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; padding-bottom:10px; border-bottom:1px solid #e2e8f0;">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <?php echo team_logo_html($match['team_home'], 40, $match['team_home_logo'] ?? null); ?>
                        </div>
                        <h3 style="margin:0; color:#1e293b; font-size:1.1rem;">إحصائيات المباراة</h3>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <?php echo team_logo_html($match['team_away'], 40, $match['team_away_logo'] ?? null); ?>
                        </div>
                    </div>
                    <?php
                    $stats_list = [
                        ['label' => 'الاستحواذ', 'home' => $match['stats_possession_home'], 'away' => $match['stats_possession_away'], 'is_percent' => true],
                        ['label' => 'التسديدات', 'home' => $match['stats_shots_home'], 'away' => $match['stats_shots_away']],
                        ['label' => 'الركنيات', 'home' => $match['stats_corners_home'], 'away' => $match['stats_corners_away']],
                        ['label' => 'الأخطاء', 'home' => $match['stats_fouls_home'], 'away' => $match['stats_fouls_away']],
                    ];
                    
                    foreach ($stats_list as $stat): 
                        $home_val = (int)$stat['home'];
                        $away_val = (int)$stat['away'];
                        $total = $home_val + $away_val;
                        
                        if ($total == 0) {
                            $home_pct = 50;
                            $away_pct = 50;
                        } else {
                            $home_pct = ($home_val / $total) * 100;
                            $away_pct = ($away_val / $total) * 100;
                        }
                    ?>
                    <div class="stat-row">
                        <div class="stat-info">
                            <span><?php echo $home_val . ($stat['is_percent'] ?? false ? '%' : ''); ?></span>
                            <span><?php echo $stat['label']; ?></span>
                            <span><?php echo $away_val . ($stat['is_percent'] ?? false ? '%' : ''); ?></span>
                        </div>
                        <div class="stat-bar-container">
                            <div class="stat-bar-home" style="width: <?php echo $home_pct; ?>%"></div>
                            <div class="stat-bar-away" style="width: <?php echo $away_pct; ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="placeholder-text">لا توجد إحصائيات متوفرة لهذه المباراة بعد.</p>
                <?php endif; ?>
            </div>

            <div id="player_stats" class="tab-content <?php echo $active_tab === 'player_stats' ? 'active' : ''; ?>">
                <?php 
                $parsed_pstats_home = parse_player_stats($player_stats_home);
                $parsed_pstats_away = parse_player_stats($player_stats_away);
                
                if (empty($parsed_pstats_home) && empty($parsed_pstats_away)): ?>
                    <p class="placeholder-text">لا توجد إحصائيات فردية للاعبين.</p>
                <?php else: ?>
                    <div class="lists-container">
                        <div class="list-column">
                            <div class="list-header"><?php echo htmlspecialchars($match['team_home']); ?></div>
                            <table class="player-stats-table">
                                <thead>
                                    <tr><th>اللاعب</th><th>أهداف</th><th>تمريرات</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($parsed_pstats_home as $p): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($p['name']); ?></td>
                                        <td><?php echo $p['goals'] > 0 ? $p['goals'] : '-'; ?></td>
                                        <td><?php echo $p['assists'] > 0 ? $p['assists'] : '-'; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="list-column">
                            <div class="list-header"><?php echo htmlspecialchars($match['team_away']); ?></div>
                            <table class="player-stats-table">
                                <thead>
                                    <tr><th>اللاعب</th><th>أهداف</th><th>تمريرات</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($parsed_pstats_away as $p): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($p['name']); ?></td>
                                        <td><?php echo $p['goals'] > 0 ? $p['goals'] : '-'; ?></td>
                                        <td><?php echo $p['assists'] > 0 ? $p['assists'] : '-'; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div id="standings" class="tab-content <?php echo $active_tab === 'standings' ? 'active' : ''; ?>">
                <p class="placeholder-text">ميزة جدول الترتيب قيد التطوير حالياً.</p>
            </div>

            <div id="events" class="tab-content <?php echo $active_tab === 'events' ? 'active' : ''; ?>">
    <?php 
    $match_events = !empty($match['match_events']) ? preg_split('/\r\n|\r|\n/', $match['match_events']) : [];
    ?>
    <?php if (empty($match_events)): ?>
        <p class="placeholder-text">لا توجد أحداث متوفرة لهذه المباراة.</p>
    <?php else: ?>
        <?php
        $parsed_events = [];
        foreach ($match_events as $event_str) {
            if (empty(trim($event_str))) continue;
            // تحليل النص: الدقيقة' الوصف (الفريق)
            if (preg_match('/^(\d+(?:\+\d+)?)[\']\s+(.+)\s+(\(مستضيف\)|\(ضيف\))$/u', trim($event_str), $matches)) {
                $min = $matches[1];
                $text = trim($matches[2]);
                $side_str = $matches[3];
                
                $sort = intval($min);
                if (strpos($min, '+') !== false) {
                    $parts = explode('+', $min);
                    $sort = intval($parts[0]) + (intval($parts[1]) * 0.01);
                }
                
                $parsed_events[] = ['min' => $min, 'sort' => $sort, 'text' => $text, 'side' => ($side_str === '(مستضيف)') ? 'home' : 'away'];
            } else {
                $parsed_events[] = ['min' => '-', 'sort' => 999, 'text' => $event_str, 'side' => 'home'];
            }
        }
        // ترتيب الأحداث زمنياً
        usort($parsed_events, function($a, $b) { return $a['sort'] <=> $b['sort']; });
        ?>
        <div class="timeline">
            <?php foreach ($parsed_events as $ev): ?>
                <div class="timeline-row">
                    <div class="timeline-content home">
                        <?php if ($ev['side'] === 'home'): ?><div class="timeline-card"><?php echo htmlspecialchars($ev['text']); ?></div><?php endif; ?>
                    </div>
                    <div class="timeline-time"><?php echo htmlspecialchars($ev['min']); ?>'</div>
                    <div class="timeline-content away">
                        <?php if ($ev['side'] === 'away'): ?><div class="timeline-card"><?php echo htmlspecialchars($ev['text']); ?></div><?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

            <?php if (!empty($match_news)): ?>
            <div class="stream-section">
                <div class="stream-title">📰 أخبار المباراة</div>
                <?php foreach ($match_news as $news_line): ?>
                    <div class="news-item">
                        <div class="news-text"><?php echo htmlspecialchars($news_line); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Latest News Section -->
            <?php if (!empty($latest_news)): ?>
            <div class="stream-section">
                <div class="stream-title">📰 آخر الأخبار</div>
                <div class="news-grid-match">
                    <?php foreach ($latest_news as $news): ?>
                        <a href="view_news.php?id=<?php echo $news['id']; ?>" class="news-card-match">
                            <?php if ($news['image_url']): ?>
                                <img src="<?php echo htmlspecialchars($news['image_url']); ?>" alt="صورة الخبر" class="news-img-match">
                            <?php else: ?>
                                <div class="news-img-placeholder">لا توجد صورة</div>
                            <?php endif; ?>
                            <div class="news-body-match">
                                <h3 class="news-title-match"><?php echo htmlspecialchars($news['title']); ?></h3>
                                <div class="news-date-match"><?php echo date('Y/m/d', strtotime($news['created_at'])); ?></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div style="text-align: center;">
                <a href="javascript:history.back()" class="back-link">العودة للخلف</a>
            </div>
        <?php endif; ?>
    </div>
    <?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
<script>
function openTab(evt, tabName) {
    var i, tabcontent, tablinks;
    tabcontent = document.getElementsByClassName("tab-content");
    for (i = 0; i < tabcontent.length; i++) {
        tabcontent[i].style.display = "none";
    }
    tablinks = document.getElementsByClassName("tab-button");
    for (i = 0; i < tablinks.length; i++) {
        tablinks[i].className = tablinks[i].className.replace(" active", "");
    }
    document.getElementById(tabName).style.display = "block";
    evt.currentTarget.className += " active";
}

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
</script>