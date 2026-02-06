<?php
require_once __DIR__ . '/db.php';
header('Content-Type: text/html; charset=utf-8');
set_time_limit(0);

echo "<h3>جاري سحب قائمة الدوريات والشعارات من المصدر الخارجي (FotMob)...</h3>";

// التأكد من وجود الجدول
$pdo->exec("CREATE TABLE IF NOT EXISTS leagues (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL UNIQUE,
  logo TEXT,
  external_id INTEGER
)");

// 1. سحب البيانات من FotMob
$url = "https://www.fotmob.com/api/allLeagues?lang=ar";

echo "جاري الاتصال بـ FotMob...<br>";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
// curl_close($ch); 

if ($httpCode === 200 && $response) {
    $data = json_decode($response, true);
    
    if (isset($data['countries'])) {
        $count_added = 0;
        $count_updated = 0;
        $count_merged = 0;
        
        $stmtCheck = $pdo->prepare("SELECT id FROM leagues WHERE name = ?");
        $stmtInsert = $pdo->prepare("INSERT INTO leagues (name, logo, external_id) VALUES (?, ?, ?)");
        $stmtUpdate = $pdo->prepare("UPDATE leagues SET logo = ?, external_id = ? WHERE name = ?");
        $stmtUpdateName = $pdo->prepare("UPDATE leagues SET name = ?, logo = ?, external_id = ? WHERE id = ?");
        $stmtDelete = $pdo->prepare("DELETE FROM leagues WHERE id = ?");
        
        // تحديث اسم البطولة في جدول المباريات
        $stmtUpdateMatches = $pdo->prepare("UPDATE matches SET championship = ? WHERE championship = ?");

        // خريطة لترجمة أسماء الدول (للاستخدام في تسمية الدوريات العامة)
        $countryMap = [
            'England' => 'الإنجليزي', 'Spain' => 'الإسباني', 'Italy' => 'الإيطالي', 'Germany' => 'الألماني', 'France' => 'الفرنسي',
            'Portugal' => 'البرتغالي', 'Netherlands' => 'الهولندي', 'Turkey' => 'التركي', 'Belgium' => 'البلجيكي',
            'Saudi Arabia' => 'السعودي', 'Egypt' => 'المصري', 'Morocco' => 'المغربي', 'Tunisia' => 'التونسي', 'Algeria' => 'الجزائري',
            'Qatar' => 'القطري', 'United Arab Emirates' => 'الإماراتي', 'Kuwait' => 'الكويتي', 'Bahrain' => 'البحريني', 'Oman' => 'العماني',
            'Jordan' => 'الأردني', 'Iraq' => 'العراقي', 'Lebanon' => 'اللبناني', 'Syria' => 'السوري', 'Palestine' => 'الفلسطيني',
            'Sudan' => 'السوداني', 'Libya' => 'الليبي',
            'Brazil' => 'البرازيلي', 'Argentina' => 'الأرجنتيني', 'USA' => 'الأمريكي', 'Mexico' => 'المكسيكي',
            'Russia' => 'الروسي', 'Ukraine' => 'الأوكراني', 'Greece' => 'اليوناني', 'Switzerland' => 'السويسري',
            'Denmark' => 'الدانماركي', 'Sweden' => 'السويدي', 'Norway' => 'النرويجي', 'Scotland' => 'الاسكتلندي',
            'Japan' => 'الياباني', 'South Korea' => 'الكوري', 'China' => 'الصيني', 'Iran' => 'الإيراني', 'Australia' => 'الأسترالي'
        ];

        // خريطة لتوحيد الأسماء مع المستخدم في الموقع (لضمان عدم تكرار الدوريات بأسماء مختلفة)
        $nameMap = [
            // الدوريات الكبرى
            'Premier League' => 'الدوري الإنجليزي',
            'LaLiga' => 'الدوري الإسباني',
            'Serie A' => 'الدوري الإيطالي',
            'Bundesliga' => 'الدوري الألماني',
            'Ligue 1' => 'الدوري الفرنسي',
            'Championship' => 'دوري البطولة الإنجليزية',
            'Serie B' => 'الدوري الإيطالي الدرجة الثانية',
            'LaLiga 2' => 'الدوري الإسباني الدرجة الثانية',
            '2. Bundesliga' => 'الدوري الألماني الدرجة الثانية',
            'Ligue 2' => 'الدوري الفرنسي الدرجة الثانية',
            
            // البطولات القارية
            'UEFA Champions League' => 'دوري أبطال أوروبا',
            'UEFA Europa League' => 'الدوري الأوروبي',
            'UEFA Conference League' => 'دوري المؤتمر الأوروبي',
            'CAF Champions League' => 'دوري أبطال أفريقيا',
            'CAF Confederation Cup' => 'كأس الكونفيدرالية الأفريقية',
            'AFC Champions League' => 'دوري أبطال آسيا',
            'AFC Champions League Elite' => 'دوري أبطال آسيا للنخبة',
            'AFC Champions League Two' => 'دوري أبطال آسيا 2',
            'FIFA Club World Cup' => 'كأس العالم للأندية',
            'Arab Club Champions Cup' => 'كأس الملك سلمان للأندية',
            'Gulf Cup' => 'كأس الخليج',
            'Asian Cup' => 'كأس آسيا',
            'Africa Cup of Nations' => 'كأس أمم أفريقيا',
            'EURO' => 'كأس أمم أوروبا',
            'Copa América' => 'كوبا أمريكا',
            
            // الدوريات العربية
            'Saudi Pro League' => 'دوري روشن السعودي',
            'Roshn Saudi League' => 'دوري روشن السعودي',
            'Egyptian Premier League' => 'الدوري المصري',
            'Botola Pro' => 'الدوري المغربي',
            'Ligue 1 Professionnelle' => 'الدوري التونسي',
            'Ligue 1 Mobilis' => 'الدوري التونسي',
            'Stars League' => 'دوري نجوم قطر',
            'ADNOC Stars League' => 'دوري أدنوك للمحترفين',
            'UAE Pro League' => 'الدوري الإماراتي',
            'Kuwait League' => 'الدوري الكويتي',
            'Zain Premier League' => 'الدوري الكويتي',
            'Bahrain League' => 'الدوري البحريني',
            'Oman Premier League' => 'الدوري العماني',
            'Jordan League' => 'الدوري الأردني',
            'Pro League' => 'الدوري الأردني', // يحتاج تدقيق حسب الدولة
            'Iraq League' => 'الدوري العراقي',
            'Iraq Stars League' => 'دوري نجوم العراق',
            'Lebanon League' => 'الدوري اللبناني',
            'Sudan League' => 'الدوري السوداني',
            'Libya League' => 'الدوري الليبي',
            'Yemen Premier League' => 'الدوري اليمني',
            
            // دوريات أوروبية أخرى
            'Eredivisie' => 'الدوري الهولندي',
            'Liga Portugal' => 'الدوري البرتغالي',
            'Süper Lig' => 'الدوري التركي',
            'Belgian Pro League' => 'الدوري البلجيكي',
            'Jupiler Pro League' => 'الدوري البلجيكي',
            'Scottish Premiership' => 'الدوري الاسكتلندي',
            'Russian Premier League' => 'الدوري الروسي',
            'Super League 1' => 'الدوري اليوناني',
            'Swiss Super League' => 'الدوري السويسري',
            'Austrian Bundesliga' => 'الدوري النمساوي',
            'Superliga' => 'الدوري الدنماركي',
            'Eliteserien' => 'الدوري النرويجي',
            'Allsvenskan' => 'الدوري السويدي',
            
            // دوريات عالمية
            'Major League Soccer' => 'الدوري الأمريكي',
            'MLS' => 'الدوري الأمريكي',
            'Brasileirão' => 'الدوري البرازيلي',
            'Brasileirao' => 'الدوري البرازيلي',
            'Liga Profesional' => 'الدوري الأرجنتيني',
            'Liga MX' => 'الدوري المكسيكي',
            
            // كؤوس
            'FA Cup' => 'كأس الاتحاد الإنجليزي',
            'Carabao Cup' => 'كأس الرابطة الإنجليزية',
            'Copa del Rey' => 'كأس ملك إسبانيا',
            'Coppa Italia' => 'كأس إيطاليا',
            'DFB Pokal' => 'كأس ألمانيا',
            'Coupe de France' => 'كأس فرنسا',
            'King Cup' => 'كأس خادم الحرمين الشريفين',
            'Egypt Cup' => 'كأس مصر',
            'Community Shield' => 'درع المجتمع الإنجليزي',
            'Supercopa de España' => 'كأس السوبر الإسباني',
            'Supercoppa Italiana' => 'كأس السوبر الإيطالي',
            'DFL-Supercup' => 'كأس السوبر الألماني',
            'Trophée des Champions' => 'كأس السوبر الفرنسي',
            'Friendlies' => 'مباريات ودية',
            'Club Friendlies' => 'مباريات ودية للأندية',
        ];

        foreach ($data['countries'] as $country) {
            if (empty($country['leagues'])) continue;
            $countryName = $country['name'];
            
            foreach ($country['leagues'] as $league) {
                $rawName = trim($league['name']);
                $id = $league['id'];
                $logo = "https://images.fotmob.com/image_resources/logo/leaguelogo/$id.png";
                
                // 1. تحديد الاسم العربي
                $name = $nameMap[$rawName] ?? $rawName;
                
                // تعريب ذكي للدوريات العامة بناءً على الدولة
                if ($name === $rawName) { // إذا لم يتم ترجمته في القائمة أعلاه
                    $arCountry = $countryMap[$countryName] ?? $countryName;
                    
                    if (stripos($rawName, 'Premier League') !== false && $countryName) {
                        $name = "الدوري " . $arCountry; // مثال: الدوري الغاني
                    } elseif (stripos($rawName, 'Cup') !== false && $countryName) {
                        $name = "كأس " . $arCountry;
                    } elseif (stripos($rawName, 'Super Cup') !== false && $countryName) {
                        $name = "كأس السوبر " . $arCountry;
                    }
                }

                // 2. التحقق من وجود الدوري بالاسم العربي
                $stmtCheck->execute([$name]);
                $arabicRow = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                // 3. التحقق من وجود الدوري بالاسم الإنجليزي (القديم)
                $stmtCheck->execute([$rawName]);
                $rawRow = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                if ($arabicRow) {
                    // الدوري موجود بالاسم العربي: تحديث البيانات
                    $stmtUpdate->execute([$logo, $id, $arabicRow['id']]);
                    $count_updated++;
                    
                    // إذا كان موجوداً أيضاً بالاسم الإنجليزي (مكرر)، نقوم بدمجه
                    if ($rawRow && $rawRow['id'] != $arabicRow['id']) {
                        // تحديث المباريات المرتبطة بالاسم القديم لتشير للاسم العربي
                        $stmtUpdateMatches->execute([$name, $rawName]);
                        // حذف السجل القديم المكرر
                        $stmtDelete->execute([$rawRow['id']]);
                        $count_merged++;
                    }
                } elseif ($rawRow) {
                    // الدوري موجود بالاسم الإنجليزي فقط: نقوم بتعريبه (تحديث الاسم)
                    $stmtUpdateName->execute([$name, $logo, $id, $rawRow['id']]);
                    // تحديث اسم البطولة في المباريات أيضاً
                    $stmtUpdateMatches->execute([$name, $rawName]);
                    $count_updated++;
                } else {
                    // دوري جديد كلياً
                    $stmtInsert->execute([$name, $logo, $id]);
                    $count_added++;
                }
            }
        }
        echo "<hr>تمت العملية بنجاح.<br>";
        echo "<span style='color:green'>تم إضافة: $count_added دوري جديد.</span><br>";
        echo "<span style='color:blue'>تم تحديث: $count_updated دوري.</span><br>";
        if ($count_merged > 0) echo "<span style='color:purple'>تم دمج: $count_merged دوري مكرر.</span><br>";
    } else {
        echo "<span style='color:red'>لم يتم العثور على بيانات الدول في الاستجابة.</span><br>";
    }
} else {
    echo "<span style='color:red'>فشل الاتصال بالمصدر. رمز الخطأ: $httpCode</span><br>";
}

echo '<br><br><a href="bot_dashboard.php" style="padding:10px; background:#2563eb; color:white; text-decoration:none; border-radius:5px;">العودة للوحة التحكم</a>';
?>