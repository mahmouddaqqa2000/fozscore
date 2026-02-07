<?php
// header.php - شريط تنقل مشترك
if (!isset($settings) && isset($pdo) && function_exists('get_site_settings')) {
    $settings = get_site_settings($pdo);
}

$header_site_name = $settings['site_name'] ?? 'FozScore';
$primary_color = $settings['primary_color'] ?? '#1e293b';
?>
<style>
    :root {
        --primary: <?php echo htmlspecialchars($primary_color); ?> !important;
    }
    .navbar {
        background-color: #ffffff;
        color: #000;
        padding: 0.8rem 1.2rem; /* تقليل الحشوة الأفقية */
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        display: flex;
        justify-content: center; /* وضع الشعار والروابط في منتصف الشريط */
        align-items: center;
        gap: 0.8rem; /* مسافة صغيرة بين العناصر */
        position: relative;
    }
    .navbar .brand {
        font-size: 1.5rem;
        font-weight: 700;
        color: #000;
        text-decoration: none;
        /* no extra margin: centered layout */
    }
    .navbar a {
        color: rgba(0,0,0,0.85);
        text-decoration: none;
        font-size: 0.9rem;
        padding: 8px 12px;
        border-radius: 5px;
        transition: background-color 0.2s, color 0.2s;
    }
    .navbar a:hover { background-color: rgba(0,0,0,0.05); color: #000; }
    .nav-links {
        display: flex;
        gap: 0.5rem;
    }
    .nav-links a {
        font-size: 1.05rem;
        padding: 10px 16px;
        font-weight: 700;
        color: inherit;
    }

    /* menu toggle button (hidden on desktop) */
    .menu-toggle {
        display: none;
        background: none;
        border: none;
        font-size: 1.6rem;
        cursor: pointer;
        color: inherit;
    }

    /* Responsive styles for mobile */
    @media (max-width: 820px) {
        .navbar {
            flex-direction: column;
            gap: 0.6rem;
            padding: 0.8rem 1rem;
        }
        /* off-canvas panel from the right */
        .nav-links {
            position: fixed;
            top: 0;
            right: 0;
            height: 100%;
            width: 220px; /* smaller width for a compact sidebar */
            background: #ffffff;
            box-shadow: -6px 0 18px rgba(0,0,0,0.12);
            transform: translateX(110%);
            transition: transform 260ms cubic-bezier(.2,.8,.2,1);
            display: flex;
            flex-direction: column;
            padding-top: 64px; /* space for navbar */
            gap: 8px;
            z-index: 1100;
            align-items: stretch;
            padding-inline: 10px;
            border-top-left-radius: 12px;
            border-bottom-left-radius: 12px;
            overflow-y: auto;
        }
        .nav-links.open {
            transform: translateX(0);
        }
        /* overlay behind panel */
        .nav-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.35);
            opacity: 0;
            visibility: hidden;
            transition: opacity 180ms ease, visibility 180ms ease;
            z-index: 1000;
        }
        .nav-overlay.visible { opacity: 1; visibility: visible; }

        /* link styling inside panel */
        .nav-links a {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            border-radius: 8px;
            font-size: 0.98rem;
            color: #101820;
            background: transparent;
            border-bottom: 1px solid #f3f5f7;
        }
        .nav-links a:hover {
            background: #f7f9fb;
        }
        /* make the last link not show unnecessary border */
        .nav-links a:last-child { border-bottom: none; }
        /* small scrollbar for panel */
        .nav-links::-webkit-scrollbar { width: 6px; }
        .nav-links::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.08); border-radius: 6px; }

        /* show toggle */
        .menu-toggle {
            display: block !important;
            position: absolute;
            right: 12px; /* show near right edge for opening right panel */
            left: auto;
            top: 50%;
            transform: translateY(-50%);
            z-index: 1300 !important; /* ensure above overlay and panel */
            background: transparent;
            border: none;
            font-size: 1.6rem;
            color: #101820; /* dark icon color */
            padding: 6px;
            line-height: 1;
        }

        /* Dark Mode Mobile Menu */
        body.dark-mode .nav-links { background: #1e293b; }
        body.dark-mode .nav-links a { color: #f1f5f9; border-bottom-color: #334155; }
        body.dark-mode .nav-links a:hover { background: #2d3748; }
    }

    /* Search Icon Style */
    .search-trigger {
        background: none;
        border: none;
        cursor: pointer;
        color: inherit;
        padding: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: background-color 0.2s;
    }
    .search-trigger:hover { background-color: rgba(0,0,0,0.05); }
    .search-trigger svg { width: 22px; height: 22px; fill: currentColor; }
    
    @media (max-width: 820px) {
        .search-trigger { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); }
    }

    /* Telegram Banner */
    .telegram-banner {
        background: linear-gradient(90deg, #24A1DE 0%, #1b8bbf 100%);
        color: #fff;
        position: relative;
        z-index: 1001;
        display: flex;
        align-items: stretch;
        justify-content: space-between;
        min-height: 46px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .telegram-link {
        text-decoration: none;
        color: #fff;
        padding: 8px 15px;
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 10px;
        font-weight: 700;
        font-size: 0.95rem;
        flex: 1;
        transition: background 0.2s;
    }
    .telegram-link:hover { background: rgba(255,255,255,0.1); }
    .telegram-link svg { 
        width: 22px; height: 22px; fill: currentColor; flex-shrink: 0;
        animation: telegram-shake 3s infinite;
    }
    @keyframes telegram-shake {
        0%, 100% { transform: rotate(0deg); }
        10%, 30% { transform: rotate(15deg); }
        20%, 40% { transform: rotate(-15deg); }
        50% { transform: rotate(0deg); }
    }
    
    .telegram-close {
        background: rgba(0,0,0,0.05);
        border: none;
        border-right: 1px solid rgba(255,255,255,0.1);
        color: rgba(255,255,255,0.9);
        width: 46px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        transition: all 0.2s;
        flex-shrink: 0;
    }
    .telegram-close:hover { background: rgba(0,0,0,0.2); color: #fff; }

    @media (max-width: 600px) {
        .telegram-link {
            font-size: 0.85rem;
            padding: 8px 10px;
            justify-content: center;
        }
        .telegram-link span {
            white-space: normal;
            text-align: center;
        }
    }
</style>

<div id="telegram-banner" class="telegram-banner" style="display:none;">
    <a href="https://t.me/kora4tv" target="_blank" class="telegram-link">
        <svg viewBox="0 0 24 24"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 11.944 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.463 4.049-1.72 4.471-1.72z"/></svg>
        <span>انضم لقناتنا على تيليجرام لمتابعة المباريات! 📲</span>
    </a>
    <button class="telegram-close" onclick="closeTelegramBanner()" aria-label="إغلاق">✕</button>
</div>

<script>
    const tgBanner = document.getElementById('telegram-banner');
    const tgHideKey = 'hide_telegram_banner_ts';
    const tgStored = localStorage.getItem(tgHideKey);
    
    // التحقق مما إذا كان الوقت المخزن غير موجود أو مر عليه أكثر من 24 ساعة (24 * 60 * 60 * 1000 ميلي ثانية)
    if (!tgStored || (new Date().getTime() - parseInt(tgStored) > 24 * 60 * 60 * 1000)) {
        if(tgBanner) tgBanner.style.display = 'flex';
    }
    function closeTelegramBanner() {
        if(tgBanner) tgBanner.style.display = 'none';
        localStorage.setItem(tgHideKey, new Date().getTime().toString());
    }
</script>

<div class="navbar">
    <a class="brand" href="./">
        <?php if (!empty($settings['favicon'])): ?>
            <img src="<?php echo htmlspecialchars($settings['favicon']); ?>" alt="Logo" style="height: 45px; width: auto; vertical-align: middle; margin-left: 8px;">
        <?php endif; ?>
        <?php echo htmlspecialchars($header_site_name); ?>
    </a>
    <button class="menu-toggle" aria-label="قائمة" aria-expanded="false">☰</button>
    <nav class="nav-links" role="navigation">
        <a href="./">مباريات اليوم</a>
        <a href="news.php">الأخبار الرياضية</a>
        <a href="teams.php">الفرق</a>
        <a href="leagues.php">الدوري</a>
    </nav>
    <a href="search.php" class="search-trigger" aria-label="بحث">
        <svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
    </a>
    <div class="nav-overlay" aria-hidden="true"></div>
</div>

<script>
// simple mobile menu toggle with icon change
document.addEventListener('DOMContentLoaded', function () {
    var btn = document.querySelector('.menu-toggle');
    var nav = document.querySelector('.nav-links');
    var overlay = document.querySelector('.nav-overlay');
    if (!btn || !nav) return;
    btn.addEventListener('click', function () {
        var opened = nav.classList.toggle('open');
        btn.setAttribute('aria-expanded', opened ? 'true' : 'false');
        // change icon and aria-label
        btn.textContent = opened ? '✕' : '☰';
        btn.setAttribute('aria-label', opened ? 'إغلاق القائمة' : 'قائمة');
        if (overlay) overlay.classList.toggle('visible', opened);
    });

    // optional: close menu when clicking outside on mobile
    document.addEventListener('click', function (e) {
        if (!nav.classList.contains('open')) return;
        var target = e.target;
        if (target === nav || nav.contains(target) || target === btn) return;
        nav.classList.remove('open');
        btn.setAttribute('aria-expanded', 'false');
        btn.textContent = '☰';
        btn.setAttribute('aria-label', 'قائمة');
        if (overlay) overlay.classList.remove('visible');
    });

    // close when clicking overlay
    if (overlay) {
        overlay.addEventListener('click', function () {
            nav.classList.remove('open');
            btn.setAttribute('aria-expanded', 'false');
            btn.textContent = '☰';
            btn.setAttribute('aria-label', 'قائمة');
            overlay.classList.remove('visible');
        });
    }
});
</script>
