<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$issue_id = (int)($input['issue_id'] ?? 0);

if (!$issue_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid issue ID']);
    exit();
}

try {
    $pdo->beginTransaction();
    
    // دریافت اطلاعات تحویل
    $stmt = $pdo->prepare("SELECT tool_id FROM tool_issues WHERE id = ? AND status = 'تحویل_داده_شده'");
    $stmt->execute([$issue_id]);
    $issue = $stmt->fetch();
    
    if (!$issue) {
        throw new Exception('تحویل یافت نشد یا قبلاً برگشت داده شده است');
    }
    
    // به‌روزرسانی وضعیت تحویل
    $stmt = $pdo->prepare("UPDATE tool_issues SET status = 'برگشت_داده_شده', actual_return_date = CURDATE() WHERE id = ?");
    $stmt->execute([$issue_id]);
    
    // تغییر وضعیت ابزار به موجود
    $stmt = $pdo->prepare("UPDATE tools SET status = 'موجود' WHERE id = ?");
    $stmt->execute([$issue['tool_id']]);
    
    $pdo->commit();
    
    logAction($pdo, 'RETURN_TOOL', "برگشت ابزار (Issue ID: $issue_id)");
    
    echo json_encode(['success' => true, 'message' => 'ابزار با موفقیت برگشت داده شد']);
    
} catch (Exception $e) {
    $pdo->rollBack();
    logAction($pdo, 'RETURN_TOOL_ERROR', "خطا در برگشت ابزار: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>