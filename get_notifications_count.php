<?php
// تنظیم header برای JSON
header('Content-Type: application/json; charset=utf-8');

// شروع session
session_start();

// بررسی وجود user_id
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['count' => 0, 'error' => 'Unauthorized']);
    exit;
}

try {
    // اتصال به دیتابیس
    include 'config.php';
    
    // بررسی وجود جدول notifications
    $check_table = $pdo->query("SHOW TABLES LIKE 'notifications'")->fetch();
    if (!$check_table) {
        echo json_encode(['count' => 0, 'error' => 'Table not found']);
        exit;
    }
    
    // دریافت تعداد اعلان‌های خوانده نشده
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = false");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch();
    
    echo json_encode(['count' => (int)$result['count']]);
    
} catch (Exception $e) {
    error_log("Error in get_notifications_count.php: " . $e->getMessage());
    echo json_encode(['count' => 0, 'error' => 'Database error']);
}
?>