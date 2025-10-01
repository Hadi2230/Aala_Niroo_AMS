<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// بررسی دسترسی ادمین
if ($_SESSION['role'] !== 'ادمین') {
    die('دسترسی غیرمجاز - فقط ادمین می‌تواند مقاله آپلود کند');
}

// بررسی CSRF token
verifyCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $title = sanitizeInput($_POST['title']);
        $content = sanitizeInput($_POST['content']);
        
        if (empty($title)) {
            throw new Exception('عنوان مقاله الزامی است');
        }
        
        $file_path = null;
        $file_size = null;
        $file_type = null;
        
        // اگر فایل آپلود شده باشد
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['pdf', 'doc', 'docx'];
            $file_path = uploadFile($_FILES['file'], __DIR__ . '/uploads/education/articles/', $allowed_types);
            $file_size = filesize($file_path);
            $file_type = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        }
        
        // ذخیره در دیتابیس
        $stmt = $pdo->prepare("INSERT INTO education_articles (title, content, file_path, file_size, file_type, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $content, $file_path, $file_size, $file_type, $_SESSION['user_id']]);
        
        // ثبت لاگ
        logAction($pdo, 'upload_article', "مقاله جدید آپلود شد: $title");
        
        $_SESSION['success_message'] = 'مقاله با موفقیت آپلود شد';
        header('Location: education.php');
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
        header('Location: education.php');
        exit();
    }
} else {
    header('Location: education.php');
    exit();
}
?>