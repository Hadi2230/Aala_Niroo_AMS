<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit();
}

include 'config.php';

$image_id = (int)$_GET['id'];

if ($image_id <= 0) {
    http_response_code(400);
    exit();
}

try {
    // افزایش تعداد مشاهده
    $stmt = $pdo->prepare("UPDATE education_images SET view_count = view_count + 1 WHERE id = ? AND is_active = 1");
    $stmt->execute([$image_id]);
    
    // ثبت لاگ
    logAction($pdo, 'view_image', "تصویر مشاهده شد: ID $image_id");
    
    http_response_code(200);
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    error_log("خطا در ثبت مشاهده تصویر: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false]);
}
?>