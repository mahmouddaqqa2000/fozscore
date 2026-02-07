<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

$message = '';

// معالجة الحفظ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings_to_save = [
        'site_name', 'site_description', 'site_url', 'favicon', 'primary_color',
        'social_twitter', 'social_facebook', 'social_youtube', 'social_instagram',
        'telegram_bot_token', 'telegram_chat_id',
        'twitter_api_key', 'twitter_api_secret', 'twitter_access_token', 'twitter_access_token_secret',
        'ad_code_header', 'ad_code_body', 'ad_code_footer', 'ad_code_sidebar'
    ];

    $stmt = $pdo->prepare("INSERT OR REPLACE INTO settings (key_name, value) VALUES (?, ?)");
    
    foreach ($settings_to_save as $key) {
        if (isset($_POST[$key])) {
            $stmt->execute([$key, $_POST[$key]]);
        }
    }
    $message = 'تم حفظ الإعدادات بنجاح ✅';
}

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
        body { font-family: 'Tajawal', sans-serif; background-color: #f1f5f9; color: #1e293b; margin: 0; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        h1 { margin-top: 0; border-bottom: 2px solid #e2e8f0; padding-bottom: 15px; color: #0f172a; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 700; color: #334155; }
        input[type="text"], input[type="url"], input[type="color"], textarea {
            width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px;
            font-family: inherit; font-size: 1rem; box-sizing: border-box;
        }
        textarea { min-height: 120px; resize: vertical; direction: ltr; font-family: monospace; font-size: 0.9rem; }
        .btn-save {
            background-color: #2563eb; color: white; padding: 12px 30px; border: none;
            border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 1rem;
            transition: background 0.2s; display: block; width: 100%;
        }
        .btn-save:hover { background-color: #1d4ed8; }
        .alert { padding: 15px; background-color: #dcfce7; color: #166534; border-radius: 8px; margin-bottom: 20px; font-weight: 700; text-align: center; }
        .section-title { margin-top: 40px; margin-bottom: 20px; font-size: 1.3rem; color: #2563eb; font-weight: 800; display: flex; align-items: center; gap: 10px; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #64748b; text-decoration: none; font-weight: 600; }
        .nav-tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; }
        .nav-tab { padding: 10px 20px; cursor: pointer; border-radius: 8px; font-weight: 600; color: #64748b; }
        .nav-tab.active { background-color: #eff6ff; color: #2563eb; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
    <script>
        function openTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.nav-tab').forEach(el => el.classList.remove('active'));
            document.getElementById(tabName).classList.add('active');
            document.getElementById('btn-' + tabName).classList.add('active');
        }
    </script>
</head>
<body>
    <div class="container">
        <a href="bot_dashboard.php" class="back-link">← العودة للوحة التحكم</a>
        <h1>⚙️ إعدادات الموقع</h1>
        
        <?php if ($message): ?>
            <div class="alert"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="nav-tabs">
            <div id="btn-general" class="nav-tab active" onclick="openTab('general')">عامة</div>
            <div id="btn-ads" class="nav-tab" onclick="openTab('ads')">الإعلانات (AdSense)</div>
            <div id="btn-social" class="nav-tab" onclick="openTab('social')">التواصل الاجتماعي</div>
            <div id="btn-api" class="nav-tab" onclick="openTab('api')">API وربط الخدمات</div>
        </div>

        <form method="post">
            <!-- تبويب عام -->
            <div id="general" class="tab-content active">
                <div class="form-group">
                    <label>اسم الموقع</label>
                    <input type="text" name="site_name" value="<?php echo htmlspecialchars($settings['site_name']); ?>">
                </div>
                <div class="form-group">
                    <label>وصف الموقع (SEO)</label>
                    <textarea name="site_description" style="min-height: 80px; direction: rtl; font-family: inherit;"><?php echo htmlspecialchars($settings['site_description']); ?></textarea>
                </div>
                <div class="form-group">
                    <label>رابط الموقع (URL)</label>
                    <input type="url" name="site_url" value="<?php echo htmlspecialchars($settings['site_url']); ?>" placeholder="https://example.com">
                </div>
                <div class="form-group">
                    <label>رابط أيقونة الموقع (Favicon)</label>
                    <input type="text" name="favicon" value="<?php echo htmlspecialchars($settings['favicon']); ?>">
                </div>
                <div class="form-group">
                    <label>اللون الرئيسي</label>
                    <input type="color" name="primary_color" value="<?php echo htmlspecialchars($settings['primary_color']); ?>" style="height: 50px;">
                </div>
            </div>

            <!-- تبويب الإعلانات -->
            <div id="ads" class="tab-content">
                <div style="background: #fffbeb; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #fcd34d; color: #92400e;">
                    💡 ضع أكواد HTML/JS الخاصة بالإعلانات (مثل Google AdSense) في الحقول أدناه. ستظهر تلقائياً في الأماكن المخصصة.
                </div>
                <div class="form-group">
                    <label>إعلان الهيدر (أعلى جميع الصفحات)</label>
                    <textarea name="ad_code_header" placeholder="<script>...</script>"><?php echo htmlspecialchars($settings['ad_code_header']); ?></textarea>
                </div>
                <div class="form-group">
                    <label>إعلان وسط المحتوى (داخل صفحة المباراة)</label>
                    <textarea name="ad_code_body" placeholder="<script>...</script>"><?php echo htmlspecialchars($settings['ad_code_body']); ?></textarea>
                </div>
                <div class="form-group">
                    <label>إعلان الفوتر (أسفل جميع الصفحات)</label>
                    <textarea name="ad_code_footer" placeholder="<script>...</script>"><?php echo htmlspecialchars($settings['ad_code_footer']); ?></textarea>
                </div>
                <div class="form-group">
                    <label>إعلان القائمة الجانبية (يظهر فقط على الكمبيوتر)</label>
                    <textarea name="ad_code_sidebar" placeholder="<script>...</script>"><?php echo htmlspecialchars($settings['ad_code_sidebar']); ?></textarea>
                </div>
            </div>

            <!-- تبويب التواصل -->
            <div id="social" class="tab-content">
                <div class="form-group">
                    <label>رابط تويتر (X)</label>
                    <input type="text" name="social_twitter" value="<?php echo htmlspecialchars($settings['social_twitter']); ?>">
                </div>
                <div class="form-group">
                    <label>رابط فيسبوك</label>
                    <input type="text" name="social_facebook" value="<?php echo htmlspecialchars($settings['social_facebook']); ?>">
                </div>
                <div class="form-group">
                    <label>رابط يوتيوب</label>
                    <input type="text" name="social_youtube" value="<?php echo htmlspecialchars($settings['social_youtube']); ?>">
                </div>
                <div class="form-group">
                    <label>رابط انستجرام</label>
                    <input type="text" name="social_instagram" value="<?php echo htmlspecialchars($settings['social_instagram']); ?>">
                </div>
            </div>

            <!-- تبويب API -->
            <div id="api" class="tab-content">
                <div class="section-title">إعدادات تيليجرام</div>
                <div class="form-group">
                    <label>Bot Token</label>
                    <input type="text" name="telegram_bot_token" value="<?php echo htmlspecialchars($settings['telegram_bot_token']); ?>">
                </div>
                <div class="form-group">
                    <label>Chat ID (القناة أو المجموعة)</label>
                    <input type="text" name="telegram_chat_id" value="<?php echo htmlspecialchars($settings['telegram_chat_id']); ?>">
                </div>

                <div class="section-title">إعدادات تويتر (X API)</div>
                <div class="form-group">
                    <label>API Key</label>
                    <input type="text" name="twitter_api_key" value="<?php echo htmlspecialchars($settings['twitter_api_key']); ?>">
                </div>
                <div class="form-group">
                    <label>API Secret</label>
                    <input type="text" name="twitter_api_secret" value="<?php echo htmlspecialchars($settings['twitter_api_secret']); ?>">
                </div>
                <div class="form-group">
                    <label>Access Token</label>
                    <input type="text" name="twitter_access_token" value="<?php echo htmlspecialchars($settings['twitter_access_token']); ?>">
                </div>
                <div class="form-group">
                    <label>Access Token Secret</label>
                    <input type="text" name="twitter_access_token_secret" value="<?php echo htmlspecialchars($settings['twitter_access_token_secret']); ?>">
                </div>
            </div>

            <button type="submit" class="btn-save">حفظ التغييرات</button>
        </form>
    </div>
</body>
</html>
```
<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/db.php';

$message = '';
$msg_type = '';

// دالة لجلب إعدادات البوت الجديد
function get_sec_bot_settings($pdo) {
    $stmt = $pdo->query("SELECT key_name, value FROM secondary_bot_settings");
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

// معالجة الحفظ والإرسال
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. حفظ الإعدادات
    if (isset($_POST['save_settings'])) {
        $token = trim($_POST['bot_token']);
        $chat_id = trim($_POST['chat_id']);
        
        $stmt = $pdo->prepare("INSERT OR REPLACE INTO secondary_bot_settings (key_name, value) VALUES (?, ?)");
        $stmt->execute(['bot_token', $token]);
        $stmt->execute(['chat_id', $chat_id]);
        
        $message = "تم حفظ إعدادات البوت بنجاح ✅";
        $msg_type = "success";
    }
    
    // 2. إرسال رسالة تجريبية
    if (isset($_POST['send_message'])) {
        $settings = get_sec_bot_settings($pdo);
        $token = $settings['bot_token'] ?? '';
        $chat_id = $settings['chat_id'] ?? '';
        $text = $_POST['message_text'] ?? '';
        
        if ($token && $chat_id && $text) {
            $url = "https://api.telegram.org/bot$token/sendMessage";
            $data = [
                'chat_id' => $chat_id,
                'text' => $text,
                'parse_mode' => 'HTML'
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $res = json_decode($result, true);
            if ($res && $res['ok']) {
                $message = "تم إرسال الرسالة بنجاح 🚀";
                $msg_type = "success";
            } else {
                $error = $res['description'] ?? 'خطأ غير معروف';
                $message = "فشل الإرسال: $error";
                $msg_type = "error";
            }
        } else {
            $message = "يرجى التأكد من حفظ الإعدادات وكتابة نص الرسالة.";
            $msg_type = "error";
        }
    }
}

$settings = get_sec_bot_settings($pdo);
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>إدارة البوت الجديد - FozScore</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Tajawal', sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header h1 { margin: 0; color: #0f172a; }
        .back-btn { text-decoration: none; background: #e2e8f0; color: #475569; padding: 10px 20px; border-radius: 8px; font-weight: bold; transition: 0.2s; }
        .back-btn:hover { background: #cbd5e1; color: #1e293b; }
        
        .card { background: white; padding: 25px; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); margin-bottom: 25px; border: 1px solid #e2e8f0; }
        .card h2 { margin-top: 0; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 20px; color: #334155; font-size: 1.2rem; }
        
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 8px; font-weight: 700; color: #475569; }
        input[type="text"], textarea { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; box-sizing: border-box; font-size: 1rem; }
        input[type="text"]:focus, textarea:focus { border-color: #3b82f6; outline: none; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        
        .btn { padding: 12px 25px; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 1rem; transition: 0.2s; color: white; }
        .btn-save { background-color: #0f172a; width: 100%; }
        .btn-save:hover { background-color: #1e293b; }
        .btn-send { background-color: #2563eb; width: 100%; }
        .btn-send:hover { background-color: #1d4ed8; }
        
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 700; }
        .alert.success { background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert.error { background-color: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        
        .info-box { background: #eff6ff; padding: 15px; border-radius: 8px; font-size: 0.9rem; color: #1e40af; margin-bottom: 20px; border: 1px solid #dbeafe; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🤖 إدارة البوت الجديد</h1>
            <a href="bot_dashboard.php" class="back-btn">العودة للرئيسية</a>
        </div>

        <?php if ($message): ?>
            <div class="alert <?php echo $msg_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <!-- إعدادات البوت -->
        <div class="card">
            <h2>⚙️ إعدادات الاتصال</h2>
            <div class="info-box">
                هذه الإعدادات منفصلة تماماً عن البوت الأساسي للموقع. يمكنك استخدام هذا البوت لأغراض خاصة (إعلانات، قناة احتياطية، إلخ).
            </div>
            <form method="post">
                <div class="form-group">
                    <label>Bot Token (من BotFather)</label>
                    <input type="text" name="bot_token" value="<?php echo htmlspecialchars($settings['bot_token'] ?? ''); ?>" placeholder="123456789:ABCdefGHIjklMNOpqrs..." required>
                </div>
                <div class="form-group">
                    <label>Chat ID (معرف القناة أو المجموعة)</label>
                    <input type="text" name="chat_id" value="<?php echo htmlspecialchars($settings['chat_id'] ?? ''); ?>" placeholder="-100xxxxxxxxxx" required>
                </div>
                <button type="submit" name="save_settings" class="btn btn-save">حفظ الإعدادات</button>
            </form>
        </div>

        <!-- إرسال رسالة -->
        <div class="card">
            <h2>📩 إرسال رسالة يدوية</h2>
            <form method="post">
                <div class="form-group">
                    <label>نص الرسالة (يدعم HTML)</label>
                    <textarea name="message_text" rows="5" placeholder="اكتب رسالتك هنا... يمكنك استخدام <b>خط عريض</b> أو روابط." required></textarea>
                </div>
                <button type="submit" name="send_message" class="btn btn-send">إرسال الآن 🚀</button>
            </form>
        </div>
        
        <!-- أدوات مساعدة -->
        <div class="card">
            <h2>🛠️ أدوات سريعة</h2>
            <p>للحصول على Chat ID:</p>
            <ol style="color: #475569; padding-right: 20px;">
                <li>أضف البوت إلى القناة/المجموعة كمسؤول.</li>
                <li>أرسل أي رسالة في القناة.</li>
                <li>افتح الرابط التالي في المتصفح (مع استبدال التوكن):<br>
                    <code style="background:#f1f5f9; padding:2px 5px; border-radius:4px; display:block; margin-top:5px; word-break:break-all;">https://api.telegram.org/bot<span style="color:red">TOKEN</span>/getUpdates</code>
                </li>
                <li>ابحث عن ID القناة في النتيجة (يبدأ عادة بـ -100).</li>
            </ol>
        </div>
    </div>
</body>
</html>
```

### 3. تعديل `index.php` (لعرض الإعلانات)

```diff