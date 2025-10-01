<?php
session_start();
if (!isset($_SESSION['user_id'])) { http_response_code(403); exit('Forbidden'); }
include 'config.php';

$disposition = (isset($_GET['disposition']) && $_GET['disposition'] === 'attachment') ? 'attachment' : 'inline';
$version_id = isset($_GET['version_id']) && ctype_digit($_GET['version_id']) ? (int)$_GET['version_id'] : null;
$contract_id = isset($_GET['id']) && ctype_digit($_GET['id']) ? (int)$_GET['id'] : null;

if (!$version_id && !$contract_id) { http_response_code(400); exit('Bad Request'); }

if ($version_id) {
    $st = $pdo->prepare("SELECT v.*, c.id as contract_id FROM contract_versions v JOIN contracts c ON v.contract_id = c.id WHERE v.id = ?");
    $st->execute([$version_id]);
    $row = $st->fetch();
} else {
    // آخرین نسخه قرارداد
    $st = $pdo->prepare("SELECT v.*, c.id as contract_id FROM contracts c JOIN contract_versions v ON v.id = c.latest_version_id WHERE c.id = ?");
    $st->execute([$contract_id]);
    $row = $st->fetch();
}

if (!$row) { http_response_code(404); exit('Not Found'); }

$cid = (int)$row['contract_id'];
if (!contractUserCan($pdo, $cid, 'view')) { http_response_code(403); exit('Forbidden'); }

$path = $row['file_path'];
$absolute = ((substr($path, 0, 1) === '/') ? $path : (__DIR__ . '/' . ltrim($path, '/')));
if (!is_file($absolute)) { http_response_code(404); exit('File Missing'); }

$mime = function_exists('mime_content_type') ? mime_content_type($absolute) : ($row['mime_type'] ?: 'application/octet-stream');
$size = filesize($absolute);
$name = basename($absolute);

header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . $mime);
header('Content-Length: ' . $size);
header('Content-Disposition: ' . $disposition . '; filename="' . rawurldecode($name) . '"');
readfile($absolute);
exit;

