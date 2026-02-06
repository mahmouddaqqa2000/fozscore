<?php
// auth_check.php - التحقق من تسجيل الدخول

// لا تقم بتشغيل التحقق إذا كان السكربت يعمل من سطر الأوامر (Cron Job)
if (php_sapi_name() === 'cli') {
    return;
}

session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}