<?php
// test_telegram.php
header('Content-Type: text/html; charset=utf-8');

// الإعدادات من المدخلات السابقة
$botToken = '8042622774:AAHsri8itQqddhC_NeuP7EKBSoMcZYzIi64';
$chatId = '1783801547';

$result = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = "👋 *تجربة بوت FozScore (تلقائي)*\n\nهذه رسالة تجريبية تلقائية للتأكد من أن إعدادات تيليجرام تعمل بنجاح!\n🕒 الوقت: " . date('Y-m-d H:i:s');
    
    $url = "https://api.telegram.org/bot$botToken/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'Markdown'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode == 200) {
        $result = "<div style='color:green; padding:15px; border:1px solid green; background:#f0fff0; border-radius:8px; margin-bottom:20px;'><strong>✅ تم الإرسال بنجاح!</strong><br>يرجى التحقق من تطبيق تيليجرام الآن.</div>";
    } else {
        $result = "<div style='color:red; padding:15px; border:1px solid red; background:#fff0f0; border-radius:8px; margin-bottom:20px;'><strong>❌ فشل الإرسال!</strong><br>رمز الخطأ: $httpCode<br>رد الخادم: " . htmlspecialchars($response) . "<br>خطأ Curl: $curlError</div>";
    }
}
?>
<!DOCTYPE html>
<html dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>اختبار تيليجرام - FozScore</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8fafc; padding: 40px; text-align: center; }
        .container { max-width: 500px; margin: 0 auto; background: white; padding: 30px; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        h2 { color: #1e293b; margin-top: 0; }
        .btn { display: inline-block; padding: 12px 24px; background: #2563eb; color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; text-decoration: none; transition: background 0.2s; }
        .btn:hover { background: #1d4ed8; }
        .back-link { display: block; margin-top: 20px; color: #64748b; text-decoration: none; }
        .back-link:hover { color: #1e293b; }
    </style>
</head>
<body>
    <div class="container">
        <h2>📡 اختبار تنبيهات تيليجرام</h2>
        <p style="color:#64748b; margin-bottom:20px;">سيتم إرسال رسالة تجريبية إلى:<br><code style="background:#f1f5f9; padding:2px 6px; border-radius:4px;"><?php echo $chatId; ?></code></p>
        
        <?php echo $result; ?>
        
        <?php if (empty($result)): ?>
        <div id="countdown" style="margin-bottom: 15px; color: #d97706; font-weight: bold;">سيتم الإرسال تلقائياً خلال <span id="timer">3</span> ثواني...</div>
        <script>
            var seconds = 3;
            var interval = setInterval(function() {
                seconds--;
                document.getElementById('timer').innerText = seconds;
                if (seconds <= 0) {
                    clearInterval(interval);
                    document.querySelector('form button').click();
                }
            }, 1000);
        </script>
        <?php endif; ?>
        
        <form method="post">
            <button type="submit" class="btn">إرسال رسالة تجريبية الآن</button>
        </form>
        
        <a href="bot_dashboard.php" class="back-link">العودة للوحة التحكم</a>
    </div>
</body>
</html>