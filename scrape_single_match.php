<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
header('Content-Type: text/html; charset=utf-8');
set_time_limit(0);

$url = $_GET['url'] ?? '';
$stats_only = isset($_GET['stats_only']) && $_GET['stats_only'] == '1';

if (empty($url)) {
    die('<div style="text-align:center;padding:20px;font-family:sans-serif;">يرجى إدخال رابط المباراة.<br><a href="bot_dashboard.php">العودة</a></div>');
}

// التحقق مما إذا كان المدخل رابطاً أم نص بحث
$is_search_query = !filter_var($url, FILTER_VALIDATE_URL);

echo '<div style="font-family:sans-serif; direction:rtl; padding:20px;">';

if ($is_search_query) {
    $search_term = $url;
    // تحويل النص إلى رابط بحث جوجل (نضيف كلمة lineup لضمان ظهور التشكيلة)
    $url = "https://www.google.com/search?q=" . urlencode($search_term . " تشكيلة lineup");
    echo "<h3>🔍 جاري البحث التلقائي في جوجل عن: <span style='color:#2563eb'>$search_term</span></h3>";
    echo "<div style='direction:ltr; font-size:0.8rem; color:#666; margin-bottom:20px;'>Search URL: $url</div>";
} else {
    echo "<h3>جاري سحب بيانات المباراة من الرابط:</h3>";
    echo "<div style='direction:ltr; background:#f1f5f9; padding:10px; border-radius:5px; margin-bottom:20px;'>$url</div>";
    if (preg_match('/^https?:\/\/(www\.)?kooora\.com\/?(\?|$)/i', $url) && strpos($url, 'm=') === false && strpos($url, 'match') === false) {
        echo "<div style='color:red; font-weight:bold; margin-bottom:10px; border:1px solid red; padding:10px; border-radius:5px; background:#fff0f0;'>⚠ تنبيه: يبدو أنك تستخدم رابط الصفحة الرئيسية لموقع كووورة.<br>يرجى استخدام رابط مباراة محددة (مثال: يحتوي على ?m= أو /match/).</div>";
    }
}

if ($stats_only) {
    echo "<div style='color:#d97706; font-weight:bold; margin-bottom:10px;'>⚠ وضع سحب الإحصائيات فقط (لن يتم تعديل التشكيلة)</div>";
}

// استدعاء دالة السحب
$details = get_match_details_single($url);

if (empty($details['home']) && empty($details['lineup_image'])) {
    echo "<div style='color:red; font-weight:bold; padding:15px; border:1px solid red; background:#fff0f0; border-radius:8px;'>❌ فشل العثور على التشكيلة.</div>";
    
    // التحقق من وجود CAPTCHA
    if (stripos($details['html_preview'], 'captcha') !== false || stripos($details['html_preview'], 'unusual traffic') !== false) {
        echo "<div style='color:darkred; margin-top:10px; font-weight:bold; padding:10px; background:#ffebeb; border-radius:5px;'>⚠️ تم حظر الطلب بواسطة Google (CAPTCHA).</div>";
        echo "<div style='color:#666; font-size:0.9rem; margin-bottom:10px;'>حاول مرة أخرى بعد قليل، أو استخدم رابط مباشر من موقع آخر (مثل YallaKora أو Kooora).</div>";
    }

    echo "<ul style='margin-top:10px; color:#b91c1c;'>";
    echo "<li>تأكد من أن المباراة لها تشكيلة معلنة حالياً.</li>";
    echo "<li>إذا كنت تستخدم البحث التلقائي، حاول كتابة الأسماء بدقة أكبر (مثال: <b>ليفربول ضد مانشستر سيتي</b>).</li>";
    echo "</ul>";
    echo "<br><strong>معاينة HTML (أول 1000 حرف):</strong><br>";
    echo "<textarea style='width:100%;height:150px;direction:ltr;'>" . htmlspecialchars(substr($details['html_preview'] ?? '', 0, 1000)) . "</textarea>";
} else {
    if (!empty($details['home'])) {
        echo "<div style='color:green; font-weight:bold;'>✅ تم العثور على تشكيلة نصية.</div>";
    }

    if (!empty($details['stats'])) {
        echo "<div style='color:#0891b2; font-weight:bold; margin-top:10px;'>📊 تم العثور على إحصائيات المباراة:</div>";
        $statsArr = json_decode($details['stats'], true);
        echo "<ul style='direction:rtl; text-align:right; background:#f0f9ff; padding:10px; border-radius:5px;'>";
        foreach ($statsArr as $stat) {
            echo "<li>" . htmlspecialchars($stat['label']) . ": " . 
                 "<span style='color:green'>" . htmlspecialchars($stat['home']) . "</span> (مستضيف) - " . 
                 "<span style='color:red'>" . htmlspecialchars($stat['away']) . "</span> (ضيف)" . 
                 "</li>";
        }
        echo "</ul>";
    }
    
    if (!empty($details['lineup_image'])) {
        echo "<div style='color:#d97706; font-weight:bold; margin-top:10px;'>📷 تم العثور على صورة قد تكون للتشكيلة:</div>";
        echo "<img src='" . htmlspecialchars($details['lineup_image']) . "' style='max-width:100%; margin-top:10px; border:1px solid #ccc; border-radius:8px;'><br>";
        echo "<small>الرابط: " . htmlspecialchars($details['lineup_image']) . "</small><br>";
    }

    // محاولة تحديث قاعدة البيانات
    // نحتاج لاستخراج أسماء الفرق من الصفحة لمحاولة مطابقتها مع قاعدة البيانات
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $details['html_full']);
    $xpath = new DOMXPath($dom);
    
    // محاولة استخراج أسماء الفرق (YallaKora)
    $teamHomeName = trim($xpath->query("//div[contains(@class, 'teamA')]//p")->item(0)->textContent ?? '');
    $teamAwayName = trim($xpath->query("//div[contains(@class, 'teamB')]//p")->item(0)->textContent ?? '');
    
    // محاولة FotMob
    if (empty($teamHomeName) && strpos($url, 'fotmob') !== false) {
        $teamHomeName = trim($xpath->query("//span[contains(@class, 'MFHeaderTeamTitle')]")->item(0)->textContent ?? '');
        $teamAwayName = trim($xpath->query("//span[contains(@class, 'MFHeaderTeamTitle')]")->item(1)->textContent ?? '');
    }

    // محاولة Kooora
    if (empty($teamHomeName) && strpos($url, 'kooora.com') !== false) {
        // كووورة غالباً يضع العنوان في <title> بصيغة: المباراة: فريق1 - فريق2
        // أو يمكن استخراجه من جداول المباراة
        $pageTitle = trim($xpath->query("//title")->item(0)->textContent ?? '');
        if (preg_match('/المباراة:\s*(.*?)\s*-\s*(.*)/u', $pageTitle, $matches)) {
            $teamHomeName = trim($matches[1]);
            $teamAwayName = trim($matches[2]);
        }
    }

    // محاولة Google Search (استخراج الأسماء من العنوان أو النتائج)
    if ((empty($teamHomeName) || $is_search_query) && strpos($url, 'google.com') !== false) {
        // في جوجل، نحاول الاعتماد على المدخلات الأصلية للمستخدم إذا كانت بحثاً
        // أو نحاول استخراجها من عنصر النتيجة الرياضية (imso_loa)
        // لكن للأمان، سنعتمد على البحث التقريبي في قاعدة البيانات باستخدام كلمات البحث نفسها
        // إذا لم نستخرج أسماء دقيقة، سنستخدم كلمات البحث كـ "فريق" للبحث
        if ($is_search_query) $teamHomeName = $search_term; 
    }

    if ($teamHomeName && $teamAwayName) {
        echo "<hr><h4>مطابقة المباراة في قاعدة البيانات:</h4>";
        echo "الفرق المستخرجة: <strong>$teamHomeName</strong> ضد <strong>$teamAwayName</strong><br>";

        // البحث في قاعدة البيانات (بحث تقريبي)
        $stmt = $pdo->prepare("SELECT * FROM matches WHERE (team_home LIKE ? OR team_away LIKE ?) AND (team_home LIKE ? OR team_away LIKE ?) ORDER BY id DESC LIMIT 1");
        
        if ($is_search_query) {
            // إذا كان بحثاً، نستخدم النص المدخل للبحث في كلا الحقلين
            $term = '%' . str_replace(' ', '%', $teamHomeName) . '%';
            $stmt = $pdo->prepare("SELECT * FROM matches WHERE team_home LIKE ? OR team_away LIKE ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$term, $term]);
        } else {
            $term1 = '%' . $teamHomeName . '%';
            $term2 = '%' . $teamAwayName . '%';
            $stmt->execute([$term1, $term1, $term2, $term2]);
        }
        
        $match = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($match) {
            echo "تم العثور على مباراة مطابقة بتاريخ: <strong>" . $match['match_date'] . "</strong><br>";
            
            // تحديث التشكيلة
            $updateData = [];
            $sql = "UPDATE matches SET ";
            
            // إذا لم يتم طلب الإحصائيات فقط، قم بتحديث التشكيلة
            if (!$stats_only && !empty($details['home'])) {
                $sql .= "lineup_home = ?, lineup_away = ?, coach_home = COALESCE(?, coach_home), coach_away = COALESCE(?, coach_away) ";
                $updateData[] = $details['home'];
                $updateData[] = $details['away'];
                $updateData[] = $details['coach_home'];
                $updateData[] = $details['coach_away'];
            }

            if (!empty($details['stats'])) {
                if (!empty($updateData)) $sql .= ", ";
                $sql .= "match_stats = ? ";
                $updateData[] = $details['stats'];
            }
            
            // إذا وجدت صورة تشكيلة، يمكننا إضافتها للأخبار أو حقل خاص (حالياً سنضيفها كخبر للمباراة)
            if (!$stats_only && !empty($details['lineup_image'])) {
                $imageNote = "صورة التشكيلة: " . $details['lineup_image'];
                if (!empty($updateData)) $sql .= ", ";
                $sql .= "match_news = ? ";
                $updateData[] = $imageNote;
            }

            $sql .= "WHERE id = ?";
            $updateData[] = $match['id'];

            if (!empty($updateData) && count($updateData) > 1) {
                $stmtUpdate = $pdo->prepare($sql);
                $stmtUpdate->execute($updateData);
                echo "<div style='color:green; font-weight:bold; margin-top:10px;'>✅ تم تحديث بيانات المباراة في قاعدة البيانات بنجاح!</div>";
            } else {
                echo "لا توجد بيانات جديدة لتحديثها.";
            }
        } else {
            echo "<div style='color:orange;'>⚠️ لم يتم العثور على المباراة في قاعدة البيانات. تأكد من أن الأسماء متطابقة أو قم بإضافة المباراة أولاً.</div>";
        }
    } else {
        echo "لم نتمكن من استخراج أسماء الفرق من الصفحة للمطابقة.";
    }
}

echo '<br><br><a href="bot_dashboard.php" style="padding:10px; background:#2563eb; color:white; text-decoration:none; border-radius:5px;">العودة للوحة التحكم</a>';
echo '</div>';

// دالة خاصة لهذا الملف
function get_match_details_single($url) {
    // استخدام CURL بدلاً من Node.js
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_ENCODING, ''); // لدعم ضغط GZIP
    $html = curl_exec($ch);
    // curl_close($ch); // Deprecated

    if (!$html) {
        return ['home' => null, 'away' => null, 'coach_home' => null, 'coach_away' => null, 'stats' => null, 'lineup_image' => null, 'html_preview' => $html];
    }

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    
    // معالجة ترميز كووورة (Windows-1256) إذا لزم الأمر
    if (strpos($url, 'kooora.com') !== false && !preg_match('//u', $html)) {
        $html = mb_convert_encoding($html, 'UTF-8', 'windows-1256');
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    }
    
    $xpath = new DOMXPath($dom);

    $homePlayers = [];
    $awayPlayers = [];

    $extractPlayer = function($node, $xpath) {
        // دعم الهيكلية الجديدة (p.playerName) والقديمة (span.name)
        // نستخدم not(@class='number') لتجنب سحب الرقم كاسم في حال كان p أيضاً
        $nameNode = $xpath->query(".//p[contains(@class, 'playerName')]|.//span[contains(@class, 'name')]|.//p[not(contains(@class, 'number'))]", $node)->item(0);
        $name = trim($nameNode->textContent ?? '');
        
        // دعم الهيكلية الجديدة (p.number) والقديمة (span.number)
        $num = trim($xpath->query(".//p[contains(@class, 'number')]|.//span[contains(@class, 'number')]", $node)->item(0)->textContent ?? '');
        $img = $xpath->query(".//img", $node)->item(0)?->getAttribute('src');
        
        if ($name) {
            $playerStr = $name;
            if ($img) $playerStr .= " | " . $img;
            if ($num) $playerStr .= " | " . $num;
            return $playerStr;
        }
        return null;
    };

    // === منطق خاص لـ Kooora ===
    if (strpos($url, 'kooora.com') !== false) {
        // كووورة يعرض التشكيلة عادة في جداول
        // نبحث عن جداول تحتوي على لاعبين (غالباً روابط للاعبين)
        $playerLinks = $xpath->query("//a[contains(@href, 'player.aspx')] | //a[contains(@href, '/player/')] | //td//a[string-length(text()) > 4]");
        
        // تقسيم اللاعبين (تخميني لأن كووورة لا يفصل بوضوح في الكود أحياناً)
        // سنحاول البحث عن حاويات محددة إذا أمكن، أو نعتمد على الترتيب
        // هذا تنفيذ مبسط:
        $playersFound = [];
        foreach ($playerLinks as $link) {
            $name = trim($link->textContent);
            if ($name && mb_strlen($name) > 2 && !in_array($name, $playersFound) && strpos($name, 'تفاصيل') === false) {
                $playersFound[] = $name;
            }
        }
        
        // إذا وجدنا لاعبين، نقسمهم مناصفة كحل مؤقت (كووورة هيكليته معقدة ومتغيرة)
        if (!empty($playersFound)) {
            $half = ceil(count($playersFound) / 2);
            $homePlayers = array_slice($playersFound, 0, $half);
            $awayPlayers = array_slice($playersFound, $half);
        }
    } else {

    // محاولات متعددة للبحث عن التشكيلة النصية
    $queries = [
        ['//div[contains(@class, "formation")]//div[contains(@class, "teamA")]//*[contains(@class, "player")]', '//div[contains(@class, "formation")]//div[contains(@class, "teamB")]//*[contains(@class, "player")]'],
        ['//div[@id="squad"]//div[contains(@class, "teamA")]//div[contains(@class, "player")]', '//div[@id="squad"]//div[contains(@class, "teamB")]//div[contains(@class, "player")]'],
        ['//div[contains(@class, "teamA")]//div[contains(@class, "player")]', '//div[contains(@class, "teamB")]//div[contains(@class, "player")]'],
        ['//section[contains(@class, "lineup")]//div[contains(@class, "teamA")]//div[contains(@class, "player")]', '//section[contains(@class, "lineup")]//div[contains(@class, "teamB")]//div[contains(@class, "player")]']
    ];

    foreach ($queries as $q) {
        $homeNodes = $xpath->query($q[0]);
        $awayNodes = $xpath->query($q[1]);
        if ($homeNodes->length > 0) break;
    }

    foreach ($homeNodes as $node) { $p = $extractPlayer($node, $xpath); if ($p) $homePlayers[] = $p; }
    foreach ($awayNodes as $node) { $p = $extractPlayer($node, $xpath); if ($p) $awayPlayers[] = $p; }

    } // End else (Non-Kooora)

    $coachHome = trim($xpath->query("//div[contains(@class, 'teamA')]//div[contains(@class, 'manager')]//p")->item(0)->textContent ?? '');
    $coachAway = trim($xpath->query("//div[contains(@class, 'teamB')]//div[contains(@class, 'manager')]//p")->item(0)->textContent ?? '');

    // البحث عن صورة التشكيلة (إذا لم توجد تشكيلة نصية أو كإضافة)
    $lineupImage = null;
    // نبحث عن جميع الصور داخل div#squad
    $squadImgNodes = $xpath->query("//div[@id='squad']//img");
    foreach ($squadImgNodes as $node) {
        $src = $node->getAttribute('src');
        // تجاهل صور التحميل (loader/loading)
        // وتجاهل شعارات الفرق (iosteams, logo)
        if (stripos($src, 'loader') === false && stripos($src, 'loading') === false && stripos($src, 'iosteams') === false && stripos($src, 'logo') === false) {
            $lineupImage = $src;
            // إصلاح الروابط النسبية
            if ($lineupImage && strpos($lineupImage, 'http') !== 0) {
                $lineupImage = "https://www.yallakora.com" . $lineupImage;
            }
            // إصلاح الشرطات المائلة العكسية في الروابط
            $lineupImage = str_replace('\\', '/', $lineupImage);
            break; // نأخذ أول صورة صالحة
        }
    }

    // استخراج الإحصائيات
    if (empty($stats)) {
    $statsNodes = $xpath->query("//div[contains(@class, 'statsDiv')]//ul//li");
    foreach ($statsNodes as $node) {
        $label = trim($xpath->query(".//div[contains(@class, 'desc')]", $node)->item(0)->textContent ?? '');
        $homeVal = trim($xpath->query(".//div[contains(@class, 'teamA')]", $node)->item(0)->textContent ?? '');
        $awayVal = trim($xpath->query(".//div[contains(@class, 'teamB')]", $node)->item(0)->textContent ?? '');
        
        if ($label && ($homeVal !== '' || $awayVal !== '')) {
            $stats[] = ['label' => $label, 'home' => $homeVal, 'away' => $awayVal];
        }
    }
    }

    return [
        'home' => !empty($homePlayers) ? implode("\n", $homePlayers) : null,
        'away' => !empty($awayPlayers) ? implode("\n", $awayPlayers) : null,
        'coach_home' => $coachHome ?: null,
        'coach_away' => $coachAway ?: null,
        'stats' => !empty($stats) ? json_encode($stats, JSON_UNESCAPED_UNICODE) : null,
        'lineup_image' => $lineupImage,
        'html_preview' => substr($html, 0, 2000),
        'html_full' => $html // نحتاجه لاستخراج أسماء الفرق
    ];
}
?>