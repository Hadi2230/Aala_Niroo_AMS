<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

include 'config.php';

try {
    // شمارش اعلان‌های خوانده نشده
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch();
    
    echo json_encode(['count' => $result['count']]);
    
} catch (Exception $e) {
    echo json_encode(['count' => 0]);
    logAction($pdo, 'NOTIFICATION_COUNT_ERROR', "خطا در شمارش اعلان‌ها: " . $e->getMessage(), 'error');
}
?>