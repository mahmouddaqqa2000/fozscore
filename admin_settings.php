<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // تحديث اسم الموقع
    if (isset($_POST['site_name'])) {
        $site_name = trim($_POST['site_name']);
        $stmt = $pdo->prepare("INSERT OR REPLACE INTO settings (key_name, value) VALUES ('site_name', ?)");
        $stmt->execute([$site_name]);
        $message = "تم حفظ الإعدادات بنجاح.";
    }

    // رفع الشعار
    if (isset($_FILES['favicon']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['ico', 'png', 'jpg', 'jpeg', 'svg'];
        $filename = $_FILES['favicon']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $uploadDir = __DIR__ . '/assets/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            
            $newFilename = 'favicon_' . time() . '.' . $ext;
            $targetPath = $uploadDir . $newFilename;
            
            if (move_uploaded_file($_FILES['favicon']['tmp_name'], $targetPath)) {
                $faviconUrl = 'assets/' . $newFilename;
                $stmt = $pdo->prepare("INSERT OR REPLACE INTO settings (key_name, value) VALUES ('favicon', ?)");
                $stmt->execute([$faviconUrl]);
                $message = "تم رفع الشعار وحفظ الإعدادات بنجاح.";
            } else {
                $message = "حدث خطأ أثناء رفع الملف.";
            }
        } else {
            $message = "صيغة الملف غير مدعومة. يرجى رفع صورة (ico, png, jpg, svg).";
        }
    }
}

$settings = get_site_settings($pdo);
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>إعدادات الموقع</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Tajawal', sans-serif; background: #f8fafc; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        h1 { margin-top: 0; color: #1e293b; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: bold; color: #334155; }
        input[type="text"] { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit; box-sizing: border-box; }
        .btn { background: #2563eb; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-family: inherit; font-weight: bold; }
        .btn:hover { background: #1d4ed8; }
        .alert { padding: 15px; background: #dcfce7; color: #166534; border-radius: 6px; margin-bottom: 20px; }
        .current-favicon { margin-top: 10px; }
        .current-favicon img { max-width: 64px; border: 1px solid #e2e8f0; padding: 4px; border-radius: 4px; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #64748b; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <a href="bot_dashboard.php" class="back-link">← العودة للوحة التحكم</a>
        <h1>إعدادات الموقع</h1>
        
        <?php if ($message): ?>
            <div class="alert"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <form method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="site_name">اسم الموقع (يظهر في تبويب المتصفح)</label>
                <input type="text" id="site_name" name="site_name" value="<?php echo htmlspecialchars($settings['site_name']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="favicon">شعار الموقع (Favicon)</label>
                <input type="file" id="favicon" name="favicon" accept=".ico,.png,.jpg,.jpeg,.svg">
                <?php if (!empty($settings['favicon'])): ?>
                    <div class="current-favicon">
                        <p style="margin: 5px 0; font-size: 0.9rem; color: #64748b;">الشعار الحالي:</p>
                        <img src="<?php echo htmlspecialchars($settings['favicon']); ?>" alt="Favicon">
                    </div>
                <?php endif; ?>
            </div>
            
            <button type="submit" class="btn">حفظ التغييرات</button>
        </form>
    </div>
</body>
</html>