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
    $stmt = $pdo->prepare("SELECT permissions FROM custom_roles WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $role = $stmt->fetch();
    
    if ($role) {
        $permissions = json_decode($role['permissions'], true);
        echo json_encode(['permissions' => $permissions ?: []]);
    } else {
        echo json_encode(['permissions' => []]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'خطا در دریافت اطلاعات']);
}
?>