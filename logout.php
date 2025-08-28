<?php
session_start();
include 'config.php';
if (isset($_SESSION['user_id'])) {
    try { logAction($pdo, 'LOGOUT', 'کاربر از سیستم خارج شد'); } catch (Throwable $e) {}
}
session_destroy();
header('Location: login.php');
exit();
?>
