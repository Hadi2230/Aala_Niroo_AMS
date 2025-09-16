<?php
session_start();
require_once 'config.php';

// بررسی دسترسی
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$type = $_GET['type'] ?? 'all';

try {
    switch ($type) {
        case 'all':
            $stmt = $pdo->query("SELECT * FROM tools ORDER BY created_at DESC");
            $data = $stmt->fetchAll();
            break;
            
        case 'available':
            $stmt = $pdo->query("SELECT * FROM tools WHERE status = 'موجود' ORDER BY name");
            $data = $stmt->fetchAll();
            break;
            
        case 'issued':
            $stmt = $pdo->query("SELECT ti.*, t.name as tool_name, t.tool_code FROM tool_issues ti JOIN tools t ON ti.tool_id = t.id WHERE ti.status = 'تحویل_داده_شده' ORDER BY ti.issue_date DESC");
            $data = $stmt->fetchAll();
            break;
            
        case 'overdue':
            $stmt = $pdo->query("SELECT ti.*, t.name as tool_name, t.tool_code FROM tool_issues ti JOIN tools t ON ti.tool_id = t.id WHERE ti.status = 'تحویل_داده_شده' AND ti.expected_return_date < CURDATE() ORDER BY ti.expected_return_date ASC");
            $data = $stmt->fetchAll();
            break;
            
        default:
            $data = [];
    }
    
    header('Content-Type: application/json');
    echo json_encode($data);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>