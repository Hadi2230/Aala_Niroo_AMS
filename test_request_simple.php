<?php
/**
 * test_request_simple.php - تست ساده سیستم درخواست‌ها
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // برای تست
    $_SESSION['username'] = 'test_user';
}

require_once 'config.php';

// فعال کردن نمایش خطاها
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>تست سیستم درخواست‌ها</h2>";

// تست اتصال دیتابیس
try {
    $stmt = $pdo->query("SELECT 1");
    echo "✅ اتصال دیتابیس: موفق<br>";
} catch (Exception $e) {
    echo "❌ خطا در اتصال دیتابیس: " . $e->getMessage() . "<br>";
    exit;
}

// تست وجود جداول
$tables = ['requests', 'request_files', 'request_workflow', 'request_notifications'];
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
        echo "✅ جدول $table: موجود<br>";
    } catch (Exception $e) {
        echo "❌ جدول $table: " . $e->getMessage() . "<br>";
    }
}

// تست ایجاد درخواست ساده
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
    
    if (function_exists('createRequest')) {
        $request_id = createRequest($pdo, $data);
        if ($request_id) {
            echo "✅ ایجاد درخواست: موفق (ID: $request_id)<br>";
        } else {
            echo "❌ ایجاد درخواست: ناموفق<br>";
        }
    } else {
        echo "❌ تابع createRequest یافت نشد<br>";
    }
} catch (Exception $e) {
    echo "❌ خطا در ایجاد درخواست: " . $e->getMessage() . "<br>";
}

echo "<br><a href='request_management.php'>بازگشت به صفحه اصلی</a>";
?>