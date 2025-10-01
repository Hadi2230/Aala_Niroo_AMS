<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Forbidden');
}

include 'config.php';

$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    exit('Bad Request');
}

$stmt = $pdo->prepare("SELECT COALESCE(video_path, '') AS path FROM training_videos WHERE id = ? AND is_active = 1 AND video_type = 'upload'");
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row || empty($row['path'])) {
    http_response_code(404);
    exit('Not Found');
}

$path = $row['path'];
$absolute = (str_starts_with($path, '/') ? $path : (__DIR__ . '/' . ltrim($path, '/')));
if (!is_file($absolute)) {
    http_response_code(404);
    exit('File Missing');
}

$size = filesize($absolute);
$mime = 'video/mp4';
header('Content-Type: ' . $mime);
header('Accept-Ranges: bytes');

$start = 0; $end = $size - 1; $length = $size;
if (isset($_SERVER['HTTP_RANGE'])) {
    if (preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
        $start = (int)$m[1];
        if ($m[2] !== '') $end = (int)$m[2];
        $end = min($end, $size - 1);
        $length = $end - $start + 1;
        header('HTTP/1.1 206 Partial Content');
        header("Content-Range: bytes $start-$end/$size");
        header('Content-Length: ' . $length);
    }
} else {
    header('Content-Length: ' . $size);
}

$fp = fopen($absolute, 'rb');
fseek($fp, $start);
$buffer = 8192; $bytes = $length;
while ($bytes > 0 && !feof($fp)) {
    $read = ($bytes > $buffer) ? $buffer : $bytes;
    echo fread($fp, $read);
    flush();
    $bytes -= $read;
}
fclose($fp);
exit;
