<?php
session_start();
include 'config.php';

// لاگ‌گیری خروج
if (isset($_SESSION['user_id'])) {
    logAction($pdo, 'LOGOUT', "خروج کاربر: " . ($_SESSION['username'] ?? 'نامشخص'), 'info', 'auth', [
        'user_id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? null,
        'role' => $_SESSION['role'] ?? null
    ]);
}

session_destroy();
header('Location: login.php');
exit();
?>
