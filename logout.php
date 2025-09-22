<?php
session_start();

// بررسی وجود فایل config
if (!file_exists('config.php')) {
    if (file_exists('config_new.php')) {
        require_once 'config_new.php';
    }
} else {
    require_once 'config.php';
}

// ثبت لاگ خروج
if (isset($_SESSION['user_id']) && $pdo && function_exists('logAction')) {
    logAction($pdo, 'LOGOUT', "خروج کاربر: " . ($_SESSION['username'] ?? 'نامشخص'), 'info', 'auth');
}

// پاک کردن session
session_destroy();

// هدایت به صفحه ورود
header("Location: login.php");
exit();
?>