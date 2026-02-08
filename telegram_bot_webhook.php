<?php
// إعدادات لمنع تكرار التنفيذ وإغلاق الاتصال بسرعة
ignore_user_abort(true);
set_time_limit(0);
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
                    ['text' => '⭐️ شحن الرصيد', 'callback_data' => 'recharge_stars_menu'],
                    ['text' => '📜 سجل طلباتي', 'callback_data' => 'my_orders']
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
                ],
                [
                    ['text' => '👤 حسابي', 'callback_data' => 'my_account']
                ]
            ]
        ];

        sendMessage($token, $chat_id, $msg, $keyboard);
    } elseif (isset($update['message']['successful_payment'])) {
        // --- معالجة الدفع الناجح (إضافة الرصيد) ---
        $payment = $update['message']['successful_payment'];
        $payload = $payment['invoice_payload']; // topup_STARS_USD
        $total_amount = $payment['total_amount']; // عدد النجوم
        
        if (strpos($payload, 'topup_') === 0) {
            $parts = explode('_', $payload);
            $stars = intval($parts[1]);
            $usd_amount = floatval($parts[2]);
            
            // تحديث رصيد المستخدم
            $stmtUser = $pdo->prepare("SELECT balance, username FROM bot_users WHERE chat_id = ?");
            $stmtUser->execute([$chat_id]);
            $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
            $current = $userRow['balance'] ?? 0;
            $username = $userRow['username'] ?? 'Unknown';
            
            $new_balance = $current + $usd_amount;
            
            $pdo->prepare("UPDATE bot_users SET balance = ? WHERE chat_id = ?")->execute([$new_balance, $chat_id]);
            
            // تسجيل المعاملة
            $pdo->prepare("INSERT INTO bot_transactions (chat_id, username, amount, stars, created_at) VALUES (?, ?, ?, ?, ?)")->execute([$chat_id, $username, $usd_amount, $stars, time()]);
            
            // إشعار الإدارة (باستخدام Chat ID المحفوظ في الإعدادات)
            $admin_chat_id = $settings['chat_id'] ?? '';
            if ($admin_chat_id) {
                $adminMsg = "🔔 **عملية شحن جديدة!**\n\n👤 المستخدم: " . htmlspecialchars($username) . " (`$chat_id`)\n⭐️ النجوم: $stars\n💰 المبلغ: $" . number_format($usd_amount, 2) . "\n⏰ الوقت: " . date('Y-m-d H:i:s');
                sendMessage($token, $admin_chat_id, $adminMsg);
            }
            
            $msg = "✅ **تم شحن الرصيد بنجاح!**\n\n";
            $msg .= "💰 المبلغ المضاف: $" . number_format($usd_amount, 2) . "\n";
            $msg .= "💵 رصيدك الحالي: $" . number_format($new_balance, 2) . "\n";
            sendMessage($token, $chat_id, $msg);
        }
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
                    
                    // --- حساب التكلفة والتحقق من الرصيد فوراً ---
                    if (isset($newData['service_id'])) {
                        $stmtSrv = $pdo->prepare("SELECT * FROM bot_services WHERE id = ?");
                        $stmtSrv->execute([$newData['service_id']]);
                        $service = $stmtSrv->fetch(PDO::FETCH_ASSOC);
                        
                        if ($service) {
                            // التحقق من الحد الأدنى للكمية
                            $min_qty = $service['min_qty'] ?? 500;
                            if ($qty < $min_qty) {
                                sendMessage($token, $chat_id, "⚠️ **الكمية قليلة جداً!**\nأقل كمية مسموح بها لهذه الخدمة هي: **$min_qty**.\nيرجى إرسال رقم صحيح أكبر من أو يساوي $min_qty.");
                                return;
                            }

                            $cost_per_1k = floatval($service['cost'] ?? 0);
                            if ($cost_per_1k > 0) {
                                $total_cost = ($qty / 1000) * $cost_per_1k;
                                
                                // التحقق من الرصيد
                                $stmtUser = $pdo->prepare("SELECT balance FROM bot_users WHERE chat_id = ?");
                                $stmtUser->execute([$chat_id]);
                                $current_balance = $stmtUser->fetchColumn();
                                
                                if ($current_balance < $total_cost) {
                                    $msg = "🚫 **عذراً، رصيدك غير كافٍ!**\n\n";
                                    $msg .= "💵 تكلفة الطلب: $" . number_format($total_cost, 2) . " (لعدد $qty)\n";
                                    $msg .= "💰 رصيدك الحالي: $" . number_format($current_balance, 2) . "\n\n";
                                    $keyboard = ['inline_keyboard' => [[['text' => '⭐️ شحن الرصيد (نجوم)', 'callback_data' => 'recharge_stars_menu']]]];
                                    sendMessage($token, $chat_id, $msg, $keyboard);
                                    return; // إيقاف العملية هنا
                                }
                                
                                // الرصيد كافٍ: حفظ التكلفة وطلب الرابط
                                $newData['total_cost'] = $total_cost;
                                
                                $msg = "💵 **التكلفة المتوقعة:** $" . number_format($total_cost, 2) . "\n";
                                $msg .= "🔗 **رابط الحساب أو المنشور:**\n\nيرجى إرسال الرابط المطلوب تنفيذ الخدمة عليه.";
                                
                                setUserState($pdo, $chat_id, 'WAITING_LINK', $newData);
                                sendMessage($token, $chat_id, $msg);
                                return;
                            }
                        }
                    }
                    // -------------------------------------------

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
                
                // حفظ الرابط في البيانات
                $data['link'] = $link;
                
                // تجهيز ملخص الطلب للمراجعة
                $platform = ucfirst($data['platform']);
                $type = $data['type_label'] ?? 'خدمة';
                $qty = $data['qty'];
                $total_cost = $data['total_cost'] ?? 0;
                
                // التحقق النهائي من الرصيد قبل عرض زر التأكيد
                if ($total_cost > 0) {
                    $stmtUser = $pdo->prepare("SELECT balance FROM bot_users WHERE chat_id = ?");
                    $stmtUser->execute([$chat_id]);
                    $current_balance = $stmtUser->fetchColumn();
                    
                    if ($current_balance < $total_cost) {
                        $msg = "🚫 **عذراً، رصيدك غير كافٍ!**\n\n";
                        $msg .= "💵 تكلفة الطلب: $" . number_format($total_cost, 2) . "\n";
                        $msg .= "💰 رصيدك الحالي: $" . number_format($current_balance, 2) . "\n";
                        $keyboard = ['inline_keyboard' => [[['text' => '⭐️ شحن الرصيد (نجوم)', 'callback_data' => 'recharge_stars_menu']]]];
                        sendMessage($token, $chat_id, $msg, $keyboard);
                        clearUserState($pdo, $chat_id);
                        return;
                    }
                } else {
                    // للطلبات العامة (بدون تكلفة محددة)، نتحقق أن الرصيد > 0
                    $stmtUser = $pdo->prepare("SELECT balance FROM bot_users WHERE chat_id = ?");
                    $stmtUser->execute([$chat_id]);
                    $current_balance = $stmtUser->fetchColumn();
                    if ($current_balance <= 0) {
                        $msg = "🚫 **عذراً، رصيدك صفر!**\n\n";
                        $contact = $settings['contact_user'] ?? 'الإدارة';
                        $msg .= "لا يمكنك طلب خدمات حتى تقوم بشحن رصيدك.\n";
                        $msg .= "💳 لشحن الرصيد، تواصل مع: $contact";
                        
                        $keyboard = null;
                        if ($contact && strpos($contact, '@') === 0) {
                            $adminUser = substr($contact, 1);
                            $keyboard = ['inline_keyboard' => [[['text' => '💳 شحن الرصيد', 'url' => "https://t.me/$adminUser"]]]];
                        }
                        sendMessage($token, $chat_id, $msg, $keyboard);
                        clearUserState($pdo, $chat_id);
                        return; // إيقاف العملية
                    }
                }
                
                $msg = " **مراجعة الطلب:**\n\n";
                $msg .= "📱 **المنصة:** $platform\n";
                $msg .= "🔧 **الخدمة:** $type\n";
                $msg .= "🔢 **العدد:** $qty\n";
                $msg .= " **الرابط:** $link\n";
                if ($total_cost > 0) {
                    $msg .= "💵 **التكلفة:** $" . number_format($total_cost, 2) . "\n";
                }
                $msg .= "\n👇 **اضغط تأكيد لإتمام الطلب وخصم الرصيد:**";
                
                $keyboard = ['inline_keyboard' => [
                    [
                        ['text' => '✅ تأكيد الطلب', 'callback_data' => 'confirm_order_final'],
                        ['text' => '❌ إلغاء', 'callback_data' => 'cancel_order']
                    ]
                ]];
                
                setUserState($pdo, $chat_id, 'WAITING_FINAL_CONFIRMATION', $data);
                sendMessage($token, $chat_id, $msg, $keyboard);
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
                    $msg .= "💰 السعر: $" . ($s['cost'] ?? 0) . " / 1k\n";
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
            // تخطي القائمة واختيار أول خدمة تلقائياً
            $service = $services[0];
            setUserState($pdo, $chat_id, 'WAITING_QTY', ['platform' => $platform, 'type' => $type, 'type_label' => $service['name'], 'service_id' => $service['id']]);
            $msg = "🔢 **الكمية المطلوبة ({$service['name']}):**\n\nيرجى كتابة العدد الذي تريده (أرقام فقط).";
            sendMessage($token, $chat_id, $msg);
            return;
        }
        // ----------------------------------------------------------------

        $typeLabels = [
            'followers' => 'متابعين', 'likes' => 'لايكات', 
            'views' => 'مشاهدات', 'comments' => 'تعليقات'
            ];
        $typeLabel = $typeLabels[$type] ?? $type;
        
        // --- التحقق من الرصيد قبل البدء (للطلب العام) ---
        $stmtUser = $pdo->prepare("SELECT balance FROM bot_users WHERE chat_id = ?");
        $stmtUser->execute([$chat_id]);
        $current_balance = $stmtUser->fetchColumn();
        
        if ($current_balance <= 0) {
            $msg = "🚫 **عذراً، رصيدك صفر!**\n\nلا يمكنك طلب خدمات حتى تقوم بشحن رصيدك.";
            
            $keyboard = ['inline_keyboard' => [[['text' => '⭐️ شحن الرصيد (نجوم)', 'callback_data' => 'recharge_stars_menu']]]];
            sendMessage($token, $chat_id, $msg, $keyboard);
            return;
        }
        
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
            // --- التحقق من الرصيد قبل البدء (للخدمات المحددة) ---
            $stmtUser = $pdo->prepare("SELECT balance FROM bot_users WHERE chat_id = ?");
            $stmtUser->execute([$chat_id]);
            $current_balance = $stmtUser->fetchColumn();
            
            if ($current_balance <= 0) {
                $msg = "🚫 **عذراً، رصيدك صفر!**\n\nلا يمكنك طلب خدمات حتى تقوم بشحن رصيدك.";
                
                $keyboard = ['inline_keyboard' => [[['text' => '⭐️ شحن الرصيد (نجوم)', 'callback_data' => 'recharge_stars_menu']]]];
                sendMessage($token, $chat_id, $msg, $keyboard);
                return;
            }

            // حفظ الحالة مع service_id ليتم خصم الرصيد لاحقاً
            setUserState($pdo, $chat_id, 'WAITING_QTY', ['platform' => $service['category'], 'type_label' => $service['name'], 'service_id' => $service['id']]);
            $min_qty = $service['min_qty'] ?? 500;
            $msg = "🔢 **الكمية المطلوبة ({$service['name']}):**\n\nأقل كمية: $min_qty\nيرجى كتابة العدد الذي تريده (أرقام فقط).";
            sendMessage($token, $chat_id, $msg);
        }
    }
    
    if ($data === 'my_account') {
        $stmtUser = $pdo->prepare("SELECT balance, username FROM bot_users WHERE chat_id = ?");
        $stmtUser->execute([$chat_id]);
        $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
        $balance = $user['balance'] ?? 0.00;
        $username = $user['username'] ?? 'مستخدم';

        $msg = "👤 **ملف المستخدم**\n\n";
        $msg .= "📛 **الاسم:** " . htmlspecialchars($username) . "\n";
        $msg .= "🆔 **ID:** `$chat_id`\n";
        $msg .= "💰 **الرصيد:** $" . number_format($balance, 2) . "\n";
        
        $keyboard = ['inline_keyboard' => []];
        $keyboard['inline_keyboard'][] = [['text' => '⭐️ شحن الرصيد (نجوم)', 'callback_data' => 'recharge_stars_menu']];
        $keyboard['inline_keyboard'][] = [['text' => '🔙 رجوع', 'callback_data' => 'back_to_main']];

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
                    ['text' => '⭐️ شحن الرصيد', 'callback_data' => 'recharge_stars_menu'],
                    ['text' => '📜 سجل طلباتي', 'callback_data' => 'my_orders']
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
                ],
                [
                    ['text' => '👤 حسابي', 'callback_data' => 'my_account']
                ]
            ]
        ];
        sendMessage($token, $chat_id, $msg, $keyboard);
    }

    // --- قائمة شحن النجوم ---
    if ($data === 'recharge_stars_menu') {
        $msg = "✨ **شحن الرصيد عبر نجوم تيليجرام** ✨\n\n";
        $msg .= "اختر الباقة المناسبة لشحن رصيدك فوراً:\n(سعر النجمة التقريبي: 0.02$)";
        
        $keyboard = [
            'inline_keyboard' => [
                [['text' => '⭐️ 50 نجمة ($1.00)', 'callback_data' => 'buy_stars_50']],
                [['text' => '⭐️ 100 نجمة ($2.00)', 'callback_data' => 'buy_stars_100']],
                [['text' => '⭐️ 250 نجمة ($5.00)', 'callback_data' => 'buy_stars_250']],
                [['text' => '⭐️ 500 نجمة ($10.00)', 'callback_data' => 'buy_stars_500']],
                [['text' => '🔙 رجوع', 'callback_data' => 'back_to_main']]
            ]
        ];
        sendMessage($token, $chat_id, $msg, $keyboard);
    }

    // --- إنشاء فاتورة النجوم ---
    if (strpos($data, 'buy_stars_') === 0) {
        $stars = intval(str_replace('buy_stars_', '', $data));
        $amount_usd = $stars * 0.02; // سعر افتراضي: 1 نجمة = 0.02 دولار
        
        $title = "شحن رصيد $$amount_usd";
        $description = "شحن رصيد في البوت بقيمة $$amount_usd مقابل $stars نجمة.";
        $payload = "topup_{$stars}_{$amount_usd}";
        $currency = "XTR"; // عملة النجوم
        $prices = [['label' => "$stars Stars", 'amount' => $stars]]; // المبلغ لـ XTR هو عدد النجوم
        
        sendInvoice($token, $chat_id, $title, $description, $payload, $currency, $prices);
    }

    // --- معالجة تأكيد الطلب النهائي ---
    if ($data === 'confirm_order_final') {
        // حذف رسالة التأكيد (التي تحتوي على الأزرار) فوراً لمنع التكرار أو الإلغاء
        $confirmMsgId = $update['callback_query']['message']['message_id'] ?? null;
        if ($confirmMsgId) {
            deleteMessage($token, $chat_id, $confirmMsgId);
        }

        $stateData = getUserState($pdo, $chat_id);
        if ($stateData && $stateData['state'] === 'WAITING_FINAL_CONFIRMATION') {
            
            // إرسال رسالة "جاري المعالجة"
            $processingMsg = sendMessage($token, $chat_id, "⏳ **جاري معالجة طلبك...**");
            $procMsgId = null;
            if ($processingMsg) $procMsgId = json_decode($processingMsg, true)['result']['message_id'] ?? null;

            // --- الحل الجذري للتكرار (Atomic Lock) ---
            // نحاول حذف الحالة أولاً. إذا نجح الحذف (عدد الصفوف 1)، فهذا يعني أننا أول من يعالج الطلب.
            // إذا فشل (عدد الصفوف 0)، فهذا يعني أن الطلب قيد المعالجة أو تم تنفيذه بالفعل.
            $stmtDel = $pdo->prepare("DELETE FROM bot_users_state WHERE chat_id = ? AND state = 'WAITING_FINAL_CONFIRMATION'");
            $stmtDel->execute([$chat_id]);
            
            if ($stmtDel->rowCount() > 0) {
                try {
                    // نحن في العملية الأولى والوحيدة -> ننفذ الطلب
                    $data = $stateData['data'];
                    $total_cost = $data['total_cost'] ?? 0;
                    
                    // خصم الرصيد
                    $stmtUser = $pdo->prepare("SELECT balance, username FROM bot_users WHERE chat_id = ?");
                    $stmtUser->execute([$chat_id]);
                    $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
                    $current_balance = $userRow['balance'] ?? 0;
                    $username = $userRow['username'] ?? 'Unknown';
                    
                    if ($total_cost > 0 && $current_balance < $total_cost) {
                        if ($procMsgId) deleteMessage($token, $chat_id, $procMsgId); // حذف رسالة المعالجة
                        sendMessage($token, $chat_id, "🚫 رصيدك غير كافٍ لإتمام العملية.");
                        return;
                    }
                    
                    $new_balance = $current_balance - $total_cost;
                    $pdo->prepare("UPDATE bot_users SET balance = ? WHERE chat_id = ?")->execute([$new_balance, $chat_id]);
                    
                    // --- إرسال الطلب إلى SMM API ---
                    $external_id = null;
                    $api_response_json = null;
                    $api_service_id = null;
                    
                    // جلب رقم الخدمة في الموقع
                    $service_id_local = $data['service_id'] ?? 0;
                    $stmtSrv = $pdo->prepare("SELECT api_service_id FROM bot_services WHERE id = ?");
                    $stmtSrv->execute([$service_id_local]);
                    $srv = $stmtSrv->fetch(PDO::FETCH_ASSOC);
                    $api_service_id = $srv['api_service_id'] ?? null;
                    
                    if ($api_service_id) {
                        $smm_url = $settings['smm_api_url'] ?? 'https://smmcost.com/api/v2';
                        $smm_key = $settings['smm_api_key'] ?? '';
                        
                        if ($smm_key) {
                            $res = placeOrderSMM($smm_url, $smm_key, $api_service_id, $data['link'], $data['qty']);
                            $api_response_json = json_encode($res);
                        if ($api_response_json === false) $api_response_json = '{}'; // تجنب الخطأ في حال فشل التحويل
                            if (isset($res['order'])) $external_id = $res['order'];
                        }
                    }
                    // --------------------------------

                    // تسجيل العملية في السجل المالي (بالسالب لأنها خصم)
                    $serviceName = $data['type_label'] ?? 'خدمة';
                    $pdo->prepare("INSERT INTO bot_transactions (chat_id, username, amount, stars, created_at) VALUES (?, ?, ?, 0, ?)")
                        ->execute([$chat_id, $username, -$total_cost, time()]);
                    
                    // تسجيل الطلب في سجل الطلبات (للمستخدم)
                    $pdo->prepare("INSERT INTO bot_orders (chat_id, service_name, qty, link, cost, status, created_at, external_id, api_response) VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, ?)")
                        ->execute([$chat_id, $serviceName, $data['qty'], $data['link'], $total_cost, time(), $external_id, $api_response_json]);
                    
                    // إشعار الإدارة بطلب جديد
                    $admin_chat_id = $settings['chat_id'] ?? '';
                    if ($admin_chat_id) {
                        $adminMsg = "🔔 **طلب جديد!**\n\n👤 المستخدم: " . htmlspecialchars($username) . " (`$chat_id`)\n🔧 الخدمة: $serviceName\n🔢 العدد: {$data['qty']}\n🔗 الرابط: {$data['link']}\n💰 التكلفة: $" . number_format($total_cost, 2);
                        if ($external_id) $adminMsg .= "\n✅ **تم الإرسال للموقع برقم:** `$external_id`";
                        else if ($api_service_id) $adminMsg .= "\n⚠️ **فشل الإرسال للموقع!** (راجع السجل)";
                        sendMessage($token, $admin_chat_id, $adminMsg);
                    }
                
                    // إرسال رسالة التأكيد النهائية
                    $msg = "✅ **تم تأكيد طلبك بنجاح!** 🚀\n\n";
                    $msg .= "طلبك الآن **قيد التنفيذ**.\n\n";
                    $msg .= "🔧 **الخدمة:** " . ($data['type_label'] ?? '') . "\n";
                    $msg .= "🔢 **العدد:** " . ($data['qty'] ?? 0) . "\n";
                    $msg .= "🔗 **الرابط:** " . ($data['link'] ?? '') . "\n";
                    if ($total_cost > 0) $msg .= "💰 **الرصيد المتبقي:** $" . number_format($new_balance, 2) . "\n";
                    
                    if ($procMsgId) deleteMessage($token, $chat_id, $procMsgId); // حذف رسالة المعالجة
                    sendMessage($token, $chat_id, $msg);

                } catch (Exception $e) {
                    // في حال حدوث خطأ، نبلغ المستخدم ونحذف رسالة المعالجة
                    if ($procMsgId) deleteMessage($token, $chat_id, $procMsgId);
                    sendMessage($token, $chat_id, "⚠️ حدث خطأ فني: " . $e->getMessage());
                }
            } else {
                // الطلب مكرر وتمت معالجته بالفعل -> لا نفعل شيئاً
                if ($procMsgId) deleteMessage($token, $chat_id, $procMsgId);
            }
        } else {
            sendMessage($token, $chat_id, "⚠️ انتهت صلاحية الجلسة، يرجى البدء من جديد.");
        }
    }

    // --- معالجة إلغاء الطلب ---
    if ($data === 'cancel_order') {
        // حذف رسالة التأكيد عند الإلغاء لتنظيف المحادثة
        $confirmMsgId = $update['callback_query']['message']['message_id'] ?? null;
        if ($confirmMsgId) {
            deleteMessage($token, $chat_id, $confirmMsgId);
        }

        clearUserState($pdo, $chat_id);
        $msg = "❌ **تم إلغاء الطلب.**\nيمكنك البدء من جديد باختيار خدمة من القائمة.";
        $keyboard = ['inline_keyboard' => [[['text' => '🔙 القائمة الرئيسية', 'callback_data' => 'back_to_main']]]];
        sendMessage($token, $chat_id, $msg, $keyboard);
    }

    // --- عرض سجل الطلبات ---
    if ($data === 'my_orders') {
        $stmt = $pdo->prepare("SELECT * FROM bot_orders WHERE chat_id = ? ORDER BY id DESC LIMIT 10");
        $stmt->execute([$chat_id]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($orders)) {
            $msg = "📭 **لا توجد طلبات سابقة.**";
        } else {
            $msg = "📜 **سجل آخر 10 طلبات:**\n\n";
            foreach ($orders as $order) {
                $statusMap = [
                    'pending' => 'قيد الانتظار ⏳',
                    'in_progress' => 'جاري التنفيذ 🚀',
                    'processing' => 'قيد المعالجة ⚙️',
                    'completed' => 'مكتمل ✅',
                    'partial' => 'مكتمل جزئياً ⚠️',
                    'canceled' => 'ملغي ❌',
                    'cancelled' => 'ملغي ❌'
                ];
                $status = $statusMap[$order['status']] ?? $order['status'];
                $date = date('Y-m-d', $order['created_at']);
                
                $msg .= "🔹 **{$order['service_name']}**\n";
                $msg .= "🔢 العدد: {$order['qty']} | 💰 {$order['cost']}$\n";
                $msg .= "📅 $date | الحالة: $status\n";
                $msg .= "🔗 " . substr($order['link'], 0, 25) . "...\n";
                $msg .= "------------------\n";
            }
        }
        $keyboard = ['inline_keyboard' => [[['text' => '🔙 رجوع', 'callback_data' => 'back_to_main']]]];
        sendMessage($token, $chat_id, $msg, $keyboard);
    }
}

// 3. معالجة طلبات الدفع المسبق (Pre-Checkout) - ضروري لقبول الدفع
if (isset($update['pre_checkout_query'])) {
    $pre_checkout_id = $update['pre_checkout_query']['id'];
    answerPreCheckoutQuery($token, $pre_checkout_id, true);
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
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
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

function sendInvoice($token, $chat_id, $title, $description, $payload, $currency, $prices) {
    $url = "https://api.telegram.org/bot$token/sendInvoice";
    $data = [
        'chat_id' => $chat_id,
        'title' => $title,
        'description' => $description,
        'payload' => $payload,
        'currency' => $currency,
        'prices' => json_encode($prices),
        'provider_token' => '' // فارغ لمدفوعات النجوم
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch);
    curl_close($ch);
}

function answerPreCheckoutQuery($token, $pre_checkout_query_id, $ok, $error_message = "") {
    $url = "https://api.telegram.org/bot$token/answerPreCheckoutQuery";
    $data = ['pre_checkout_query_id' => $pre_checkout_query_id, 'ok' => $ok];
    if (!$ok) $data['error_message'] = $error_message;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch);
    curl_close($ch);
}

function deleteMessage($token, $chat_id, $message_id) {
    $url = "https://api.telegram.org/bot$token/deleteMessage";
    $data = ['chat_id' => $chat_id, 'message_id' => $message_id];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch);
    curl_close($ch);
}

function placeOrderSMM($url, $key, $service, $link, $quantity) {
    $post = [
        'key' => $key,
        'action' => 'add',
        'service' => $service,
        'link' => $link,
        'quantity' => $quantity
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
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

// إنهاء الطلب بـ 200 OK لإيقاف محاولات تيليجرام
http_response_code(200);
?>