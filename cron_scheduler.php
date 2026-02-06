<?php
// cron_scheduler.php - يتم تشغيله عبر Cron Job كل دقيقة
require_once __DIR__ . '/db.php';

// هام: اضبط هذا التوقيت ليتطابق مع توقيت المباريات المخزنة في قاعدة البيانات
// نستخدم نفس توقيت db.php (Asia/Riyadh) لضمان دقة الحسابات
date_default_timezone_set('Asia/Riyadh'); 

// منع توقف السكربت
set_time_limit(0);

$now = time();
$today = date('Y-m-d');

echo "Checking matches for $today at " . date('H:i:s') . "\n";

// جلب مباريات اليوم (سواء للتشكيلة أو الإحصائيات)
$stmt = $pdo->prepare("SELECT * FROM matches WHERE match_date = ?");
$stmt->execute([$today]);
$matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($matches as $match) {
    if (empty($match['match_time']) || empty($match['source_url'])) continue;

    // تنظيف وقت المباراة (بعض الأوقات قد تحتوي على نصوص مثل "م" أو "ص")
    // سنحاول تحويلها باستخدام strtotime الذي ذكي بما يكفي لمعظم الصيغ
    $timeStr = str_replace(['ص', 'م'], ['AM', 'PM'], $match['match_time']);
    $matchTimestamp = strtotime("$today $timeStr");

    if ($matchTimestamp === false) {
        echo "Error parsing time for match ID {$match['id']}: {$match['match_time']}\n";
        continue;
    }

    // حساب الفرق بالدقائق
    $diffSeconds = $matchTimestamp - $now;
    $diffMinutes = $diffSeconds / 60;
    // ملاحظة:
    // قيمة موجبة (+) تعني المباراة في المستقبل
    // قيمة سالبة (-) تعني المباراة بدأت أو انتهت

    $should_fetch = false;

    // 1. سحب التشكيلة: إذا كانت ناقصة والوقت (قبل 15 دقيقة من البداية إلى 30 دقيقة بعد البداية)
    if (empty($match['lineup_home']) && $diffMinutes <= 15 && $diffMinutes >= -30) {
        $should_fetch = true;
        echo "[Lineup Check] ";
    }

    // 2. سحب الإحصائيات: إذا كانت ناقصة والوقت (بعد 15 دقيقة من البداية إلى 3 ساعات بعد البداية)
    // هذا يغطي وقت المباراة بالكامل وفترة ما بعد النهاية
    if (empty($match['match_stats']) && $diffMinutes <= -15 && $diffMinutes >= -180) {
        $should_fetch = true;
        echo "[Stats Check] ";
    }

    if ($should_fetch) {
        echo "Fetching details for: " . $match['team_home'] . " vs " . $match['team_away'] . " (Time: {$match['match_time']})\n";
        
        $details = get_match_details_cron($match['source_url']);
        
        if (!empty($details['home']) || !empty($details['stats'])) {
            $update = $pdo->prepare("UPDATE matches SET lineup_home = COALESCE(?, lineup_home), lineup_away = COALESCE(?, lineup_away), coach_home = COALESCE(?, coach_home), coach_away = COALESCE(?, coach_away), match_stats = COALESCE(?, match_stats), match_events = COALESCE(?, match_events) WHERE id = ?");
            $update->execute([$details['home'], $details['away'], $details['coach_home'], $details['coach_away'], $details['stats'], $details['match_events'], $match['id']]);
            echo "Data updated successfully!\n";
        }
    }
}

// دالة السحب (مقتبسة من scrape_single_match.php)
function get_match_details_cron($url) {
    // =================================================================
    // تم تعطيل هذه الميزة لأنها تتطلب Node.js وهو غير مدعوم على خطة الاستضافة الحالية
    // سيعيد هذا التعديل قيمة فارغة دائماً لمنع تعليق السكربت
    // =================================================================
    return ['home' => null, 'away' => null, 'coach_home' => null, 'coach_away' => null, 'stats' => null, 'match_events' => null];

    $nodeScript = __DIR__ . '/scraper_lineup.js';
    $html = null;
    $matchEventsStr = null;

    if (file_exists($nodeScript)) {
        $cmd = "node " . escapeshellarg($nodeScript) . " " . escapeshellarg($url) . " 2>&1";
        $output = shell_exec($cmd);
        
        // محاولة فك تشفير JSON
        $jsonResult = json_decode($output, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($jsonResult['html'])) {
            $html = $jsonResult['html'];
            $matchEventsStr = !empty($jsonResult['extracted_events']) ? implode("\n", $jsonResult['extracted_events']) : null;
        } else {
            $html = $output;
        }
    }

    if (!$html || strlen($html) < 100) {
        return ['home' => null, 'away' => null, 'coach_home' => null, 'coach_away' => null, 'stats' => null, 'match_events' => null];
    }

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);

    $homePlayers = [];
    $awayPlayers = [];

    $extractPlayer = function($node, $xpath) {
        $nameNode = $xpath->query(".//p[contains(@class, 'playerName')]|.//span[contains(@class, 'name')]|.//p[not(contains(@class, 'number'))]", $node)->item(0);
        $name = trim($nameNode->textContent ?? '');
        
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

    // منطق FotMob
    if (strpos($url, 'fotmob.com') !== false) {
        $lineupContainer = $xpath->query('//div[@data-testid="lineups"]')->item(0);
        if ($lineupContainer) {
            $playerNodes = $xpath->query('.//a[contains(@href, "/players/")]', $lineupContainer);
            $totalPlayers = $playerNodes->length;
            $half = floor($totalPlayers / 2);
            for ($i = 0; $i < $totalPlayers; $i++) {
                $name = trim($playerNodes->item($i)->textContent);
                if ($name) {
                    if ($i < $half) $homePlayers[] = $name; else $awayPlayers[] = $name;
                }
            }
        }
    } else {
        // منطق YallaKora
        $queries = [
            ['//div[contains(@class, "formation")]//div[contains(@class, "teamA")]//*[contains(@class, "player")]', '//div[contains(@class, "formation")]//div[contains(@class, "teamB")]//*[contains(@class, "player")]'],
            ['//div[@id="squad"]//div[contains(@class, "teamA")]//div[contains(@class, "player")]', '//div[@id="squad"]//div[contains(@class, "teamB")]//div[contains(@class, "player")]'],
            ['//div[contains(@class, "teamA")]//div[contains(@class, "player")]', '//div[contains(@class, "teamB")]//div[contains(@class, "player")]']
        ];
        foreach ($queries as $q) {
            $homeNodes = $xpath->query($q[0]); $awayNodes = $xpath->query($q[1]);
            if ($homeNodes->length > 0) break;
        }
        foreach ($homeNodes as $node) { $p = $extractPlayer($node, $xpath); if ($p) $homePlayers[] = $p; }
        foreach ($awayNodes as $node) { $p = $extractPlayer($node, $xpath); if ($p) $awayPlayers[] = $p; }
    }
    $coachHome = trim($xpath->query("//div[contains(@class, 'teamA')]//div[contains(@class, 'manager')]//p")->item(0)->textContent ?? '');
    $coachAway = trim($xpath->query("//div[contains(@class, 'teamB')]//div[contains(@class, 'manager')]//p")->item(0)->textContent ?? '');
    
    // استخراج الإحصائيات
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

    return ['home' => !empty($homePlayers) ? implode("\n", $homePlayers) : null, 'away' => !empty($awayPlayers) ? implode("\n", $awayPlayers) : null, 'coach_home' => $coachHome ?: null, 'coach_away' => $coachAway ?: null, 'stats' => !empty($stats) ? json_encode($stats, JSON_UNESCAPED_UNICODE) : null, 'match_events' => $matchEventsStr];
}
?>