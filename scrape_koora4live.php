<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

$message = '';
$extracted_code = '';
$status_class = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $url = $_POST['url'] ?? '';
    $match_id = $_POST['match_id'] ?? '';

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        $message = "الرابط المدخل غير صالح.";
        $status_class = "error";
    } else {
        $result = get_stream_iframe($url);
        
        if ($result['success']) {
            $extracted_code = $result['code'];
            if ($match_id) {
                $stmt = $pdo->prepare("UPDATE matches SET stream_url = ? WHERE id = ?");
                $stmt->execute([$extracted_code, $match_id]);
                $message = "✅ تم سحب كود البث وتحديث المباراة بنجاح!";
            } else {
                $message = "✅ تم سحب الكود بنجاح (لم يتم تحديد مباراة للتحديث).";
            }
            $status_class = "success";
        } else {
            $message = "❌ " . $result['message'];
            $status_class = "error";
        }
    }
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>نتيجة سحب البث</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Tajawal', sans-serif; background: #f1f5f9; padding: 20px; text-align: center; }
        .container { max-width: 600px; margin: 50px auto; background: white; padding: 30px; border-radius: 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .success { color: #10b981; font-weight: bold; font-size: 1.2rem; }
        .error { color: #ef4444; font-weight: bold; font-size: 1.2rem; }
        textarea { width: 100%; height: 150px; margin-top: 20px; padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px; direction: ltr; font-family: monospace; }
        .btn { display: inline-block; margin-top: 20px; padding: 10px 20px; background: #2563eb; color: white; text-decoration: none; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="<?php echo $status_class; ?>"><?php echo $message; ?></div>
        <?php if ($extracted_code): ?>
            <p>الكود المسحوب:</p>
            <textarea readonly><?php echo htmlspecialchars($extracted_code); ?></textarea>
        <?php endif; ?>
        <br>
        <a href="bot_dashboard.php" class="btn">العودة للوحة التحكم</a>
    </div>
</body>
</html>