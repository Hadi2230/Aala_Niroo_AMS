<?php
/**
 * اسکریپت به‌روزرسانی جدول customers
 * برای اضافه کردن فیلدهای ایمیل و اطلاع‌رسانی
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// بررسی دسترسی ادمین
$is_admin = ($_SESSION['role'] === 'ادمین' || $_SESSION['role'] === 'admin' || $_SESSION['role'] === 'administrator');
if (!$is_admin) {
    die('دسترسی غیرمجاز - فقط ادمین می‌تواند جدول را به‌روزرسانی کند');
}

$success = '';
$error = '';

try {
    // بررسی وجود فیلدها
    $columns = $pdo->query("SHOW COLUMNS FROM customers")->fetchAll(PDO::FETCH_COLUMN);
    
    $needed_columns = [
        'email' => "ALTER TABLE customers ADD COLUMN email VARCHAR(255) DEFAULT '' AFTER phone",
        'company_email' => "ALTER TABLE customers ADD COLUMN company_email VARCHAR(255) DEFAULT '' AFTER responsible_phone",
        'notification_type' => "ALTER TABLE customers ADD COLUMN notification_type ENUM('none','email','sms','both') DEFAULT 'none' AFTER notes"
    ];
    
    $added_columns = [];
    
    foreach ($needed_columns as $column => $sql) {
        if (!in_array($column, $columns)) {
            $pdo->exec($sql);
            $added_columns[] = $column;
        }
    }
    
    if (!empty($added_columns)) {
        $success = 'فیلدهای زیر با موفقیت اضافه شدند: ' . implode(', ', $added_columns);
    } else {
        $success = 'همه فیلدهای مورد نیاز موجود هستند.';
    }
    
    // ایجاد جدول notification_templates اگر وجود نداشته باشد
    $pdo->exec("CREATE TABLE IF NOT EXISTS notification_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type ENUM('email','sms') NOT NULL,
        name VARCHAR(255) NOT NULL,
        subject VARCHAR(500) DEFAULT '',
        content TEXT NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // درج قالب‌های پیش‌فرض
    $pdo->exec("INSERT IGNORE INTO notification_templates (type, name, subject, content) VALUES 
        ('email', 'خوش‌آمدگویی مشتری حقیقی', 'خوش‌آمدید به سیستم مدیریت اعلا نیرو', 'سلام {full_name} عزیز،\n\nبه سیستم مدیریت اعلا نیرو خوش‌آمدید!\n\nاطلاعات شما:\nنام: {full_name}\nتلفن: {phone}\nآدرس: {address}\n\nبا تشکر\nتیم اعلا نیرو'),
        ('email', 'خوش‌آمدگویی مشتری حقوقی', 'خوش‌آمدید به سیستم مدیریت اعلا نیرو', 'سلام {responsible_name} عزیز،\n\nشرکت {company} به سیستم مدیریت اعلا نیرو خوش‌آمدید!\n\nاطلاعات شرکت:\nنام شرکت: {company}\nمسئول: {responsible_name}\nتلفن شرکت: {company_phone}\nآدرس: {address}\n\nبا تشکر\nتیم اعلا نیرو'),
        ('sms', 'خوش‌آمدگویی SMS', '', 'سلام {full_name} عزیز، به سیستم مدیریت اعلا نیرو خوش‌آمدید! تیم اعلا نیرو')
    ");
    
    $success .= '<br>جدول notification_templates نیز ایجاد شد و قالب‌های پیش‌فرض اضافه شدند.';
    
} catch (Exception $e) {
    $error = 'خطا در به‌روزرسانی جدول: ' . $e->getMessage();
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>به‌روزرسانی جدول مشتریان</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <style>
        body { background:#f6f8fb; font-family: 'Vazirmatn', Tahoma, sans-serif; }
        .center-card { max-width:800px; margin:60px auto 30px; }
        .card-modern { border-radius:12px; box-shadow:0 14px 40px rgba(8,24,48,0.06); }
    </style>
</head>
<body>
<?php if (file_exists('navbar.php')) include 'navbar.php'; ?>

<div class="container center-card">
    <div class="card card-modern">
        <div class="card-header bg-primary text-white">
            <h4 class="m-0"><i class="fas fa-database me-2"></i>به‌روزرسانی جدول مشتریان</h4>
        </div>
        
        <div class="card-body">
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <?= $success ?>
                </div>
                <div class="text-center mt-4">
                    <a href="customers.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-arrow-left me-2"></i>برو به صفحه مشتریان
                    </a>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?= $error ?>
                </div>
            <?php endif; ?>
            
            <div class="mt-4">
                <h6>فیلدهای اضافه شده:</h6>
                <ul class="list-group">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-envelope me-2 text-success"></i>email</span>
                        <span class="badge bg-success">ایمیل مشتری حقیقی</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-building me-2 text-info"></i>company_email</span>
                        <span class="badge bg-info">ایمیل شرکت</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-bell me-2 text-warning"></i>notification_type</span>
                        <span class="badge bg-warning">نوع اطلاع‌رسانی</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>