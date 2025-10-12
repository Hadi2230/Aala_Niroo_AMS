<?php
session_start();

// شبیه‌سازی session
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'admin';
    $_SESSION['role'] = 'ادمین';
}

include 'config_simple.php';

echo "<h1>تست ساده سیستم خودروها</h1>";

try {
    echo "<p>✅ اتصال به دیتابیس موفق</p>";
    
    // تست جداول
    $tables = ['company_cars', 'car_assignments', 'car_maintenance'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "<p>✅ جدول $table موجود است</p>";
        } else {
            echo "<p>❌ جدول $table موجود نیست</p>";
        }
    }
    
    // تست توابع
    $stats = getCarStatistics($pdo);
    echo "<p>✅ آمار خودروها: " . $stats['total_cars'] . "</p>";
    
    $cars = getAvailableCars($pdo, date('Y-m-d'), date('Y-m-d'));
    echo "<p>✅ خودروهای موجود: " . count($cars) . "</p>";
    
    echo "<h2>🎉 تست موفق!</h2>";
    echo "<p><a href='cars.php'>برو به مدیریت خودروها</a></p>";
    
} catch (Exception $e) {
    echo "<p>❌ خطا: " . $e->getMessage() . "</p>";
}
?>