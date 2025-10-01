<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// بررسی دسترسی ادمین
if ($_SESSION['role'] !== 'ادمین') {
    die('دسترسی غیرمجاز - فقط ادمین می‌تواند ویدیو آپلود کند');
}

// بررسی CSRF token
verifyCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    try {
        $title = sanitizeInput($_POST['title']);
        $description = sanitizeInput($_POST['description']);
        
        if (empty($title)) {
            throw new Exception('عنوان ویدیو الزامی است');
        }
        
        // آپلود فایل اصلی
        $allowed_types = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm'];
        $uploaded_file = uploadFile($_FILES['file'], __DIR__ . '/uploads/education/videos/', $allowed_types);
        
        // ایجاد thumbnail برای ویدیو
        $thumbnail_path = createVideoThumbnail($uploaded_file);
        
        // محاسبه مدت زمان ویدیو
        $duration = getVideoDuration($uploaded_file);
        
        // دریافت اطلاعات فایل
        $file_size = filesize($uploaded_file);
        $file_type = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        
        // ذخیره در دیتابیس
        $stmt = $pdo->prepare("INSERT INTO education_videos (title, description, video_path, thumbnail_path, duration, file_size, file_type, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $description, $uploaded_file, $thumbnail_path, $duration, $file_size, $file_type, $_SESSION['user_id']]);
        
        // ثبت لاگ
        logAction($pdo, 'upload_video', "ویدیو جدید آپلود شد: $title");
        
        $_SESSION['success_message'] = 'ویدیو با موفقیت آپلود شد';
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
 * ایجاد thumbnail برای ویدیو
 */
function createVideoThumbnail($video_path) {
    // بررسی وجود ffmpeg
    if (!function_exists('shell_exec') || !shell_exec('which ffmpeg')) {
        // اگر ffmpeg موجود نیست، از تصویر پیش‌فرض استفاده کن
        return '/uploads/education/thumbnails/default_video.jpg';
    }
    
    try {
        $thumbnail_name = 'video_thumb_' . time() . '_' . uniqid() . '.jpg';
        $thumbnail_path = __DIR__ . '/uploads/education/thumbnails/' . $thumbnail_name;
        
        // ایجاد thumbnail در ثانیه 5 ویدیو
        $command = "ffmpeg -i " . escapeshellarg($video_path) . " -ss 00:00:05 -vframes 1 -q:v 2 " . escapeshellarg($thumbnail_path) . " 2>&1";
        $output = shell_exec($command);
        
        if (file_exists($thumbnail_path)) {
            return '/uploads/education/thumbnails/' . $thumbnail_name;
        } else {
            throw new Exception('خطا در ایجاد thumbnail');
        }
    } catch (Exception $e) {
        // در صورت خطا، از تصویر پیش‌فرض استفاده کن
        return '/uploads/education/thumbnails/default_video.jpg';
    }
}

/**
 * محاسبه مدت زمان ویدیو
 */
function getVideoDuration($video_path) {
    // بررسی وجود ffprobe
    if (!function_exists('shell_exec') || !shell_exec('which ffprobe')) {
        return 0;
    }
    
    try {
        $command = "ffprobe -v quiet -show_entries format=duration -of csv=p=0 " . escapeshellarg($video_path);
        $duration = shell_exec($command);
        
        return (int)floatval(trim($duration));
    } catch (Exception $e) {
        return 0;
    }
}
?>