const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');

(async () => {
    const url = process.argv[2];
    const mode = process.argv[3] || 'full'; // 'full' (default) or 'events_only'
    if (!url) {
        console.error('No URL provided');
        process.exit(1);
    }

    try {
        const browser = await puppeteer.launch({
            headless: "new", // تشغيل في الخلفية
            args: [
                '--no-sandbox', 
                '--disable-setuid-sandbox', 
                '--disable-dev-shm-usage', 
                '--window-size=1920,1080',
                '--disable-blink-features=AutomationControlled', // إخفاء خاصية الأتمتة لتجنب الكشف
                '--disable-infobars'
            ],
            ignoreDefaultArgs: ['--enable-automation'] // إخفاء شريط التحكم الآلي
        });
        const page = await browser.newPage();
        
        // تسريع التحميل: منع تحميل الصور والخطوط والوسائط لأننا نحتاج النصوص فقط
        await page.setRequestInterception(true);
        page.on('request', (req) => {
            if (['image', 'font', 'media'].includes(req.resourceType())) {
                // تم إزالة 'stylesheet' من الحظر لأن YallaKora يحتاج CSS لعمل زر التشكيلة بشكل صحيح
                // هذا يضمن ظهور العناصر وقابليتها للنقر
                req.abort();
            } else {
                req.continue();
            }
        });

        // تعيين User-Agent ليبدو كمتصفح حقيقي
        await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36');
        await page.setViewport({ width: 1920, height: 1080 });

        // إضافة ترويسات HTTP إضافية لتبدو كمتصفح حقيقي
        await page.setExtraHTTPHeaders({
            'Accept-Language': 'ar,en-US;q=0.9,en;q=0.8',
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'Sec-Ch-Ua': '"Google Chrome";v="123", "Not:A-Brand";v="8", "Chromium";v="123"',
            'Sec-Ch-Ua-Mobile': '?0',
            'Sec-Ch-Ua-Platform': '"Windows"'
        });

        // إخفاء هوية الأتمتة (Stealth) لتجاوز الحماية
        await page.evaluateOnNewDocument(() => {
            Object.defineProperty(navigator, 'webdriver', { get: () => false });
            // محاكاة المتصفح الحقيقي
            if (!window.chrome) window.chrome = { runtime: {} };
            Object.defineProperty(navigator, 'plugins', { get: () => [1, 2, 3, 4, 5] });
            Object.defineProperty(navigator, 'languages', { get: () => ['ar', 'en-US', 'en'] });
        });

        // الذهاب للصفحة وانتظار تحميلها
        // استخدام domcontentloaded أسرع بكثير من networkidle2 (لا ينتظر الإعلانات)
        await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 45000 });
        
        let events = [];

        // انتظار إضافي للسماح بتنفيذ JavaScript، خاصة لمواقع البث التي تحمل المحتوى بشكل متأخر
        // هذا الانتظار مهم للمواقع العامة التي ليس لها منطق مخصص أدناه
        await new Promise(r => setTimeout(r, 4000)); // زيادة الانتظار إلى 4 ثواني

        // ================= YallaKora Logic =================
        if (url.includes('yallakora.com')) {
            // سحب التشكيلة والإحصائيات فقط إذا لم يكن الوضع "أحداث فقط"
            if (mode !== 'events_only') {
                try {
                    await page.waitForSelector('#squadButton', { timeout: 8000 });
                    await page.evaluate(() => {
                        const btn = document.querySelector('#squadButton');
                        if (btn) btn.scrollIntoView({behavior: 'smooth', block: 'center'});
                    });
                    await new Promise(r => setTimeout(r, 200));
                    await page.click('#squadButton');
                    await new Promise(r => setTimeout(r, 500));
                } catch (e) {
                    // في حال عدم وجود زر التشكيلة، نقوم بالتمرير لأسفل لضمان تحميل الإحصائيات (Lazy Loading)
                    await page.evaluate(() => {
                        window.scrollBy(0, 600);
                    });
                }

                // انتظار ظهور اللاعبين
                try {
                    await page.waitForSelector('#squad .player, .formation .player', { timeout: 4000 });
                } catch (e) {}

                // انتظار ظهور الإحصائيات (إن وجدت)
                try {
                    await page.waitForSelector('.statsDiv', { timeout: 3000 });
                } catch (e) {}
            }
            
            // تمرير بسيط لضمان تحميل الأحداث (Lazy Loading)
            await page.evaluate(() => {
                window.scrollBy(0, 500);
            });

            // استخراج أحداث المباراة (أهداف، بطاقات، تبديلات)
            try {
                // ننتظر قليلاً لضمان تحميل الأحداث
                await page.waitForSelector('.eventsTtl', { timeout: 2000 });
                
                const eventItems = await page.$$('.eventsTtl + ul li');
                for (const item of eventItems) {
                    const className = await page.evaluate(el => el.className, item);
                    if (className.includes('referee')) continue; // تخطي صافرة الحكم

                    const min = await page.evaluate(el => el.querySelector('.min')?.innerText.trim() || '', item);
                    let text = '';
                    
                    if (className.includes('goal')) {
                        text = '⚽ ' + await page.evaluate(el => el.querySelector('.description')?.innerText.replace(/[\n\r]+/g, ' ').trim(), item);
                    } else if (className.includes('yellowCard')) {
                        text = '🟨 ' + await page.evaluate(el => el.querySelector('.description')?.innerText.trim(), item);
                    } else if (className.includes('redCard')) {
                        text = '🟥 ' + await page.evaluate(el => el.querySelector('.description')?.innerText.trim(), item);
                    } else if (className.includes('sub')) {
                        const subIn = await page.evaluate(el => el.querySelector('.subIn')?.innerText.trim(), item);
                        const subOut = await page.evaluate(el => el.querySelector('.subOut')?.innerText.trim(), item);
                        text = `🔄 دخول: ${subIn} | خروج: ${subOut}`;
                    } else if (className.includes('penOut')) {
                        text = '❌ ركلة جزاء ضائعة: ' + await page.evaluate(el => el.querySelector('.description')?.innerText.trim(), item);
                    }

                    if (text) {
                        // تحديد الفريق (يمين = مستضيف عادة، يسار = ضيف)
                        const side = className.includes('left') ? '(ضيف)' : '(مستضيف)';
                        events.push(`${min}' ${text} ${side}`);
                    }
                }
            } catch (e) { /* تجاهل الأخطاء إذا لم توجد أحداث */ }
            
            // إضافة الأحداث إلى الناتج (سنقوم بطباعتها كجزء من JSON في النهاية)
            // بما أننا نطبع HTML حالياً، سنقوم بتعديل طريقة الإرجاع في الخطوة التالية
        }
        
        // ================= Google Search Logic =================
        if (url.includes('google.com')) {
            try {
                // انتظار تحميل حاوية الرياضة
                await page.waitForSelector('div[data-attrid="sport_event"]', { timeout: 8000 });
                
                // البحث عن تبويب "التشكيلة" أو "Lineups" والنقر عليه
                const tabs = await page.$x("//div[@role='tab'][contains(., 'Lineups') or contains(., 'التشكيلة')]");
                if (tabs.length > 0) {
                    await tabs[0].click();
                    await new Promise(r => setTimeout(r, 2000)); // انتظار تحميل التشكيلة
                }
            } catch (e) {}
            // ننتظر قليلاً في كل الأحوال
        }

        // ================= Kooora Logic =================
        if (url.includes('kooora.com')) {
            try {
                // انتظار أولي
                try {
                    await page.waitForSelector('body', { timeout: 10000 });
                } catch(e) {}

                // محاولة البحث عن تبويب "التشكيلة" والضغط عليه بجميع الطرق الممكنة
                try {
                    // البحث عن العناصر التي تحتوي على النص
                    const tabs = await page.$x("//*[contains(text(), 'التشكيلة') or contains(text(), 'Lineup')]");
                    
                    for (const tab of tabs) {
                        try {
                            // النقر عبر JavaScript (أكثر موثوقية من النقر العادي)
                            await page.evaluate(el => el.click(), tab);
                            await new Promise(r => setTimeout(r, 500)); // انتظار بسيط بين النقرات
                        } catch (e) {}
                    }
                    
                    // انتظار تحميل البيانات بعد النقر
                    if (tabs.length > 0) {
                        await new Promise(r => setTimeout(r, 4000));
                    }
                } catch (e) {}

                // التمرير لأسفل لضمان تحميل العناصر (Lazy Loading)
                await page.evaluate(async () => {
                    window.scrollBy(0, 500);
                });
                await new Promise(r => setTimeout(r, 1000));

            } catch (e) {}
        }

        // ================= Koora4Live Logic =================
        if (url.includes('koora4live')) {
            try {
                // الانتظار تحديداً لظهور الإطار داخل حاوية البث المعروفة
                await page.waitForSelector('#iframe-placeholder iframe', { visible: true, timeout: 15000 });
            } catch (e) {
                // تجاهل الخطأ والاستمرار، ربما الهيكلية مختلفة
            }
        }

        // طباعة كود HTML الناتج
        // سنقوم بطباعة JSON خاص يحتوي على HTML والأحداث المستخرجة
        const result = {
            html: await page.content(),
            extracted_events: events
        };
        console.log(JSON.stringify(result));

        await browser.close();
    } catch (error) {
        const logPath = path.join(__dirname, 'puppeteer_errors.log');
        const timestamp = new Date().toISOString();
        const logMessage = `[${timestamp}] Error processing URL: ${url}\nMessage: ${error.message}\nStack: ${error.stack}\n--------------------------------------------------\n`;
        fs.appendFileSync(logPath, logMessage);
        console.error(error);

        // التحقق من تكرار الأخطاء وإرسال تنبيه
        try {
            const logContent = fs.readFileSync(logPath, 'utf8');
            const lines = logContent.split('\n').filter(l => l.trim());
            const now = new Date();
            const oneHourAgo = new Date(now - 60 * 60 * 1000);
            
            let recentErrors = 0;
            // عد الأخطاء في آخر ساعة (نقرأ من النهاية للأداء)
            for (let i = lines.length - 1; i >= 0; i--) {
                const match = lines[i].match(/^\[([^\]]+)\]/);
                if (match) {
                    const errTime = new Date(match[1]);
                    if (errTime > oneHourAgo) recentErrors++;
                    else break;
                }
            }

            // إذا زادت الأخطاء عن 10 في الساعة
            if (recentErrors >= 10) {
                const alertFile = path.join(__dirname, 'alert_cooldown.txt');
                let lastAlert = 0;
                if (fs.existsSync(alertFile)) lastAlert = parseInt(fs.readFileSync(alertFile, 'utf8'));
                
                if (now - lastAlert > 60 * 60 * 1000) { // تنبيه واحد كل ساعة كحد أقصى لتجنب الإزعاج
                    await sendTelegramAlert(recentErrors, error.message);
                    fs.writeFileSync(alertFile, now.getTime().toString());
                }
            }
        } catch (alertErr) { console.error('Alert check failed:', alertErr.message); }

        process.exit(1);
    }
})();

async function sendTelegramAlert(count, lastError) {
    // إعدادات تيليجرام
    // ضع التوكن والـ ID الخاص بك هنا
    const botToken = '8042622774:AAHsri8itQqddhC_NeuP7EKBSoMcZYzIi64'; 
    const chatId = '1783801547';
    
    if (botToken === 'YOUR_TELEGRAM_BOT_TOKEN' || chatId === 'YOUR_TELEGRAM_CHAT_ID') {
        console.error('⚠ لم يتم إرسال التنبيه: يرجى إعداد Bot Token و Chat ID في ملف scraper_lineup.js');
        return;
    }

    const https = require('https');
    const message = `🚨 *تنبيه من البوت*\nعدد الأخطاء في آخر ساعة: ${count}\n\n*آخر خطأ:*\n\`${lastError}\``;
    
    const data = JSON.stringify({
        chat_id: chatId,
        text: message,
        parse_mode: 'Markdown'
    });

    const options = {
        hostname: 'api.telegram.org',
        path: `/bot${botToken}/sendMessage`,
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Content-Length': data.length
        }
    };

    return new Promise((resolve) => {
        const req = https.request(options, (res) => {
            if (res.statusCode >= 200 && res.statusCode < 300) {
                console.error('📧 Telegram alert sent.');
            } else {
                console.error(`⚠ Failed to send Telegram alert. Status: ${res.statusCode}`);
            }
            resolve();
        });
        
        req.on('error', (e) => {
            console.error(`⚠ Telegram request error: ${e.message}`);
            resolve();
        });
        
        req.write(data);
        req.end();
    });
}