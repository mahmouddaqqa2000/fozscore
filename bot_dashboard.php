<?php
session_start();
// حماية الصفحة
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// جلب اتصال قاعدة البيانات
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

$settings = get_site_settings($pdo);
$favicon = $settings['favicon'];
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>إدارة البوت - FozScore</title>
    <?php if ($favicon): ?><link rel="icon" href="<?php echo htmlspecialchars($favicon); ?>"><?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1e293b;
            --secondary: #2563eb;
            --bg: #f1f5f9;
            --card: #ffffff;
            --text: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #0ea5e9;
        }
        body { font-family: 'Tajawal', sans-serif; background-color: var(--bg); color: var(--text); margin: 0; padding: 0; line-height: 1.6; }
        
        .navbar { background-color: var(--primary); color: #fff; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .navbar .brand { font-size: 1.5rem; font-weight: 800; text-decoration: none; color: #fff; display: flex; align-items: center; gap: 10px; }
        .navbar .nav-links { display: flex; gap: 20px; }
        .navbar .nav-links a { color: #cbd5e1; text-decoration: none; font-weight: 500; transition: color 0.2s; }
        .navbar .nav-links a:hover { color: #fff; }

        .container { max-width: 1100px; margin: 3rem auto; padding: 0 1.5rem; }
        
        .dashboard-header { text-align: center; margin-bottom: 3rem; }
        .dashboard-header h1 { font-size: 2.5rem; color: var(--primary); margin-bottom: 0.5rem; font-weight: 800; }
        .dashboard-header p { color: var(--text-muted); font-size: 1.1rem; }

        .grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 2rem;
        }

        .section-card {
            background: var(--card);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05), 0 4px 6px -2px rgba(0,0,0,0.025);
            border: 1px solid var(--border);
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        .section-card:hover { transform: translateY(-5px); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }

        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--bg);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .action-list { display: flex; flex-direction: column; gap: 1rem; flex: 1; }

        .btn-bot {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.2s;
            color: white;
            border: none;
            cursor: pointer;
            width: 100%;
            text-align: right;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .btn-bot:hover { filter: brightness(110%); transform: translateX(-3px); }
        .btn-bot .icon { font-size: 1.2rem; }

        /* Colors */
        .bg-slate { background-color: #475569; }
        .bg-blue { background-color: var(--secondary); }
        .bg-dark { background-color: #0f172a; }
        .bg-green { background-color: var(--success); }
        .bg-purple { background-color: #8b5cf6; }
        .bg-rose { background-color: #f43f5e; }
        .bg-sky { background-color: #0ea5e9; }
        .bg-indigo { background-color: #6366f1; }
        .bg-amber { background-color: var(--warning); }
        .bg-cyan { background-color: #0891b2; }
        .bg-red { background-color: var(--danger); }

        /* Form Styling */
        .tool-form {
            background: var(--bg);
            padding: 1.5rem;
            border-radius: 12px;
            margin-top: 1.5rem;
        }
        .form-group { display: flex; gap: 10px; margin-bottom: 10px; }
        .form-input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid var(--border);
            border-radius: 8px;
            outline: none;
            font-family: inherit;
            direction: ltr;
        }
        .form-input:focus { border-color: var(--secondary); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            color: var(--text-muted);
            cursor: pointer;
        }

        .info-text { margin-top: 3rem; color: var(--text-muted); font-size: 0.9rem; text-align: center; }
        
        @media (max-width: 768px) {
            .navbar { flex-direction: column; gap: 1rem; }
            .grid-container { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <a class="brand" href="dashboard.php">🤖 لوحة تحكم FozScore</a>
        <div class="nav-links">
            <a href="dashboard.php">المباريات</a>
            <a href="news_dashboard.php">الأخبار</a>
            <a href="admin_messages.php">📩 الرسائل</a>
            <a href="settings.php">⚙️ الإعدادات</a>
            <a href="./">عرض الموقع</a>
        </div>
    </div>

    <div class="container">
        <div class="dashboard-header">
            <h1>إدارة البوت الآلي</h1>
            <p>تحكم في عمليات السحب والتحديث التلقائي للمباريات والبيانات</p>
        </div>

        <div class="grid-container">
            <!-- Section 1: Main Scraping -->
            <div class="section-card">
                <div class="section-title">
                    <span>📅</span> تحديث المباريات الأساسي
                </div>
                <div class="action-list">
                    <a href="scraper_yallakora.php?mode=yesterday&details=1" class="btn-bot bg-slate">
                        <span>سحب مباريات الأمس (تحديث النتائج)</span>
                        <span class="icon">⏮️</span>
                    </a>
                    <a href="scraper_all.php?mode=today" class="btn-bot bg-blue">
                        <span>سحب مباريات اليوم (مباشر)</span>
                        <span class="icon">🔴</span>
                    </a>
                    <a href="scraper_all.php" class="btn-bot bg-green">
                        <span>تحديث شامل (أمس، اليوم، غداً)</span>
                        <span class="icon">🔄</span>
                    </a>
                    <a href="scraper_btolat.php" class="btn-bot bg-rose">
                        <span>سحب من بطولات (Btolat)</span>
                        <span class="icon">🅱️</span>
                    </a>
                </div>
            </div>

            <!-- Section 2: Data Enrichment -->
            <div class="section-card">
                <div class="section-title">
                    <span>📊</span> إثراء البيانات والتفاصيل
                </div>
                <div class="action-list">
                    <a href="scrape_stats_recent.php?type=full" class="btn-bot bg-purple">
                        <span>سحب إحصائيات وتشكيلات (شامل)</span>
                        <span class="icon">📈</span>
                    </a>
                    <a href="scrape_news_only.php" class="btn-bot bg-sky">
                        <span>سحب الأخبار فقط (سريع)</span>
                        <span class="icon">📰</span>
                    </a>
                    <a href="scrape_stats_recent.php?type=events" class="btn-bot bg-rose">
                        <span>سحب أحداث المباريات (أهداف/بطاقات)</span>
                        <span class="icon">⚽</span>
                    </a>
                    <a href="scrape_stats_recent.php?type=standings" class="btn-bot bg-teal" style="background-color: #0d9488;">
                        <span>تحديث جدول الترتيب (المراكز) فقط</span>
                        <span class="icon">📊</span>
                    </a>
                    <a href="scrape_lineups_today.php" class="btn-bot bg-sky" title="هذه الميزة معطلة حالياً لأنها تتطلب Node.js">
                        <span style="text-decoration: line-through; opacity: 0.7;">سحب تشكيلات مباريات اليوم</span>
                        <span class="icon">👕</span>
                    </a>
                    <a href="scrape_lineups_yesterday.php" class="btn-bot bg-indigo">
                        <span>تحديث إحصائيات الأمس</span>
                        <span class="icon">📊</span>
                    </a>
                    <a href="scraper_laliga_teams.php" class="btn-bot bg-amber">
                        <span>سحب فرق وشعارات الدوري الإسباني</span>
                        <span class="icon">🇪🇸</span>
                    </a>
                    <a href="scraper_leagues.php" class="btn-bot bg-cyan">
                        <span>سحب جميع الدوريات والشعارات</span>
                        <span class="icon">🏆</span>
                    </a>
                    <a href="scraper_leagues.php" class="btn-bot bg-cyan">
                        <span>سحب جميع الدوريات والشعارات</span>
                        <span class="icon">🏆</span>
                    </a>
                </div>
            </div>

            <!-- Section: Stream Scraping (Koora4Live) -->
            <div class="section-card">
                <div class="section-title">
                    <span>📺</span> سحب البث (Koora4Live)
                </div>
                <div style="text-align:center; margin-bottom:10px;"><a href="https://koora4live.pro/" target="_blank" style="font-size:0.85rem; color:var(--secondary);">فتح موقع koora4live.pro في نافذة جديدة ↗</a></div>
                <a href="scrape_streams_auto.php" class="btn-bot bg-purple" style="margin-bottom: 15px; text-align: center; justify-content: center;">
                    <span class="icon">📡</span>
                    بحث وسحب تلقائي لجميع مباريات اليوم
                </a>
                <form action="scrape_koora4live.php" method="post" class="tool-form">
                    <div class="form-group">
                        <input type="text" name="url" class="form-input" placeholder="رابط المباراة من koora4live.pro" required>
                    </div>
                    <div class="form-group">
                        <select name="match_id" class="form-input" style="background:white;">
                            <option value="">-- اختر المباراة لربط البث (اختياري) --</option>
                            <?php
                            $stmt = $pdo->query("SELECT id, team_home, team_away FROM matches WHERE match_date >= DATE('now', '-1 day') ORDER BY match_date DESC, match_time ASC");
                            while ($m = $stmt->fetch()) {
                                echo "<option value='{$m['id']}'>{$m['team_home']} vs {$m['team_away']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <button type="submit" class="btn-bot bg-red">سحب وتحديث البث</button>
                </form>
            </div>

            <!-- Section 3: Tools & Maintenance -->
            <div class="section-card">
                <div class="section-title">
                    <span>🛠️</span> أدوات وصيانة
                </div>
                <div class="action-list">
                    <a href="db_migrate.php" class="btn-bot bg-amber">
                        <span>فحص وتحديث قاعدة البيانات</span>
                        <span class="icon">🗄️</span>
                    </a>
                    <a href="test_telegram.php" class="btn-bot bg-cyan">
                        <span>اختبار تنبيهات تيليجرام</span>
                        <span class="icon">✈️</span>
                    </a>
                    <a href="test_twitter.php" class="btn-bot bg-sky">
                        <span>اختبار نشر تويتر</span>
                        <span class="icon">🐦</span>
                    </a>
                    <a href="get_chat_id.php" class="btn-bot bg-indigo">
                        <span>جلب معرف المجموعة (Chat ID)</span>
                        <span class="icon">🆔</span>
                    </a>
                    <a href="send_daily_summary.php" class="btn-bot bg-green">
                        <span>إرسال ملخص اليوم (تيليجرام)</span>
                        <span class="icon">📝</span>
                    </a>
                    <a href="send_poll_manual.php" class="btn-bot bg-orange" style="background-color: #f97316;">
                        <span>إرسال استفتاء يدوي</span>
                        <span class="icon">🗳️</span>
                    </a>
                    <a href="dashboard.php?action=delete_old" class="btn-bot bg-red" onclick="return confirm('هل أنت متأكد؟ سيتم حذف جميع المباريات التي مر عليها أكثر من أسبوع.');">
                        <span>تنظيف المباريات القديمة</span>
                        <span class="icon">🗑️</span>
                    </a>
                    <a href="#" onclick="document.querySelector('input[name=url]').value = 'https://www.kooora.com/?m='; document.querySelector('input[name=url]').focus(); return false;" class="btn-bot bg-indigo" title="أدخل رقم المباراة بعد m=">
                        <span>تجربة سحب من كووورة (مباراة واحدة)</span>
                        <span class="icon">🥅</span>
                    </a>
                    <a href="#" onclick="document.querySelector('input[name=url]').value = 'أتلتيكو مدريد ضد ريال بيتيس'; document.querySelector('input[name=url]').focus(); return false;" class="btn-bot bg-rose">
                        <span>مثال: أتلتيكو مدريد ضد ريال بيتيس (بحث تلقائي)</span>
                        <span class="icon">🇪🇸</span>
                    </a>
                </div>

                <!-- قسم السحب الذكي عبر Gemini -->
                <div style="margin-top: 2rem; padding-top: 2rem; border-top: 2px dashed #e2e8f0;">
                    <div class="section-title" style="border:none; margin-bottom:1rem;">
                        <span>🧠</span> السحب الذكي الشامل (Gemini AI)
                    </div>
                    <form action="scrape_smart_gemini.php" method="get" class="tool-form" style="margin-top:0; background:#f8fafc; border:1px solid #e2e8f0;">
                        <div style="font-size:0.9rem; margin-bottom:10px; color:var(--text-muted);">
                            ضع أي رابط مباراة (من أي موقع رياضي)، وسيقوم الذكاء الاصطناعي بتحليل الصفحة واستخراج البيانات والتشكيلة تلقائياً.
                        </div>
                        <div class="form-group">
                            <input type="text" name="url" class="form-input" placeholder="رابط المباراة (BBC, Sky, FlashScore, etc...)" required>
                            <button type="submit" class="btn-bot bg-purple" style="width: auto; padding: 0 20px;">تحليل وسحب</button>
                        </div>
                    </form>
                </div>

                <form action="scrape_single_match.php" method="get" class="tool-form">
                    <div style="font-weight:bold; margin-bottom:10px; font-size:0.9rem;">سحب مباراة محددة (رابط أو بحث):</div>
                    <div class="form-group">
                        <input type="text" name="url" class="form-input" placeholder="ضع الرابط هنا، أو اكتب اسم الفريقين (مثال: الأهلي والزمالك)" required>
                        <button type="submit" class="btn-bot bg-green" style="width: auto; padding: 0 20px;">بحث وسحب</button>
                    </div>
                    <label class="checkbox-label">
                        <input type="checkbox" name="stats_only" value="1" style="width: 18px; height: 18px;">
                        سحب الإحصائيات فقط (دون تغيير التشكيلة الحالية)
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="standings_only" value="1" style="width: 18px; height: 18px;">
                        سحب جدول الترتيب فقط
                    </label>
                </form>
            </div>
        </div>
        
        <div class="info-text">ملاحظة: عملية السحب قد تستغرق بضع ثوانٍ. يرجى الانتظار حتى تظهر رسالة "تم الانتهاء".</div>
    </div>
</body>
</html>