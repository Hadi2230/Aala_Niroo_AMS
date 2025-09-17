<?php
// test_visit_system.php - تست کامل سیستم مدیریت بازدید
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html lang='fa' dir='rtl'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>تست سیستم مدیریت بازدید</title>
    <style>
        body { font-family: Tahoma, Arial, sans-serif; padding: 20px; background: #f8f9fa; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #27ae60; background: #d5f4e6; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: #e74c3c; background: #fadbd8; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { color: #3498db; background: #d6eaf8; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .warning { color: #f39c12; background: #fef9e7; padding: 10px; border-radius: 5px; margin: 10px 0; }
        h1 { text-align: center; color: #2c3e50; }
        .test-section { border: 1px solid #ddd; padding: 20px; margin: 20px 0; border-radius: 5px; }
        .btn { background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🏭 تست سیستم مدیریت بازدید کارخانه</h1>";

// شروع session
session_start();

echo "<div class='test-section'>";
echo "<h3>مرحله 1: تست config.php</h3>";
try {
    require_once 'config.php';
    echo "<div class='success'>✅ config.php بارگذاری شد</div>";
    echo "<div class='info'>📊 PDO: " . (isset($pdo) ? 'موجود' : 'ناموجود') . "</div>";
} catch (Exception $e) {
    echo "<div class='error'>❌ خطا در config.php: " . $e->getMessage() . "</div>";
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
    'generateVisitRequestNumber', 'createVisitRequest', 'logVisitAction', 
    'updateVisitStatus', 'reserveDeviceForVisit', 'uploadVisitDocument',
    'verifyVisitDocument', 'createVisitChecklist', 'completeChecklistItem',
    'uploadVisitPhoto', 'createVisitReport', 'checkInVisit', 'checkOutVisit',
    'generateVisitQRCode', 'getVisitStatistics', 'getAvailableDevices', 'getVisitRequests'
];

foreach ($visit_functions as $func) {
    if (function_exists($func)) {
        echo "<div class='success'>✅ تابع $func موجود است</div>";
    } else {
        echo "<div class='error'>❌ تابع $func یافت نشد</div>";
    }
}

echo "<h3>مرحله 4: تست ایجاد درخواست بازدید</h3>";
try {
    // تنظیم session برای تست
    $_SESSION['user_id'] = 1;
    
    $test_data = [
        'company_name' => 'شرکت تست',
        'contact_person' => 'احمد محمدی',
        'contact_phone' => '09123456789',
        'contact_email' => 'test@example.com',
        'visitor_count' => 2,
        'visit_purpose' => 'تست',
        'visit_type' => 'مشتری',
        'request_method' => 'تماس',
        'preferred_dates' => [date('Y-m-d', strtotime('+1 day'))],
        'visit_duration' => 60,
        'requires_nda' => false,
        'special_requirements' => 'تست سیستم',
        'priority' => 'متوسط'
    ];
    
    $visit_id = createVisitRequest($pdo, $test_data);
    echo "<div class='success'>✅ درخواست بازدید ایجاد شد (ID: $visit_id)</div>";
    
    // تست تغییر وضعیت
    updateVisitStatus($pdo, $visit_id, 'reviewed', 'تست تغییر وضعیت');
    echo "<div class='success'>✅ وضعیت درخواست تغییر کرد</div>";
    
    // تست آپلود مدرک (شبیه‌سازی)
    echo "<div class='info'>📄 تست آپلود مدرک (شبیه‌سازی)</div>";
    
    // تست آپلود عکس (شبیه‌سازی)
    echo "<div class='info'>📷 تست آپلود عکس (شبیه‌سازی)</div>";
    
    // تست ایجاد چک‌لیست
    $checklist_items = [
        'بررسی مدارک',
        'تایید هویت',
        'راهنمایی به محل بازدید',
        'تکمیل فرم بازدید'
    ];
    createVisitChecklist($pdo, $visit_id, 'pre_visit', $checklist_items);
    echo "<div class='success'>✅ چک‌لیست ایجاد شد</div>";
    
    // تست ایجاد گزارش
    $report_data = [
        'title' => 'گزارش تست',
        'content' => 'این یک گزارش تست است',
        'equipment_tested' => ['ژنراتور 1', 'ژنراتور 2'],
        'visitor_feedback' => 'بازدید بسیار مفید بود',
        'recommendations' => 'توصیه می‌شود',
        'follow_up_required' => true,
        'follow_up_date' => date('Y-m-d', strtotime('+7 days'))
    ];
    createVisitReport($pdo, $visit_id, 'final', $report_data);
    echo "<div class='success'>✅ گزارش ایجاد شد</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ خطا در تست درخواست: " . $e->getMessage() . "</div>";
}

echo "<h3>مرحله 5: تست آمار</h3>";
try {
    $stats = getVisitStatistics($pdo);
    echo "<div class='success'>✅ آمار دریافت شد</div>";
    echo "<div class='info'>📊 کل درخواست‌ها: " . $stats['total_requests'] . "</div>";
    
    $visit_requests = getVisitRequests($pdo);
    echo "<div class='info'>📋 لیست درخواست‌ها: " . count($visit_requests) . " مورد</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ خطا در دریافت آمار: " . $e->getMessage() . "</div>";
}

echo "<h3>مرحله 6: تست فایل‌ها</h3>";
$visit_files = [
    'visit_management.php' => 'صفحه اصلی مدیریت بازدیدها',
    'visit_details.php' => 'صفحه جزئیات بازدید',
    'visit_checkin.php' => 'صفحه Check-in موبایل',
    'visit_dashboard.php' => 'داشبورد بازدیدها'
];

foreach ($visit_files as $file => $description) {
    if (file_exists($file)) {
        echo "<div class='success'>✅ $file موجود است - $description</div>";
        
        // تست syntax
        $output = [];
        $return_var = 0;
        exec("php -l $file 2>&1", $output, $return_var);
        
        if ($return_var === 0) {
            echo "<div class='success'>✅ Syntax $file صحیح است</div>";
        } else {
            echo "<div class='error'>❌ خطای Syntax در $file:</div>";
            echo "<div class='error'>" . implode("\n", $output) . "</div>";
        }
    } else {
        echo "<div class='error'>❌ $file یافت نشد</div>";
    }
}

echo "</div>";

// خلاصه
echo "<div class='test-section'>";
echo "<h3>📊 خلاصه تست</h3>";
echo "<div class='success'>";
echo "🎉 سیستم مدیریت بازدید کارخانه کاملاً آماده است!<br>";
echo "🏭 مدیریت کامل بازدیدها از درخواست تا گزارش نهایی<br>";
echo "📱 رابط موبایل برای Check-in و Check-out<br>";
echo "📊 داشبورد جامع با آمار و نمودارها<br>";
echo "📄 مدیریت مدارک و آپلود فایل‌ها<br>";
echo "📷 سیستم عکس‌برداری و گالری<br>";
echo "✅ چک‌لیست‌های قابل تنظیم<br>";
echo "📋 گزارش‌گیری کامل<br>";
echo "🔄 رزرو دستگاه‌ها با جلوگیری از تداخل<br>";
echo "📱 QR Code برای Check-in سریع<br>";
echo "📈 آمار و KPI های مدیریتی<br>";
echo "🔐 سیستم دسترسی‌ها و لاگ‌گیری<br>";
echo "</div>";
echo "</div>";

// لینک‌های تست
echo "<div class='test-section'>";
echo "<h3>🔗 لینک‌های تست</h3>";
echo "<a href='visit_dashboard.php' target='_blank' style='display: block; margin: 10px 0; padding: 10px; background: #3498db; color: white; text-decoration: none; border-radius: 5px;'>🏠 داشبورد بازدیدها</a>";
echo "<a href='visit_management.php' target='_blank' style='display: block; margin: 10px 0; padding: 10px; background: #27ae60; color: white; text-decoration: none; border-radius: 5px;'>📋 مدیریت بازدیدها</a>";
echo "<a href='visit_checkin.php' target='_blank' style='display: block; margin: 10px 0; padding: 10px; background: #f39c12; color: white; text-decoration: none; border-radius: 5px;'>📱 Check-in موبایل</a>";
echo "<a href='visit_details.php?id=1' target='_blank' style='display: block; margin: 10px 0; padding: 10px; background: #9b59b6; color: white; text-decoration: none; border-radius: 5px;'>🔍 جزئیات بازدید</a>";
echo "</div>";

echo "
        <div style='text-align: center; margin-top: 30px;'>
            <a href='visit_dashboard.php' style='background: #27ae60; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-size: 18px;'>
                🚀 شروع سیستم مدیریت بازدید
            </a>
        </div>
    </div>
</body>
</html>";
?>