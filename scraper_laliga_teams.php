<?php
require_once __DIR__ . '/db.php';
header('Content-Type: text/html; charset=utf-8');
set_time_limit(0);

echo "<h3>جاري تحديث قائمة الفرق وشعارات الدوري الإسباني من المصدر الخارجي...</h3>";

// 1. ترحيل الفرق الموجودة حالياً في جدول المباريات إلى جدول الفرق (للحفاظ على البيانات القديمة للفرق الأخرى)
$sql_migrate = "
    INSERT OR IGNORE INTO teams (name, logo)
    SELECT team_name, MAX(team_logo)
    FROM (
        SELECT team_home as team_name, team_home_logo as team_logo FROM matches
        UNION ALL
        SELECT team_away as team_name, team_away_logo as team_logo FROM matches
    )
    WHERE team_name IS NOT NULL AND team_name != ''
    GROUP BY team_name
";
$pdo->exec($sql_migrate);

echo "جاري الاتصال بـ FotMob لجلب فرق الدوري الإسباني (La Liga)...<br>";

// معرف الدوري الإسباني في FotMob هو 87
// نستخدم lang=ar للحصول على الأسماء بالعربية
$url = "https://www.fotmob.com/api/leagues?id=87&cccode3=SAU&lang=ar";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
// curl_close($ch); // تم تعطيلها لتجنب رسالة Deprecated في PHP الحديث

if ($httpCode === 200 && $response) {
    $data = json_decode($response, true);
    
    // محاولة الوصول لجدول الترتيب لاستخراج الفرق
    $teams_list = [];
    
    // مسارات محتملة للبيانات في JSON حسب هيكلية FotMob
    if (isset($data['table'][0]['data']['table']['all'])) {
        $teams_list = $data['table'][0]['data']['table']['all'];
    } elseif (isset($data['overview']['table'][0]['data']['table']['all'])) {
        $teams_list = $data['overview']['table'][0]['data']['table']['all'];
    }

    // قائمة ترجمة يدوية لضمان ظهور الأسماء بالعربية
    $translations = [
        'Real Madrid' => 'ريال مدريد',
        'Barcelona' => 'برشلونة',
        'Girona' => 'جيرونا',
        'Atlético de Madrid' => 'أتلتيكو مدريد',
        'Atletico Madrid' => 'أتلتيكو مدريد',
        'Athletic Club' => 'أتلتيك بلباو',
        'Real Sociedad' => 'ريال سوسيداد',
        'Real Betis' => 'ريال بيتيس',
        'Valencia' => 'فالنسيا',
        'Villarreal' => 'فياريال',
        'Getafe' => 'خيتافي',
        'Osasuna' => 'أوساسونا',
        'Sevilla' => 'إشبيلية',
        'Alavés' => 'ديبورتيفو ألافيس',
        'Alaves' => 'ديبورتيفو ألافيس',
        'Deportivo Alaves' => 'ديبورتيفو ألافيس',
        'Las Palmas' => 'لاس بالماس',
        'Rayo Vallecano' => 'رايو فايكانو',
        'Mallorca' => 'ريال مايوركا',
        'Celta de Vigo' => 'سيلتا فيغو',
        'Celta Vigo' => 'سيلتا فيغو',
        'Leganés' => 'ليغانيس',
        'Leganes' => 'ليغانيس',
        'Real Valladolid' => 'بلد الوليد',
        'Espanyol' => 'إسبانيول',
        'Cádiz' => 'قادش',
        'Cadiz' => 'قادش',
        'Granada' => 'غرناطة',
        'Almería' => 'ألميريا',
        'Almeria' => 'ألميريا',
        'Elche' => 'إلتشي',
        'Levante' => 'ليفانتي',
        'Real Oviedo' => 'ريال أوفييدو'
    ];

    if (!empty($teams_list)) {
        echo "تم العثور على " . count($teams_list) . " فريق في الدوري الإسباني.<br>";
        $count_updated = 0;
        $count_added = 0;

        $stmtCheck = $pdo->prepare("SELECT id FROM teams WHERE name = ?");
        $stmtInsert = $pdo->prepare("INSERT INTO teams (name, logo, league_name) VALUES (?, ?, ?)");
        $stmtUpdate = $pdo->prepare("UPDATE teams SET logo = ?, league_name = ? WHERE name = ?");
        $stmtUpdateName = $pdo->prepare("UPDATE teams SET name = ?, logo = ?, league_name = ? WHERE name = ?");
        
        // استعلامات لتحديث جدول المباريات أيضاً للحفاظ على التناسق
        $stmtUpdateMatchesHome = $pdo->prepare("UPDATE matches SET team_home = ? WHERE team_home = ?");
        $stmtUpdateMatchesAway = $pdo->prepare("UPDATE matches SET team_away = ? WHERE team_away = ?");

        foreach ($teams_list as $teamData) {
            $originalName = trim($teamData['name']);
            // استخدام الاسم العربي من القائمة إذا وجد، وإلا استخدام الاسم الأصلي
            $arabicName = $translations[$originalName] ?? $originalName;
            
            // تخطي الأسماء التي لا تزال بالإنجليزية (لم تترجم)
            if (preg_match('/[a-zA-Z]/', $arabicName)) {
                continue;
            }
            
            $fotmobId = $teamData['id'];
            // رابط الشعار المباشر من FotMob
            $logoUrl = "https://images.fotmob.com/image_resources/logo/teamlogo/$fotmobId.png";
            $leagueName = "الدوري الإسباني";

            // 1. التحقق مما إذا كان الفريق موجوداً بالاسم العربي
            $stmtCheck->execute([$arabicName]);
            if ($stmtCheck->fetch()) {
                // موجود بالاسم العربي: تحديث الشعار فقط
                $stmtUpdate->execute([$logoUrl, $leagueName, $arabicName]);
                $count_updated++;
                echo "<span style='color:blue'>تحديث (موجود): $arabicName</span><br>";
            } else {
                // 2. التحقق مما إذا كان موجوداً بالاسم الإنجليزي (لتعريبه)
                $stmtCheck->execute([$originalName]);
                if ($stmtCheck->fetch()) {
                    // موجود بالاسم الإنجليزي: تحديث الاسم إلى العربي + الشعار
                    $stmtUpdateName->execute([$arabicName, $logoUrl, $leagueName, $originalName]);
                    
                    // تحديث اسم الفريق في جدول المباريات أيضاً
                    $stmtUpdateMatchesHome->execute([$arabicName, $originalName]);
                    $stmtUpdateMatchesAway->execute([$arabicName, $originalName]);
                    
                    $count_updated++;
                    echo "<span style='color:purple'>تعريب وتحديث: $originalName -> $arabicName</span><br>";
                } else {
                    // غير موجود: إضافة جديد
                    $stmtInsert->execute([$arabicName, $logoUrl, $leagueName]);
                    $count_added++;
                    echo "<span style='color:green'>إضافة جديد: $arabicName</span><br>";
                }
            }
        }
        echo "<hr>النتيجة: تم إضافة $count_added فرق جديدة، وتحديث $count_updated فريق.<br>";
    } else {
        echo "<span style='color:red'>لم يتم العثور على جدول الترتيب في استجابة API.</span><br>";
    }
} else {
    echo "<span style='color:red'>فشل الاتصال بـ FotMob. رمز الحالة: $httpCode</span><br>";
}

echo '<br><br><a href="bot_dashboard.php" style="padding:10px; background:#2563eb; color:white; text-decoration:none; border-radius:5px;">العودة للوحة التحكم</a>';
?>