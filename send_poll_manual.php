<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

$settings = get_site_settings($pdo);
$favicon = $settings['favicon'];

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $match_id = $_POST['match_id'] ?? null;
    
    if ($match_id) {
        $stmt = $pdo->prepare("SELECT * FROM matches WHERE id = ?");
        $stmt->execute([$match_id]);
        $match = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($match) {
            $question = "🗳️ توقعاتكم للمباراة:\n" . $match['team_home'] . " 🆚 " . $match['team_away'];
            $options = ["فوز " . $match['team_home'], "تعادل", "فوز " . $match['team_away']];
            
            $result = send_telegram_poll($pdo, $question, $options, $match['championship']);
            $res = json_decode($result, true);
            
            if ($res && isset($res['ok']) && $res['ok']) {
                $message = '<div class="alert alert-success">تم إرسال الاستفتاء بنجاح!</div>';
            } else {
                $error = $res['description'] ?? 'خطأ غير معروف';
                $message = '<div class="alert alert-danger">فشل الإرسال: ' . htmlspecialchars($error) . '</div>';
            }
        } else {
            $message = '<div class="alert alert-danger">المباراة غير موجودة.</div>';
        }
    } else {
        $message = '<div class="alert alert-danger">يرجى اختيار مباراة.</div>';
    }
}

// جلب المباريات القادمة (اليوم وغداً)
$stmt = $pdo->query("SELECT id, team_home, team_away, match_time, match_date, championship FROM matches WHERE match_date >= DATE('now') ORDER BY match_date ASC, match_time ASC");
$matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>إرسال استفتاء يدوي - FozScore</title>
    <?php if ($favicon): ?><link rel="icon" href="<?php echo htmlspecialchars($favicon); ?>"><?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Tajawal', sans-serif; background: #f8fafc; padding: 2rem; direction: rtl; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        h2 { margin-top: 0; color: #1e293b; }
        .form-group { margin-bottom: 15px; }
        .form-input { width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px; font-family: inherit; box-sizing: border-box; }
        .btn { padding: 12px 20px; background: #f97316; color: white; border: none; border-radius: 8px; cursor: pointer; width: 100%; font-weight: bold; font-size: 1rem; transition: background 0.2s; }
        .btn:hover { background: #ea580c; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-danger { background: #fee2e2; color: #991b1b; }
        .back-link { display: block; text-align: center; margin-top: 20px; color: #64748b; text-decoration: none; }
        .back-link:hover { color: #1e293b; }
    </style>
</head>
<body>
    <div class="container">
        <h2>🗳️ إرسال استفتاء يدوي</h2>
        <p style="color: #64748b; margin-bottom: 20px;">اختر مباراة لإرسال استفتاء "توقعات الفوز" إلى مجموعة التيليجرام.</p>
        
        <?php echo $message; ?>
        
        <form method="post">
            <div class="form-group">
                <label style="display:block; margin-bottom:8px; font-weight:bold;">اختر المباراة:</label>
                <select name="match_id" class="form-input" required>
                    <option value="">-- اختر مباراة --</option>
                    <?php foreach ($matches as $m): ?>
                        <option value="<?php echo $m['id']; ?>">
                            <?php echo htmlspecialchars($m['match_date'] . ' | ' . $m['team_home'] . ' vs ' . $m['team_away']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn">إرسال الاستفتاء الآن</button>
        </form>
        
        <a href="bot_dashboard.php" class="back-link">العودة للوحة التحكم</a>
    </div>
</body>
</html>