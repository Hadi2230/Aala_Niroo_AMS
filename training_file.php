<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Forbidden');
}

include 'config.php';

// پشتیبانی از دانلود inline/attachment برای فرم‌ها، تصاویر و مقالات
$type = isset($_GET['type']) ? trim($_GET['type']) : '';
$id   = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
$disposition = (isset($_GET['disposition']) && $_GET['disposition'] === 'attachment') ? 'attachment' : 'inline';

if (!in_array($type, ['form','image','article'], true) || $id <= 0) {
    http_response_code(400);
    exit('Bad Request');
}

switch ($type) {
    case 'form':
        $stmt = $pdo->prepare("SELECT file_path, file_name FROM training_forms WHERE id = ? AND is_active = 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        $path = $row['file_path'] ?? '';
        $name = $row['file_name'] ?? basename($path);
        break;
    case 'image':
        $stmt = $pdo->prepare("SELECT image_path, title FROM training_gallery WHERE id = ? AND is_active = 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        $path = $row['image_path'] ?? '';
        $ext  = pathinfo($path, PATHINFO_EXTENSION);
        $name = ($row['title'] ?? 'image') . ($ext ? ('.' . $ext) : '');
        break;
    case 'article':
        $stmt = $pdo->prepare("SELECT COALESCE(pdf_file, featured_image) AS path, title FROM training_articles WHERE id = ? AND is_active = 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        $path = $row['path'] ?? '';
        $ext  = pathinfo($path, PATHINFO_EXTENSION);
        $name = ($row['title'] ?? 'article') . ($ext ? ('.' . $ext) : '');
        break;
}

if (empty($path)) {
    http_response_code(404);
    exit('Not Found');
}

$absolute = ((substr($path, 0, 1) === '/') ? $path : (__DIR__ . '/' . ltrim($path, '/')));
if (!is_file($absolute)) {
    http_response_code(404);
    exit('File Missing');
}

$mime = function_exists('mime_content_type') ? mime_content_type($absolute) : 'application/octet-stream';
$size = filesize($absolute);

header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . $mime);
header('Content-Length: ' . $size);
header('Content-Disposition: ' . $disposition . '; filename="' . rawurldecode($name) . '"');

readfile($absolute);
exit;
