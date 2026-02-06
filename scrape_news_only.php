<?php
// scrape_news_only.php - سحب الأخبار فقط
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
header('Content-Type: text/html; charset=utf-8');
set_time_limit(0);

echo '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><title>جاري سحب الأخبار...</title>';
echo '<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">';
echo '<style>body { font-family: "Tajawal", sans-serif; background: #f1f5f9; padding: 20px; } .container { max-width: 900px; margin: auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }</style>';
echo '</head><body><div class="container">';

scrape_yallakora_news($pdo);

echo '<br><br><a href="bot_dashboard.php" style="padding:10px 20px; background:#2563eb; color:white; text-decoration:none; border-radius:5px;">العودة للوحة التحكم</a>';
echo '</div></body></html>';
?>