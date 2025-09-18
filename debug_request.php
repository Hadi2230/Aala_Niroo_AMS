<?php
/**
 * debug_request.php - دیباگ سیستم درخواست‌ها
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'test_user';
}

require_once 'config.php';

// فعال کردن نمایش خطاها
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>دیباگ سیستم درخواست‌ها</h2>";

// تست 1: بررسی اتصال دیتابیس
echo "<h3>1. تست اتصال دیتابیس:</h3>";
try {
    $stmt = $pdo->query("SELECT 1");
    echo "✅ اتصال دیتابیس: موفق<br>";
} catch (Exception $e) {
    echo "❌ خطا در اتصال دیتابیس: " . $e->getMessage() . "<br>";
    exit;
}

// تست 2: بررسی وجود جدول requests
echo "<h3>2. تست جدول requests:</h3>";
try {
    $stmt = $pdo->query("DESCRIBE requests");
    $columns = $stmt->fetchAll();
    echo "✅ جدول requests موجود با " . count($columns) . " ستون<br>";
    foreach ($columns as $col) {
        echo "- " . $col['Field'] . " (" . $col['Type'] . ")<br>";
    }
} catch (Exception $e) {
    echo "❌ خطا در جدول requests: " . $e->getMessage() . "<br>";
}

// تست 3: بررسی تابع generateRequestNumber
echo "<h3>3. تست تابع generateRequestNumber:</h3>";
if (function_exists('generateRequestNumber')) {
    try {
        $request_number = generateRequestNumber($pdo);
        echo "✅ generateRequestNumber: " . $request_number . "<br>";
    } catch (Exception $e) {
        echo "❌ خطا در generateRequestNumber: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ تابع generateRequestNumber یافت نشد<br>";
}

// تست 4: بررسی تابع createRequest
echo "<h3>4. تست تابع createRequest:</h3>";
if (function_exists('createRequest')) {
    echo "✅ تابع createRequest موجود<br>";
    
    // تست ایجاد درخواست
    $data = [
        'requester_id' => 1,
        'requester_name' => 'test_user',
        'item_name' => 'تست آیتم',
        'quantity' => 1,
        'price' => 1000,
        'description' => 'تست توضیحات',
        'priority' => 'کم'
    ];
    
    try {
        $request_id = createRequest($pdo, $data);
        if ($request_id) {
            echo "✅ ایجاد درخواست: موفق (ID: $request_id)<br>";
        } else {
            echo "❌ ایجاد درخواست: ناموفق<br>";
        }
    } catch (Exception $e) {
        echo "❌ خطا در ایجاد درخواست: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ تابع createRequest یافت نشد<br>";
}

// تست 5: بررسی جداول مرتبط
echo "<h3>5. تست جداول مرتبط:</h3>";
$tables = ['request_files', 'request_workflow', 'request_notifications'];
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
        echo "✅ جدول $table: موجود<br>";
    } catch (Exception $e) {
        echo "❌ جدول $table: " . $e->getMessage() . "<br>";
    }
}

echo "<br><a href='request_management.php'>بازگشت به صفحه اصلی</a>";
?>