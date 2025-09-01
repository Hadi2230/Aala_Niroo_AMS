<?php
session_start();
include 'config.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

$asset_id = (int)($_POST['asset_id'] ?? 0);
$service_date = $_POST['service_date'] ?? '';
$service_type = $_POST['service_type'] ?? 'دوره‌ای';
$performed_by = trim($_POST['performed_by'] ?? '');
$summary = trim($_POST['summary'] ?? '');
$next_due_date = $_POST['next_due_date'] ?? null;
$notes = trim($_POST['notes'] ?? '');

if ($asset_id <= 0 || $service_date === '') { header('Location: profile.php?id='.$asset_id); exit(); }

try {
    $stmt = $pdo->prepare("INSERT INTO asset_services (asset_id, service_date, service_type, performed_by, summary, notes, next_due_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$asset_id, $service_date, $service_type, $performed_by, $summary, $notes, $next_due_date]);
    logAction($pdo, 'SERVICE_ADD', 'افزودن سرویس برای دستگاه '.$asset_id);
} catch (Throwable $e) {
    $_SESSION['error'] = 'خطا در ثبت سرویس: '.$e->getMessage();
}
header('Location: profile.php?id='.$asset_id);
exit();
?>

