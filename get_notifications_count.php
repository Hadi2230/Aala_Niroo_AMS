<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['count' => 0]);
    exit();
}

include 'config.php';

$unread_count = $pdo->query("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = false")->fetch(['user_id' => $_SESSION['user_id']])['count'];

echo json_encode(['count' => $unread_count]);
?>