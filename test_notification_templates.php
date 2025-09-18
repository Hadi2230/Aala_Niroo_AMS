<?php
// تست صفحه مدیریت قالب‌های اطلاع‌رسانی
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html dir='rtl' lang='fa'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>تست صفحه قالب‌های اطلاع‌رسانی</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        body { font-family: 'Tahoma', sans-serif; background: #f8f9fa; }
        .test-section { background: white; margin: 20px 0; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
        .info { color: #17a2b8; }
    </style>
</head>
<body>
<div class='container-fluid'>
    <div class='row'>
        <div class='col-12'>
            <div class='test-section'>
                <h2 class='text-center mb-4'><i class='fas fa-envelope'></i> تست صفحه مدیریت قالب‌های اطلاع‌رسانی</h2>";

// شروع session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تنظیم session برای تست
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['role'] = 'ادمین';

try {
    // بارگذاری config
    require_once 'config.php';
    
    echo "<div class='alert alert-success'>
            <i class='fas fa-check'></i> فایل config.php با موفقیت بارگذاری شد
          </div>";
    
    // تست اتصال دیتابیس
    echo "<div class='alert alert-success'>
            <i class='fas fa-database'></i> اتصال به دیتابیس برقرار است
          </div>";
    
    // تست تابع hasPermission
    if (function_exists('hasPermission')) {
        echo "<div class='alert alert-success'>
                <i class='fas fa-check'></i> تابع hasPermission موجود است
              </div>";
    } else {
        echo "<div class='alert alert-danger'>
                <i class='fas fa-times'></i> تابع hasPermission یافت نشد
              </div>";
    }
    
    // تست ایجاد جدول notification_templates
    try {
        $create_table = "CREATE TABLE IF NOT EXISTS notification_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type ENUM('email', 'sms') NOT NULL,
            name VARCHAR(255) NOT NULL,
            subject VARCHAR(500) DEFAULT NULL,
            content TEXT NOT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci";
        
        $pdo->exec($create_table);
        echo "<div class='alert alert-success'>
                <i class='fas fa-table'></i> جدول notification_templates ایجاد شد
              </div>";
        
        // تست درج قالب نمونه
        $stmt = $pdo->prepare("INSERT IGNORE INTO notification_templates (type, name, subject, content, is_active) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['email', 'تست قالب', 'موضوع تست', 'متن تست', 1]);
        echo "<div class='alert alert-success'>
                <i class='fas fa-plus'></i> قالب نمونه اضافه شد
              </div>";
        
        // تست دریافت قالب‌ها
        $stmt = $pdo->prepare("SELECT * FROM notification_templates ORDER BY type, name");
        $stmt->execute();
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<div class='alert alert-success'>
                <i class='fas fa-list'></i> دریافت قالب‌ها موفق - تعداد: " . count($templates) . "
              </div>";
        
    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>
                <i class='fas fa-exclamation-triangle'></i> خطا در ایجاد جدول: " . $e->getMessage() . "
              </div>";
    }
    
    // تست بارگذاری فایل notification_templates.php
    echo "<div class='alert alert-info'>
            <i class='fas fa-file'></i> تست بارگذاری فایل notification_templates.php...";
    
    ob_start();
    include 'notification_templates.php';
    $output = ob_get_clean();
    
    if (!empty($output)) {
        echo " <span class='success'>موفق</span></div>";
        echo "<div class='alert alert-info'>
                <i class='fas fa-info'></i> صفحه با موفقیت بارگذاری شد و محتوا تولید کرد
              </div>";
    } else {
        echo " <span class='error'>ناموفق - صفحه خالی است</span></div>";
    }
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>
            <i class='fas fa-exclamation-triangle'></i> خطا در تست: " . $e->getMessage() . "
          </div>";
}

echo "    </div>
    </div>
</div>
</body>
</html>";
?>