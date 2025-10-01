<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// بررسی دسترسی ادمین
if ($_SESSION['role'] !== 'ادمین') {
    die('دسترسی غیرمجاز - فقط ادمین می‌تواند تصویر آپلود کند');
}

// بررسی CSRF token
verifyCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    try {
        $title = sanitizeInput($_POST['title']);
        $description = sanitizeInput($_POST['description']);
        
        if (empty($title)) {
            throw new Exception('عنوان تصویر الزامی است');
        }
        
        // آپلود فایل اصلی
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $uploaded_file = uploadFile($_FILES['file'], __DIR__ . '/uploads/education/images/', $allowed_types);
        
        // ایجاد thumbnail
        $thumbnail_path = createThumbnail($uploaded_file, 300, 300);
        
        // دریافت اطلاعات فایل
        $file_size = filesize($uploaded_file);
        $file_type = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        
        // ذخیره در دیتابیس
        $stmt = $pdo->prepare("INSERT INTO education_images (title, description, image_path, thumbnail_path, file_size, file_type, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $description, $uploaded_file, $thumbnail_path, $file_size, $file_type, $_SESSION['user_id']]);
        
        // ثبت لاگ
        logAction($pdo, 'upload_image', "تصویر جدید آپلود شد: $title");
        
        $_SESSION['success_message'] = 'تصویر با موفقیت آپلود شد';
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

/**
 * ایجاد thumbnail برای تصاویر
 */
function createThumbnail($source_path, $max_width, $max_height) {
    $info = getimagesize($source_path);
    if (!$info) {
        throw new Exception('فایل تصویر نامعتبر است');
    }
    
    $source_width = $info[0];
    $source_height = $info[1];
    $mime_type = $info['mime'];
    
    // محاسبه ابعاد جدید
    $ratio = min($max_width / $source_width, $max_height / $source_height);
    $new_width = (int)($source_width * $ratio);
    $new_height = (int)($source_height * $ratio);
    
    // ایجاد تصویر منبع
    switch ($mime_type) {
        case 'image/jpeg':
            $source_image = imagecreatefromjpeg($source_path);
            break;
        case 'image/png':
            $source_image = imagecreatefrompng($source_path);
            break;
        case 'image/gif':
            $source_image = imagecreatefromgif($source_path);
            break;
        case 'image/webp':
            $source_image = imagecreatefromwebp($source_path);
            break;
        default:
            throw new Exception('نوع تصویر پشتیبانی نمی‌شود');
    }
    
    // ایجاد thumbnail
    $thumbnail = imagecreatetruecolor($new_width, $new_height);
    
    // حفظ شفافیت برای PNG و GIF
    if ($mime_type === 'image/png' || $mime_type === 'image/gif') {
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
        $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
        imagefilledrectangle($thumbnail, 0, 0, $new_width, $new_height, $transparent);
    }
    
    // تغییر اندازه
    imagecopyresampled($thumbnail, $source_image, 0, 0, 0, 0, $new_width, $new_height, $source_width, $source_height);
    
    // ذخیره thumbnail
    $thumbnail_name = 'thumb_' . time() . '_' . uniqid() . '.jpg';
    $thumbnail_path = __DIR__ . '/uploads/education/thumbnails/' . $thumbnail_name;
    
    imagejpeg($thumbnail, $thumbnail_path, 85);
    
    // آزاد کردن حافظه
    imagedestroy($source_image);
    imagedestroy($thumbnail);
    
    return '/uploads/education/thumbnails/' . $thumbnail_name;
}
?>