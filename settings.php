<?php
session_start();
// حماية الصفحة: التحقق من تسجيل الدخول
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

$message = '';

// معالجة النموذج عند الإرسال
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $site_name = trim($_POST['site_name'] ?? '');
    $favicon_url = trim($_POST['favicon_url'] ?? '');
    $site_url = trim($_POST['site_url'] ?? '');
    $primary_color = trim($_POST['primary_color'] ?? '');
    $site_description = trim($_POST['site_description'] ?? '');
    $social_twitter = trim($_POST['social_twitter'] ?? '');
    $social_facebook = trim($_POST['social_facebook'] ?? '');
    $social_youtube = trim($_POST['social_youtube'] ?? '');
    $social_instagram = trim($_POST['social_instagram'] ?? '');
    $telegram_bot_token = trim($_POST['telegram_bot_token'] ?? '');
    $telegram_chat_id = trim($_POST['telegram_chat_id'] ?? '');
    $twitter_api_key = trim($_POST['twitter_api_key'] ?? '');
    $twitter_api_secret = trim($_POST['twitter_api_secret'] ?? '');
    $twitter_access_token = trim($_POST['twitter_access_token'] ?? '');
    $twitter_access_token_secret = trim($_POST['twitter_access_token_secret'] ?? '');

    // معالجة رفع ملف الشعار (إذا تم اختيار ملف)
    if (isset($_FILES['favicon_file']) && $_FILES['favicon_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/assets/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileInfo = pathinfo($_FILES['favicon_file']['name']);
        $extension = strtolower($fileInfo['extension']);
        $allowedExtensions = ['png', 'jpg', 'jpeg', 'gif', 'ico', 'svg'];
        
        if (in_array($extension, $allowedExtensions)) {
            $newFileName = 'favicon_' . time() . '.' . $extension;
            $targetPath = $uploadDir . $newFileName;
            
            if (move_uploaded_file($_FILES['favicon_file']['tmp_name'], $targetPath)) {
                $favicon_url = 'assets/uploads/' . $newFileName;
            } else {
                $message = '<div class="alert alert-danger">حدث خطأ أثناء رفع الملف.</div>';
            }
        } else {
            $message = '<div class="alert alert-danger">نوع الملف غير مدعوم. يرجى رفع صورة (PNG, JPG, ICO, SVG).</div>';
        }
    }

    // حفظ الإعدادات في قاعدة البيانات
    try {
        $stmt = $pdo->prepare("INSERT OR REPLACE INTO settings (key_name, value) VALUES (?, ?)");
        
        if (!empty($site_name)) {
            $stmt->execute(['site_name', $site_name]);
        }
        
        // تحديث الشعار فقط إذا تم إدخال رابط جديد أو رفع ملف
        if (!empty($favicon_url)) {
            $stmt->execute(['favicon', $favicon_url]);
        }
        
        // حفظ باقي الإعدادات
        if (!empty($site_url)) $stmt->execute(['site_url', rtrim($site_url, '/')]); // نحفظ الرابط بدون الشرطة في النهاية
        $stmt->execute(['primary_color', $primary_color]);
        $stmt->execute(['site_description', $site_description]);
        $stmt->execute(['social_twitter', $social_twitter]);
        $stmt->execute(['social_facebook', $social_facebook]);
        $stmt->execute(['social_youtube', $social_youtube]);
        $stmt->execute(['social_instagram', $social_instagram]);
        $stmt->execute(['telegram_bot_token', $telegram_bot_token]);
        $stmt->execute(['telegram_chat_id', $telegram_chat_id]);
        $stmt->execute(['twitter_api_key', $twitter_api_key]);
        $stmt->execute(['twitter_api_secret', $twitter_api_secret]);
        $stmt->execute(['twitter_access_token', $twitter_access_token]);
        $stmt->execute(['twitter_access_token_secret', $twitter_access_token_secret]);

        $message = '<div class="alert alert-success">تم حفظ الإعدادات بنجاح!</div>';
    } catch (PDOException $e) {
        $message = '<div class="alert alert-danger">خطأ في قاعدة البيانات: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// جلب الإعدادات الحالية لعرضها في النموذج
$settings = get_site_settings($pdo);
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>إعدادات الموقع - FozScore</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #1e293b; --secondary: #2563eb; --bg: #f1f5f9; --card: #ffffff; --text: #0f172a; --border: #e2e8f0; }
        body { font-family: 'Tajawal', sans-serif; background-color: var(--bg); color: var(--text); margin: 0; padding: 0; }
        .navbar { background-color: var(--primary); color: #fff; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; }
        .navbar .brand { font-size: 1.5rem; font-weight: 800; text-decoration: none; color: #fff; }
        .navbar .nav-links a { color: #cbd5e1; text-decoration: none; margin-left: 15px; font-weight: 500; }
        .navbar .nav-links a:hover { color: #fff; }
        .container { max-width: 800px; margin: 3rem auto; padding: 0 1.5rem; }
        .card { background: var(--card); border-radius: 16px; padding: 2rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid var(--border); }
        .form-group { margin-bottom: 1.5rem; }
        .form-label { display: block; margin-bottom: 0.5rem; font-weight: 700; }
        .form-input { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px; font-family: inherit; box-sizing: border-box; }
        .btn-save { background: var(--secondary); color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 700; cursor: pointer; width: 100%; font-size: 1rem; }
        .btn-save:hover { background: #1d4ed8; }
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-danger { background: #fee2e2; color: #991b1b; }
        .preview-img { max-width: 100px; max-height: 100px; margin-top: 10px; border: 1px solid var(--border); padding: 5px; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="navbar">
        <a class="brand" href="bot_dashboard.php">🤖 لوحة التحكم</a>
        <div class="nav-links">
            <a href="bot_dashboard.php">الرئيسية</a>
            <a href="index.php" target="_blank">عرض الموقع</a>
        </div>
    </div>
    <div class="container">
        <h1 style="margin-bottom: 2rem; color: var(--primary);">⚙️ إعدادات الموقع</h1>
        <?php echo $message; ?>
        <div class="card">
            <form method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label class="form-label">اسم الموقع</label>
                    <input type="text" name="site_name" class="form-input" value="<?php echo htmlspecialchars($settings['site_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">شعار الموقع (Favicon)</label>
                    <div style="margin-bottom: 10px; font-size: 0.9rem; color: #64748b;">يمكنك وضع رابط مباشر للصورة أو رفع ملف من جهازك.</div>
                    <input type="text" name="favicon_url" class="form-input" placeholder="https://example.com/favicon.ico" value="<?php echo htmlspecialchars($settings['favicon']); ?>" style="direction: ltr;">
                    <div style="margin-top: 10px;">
                        <label style="cursor: pointer; background: #f1f5f9; padding: 8px 15px; border-radius: 6px; border: 1px solid var(--border); display: inline-block;">
                            📂 رفع صورة من الجهاز
                            <input type="file" name="favicon_file" style="display: none;" onchange="document.getElementById('file-name').textContent = this.files[0].name">
                        </label>
                        <span id="file-name" style="margin-right: 10px; font-size: 0.9rem;"></span>
                    </div>
                    <?php if (!empty($settings['favicon'])): ?>
                        <div style="margin-top: 15px;">
                            <div>المعاينة الحالية:</div>
                            <img src="<?php echo htmlspecialchars($settings['favicon']); ?>" class="preview-img" alt="Favicon">
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label class="form-label">رابط الموقع الأساسي (URL)</label>
                    <input type="url" name="site_url" class="form-input" value="<?php echo htmlspecialchars($settings['site_url']); ?>" placeholder="https://example.com" required style="direction: ltr;">
                </div>
                
                <div class="form-group">
                    <label class="form-label">اللون الرئيسي للموقع</label>
                    <input type="color" name="primary_color" class="form-input" value="<?php echo htmlspecialchars($settings['primary_color']); ?>" style="height: 50px; padding: 5px;">
                </div>
                
                <div class="form-group">
                    <label class="form-label">وصف الموقع (يظهر في الفوتر)</label>
                    <textarea name="site_description" class="form-input" rows="3"><?php echo htmlspecialchars($settings['site_description']); ?></textarea>
                </div>

                <h3 style="margin-top: 2rem; margin-bottom: 1rem; color: var(--primary); border-bottom: 1px solid var(--border); padding-bottom: 10px;">🔗 روابط التواصل الاجتماعي</h3>
                
                <div class="form-group">
                    <label class="form-label">رابط فيسبوك</label>
                    <input type="text" name="social_facebook" class="form-input" value="<?php echo htmlspecialchars($settings['social_facebook']); ?>" placeholder="https://facebook.com/..." style="direction: ltr;">
                </div>
                <div class="form-group">
                    <label class="form-label">رابط تويتر (X)</label>
                    <input type="text" name="social_twitter" class="form-input" value="<?php echo htmlspecialchars($settings['social_twitter']); ?>" placeholder="https://twitter.com/..." style="direction: ltr;">
                </div>
                <div class="form-group">
                    <label class="form-label">رابط يوتيوب</label>
                    <input type="text" name="social_youtube" class="form-input" value="<?php echo htmlspecialchars($settings['social_youtube']); ?>" placeholder="https://youtube.com/..." style="direction: ltr;">
                </div>
                <div class="form-group">
                    <label class="form-label">رابط انستجرام</label>
                    <input type="text" name="social_instagram" class="form-input" value="<?php echo htmlspecialchars($settings['social_instagram']); ?>" placeholder="https://instagram.com/..." style="direction: ltr;">
                </div>

                <h3 style="margin-top: 2rem; margin-bottom: 1rem; color: var(--primary); border-bottom: 1px solid var(--border); padding-bottom: 10px;">🤖 إعدادات بوت تيليجرام</h3>
                <div class="form-group">
                    <label class="form-label">توكن البوت (Bot Token)</label>
                    <input type="text" name="telegram_bot_token" class="form-input" value="<?php echo htmlspecialchars($settings['telegram_bot_token']); ?>" placeholder="123456789:ABC..." style="direction: ltr;">
                </div>
                <div class="form-group">
                    <label class="form-label">معرف المجموعة (Chat ID)</label>
                    <input type="text" name="telegram_chat_id" class="form-input" value="<?php echo htmlspecialchars($settings['telegram_chat_id']); ?>" placeholder="-100..." style="direction: ltr;">
                </div>

                <h3 style="margin-top: 2rem; margin-bottom: 1rem; color: var(--primary); border-bottom: 1px solid var(--border); padding-bottom: 10px;">🐦 إعدادات النشر التلقائي على تويتر (X)</h3>
                <div class="form-group">
                    <label class="form-label">API Key (Consumer Key)</label>
                    <input type="text" name="twitter_api_key" class="form-input" value="<?php echo htmlspecialchars($settings['twitter_api_key']); ?>" style="direction: ltr;">
                </div>
                <div class="form-group">
                    <label class="form-label">API Secret (Consumer Secret)</label>
                    <input type="text" name="twitter_api_secret" class="form-input" value="<?php echo htmlspecialchars($settings['twitter_api_secret']); ?>" style="direction: ltr;">
                </div>
                <div class="form-group">
                    <label class="form-label">Access Token</label>
                    <input type="text" name="twitter_access_token" class="form-input" value="<?php echo htmlspecialchars($settings['twitter_access_token']); ?>" style="direction: ltr;">
                </div>
                <div class="form-group">
                    <label class="form-label">Access Token Secret</label>
                    <input type="text" name="twitter_access_token_secret" class="form-input" value="<?php echo htmlspecialchars($settings['twitter_access_token_secret']); ?>" style="direction: ltr;">
                </div>

                <button type="submit" class="btn-save">حفظ التغييرات</button>
            </form>
        </div>
    </div>
</body>
</html>