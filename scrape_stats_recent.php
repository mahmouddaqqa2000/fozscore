<?php
// scrape_stats_recent.php - سحب الأحداث والإحصائيات للمباريات القريبة
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

header('Content-Type: text/html; charset=utf-8');
set_time_limit(0); // منع توقف السكربت

// إجبار السيرفر على إرسال المخرجات فوراً (مثل scraper_all.php)
if (function_exists('apache_setenv')) @apache_setenv('no-gzip', 1);
@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);
for ($i = 0; $i < ob_get_level(); $i++) { ob_end_flush(); }
ob_implicit_flush(1);

$type = $_GET['type'] ?? 'events'; // 'events' or 'full'

echo '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><title>تحديث الأحداث</title>';
echo '<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">';
echo '<style>
    body { font-family: "Tajawal", sans-serif; background: #f8fafc; padding: 20px; color: #1e293b; }
    .container { max-width: 800px; margin: auto; background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
    h2 { color: #1e293b; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; margin-top: 0; }
    .log-item { padding: 8px 10px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
    .log-item:last-child { border-bottom: none; }
    .status-ok { color: #10b981; font-weight: bold; }
    .status-skip { color: #64748b; font-size: 0.9em; }
    .status-fail { color: #ef4444; font-weight: bold; }
    .date-header { background: #e2e8f0; padding: 8px 12px; border-radius: 6px; margin: 20px 0 10px; font-weight: bold; color: #475569; }
    .btn { display: inline-block; padding: 10px 20px; background: #2563eb; color: white; text-decoration: none; border-radius: 6px; margin-top: 20px; font-weight: bold; }
    .btn:hover { background: #1d4ed8; }
</style>';
echo '</head><body><div class="container">';

$title = ($type === 'events') ? 'سحب أحداث المباريات (أهداف، بطاقات، تبديلات)' : 'سحب التفاصيل الكاملة (إحصائيات وتشكيلات)';
echo "<h2>🔄 $title</h2>";
echo "<p>جاري تحديث البيانات للمباريات (أمس، اليوم، غداً)...</p>";
// إضافة حشو لإجبار المتصفح على عرض البداية فوراً
echo "<!-- " . str_repeat(" ", 4096) . " -->";
flush();

// التواريخ المستهدفة
$dates = [
    date('Y-m-d', strtotime('-1 day')),
    date('Y-m-d'),
    date('Y-m-d', strtotime('+1 day'))
];

$total_updated = 0;

foreach ($dates as $date) {
    echo "<div class='date-header'>📅 $date</div>";
    
    // جلب المباريات التي لها رابط مصدر
    $stmt = $pdo->prepare("SELECT id, team_home, team_away, source_url, match_events, match_stats FROM matches WHERE match_date = ? AND source_url IS NOT NULL AND source_url != ''");
    $stmt->execute([$date]);
    $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($matches)) {
        echo "<div class='log-item' style='justify-content:center; color:#94a3b8;'>لا توجد مباريات مرتبطة برابط مصدر.</div>";
        continue;
    }

    echo "<div style='padding:5px 10px; font-size:0.9em; color:#64748b;'>تم العثور على " . count($matches) . " مباراة. جاري المعالجة...</div>";
    flush();
    
    foreach ($matches as $match) {
        echo "<div class='log-item'>";
        echo "<span>{$match['team_home']} 🆚 {$match['team_away']}</span>";
        echo str_repeat(" ", 1024); // حشو إضافي لكل سطر
        flush(); // إرسال النص فوراً قبل بدء السحب
        
        // سحب التفاصيل
        $details = get_match_details($match['source_url']);
        
        $updates = [];
        $params = [];
        
        // تحديث الأحداث
        if (!empty($details['match_events'])) {
            // مقارنة بسيطة لتجنب التحديث غير الضروري
            $new_events_clean = preg_replace('/\s+/', '', $details['match_events']);
            $old_events_clean = preg_replace('/\s+/', '', $match['match_events'] ?? '');
            
            if ($new_events_clean !== $old_events_clean) {
                $updates[] = "match_events = ?";
                $params[] = $details['match_events'];
            }
        }
        
        if (!empty($updates)) {
            $sql = "UPDATE matches SET " . implode(', ', $updates) . " WHERE id = ?";
            $params[] = $match['id'];
            $pdo->prepare($sql)->execute($params);
            echo "<span class='status-ok'>تم التحديث ✅</span>";
            $total_updated++;
        } else {
            if (empty($details['match_events'])) {
                if (strpos($details['html_preview'], 'Cloudflare') !== false || strpos($details['html_preview'], 'Attention Required') !== false) {
                    echo "<span class='status-fail'>تم حظر الطلب (Cloudflare) ⛔</span>";
                } elseif (strpos($details['html_preview'], 'فشل الاتصال') !== false) {
                    echo "<span class='status-fail'>" . htmlspecialchars($details['html_preview']) . " ❌</span>";
                } else {
                    echo "<span class='status-skip' style='color:#d97706;'>لا توجد أحداث (أو لم تبدأ)</span>";
                }
            } else {
                echo "<span class='status-skip'>لا تغيير</span>";
            }
        }
        
        echo "</div>";
        flush(); // إرسال المخرجات للمتصفح فوراً
        usleep(100000); // انتظار 0.1 ثانية لتخفيف الحمل
    }
}

echo "<div style='margin-top:30px; text-align:center;'>";
echo "<div style='font-size:1.2rem; font-weight:bold; color:#1e293b; margin-bottom:10px;'>تم الانتهاء!</div>";
echo "<div>تم تحديث بيانات <strong>$total_updated</strong> مباراة.</div>";
echo '<a href="bot_dashboard.php" class="btn">العودة للوحة التحكم</a>';
echo "</div>";

echo '</div></body></html>';
?>