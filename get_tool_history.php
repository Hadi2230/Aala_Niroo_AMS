<?php
session_start();
require_once 'config.php';

// بررسی دسترسی
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$tool_id = $_GET['id'] ?? null;

if (!$tool_id) {
    echo json_encode(['error' => 'شناسه ابزار الزامی است']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            th.*,
            u.username as performer_name
        FROM tool_history th
        LEFT JOIN users u ON th.performed_by = u.id
        WHERE th.tool_id = ?
        ORDER BY th.performed_at DESC
    ");
    $stmt->execute([$tool_id]);
    $history = $stmt->fetchAll();
    
    header('Content-Type: application/json');
    echo json_encode($history);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'خطا در دریافت تاریخچه ابزار: ' . $e->getMessage()]);
}
?>