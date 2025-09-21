<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// ثبت لاگ
logAction($pdo, 'VIEW_QUICK_TEST', 'مشاهده صفحه تست سریع');

$tests = [];
$overall_ok = true;

// تست 1: اتصال دیتابیس
try {
    $stmt = $pdo->query("SELECT 1");
    $tests[] = ['name' => 'اتصال دیتابیس', 'status' => 'ok', 'message' => 'موفق'];
} catch (Exception $e) {
    $tests[] = ['name' => 'اتصال دیتابیس', 'status' => 'error', 'message' => $e->getMessage()];
    $overall_ok = false;
}

// تست 2: جداول اصلی
$tables = ['users', 'assets', 'customers', 'asset_assignments', 'system_logs'];
$missing_tables = [];
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() == 0) {
            $missing_tables[] = $table;
        }
    } catch (Exception $e) {
        $missing_tables[] = $table;
    }
}

if (empty($missing_tables)) {
    $tests[] = ['name' => 'جداول دیتابیس', 'status' => 'ok', 'message' => 'همه جداول موجود'];
} else {
    $tests[] = ['name' => 'جداول دیتابیس', 'status' => 'error', 'message' => 'جداول مفقود: ' . implode(', ', $missing_tables)];
    $overall_ok = false;
}

// تست 3: توابع تاریخ
try {
    $jalali = '1403/01/01';
    $gregorian = jalaliToGregorianForDB($jalali);
    $back_to_jalali = gregorianToJalaliFromDB($gregorian);
    
    if ($back_to_jalali === $jalali) {
        $tests[] = ['name' => 'توابع تاریخ شمسی', 'status' => 'ok', 'message' => 'کار می‌کند'];
    } else {
        $tests[] = ['name' => 'توابع تاریخ شمسی', 'status' => 'error', 'message' => 'مشکل در تبدیل تاریخ'];
        $overall_ok = false;
    }
} catch (Exception $e) {
    $tests[] = ['name' => 'توابع تاریخ شمسی', 'status' => 'error', 'message' => $e->getMessage()];
    $overall_ok = false;
}

// تست 4: فایل‌های اصلی
$files = ['config.php', 'navbar.php', 'login.php', 'assets.php', 'customers.php', 'assignments.php'];
$missing_files = [];
foreach ($files as $file) {
    if (!file_exists($file)) {
        $missing_files[] = $file;
    }
}

if (empty($missing_files)) {
    $tests[] = ['name' => 'فایل‌های اصلی', 'status' => 'ok', 'message' => 'همه فایل‌ها موجود'];
} else {
    $tests[] = ['name' => 'فایل‌های اصلی', 'status' => 'error', 'message' => 'فایل‌های مفقود: ' . implode(', ', $missing_files)];
    $overall_ok = false;
}

// تست 5: سیستم لاگ
try {
    logAction($pdo, 'QUICK_TEST', 'تست سیستم لاگ');
    $tests[] = ['name' => 'سیستم لاگ', 'status' => 'ok', 'message' => 'کار می‌کند'];
} catch (Exception $e) {
    $tests[] = ['name' => 'سیستم لاگ', 'status' => 'error', 'message' => $e->getMessage()];
    $overall_ok = false;
}

// تست 6: تعداد رکوردها
try {
    $user_count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $asset_count = $pdo->query("SELECT COUNT(*) FROM assets")->fetchColumn();
    $customer_count = $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
    
    $tests[] = ['name' => 'داده‌های سیستم', 'status' => 'ok', 'message' => "کاربران: $user_count, دارایی‌ها: $asset_count, مشتریان: $customer_count"];
} catch (Exception $e) {
    $tests[] = ['name' => 'داده‌های سیستم', 'status' => 'error', 'message' => $e->getMessage()];
    $overall_ok = false;
}

// تست 7: مجوزهای فایل
$upload_dir = 'uploads/';
if (is_dir($upload_dir) && is_writable($upload_dir)) {
    $tests[] = ['name' => 'مجوزهای فایل', 'status' => 'ok', 'message' => 'پوشه آپلود قابل نوشتن است'];
} else {
    $tests[] = ['name' => 'مجوزهای فایل', 'status' => 'error', 'message' => 'مشکل در مجوزهای فایل'];
    $overall_ok = false;
}

// تست 8: تنظیمات PHP
$memory_limit = ini_get('memory_limit');
$max_execution_time = ini_get('max_execution_time');
$upload_max_filesize = ini_get('upload_max_filesize');

$tests[] = ['name' => 'تنظیمات PHP', 'status' => 'ok', 'message' => "Memory: $memory_limit, Time: {$max_execution_time}s, Upload: $upload_max_filesize"];
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تست سریع سیستم - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    
    <style>
        body { font-family: Vazirmatn, Tahoma, Arial, sans-serif; background: #f8f9fa; }
        .test-card { background: white; border-radius: 15px; padding: 1.5rem; margin-bottom: 1rem; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .status-ok { color: #28a745; }
        .status-error { color: #dc3545; }
        .overall-status { text-align: center; padding: 2rem; border-radius: 15px; margin-bottom: 2rem; }
        .overall-ok { background: linear-gradient(135deg, #28a745, #20c997); color: white; }
        .overall-error { background: linear-gradient(135deg, #dc3545, #fd7e14); color: white; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="overall-status <?php echo $overall_ok ? 'overall-ok' : 'overall-error'; ?>">
            <h2>
                <i class="fas fa-<?php echo $overall_ok ? 'check-circle' : 'times-circle'; ?> me-2"></i>
                <?php echo $overall_ok ? 'سیستم سالم است' : 'مشکلی در سیستم وجود دارد'; ?>
            </h2>
            <p class="mb-0">تست سریع سیستم - <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
        
        <div class="row">
            <div class="col-12">
                <h3 class="mb-4"><i class="fas fa-list me-2"></i>نتایج تست</h3>
                
                <?php foreach ($tests as $test): ?>
                <div class="test-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-1">
                                <i class="fas fa-<?php echo $test['status'] === 'ok' ? 'check-circle status-ok' : 'times-circle status-error'; ?> me-2"></i>
                                <?php echo $test['name']; ?>
                            </h5>
                            <p class="mb-0 text-muted"><?php echo $test['message']; ?></p>
                        </div>
                        <span class="badge bg-<?php echo $test['status'] === 'ok' ? 'success' : 'danger'; ?> fs-6">
                            <?php echo $test['status'] === 'ok' ? 'موفق' : 'خطا'; ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <div class="text-center mt-4">
                    <a href="system_test.php" class="btn btn-primary me-2">
                        <i class="fas fa-vial me-2"></i>تست کامل سیستم
                    </a>
                    <button class="btn btn-success" onclick="location.reload()">
                        <i class="fas fa-sync-alt me-2"></i>تست مجدد
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>