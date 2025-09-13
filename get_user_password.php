<?php
session_start();
include 'config.php';

// بررسی دسترسی ادمین
if (!hasPermission('*')) {
    http_response_code(403);
    echo json_encode(['error' => 'دسترسی غیرمجاز']);
    exit();
}

$user_id = (int)($_GET['user_id'] ?? 0);

if (!$user_id) {
    http_response_code(400);
    echo json_encode(['error' => 'شناسه کاربر نامعتبر']);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if ($user) {
        echo json_encode(['password' => $user['password']]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'کاربر یافت نشد']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'خطا در دریافت اطلاعات']);
}
?>