<?php
set_time_limit(0);
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html dir="rtl">
<head>
    <title>اختبار سحب FotMob</title>
    <style>body { font-family: sans-serif; padding: 20px; }</style>
</head>
<body>
    <h2>اختبار سحب التشكيلة من FotMob</h2>
    <form method="get">
        <label>رابط المباراة (FotMob):</label><br>
        <input type="text" name="url" style="width:100%; max-width:600px; padding:10px; direction:ltr;" placeholder="https://www.fotmob.com/matches/..." value="<?php echo isset($_GET['url']) ? htmlspecialchars($_GET['url']) : ''; ?>">
        <br><br>
        <button type="submit" style="padding:10px 20px; background:blue; color:white; border:none; cursor:pointer;">تجربة السحب</button>
    </form>
    <hr>
    <?php
    if (isset($_GET['url']) && !empty($_GET['url'])) {
        $url = $_GET['url'];
        echo "<h3>جاري فحص الرابط: <span dir='ltr'>$url</span></h3>";
        
        $nodeScript = __DIR__ . '/scraper_lineup.js';
        $cmd = "node " . escapeshellarg($nodeScript) . " " . escapeshellarg($url) . " 2>&1";
        
        $startTime = microtime(true);
        $output = shell_exec($cmd);
        $endTime = microtime(true);
        
        echo "<p>تم التنفيذ في " . round($endTime - $startTime, 2) . " ثانية.</p>";
        echo "<h4>مخرجات HTML (أول 100,000 حرف):</h4>";
        echo "<textarea style='width:100%; height:500px; direction:ltr; font-family:monospace;'>" . htmlspecialchars(substr($output, 0, 100000)) . "</textarea>";
    }
    ?>
</body>
</html>