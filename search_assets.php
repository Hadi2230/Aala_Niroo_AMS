<?php
session_start();
include 'config.php';
if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['items'=>[]]); exit(); }

header('Content-Type: application/json; charset=utf-8');
$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20; $offset = ($page-1)*$limit;

$params = [];
$sql = "SELECT id, name, device_model, device_serial, engine_model, engine_serial FROM assets WHERE status IN ('فعال','آماده بهره‌برداری')";
if ($q !== '') {
    $sql .= " AND (name LIKE ? OR device_model LIKE ? OR device_serial LIKE ? OR engine_model LIKE ? OR engine_serial LIKE ?)";
    $term = "%$q%"; $params = array_merge($params, [$term,$term,$term,$term,$term]);
}
$sql .= " ORDER BY name LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$items = array_map(function($r){
    $label = $r['name'];
    if (!empty($r['device_model'])) $label .= " | مدل: " . $r['device_model'];
    if (!empty($r['device_serial'])) $label .= " | سریال: " . $r['device_serial'];
    return [
        'id' => $r['id'],
        'text' => $label,
        'device_model' => $r['device_model'] ?? '',
        'device_serial' => $r['device_serial'] ?? '',
        'engine_model' => $r['engine_model'] ?? '',
        'engine_serial' => $r['engine_serial'] ?? ''
    ];
}, $rows);

echo json_encode(['items'=>$items], JSON_UNESCAPED_UNICODE);
exit();
?>

