<?php
session_start();
require_once 'config.php';

// بررسی دسترسی
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$tool_id = $_GET['id'] ?? null;

if (!$tool_id) {
    echo json_encode(['success' => false, 'message' => 'شناسه ابزار الزامی است']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM tools WHERE id = ?");
    $stmt->execute([$tool_id]);
    $tool = $stmt->fetch();
    
    if (!$tool) {
        echo json_encode(['success' => false, 'message' => 'ابزار یافت نشد']);
        exit;
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'tool' => $tool]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطا در دریافت اطلاعات ابزار: ' . $e->getMessage()]);
}
?>