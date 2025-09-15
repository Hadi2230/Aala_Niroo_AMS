<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once 'config.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->query("SELECT id, tool_code, name, brand FROM tools WHERE status = 'موجود' ORDER BY name");
    $tools = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($tools);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>