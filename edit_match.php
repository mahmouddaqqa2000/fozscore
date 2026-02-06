<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

// حماية الصفحة: التحقق مما إذا كان المستخدم قد سجل دخوله
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

if (!isset($_GET['id'])) {
    header('Location: dashboard.php');
    exit;
}

$id = (int)$_GET['id'];
$stmt = $pdo->prepare('SELECT * FROM matches WHERE id = ?');
$stmt->execute([$id]);
$match = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$match) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = trim($_POST['match_date'] ?? '');
    $time = trim($_POST['match_time'] ?? '');
    $team_home = trim($_POST['team_home'] ?? '');
    $team_away = trim($_POST['team_away'] ?? '');
    $venue = trim($_POST['venue'] ?? '');
    $championship = trim($_POST['championship'] ?? '');
    $channel = trim($_POST['channel'] ?? '');
    $commentator = trim($_POST['commentator'] ?? '');
    $stream_url = trim($_POST['stream_url'] ?? '');
    $coach_home = trim($_POST['coach_home'] ?? '');
    $coach_away = trim($_POST['coach_away'] ?? '');
    $coach_home_image = trim($_POST['coach_home_image'] ?? '');
    $coach_away_image = trim($_POST['coach_away_image'] ?? '');
    // Stats
    $stats_possession_home = isset($_POST['stats_possession_home']) && $_POST['stats_possession_home'] !== '' ? (int)$_POST['stats_possession_home'] : null;
    $stats_possession_away = isset($_POST['stats_possession_away']) && $_POST['stats_possession_away'] !== '' ? (int)$_POST['stats_possession_away'] : null;
    $stats_shots_home = isset($_POST['stats_shots_home']) && $_POST['stats_shots_home'] !== '' ? (int)$_POST['stats_shots_home'] : null;
    $stats_shots_away = isset($_POST['stats_shots_away']) && $_POST['stats_shots_away'] !== '' ? (int)$_POST['stats_shots_away'] : null;
    $stats_corners_home = isset($_POST['stats_corners_home']) && $_POST['stats_corners_home'] !== '' ? (int)$_POST['stats_corners_home'] : null;
    $stats_corners_away = isset($_POST['stats_corners_away']) && $_POST['stats_corners_away'] !== '' ? (int)$_POST['stats_corners_away'] : null;
    $stats_fouls_home = isset($_POST['stats_fouls_home']) && $_POST['stats_fouls_home'] !== '' ? (int)$_POST['stats_fouls_home'] : null;
    $stats_fouls_away = isset($_POST['stats_fouls_away']) && $_POST['stats_fouls_away'] !== '' ? (int)$_POST['stats_fouls_away'] : null;
    $bench_home = trim($_POST['bench_home'] ?? '');
    $bench_away = trim($_POST['bench_away'] ?? '');
    $absent_home = trim($_POST['absent_home'] ?? '');
    $absent_away = trim($_POST['absent_away'] ?? '');
    $match_news = trim($_POST['match_news'] ?? '');
    $player_stats_home = trim($_POST['player_stats_home'] ?? '');
    $player_stats_away = trim($_POST['player_stats_away'] ?? '');

    if ($date === '') {
        $errors[] = 'يرجى إدخال التاريخ.';
    }
    if ($team_home === '' || $team_away === '') {
        $errors[] = 'يرجى إدخال اسم الفريقين.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('UPDATE matches SET match_date = ?, match_time = ?, team_home = ?, team_away = ?, venue = ?, championship = ?, channel = ?, commentator = ?, stream_url = ?, coach_home = ?, coach_away = ?, coach_home_image = ?, coach_away_image = ?, stats_possession_home = ?, stats_possession_away = ?, stats_shots_home = ?, stats_shots_away = ?, stats_corners_home = ?, stats_corners_away = ?, stats_fouls_home = ?, stats_fouls_away = ?, bench_home = ?, bench_away = ?, absent_home = ?, absent_away = ?, match_news = ?, player_stats_home = ?, player_stats_away = ? WHERE id = ?');
        $stmt->execute([$date, $time, $team_home, $team_away, $venue, $championship, $channel, $commentator, $stream_url, $coach_home, $coach_away, $coach_home_image, $coach_away_image, $stats_possession_home, $stats_possession_away, $stats_shots_home, $stats_shots_away, $stats_corners_home, $stats_corners_away, $stats_fouls_home, $stats_fouls_away, $bench_home, $bench_away, $absent_home, $absent_away, $match_news, $player_stats_home, $player_stats_away, $id]);
        $_SESSION['success_message'] = 'تم تعديل المباراة بنجاح.';
        header('Location: dashboard.php');
        exit;
    }
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تعديل المباراة - FozScore</title>
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
        }
        body {
            font-family: 'Tajawal', sans-serif;
            background-color: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 0;
        }
        .navbar { background-color: var(--primary); color: #fff; padding: 1rem 1.5rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; align-items: center; }
        .navbar .brand { font-size: 1.5rem; font-weight: 800; text-decoration: none; color: #fff; }
        
        .container { max-width: 700px; margin: 2rem auto; padding: 0 1rem; }
        .page-title { font-size: 1.8rem; font-weight: 800; color: var(--primary); margin-bottom: 1.5rem; text-align: center; }
        
        .form-card {
            background: var(--card);
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03);
            padding: 2rem;
        }
        
        .form-row { display: flex; gap: 1rem; margin-bottom: 0.5rem; }
        .form-group { flex: 1; margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text); }
        .form-group input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-family: inherit;
            font-size: 1rem;
            box-sizing: border-box;
            transition: border-color 0.2s;
        }
        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }
        .form-group input:focus { outline: none; border-color: var(--secondary); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
        
        .form-actions { display: flex; gap: 1rem; margin-top: 1.5rem; }
        .btn {
            flex: 1;
            padding: 12px;
            border-radius: 10px;
            font-weight: 700;
            text-align: center;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.2s;
        }
        .btn-primary { background-color: var(--secondary); color: white; }
        .btn-primary:hover { background-color: #1d4ed8; }
        .btn-secondary { background-color: #e2e8f0; color: var(--text); }
        .btn-secondary:hover { background-color: #cbd5e1; }
        
        .errors { background: #fee2e2; border: 1px solid #fecaca; color: #b91c1c; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; }
        .errors ul { margin: 0; padding-right: 1.5rem; }
        
        @media (max-width: 600px) {
            .form-row { flex-direction: column; gap: 0; }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <a class="brand" href="dashboard.php">لوحة تحكم FozScore</a>
    </div>
    <div class="container">
        <h1 class="page-title">تعديل المباراة</h1>
        <?php if (!empty($errors)): ?>
            <div class="errors">
                <ul>
                    <?php foreach ($errors as $e): ?>
                        <li><?php echo htmlspecialchars($e); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <form method="post" class="form-card">
            <div class="form-row">
                <div class="form-group">
                    <label>التاريخ</label>
                    <input type="date" name="match_date" value="<?php echo htmlspecialchars($_POST['match_date'] ?? $match['match_date'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>الوقت</label>
                    <input type="time" name="match_time" value="<?php echo htmlspecialchars($_POST['match_time'] ?? $match['match_time'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>الفريق المستضيف</label>
                    <input type="text" name="team_home" value="<?php echo htmlspecialchars($_POST['team_home'] ?? $match['team_home'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>الفريق الضيف</label>
                    <input type="text" name="team_away" value="<?php echo htmlspecialchars($_POST['team_away'] ?? $match['team_away'] ?? ''); ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label>البطولة</label>
                <input type="text" name="championship" value="<?php echo htmlspecialchars($_POST['championship'] ?? $match['championship'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label>الملعب</label>
                <input type="text" name="venue" value="<?php echo htmlspecialchars($_POST['venue'] ?? $match['venue'] ?? ''); ?>">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>القناة الناقلة</label>
                    <input type="text" name="channel" value="<?php echo htmlspecialchars($_POST['channel'] ?? $match['channel'] ?? ''); ?>" placeholder="مثال: beIN Sports 1">
                </div>
                <div class="form-group">
                    <label>المعلق</label>
                    <input type="text" name="commentator" value="<?php echo htmlspecialchars($_POST['commentator'] ?? $match['commentator'] ?? ''); ?>" placeholder="مثال: عصام الشوالي">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>مدرب المستضيف</label>
                    <input type="text" name="coach_home" value="<?php echo htmlspecialchars($_POST['coach_home'] ?? $match['coach_home'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>مدرب الضيف</label>
                    <input type="text" name="coach_away" value="<?php echo htmlspecialchars($_POST['coach_away'] ?? $match['coach_away'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>صورة مدرب المستضيف (رابط)</label>
                    <input type="text" name="coach_home_image" value="<?php echo htmlspecialchars($_POST['coach_home_image'] ?? $match['coach_home_image'] ?? ''); ?>" placeholder="https://...">
                </div>
                <div class="form-group">
                    <label>صورة مدرب الضيف (رابط)</label>
                    <input type="text" name="coach_away_image" value="<?php echo htmlspecialchars($_POST['coach_away_image'] ?? $match['coach_away_image'] ?? ''); ?>" placeholder="https://...">
                </div>
            </div>

            <h3 style="margin-top: 1.5rem; margin-bottom: 1rem; color: var(--primary);">إحصائيات المباراة</h3>
            <div class="form-row">
                <div class="form-group">
                    <label>الاستحواذ % (مستضيف)</label>
                    <input type="number" name="stats_possession_home" value="<?php echo htmlspecialchars($_POST['stats_possession_home'] ?? $match['stats_possession_home'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>الاستحواذ % (ضيف)</label>
                    <input type="number" name="stats_possession_away" value="<?php echo htmlspecialchars($_POST['stats_possession_away'] ?? $match['stats_possession_away'] ?? ''); ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>التسديدات (مستضيف)</label>
                    <input type="number" name="stats_shots_home" value="<?php echo htmlspecialchars($_POST['stats_shots_home'] ?? $match['stats_shots_home'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>التسديدات (ضيف)</label>
                    <input type="number" name="stats_shots_away" value="<?php echo htmlspecialchars($_POST['stats_shots_away'] ?? $match['stats_shots_away'] ?? ''); ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>الركنيات (مستضيف)</label>
                    <input type="number" name="stats_corners_home" value="<?php echo htmlspecialchars($_POST['stats_corners_home'] ?? $match['stats_corners_home'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>الركنيات (ضيف)</label>
                    <input type="number" name="stats_corners_away" value="<?php echo htmlspecialchars($_POST['stats_corners_away'] ?? $match['stats_corners_away'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-row">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>مقاعد بدلاء المستضيف</label>
                    <textarea name="bench_home" placeholder="الاسم | الرقم"><?php echo htmlspecialchars($_POST['bench_home'] ?? $match['bench_home'] ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label>مقاعد بدلاء الضيف</label>
                    <textarea name="bench_away" placeholder="الاسم | الرقم"><?php echo htmlspecialchars($_POST['bench_away'] ?? $match['bench_away'] ?? ''); ?></textarea>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>غيابات المستضيف (إصابات/إيقاف)</label>
                    <textarea name="absent_home" placeholder="الاسم | نوع الإصابة"><?php echo htmlspecialchars($_POST['absent_home'] ?? $match['absent_home'] ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label>غيابات الضيف (إصابات/إيقاف)</label>
                    <textarea name="absent_away" placeholder="الاسم | نوع الإصابة"><?php echo htmlspecialchars($_POST['absent_away'] ?? $match['absent_away'] ?? ''); ?></textarea>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>إحصائيات لاعبي المستضيف (الاسم | أهداف | تمريرات)</label>
                    <textarea name="player_stats_home" placeholder="بنزيما | 2 | 1"><?php echo htmlspecialchars($_POST['player_stats_home'] ?? $match['player_stats_home'] ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label>إحصائيات لاعبي الضيف (الاسم | أهداف | تمريرات)</label>
                    <textarea name="player_stats_away" placeholder="ليفاندوفسكي | 1 | 0"><?php echo htmlspecialchars($_POST['player_stats_away'] ?? $match['player_stats_away'] ?? ''); ?></textarea>
                </div>
            </div>

            <div class="form-group">
                <label>أخبار المباراة (خبر لكل سطر)</label>
                <textarea name="match_news" placeholder="أدخل الأخبار المتعلقة بالمباراة هنا..."><?php echo htmlspecialchars($_POST['match_news'] ?? $match['match_news'] ?? ''); ?></textarea>
            </div>

            <?php if (!empty($match['match_stats'])): ?>
            <div class="form-group" style="background: #f1f5f9; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0;">
                <label style="color: #0891b2; margin-bottom: 10px;">📊 الإحصائيات المسحوبة تلقائياً (للعرض فقط)</label>
                <div style="font-size: 0.9rem;">
                    <?php 
                        echo render_match_stats($match['match_stats'], $match['team_home'], $match['team_away'], $match['team_home_logo'] ?? null, $match['team_away_logo'] ?? null); 
                    ?>
                </div>
                <div style="margin-top: 10px; font-size: 0.8rem; color: #64748b;">
                    * هذه البيانات مخزنة تلقائياً. لتحديثها، استخدم زر "سحب إحصائيات وتشكيلات" من لوحة التحكم.
                </div>
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label>كود البث المباشر (Embed Code) أو الرابط</label>
                <input type="text" name="stream_url" value="<?php echo htmlspecialchars($_POST['stream_url'] ?? $match['stream_url'] ?? ''); ?>" placeholder='<iframe src="..."></iframe> أو الرابط'>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">حفظ التعديلات</button>
                <a class="btn btn-secondary" href="dashboard.php">إلغاء</a>
            </div>
        </form>
    </div>
</body>
</html>
