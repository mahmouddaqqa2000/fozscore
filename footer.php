<style>
    .site-footer {
        background-color: #1e293b;
        background-color: var(--primary, #1e293b);
        color: #cbd5e1;
        padding: 3rem 1rem 2rem;
        margin-top: 4rem;
        font-family: 'Tajawal', sans-serif;
        border-top: 1px solid rgba(255,255,255,0.1);
    }
    .footer-content {
        max-width: 1000px;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 2rem;
        text-align: center;
    }
    @media (min-width: 768px) {
        .site-footer { padding: 1.5rem 2rem; }
        .footer-content {
            flex-direction: row;
            justify-content: space-between;
            align-items: flex-start;
            text-align: right;
            flex-wrap: wrap;
        }
        .footer-info {
            align-items: flex-start;
            text-align: right;
            display: flex;
            flex-direction: column;
        }
        .site-footer-links {
            margin-top: 30px;
            align-items: flex-end;
            text-align: left;
        }
        .footer-brand { margin-top: 18px; }
    }
    .footer-brand {
        font-size: 1.8rem;
        font-weight: 800;
        color: #fff;
        margin-bottom: 0.5rem;
        display: block;
        text-decoration: none;
    }
    .footer-desc {
        max-width: 500px;
        margin: 0 auto;
        font-size: 0.95rem;
        line-height: 1.6;
        opacity: 0.8;
    }
    @media (min-width: 768px) {
        .footer-desc { margin: 0; max-width: 400px; }
        .site-footer-links { margin-top: 30px; }
    }
    .site-footer-links {
        display: flex;
        justify-content: center;
        gap: 25px;
        flex-wrap: wrap;
    }
    .site-footer a { color: #cbd5e1; text-decoration: none; transition: all 0.2s; font-weight: 500; }
    .site-footer a:hover { color: #fff; }
    
    .social-icons {
        display: flex;
        gap: 15px;
        margin: 1rem 0;
    }
    @media (min-width: 768px) {
        .social-icons { margin: 0; }
    }
    .social-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: rgba(255,255,255,0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s;
        color: #fff;
    }
    .social-icon:hover {
        background: var(--secondary, #2563eb);
        transform: translateY(-3px);
    }
    .social-icon svg {
        width: 20px;
        height: 20px;
        fill: currentColor;
    }
    
    .copyright {
        margin-top: 1.5rem;
        padding-top: 1.5rem;
        border-top: 1px solid rgba(255,255,255,0.1);
        width: 100%;
        font-size: 0.9rem;
        opacity: 0.6;
        text-align: center;
    }
</style>
<footer class="site-footer">
    <div class="footer-content">
        <div class="footer-info">
            <a href="index.php" class="footer-brand">FozScore</a>
            <p class="footer-desc">موقع رياضي شامل يقدم لك أحدث نتائج المباريات، أخبار الكرة العالمية والمحلية، وجداول الترتيب لحظة بلحظة.</p>
        </div>
        <div class="site-footer-links">
            <a href="index.php">الرئيسية</a>
            <a href="news.php">الأخبار</a>
            <a href="yesterday.php">مباريات الأمس</a>
            <a href="tomorrow.php">مباريات الغد</a>
        </div>

        <div class="social-icons">
            <a href="#" class="social-icon" aria-label="Twitter">
                <svg viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
            </a>
            <a href="#" class="social-icon" aria-label="Facebook">
                <svg viewBox="0 0 24 24"><path d="M9.101 23.691v-7.98H6.627v-3.667h2.474v-1.58c0-4.085 1.848-5.978 5.858-5.978.401 0 .955.042 1.468.103a8.68 8.68 0 0 1 1.141.195v3.325a8.623 8.623 0 0 0-.653-.036c-2.148 0-2.797 1.651-2.797 2.895v1.076h3.441l-.455 3.667h-2.986v7.98c-.087.586.085-.548.085-.548z"/></svg>
            </a>
            <a href="#" class="social-icon" aria-label="Instagram">
                <svg viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
            </a>
            <a href="#" class="social-icon" aria-label="YouTube">
                <svg viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
            </a>
        </div>

        <div class="copyright">
            <p style="margin: 0;">&copy; <?php echo date('Y'); ?> FozScore. جميع الحقوق محفوظة.</p>
        </div>
    </div>
</footer>