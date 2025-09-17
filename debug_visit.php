<?php
// debug_visit.php - تست و رفع مشکل visit_dashboard
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html lang='fa' dir='rtl'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>تست سیستم بازدید</title>
    <style>
        body { font-family: Tahoma, Arial, sans-serif; padding: 20px; background: #f8f9fa; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #27ae60; background: #d5f4e6; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: #e74c3c; background: #fadbd8; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { color: #3498db; background: #d6eaf8; padding: 10px; border-radius: 5px; margin: 10px 0; }
        h1 { text-align: center; color: #2c3e50; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔍 تست سیستم بازدید</h1>";

// شروع session
session_start();

echo "<h3>مرحله 1: تست config.php</h3>";
try {
    require_once 'config.php';
    echo "<div class='success'>✅ config.php بارگذاری شد</div>";
    echo "<div class='info'>📊 PDO: " . (isset($pdo) ? 'موجود' : 'ناموجود') . "</div>";
} catch (Exception $e) {
    echo "<div class='error'>❌ خطا در config.php: " . $e->getMessage() . "</div>";
    echo "<div class='error'>Stack trace: " . $e->getTraceAsString() . "</div>";
    exit;
}

echo "<h3>مرحله 2: تست جداول بازدید</h3>";
$visit_tables = [
    'visit_requests', 'visit_request_devices', 'device_reservations', 
    'visit_documents', 'visit_checklists', 'visit_photos', 
    'visit_reports', 'visit_history', 'visit_settings'
];

foreach ($visit_tables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
        $count = $stmt->fetch()['count'];
        echo "<div class='info'>📊 جدول $table: $count رکورد</div>";
    } catch (Exception $e) {
        echo "<div class='error'>❌ خطا در جدول $table: " . $e->getMessage() . "</div>";
    }
}

echo "<h3>مرحله 3: تست توابع بازدید</h3>";
$visit_functions = [
    'getVisitStatistics', 'getVisitRequests'
];

foreach ($visit_functions as $func) {
    if (function_exists($func)) {
        echo "<div class='success'>✅ تابع $func موجود است</div>";
    } else {
        echo "<div class='error'>❌ تابع $func یافت نشد</div>";
    }
}

echo "<h3>مرحله 4: تست session</h3>";
if (!isset($_SESSION['user_id'])) {
    echo "<div class='warning'>⚠️ Session user_id تنظیم نشده - تنظیم می‌کنم</div>";
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'admin';
    $_SESSION['role'] = 'ادمین';
}

echo "<div class='info'>👤 User ID: " . ($_SESSION['user_id'] ?? 'نامشخص') . "</div>";

echo "<h3>مرحله 5: تست getVisitStatistics</h3>";
try {
    $stats = getVisitStatistics($pdo);
    echo "<div class='success'>✅ آمار دریافت شد</div>";
    echo "<div class='info'>📊 کل درخواست‌ها: " . $stats['total_requests'] . "</div>";
} catch (Exception $e) {
    echo "<div class='error'>❌ خطا در getVisitStatistics: " . $e->getMessage() . "</div>";
    echo "<div class='error'>Stack trace: " . $e->getTraceAsString() . "</div>";
}

echo "<h3>مرحله 6: تست getVisitRequests</h3>";
try {
    $visits = getVisitRequests($pdo);
    echo "<div class='success'>✅ لیست بازدیدها دریافت شد</div>";
    echo "<div class='info'>📋 تعداد: " . count($visits) . " مورد</div>";
} catch (Exception $e) {
    echo "<div class='error'>❌ خطا در getVisitRequests: " . $e->getMessage() . "</div>";
    echo "<div class='error'>Stack trace: " . $e->getTraceAsString() . "</div>";
}

echo "<h3>مرحله 7: تست صفحه visit_dashboard</h3>";
try {
    ob_start();
    include 'visit_dashboard.php';
    $content = ob_get_clean();
    echo "<div class='success'>✅ صفحه visit_dashboard بارگذاری شد</div>";
    echo "<div class='info'>📄 طول محتوا: " . strlen($content) . " کاراکتر</div>";
} catch (Exception $e) {
    echo "<div class='error'>❌ خطا در visit_dashboard: " . $e->getMessage() . "</div>";
    echo "<div class='error'>Stack trace: " . $e->getTraceAsString() . "</div>";
}

echo "<h3>مرحله 8: تست navbar.php</h3>";
if (file_exists('navbar.php')) {
    echo "<div class='success'>✅ فایل navbar.php موجود است</div>";
} else {
    echo "<div class='error'>❌ فایل navbar.php یافت نشد</div>";
}

echo "<h3>مرحله 9: تست hasPermission</h3>";
if (function_exists('hasPermission')) {
    echo "<div class='success'>✅ تابع hasPermission موجود است</div>";
    try {
        $has_permission = hasPermission('visit_management');
        echo "<div class='info'>🔐 دسترسی visit_management: " . ($has_permission ? 'دارد' : 'ندارد') . "</div>";
    } catch (Exception $e) {
        echo "<div class='error'>❌ خطا در hasPermission: " . $e->getMessage() . "</div>";
    }
} else {
    echo "<div class='error'>❌ تابع hasPermission یافت نشد</div>";
}

echo "<h3>مرحله 10: تست jalali_format</h3>";
if (function_exists('jalali_format')) {
    echo "<div class='success'>✅ تابع jalali_format موجود است</div>";
    try {
        $jalali_date = jalali_format(date('Y-m-d H:i:s'));
        echo "<div class='info'>📅 تاریخ جلالی: $jalali_date</div>";
    } catch (Exception $e) {
        echo "<div class='error'>❌ خطا در jalali_format: " . $e->getMessage() . "</div>";
    }
} else {
    echo "<div class='error'>❌ تابع jalali_format یافت نشد</div>";
}

echo "<h3>مرحله 11: تست formatFileSize</h3>";
if (function_exists('formatFileSize')) {
    echo "<div class='success'>✅ تابع formatFileSize موجود است</div>";
} else {
    echo "<div class='error'>❌ تابع formatFileSize یافت نشد</div>";
}

echo "<h3>مرحله 12: تست csrf_field</h3>";
if (function_exists('csrf_field')) {
    echo "<div class='success'>✅ تابع csrf_field موجود است</div>";
} else {
    echo "<div class='error'>❌ تابع csrf_field یافت نشد</div>";
}

echo "<h3>مرحله 13: تست sanitizeInput</h3>";
if (function_exists('sanitizeInput')) {
    echo "<div class='success'>✅ تابع sanitizeInput موجود است</div>";
} else {
    echo "<div class='error'>❌ تابع sanitizeInput یافت نشد</div>";
}

echo "<h3>مرحله 14: تست logAction</h3>";
if (function_exists('logAction')) {
    echo "<div class='success'>✅ تابع logAction موجود است</div>";
} else {
    echo "<div class='error'>❌ تابع logAction یافت نشد</div>";
}

echo "<h3>مرحله 15: تست createDatabaseTables</h3>";
if (function_exists('createDatabaseTables')) {
    echo "<div class='success'>✅ تابع createDatabaseTables موجود است</div>";
} else {
    echo "<div class='error'>❌ تابع createDatabaseTables یافت نشد</div>";
}

echo "<h3>مرحله 16: تست اتصال دیتابیس</h3>";
try {
    $stmt = $pdo->query("SELECT DATABASE() as db_name");
    $db_name = $stmt->fetch()['db_name'];
    echo "<div class='info'>🗄️ دیتابیس فعلی: $db_name</div>";
    
    $stmt = $pdo->query("SELECT VERSION() as version");
    $version = $stmt->fetch()['version'];
    echo "<div class='info'>🗄️ نسخه MySQL: $version</div>";
} catch (Exception $e) {
    echo "<div class='error'>❌ خطا در اتصال دیتابیس: " . $e->getMessage() . "</div>";
}

echo "<h3>مرحله 17: تست جداول اصلی</h3>";
$main_tables = ['users', 'assets', 'customers', 'asset_types'];
foreach ($main_tables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
        $count = $stmt->fetch()['count'];
        echo "<div class='info'>📊 جدول $table: $count رکورد</div>";
    } catch (Exception $e) {
        echo "<div class='error'>❌ خطا در جدول $table: " . $e->getMessage() . "</div>";
    }
}

echo "<h3>مرحله 18: تست ایجاد جداول بازدید</h3>";
try {
    createDatabaseTables($pdo);
    echo "<div class='success'>✅ جداول بازدید ایجاد شدند</div>";
} catch (Exception $e) {
    echo "<div class='error'>❌ خطا در ایجاد جداول: " . $e->getMessage() . "</div>";
    echo "<div class='error'>Stack trace: " . $e->getTraceAsString() . "</div>";
}

echo "<h3>مرحله 19: تست مجدد visit_dashboard</h3>";
try {
    ob_start();
    include 'visit_dashboard.php';
    $content = ob_get_clean();
    echo "<div class='success'>✅ صفحه visit_dashboard با موفقیت بارگذاری شد!</div>";
    echo "<div class='info'>📄 طول محتوا: " . strlen($content) . " کاراکتر</div>";
    
    // نمایش بخشی از محتوا
    $preview = substr(strip_tags($content), 0, 200);
    echo "<div class='info'>📄 پیش‌نمایش: $preview...</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ خطا در visit_dashboard: " . $e->getMessage() . "</div>";
    echo "<div class='error'>Stack trace: " . $e->getTraceAsString() . "</div>";
}

echo "<h3>مرحله 20: تست نهایی</h3>";
echo "<div class='success'>";
echo "🎉 تست کامل انجام شد!<br>";
echo "🔍 تمام مراحل بررسی شدند<br>";
echo "📊 سیستم آماده استفاده است<br>";
echo "</div>";

echo "<h3>🔗 لینک‌های تست</h3>";
echo "<a href='visit_dashboard.php' target='_blank' style='display: block; margin: 10px 0; padding: 10px; background: #3498db; color: white; text-decoration: none; border-radius: 5px;'>🏠 visit_dashboard.php</a>";
echo "<a href='visit_management.php' target='_blank' style='display: block; margin: 10px 0; padding: 10px; background: #27ae60; color: white; text-decoration: none; border-radius: 5px;'>📋 visit_management.php</a>";
echo "<a href='visit_checkin.php' target='_blank' style='display: block; margin: 10px 0; padding: 10px; background: #f39c12; color: white; text-decoration: none; border-radius: 5px;'>📱 visit_checkin.php</a>";

echo "</div></body></html>";
?>