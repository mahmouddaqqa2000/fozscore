<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php'; // For get_match_status
header('Content-Type: text/html; charset=utf-8');
set_time_limit(0);

echo "<h3>بدء عملية سحب تشكيلات مباريات الأمس (للتجربة)...</h3>";

if (isset($_GET['date'])) {
    $target_date = $_GET['date'];
} else {
    $target_date = date('Y-m-d', strtotime('-1 day'));
}

// جلب مباريات الأمس التي لا تملك تشكيلة بعد (أو حتى التي تملكها للتحديث)
$stmt = $pdo->prepare("SELECT * FROM matches WHERE match_date = ?");
$stmt->execute([$target_date]);
$matches_to_check = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($matches_to_check)) {
    echo "لا توجد مباريات مسجلة للتاريخ $target_date.<br>";
    echo '<br><a href="bot_dashboard.php">العودة</a>';
    exit;
}

echo "تم العثور على " . count($matches_to_check) . " مباراة للتحقق منها...<br><hr>";

$count_updated = 0;

foreach ($matches_to_check as $match) {
    echo "جاري فحص مباراة: <strong>" . htmlspecialchars($match['team_home']) . " ضد " . htmlspecialchars($match['team_away']) . "</strong>... ";

    if (empty($match['source_url'])) {
        echo "<span style='color:orange;'>خطأ: لا يوجد رابط مصدر للمباراة. يرجى تشغيل 'تحديث شامل' أولاً.</span><br>";
        continue;
    }

    echo "<br><small>الرابط: <a href='" . htmlspecialchars($match['source_url']) . "' target='_blank'>" . htmlspecialchars($match['source_url']) . "</a></small><br>";

    $details = get_match_details($match['source_url']);
    
    if (!empty($details['home'])) {
        try {
            $update = $pdo->prepare("UPDATE matches SET lineup_home = ?, lineup_away = ?, coach_home = COALESCE(?, coach_home), coach_away = COALESCE(?, coach_away), match_stats = COALESCE(?, match_stats) WHERE id = ?");
            $update->execute([$details['home'], $details['away'], $details['coach_home'], $details['coach_away'], $details['stats'], $match['id']]);
        } catch (PDOException $e) {
            die("<div style='color:red;padding:10px;background:#fff0f0;border:1px solid red;'><b>خطأ في قاعدة البيانات:</b> " . $e->getMessage() . "<br>يبدو أن عمود 'match_stats' غير موجود. يرجى الذهاب إلى لوحة التحكم وتشغيل 'فحص وتحديث قاعدة البيانات'.</div>");
        }
        echo "<span style='color:green;'>✔ تم سحب وتحديث التشكيلة بنجاح!</span><br>";
        $count_updated++;
    } else {
        echo "<span style='color:red;'>لم يتم العثور على تشكيلة في المصدر.</span><br>";
        if (!empty($details['html_preview'])) {
            echo "<textarea style='width:100%;height:200px;font-size:11px;direction:ltr;color:#d32f2f;background:#fff0f0;margin-top:5px;border:1px solid #ffcdd2;'>" . htmlspecialchars($details['html_preview']) . "</textarea><br>";
        }
    }
    
    usleep(300000); // انتظار 0.3 ثانية بين كل طلب
}

echo "<hr>تم الانتهاء. تم تحديث تشكيلة <strong>$count_updated</strong> مباراة.<br>";
echo '<br><a href="bot_dashboard.php" style="padding:10px; background:#2563eb; color:white; text-decoration:none; border-radius:5px;">العودة للوحة التحكم</a>';


// دالة مساعدة لجلب تفاصيل المباراة (التشكيلة) - منسوخة من scraper_all.php
function get_match_details($url) {
    // استخدام Puppeteer عبر Node.js
    $nodeScript = __DIR__ . '/scraper_lineup.js';
    $html = null;

    if (file_exists($nodeScript)) {
        // استدعاء Node.js
        $cmd = "node " . escapeshellarg($nodeScript) . " " . escapeshellarg($url);
        $output = shell_exec($cmd);

        // محاولة فك تشفير JSON
        $jsonResult = json_decode($output, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($jsonResult['html'])) {
            $html = $jsonResult['html'];
        } else {
            $html = $output;
        }
    }

    if (!$html || strlen($html) < 100 || stripos($html, '<html') === false) {
        return ['home' => null, 'away' => null, 'coach_home' => null, 'coach_away' => null, 'stats' => null, 'html_preview' => 'Puppeteer Error: ' . $html];
    }

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
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

    // === منطق خاص لـ FotMob ===
    if (strpos($url, 'fotmob.com') !== false) {
        // البحث عن حاوية التشكيلة في FotMob
        $lineupContainer = $xpath->query('//div[@data-testid="lineups"]')->item(0);
        if ($lineupContainer) {
            // استخراج جميع روابط اللاعبين (عادة تكون داخل a href="/players/...")
            $playerNodes = $xpath->query('.//a[contains(@href, "/players/")]', $lineupContainer);
            
            // FotMob يعرض الفريقين بالتتابع، لذا نقسم القائمة إلى نصفين
            $totalPlayers = $playerNodes->length;
            $half = floor($totalPlayers / 2);
            
            for ($i = 0; $i < $totalPlayers; $i++) {
                $name = trim($playerNodes->item($i)->textContent);
                if ($name) {
                    if ($i < $half) $homePlayers[] = $name;
                    else $awayPlayers[] = $name;
                }
            }
            // محاولة استخراج المدربين (قد تحتاج لتحديث المحددات حسب تصميم FotMob الحالي)
            // حالياً نكتفي باللاعبين
        }
    } else {
    // === منطق YallaKora (القديم) ===
    // محاولات متعددة للبحث عن التشكيلة في نفس الصفحة
    $queries = [
        ['//div[contains(@class, "formation")]//div[contains(@class, "teamA")]//*[contains(@class, "player")]', '//div[contains(@class, "formation")]//div[contains(@class, "teamB")]//*[contains(@class, "player")]'],
        ['//div[@id="squad"]//div[contains(@class, "teamA")]//div[contains(@class, "player")]', '//div[@id="squad"]//div[contains(@class, "teamB")]//div[contains(@class, "player")]'],
        ['//div[contains(@class, "teamA")]//div[contains(@class, "player")]', '//div[contains(@class, "teamB")]//div[contains(@class, "player")]'],
        ['//div[contains(@class, "team1")]//div[contains(@class, "player")]', '//div[contains(@class, "team2")]//div[contains(@class, "player")]'],
        ['//div[contains(@class, "home")]//div[contains(@class, "player")]', '//div[contains(@class, "away")]//div[contains(@class, "player")]'],
        ['//section[contains(@class, "lineup")]//div[contains(@class, "teamA")]//div[contains(@class, "player")]', '//section[contains(@class, "lineup")]//div[contains(@class, "teamB")]//div[contains(@class, "player")]']
    ];

    foreach ($queries as $q) {
        $homeNodes = $xpath->query($q[0]);
        $awayNodes = $xpath->query($q[1]);
        if ($homeNodes->length > 0) {
            break;
        }
    }

    // معالجة النتائج
    foreach ($homeNodes as $node) {
        $p = $extractPlayer($node, $xpath);
        if ($p) $homePlayers[] = $p;
    }

    foreach ($awayNodes as $node) {
        $p = $extractPlayer($node, $xpath);
        if ($p) $awayPlayers[] = $p;
    }
    } // end else

    $coachHome = trim($xpath->query("//div[contains(@class, 'teamA')]//div[contains(@class, 'manager')]//p")->item(0)->textContent ?? '');
    $coachAway = trim($xpath->query("//div[contains(@class, 'teamB')]//div[contains(@class, 'manager')]//p")->item(0)->textContent ?? '');

    // استخراج الإحصائيات (YallaKora)
    $stats = [];
    $statsNodes = $xpath->query("//div[contains(@class, 'statsDiv')]//ul//li");
    foreach ($statsNodes as $node) {
        $label = trim($xpath->query(".//div[contains(@class, 'desc')]", $node)->item(0)->textContent ?? '');
        $homeVal = trim($xpath->query(".//div[contains(@class, 'teamA')]", $node)->item(0)->textContent ?? '');
        $awayVal = trim($xpath->query(".//div[contains(@class, 'teamB')]", $node)->item(0)->textContent ?? '');
        
        if ($label && ($homeVal !== '' || $awayVal !== '')) {
            $stats[] = ['label' => $label, 'home' => $homeVal, 'away' => $awayVal];
        }
    }

    return [
        'home' => !empty($homePlayers) ? implode("\n", $homePlayers) : null,
        'away' => !empty($awayPlayers) ? implode("\n", $awayPlayers) : null,
        'coach_home' => $coachHome ?: null,
        'coach_away' => $coachAway ?: null,
        'stats' => !empty($stats) ? json_encode($stats, JSON_UNESCAPED_UNICODE) : null,
        'html_preview' => substr($html, 0, 20000) // زيادة حجم المعاينة أكثر
    ];
}
?>