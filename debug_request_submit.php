<?php
/**
 * debug_request_submit.php - دیباگ ارسال درخواست
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'test_user';
}

require_once 'config_simple.php';

// فعال کردن نمایش خطاها
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>دیباگ ارسال درخواست</h2>";

// تست 1: بررسی POST data
echo "<h3>1. بررسی POST data:</h3>";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    
    echo "<h4>FILES:</h4>";
    echo "<pre>";
    print_r($_FILES);
    echo "</pre>";
} else {
    echo "هیچ POST data دریافت نشد<br>";
}

// تست 2: بررسی توابع
echo "<h3>2. بررسی توابع:</h3>";
$functions = ['createRequest', 'generateRequestNumber', 'uploadRequestFile', 'createRequestWorkflow'];
foreach ($functions as $func) {
    if (function_exists($func)) {
        echo "✅ تابع $func: موجود<br>";
    } else {
        echo "❌ تابع $func: یافت نشد<br>";
    }
}

// تست 3: تست ایجاد درخواست ساده
echo "<h3>3. تست ایجاد درخواست ساده:</h3>";
try {
    $data = [
        'requester_id' => 1,
        'requester_name' => 'test_user',
        'item_name' => 'تست آیتم دیباگ',
        'quantity' => 1,
        'price' => 1000,
        'description' => 'تست دیباگ',
        'priority' => 'کم'
    ];
    
    $request_id = createRequest($pdo, $data);
    if ($request_id) {
        echo "✅ درخواست تست ایجاد شد با ID: $request_id<br>";
    } else {
        echo "❌ خطا در ایجاد درخواست تست<br>";
    }
} catch (Exception $e) {
    echo "❌ خطا در تست: " . $e->getMessage() . "<br>";
}

// تست 4: بررسی جداول
echo "<h3>4. بررسی جداول:</h3>";
$tables = ['requests', 'request_files', 'request_workflow', 'request_notifications'];
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
        $count = $stmt->fetchColumn();
        echo "✅ جدول $table: $count رکورد<br>";
    } catch (Exception $e) {
        echo "❌ جدول $table: " . $e->getMessage() . "<br>";
    }
}

echo "<br><a href='request_management_final.php'>بازگشت به صفحه اصلی</a>";
?>