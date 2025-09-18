<?php
/**
 * test_request_final.php - تست نهایی سیستم درخواست‌ها
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'test_user';
}

// فعال کردن نمایش خطاها
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>تست نهایی سیستم درخواست‌ها</h2>";

// تست 1: بارگذاری config.php
echo "<h3>1. بارگذاری config.php:</h3>";
try {
    require_once 'config.php';
    echo "✅ config.php بارگذاری شد<br>";
} catch (Exception $e) {
    echo "❌ خطا در بارگذاری config.php: " . $e->getMessage() . "<br>";
    exit;
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

// تست 3: تست ایجاد درخواست
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
        
        // بررسی درخواست در دیتابیس
        $stmt = $pdo->prepare("SELECT * FROM requests WHERE id = ?");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch();
        
        if ($request) {
            echo "✅ درخواست در دیتابیس یافت شد:<br>";
            echo "- شماره درخواست: " . $request['request_number'] . "<br>";
            echo "- نام آیتم: " . $request['item_name'] . "<br>";
            echo "- وضعیت: " . $request['status'] . "<br>";
        } else {
            echo "❌ درخواست در دیتابیس یافت نشد<br>";
        }
    } else {
        echo "❌ خطا در ایجاد درخواست<br>";
    }
} catch (Exception $e) {
    echo "❌ خطا در تست: " . $e->getMessage() . "<br>";
}

echo "<br><a href='request_management.php'>بازگشت به صفحه اصلی</a>";
?>