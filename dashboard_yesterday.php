<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// Actions (delete)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('DELETE FROM matches WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    $_SESSION['success_message'] = 'تم حذف مباراة الأمس بنجاح.';
    header('Location: dashboard_yesterday.php');
    exit;
}

// Pagination
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

$yesterday = date('Y-m-d', strtotime('-1 day'));

$search_query = $_GET['search'] ?? '';
if ($search_query) {
    $term = '%' . $search_query . '%';
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM matches WHERE match_date = ? AND (team_home LIKE ? OR team_away LIKE ? OR championship LIKE ?)');
    $countStmt->execute([$yesterday, $term, $term, $term]);
    $total_matches = $countStmt->fetchColumn();

    $sql = "SELECT * FROM matches WHERE match_date = ? AND (team_home LIKE ? OR team_away LIKE ? OR championship LIKE ?) ORDER BY championship ASC, match_time ASC LIMIT ? OFFSET ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$yesterday, $term, $term, $term, $perPage, $offset]);
} else {
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM matches WHERE match_date = ?');
    $countStmt->execute([$yesterday]);
    $total_matches = $countStmt->fetchColumn();
    
    $sql = "SELECT * FROM matches WHERE match_date = ? ORDER BY championship ASC, match_time ASC LIMIT ? OFFSET ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$yesterday, $perPage, $offset]);
}
$matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Grouping
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
    <title>مباريات الأمس - لوحة التحكم</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        /* Same styles as dashboard.php */
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
        .navbar { background-color: var(--primary); color: #fff; padding: 1rem 1.5rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .navbar .brand { font-size: 1.5rem; font-weight: 800; text-decoration: none; color: #fff; }
        .navbar .nav-links a { color: #cbd5e1; text-decoration: none; font-size: 0.95rem; padding: 8px 14px; border-radius: 8px; transition: all 0.2s; }
        .navbar .nav-links a:hover { background-color: #334155; color: #fff; }
        .container { max-width: 900px; margin: 2rem auto; padding: 0 1rem; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .page-header h1 { font-size: 2rem; font-weight: 800; color: var(--primary); margin: 0; }
        .button-add { display: inline-block; background-color: var(--secondary); padding: 12px 24px; border-radius: 10px; color: white; text-decoration: none; font-weight: 700; transition: all 0.2s; }
        .button-add:hover { background-color: #1d4ed8; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2); }
        .header-actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .championship-group { margin-bottom: 2rem; }
        .championship-header { background-color: transparent; color: var(--primary); padding: 10px 5px; font-size: 1.1rem; font-weight: 700; display: flex; align-items: center; margin-bottom: 0.8rem; border-bottom: 2px solid var(--border); }
        .championship-header .league-name { margin-inline-start: 10px; }
        .match-card { background: var(--card); border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03); overflow: hidden; }
        .match-item { display: flex; align-items: center; border-bottom: 1px solid var(--border); padding: 1rem 1.5rem; transition: background-color 0.2s; }
        .match-item:last-child { border-bottom: none; }
        .match-item:hover { background-color: #f8fafc; }
        .match-details { flex-grow: 1; display: flex; align-items: center; gap: 1rem; }
        .match-date-time { min-width: 120px; text-align: right; color: var(--text-light); font-size: 0.9rem; }
        .match-date-time .date { font-weight: 600; color: var(--text); }
        .match-info { flex: 1; display: flex; justify-content: center; align-items: center; gap: 1rem; }
        .team { flex: 1; font-weight: 700; font-size: 1rem; display: flex; align-items: center; gap: 10px; }
        .team.home { justify-content: flex-start; }
        .team.away { justify-content: flex-end; }
        .match-meta { font-size: 0.8rem; color: var(--text-light); margin-top: 4px; display: flex; gap: 10px; justify-content: center; }
        .score-box { background: var(--primary); color: #fff; padding: 4px 12px; border-radius: 10px; font-weight: 700; min-width: 60px; text-align: center; font-size: 1rem; }
        .score-box.vs { background: #e2e8f0; color: var(--text); }
        .actions { display: flex; gap: 8px; margin-inline-start: 1.5rem; }
        .btn-action { text-decoration: none; font-size: 0.9rem; font-weight: 600; padding: 6px 12px; border-radius: 8px; transition: all 0.2s; border: 1px solid transparent; }
        .btn-action.edit { color: var(--secondary); background-color: #eef2ff; border-color: #dbeafe; }
        .btn-action.edit:hover { background-color: #dbeafe; color: #1e4ed8; }
        .btn-action.delete { color: var(--accent); background-color: #fee2e2; border-color: #fecaca; }
        .btn-action.delete:hover { background-color: #fecaca; color: #b91c1c; }
        .no-matches { text-align: center; padding: 3rem; color: var(--text-light); background: var(--card); border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
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
        .search-container { margin-bottom: 1.5rem; }
        .search-form { display: flex; gap: 10px; }
        .search-input { flex: 1; padding: 12px; border: 1px solid var(--border); border-radius: 10px; font-family: inherit; }
        .btn-search { background: var(--primary); color: #fff; border: none; padding: 0 24px; border-radius: 10px; cursor: pointer; font-weight: 700; }
        .alert { padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem; font-weight: 600; display: flex; align-items: center; justify-content: space-between; }
        .alert-success { background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .close-alert { cursor: pointer; font-size: 1.2rem; line-height: 1; opacity: 0.6; }
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
            <a href="dashboard.php">المباريات</a>
            <a href="news_dashboard.php">📰 الأخبار</a>
            <a href="bot_dashboard.php">🤖 البوت الآلي</a>
            <a href="index.php">عرض الموقع</a>
            <a href="logout.php">تسجيل الخروج</a>
        </div>
    </div>
    <div class="container">
        <div class="page-header">
            <h1>مباريات الأمس (<?php echo $yesterday; ?>)</h1>
            <div class="header-actions">
                <a class="button-add" href="dashboard.php">العودة للمباريات الحالية</a>
            </div>
        </div>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <span><?php echo htmlspecialchars($_SESSION['success_message']); ?></span>
                <span class="close-alert" onclick="this.parentElement.style.display='none';">&times;</span>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <div class="search-container">
            <form method="get" class="search-form">
                <input type="text" name="search" class="search-input" placeholder="ابحث في مباريات الأمس..." value="<?php echo htmlspecialchars($search_query); ?>">
                <button type="submit" class="btn-search">بحث</button>
            </form>
        </div>

        <?php if (empty($matches)): ?>
            <div class="no-matches">
                لا توجد مباريات مسجلة لتاريخ الأمس.
            </div>
        <?php else: ?>
            <?php foreach ($grouped_by_championship as $championship => $championship_matches): ?>
                <div class="championship-group">
                    <div class="championship-header">
                        <?php echo league_logo_html($championship, 28, $championship_matches[0]['championship_logo'] ?? null); ?>
                        <span class="league-name"><?php echo htmlspecialchars($championship); ?></span>
                    </div>
                    <div class="match-card">
                        <?php foreach ($championship_matches as $m): ?>
                            <div class="match-item">
                                <div class="match-details">
                                    <div class="match-date-time">
                                        <div class="time"><?php echo format_time_ar($m['match_time']); ?></div>
                                        <?php
                                            $status = get_match_status($m);
                                        ?>
                                        <div style="font-size:0.8rem; color:var(--text-light); margin-top:4px;"><?php echo $status['text']; ?></div>
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
                                        </div>
                                    </div>
                                </div>
                                <div class="actions">
                                    <a href="edit_match.php?id=<?php echo $m['id']; ?>" class="btn-action edit" title="تعديل">تعديل</a>
                                    <a href="dashboard_yesterday.php?action=delete&id=<?php echo $m['id']; ?>" class="btn-action delete" onclick="return confirm('هل أنت متأكد أنك تريد الحذف؟');" title="حذف">حذف</a>
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