<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

$image_id = (int)$_GET['id'];

if ($image_id <= 0) {
    die('شناسه تصویر نامعتبر است');
}

try {
    // دریافت اطلاعات تصویر
    $stmt = $pdo->prepare("SELECT * FROM education_images WHERE id = ? AND is_active = 1");
    $stmt->execute([$image_id]);
    $image = $stmt->fetch();
    
    if (!$image) {
        die('تصویر مورد نظر یافت نشد');
    }
    
    // بررسی وجود فایل
    if (!file_exists($image['image_path'])) {
        die('فایل مورد نظر یافت نشد');
    }
    
    // افزایش تعداد دانلود
    $stmt = $pdo->prepare("UPDATE education_images SET download_count = download_count + 1 WHERE id = ?");
    $stmt->execute([$image_id]);
    
    // ثبت لاگ
    logAction($pdo, 'download_image', "تصویر دانلود شد: " . $image['title']);
    
    // ارسال فایل
    $file_name = $image['title'] . '.' . $image['file_type'];
    $file_size = filesize($image['image_path']);
    
    header('Content-Type: image/' . $image['file_type']);
    header('Content-Disposition: attachment; filename="' . $file_name . '"');
    header('Content-Length: ' . $file_size);
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    readfile($image['image_path']);
    exit();
    
} catch (Exception $e) {
    error_log("خطا در دانلود تصویر: " . $e->getMessage());
    die('خطا در دانلود فایل');
}
?>