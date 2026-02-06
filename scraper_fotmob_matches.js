const puppeteer = require('puppeteer');

(async () => {
    const apiUrl = process.argv[2];
    if (!apiUrl) {
        console.error('No URL provided');
        process.exit(1);
    }

    try {
        const browser = await puppeteer.launch({
            headless: "new",
            args: [
                '--no-sandbox', 
                '--disable-setuid-sandbox', 
                '--disable-dev-shm-usage',
                '--disable-blink-features=AutomationControlled' // إخفاء إضافي للبوت
            ]
        });
        const page = await browser.newPage();
        
        await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36');

        // إخفاء هوية الأتمتة (Stealth)
        await page.evaluateOnNewDocument(() => {
            Object.defineProperty(navigator, 'webdriver', { get: () => false });
        });

        // الاستراتيجية: الذهاب للصفحة الرئيسية أولاً لتعيين الكوكيز والجلسة
        await page.goto('https://www.fotmob.com/', { waitUntil: 'domcontentloaded', timeout: 60000 });

        // طلب البيانات عبر fetch من داخل سياق الصفحة (يبدو كطلب طبيعي من الموقع)
        const content = await page.evaluate(async (url) => {
            try {
                const response = await fetch(url, {
                    headers: { 'Accept': 'application/json, text/plain, */*' }
                });
                if (!response.ok) return null;
                return await response.text();
            } catch (e) {
                return null;
            }
        }, apiUrl);

        if (content) {
            console.log(content);
        } else {
            // محاولة احتياطية: الذهاب المباشر وقراءة المحتوى
            const response = await page.goto(apiUrl, { waitUntil: 'networkidle2' });
            // التحقق مما إذا كانت الصفحة تحتوي على JSON
            const text = await page.evaluate(() => document.body.innerText);
            // إذا كان النص يبدأ بـ { أو [ فهو غالباً JSON
            if (text.trim().startsWith('{') || text.trim().startsWith('[')) {
                console.log(text);
            } else {
                // إذا لم يكن JSON، نطبع رسالة خطأ أو المحتوى كما هو للتشخيص
                console.log(text); 
            }
        }

        await browser.close();
    } catch (error) {
        console.error(error);
        process.exit(1);
    }
})();