<?php
// example_match_view.php - مثال لكيفية عرض الإحصائيات في صفحة المباراة
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

// (للتجربة فقط) جلب آخر مباراة تحتوي على إحصائيات لعرضها
$stmt = $pdo->query("SELECT * FROM matches WHERE match_stats IS NOT NULL AND match_stats != '' ORDER BY id DESC LIMIT 1");
$match = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$match) {
    die("<div style='text-align:center;padding:50px;font-family:sans-serif;'>لا توجد مباريات تحتوي على إحصائيات حالياً لعرض المثال.<br>يرجى سحب إحصائيات مباراة أولاً من لوحة التحكم.</div>");
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($match['team_home'] . ' ضد ' . $match['team_away']); ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f1f5f9; padding: 20px; margin: 0; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        
        /* تنسيق التبويبات */
        .tabs { display: flex; gap: 10px; border-bottom: 2px solid #e2e8f0; margin-bottom: 20px; }
        .tab-btn { 
            padding: 12px 24px; 
            background: none; 
            border: none; 
            cursor: pointer; 
            font-size: 16px; 
            font-weight: bold; 
            color: #64748b; 
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all 0.3s;
        }
        .tab-btn:hover { color: #1e293b; }
        .tab-btn.active { color: #2563eb; border-bottom-color: #2563eb; }
        
        .tab-content { display: none; animation: fadeIn 0.3s; }
        .tab-content.active { display: block; }
        
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>

<div class="container">
    <h2 style="text-align:center; color:#1e293b; margin-bottom:5px;"><?php echo htmlspecialchars($match['team_home']); ?> <span style="color:#ef4444;">VS</span> <?php echo htmlspecialchars($match['team_away']); ?></h2>
    <p style="text-align:center; color:#64748b; margin-top:0;">مثال لعرض التفاصيل</p>

    <!-- أزرار التبويب -->
    <div class="tabs">
        <button class="tab-btn active" onclick="openTab(event, 'stats')">الإحصائيات</button>
        <button class="tab-btn" onclick="openTab(event, 'lineup')">التشكيلة</button>
    </div>

    <!-- محتوى الإحصائيات -->
    <div id="stats" class="tab-content active">
        <?php echo render_match_stats($match['match_stats'], $match['team_home'], $match['team_away'], $match['team_home_logo'] ?? null, $match['team_away_logo'] ?? null); ?>
    </div>

    <!-- محتوى التشكيلة (مثال) -->
    <div id="lineup" class="tab-content">
        <div style="padding:20px; text-align:center; background:#f8fafc; border-radius:8px; color:#64748b;">
            هنا يتم عرض التشكيلة...
        </div>
    </div>
</div>

<script>
function openTab(evt, tabName) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById(tabName).classList.add('active');
    evt.currentTarget.classList.add('active');
}
</script>

</body>
</html>