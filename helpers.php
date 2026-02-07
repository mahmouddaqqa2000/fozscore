<?php
// helpers.php - دوال مساعدة للموقع
function team_logo_html($name, $size = 36, $logo_url = null) {
    if (!empty($logo_url)) {
        return "<img src=\"" . htmlspecialchars($logo_url) . "\" alt=\"" . htmlspecialchars($name) . "\" style=\"width:{$size}px;height:{$size}px;object-fit:contain;display:inline-block;vertical-align:middle;flex-shrink:0;\">";
    }

    $initials = '';
    $parts = preg_split('/\s+/', trim($name));
    if (count($parts) === 1) {
        $initials = mb_substr($parts[0], 0, 1);
    } else {
        $initials = mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1);
    }
    $initials = mb_strtoupper($initials);

    // generate deterministic color from name
    $hash = crc32($name);
    $h = $hash % 360;
    $s = 65;
    $l = 45;
    $bg = "hsl($h, {$s}%, {$l}%)";

    $fontSize = max(10, (int)($size * 0.45));
    $style = "display:inline-flex;align-items:center;justify-content:center;border-radius:50%;width:{$size}px;height:{$size}px;background:{$bg};color:#fff;font-weight:700;font-size:{$fontSize}px;flex-shrink:0;";

    return "<div class=\"team-logo\" style=\"{$style}\">" . htmlspecialchars($initials) . "</div>";
}

function league_logo_html($name, $size = 28, $logo_url = null) {
    if (!empty($logo_url)) {
        return "<img src=\"" . htmlspecialchars($logo_url) . "\" alt=\"" . htmlspecialchars($name) . "\" style=\"width:{$size}px;height:{$size}px;object-fit:contain;display:inline-block;vertical-align:middle;flex-shrink:0;margin-inline-end:6px;\">";
    }

    $initials = '';
    $parts = preg_split('/\s+/', trim($name));
    if (count($parts) === 1) {
        $initials = mb_substr($parts[0], 0, 1);
    } else {
        $initials = mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1);
    }
    $initials = mb_strtoupper($initials);

    // deterministic color from name but lighter variant
    $hash = crc32($name);
    $h = $hash % 360;
    $s = 55;
    $l = 40;
    $bg = "hsl($h, {$s}%, {$l}%)";

    $fontSize = max(10, (int)($size * 0.45));
    $style = "display:inline-flex;align-items:center;justify-content:center;border-radius:50%;width:{$size}px;height:{$size}px;background:{$bg};color:#fff;font-weight:700;font-size:{$fontSize}px;flex-shrink:0;margin-inline-end:6px;";

    return "<div class=\"league-logo\" style=\"{$style}\">" . htmlspecialchars($initials) . "</div>";
}

function format_time_ar($time, $date = null) {
    if (empty($time)) return '';
    try {
        $clean_time = str_replace(['ص', 'م'], ['AM', 'PM'], $time);
        // نفترض أن التوقيت الأصلي هو توقيت القاهرة (مصدر البيانات)
        $timezone = new DateTimeZone('Africa/Cairo');
        $dt = new DateTime($date ? "$date $clean_time" : $clean_time, $timezone);
    } catch (Exception $e) {
        return htmlspecialchars($time);
    }
    $time12 = $dt->format('g:i'); // 12-hour without leading zeros
    $ampm = strtolower($dt->format('a'));
    $arabic = ($ampm === 'am') ? 'ص' : 'م';
    $formatted = $time12 . ' ' . $arabic;

    if ($date) {
        return '<span class="local-time" data-timestamp="' . $dt->format('c') . '">' . $formatted . '</span>';
    }
    return $formatted;
}

/**
 * يبحث عن رابط شعار القناة.
 * لكي تعمل هذه الدالة، يجب إنشاء مجلد `assets/channels/` في جذر المشروع.
 * يجب أن تكون أسماء ملفات الشعارات باللغة الإنجليزية وبأحرف صغيرة، مع استبدال المسافات بـ "-".
 * مثال: "beIN Sports 1" يجب أن يكون اسم ملفها `bein-sports-1.png`.
 *
 * @param string $channel_name اسم القناة.
 * @return string|false رابط الشعار، أو false إذا لم يتم العثور عليه.
 */
function get_channel_logo_url($channel_name) {
    if (empty($channel_name)) {
        return false;
    }

    // يمكنك إضافة المزيد من الأسماء الشائعة هنا لربطها بملفات محددة
    $channel_map = [
        'ssc sport 1 hd' => 'ssc-1.png',
        'ssc 1 hd' => 'ssc-1.png',
    ];

    $normalized_name = strtolower(trim($channel_name));
    
    // استخدم الخريطة أولاً، ثم القاعدة العامة
    $logo_filename = $channel_map[$normalized_name] ?? str_replace(' ', '-', $normalized_name) . '.png';
    
    $logo_path = 'assets/channels/' . $logo_filename;

    if (file_exists(__DIR__ . '/' . $logo_path)) {
        return $logo_path;
    }
    
    return false;
}

/**
 * تحديد حالة المباراة (لم تبدأ، جارية، انتهت) بناءً على الوقت والنتيجة.
 *
 * @param array $match بيانات المباراة.
 * @return array تحتوي على مفتاح الحالة 'key' والنص 'text'.
 */
function get_match_status($match) {
    // إذا لم يتم تحديد الوقت، فالمباراة 'لم تبدأ'.
    if (empty($match['match_date']) || empty($match['match_time'])) {
        return ['key' => 'not_started', 'text' => 'لم تبدأ'];
    }

    try {
        $now = new DateTime();
        $clean_time = str_replace(['ص', 'م'], ['AM', 'PM'], $match['match_time']);
        $match_datetime = new DateTime($match['match_date'] . ' ' . $clean_time);
        
        // إذا كان وقت المباراة في المستقبل، فهي 'لم تبدأ'.
        if ($now < $match_datetime) {
            return ['key' => 'not_started', 'text' => 'لم تبدأ'];
        }

        // نفترض أن المباراة تستمر 120 دقيقة لتحديد الحالة 'جارية'.
        $match_end_time = (clone $match_datetime)->add(new DateInterval('PT120M')); 
        
        // إذا كان الوقت الحالي بين بداية المباراة ونهايتها المفترضة، فهي 'جارية'.
        if ($now <= $match_end_time) {
            return ['key' => 'live', 'text' => 'جارية الآن'];
        }

        // إذا مر أكثر من 120 دقيقة، نعتبرها 'انتهت' حتى لو لم تدخل النتيجة بعد.
        return ['key' => 'finished', 'text' => 'انتهت'];

    } catch (Exception $e) {
        return ['key' => 'not_started', 'text' => 'لم تبدأ'];
    }
}

/**
 * يوزع اللاعبين على الملعب بناءً على خطة اللعب.
 * يفترض أن اللاعبين مرتبون (حارس، دفاع، وسط، هجوم).
 *
 * @param array $players قائمة اللاعبين.
 * @param string $formation خطة اللعب (مثال: '4-3-3').
 * @return array|null مصفوفة منظمة للاعبين أو null.
 */
function parse_lineup_to_formation($players, $formation = '4-3-3') {
    if (empty($players) || count($players) < 11) {
        return null; // لا يمكن عرض التشكيلة إذا كانت غير مكتملة
    }
    // نأخذ أول 11 لاعب فقط
    $players = array_slice($players, 0, 11);

    // معالجة اللاعبين لفصل الاسم عن الصورة (الاسم | الرابط)
    $processed_players = [];
    foreach ($players as $player_str) {
        $parts = explode('|', $player_str);
        $name = trim($parts[0]);
        $image = null;
        $number = null;

        if (isset($parts[1])) {
            $p1 = trim($parts[1]);
            // التحقق مما إذا كان الجزء الثاني رقماً أم رابط صورة
            if (preg_match('/^[0-9]+$/', $p1)) {
                $number = $p1;
            } else {
                $image = $p1;
            }
        }
        
        if (isset($parts[2])) {
            $number = trim($parts[2]);
        }

        $processed_players[] = ['name' => $name, 'image' => $image, 'number' => $number];
    }

    $parts = explode('-', $formation);
    if (count($parts) !== 3 || array_sum($parts) !== 10) {
        $parts = [4, 4, 2]; // خطة افتراضية 4-4-2
    }

    $def_count = (int)$parts[0];
    $mid_count = (int)$parts[1];
    $fwd_count = (int)$parts[2];

    $structured_lineup = [
        'gk' => [array_shift($processed_players)],
        'def' => array_splice($processed_players, 0, $def_count),
        'mid' => array_splice($processed_players, 0, $mid_count),
        'fwd' => array_splice($processed_players, 0, $fwd_count),
    ];

    return $structured_lineup;
}

/**
 * تحليل قائمة لاعبين بسيطة (مثل البدلاء أو الغيابات).
 * الصيغة: الاسم | الصورة | الرقم | معلومات إضافية (إصابة/طرد)
 * الترتيب غير مهم بعد الاسم.
 *
 * @param array $lines مصفوفة الأسطر النصية.
 * @return array مصفوفة مهيكلة.
 */
function parse_simple_list($lines) {
    $processed = [];
    if (empty($lines)) return $processed;
    
    foreach ($lines as $line) {
        if (empty(trim($line))) continue;
        $parts = explode('|', $line);
        $name = trim($parts[0]);
        $image = null;
        $number = null;
        $extra = null;

        for ($i = 1; $i < count($parts); $i++) {
            $p = trim($parts[$i]);
            if (empty($p)) continue;

            if (preg_match('/^[0-9]+$/', $p)) {
                $number = $p;
            } elseif (filter_var($p, FILTER_VALIDATE_URL) || strpos($p, 'http') === 0) {
                $image = $p;
            } else {
                $extra = $p;
            }
        }
        $processed[] = ['name' => $name, 'image' => $image, 'number' => $number, 'extra' => $extra];
    }
    return $processed;
}

/**
 * تحليل قائمة إحصائيات اللاعبين.
 * الصيغة: الاسم | الأهداف | التمريرات الحاسمة
 *
 * @param array $lines مصفوفة الأسطر النصية.
 * @return array مصفوفة مهيكلة.
 */
function parse_player_stats($lines) {
    $processed = [];
    if (empty($lines)) return $processed;
    
    foreach ($lines as $line) {
        if (empty(trim($line))) continue;
        $parts = explode('|', $line);
        $name = trim($parts[0]);
        $goals = isset($parts[1]) ? (int)trim($parts[1]) : 0;
        $assists = isset($parts[2]) ? (int)trim($parts[2]) : 0;
        
        if ($goals > 0 || $assists > 0) {
             $processed[] = ['name' => $name, 'goals' => $goals, 'assists' => $assists];
        }
    }
    return $processed;
}

/**
 * عرض إحصائيات المباراة كأشرطة بيانية.
 *
 * @param string $json_stats نص JSON يحتوي على الإحصائيات.
 * @return string كود HTML للإحصائيات.
 */
function render_match_stats($json_stats, $team_home = null, $team_away = null, $logo_home = null, $logo_away = null) {
    if (empty($json_stats)) return '';
    
    $stats = json_decode($json_stats, true);
    if (!$stats) return '';

    $output = '<div class="stats-container" style="background:white; padding:20px; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.05); margin-top:20px;">';
    
    if ($team_home && $team_away) {
        $output .= '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; padding-bottom:10px; border-bottom:1px solid #f1f5f9;">';
        $output .= '<div>' . team_logo_html($team_home, 40, $logo_home) . '</div>';
        $output .= '<h3 style="margin:0; color:#1e293b; font-size:1.1rem;">إحصائيات المباراة</h3>';
        $output .= '<div>' . team_logo_html($team_away, 40, $logo_away) . '</div>';
        $output .= '</div>';
    } else {
        $output .= '<h3 style="text-align:center; margin-bottom:20px; color:#1e293b; font-size:1.2rem;">إحصائيات المباراة</h3>';
    }
    
    foreach ($stats as $stat) {
        $home = (int)$stat['home'];
        $away = (int)$stat['away'];
        $total = $home + $away;
        
        $homePct = ($total > 0) ? ($home / $total) * 100 : 50;
        $awayPct = ($total > 0) ? ($away / $total) * 100 : 50;
        if ($total == 0) { $homePct = 0; $awayPct = 0; }

        $output .= '<div class="stat-item" style="margin-bottom:15px;">';
        
        // Labels and Numbers
        $output .= '<div style="display:flex; justify-content:space-between; margin-bottom:5px; font-weight:bold; font-size:0.9rem;">';
        $output .= '<span style="color:#10b981;">' . $home . '</span>';
        $output .= '<span style="color:#64748b;">' . htmlspecialchars($stat['label']) . '</span>';
        $output .= '<span style="color:#ef4444;">' . $away . '</span>';
        $output .= '</div>';
        
        // Progress Bar
        $output .= '<div class="progress-track" style="display:flex; height:8px; background:#f1f5f9; border-radius:4px; overflow:hidden;">';
        $output .= '<div style="width:' . $homePct . '%; background:#10b981;"></div>';
        $output .= '<div style="width:' . $awayPct . '%; background:#ef4444;"></div>';
        $output .= '</div>';
        
        $output .= '</div>';
    }
    
    $output .= '</div>';
    return $output;
}

/**
 * دالة للتواصل مع Google Gemini API لاستخراج البيانات
 */
function ask_gemini_json($prompt, $content) {
    // ضع مفتاح API الخاص بك هنا
    $apiKey = 'AIzaSyCckHP1JgyZdrUpTv-Bml5TqCdPX3b0i8s';

    if ($apiKey === 'YOUR_GEMINI_API_KEY') {
        return null; // لم يتم إعداد المفتاح
    }

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.0-pro:generateContent?key=$apiKey";

    $data = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt . "\n\nContext:\n" . substr($content, 0, 30000)] // نرسل أول 30 ألف حرف لتجنب تجاوز الحدود
                ]
            ]
        ],
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    
    if ($response === false) {
        echo "<div style='color:red;margin:10px 0;padding:10px;border:1px solid red;background:#fff0f0;'><strong>Curl Error:</strong> " . curl_error($ch) . "</div>";
        return null;
    }

    // curl_close($ch); // تم تعطيلها لأنها deprecated في نسخ PHP الحديثة

    $result = json_decode($response, true);

    if (!is_array($result)) {
        echo "<div style='color:red;margin:10px 0;padding:10px;border:1px solid red;background:#fff0f0;'><strong>Gemini Error:</strong> Invalid response format.<br>Raw: " . htmlspecialchars(substr($response, 0, 200)) . "...</div>";
        return null;
    }
    
    if (isset($result['error'])) {
        error_log('Gemini API Error: ' . json_encode($result['error']));
        echo "<div style='color:red;margin:10px 0;padding:10px;border:1px solid red;background:#fff0f0;'><strong>Gemini API Error:</strong> " . htmlspecialchars($result['error']['message'] ?? 'Unknown error') . "</div>";
        return null;
    }
    
    // استخراج النص من استجابة Gemini
    return $result['candidates'][0]['content']['parts'][0]['text'] ?? null;
}

/**
 * دالة لسحب الأخبار من YallaKora
 */
function scrape_yallakora_news($pdo, $dateStr = null) {
    if ($dateStr === 'homepage') {
        $url = "https://www.yallakora.com/";
        echo "<hr><h3>جاري سحب أحدث الأخبار من الصفحة الرئيسية لـ YallaKora...</h3>";
    } elseif ($dateStr) {
        $url = "https://www.yallakora.com/newslisting/index?date=$dateStr";
        echo "<hr><h3>جاري سحب الأخبار من YallaKora لتاريخ $dateStr...</h3>";
    } else {
        $url = "https://www.yallakora.com/newslisting";
        echo "<hr><h3>جاري سحب أحدث الأخبار من YallaKora...</h3>";
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
        'Accept-Language: ar,en-US;q=0.9,en;q=0.8',
        'Cache-Control: max-age=0',
        'Connection: keep-alive',
        'Upgrade-Insecure-Requests: 1'
    ]);
    curl_setopt($ch, CURLOPT_REFERER, "https://www.yallakora.com/");
    curl_setopt($ch, CURLOPT_ENCODING, '');
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    $html = curl_exec($ch);

    if (!$html) {
        echo "فشل في جلب صفحة الأخبار.<br>";
        return;
    }

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);

    $queries = [
        "//ul[contains(@id, 'ulNewsList')]//li", "//div[contains(@class, 'newsListing')]//div[contains(@class, 'item')]",
        "//div[contains(@class, 'newsListing')]//li", "//div[contains(@class, 'newsList')]//li",
        "//div[contains(@class, 'news')]//div[contains(@class, 'item')]", "//section[contains(@class, 'news')]//li",
        "//div[contains(@class, 'rightSection')]//li", "//div[contains(@class, 'newsSection')]//li",
        "//div[contains(@class, 'cnts')]//li"
    ];

    $newsItems = null;
    foreach ($queries as $query) {
        $result = $xpath->query($query);
        if ($result->length > 0) { $newsItems = $result; break; }
    }
    
    if (!$newsItems || $newsItems->length === 0) {
        echo "تنبيه: لم يتم العثور على عناصر أخبار في الصفحة ($url).<br>";
        return;
    }

    $count = 0;
    $output = "<div style='display:flex;flex-wrap:wrap;gap:20px;'>";
    $pdo->exec("DELETE FROM news WHERE image_url IS NULL OR image_url = ''");
    $settings = get_site_settings($pdo); // جلب الإعدادات لاستخدامها في الإرسال

    foreach ($newsItems as $item) {
        if ($count >= 20) break;
        $linkNode = $xpath->query(".//a", $item)->item(0);
        if (!$linkNode) continue;
        $href = $linkNode->getAttribute('href');
        $fullLink = (strpos($href, 'http') === 0) ? $href : "https://www.yallakora.com" . $href;
        $imgNode = $xpath->query(".//img", $item)->item(0);
        $imgUrl = '';
        if ($imgNode) {
            $imgUrl = $imgNode->getAttribute('data-src') ?: $imgNode->getAttribute('data-image') ?: $imgNode->getAttribute('src');
            if (!empty($imgUrl) && strpos($imgUrl, 'http') !== 0) $imgUrl = "https://www.yallakora.com" . $imgUrl;
        }
        if (empty($imgUrl)) continue;

        $titleNode = $xpath->query(".//p|.//h3|.//div[contains(@class, 'desc')]", $item)->item(0);
        $title = $titleNode ? trim($titleNode->textContent) : '';
        if (!$title) continue;
        
        $title = preg_replace('/\d{1,2}\s+(?:يناير|فبراير|مارس|أبريل|مايو|يونيو|يوليو|أغسطس|سبتمبر|أكتوبر|نوفمبر|ديسمبر)\s+\d{4}(?:\s+\d{1,2}:\d{2}\s+(?:م|ص))?/u', '', $title);
        $title = trim($title);
        if (mb_strpos($title, 'مواقعنا الأخرى') !== false) continue;
        
        // استبدال "يلا كورة" بـ "كورة فور" في العنوان
        $title = str_replace('يلا كورة', 'كورة فور', $title);

        $stmt = $pdo->prepare("SELECT id FROM news WHERE title = ?");
        $stmt->execute([$title]);
        if ($stmt->fetch()) continue;

        $content = get_yallakora_article_content($fullLink);
        if (!$content) $content = $title;
        
        // استبدال "يلا كورة" بـ "كورة فور" في المحتوى
        $content = str_replace('يلا كورة', 'كورة فور', $content);
        
        // إضافة نص ثابت في نهاية الخبر
        $content .= "\n\nالمصدر: كورة فور سبورت";
        
        $summary = $title;
        if (function_exists('ask_gemini_json') && !empty($content)) {
            $prompt = "قم بكتابة ملخص قصير وجذاب (حوالي 30 كلمة) لهذا الخبر الرياضي باللغة العربية، بأسلوب صحفي مشوق ومناسب لمحركات البحث (SEO).";
            $ai_summary = ask_gemini_json($prompt, $content);
            if ($ai_summary) {
                $summary = trim(str_replace(['```json', '```'], '', $ai_summary));
                $output .= "<div style='color:purple; font-size:0.9em; margin:5px 0; padding:5px; background:#f3e8ff; border-radius:4px;'>🤖 <strong>Gemini:</strong> " . htmlspecialchars($summary) . "</div>";
            }
        }

        $stmt = $pdo->prepare("INSERT INTO news (title, summary, content, image_url) VALUES (?, ?, ?, ?)");
        $stmt->execute([$title, $summary, $content, $imgUrl]);
        
        // --- إرسال إشعار تيليجرام للخبر الجديد ---
        if (!empty($settings['telegram_bot_token']) && !empty($settings['telegram_chat_id'])) {
            $newsId = $pdo->lastInsertId();
            $newsLink = rtrim($settings['site_url'], '/') . "/view_news.php?id=$newsId";
            $msg = "📰 <b>خبر جديد</b>\n\n";
            $msg .= "<b>{$title}</b>\n\n";
            $msg .= "<a href=\"{$newsLink}\">اقرأ التفاصيل كاملة</a>";
            send_telegram_msg($pdo, $msg);
        }
        // -----------------------------------------

        $output .= "<div style='width:350px;border:1px solid #eee;padding:10px;border-radius:8px;background:#fafafa;'>";
        if ($imgUrl) $output .= "<img src='$imgUrl' alt='خبر' style='width:100%;height:180px;object-fit:cover;border-radius:6px;'>";
        $output .= "<h4 style='margin:10px 0 5px 0;font-size:18px;'><a href='$fullLink' target='_blank' style='color:#2563eb;text-decoration:none;'>$title</a></h4>";
        $output .= "<div style='font-size:15px;color:#333;max-height:120px;overflow:auto;'>" . nl2br(htmlspecialchars($content)) . "</div>";
        $output .= "</div>";
        $count++;
    }
    $output .= "</div>";
    
    echo "تم إضافة $count أخبار جديدة.<br>";
    echo $output;
}

function get_yallakora_article_content($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_ENCODING, '');
    $html = curl_exec($ch);
    if (!$html) return null;

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);

    $bodyNode = $xpath->query("//div[contains(@class, 'ArticleDetails')] | //div[contains(@class, 'details')]")->item(0);

    if ($bodyNode) {
        $paragraphs = $xpath->query(".//p", $bodyNode);
        $text = "";
        foreach ($paragraphs as $p) {
            $text .= trim($p->textContent) . "\n\n";
        }
        return empty($text) ? trim($bodyNode->textContent) : $text;
    }
    return null;
}

/**
 * جلب إعدادات الموقع (الاسم، الشعار) من قاعدة البيانات
 */
function get_site_settings($pdo) {
    // إنشاء الجدول إذا لم يكن موجوداً
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        key_name TEXT PRIMARY KEY,
        value TEXT
    )");

    $stmt = $pdo->query("SELECT key_name, value FROM settings");
    $db_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $defaults = [
        'site_name' => 'FozScore',
        'favicon' => '',
        'site_url' => '',
        'primary_color' => '#1e293b',
        'site_description' => 'موقع رياضي شامل يقدم لك أحدث نتائج المباريات، أخبار الكرة العالمية والمحلية، وجداول الترتيب لحظة بلحظة.',
        'social_twitter' => '#',
        'social_facebook' => '#',
        'social_youtube' => '#',
        'social_instagram' => '#',
        'telegram_bot_token' => '',
        'telegram_chat_id' => '',
        'twitter_api_key' => '',
        'twitter_api_secret' => '',
        'twitter_access_token' => '',
        'twitter_access_token_secret' => ''
    ];

    return array_merge($defaults, $db_settings);
}

/**
 * إرسال رسالة عبر تيليجرام باستخدام الإعدادات المحفوظة
 */
function send_telegram_msg($pdo, $message) {
    $settings = get_site_settings($pdo);
    $token = $settings['telegram_bot_token'];
    $chatId = $settings['telegram_chat_id'];

    if (empty($token) || empty($chatId)) {
        return false;
    }

    $url = "https://api.telegram.org/bot$token/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'HTML' // نستخدم HTML لتنسيق الرسالة
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $result = curl_exec($ch);
    // curl_close($ch);

    return $result;
}

/**
 * إرسال تغريدة عبر تويتر (X)
 */
function send_twitter_tweet($pdo, $message, $league_name = null) {
    $settings = get_site_settings($pdo);
    $consumer_key = $settings['twitter_api_key'];
    $consumer_secret = $settings['twitter_api_secret'];
    $oauth_token = $settings['twitter_access_token'];
    $oauth_token_secret = $settings['twitter_access_token_secret'];

    if (empty($consumer_key) || empty($consumer_secret) || empty($oauth_token) || empty($oauth_token_secret)) {
        return false;
    }

    // تحويل HTML إلى نص عادي لتويتر
    // تحويل الروابط: <a href="url">text</a> -> text url
    $text = preg_replace('/<a\s+(?:[^>]*?\s+)?href="([^"]*)"[^>]*>(.*?)<\/a>/i', '$2 $1', $message);
    $text = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $text));
    $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    
    // إضافة هاشتاج للدوري
    if ($league_name) {
        $hashtag = '#' . str_replace(' ', '_', preg_replace('/[^\p{L}\p{N}\s]/u', '', $league_name));
        $text .= "\n" . $hashtag;
    }
    
    $text .= "\n#FozScore";

    $url = 'https://api.twitter.com/2/tweets';
    $method = 'POST';
    
    // إعداد توقيع OAuth 1.0a
    $oauth = [
        'oauth_consumer_key' => $consumer_key,
        'oauth_nonce' => bin2hex(random_bytes(16)),
        'oauth_signature_method' => 'HMAC-SHA1',
        'oauth_timestamp' => time(),
        'oauth_token' => $oauth_token,
        'oauth_version' => '1.0'
    ];

    $base_info = twitter_buildBaseString($url, $method, $oauth);
    $composite_key = rawurlencode($consumer_secret) . '&' . rawurlencode($oauth_token_secret);
    $oauth_signature = base64_encode(hash_hmac('sha1', $base_info, $composite_key, true));
    $oauth['oauth_signature'] = $oauth_signature;

    $header = 'Authorization: OAuth ';
    $values = [];
    foreach($oauth as $key => $value) $values[] = $key . '="' . rawurlencode($value) . '"';
    $header .= implode(', ', $values);

    $payload = ['text' => mb_substr($text, 0, 280)]; // التأكد من حد 280 حرف

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [$header, 'Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    return $response;
}

function twitter_buildBaseString($baseURI, $method, $params) {
    $r = []; ksort($params);
    foreach($params as $key=>$value) $r[] = "$key=" . rawurlencode($value);
    return $method . "&" . rawurlencode($baseURI) . '&' . rawurlencode(implode('&', $r));
}

/**
 * إرسال استفتاء (Poll) عبر تيليجرام
 */
function send_telegram_poll($pdo, $question, $options, $league_name = null) {
    $settings = get_site_settings($pdo);
    $token = $settings['telegram_bot_token'];
    $chatId = $settings['telegram_chat_id'];
    $threadId = null;

    if ($league_name) {
        // التأكد من وجود جدول التخصيص لتجنب الأخطاء
        $pdo->exec("CREATE TABLE IF NOT EXISTS telegram_league_chats (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            league_name TEXT UNIQUE,
            chat_id TEXT,
            thread_id TEXT
        )");
        $stmt = $pdo->prepare("SELECT chat_id, thread_id FROM telegram_league_chats WHERE league_name = ?");
        $stmt->execute([$league_name]);
        $mapping = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($mapping && !empty($mapping['chat_id'])) {
            $chatId = $mapping['chat_id'];
            $threadId = $mapping['thread_id'];
        }
    }

    if (empty($token) || empty($chatId)) return false;

    $url = "https://api.telegram.org/bot$token/sendPoll";
    $data = [
        'chat_id' => $chatId,
        'question' => $question,
        'options' => json_encode($options),
        'is_anonymous' => true, // استفتاء مجهول (الأكثر شيوعاً)
    ];
    
    if (!empty($threadId)) $data['message_thread_id'] = $threadId;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $result = curl_exec($ch);
    // curl_close($ch);

    return $result;
}

// دالة مساعدة لجلب تفاصيل المباراة (التشكيلة) - منسوخة من scraper_all.php
function get_match_details($url) {
    // استخدام CURL لسحب الصفحة
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); // ضروري لبعض الاستضافات
    curl_setopt($ch, CURLOPT_ENCODING, ''); 
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // تقليل المهلة إلى 10 ثواني
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);        // تقليل مهلة القراءة إلى 30 ثانية
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4); // إجبار IPv4 لحل مشاكل التعليق
    // إضافة ترويسات لتقليل احتمالية الحظر أو اختلاف المحتوى على الاستضافة
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
        'Accept-Language: ar,en-US;q=0.9,en;q=0.8',
        'Cache-Control: max-age=0',
        'Connection: keep-alive',
        'Upgrade-Insecure-Requests: 1',
        'Sec-Fetch-Dest: document',
        'Sec-Fetch-Mode: navigate',
        'Sec-Fetch-Site: none',
        'Sec-Fetch-User: ?1',
        'Pragma: no-cache'
    ]);
    curl_setopt($ch, CURLOPT_REFERER, 'https://www.yallakora.com/');
    $html = curl_exec($ch);
    $curl_error = curl_error($ch);
    
    if (!$html) {
        return ['home' => null, 'away' => null, 'coach_home' => null, 'coach_away' => null, 'stats' => null, 'match_events' => null, 'stream_url' => null, 'html_preview' => 'فشل الاتصال: ' . $curl_error];
    }
    
    // التحقق من الحظر (Cloudflare / WAF)
    if (strpos($html, 'Just a moment') !== false || strpos($html, 'Attention Required') !== false) {
        return ['home' => null, 'away' => null, 'coach_home' => null, 'coach_away' => null, 'stats' => null, 'match_events' => null, 'stream_url' => null, 'html_preview' => 'تم حظر الطلب (Cloudflare)'];
    }

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);

    // --- استخراج أحداث المباراة ---
    $events = [];
    
    // 1. استراتيجية XPath: محاولات متعددة للبحث عن قائمة الأحداث
    $eventQueries = [
        "//div[contains(@class, 'eventsTtl')]/following-sibling::ul/li", // الهيكل القياسي
        "//div[@id='events']//ul/li", // هيكل التبويبات القديم
        "//div[contains(@class, 'matchEvents')]//ul/li", // حاوية الأحداث العامة
        "//div[contains(@class, 'events')]//ul/li", // بحث عام عن كلاس events
        "//div[contains(@class, 'tabContent')][contains(@class, 'events')]//ul/li", // محتوى التبويب الجديد
        "//li[.//span[contains(@class, 'min')] and .//div[contains(@class, 'description')]]", // بحث عام ذكي عن أي سطر حدث في الصفحة
        "//div[contains(@class, 'item')][.//span[contains(@class, 'min')] and .//div[contains(@class, 'description')]]" // بحث عن div بدلاً من li
    ];

    $eventNodes = null;
    foreach ($eventQueries as $query) {
        $nodes = $xpath->query($query);
        if ($nodes && $nodes->length > 0) {
            $eventNodes = $nodes;
            break;
        }
    }

    if ($eventNodes) {
        foreach ($eventNodes as $node) {
            $class = $node->getAttribute('class');
            if (strpos($class, 'referee') !== false) continue; // تخطي الحكم

            $min = trim($xpath->query(".//span[contains(@class, 'min')]", $node)->item(0)->textContent ?? '');
            $desc = trim($xpath->query(".//div[contains(@class, 'description')]", $node)->item(0)->textContent ?? '');
            $desc = preg_replace('/\s+/', ' ', $desc); // تنظيف المسافات

            $type = '';
            if (strpos($class, 'goal') !== false) $type = '⚽';
            elseif (strpos($class, 'yellowCard') !== false) $type = '🟨';
            elseif (strpos($class, 'redCard') !== false) $type = '🟥';
            elseif (strpos($class, 'sub') !== false) {
                $type = '🔄';
                $subIn = trim($xpath->query(".//span[contains(@class, 'subIn')]", $node)->item(0)->textContent ?? '');
                $subOut = trim($xpath->query(".//span[contains(@class, 'subOut')]", $node)->item(0)->textContent ?? '');
                if ($subIn && $subOut) $desc = "دخول: $subIn | خروج: $subOut";
            }
            elseif (strpos($class, 'penOut') !== false) $type = '❌ ركلة جزاء ضائعة:';

            if ($desc) {
                $side = strpos($class, 'left') !== false ? '(ضيف)' : '(مستضيف)';
                $events[] = "$min' $type $desc $side";
            }
        }
    }
    
    // 2. استراتيجية Regex (احتياطية قوية): إذا فشل XPath، نبحث في النص مباشرة
    if (empty($events)) {
        // تحسين Regex ليكون أكثر مرونة (لا يعتمد على ترتيب العناصر بدقة)
        // نبحث عن حاوية تحتوي على كلاس حدث، وبداخلها دقيقة ووصف
        preg_match_all('/class="([^"]*(?:goal|yellowCard|redCard|sub)[^"]*)"[^>]*>.*?class="min"[^>]*>([^<]+)<.*?class="description"[^>]*>(.*?)<\/div>/is', $html, $matches_regex, PREG_SET_ORDER);
        
        foreach ($matches_regex as $m) {
            $class = $m[1];
            $min = trim(strip_tags($m[2])); // الدقيقة
            $desc = trim(strip_tags($m[3]));
            $desc = preg_replace('/\s+/', ' ', $desc);
            
            if (strpos($class, 'referee') !== false) continue;

            $type = '';
            if (strpos($class, 'goal') !== false) $type = '⚽';
            elseif (strpos($class, 'yellowCard') !== false) $type = '🟨';
            elseif (strpos($class, 'redCard') !== false) $type = '🟥';
            elseif (strpos($class, 'sub') !== false) {
                $type = '🔄';
                // محاولة استخراج التبديل من الوصف إذا لم يكن واضحاً
                if (preg_match('/<span[^>]*class="subIn"[^>]*>(.*?)<\/span>.*?<span[^>]*class="subOut"[^>]*>(.*?)<\/span>/is', $m[0], $subMatch)) {
                    $desc = "دخول: " . trim(strip_tags($subMatch[1])) . " | خروج: " . trim(strip_tags($subMatch[2]));
                }
            }
            
            $side = strpos($class, 'left') !== false ? '(ضيف)' : '(مستضيف)';
            
            if ($type && $desc) {
                $events[] = "$min' $type $desc $side";
            }
        }
    }
    
    // 3. استراتيجية البحث النصي الشامل (Nuclear Fallback)
    // إذا فشل كل شيء، نبحث عن أي عنصر يحتوي على توقيت (رقم + ')
    if (empty($events)) {
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new DOMXPath($dom);
        
        // نبحث عن أي عنصر يحتوي على نص يشبه التوقيت (مثل 45' أو 90+2')
        $timeNodes = $xpath->query("//*[contains(text(), \"'\")]");
        
        foreach ($timeNodes as $node) {
            $text = trim($node->textContent);
            // التحقق من أن النص هو توقيت فقط (أرقام و ')
            if (preg_match('/^(\d+(?:\+\d+)?)\'$/', $text)) {
                $min = $text;
                // البحث عن الوصف في العناصر المجاورة أو الآباء
                // عادة الوصف يكون في عنصر مجاور أو في نفس الحاوية الأب
                $parent = $node->parentNode;
                $fullText = $parent->textContent;
                $cleanText = trim(str_replace($min, '', $fullText));
                $cleanText = preg_replace('/\s+/', ' ', $cleanText);
                
                // إذا كان النص يحتوي على معلومات مفيدة، نعتبره حدثاً
                if (mb_strlen($cleanText) > 5 && mb_strlen($cleanText) < 100) {
                    // محاولة تخمين النوع من الكلاسات أو النص (اختياري)
                    $events[] = "$min ⚽ $cleanText (مستضيف)"; // افتراضي، سيتم تصحيحه يدوياً أو تحسينه لاحقاً
                }
            }
        }
    }
    
    // 3. استراتيجية البحث النصي الشامل (Nuclear Fallback) - للاستضافات التي قد تستقبل HTML مختلف
    // إذا فشل كل شيء، نبحث عن أي عنصر يحتوي على توقيت (رقم + ')
    if (empty($events)) {
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new DOMXPath($dom);
        
        // نبحث عن أي عنصر يحتوي على نص يشبه التوقيت (مثل 45' أو 90+2')
        $timeNodes = $xpath->query("//*[contains(text(), \"'\")]");
        
        foreach ($timeNodes as $node) {
            $text = trim($node->textContent);
            // التحقق من أن النص هو توقيت فقط (أرقام و ')
            if (preg_match('/^(\d+(?:\+\d+)?)\'$/', $text)) {
                $min = $text;
                // البحث عن الوصف في العناصر المجاورة أو الآباء
                $parent = $node->parentNode;
                $fullText = $parent->textContent;
                $cleanText = trim(str_replace($min, '', $fullText));
                $cleanText = preg_replace('/\s+/', ' ', $cleanText);
                
                // إذا كان النص يحتوي على معلومات مفيدة، نعتبره حدثاً
                if (mb_strlen($cleanText) > 5 && mb_strlen($cleanText) < 100) {
                    // محاولة تخمين النوع من الكلاسات أو النص (اختياري)
                    $events[] = "$min ⚽ $cleanText (مستضيف)"; // افتراضي
                }
            }
        }
    }

    // --- استخراج التشكيلة (Lineups) ---
    $homePlayers = [];
    $awayPlayers = [];
    $coachHome = null;
    $coachAway = null;

    // دالة مساعدة لاستخراج بيانات اللاعب
    $extractPlayer = function($node, $xpath) {
        $nameNode = $xpath->query(".//p[contains(@class, 'playerName')]|.//span[contains(@class, 'name')]|.//p[contains(@class, 'name')]|.//div[contains(@class, 'name')]", $node)->item(0);
        $name = trim($nameNode->textContent ?? '');
        $num = trim($xpath->query(".//p[contains(@class, 'number')]|.//span[contains(@class, 'number')]", $node)->item(0)->textContent ?? '');
        $img = $xpath->query(".//img", $node)->item(0)?->getAttribute('src');
        
        if ($name) {
            $playerStr = $name;
            if ($img) {
                if (strpos($img, 'http') !== 0) $img = "https://www.yallakora.com" . $img;
                $playerStr .= " | " . $img;
            }
            if ($num) $playerStr .= " | " . $num;
            return $playerStr;
        }
        return null;
    };

    // محاولات البحث عن التشكيلة
    $lineupDebug = "لم يتم العثور على التشكيلة";
    
    $lineupQueries = [
        ['//div[@id="squad"]//div[contains(@class, "teamA")]//*[contains(@class, "player")]', '//div[@id="squad"]//div[contains(@class, "teamB")]//*[contains(@class, "player")]'],
        ['//div[contains(@class, "squad")]//div[contains(@class, "teamA")]//*[contains(@class, "player")]', '//div[contains(@class, "squad")]//div[contains(@class, "teamB")]//*[contains(@class, "player")]'],
        ['//div[@id="squad"]//div[contains(@class, "team1")]//*[contains(@class, "player")]', '//div[@id="squad"]//div[contains(@class, "team2")]//*[contains(@class, "player")]'],
        ['//div[contains(@class, "formation")]//div[contains(@class, "teamA")]//*[contains(@class, "player")]', '//div[contains(@class, "formation")]//div[contains(@class, "teamB")]//*[contains(@class, "player")]'],
        ['//div[contains(@class, "matchLineup")]//div[contains(@class, "teamA")]//*[contains(@class, "player")]', '//div[contains(@class, "matchLineup")]//div[contains(@class, "teamB")]//*[contains(@class, "player")]'],
        ['//div[contains(@class, "teamA")]//*[contains(@class, "player")]', '//div[contains(@class, "teamB")]//*[contains(@class, "player")]'],
        // استراتيجية البحث العام: جلب كل اللاعبين في الحاوية وتقسيمهم لاحقاً
        ['//div[@id="squad"]//*[contains(@class, "player")]', ''],
        ['//div[contains(@class, "squad")]//*[contains(@class, "player")]', ''],
        ['//div[contains(@class, "formation")]//*[contains(@class, "player")]', '']
    ];

    foreach ($lineupQueries as $idx => $q) {
        $homeNodes = $xpath->query($q[0]);
        
        if ($q[1] === '') {
            // منطق البحث العام (قائمة واحدة)
            if ($homeNodes->length > 0) {
                $lineupDebug = "تم العثور عليها باستخدام XPath Generic #$idx";
                $allPlayers = [];
                foreach ($homeNodes as $node) { 
                    $p = $extractPlayer($node, $xpath); 
                    if ($p) $allPlayers[] = $p; 
                }
                // تقسيم اللاعبين مناصفة
                $total = count($allPlayers);
                if ($total > 0) {
                    $half = ceil($total / 2);
                    $homePlayers = array_slice($allPlayers, 0, $half);
                    $awayPlayers = array_slice($allPlayers, $half);
                    break;
                }
            }
        } else {
            // المنطق التقليدي (فريقين منفصلين)
            $awayNodes = $xpath->query($q[1]);
            if ($homeNodes->length > 0) {
                $lineupDebug = "تم العثور عليها باستخدام XPath رقم #$idx";
                foreach ($homeNodes as $node) { $p = $extractPlayer($node, $xpath); if ($p) $homePlayers[] = $p; }
                foreach ($awayNodes as $node) { $p = $extractPlayer($node, $xpath); if ($p) $awayPlayers[] = $p; }
                break;
            }
        }
    }

    // استراتيجية Regex (احتياطية قوية) للتشكيلة إذا فشل XPath
    if (empty($homePlayers)) {
        $lineupDebug = "فشل XPath. جاري تجربة Regex...";
        
        // Regex محسن 2.0: البحث عن الأسماء والأرقام بشكل منفصل (أكثر مرونة مع تداخل HTML)
        // نبحث عن أي عنصر يحمل كلاس name أو playerName ويحتوي على نص عربي
        preg_match_all('/class="[^"]*(?:name|playerName)[^"]*"[^>]*>\s*([^\d<]+?)\s*<\//iu', $html, $nameMatches);
        
        if (!empty($nameMatches[1])) {
            $allPlayers = [];
            // تنظيف الأسماء
            $names = array_map('trim', $nameMatches[1]);
            // استبعاد الأسماء القصيرة جداً أو التي تحتوي على كلمات محجوزة
            $names = array_filter($names, function($n) { 
                return mb_strlen($n) > 2 && !in_array($n, ['التشكيل', 'دقيقة بدقيقة', 'إحصائيات', 'أحداث', 'صور', 'فيديو']); 
            });
            
            // البحث عن الأرقام (اختياري) - نحاول ربطها بالترتيب
            preg_match_all('/class="[^"]*number[^"]*"[^>]*>\s*(\d+)\s*<\//i', $html, $numMatches);
            $numbers = $numMatches[1] ?? [];

            // إعادة ترتيب المصفوفة
            $names = array_values($names);
            
            // إذا وجدنا عدداً منطقياً من اللاعبين (مثلاً أكثر من 15 لفريقين)
            if (count($names) >= 11) {
                foreach ($names as $i => $name) {
                    $num = $numbers[$i] ?? '';
                    // لا يمكننا ربط الصور بدقة بهذه الطريقة، لذا نكتفي بالاسم والرقم
                    $playerStr = $name;
                    if ($num) $playerStr .= " | " . $num;
                    $allPlayers[] = $playerStr;
                }
            }
            
            if (!empty($allPlayers)) {
                $total = count($allPlayers);
                $half = ceil($total / 2);
                $homePlayers = array_slice($allPlayers, 0, $half);
                $awayPlayers = array_slice($allPlayers, $half);
                $lineupDebug = "تم العثور عليها باستخدام Regex المنفصل ($total لاعب)";
            } else {
                $lineupDebug .= " فشل Regex المنفصل (الأسماء غير كافية: " . count($names) . ")";
            }
        } else {
            // تشخيص سبب الفشل
            $hasSquad = strpos($html, 'squad') !== false ? 'نعم' : 'لا';
            $hasPlayer = strpos($html, 'player') !== false ? 'نعم' : 'لا';
            $hasFormation = strpos($html, 'formation') !== false ? 'نعم' : 'لا';
            $lineupDebug = "فشل كلي. طول الصفحة: " . strlen($html) . ". كلمات مفتاحية: squad=$hasSquad, player=$hasPlayer, formation=$hasFormation";
        }
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

    return [
        'home' => !empty($homePlayers) ? implode("\n", $homePlayers) : null,
        'away' => !empty($awayPlayers) ? implode("\n", $awayPlayers) : null,
        'coach_home' => $coachHome ?: null,
        'coach_away' => $coachAway ?: null,
        'stats' => !empty($stats) ? json_encode($stats, JSON_UNESCAPED_UNICODE) : null,
        'match_events' => !empty($events) ? implode("\n", $events) : null,
        'stream_url' => null,
        'html_preview' => '',
        'lineup_debug' => $lineupDebug
    ];
}

/**
 * دالة لاستخراج كود البث (iframe) من رابط معين
 */
function get_stream_iframe($url) {
    // =================================================================
    // تم تعطيل هذه الميزة لأنها تتطلب Node.js وهو غير مدعوم على خطة الاستضافة الحالية
    // =================================================================
    return ['success' => false, 'message' => 'تم تعطيل سحب البث لأنه يتطلب Node.js وهو غير مدعوم على خطة الاستضافة الحالية.'];

    $nodeScript = __DIR__ . '/scraper_lineup.js';
    $html = null;

    if (file_exists($nodeScript)) {
        $cmd = "node " . escapeshellarg($nodeScript) . " " . escapeshellarg($url);
        $output = shell_exec($cmd);
        $jsonResult = json_decode($output, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($jsonResult['html'])) {
            $html = $jsonResult['html'];
        } else {
            $html = $output;
        }
    }

    if (!$html || strlen($html) < 100) {
        return ['success' => false, 'message' => 'فشل جلب الصفحة via Puppeteer'];
    }

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    if (strpos($html, '<?xml encoding') === false) {
        $html = '<?xml encoding="UTF-8">' . $html;
    }
    $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);

    $iframeNode = null;
    $allIframes = $xpath->query('//iframe');
    
    foreach ($allIframes as $node) {
        $src = $node->getAttribute('src');
        $style = $node->getAttribute('style');
        
        if (strpos($style, 'z-index') !== false && preg_match('/z-index:\s*\d{5,}/', $style)) continue;
        if (strpos($src, 'google') !== false || strpos($src, 'facebook') !== false) continue;
        
        $parent = $node->parentNode;
        if ($parent && $parent->getAttribute('id') === 'iframe-placeholder') {
            $iframeNode = $node;
            break;
        }
    }
    
    if ($iframeNode) {
        // إصلاح الروابط النسبية في src (مشكلة "بفوتني على موقعي")
        $src = $iframeNode->getAttribute('src');
        if ($src && strpos($src, 'http') !== 0) {
            $parsedUrl = parse_url($url);
            $baseUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
            // إذا كان الرابط يبدأ بـ / نضيف الدومين، وإلا نضيف / والدومين
            $newSrc = $baseUrl . (strpos($src, '/') === 0 ? '' : '/') . $src;
            $iframeNode->setAttribute('src', $newSrc);
        }

        $extracted_code = $dom->saveHTML($iframeNode);
        $extracted_code = preg_replace('/width=["\']\d+(px|%)?["\']/', 'width="100%"', $extracted_code);
        $extracted_code = preg_replace('/height=["\']\d+(px|%)?["\']/', 'height="100%"', $extracted_code);
        return ['success' => true, 'code' => $extracted_code];
    }
    return ['success' => false, 'message' => 'لم يتم العثور على iframe مناسب'];
}

/**
 * تحويل النص إلى صيغة مناسبة للروابط (Slug)
 */
function slugify($text) {
    // استبدال أي شيء ليس حرفاً أو رقماً بشرطة
    $text = preg_replace('~[^\p{L}\p{N}]+~u', '-', $text);
    // إزالة الشرطات من البداية والنهاية
    return trim($text, '-');
}
