<?php
// Complete test for Factory Visit Management System
require_once 'config.php';

echo "<!DOCTYPE html>
<html lang='fa' dir='rtl'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>تست کامل سیستم مدیریت بازدید کارخانه</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>
    <style>
        .test-section { margin-bottom: 30px; }
        .test-item { margin-bottom: 15px; padding: 10px; border-radius: 8px; }
        .test-success { background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .test-error { background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .test-warning { background-color: #fff3cd; border: 1px solid #ffeaa7; color: #856404; }
        .feature-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px; }
        .feature-card { border: 1px solid #dee2e6; border-radius: 10px; padding: 20px; background: white; }
        .status-badge { padding: 5px 10px; border-radius: 15px; font-size: 0.8rem; font-weight: bold; }
        .status-complete { background: #28a745; color: white; }
        .status-partial { background: #ffc107; color: #212529; }
        .status-missing { background: #dc3545; color: white; }
    </style>
</head>
<body>
<div class='container mt-5'>
    <div class='row'>
        <div class='col-12'>
            <h1 class='text-center mb-4'>
                <i class='fas fa-building me-2'></i>
                تست کامل سیستم مدیریت بازدید کارخانه
            </h1>
";

// Test 1: Database Connection
echo "<div class='test-section'>
    <h3><i class='fas fa-database me-2'></i>تست اتصال دیتابیس</h3>";

try {
    $pdo->query("SELECT 1");
    echo "<div class='test-item test-success'>
        <i class='fas fa-check-circle me-2'></i>
        اتصال به دیتابیس موفق
    </div>";
} catch (Exception $e) {
    echo "<div class='test-item test-error'>
        <i class='fas fa-exclamation-circle me-2'></i>
        خطا در اتصال به دیتابیس: " . $e->getMessage() . "
    </div>";
}

echo "</div>";

// Test 2: Visit Management Functions
echo "<div class='test-section'>
    <h3><i class='fas fa-cogs me-2'></i>تست توابع مدیریت بازدید</h3>";

$visit_functions = [
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

$function_count = 0;
$total_functions = count($visit_functions);

foreach ($visit_functions as $function => $description) {
    if (function_exists($function)) {
        echo "<div class='test-item test-success'>
            <i class='fas fa-check me-2'></i>
            $description: ✅ موجود
        </div>";
        $function_count++;
    } else {
        echo "<div class='test-item test-error'>
            <i class='fas fa-times me-2'></i>
            $description: ❌ موجود نیست
        </div>";
    }
}

echo "</div>";

// Test 3: Database Tables
echo "<div class='test-section'>
    <h3><i class='fas fa-table me-2'></i>تست جداول دیتابیس</h3>";

$visit_tables = [
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

$table_count = 0;
$total_tables = count($visit_tables);

foreach ($visit_tables as $table => $description) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
        $count = $stmt->fetch()['count'];
        echo "<div class='test-item test-success'>
            <i class='fas fa-check me-2'></i>
            $description: ✅ موجود ($count رکورد)
        </div>";
        $table_count++;
    } catch (Exception $e) {
        echo "<div class='test-item test-error'>
            <i class='fas fa-times me-2'></i>
            $description: ❌ موجود نیست
        </div>";
    }
}

echo "</div>";

// Test 4: Pages
echo "<div class='test-section'>
    <h3><i class='fas fa-file me-2'></i>تست صفحات</h3>";

$visit_pages = [
    'visit_dashboard.php' => 'داشبورد بازدید',
    'visit_management.php' => 'مدیریت بازدید',
    'visit_details.php' => 'جزئیات بازدید',
    'visit_checkin.php' => 'چک‌این بازدید'
];

$page_count = 0;
$total_pages = count($visit_pages);

foreach ($visit_pages as $page => $description) {
    if (file_exists($page)) {
        echo "<div class='test-item test-success'>
            <i class='fas fa-check me-2'></i>
            $description: ✅ موجود
        </div>";
        $page_count++;
    } else {
        echo "<div class='test-item test-error'>
            <i class='fas fa-times me-2'></i>
            $description: ❌ موجود نیست
        </div>";
    }
}

echo "</div>";

// Test 5: Directories
echo "<div class='test-section'>
    <h3><i class='fas fa-folder me-2'></i>تست پوشه‌ها</h3>";

$directories = [
    'uploads' => 'پوشه آپلود',
    'uploads/visit_documents' => 'پوشه مدارک بازدید',
    'uploads/visit_photos' => 'پوشه عکس‌های بازدید',
    'logs' => 'پوشه لاگ‌ها'
];

foreach ($directories as $dir => $description) {
    if (is_dir($dir)) {
        if (is_writable($dir)) {
            echo "<div class='test-item test-success'>
                <i class='fas fa-check me-2'></i>
                $description: ✅ موجود و قابل نوشتن
            </div>";
        } else {
            echo "<div class='test-item test-warning'>
                <i class='fas fa-exclamation-triangle me-2'></i>
                $description: ⚠️ موجود اما غیرقابل نوشتن
            </div>";
        }
    } else {
        echo "<div class='test-item test-error'>
            <i class='fas fa-times me-2'></i>
            $description: ❌ موجود نیست
        </div>";
    }
}

echo "</div>";

// Test 6: Create Sample Visit Request
echo "<div class='test-section'>
    <h3><i class='fas fa-flask me-2'></i>تست ایجاد درخواست بازدید نمونه</h3>";

try {
    // Set up test session
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['user_id'] = 1;
        $_SESSION['username'] = 'test_user';
        $_SESSION['role'] = 'ادمین';
    }
    
    $test_data = [
        'company_name' => 'شرکت تست سیستم',
        'contact_person' => 'احمد احمدی',
        'contact_phone' => '09123456789',
        'contact_email' => 'test@example.com',
        'visitor_count' => 3,
        'visit_purpose' => 'تست',
        'visit_type' => 'مشتری',
        'request_method' => 'تماس',
        'preferred_dates' => [['date' => date('Y-m-d', strtotime('+1 day')), 'time' => '10:00']],
        'visit_duration' => 90,
        'requires_nda' => true,
        'special_requirements' => 'تست کامل سیستم مدیریت بازدید',
        'priority' => 'بالا'
    ];
    
    $visit_id = createVisitRequest($pdo, $test_data);
    
    echo "<div class='test-item test-success'>
        <i class='fas fa-check-circle me-2'></i>
        درخواست بازدید تست با موفقیت ایجاد شد (ID: $visit_id)
    </div>";
    
    // Test getting visit statistics
    $stats = getVisitStatistics($pdo);
    echo "<div class='test-item test-success'>
        <i class='fas fa-chart-bar me-2'></i>
        آمار بازدیدها: " . $stats['total_requests'] . " درخواست کل
    </div>";
    
} catch (Exception $e) {
    echo "<div class='test-item test-error'>
        <i class='fas fa-exclamation-circle me-2'></i>
        خطا در تست: " . $e->getMessage() . "
    </div>";
}

echo "</div>";

// Feature Status Overview
echo "<div class='test-section'>
    <h3><i class='fas fa-clipboard-check me-2'></i>وضعیت ویژگی‌ها</h3>
    <div class='feature-grid'>";

$features = [
    [
        'title' => 'داشبورد بازدید',
        'description' => 'آمار و عملیات سریع',
        'status' => $page_count >= 4 ? 'complete' : 'missing',
        'icon' => 'fas fa-tachometer-alt'
    ],
    [
        'title' => 'ثبت درخواست بازدید',
        'description' => 'فرم کامل با مدارک',
        'status' => $function_count >= 16 ? 'complete' : 'partial',
        'icon' => 'fas fa-plus-circle'
    ],
    [
        'title' => 'مدیریت دستگاه‌ها',
        'description' => 'رزرو و جلوگیری از تداخل',
        'status' => $table_count >= 9 ? 'complete' : 'partial',
        'icon' => 'fas fa-cogs'
    ],
    [
        'title' => 'چک‌این موبایل',
        'description' => 'QR Code و موبایل',
        'status' => file_exists('visit_checkin.php') ? 'complete' : 'missing',
        'icon' => 'fas fa-qrcode'
    ],
    [
        'title' => 'مدارک و آپلود',
        'description' => 'آپلود و تایید مدارک',
        'status' => $table_count >= 9 ? 'complete' : 'missing',
        'icon' => 'fas fa-file-upload'
    ],
    [
        'title' => 'چک‌لیست‌ها',
        'description' => 'قبل، حین و بعد از بازدید',
        'status' => $table_count >= 9 ? 'complete' : 'missing',
        'icon' => 'fas fa-list-check'
    ],
    [
        'title' => 'گزارش‌گیری',
        'description' => 'آمار و گزارش‌های مدیریتی',
        'status' => $function_count >= 16 ? 'complete' : 'partial',
        'icon' => 'fas fa-chart-pie'
    ],
    [
        'title' => 'تاریخچه و لاگ',
        'description' => 'ردیابی کامل عملیات',
        'status' => $table_count >= 9 ? 'complete' : 'missing',
        'icon' => 'fas fa-history'
    ]
];

foreach ($features as $feature) {
    $status_class = $feature['status'] === 'complete' ? 'status-complete' : 
                   ($feature['status'] === 'partial' ? 'status-partial' : 'status-missing');
    $status_text = $feature['status'] === 'complete' ? 'تکمیل شده' : 
                  ($feature['status'] === 'partial' ? 'نیمه‌تکمیل' : 'مفقود');
    
    echo "<div class='feature-card'>
        <div class='d-flex align-items-center mb-3'>
            <i class='{$feature['icon']} fa-2x text-primary me-3'></i>
            <div>
                <h5 class='mb-1'>{$feature['title']}</h5>
                <span class='status-badge $status_class'>$status_text</span>
            </div>
        </div>
        <p class='text-muted mb-0'>{$feature['description']}</p>
    </div>";
}

echo "</div></div>";

// Summary
$overall_score = round((($function_count / $total_functions) + ($table_count / $total_tables) + ($page_count / $total_pages)) / 3 * 100);

echo "<div class='test-section'>
    <div class='card'>
        <div class='card-header'>
            <h5><i class='fas fa-trophy me-2'></i>خلاصه تست</h5>
        </div>
        <div class='card-body'>
            <div class='row'>
                <div class='col-md-6'>
                    <h6>📊 آمار کلی:</h6>
                    <ul class='list-unstyled'>
                        <li><i class='fas fa-check text-success me-2'></i>توابع: $function_count/$total_functions</li>
                        <li><i class='fas fa-check text-success me-2'></i>جداول: $table_count/$total_tables</li>
                        <li><i class='fas fa-check text-success me-2'></i>صفحات: $page_count/$total_pages</li>
                    </ul>
                </div>
                <div class='col-md-6'>
                    <h6>🎯 امتیاز کلی: $overall_score%</h6>
                    <div class='progress mb-3'>
                        <div class='progress-bar' style='width: $overall_score%'></div>
                    </div>
                    <p class='text-muted'>سیستم مدیریت بازدید کارخانه " . ($overall_score >= 80 ? 'آماده استفاده' : 'نیاز به تکمیل') . "</p>
                </div>
            </div>
        </div>
    </div>
</div>";

// Action Buttons
echo "<div class='text-center mb-5'>
    <a href='visit_dashboard.php' class='btn btn-primary btn-lg me-3'>
        <i class='fas fa-tachometer-alt me-2'></i>
        داشبورد بازدید
    </a>
    <a href='visit_management.php' class='btn btn-outline-primary btn-lg me-3'>
        <i class='fas fa-building me-2'></i>
        مدیریت بازدیدها
    </a>
    <a href='visit_checkin.php' class='btn btn-outline-success btn-lg'>
        <i class='fas fa-qrcode me-2'></i>
        چک‌این موبایل
    </a>
</div>";

echo "</div></div></div></body></html>";
?>