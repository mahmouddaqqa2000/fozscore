<?php
// add_events_column.php - سكربت لإضافة عمود الأحداث الناقص في قاعدة البيانات
require_once __DIR__ . '/db.php';

try {
    // محاولة إضافة العمود
    $pdo->exec("ALTER TABLE matches ADD COLUMN match_events TEXT DEFAULT NULL");
    echo "✅ تم إضافة عمود 'match_events' بنجاح إلى جدول 'matches'.<br>";
    echo "يمكنك الآن تشغيل البوت مرة أخرى.";
} catch (PDOException $e) {
    echo "⚠️ تنبيه (قد يكون العمود موجوداً بالفعل): " . $e->getMessage();
}
?>