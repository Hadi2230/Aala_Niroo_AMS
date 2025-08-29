<?php
session_start();
include 'config.php';
if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['items'=>[]]); exit(); }

header('Content-Type: application/json; charset=utf-8');
$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20; $offset = ($page-1)*$limit;

$params = [];
$sql = "SELECT id, full_name, phone FROM customers WHERE 1=1";
if ($q !== '') {
    $sql .= " AND (full_name LIKE ? OR phone LIKE ?)";
    $term = "%$q%"; $params[] = $term; $params[] = $term;
}
$sql .= " ORDER BY full_name LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$items = array_map(function($r){
    return [
        'id' => $r['id'],
        'text' => $r['full_name'] . (empty($r['phone']) ? '' : (" - " . $r['phone'])),
        'phone' => $r['phone'] ?? ''
    ];
}, $rows);

echo json_encode(['items'=>$items], JSON_UNESCAPED_UNICODE);
exit();
?>

