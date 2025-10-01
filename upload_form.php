<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// بررسی دسترسی ادمین
if ($_SESSION['role'] !== 'ادمین') {
    die('دسترسی غیرمجاز - فقط ادمین می‌تواند فرم آپلود کند');
}

// بررسی CSRF token
verifyCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    try {
        $title = sanitizeInput($_POST['title']);
        $description = sanitizeInput($_POST['description']);
        
        if (empty($title)) {
            throw new Exception('عنوان فرم الزامی است');
        }
        
        // آپلود فایل
        $allowed_types = ['pdf', 'doc', 'docx'];
        $uploaded_file = uploadFile($_FILES['file'], __DIR__ . '/uploads/education/forms/', $allowed_types);
        
        // دریافت اطلاعات فایل
        $file_size = filesize($uploaded_file);
        $file_type = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        
        // ذخیره در دیتابیس
        $stmt = $pdo->prepare("INSERT INTO education_forms (title, description, file_path, file_size, file_type, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $description, $uploaded_file, $file_size, $file_type, $_SESSION['user_id']]);
        
        // ثبت لاگ
        logAction($pdo, 'upload_form', "فرم جدید آپلود شد: $title");
        
        $_SESSION['success_message'] = 'فرم با موفقیت آپلود شد';
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