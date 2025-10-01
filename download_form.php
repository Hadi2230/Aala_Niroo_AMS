<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

$form_id = (int)$_GET['id'];

if ($form_id <= 0) {
    die('شناسه فرم نامعتبر است');
}

try {
    // دریافت اطلاعات فرم
    $stmt = $pdo->prepare("SELECT * FROM education_forms WHERE id = ? AND is_active = 1");
    $stmt->execute([$form_id]);
    $form = $stmt->fetch();
    
    if (!$form) {
        die('فرم مورد نظر یافت نشد');
    }
    
    // بررسی وجود فایل
    if (!file_exists($form['file_path'])) {
        die('فایل مورد نظر یافت نشد');
    }
    
    // افزایش تعداد دانلود
    $stmt = $pdo->prepare("UPDATE education_forms SET download_count = download_count + 1 WHERE id = ?");
    $stmt->execute([$form_id]);
    
    // ثبت لاگ
    logAction($pdo, 'download_form', "فرم دانلود شد: " . $form['title']);
    
    // ارسال فایل
    $file_name = $form['title'] . '.' . $form['file_type'];
    $file_size = filesize($form['file_path']);
    
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $file_name . '"');
    header('Content-Length: ' . $file_size);
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    readfile($form['file_path']);
    exit();
    
} catch (Exception $e) {
    error_log("خطا در دانلود فرم: " . $e->getMessage());
    die('خطا در دانلود فایل');
}
?>