<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once 'config.php';

header('Content-Type: application/json');

$type = $_GET['type'] ?? 'all';

try {
    switch ($type) {
        case 'all':
            $stmt = $pdo->query("SELECT * FROM tools ORDER BY created_at DESC");
            break;
            
        case 'available':
            $stmt = $pdo->query("SELECT * FROM tools WHERE status = 'موجود' ORDER BY name");
            break;
            
        case 'issued':
            $stmt = $pdo->query("
                SELECT t.tool_code, t.name, ti.issued_to, ti.issue_date, ti.expected_return_date, ti.id
                FROM tools t 
                JOIN tool_issues ti ON t.id = ti.tool_id 
                WHERE ti.status = 'تحویل_داده_شده'
                ORDER BY ti.issue_date DESC
            ");
            break;
            
        case 'overdue':
            $stmt = $pdo->query("
                SELECT t.tool_code, t.name, ti.issued_to, ti.issue_date, ti.expected_return_date, ti.id
                FROM tools t 
                JOIN tool_issues ti ON t.id = ti.tool_id 
                WHERE ti.status = 'تحویل_داده_شده' 
                AND ti.expected_return_date < CURDATE()
                ORDER BY ti.expected_return_date ASC
            ");
            break;
            
        default:
            throw new Exception('Invalid type');
    }
    
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($data);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>