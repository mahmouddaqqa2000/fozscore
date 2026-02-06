<?php
session_start();
require_once __DIR__ . '/db.php';

require_once __DIR__ . '/helpers.php'; // For logo functions
// حماية الصفحة: التحقق مما إذا كان المستخدم قد سجل دخوله
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// حذف مباراة إذا تم تمرير id و action=delete
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('DELETE FROM matches WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    $_SESSION['success_message'] = 'تم حذف المباراة بنجاح.';
    header('Location: dashboard.php');
    exit;
}

// حذف جميع المباريات إذا تم تمرير action=delete_all
if (isset($_GET['action']) && $_GET['action'] === 'delete_all') {
    $pdo->exec('DELETE FROM matches');
    $pdo->exec("DELETE FROM sqlite_sequence WHERE name='matches'"); // إعادة تعيين العداد
    $_SESSION['success_message'] = 'تم حذف جميع المباريات بنجاح.';
    header('Location: dashboard.php');
    exit;
}

// حذف المباريات القديمة (أقدم من أسبوع)
if (isset($_GET['action']) && $_GET['action'] === 'delete_old') {
    $week_ago = date('Y-m-d', strtotime('-7 days'));
    $stmt = $pdo->prepare('DELETE FROM matches WHERE match_date < ?');
    $stmt->execute([$week_ago]);
    $deleted_count = $stmt->rowCount();
    $_SESSION['success_message'] = "تم تنظيف قاعدة البيانات: تم حذف $deleted_count مباراة أقدم من $week_ago.";
    header('Location: dashboard.php');
    exit;
}

// إعدادات الترقيم (Pagination)
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10; // عدد المباريات في كل صفحة
$offset = ($page - 1) * $perPage;

$yesterday = date('Y-m-d', strtotime('-1 day'));

$search_query = $_GET['search'] ?? '';
if ($search_query) {
    $term = '%' . $search_query . '%';
    
    // جلب جميع النتائج (بدون ترقيم في SQL) لترتيب المباشر أولاً
    $sql = "SELECT * FROM matches WHERE match_date != ? AND (team_home LIKE ? OR team_away LIKE ? OR championship LIKE ?) ORDER BY 
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
            END ASC, championship ASC, match_date ASC, match_time ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$yesterday, $term, $term, $term]);
} else {
    
    $sql = "SELECT * FROM matches WHERE match_date != ? ORDER BY 
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
            END ASC, championship ASC, match_date ASC, match_time ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$yesterday]);
}
$all_matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ترتيب المباريات: الجارية أولاً
$live_matches = [];
$other_matches = [];

foreach ($all_matches as $match) {
    $status = get_match_status($match);
    if ($status['key'] === 'live') {
        $live_matches[] = $match;
    } else {
        $other_matches[] = $match;
    }
}

$matches_sorted = array_merge($live_matches, $other_matches);

// تطبيق الترقيم (Pagination) يدوياً
$total_matches = count($matches_sorted);
$total_pages = ceil($total_matches / $perPage);
$matches = array_slice($matches_sorted, $offset, $perPage);

// تجميع المباريات حسب البطولة
$grouped_by_championship = [];
foreach ($matches as $match) {
    $championship = !empty($match['championship']) ? $match['championship'] : 'مباريات متنوعة';
    $grouped_by_championship[$championship][] = $match;
}

$total_pages = ceil($total_matches / $perPage);
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>لوحة التحكم - FozScore</title>
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
            --border: #e2e8f0;
            --accent: #ef4444;
            --success: #22c55e;
        }
        body { font-family: 'Tajawal', sans-serif; background-color: var(--bg); color: var(--text); margin: 0; padding: 0; }
        
        /* Dashboard Navbar */
        .navbar { background-color: var(--primary); color: #fff; padding: 1rem 1.5rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .navbar .brand { font-size: 1.5rem; font-weight: 800; text-decoration: none; color: #fff; }
        .navbar .nav-links a { color: #cbd5e1; text-decoration: none; font-size: 0.95rem; padding: 8px 14px; border-radius: 8px; transition: all 0.2s; }
        .navbar .nav-links a:hover { background-color: #334155; color: #fff; }
        
        .container { max-width: 900px; margin: 2rem auto; padding: 0 1rem; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .page-header h1 { font-size: 2rem; font-weight: 800; color: var(--primary); margin: 0; }
        
        /* Add Button */
        .button-add { display: inline-block; background-color: var(--secondary); padding: 12px 24px; border-radius: 10px; color: white; text-decoration: none; font-weight: 700; transition: all 0.2s; }
        .button-add:hover { background-color: #1d4ed8; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2); }
        .button-add.delete-all { background-color: var(--accent); }
        .button-add.delete-all:hover { background-color: #b91c1c; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2); }
        .header-actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .button-yesterday { background-color: #64748b; }
        .button-yesterday:hover { background-color: #475569; }
        
        /* Match List Styling */
        .championship-group { margin-bottom: 2rem; }
        .championship-header { background-color: transparent; color: var(--primary); padding: 10px 5px; font-size: 1.1rem; font-weight: 700; display: flex; align-items: center; margin-bottom: 0.8rem; border-bottom: 2px solid var(--border); }
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
        
        .match-card { background: var(--card); border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03); overflow: hidden; }
        .match-item { display: flex; align-items: center; border-bottom: 1px solid var(--border); padding: 1rem 1.5rem; transition: background-color 0.2s; }
        .match-item:last-child { border-bottom: none; }
        .match-item:hover { background-color: #f8fafc; }
        
        .match-details { flex-grow: 1; display: flex; align-items: center; gap: 1rem; }
        .match-date-time { min-width: 120px; text-align: right; color: var(--text-light); font-size: 0.9rem; }
        .match-date-time .date { font-weight: 600; color: var(--text); }
        
        /* Match Status Badge */
        .match-status-badge {
            font-size: 0.75rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 20px;
            margin-top: 5px;
            display: inline-flex;
            align-items: center;
            text-align: center;
        }
        .match-status-badge.not_started { background-color: #eef2ff; color: #4338ca; }
        .match-status-badge.live { background-color: #fee2e2; color: #b91c1c; }
        .match-status-badge.finished { background-color: #f0fdf4; color: #15803d; }
        .match-status-badge .live-indicator {
            width: 7px; height: 7px; background-color: var(--accent); border-radius: 50%;
            animation: blink 1.5s infinite; margin-inline-end: 5px;
        }
        @keyframes blink {
            50% { opacity: 0.2; }
        }

        .match-info { flex: 1; display: flex; justify-content: center; align-items: center; gap: 1rem; }
        .team { flex: 1; font-weight: 700; font-size: 1rem; display: flex; align-items: center; gap: 10px; }
        .team.home { justify-content: flex-start; }
        .team.away { justify-content: flex-end; }
        .match-meta { font-size: 0.8rem; color: var(--text-light); margin-top: 4px; display: flex; gap: 10px; justify-content: center; }
        .match-meta img {
            height: 16px;
            width: auto;
            vertical-align: middle;
        }
        
        .score-box { background: var(--primary); color: #fff; padding: 4px 12px; border-radius: 10px; font-weight: 700; min-width: 60px; text-align: center; font-size: 1rem; }
        .score-box.vs { background: #e2e8f0; color: var(--text); }
        
        /* Action Buttons */
        .actions { display: flex; gap: 8px; margin-inline-start: 1.5rem; }
        .btn-action { text-decoration: none; font-size: 0.9rem; font-weight: 600; padding: 6px 12px; border-radius: 8px; transition: all 0.2s; border: 1px solid transparent; }
        .btn-action.edit { color: var(--secondary); background-color: #eef2ff; border-color: #dbeafe; }
        .btn-action.edit:hover { background-color: #dbeafe; color: #1e4ed8; }
        .btn-action.delete { color: var(--accent); background-color: #fee2e2; border-color: #fecaca; }
        .btn-action.delete:hover { background-color: #fecaca; color: #b91c1c; }
        
        .no-matches { text-align: center; padding: 3rem; color: var(--text-light); background: var(--card); border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }

        /* Responsive */
        @media (max-width: 768px) {
            .page-header { flex-direction: column; gap: 1rem; align-items: stretch; text-align: center; }
            .match-item { flex-direction: column; align-items: stretch; gap: 1rem; }
            .match-details { flex-direction: column; gap: 0.8rem; }
            .match-date-time { text-align: center; }
            .match-info { width: 100%; }
            .team { font-size: 0.9rem; flex-direction: column; gap: 5px; text-align: center; justify-content: center; }
            .team.away { flex-direction: column-reverse; }
            .actions { margin-inline-start: 0; justify-content: center; }
        }
        
        /* Search & Alerts */
        .search-container { margin-bottom: 1.5rem; }
        .search-form { display: flex; gap: 10px; }
        .search-input { flex: 1; padding: 12px; border: 1px solid var(--border); border-radius: 10px; font-family: inherit; }
        .btn-search { background: var(--primary); color: #fff; border: none; padding: 0 24px; border-radius: 10px; cursor: pointer; font-weight: 700; }
        
        .alert { padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem; font-weight: 600; display: flex; align-items: center; justify-content: space-between; }
        .alert-success { background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .close-alert { cursor: pointer; font-size: 1.2rem; line-height: 1; opacity: 0.6; }

        /* Pagination */
        .pagination { display: flex; justify-content: center; gap: 5px; margin-top: 2rem; }
        .pagination a { padding: 8px 12px; background: var(--card); border: 1px solid var(--border); border-radius: 6px; text-decoration: none; color: var(--text); transition: all 0.2s; }
        .pagination a.active { background: var(--secondary); color: white; border-color: var(--secondary); }
        .pagination a:hover:not(.active) { background: #e2e8f0; }
    </style>
</head>
<body>
    <div class="navbar">
        <a class="brand" href="dashboard.php">لوحة تحكم FozScore</a>
        <div class="nav-links">
            <a href="news_dashboard.php">📰 الأخبار</a>
            <a href="bot_dashboard.php">🤖 البوت الآلي</a>
            <a href="admin_messages.php">📩 الرسائل</a>
            <a href="settings.php">⚙️ الإعدادات</a>
            <a href="index.php">عرض الموقع</a>
            <a href="logout.php">تسجيل الخروج</a>
        </div>
    </div>
    <div class="container">
        <div class="page-header">
            <h1>إدارة المباريات</h1>
            <div class="header-actions">
                <a class="button-add button-yesterday" href="dashboard_yesterday.php">مباريات الأمس</a>
                <a class="button-add" href="add_match.php">+ إضافة مباراة جديدة</a>
                <a class="button-add delete-all" href="dashboard.php?action=delete_all" onclick="return confirm('هل أنت متأكد من حذف جميع المباريات؟ سيتم حذف كل البيانات ولا يمكن التراجع عن هذا الإجراء.');">حذف جميع المباريات</a>
            </div>
        </div>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <span><?php echo htmlspecialchars($_SESSION['success_message']); ?></span>
                <span class="close-alert" onclick="this.parentElement.style.display='none';">&times;</span>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <div class="stats-overview" style="display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap;">
            <div class="stat-card" style="background: var(--card); padding: 15px 20px; border-radius: 12px; flex: 1; min-width: 200px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); display: flex; align-items: center; justify-content: space-between; border: 1px solid var(--border);">
                <div>
                    <div style="font-size: 0.9rem; color: var(--text-light); font-weight: 600;">إجمالي المباريات</div>
                    <div style="font-size: 1.8rem; font-weight: 800; color: var(--primary); margin-top: 5px;"><?php echo $total_matches; ?></div>
                </div>
                <div style="font-size: 2rem; opacity: 0.8;">⚽</div>
            </div>
            <div class="stat-card" style="background: var(--card); padding: 15px 20px; border-radius: 12px; flex: 1; min-width: 200px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); display: flex; align-items: center; justify-content: space-between; border: 1px solid var(--border);">
                <div>
                    <div style="font-size: 0.9rem; color: var(--text-light); font-weight: 600;">مباريات جارية الآن</div>
                    <div style="font-size: 1.8rem; font-weight: 800; color: var(--accent); margin-top: 5px;"><?php echo count($live_matches); ?></div>
                </div>
                <div style="font-size: 2rem; opacity: 0.8;">🔴</div>
            </div>
        </div>

        <div class="search-container">
            <form method="get" class="search-form">
                <input type="text" name="search" class="search-input" placeholder="ابحث عن فريق أو بطولة..." value="<?php echo htmlspecialchars($search_query); ?>">
                <button type="submit" class="btn-search">بحث</button>
            </form>
        </div>

        <?php if (empty($matches)): ?>
            <div class="no-matches">
                لا توجد مباريات. أضف مباراة جديدة للبدء.
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
                        <span class="league-name"><?php echo htmlspecialchars($championship); ?></span>
                    </div>
                    <div class="match-card">
                        <?php foreach ($championship_matches as $m): ?>
                            <div class="match-item">
                                <div class="match-details">
                                    <div class="match-date-time">
                                        <div class="date"><?php echo htmlspecialchars($m['match_date']); ?></div>
                                        <div class="time"><?php echo format_time_ar($m['match_time']); ?></div>
                                        <?php
                                            $status = get_match_status($m);
                                        ?>
                                        <div class="match-status-badge <?php echo $status['key']; ?>">
                                            <?php if ($status['key'] === 'live'): ?><span class="live-indicator"></span><?php endif; ?>
                                            <?php echo $status['text']; ?>
                                        </div>
                                    </div>
                                    <div class="match-info">
                                        <div style="flex: 1;">
                                            <div style="display: flex; align-items: center; gap: 1rem;">
                                                <div class="team home">
                                                    <?php echo team_logo_html($m['team_home'], 50, $m['team_home_logo'] ?? null); ?>
                                                    <span><?php echo htmlspecialchars($m['team_home']); ?></span>
                                                </div>
                                                <?php if ($m['score_home'] !== null && $m['score_away'] !== null): ?>
                                                    <div class="score-box"><?php echo (int)$m['score_home'] . ' - ' . (int)$m['score_away']; ?></div>
                                                <?php else: ?>
                                                    <div class="score-box vs">VS</div>
                                                <?php endif; ?>
                                                <div class="team away">
                                                    <span><?php echo htmlspecialchars($m['team_away']); ?></span>
                                                    <?php echo team_logo_html($m['team_away'], 50, $m['team_away_logo'] ?? null); ?>
                                                </div>
                                            </div>
                                            <div class="match-meta">
                                                <?php if(!empty($m['channel'])): ?>
                                                    <span>
                                                        <?php 
                                                        $logo_url = get_channel_logo_url($m['channel']);
                                                        if ($logo_url): ?>
                                                            <img src="<?php echo $logo_url; ?>" alt="<?php echo htmlspecialchars($m['channel']); ?>" title="<?php echo htmlspecialchars($m['channel']); ?>">
                                                        <?php else: ?>
                                                            📺 <?php echo htmlspecialchars($m['channel']); ?>
                                                        <?php endif; ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if(!empty($m['commentator'])): ?><span>🎙️ <?php echo htmlspecialchars($m['commentator']); ?></span><?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="actions">
                                    <a href="edit_match.php?id=<?php echo $m['id']; ?>" class="btn-action edit" title="تعديل">تعديل</a>
                                    <a href="dashboard.php?action=delete&id=<?php echo $m['id']; ?>" class="btn-action delete" onclick="return confirm('هل أنت متأكد أنك تريد الحذف؟');" title="حذف">حذف</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Pagination Links -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?><?php echo $search_query ? '&search=' . urlencode($search_query) : ''; ?>" 
                           class="<?php echo $page === $i ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>