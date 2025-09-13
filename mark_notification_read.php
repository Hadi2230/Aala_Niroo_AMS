<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notification_id'])) {
    if (markNotificationAsRead($pdo, sanitizeInput($_POST['notification_id']), $_SESSION['user_id'])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'خطا در به‌روزرسانی اعلان']);
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'درخواست نامعتبر']);
}
?>