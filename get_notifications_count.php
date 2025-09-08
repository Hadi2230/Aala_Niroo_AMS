<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['count' => 0]);
    exit;
}

include 'config.php';

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = false");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch();
    echo json_encode(['count' => (int)$result['count']]);
} catch (Exception $e) {
    echo json_encode(['count' => 0]);
}
?>