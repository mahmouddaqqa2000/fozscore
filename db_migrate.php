<?php
require_once __DIR__ . '/db.php';
header('Content-Type: text/html; charset=utf-8');

echo "<h3>جاري فحص وتحديث هيكل قاعدة البيانات...</h3>";

try {
    // Check for 'match_stats' column in 'matches' table
    $result = $pdo->query("PRAGMA table_info(matches)");
    $columns = $result->fetchAll(PDO::FETCH_COLUMN, 1);

    if (!in_array('match_stats', $columns)) {
        echo "لم يتم العثور على عمود 'match_stats'. جاري إضافته...<br>";
        $pdo->exec("ALTER TABLE matches ADD COLUMN match_stats TEXT DEFAULT NULL");
        echo "<span style='color:green;'>✅ تم إضافة عمود 'match_stats' بنجاح!</span><br>";
    } else {
        echo "<span style='color:blue;'>- عمود 'match_stats' موجود بالفعل.</span><br>";
    }
    
    echo "<hr>تم الانتهاء من فحص قاعدة البيانات.<br>";

} catch (PDOException $e) {
    echo "<span style='color:red;'>❌ حدث خطأ أثناء تحديث قاعدة البيانات: " . $e->getMessage() . "</span><br>";
}

echo '<br><a href="bot_dashboard.php" style="padding:10px; background:#2563eb; color:white; text-decoration:none; border-radius:5px;">العودة للوحة التحكم</a>';
?>