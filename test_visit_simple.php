<?php
// test_visit_simple.php - تست ساده سیستم بازدید
session_start();

// تنظیم session برای تست
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['role'] = 'ادمین';

echo "<h1>تست سیستم بازدید کارخانه</h1>";

// تست config.php
try {
    require_once 'config.php';
    echo "<p style='color: green;'>✅ config.php بارگذاری شد</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ خطا در config.php: " . $e->getMessage() . "</p>";
    exit();
}

// تست اتصال دیتابیس
try {
    $stmt = $pdo->query("SELECT 1");
    echo "<p style='color: green;'>✅ اتصال دیتابیس موفق</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ خطا در اتصال دیتابیس: " . $e->getMessage() . "</p>";
}

// تست ایجاد جداول
try {
    if (function_exists('createDatabaseTables')) {
        createDatabaseTables($pdo);
        echo "<p style='color: green;'>✅ جداول ایجاد شدند</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ تابع createDatabaseTables یافت نشد</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ خطا در ایجاد جداول: " . $e->getMessage() . "</p>";
}

// تست توابع بازدید
$visit_functions = [
    'generateVisitRequestNumber',
    'createVisitRequest',
    'updateVisitStatus',
    'reserveDeviceForVisit',
    'uploadVisitDocument',
    'createVisitChecklist',
    'uploadVisitPhoto',
    'createVisitReport',
    'checkInVisit',
    'checkOutVisit',
    'generateVisitQRCode',
    'getVisitStatistics',
    'getAvailableDevices',
    'getVisitRequests',
    'logVisitAction',
    'verifyVisitDocument',
    'completeChecklistItem'
];

echo "<h2>تست توابع بازدید:</h2>";
foreach ($visit_functions as $func) {
    if (function_exists($func)) {
        echo "<p style='color: green;'>✅ $func</p>";
    } else {
        echo "<p style='color: red;'>❌ $func</p>";
    }
}

// تست صفحات
echo "<h2>تست صفحات:</h2>";
$pages = [
    'visit_dashboard.php' => 'داشبورد بازدیدها',
    'visit_management.php' => 'مدیریت بازدیدها',
    'visit_details.php' => 'جزئیات بازدید',
    'visit_checkin.php' => 'Check-in موبایل'
];

foreach ($pages as $page => $title) {
    if (file_exists($page)) {
        echo "<p style='color: green;'>✅ $title ($page)</p>";
    } else {
        echo "<p style='color: red;'>❌ $title ($page)</p>";
    }
}

echo "<h2>لینک‌های تست:</h2>";
echo "<p><a href='visit_dashboard.php' target='_blank'>داشبورد بازدیدها</a></p>";
echo "<p><a href='visit_management.php' target='_blank'>مدیریت بازدیدها</a></p>";
echo "<p><a href='visit_checkin.php' target='_blank'>Check-in موبایل</a></p>";
echo "<p><a href='test_visit_final.php' target='_blank'>تست نهایی</a></p>";
?>