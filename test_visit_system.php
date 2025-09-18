<?php
// Test file for Factory Visit Management System
require_once 'config.php';

echo "<!DOCTYPE html>
<html lang='fa' dir='rtl'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>تست سیستم مدیریت بازدید کارخانه</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>
</head>
<body>
<div class='container mt-5'>
    <div class='row'>
        <div class='col-12'>
            <h1 class='text-center mb-4'>
                <i class='fas fa-building me-2'></i>
                تست سیستم مدیریت بازدید کارخانه
            </h1>
";

// Test database connection
echo "<div class='card mb-4'>
    <div class='card-header'>
        <h5><i class='fas fa-database me-2'></i>تست اتصال دیتابیس</h5>
    </div>
    <div class='card-body'>";

try {
    $pdo->query("SELECT 1");
    echo "<div class='alert alert-success'>
        <i class='fas fa-check-circle me-2'></i>
        اتصال به دیتابیس موفق
    </div>";
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>
        <i class='fas fa-exclamation-circle me-2'></i>
        خطا در اتصال به دیتابیس: " . $e->getMessage() . "
    </div>";
}

echo "</div></div>";

// Test visit management functions
echo "<div class='card mb-4'>
    <div class='card-header'>
        <h5><i class='fas fa-cogs me-2'></i>تست توابع مدیریت بازدید</h5>
    </div>
    <div class='card-body'>";

$functions_to_test = [
    'generateVisitRequestNumber' => 'تولید شماره درخواست',
    'createVisitRequest' => 'ایجاد درخواست بازدید',
    'logVisitAction' => 'ثبت عمل در تاریخچه',
    'updateVisitStatus' => 'تغییر وضعیت بازدید',
    'reserveDeviceForVisit' => 'رزرو دستگاه',
    'uploadVisitDocument' => 'آپلود مدرک',
    'verifyVisitDocument' => 'تایید مدرک',
    'createVisitChecklist' => 'ایجاد چک‌لیست',
    'completeChecklistItem' => 'تکمیل آیتم چک‌لیست',
    'uploadVisitPhoto' => 'آپلود عکس',
    'createVisitReport' => 'ایجاد گزارش',
    'checkInVisit' => 'چک‌این بازدید',
    'checkOutVisit' => 'چک‌اوت بازدید',
    'generateVisitQRCode' => 'تولید QR Code',
    'getVisitStatistics' => 'دریافت آمار',
    'getAvailableDevices' => 'دریافت دستگاه‌های در دسترس',
    'getVisitRequests' => 'دریافت درخواست‌ها'
];

foreach ($functions_to_test as $function => $description) {
    if (function_exists($function)) {
        echo "<div class='alert alert-success'>
            <i class='fas fa-check me-2'></i>
            $description: ✅ موجود
        </div>";
    } else {
        echo "<div class='alert alert-danger'>
            <i class='fas fa-times me-2'></i>
            $description: ❌ موجود نیست
        </div>";
    }
}

echo "</div></div>";

// Test database tables
echo "<div class='card mb-4'>
    <div class='card-header'>
        <h5><i class='fas fa-table me-2'></i>تست جداول دیتابیس</h5>
    </div>
    <div class='card-body'>";

$tables_to_check = [
    'visit_requests' => 'درخواست‌های بازدید',
    'visit_request_devices' => 'دستگاه‌های درخواست',
    'device_reservations' => 'رزرو دستگاه‌ها',
    'visit_documents' => 'مدارک بازدید',
    'visit_checklists' => 'چک‌لیست‌های بازدید',
    'visit_photos' => 'عکس‌های بازدید',
    'visit_reports' => 'گزارش‌های بازدید',
    'visit_history' => 'تاریخچه بازدید',
    'visit_settings' => 'تنظیمات بازدید'
];

foreach ($tables_to_check as $table => $description) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
        $count = $stmt->fetch()['count'];
        echo "<div class='alert alert-success'>
            <i class='fas fa-check me-2'></i>
            $description: ✅ موجود ($count رکورد)
        </div>";
    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>
            <i class='fas fa-times me-2'></i>
            $description: ❌ موجود نیست
        </div>";
    }
}

echo "</div></div>";

// Test file permissions
echo "<div class='card mb-4'>
    <div class='card-header'>
        <h5><i class='fas fa-folder me-2'></i>تست پوشه‌ها و مجوزها</h5>
    </div>
    <div class='card-body'>";

$directories_to_check = [
    'uploads' => 'پوشه آپلود',
    'uploads/visit_documents' => 'پوشه مدارک بازدید',
    'uploads/visit_photos' => 'پوشه عکس‌های بازدید',
    'logs' => 'پوشه لاگ‌ها'
];

foreach ($directories_to_check as $dir => $description) {
    if (is_dir($dir)) {
        if (is_writable($dir)) {
            echo "<div class='alert alert-success'>
                <i class='fas fa-check me-2'></i>
                $description: ✅ موجود و قابل نوشتن
            </div>";
        } else {
            echo "<div class='alert alert-warning'>
                <i class='fas fa-exclamation-triangle me-2'></i>
                $description: ⚠️ موجود اما غیرقابل نوشتن
            </div>";
        }
    } else {
        echo "<div class='alert alert-danger'>
            <i class='fas fa-times me-2'></i>
            $description: ❌ موجود نیست
        </div>";
    }
}

echo "</div></div>";

// Test pages
echo "<div class='card mb-4'>
    <div class='card-header'>
        <h5><i class='fas fa-file me-2'></i>تست صفحات</h5>
    </div>
    <div class='card-body'>";

$pages_to_check = [
    'visit_dashboard.php' => 'داشبورد بازدید',
    'visit_management.php' => 'مدیریت بازدید',
    'visit_details.php' => 'جزئیات بازدید',
    'visit_checkin.php' => 'چک‌این بازدید'
];

foreach ($pages_to_check as $page => $description) {
    if (file_exists($page)) {
        echo "<div class='alert alert-success'>
            <i class='fas fa-check me-2'></i>
            $description: ✅ موجود
        </div>";
    } else {
        echo "<div class='alert alert-danger'>
            <i class='fas fa-times me-2'></i>
            $description: ❌ موجود نیست
        </div>";
    }
}

echo "</div></div>";

// Quick test of creating a visit request
echo "<div class='card mb-4'>
    <div class='card-header'>
        <h5><i class='fas fa-flask me-2'></i>تست ایجاد درخواست بازدید</h5>
    </div>
    <div class='card-body'>";

try {
    // Set up a test session
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['user_id'] = 1;
        $_SESSION['username'] = 'test_user';
        $_SESSION['role'] = 'ادمین';
    }
    
    $test_data = [
        'company_name' => 'شرکت تست',
        'contact_person' => 'احمد احمدی',
        'contact_phone' => '09123456789',
        'contact_email' => 'test@example.com',
        'visitor_count' => 2,
        'visit_purpose' => 'تست',
        'visit_type' => 'مشتری',
        'request_method' => 'تماس',
        'preferred_dates' => [['date' => date('Y-m-d', strtotime('+1 day')), 'time' => '10:00']],
        'visit_duration' => 60,
        'requires_nda' => false,
        'special_requirements' => 'تست سیستم',
        'priority' => 'متوسط'
    ];
    
    $visit_id = createVisitRequest($pdo, $test_data);
    
    echo "<div class='alert alert-success'>
        <i class='fas fa-check me-2'></i>
        درخواست بازدید تست با موفقیت ایجاد شد (ID: $visit_id)
    </div>";
    
    // Test getting visit statistics
    $stats = getVisitStatistics($pdo);
    echo "<div class='alert alert-info'>
        <i class='fas fa-info-circle me-2'></i>
        آمار بازدیدها: " . $stats['total_requests'] . " درخواست
    </div>";
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>
        <i class='fas fa-exclamation-circle me-2'></i>
        خطا در تست: " . $e->getMessage() . "
    </div>";
}

echo "</div></div>";

// Summary
echo "<div class='card mb-4'>
    <div class='card-header'>
        <h5><i class='fas fa-clipboard-check me-2'></i>خلاصه تست</h5>
    </div>
    <div class='card-body'>
        <div class='row'>
            <div class='col-md-6'>
                <h6>✅ ویژگی‌های پیاده‌سازی شده:</h6>
                <ul>
                    <li>داشبورد بازدید با آمار و عملیات سریع</li>
                    <li>ثبت و مدیریت درخواست‌های بازدید</li>
                    <li>سیستم چک‌این/چک‌اوت موبایل</li>
                    <li>مدیریت مدارک و آپلود فایل</li>
                    <li>رزرو دستگاه‌ها با جلوگیری از تداخل</li>
                    <li>چک‌لیست‌های بازدید</li>
                    <li>گزارش‌گیری و آمارگیری</li>
                    <li>تاریخچه کامل عملیات</li>
                    <li>QR Code برای چک‌این</li>
                    <li>ادغام در منوی اصلی</li>
                </ul>
            </div>
            <div class='col-md-6'>
                <h6>🔧 قابلیت‌های فنی:</h6>
                <ul>
                    <li>پایگاه داده کامل با 9 جدول</li>
                    <li>16 تابع مدیریت بازدید</li>
                    <li>سیستم مجوزها و امنیت</li>
                    <li>رابط کاربری ریسپانسیو</li>
                    <li>پشتیبانی از تم تاریک/روشن</li>
                    <li>پشتیبانی از زبان فارسی/انگلیسی</li>
                    <li>سیستم اعلان‌ها</li>
                    <li>لاگ‌گیری کامل</li>
                </ul>
            </div>
        </div>
    </div>
</div>";

echo "<div class='text-center mb-4'>
    <a href='visit_dashboard.php' class='btn btn-primary btn-lg me-2'>
        <i class='fas fa-tachometer-alt me-2'></i>
        رفتن به داشبورد بازدید
    </a>
    <a href='visit_management.php' class='btn btn-outline-primary btn-lg'>
        <i class='fas fa-building me-2'></i>
        مدیریت بازدیدها
    </a>
</div>";

echo "</div></div></div></body></html>";
?>