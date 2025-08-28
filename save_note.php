<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$note = isset($_POST['note']) ? trim($_POST['note']) : '';
if ($note === '') {
    $_SESSION['error'] = 'متن یادداشت خالی است';
    header('Location: dashboard.php');
    exit();
}

try {
    // اگر آخرین یادداشت مشابه وجود دارد می‌توان ادغام کرد؛ فعلاً درج جدید
    $stmt = $pdo->prepare("INSERT INTO user_notes (user_id, note) VALUES (?, ?)");
    $stmt->execute([$_SESSION['user_id'], $note]);
    $_SESSION['success'] = 'یادداشت ذخیره شد';
} catch (Throwable $e) {
    $_SESSION['error'] = 'خطا در ذخیره یادداشت: ' . $e->getMessage();
}

header('Location: dashboard.php');
exit();
?>
