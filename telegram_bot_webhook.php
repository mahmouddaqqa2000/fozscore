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

    if ($text === '/start') {
        clearUserState($pdo, $chat_id); // إعادة تعيين الحالة عند البدء
        $msg = "👋 **أهلاً بك يا $username في بوت خدمات السوشيال ميديا!** 🚀\n\n";
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
                clearUserState($pdo, $chat_id); // انتهت المحادثة
                
                // تجهيز ملخص الطلب
                $platform = ucfirst($data['platform']);
                $type = $data['type_label'];
                $qty = $data['qty'];
                $contact = $settings['contact_user'] ?? 'الإدارة';
                
                $msg = "✅ **تم تسجيل تفاصيل طلبك!**\n\n";
                $msg .= "📱 **المنصة:** $platform\n";
                $msg .= "🔧 **الخدمة:** $type\n";
                $msg .= "🔢 **العدد:** $qty\n";
                $msg .= "🔗 **الرابط:** $link\n\n";
                $msg .= "💰 **لإتمام الطلب والدفع، يرجى تحويل هذه الرسالة إلى:**\n$contact";
                
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
        
        $typeLabels = [
            'followers' => 'متابعين', 'likes' => 'لايكات', 
            'views' => 'مشاهدات', 'comments' => 'تعليقات'
            ];
        $typeLabel = $typeLabels[$type] ?? $type;
        
        // حفظ الحالة: ننتظر العدد
        setUserState($pdo, $chat_id, 'WAITING_QTY', ['platform' => $platform, 'type' => $type, 'type_label' => $typeLabel]);
        
        $msg = "🔢 **الكمية المطلوبة ($typeLabel):**\n\nيرجى كتابة العدد الذي تريده (أرقام فقط، مثال: 1000).";
        sendMessage($token, $chat_id, $msg);
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