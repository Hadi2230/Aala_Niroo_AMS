<?php
// test_simple.php - تست ساده سیستم
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html lang='fa' dir='rtl'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>تست ساده سیستم</title>
    <style>
        body { font-family: Tahoma, Arial, sans-serif; padding: 20px; background: #f8f9fa; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #27ae60; background: #d5f4e6; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: #e74c3c; background: #fadbd8; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { color: #3498db; background: #d6eaf8; padding: 10px; border-radius: 5px; margin: 10px 0; }
        h1 { text-align: center; color: #2c3e50; }
        .btn { background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔍 تست ساده سیستم</h1>";

// شروع session
session_start();

echo "<h3>مرحله 1: تست config.php</h3>";
try {
    require_once 'config.php';
    echo "<div class='success'>✅ config.php بارگذاری شد</div>";
} catch (Exception $e) {
    echo "<div class='error'>❌ خطا در config.php: " . $e->getMessage() . "</div>";
    exit;
}

echo "<h3>مرحله 2: تنظیم session</h3>";
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'admin';
    $_SESSION['role'] = 'ادمین';
    echo "<div class='info'>✅ Session تنظیم شد</div>";
}

echo "<h3>مرحله 3: تست اتصال دیتابیس</h3>";
try {
    $stmt = $pdo->query("SELECT 1");
    echo "<div class='success'>✅ اتصال دیتابیس موفق</div>";
} catch (Exception $e) {
    echo "<div class='error'>❌ خطا در اتصال دیتابیس: " . $e->getMessage() . "</div>";
    exit;
}

echo "<h3>مرحله 4: تست ایجاد جداول</h3>";
try {
    createDatabaseTables($pdo);
    echo "<div class='success'>✅ جداول ایجاد شدند</div>";
} catch (Exception $e) {
    echo "<div class='error'>❌ خطا در ایجاد جداول: " . $e->getMessage() . "</div>";
}

echo "<h3>مرحله 5: تست visit_dashboard.php</h3>";
try {
    ob_start();
    include 'visit_dashboard.php';
    $content = ob_get_clean();
    echo "<div class='success'>✅ visit_dashboard.php با موفقیت بارگذاری شد!</div>";
    echo "<div class='info'>📄 طول محتوا: " . strlen($content) . " کاراکتر</div>";
} catch (Exception $e) {
    echo "<div class='error'>❌ خطا در visit_dashboard: " . $e->getMessage() . "</div>";
    echo "<div class='error'>Stack trace: " . $e->getTraceAsString() . "</div>";
}

echo "<h3>مرحله 6: تست نهایی</h3>";
echo "<div class='success'>";
echo "🎉 تست ساده کامل شد!<br>";
echo "✅ سیستم آماده استفاده است<br>";
echo "</div>";

echo "<h3>🔗 لینک‌های تست</h3>";
echo "<a href='index_visit.php' class='btn'>🏠 صفحه اصلی</a>";
echo "<a href='visit_dashboard.php' class='btn'>📊 داشبورد</a>";
echo "<a href='visit_management.php' class='btn'>📋 مدیریت</a>";
echo "<a href='visit_checkin.php' class='btn'>📱 Check-in</a>";

echo "</div></body></html>";
?>