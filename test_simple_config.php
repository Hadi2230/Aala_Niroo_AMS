<?php
/**
 * test_simple_config.php - تست با config ساده
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'test_user';
}

// فعال کردن نمایش خطاها
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>تست با config ساده</h2>";

// بارگذاری config ساده
require_once 'config_simple.php';

echo "<h3>1. بررسی توابع:</h3>";
$functions = ['generateRequestNumber', 'createRequest', 'uploadRequestFile', 'createRequestWorkflow'];
foreach ($functions as $func) {
    if (function_exists($func)) {
        echo "✅ تابع $func: موجود<br>";
    } else {
        echo "❌ تابع $func: یافت نشد<br>";
    }
}

echo "<h3>2. تست اتصال دیتابیس:</h3>";
try {
    $stmt = $pdo->query("SELECT 1");
    echo "✅ اتصال دیتابیس: موفق<br>";
} catch (Exception $e) {
    echo "❌ خطا در اتصال دیتابیس: " . $e->getMessage() . "<br>";
}

echo "<h3>3. تست ایجاد درخواست:</h3>";
try {
    $data = [
        'requester_id' => 1,
        'requester_name' => 'test_user',
        'item_name' => 'تست آیتم',
        'quantity' => 1,
        'price' => 1000,
        'description' => 'تست توضیحات',
        'priority' => 'کم'
    ];
    
    $request_id = createRequest($pdo, $data);
    if ($request_id) {
        echo "✅ درخواست ایجاد شد با ID: $request_id<br>";
    } else {
        echo "❌ خطا در ایجاد درخواست<br>";
    }
} catch (Exception $e) {
    echo "❌ خطا در تست: " . $e->getMessage() . "<br>";
}

echo "<br><a href='setup_requests.php'>ایجاد جداول</a> | <a href='request_management.php'>صفحه اصلی</a>";
?>