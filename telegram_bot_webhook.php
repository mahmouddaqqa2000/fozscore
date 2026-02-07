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
        $msg = "أهلاً بك يا $username في بوت خدمات السوشيال ميديا! 🚀\n\n";
        $msg .= "نقدم لك أفضل الخدمات لزيادة التفاعل والمتابعين.\n";
        $msg .= "👇 **يرجى اختيار المنصة التي تريد خدمات لها:**";

        $keyboard = [
            'inline_keyboard' => [
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
            'other' => 'أخرى'
        ];
        
        $platformAr = $platformNames[$platform] ?? $platform;
        
        // البحث عن الخدمات في قاعدة البيانات
        // نبحث عن الخدمات التي يحتوي اسمها أو وصفها على اسم المنصة
        $stmt = $pdo->prepare("SELECT * FROM bot_services WHERE name LIKE ? OR description LIKE ?");
        $stmt->execute(["%$platformAr%", "%$platformAr%"]);
        $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($services)) {
            $msg = "عذراً، لا توجد خدمات متاحة حالياً لمنصة **$platformAr**. 😔\nيرجى المحاولة لاحقاً.";
        } else {
            $msg = "🔥 **خدمات $platformAr المتاحة:**\n\n";
            foreach ($services as $s) {
                $msg .= "💎 <b>{$s['name']}</b>\n";
                $msg .= "💰 السعر: {$s['price']}\n";
                if (!empty($s['description'])) $msg .= "📝 {$s['description']}\n";
                $msg .= "------------------\n";
            }
            
            $contact = $settings['contact_user'] ?? '';
            if ($contact) {
                $msg .= "\n📩 **للطلب والاستفسار تواصل معنا:** $contact";
            }
        }
        
        // زر للعودة للقائمة الرئيسية
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🔙 العودة للقائمة الرئيسية', 'callback_data' => 'back_to_main']
                ]
            ]
        ];

        sendMessage($token, $chat_id, $msg, $keyboard);
    }
    
    if ($data === 'back_to_main') {
        // إعادة إرسال رسالة البداية
        // يمكننا استدعاء نفس المنطق أو إرسال رسالة جديدة
        // هنا سنرسل رسالة جديدة للتبسيط
        $msg = "👇 **يرجى اختيار المنصة التي تريد خدمات لها:**";
        $keyboard = [
            'inline_keyboard' => [
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
?>