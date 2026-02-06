<style>
    .admin-header {
        background: #1e293b;
        color: white;
        padding: 1rem 2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .admin-header h1 { margin: 0; font-size: 1.5rem; }
    .admin-header a { color: white; text-decoration: none; margin-left: 1.5rem; font-weight: 600; }
    .admin-header a:hover { text-decoration: underline; }
</style>
<header class="admin-header">
    <h1><a href="bot_dashboard.php">لوحة تحكم FozScore</a></h1>
    <nav>
        <span>أهلاً, <strong><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></strong></span>
        <a href="admin_settings.php">الإعدادات</a>
        <a href="logout.php">تسجيل الخروج</a>
    </nav>
</header>