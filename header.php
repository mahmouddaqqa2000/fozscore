<?php
// header.php - شريط تنقل مشترك
?>
<style>
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
    }
</style>

<div class="navbar">
    <a class="brand" href="index.php">FozScore</a>
    <button class="menu-toggle" aria-label="قائمة" aria-expanded="false">☰</button>
    <nav class="nav-links" role="navigation">
        <a href="index.php">مباريات اليوم</a>
        <a href="news.php">الأخبار الرياضية</a>
        <a href="teams.php">الفرق</a>
        <a href="leagues.php">الدوري</a>
    </nav>
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
