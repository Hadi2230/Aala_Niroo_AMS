<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

$article_id = (int)$_GET['id'];

if ($article_id <= 0) {
    die('شناسه مقاله نامعتبر است');
}

try {
    // دریافت اطلاعات مقاله
    $stmt = $pdo->prepare("SELECT * FROM education_articles WHERE id = ? AND is_active = 1");
    $stmt->execute([$article_id]);
    $article = $stmt->fetch();
    
    if (!$article) {
        die('مقاله مورد نظر یافت نشد');
    }
    
    if (!$article['file_path'] || !file_exists($article['file_path'])) {
        die('فایل مورد نظر یافت نشد');
    }
    
    // افزایش تعداد دانلود
    $stmt = $pdo->prepare("UPDATE education_articles SET download_count = download_count + 1 WHERE id = ?");
    $stmt->execute([$article_id]);
    
    // ثبت لاگ
    logAction($pdo, 'download_article', "مقاله دانلود شد: " . $article['title']);
    
    // ارسال فایل
    $file_name = $article['title'] . '.' . $article['file_type'];
    $file_size = filesize($article['file_path']);
    
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $file_name . '"');
    header('Content-Length: ' . $file_size);
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    readfile($article['file_path']);
    exit();
    
} catch (Exception $e) {
    error_log("خطا در دانلود مقاله: " . $e->getMessage());
    die('خطا در دانلود فایل');
}
?>