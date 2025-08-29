<?php
session_start();
include 'config.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

$asset_id = (int)($_POST['asset_id'] ?? 0);
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$status = $_POST['status'] ?? 'برنامه‌ریزی';
$priority = $_POST['priority'] ?? 'متوسط';
$planned_date = $_POST['planned_date'] ?? null;
$done_date = $_POST['done_date'] ?? null;

if ($asset_id <= 0 || $title === '') { header('Location: profile.php?id='.$asset_id); exit(); }

try {
    $stmt = $pdo->prepare("INSERT INTO maintenance_tasks (asset_id, title, description, status, priority, planned_date, done_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$asset_id, $title, $description, $status, $priority, $planned_date, $done_date]);
    logAction($pdo, 'TASK_ADD', 'افزودن تسک نگهداشت برای دستگاه '.$asset_id);
} catch (Throwable $e) {
    $_SESSION['error'] = 'خطا در ثبت تسک: '.$e->getMessage();
}
header('Location: profile.php?id='.$asset_id);
exit();
?>
