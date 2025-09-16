<?php
session_start();
require_once 'config.php';

// ثبت لاگ خروج
if (isset($_SESSION['user_id'])) {
    logAction($pdo, 'LOGOUT', "خروج کاربر: " . ($_SESSION['username'] ?? 'نامشخص'), 'info', 'auth');
}

// پاک کردن session
session_destroy();

// هدایت به صفحه ورود
header("Location: login.php");
exit();
?>