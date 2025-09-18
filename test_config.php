<?php
/**
 * test_config.php - تست بارگذاری config.php
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'test_user';
}

// فعال کردن نمایش خطاها
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>تست بارگذاری config.php</h2>";

// تست 1: بررسی وجود فایل config.php
echo "<h3>1. بررسی وجود فایل config.php:</h3>";
if (file_exists('config.php')) {
    echo "✅ فایل config.php موجود<br>";
} else {
    echo "❌ فایل config.php یافت نشد<br>";
    exit;
}

// تست 2: بارگذاری config.php
echo "<h3>2. بارگذاری config.php:</h3>";
try {
    require_once 'config.php';
    echo "✅ config.php بارگذاری شد<br>";
} catch (Exception $e) {
    echo "❌ خطا در بارگذاری config.php: " . $e->getMessage() . "<br>";
    exit;
}

// تست 3: بررسی متغیر $pdo
echo "<h3>3. بررسی متغیر \$pdo:</h3>";
if (isset($pdo)) {
    echo "✅ متغیر \$pdo موجود<br>";
} else {
    echo "❌ متغیر \$pdo یافت نشد<br>";
    exit;
}

// تست 4: بررسی توابع
echo "<h3>4. بررسی توابع:</h3>";
$functions = [
    'generateRequestNumber',
    'createRequest', 
    'uploadRequestFile',
    'createRequestWorkflow',
    'createRequestNotification'
];

foreach ($functions as $func) {
    if (function_exists($func)) {
        echo "✅ تابع $func: موجود<br>";
    } else {
        echo "❌ تابع $func: یافت نشد<br>";
    }
}

// تست 5: تست اتصال دیتابیس
echo "<h3>5. تست اتصال دیتابیس:</h3>";
try {
    $stmt = $pdo->query("SELECT 1");
    echo "✅ اتصال دیتابیس: موفق<br>";
} catch (Exception $e) {
    echo "❌ خطا در اتصال دیتابیس: " . $e->getMessage() . "<br>";
}

// تست 6: بررسی جداول
echo "<h3>6. بررسی جداول:</h3>";
$tables = ['requests', 'request_files', 'request_workflow', 'request_notifications', 'users'];
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
        echo "✅ جدول $table: موجود<br>";
    } catch (Exception $e) {
        echo "❌ جدول $table: " . $e->getMessage() . "<br>";
    }
}

echo "<br><a href='setup_requests.php'>ایجاد جداول</a> | <a href='request_management.php'>صفحه اصلی</a>";
?>