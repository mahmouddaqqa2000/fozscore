<?php
require_once __DIR__ . '/db.php';

// دالة لجلب إعدادات البوت الجديد
function get_sec_bot_settings_webhook($pdo) {
    $stmt = $pdo->query("SELECT key_name, value FROM secondary_bot_settings");
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

$settings = get_sec_bot_settings_webhook($pdo);
$token = $settings['bot_token'] ?? '';

if (empty($token)) {
    http_response_code(403);
    die("Bot token not configured.");
}

// استقبال التحديث من تيليجرام
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) {
    // لا يوجد تحديث، ربما فتح الملف من المتصفح
    echo "Bot Webhook is active.";
    exit;
}

// 1. معالجة الرسائل النصية (مثل /start)
if (isset($update['message'])) {
    $chat_id = $update['message']['chat']['id'];
    $text = $update['message']['text'] ?? '';
    $username = $update['message']['from']['first_name'] ?? 'مستخدم';

    // --- تسجيل المستخدم أو تحديث بياناته ---
    $stmtUser = $pdo->prepare("SELECT * FROM bot_users WHERE chat_id = ?");
    $stmtUser->execute([$chat_id]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $pdo->prepare("INSERT INTO bot_users (chat_id, username, balance, created_at) VALUES (?, ?, 0.00, ?)")->execute([$chat_id, $username, time()]);
        $balance = 0.00;
    } else {
        $balance = $user['balance'];
        // تحديث الاسم إذا تغير
        if ($user['username'] !== $username) {
            $pdo->prepare("UPDATE bot_users SET username = ? WHERE chat_id = ?")->execute([$username, $chat_id]);
        }
    }

    if ($text === '/start') {
        clearUserState($pdo, $chat_id); // إعادة تعيين الحالة عند البدء
        $msg = "👋 **أهلاً بك يا $username في بوت خدمات السوشيال ميديا!** 🚀\n\n";
        $msg .= "🆔 **ID الخاص بك:** `$chat_id`\n";
        $msg .= "💰 **رصيدك الحالي:** $" . number_format($balance, 2) . "\n\n";
        $msg .= "✨ **نقدم لك أفضل الحلول لزيادة التفاعل والمتابعين على جميع المنصات.**\n";
        $msg .= "✅ خدمات سريعة ومضمونة.\n";
        $msg .= "✅ أسعار منافسة.\n";
        $msg .= "✅ دعم فني متواصل.\n\n";
        $msg .= "👇 **اختر المنصة التي تريد تصفح خدماتها:**";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🔥 العروض الخاصة', 'callback_data' => 'platform_special_offers']
                ],
                [
                    ['text' => '📸 انستجرام', 'callback_data' => 'platform_instagram'],
                    ['text' => '📘 فيسبوك', 'callback_data' => 'platform_facebook']
                ],
                [
                    ['text' => '🎵 تيك توك', 'callback_data' => 'platform_tiktok'],
                    ['text' => '📺 يوتيوب', 'callback_data' => 'platform_youtube']
                ],
                [
                    ['text' => '🐦 تويتر (X)', 'callback_data' => 'platform_twitter'],
                    ['text' => '✈️ تيليجرام', 'callback_data' => 'platform_telegram']
                ],
                [
                    ['text' => '🌐 خدمات أخرى', 'callback_data' => 'platform_other']
                ]
            ]
        ];

        sendMessage($token, $chat_id, $msg, $keyboard);
    } else {
        // معالجة المدخلات النصية (العدد والرابط) بناءً على حالة المستخدم
        $stateData = getUserState($pdo, $chat_id);
        
        if ($stateData) {
            if ($stateData['state'] === 'WAITING_QTY') {
                // المستخدم أدخل العدد
                if (is_numeric($text)) {
                    $qty = intval($text);
                    $newData = $stateData['data'];
                    $newData['qty'] = $qty;
                    setUserState($pdo, $chat_id, 'WAITING_LINK', $newData);
                    
                    $msg = "🔗 **رابط الحساب أو المنشور:**\n\nيرجى إرسال الرابط المطلوب تنفيذ الخدمة عليه.";
                    sendMessage($token, $chat_id, $msg);
                } else {
                    sendMessage($token, $chat_id, "⚠️ يرجى إرسال رقم صحيح (مثال: 1000).");
                }
            } elseif ($stateData['state'] === 'WAITING_LINK') {
                // المستخدم أدخل الرابط
                $link = $text;
                $data = $stateData['data'];
                
                // --- التحقق من صحة الرابط (Validation) ---
                // إذا كانت الخدمة متابعين والمنصة انستجرام، نتحقق من أن الرابط ليس رابط منشور
                if ($data['platform'] === 'instagram' && $data['type'] === 'followers') {
                    if (strpos($link, '/p/') !== false || strpos($link, '/reel/') !== false || strpos($link, '/tv/') !== false) {
                        $errorMsg = "⚠️ **عذراً، الرابط غير صحيح!**\n\n";
                        $errorMsg .= "لقد طلبت خدمة **متابعين**، ولكن الرابط المرسل يبدو أنه رابط **منشور**.\n";
                        $errorMsg .= "يرجى إرسال رابط الحساب (البروفايل) أو اسم المستخدم.";
                        sendMessage($token, $chat_id, $errorMsg);
                        return; // نخرج من الدالة ليبقى المستخدم في حالة انتظار الرابط الصحيح
                    }
                }
                // -----------------------------------------
                
                // --- التحقق من الرصيد وخصم التكلفة (للخدمات المسعرة) ---
                if (isset($data['service_id'])) {
                    $service_id = $data['service_id'];
                    $qty = $data['qty'];
                    
                    $stmtSrv = $pdo->prepare("SELECT * FROM bot_services WHERE id = ?");
                    $stmtSrv->execute([$service_id]);
                    $service = $stmtSrv->fetch(PDO::FETCH_ASSOC);
                    
                    if ($service) {
                        $cost_per_1k = floatval($service['cost'] ?? 0);
                        // إذا كانت الخدمة لها تكلفة محددة
                        if ($cost_per_1k > 0) {
                            $total_cost = ($qty / 1000) * $cost_per_1k;
                            
                            // التحقق من الرصيد الحالي
                            $stmtUser = $pdo->prepare("SELECT balance FROM bot_users WHERE chat_id = ?");
                            $stmtUser->execute([$chat_id]);
                            $current_balance = $stmtUser->fetchColumn();
                            
                            if ($current_balance < $total_cost) {
                                $msg = "🚫 **عذراً، رصيدك غير كافٍ!**\n\n";
                                $msg .= "💵 تكلفة الطلب: $" . number_format($total_cost, 2) . "\n";
                                $msg .= "💰 رصيدك الحالي: $" . number_format($current_balance, 2) . "\n\n";
                                $msg .= "💳 لشحن الرصيد، يرجى إرسال الـ ID الخاص بك للإدارة:\n`$chat_id`";
                                sendMessage($token, $chat_id, $msg);
                                clearUserState($pdo, $chat_id);
                                return; // إيقاف العملية
                            }
                            
                            // خصم الرصيد
                            $new_balance = $current_balance - $total_cost;
                            $pdo->prepare("UPDATE bot_users SET balance = ? WHERE chat_id = ?")->execute([$new_balance, $chat_id]);
                            
                            // إضافة معلومات التكلفة للبيانات لعرضها في الرسالة النهائية
                            $data['total_cost'] = $total_cost;
                            $data['new_balance'] = $new_balance;
                        }
                    }
                }
                else {
                    // --- حالة الطلب العام (بدون خدمة محددة) ---
                    // نتحقق فقط من أن الرصيد أكبر من صفر للسماح بالطلب
                    $stmtUser = $pdo->prepare("SELECT balance FROM bot_users WHERE chat_id = ?");
                    $stmtUser->execute([$chat_id]);
                    $current_balance = $stmtUser->fetchColumn();
                    
                    if ($current_balance <= 0) {
                        $msg = "🚫 **عذراً، رصيدك صفر!**\n\n";
                        $msg .= "لا يمكنك طلب خدمات حتى تقوم بشحن رصيدك.\n";
                        $msg .= "💳 لشحن الرصيد، يرجى إرسال الـ ID الخاص بك للإدارة:\n`$chat_id`";
                        sendMessage($token, $chat_id, $msg);
                        clearUserState($pdo, $chat_id);
                        return; // إيقاف العملية
                    }
                }
                // -------------------------------------------------------

                clearUserState($pdo, $chat_id); // انتهت المحادثة
                
                // تجهيز ملخص الطلب
                $platform = ucfirst($data['platform']);
                $type = $data['type_label'] ?? ($service['name'] ?? 'خدمة');
                $qty = $data['qty'];
                $contact = $settings['contact_user'] ?? 'الإدارة';
                
                $msg = "✅ **تم تأكيد طلبك!** 🚀\n\n";
                $msg .= "طلبك الآن **قيد التنفيذ** وسيتم البدء به قريباً.\n\n";
                $msg .= "📱 **المنصة:** $platform\n";
                $msg .= "🔧 **الخدمة:** $type\n";
                $msg .= "🔢 **العدد:** $qty\n";
                if (isset($data['total_cost'])) {
                    $msg .= "💵 **التكلفة:** $" . number_format($data['total_cost'], 2) . "\n";
                    $msg .= "💰 **الرصيد المتبقي:** $" . number_format($data['new_balance'], 2) . "\n";
                }
                $msg .= "🔗 **الرابط:** $link\n\n";
                if (!isset($data['total_cost'])) $msg .= "💰 **لإتمام الدفع، تواصل مع:** $contact";
                
                sendMessage($token, $chat_id, $msg);
            }
        }
    }
}

// 2. معالجة الضغط على الأزرار (Callback Query)
if (isset($update['callback_query'])) {
    $callback_id = $update['callback_query']['id'];
    $chat_id = $update['callback_query']['message']['chat']['id'];
    $data = $update['callback_query']['data'];

    // إخبار تيليجرام أننا استلمنا الطلب (لإخفاء ساعة التحميل)
    answerCallbackQuery($token, $callback_id);

    if (strpos($data, 'platform_') === 0) {
        $platform = str_replace('platform_', '', $data);
        
        // تحديد اسم المنصة بالعربية للبحث
        $platformNames = [
            'instagram' => 'انستجرام',
            'facebook' => 'فيسبوك',
            'tiktok' => 'تيك توك',
            'youtube' => 'يوتيوب',
            'twitter' => 'تويتر',
            'telegram' => 'تيليجرام',
            'special_offers' => 'العروض الخاصة',
            'other' => 'أخرى'
        ];
        
        $platformAr = $platformNames[$platform] ?? $platform;
        
        // إذا كانت المنصة من المنصات الرئيسية، نعرض خيارات تفاعلية (متابعين، لايكات..)
        // أما "العروض الخاصة" و "أخرى" فتبقى كما هي (قائمة من قاعدة البيانات)
        if (in_array($platform, ['instagram', 'facebook', 'tiktok', 'youtube', 'twitter', 'telegram'])) {
            
            $msg = "✅ لقد اخترت **$platformAr**.\n👇 **ما نوع الخدمة التي تريدها؟**";
            
            // أزرار الخدمات العامة
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '👤 متابعين (Followers)', 'callback_data' => "cat_{$platform}_followers"],
                        ['text' => '❤️ لايكات (Likes)', 'callback_data' => "cat_{$platform}_likes"]
                    ],
                    [
                        ['text' => '👁 مشاهدات (Views)', 'callback_data' => "cat_{$platform}_views"],
                        ['text' => '💬 تعليقات (Comments)', 'callback_data' => "cat_{$platform}_comments"]
                    ],
                    [
                        ['text' => '🔙 رجوع', 'callback_data' => 'back_to_main']
                    ]
                ]
            ];
            
            // إرسال الصورة إذا كانت انستجرام، أو رسالة عادية للباقي
            if ($platform === 'instagram') {
                $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
                $host = $_SERVER['HTTP_HOST'];
                $uri = dirname($_SERVER['REQUEST_URI']);
                $uri = rtrim($uri, '/');
                $photoUrl = "$protocol://$host$uri/instagram.png";
                
                $res = sendPhoto($token, $chat_id, $photoUrl, $msg, $keyboard);
                $json = json_decode($res, true);
                if (!$json || !$json['ok']) {
                    sendMessage($token, $chat_id, $msg, $keyboard);
                }
            } else {
                sendMessage($token, $chat_id, $msg, $keyboard);
            }

        } else {
            // المنطق القديم للعروض الخاصة وغيرها (جلب من قاعدة البيانات)
            $stmt = $pdo->prepare("SELECT * FROM bot_services WHERE category = ? OR (category IS NULL AND (name LIKE ? OR description LIKE ?))");
            $stmt->execute([$platform, "%$platformAr%", "%$platformAr%"]);
            $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($services)) {
                $msg = "عذراً، لا توجد عروض متاحة حالياً في قسم **$platformAr**. 😔";
                $keyboard = ['inline_keyboard' => [[['text' => '🔙 رجوع', 'callback_data' => 'back_to_main']]]];
                sendMessage($token, $chat_id, $msg, $keyboard);
            } else {
                $pIcon = ($platform == 'special_offers') ? '🔥' : '💎';
                $msg = "$pIcon **قائمة $platformAr:**\n\n";
                foreach ($services as $s) {
                    $msg .= "🔹 <b>{$s['name']}</b>\n";
                    $msg .= "💰 السعر: {$s['price']}\n";
                    if (!empty($s['description'])) $msg .= "📝 {$s['description']}\n";
                    $msg .= "------------------\n";
                }
                $contact = $settings['contact_user'] ?? '';
                if ($contact) $msg .= "\n📩 **للطلب:** $contact";
                
                $keyboard = ['inline_keyboard' => [[['text' => '🔙 رجوع', 'callback_data' => 'back_to_main']]]];
                sendMessage($token, $chat_id, $msg, $keyboard);
            }
        }
    }

    // معالجة اختيار نوع الخدمة (متابعين، لايكات...)
    if (strpos($data, 'cat_') === 0) {
        // format: cat_platform_type
        $parts = explode('_', $data);
        $platform = $parts[1];
        $type = $parts[2];
        
        // --- محاولة العثور على خدمات مسجلة في قاعدة البيانات لهذا النوع ---
        $searchTerms = [];
        if ($type == 'followers') $searchTerms = ['متابعين', 'followers', 'متابع'];
        elseif ($type == 'likes') $searchTerms = ['لايكات', 'likes', 'لايك'];
        elseif ($type == 'views') $searchTerms = ['مشاهدات', 'views', 'مشاهدة'];
        elseif ($type == 'comments') $searchTerms = ['تعليقات', 'comments', 'تعليق'];
        
        $services = [];
        if (!empty($searchTerms)) {
            $sql = "SELECT * FROM bot_services WHERE category = ? AND (";
            $params = [$platform];
            $conds = [];
            foreach ($searchTerms as $term) {
                $conds[] = "name LIKE ?";
                $params[] = "%$term%";
            }
            $sql .= implode(' OR ', $conds) . ")";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        if (count($services) > 0) {
            // وجدنا خدمات محددة، نعرضها للمستخدم ليختار منها (وبالتالي نضمن وجود سعر)
            $msg = "👇 **اختر الخدمة المناسبة:**";
            $keyboard = ['inline_keyboard' => []];
            foreach ($services as $s) {
                $btnText = $s['name'] . " (" . $s['price'] . ")";
                $keyboard['inline_keyboard'][] = [['text' => $btnText, 'callback_data' => "srv_" . $s['id']]];
            }
            $keyboard['inline_keyboard'][] = [['text' => '🔙 رجوع', 'callback_data' => "platform_$platform"]];
            sendMessage($token, $chat_id, $msg, $keyboard);
            return; // نتوقف هنا وننتظر اختيار المستخدم للخدمة
        }
        // ----------------------------------------------------------------

        $typeLabels = [
            'followers' => 'متابعين', 'likes' => 'لايكات', 
            'views' => 'مشاهدات', 'comments' => 'تعليقات'
            ];
        $typeLabel = $typeLabels[$type] ?? $type;
        
        // حفظ الحالة (طلب عام): ننتظر العدد
        setUserState($pdo, $chat_id, 'WAITING_QTY', ['platform' => $platform, 'type' => $type, 'type_label' => $typeLabel]);
        
        $msg = "🔢 **الكمية المطلوبة ($typeLabel):**\n\nيرجى كتابة العدد الذي تريده (أرقام فقط، مثال: 1000).";
        sendMessage($token, $chat_id, $msg);
    }

    // معالجة اختيار خدمة محددة (srv_)
    if (strpos($data, 'srv_') === 0) {
        $service_id = str_replace('srv_', '', $data);
        $stmt = $pdo->prepare("SELECT * FROM bot_services WHERE id = ?");
        $stmt->execute([$service_id]);
        $service = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($service) {
            // حفظ الحالة مع service_id ليتم خصم الرصيد لاحقاً
            setUserState($pdo, $chat_id, 'WAITING_QTY', ['platform' => $service['category'], 'type_label' => $service['name'], 'service_id' => $service['id']]);
            $msg = "🔢 **الكمية المطلوبة ({$service['name']}):**\n\nيرجى كتابة العدد الذي تريده (أرقام فقط).";
            sendMessage($token, $chat_id, $msg);
        }
    }
    
    if ($data === 'back_to_main') {
        // إعادة إرسال رسالة البداية
        // يمكننا استدعاء نفس المنطق أو إرسال رسالة جديدة
        // هنا سنرسل رسالة جديدة للتبسيط
        $msg = "👇 **يرجى اختيار المنصة التي تريد خدمات لها:**";
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🔥 العروض الخاصة', 'callback_data' => 'platform_special_offers']
                ],
                [
                    ['text' => '📸 انستجرام', 'callback_data' => 'platform_instagram'],
                    ['text' => '📘 فيسبوك', 'callback_data' => 'platform_facebook']
                ],
                [
                    ['text' => '🎵 تيك توك', 'callback_data' => 'platform_tiktok'],
                    ['text' => '📺 يوتيوب', 'callback_data' => 'platform_youtube']
                ],
                [
                    ['text' => '🐦 تويتر (X)', 'callback_data' => 'platform_twitter'],
                    ['text' => '✈️ تيليجرام', 'callback_data' => 'platform_telegram']
                ],
                [
                    ['text' => '🌐 خدمات أخرى', 'callback_data' => 'platform_other']
                ]
            ]
        ];
        sendMessage($token, $chat_id, $msg, $keyboard);
    }
}

function sendMessage($token, $chat_id, $text, $keyboard = null) {
    $url = "https://api.telegram.org/bot$token/sendMessage";
    $data = ['chat_id' => $chat_id, 'text' => $text, 'parse_mode' => 'HTML'];
    if ($keyboard) $data['reply_markup'] = json_encode($keyboard);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch);
    curl_close($ch);
}

function answerCallbackQuery($token, $callback_query_id) {
    $url = "https://api.telegram.org/bot$token/answerCallbackQuery";
    $data = ['callback_query_id' => $callback_query_id];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch);
    curl_close($ch);
}

function sendPhoto($token, $chat_id, $photo, $caption, $keyboard = null) {
    $url = "https://api.telegram.org/bot$token/sendPhoto";
    $data = ['chat_id' => $chat_id, 'photo' => $photo, 'caption' => $caption, 'parse_mode' => 'HTML'];
    if ($keyboard) $data['reply_markup'] = json_encode($keyboard);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

// --- دوال إدارة الحالة ---
function getUserState($pdo, $chat_id) {
    $stmt = $pdo->prepare("SELECT state, data FROM bot_users_state WHERE chat_id = ?");
    $stmt->execute([$chat_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return ['state' => $row['state'], 'data' => json_decode($row['data'], true)];
    }
    return null;
}

function setUserState($pdo, $chat_id, $state, $data = []) {
    $stmt = $pdo->prepare("INSERT OR REPLACE INTO bot_users_state (chat_id, state, data, updated_at) VALUES (?, ?, ?, ?)");
    $stmt->execute([$chat_id, $state, json_encode($data), time()]);
}

function clearUserState($pdo, $chat_id) {
    $stmt = $pdo->prepare("DELETE FROM bot_users_state WHERE chat_id = ?");
    $stmt->execute([$chat_id]);
}
?>