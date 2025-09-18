<?php
/**
 * test_final.php - تست نهایی سیستم
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'test_user';
}

echo "<h2>تست نهایی سیستم درخواست‌ها</h2>";

// بارگذاری config_simple
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

echo "<h3>3. بررسی جداول:</h3>";
$tables = ['requests', 'request_files', 'request_workflow', 'request_notifications'];
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
        echo "✅ جدول $table: موجود<br>";
    } catch (Exception $e) {
        echo "❌ جدول $table: " . $e->getMessage() . "<br>";
    }
}

echo "<h3>4. تست ایجاد درخواست:</h3>";
try {
    $data = [
        'requester_id' => 1,
        'requester_name' => 'test_user',
        'item_name' => 'تست آیتم نهایی',
        'quantity' => 2,
        'price' => 5000,
        'description' => 'تست نهایی سیستم',
        'priority' => 'متوسط'
    ];
    
    $request_id = createRequest($pdo, $data);
    if ($request_id) {
        echo "✅ درخواست ایجاد شد با ID: $request_id<br>";
        
        // بررسی درخواست در دیتابیس
        $stmt = $pdo->prepare("SELECT * FROM requests WHERE id = ?");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch();
        
        if ($request) {
            echo "✅ درخواست در دیتابیس:<br>";
            echo "- شماره: " . $request['request_number'] . "<br>";
            echo "- آیتم: " . $request['item_name'] . "<br>";
            echo "- وضعیت: " . $request['status'] . "<br>";
        }
    } else {
        echo "❌ خطا در ایجاد درخواست<br>";
    }
} catch (Exception $e) {
    echo "❌ خطا در تست: " . $e->getMessage() . "<br>";
}

echo "<br><a href='request_management.php' style='background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>بازگشت به صفحه اصلی</a>";
?>