<?php
session_start();
require_once 'config.php';

// بررسی دسترسی
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tool_issue_id = $_POST['tool_issue_id'] ?? null;
    
    if (!$tool_issue_id) {
        echo json_encode(['success' => false, 'message' => 'شناسه تحویل ابزار الزامی است']);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        // دریافت اطلاعات تحویل ابزار
        $stmt = $pdo->prepare("SELECT tool_id FROM tool_issues WHERE id = ?");
        $stmt->execute([$tool_issue_id]);
        $tool_issue = $stmt->fetch();
        
        if (!$tool_issue) {
            throw new Exception('تحویل ابزار یافت نشد');
        }
        
        // به‌روزرسانی وضعیت تحویل ابزار
        $stmt = $pdo->prepare("UPDATE tool_issues SET status = 'برگشت_داده_شده', actual_return_date = CURDATE() WHERE id = ?");
        $stmt->execute([$tool_issue_id]);
        
        // به‌روزرسانی وضعیت ابزار
        $stmt = $pdo->prepare("UPDATE tools SET status = 'موجود' WHERE id = ?");
        $stmt->execute([$tool_issue['tool_id']]);
        
        $pdo->commit();
        
        // لاگ‌گیری
        logAction($pdo, 'RETURN_TOOL', "برگشت ابزار: " . $tool_issue['tool_id']);
        
        echo json_encode(['success' => true, 'message' => 'ابزار با موفقیت برگشت داده شد']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'خطا در برگشت ابزار: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>