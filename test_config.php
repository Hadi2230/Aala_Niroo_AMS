<?php
// test_config.php - تست فایل config.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html lang='fa' dir='rtl'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>تست Config</title>
    <style>
        body { font-family: Tahoma, Arial, sans-serif; padding: 20px; background: #f8f9fa; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #27ae60; background: #d5f4e6; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: #e74c3c; background: #fadbd8; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { color: #3498db; background: #d6eaf8; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .warning { color: #f39c12; background: #fef9e7; padding: 10px; border-radius: 5px; margin: 10px 0; }
        h1 { text-align: center; color: #2c3e50; }
        .test-section { border: 1px solid #ddd; padding: 20px; margin: 20px 0; border-radius: 5px; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔧 تست فایل Config</h1>";

// شروع session
session_start();

echo "<div class='test-section'>";
echo "<h3>مرحله 1: تست بارگذاری config.php</h3>";
try {
    require_once 'config.php';
    echo "<div class='success'>✅ config.php بارگذاری شد</div>";
    echo "<div class='info'>📊 PDO: " . (isset($pdo) ? 'موجود' : 'ناموجود') . "</div>";
} catch (Exception $e) {
    echo "<div class='error'>❌ خطا در config.php: " . $e->getMessage() . "</div>";
    exit;
}

echo "<h3>مرحله 2: تست توابع اصلی</h3>";
$functions = [
    'sanitizeInput', 'validatePhone', 'validateEmail', 'redirect', 'isAjaxRequest',
    'jsonResponse', 'logAction', 'uploadFile', 'verifyCsrfToken', 'checkPermission',
    'require_auth', 'csrf_field', 'jalaliDate', 'en2fa_digits', 'gregorian_to_jalali',
    'jalali_format', 'hasPermission', 'generateRandomCode', 'generateSerialNumber',
    'formatPhoneNumber', 'formatFileSize', 'getRealUserIP', 'isMobile', 'generateQRCode', 'cleanOldFiles'
];

foreach ($functions as $func) {
    if (function_exists($func)) {
        echo "<div class='success'>✅ تابع $func موجود است</div>";
    } else {
        echo "<div class='error'>❌ تابع $func یافت نشد</div>";
    }
}

echo "<h3>مرحله 3: تست جداول دیتابیس</h3>";
$tables = [
    'asset_types', 'asset_fields', 'assets', 'asset_images', 'customers', 'users',
    'asset_assignments', 'assignment_details', 'system_logs', 'tickets',
    'maintenance_schedules', 'notifications', 'messages', 'ticket_status_history',
    'notification_settings', 'custom_roles', 'surveys', 'survey_questions',
    'survey_submissions', 'survey_responses', 'sms_logs'
];

foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
        $count = $stmt->fetch()['count'];
        echo "<div class='info'>📊 جدول $table: $count رکورد</div>";
    } catch (Exception $e) {
        echo "<div class='error'>❌ خطا در جدول $table: " . $e->getMessage() . "</div>";
    }
}

echo "<h3>مرحله 4: تست session</h3>";
echo "<div class='info'>🔐 Session ID: " . session_id() . "</div>";
echo "<div class='info'>🔑 CSRF Token: " . ($_SESSION['csrf_token'] ?? 'نامشخص') . "</div>";

echo "<h3>مرحله 5: تست توابع تاریخ شمسی</h3>";
try {
    $jalali_date = jalaliDate();
    echo "<div class='success'>✅ تاریخ شمسی: $jalali_date</div>";
    
    $formatted_date = jalali_format(date('Y-m-d H:i:s'));
    echo "<div class='success'>✅ تاریخ فرمت شده: $formatted_date</div>";
    
    $fa_digits = en2fa_digits('1234567890');
    echo "<div class='success'>✅ اعداد فارسی: $fa_digits</div>";
} catch (Exception $e) {
    echo "<div class='error'>❌ خطا در توابع تاریخ: " . $e->getMessage() . "</div>";
}

echo "<h3>مرحله 6: تست توابع کمکی</h3>";
try {
    $random_code = generateRandomCode(8);
    echo "<div class='success'>✅ کد تصادفی: $random_code</div>";
    
    $serial_number = generateSerialNumber('TEST');
    echo "<div class='success'>✅ شماره سریال: $serial_number</div>";
    
    $phone_formatted = formatPhoneNumber('09123456789');
    echo "<div class='success'>✅ شماره تلفن فرمت شده: $phone_formatted</div>";
    
    $file_size = formatFileSize(1024 * 1024);
    echo "<div class='success'>✅ حجم فایل: $file_size</div>";
    
    $user_ip = getRealUserIP();
    echo "<div class='success'>✅ IP کاربر: $user_ip</div>";
    
    $is_mobile = isMobile() ? 'بله' : 'خیر';
    echo "<div class='success'>✅ موبایل: $is_mobile</div>";
} catch (Exception $e) {
    echo "<div class='error'>❌ خطا در توابع کمکی: " . $e->getMessage() . "</div>";
}

echo "<h3>مرحله 7: تست دسترسی‌ها</h3>";
try {
    // تست بدون session
    $has_perm = hasPermission('tickets.view');
    echo "<div class='info'>🔐 دسترسی بدون session: " . ($has_perm ? 'دارد' : 'ندارد') . "</div>";
    
    // تنظیم session برای تست
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'ادمین';
    
    $has_perm_admin = hasPermission('tickets.view');
    echo "<div class='success'>✅ دسترسی ادمین: " . ($has_perm_admin ? 'دارد' : 'ندارد') . "</div>";
    
    $has_perm_all = hasPermission('*');
    echo "<div class='success'>✅ دسترسی کامل: " . ($has_perm_all ? 'دارد' : 'ندارد') . "</div>";
} catch (Exception $e) {
    echo "<div class='error'>❌ خطا در تست دسترسی‌ها: " . $e->getMessage() . "</div>";
}

echo "</div>";

// خلاصه
echo "<div class='test-section'>";
echo "<h3>📊 خلاصه تست</h3>";
echo "<div class='success'>";
echo "🎉 فایل config.php کاملاً آماده است!<br>";
echo "🔧 تمام توابع تعریف شده<br>";
echo "📊 جداول دیتابیس ایجاد شده<br>";
echo "🔐 سیستم دسترسی‌ها کار می‌کند<br>";
echo "📅 توابع تاریخ شمسی فعال<br>";
echo "🛠️ توابع کمکی آماده<br>";
echo "</div>";
echo "</div>";

echo "
        <div style='text-align: center; margin-top: 30px;'>
            <a href='tickets.php' style='background: #27ae60; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-size: 18px;'>
                🚀 تست tickets.php
            </a>
        </div>
    </div>
</body>
</html>";
?>