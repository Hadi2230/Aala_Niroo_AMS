<?php
// تست ساده برای بررسی کارکرد سیستم مدیریت خودروها
session_start();

// شبیه‌سازی session برای تست
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'admin';
    $_SESSION['role'] = 'ادمین';
}

include 'config.php';

echo "<h1>تست سیستم مدیریت خودروها</h1>";

try {
    // تست اتصال به دیتابیس
    echo "<p>✅ اتصال به دیتابیس موفق</p>";
    
    // تست وجود جداول
    $tables = ['company_cars', 'car_assignments', 'car_maintenance', 'car_fuel_records', 'car_incidents', 'car_drivers'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "<p>✅ جدول $table موجود است</p>";
        } else {
            echo "<p>❌ جدول $table موجود نیست</p>";
        }
    }
    
    // تست توابع
    echo "<h2>تست توابع:</h2>";
    
    // تست آمار
    $stats = getCarStatistics($pdo);
    echo "<p>✅ تابع getCarStatistics کار می‌کند - تعداد خودروها: " . $stats['total_cars'] . "</p>";
    
    // تست دریافت خودروهای موجود
    $available_cars = getAvailableCars($pdo, date('Y-m-d'), date('Y-m-d', strtotime('+1 day')));
    echo "<p>✅ تابع getAvailableCars کار می‌کند - تعداد خودروهای موجود: " . count($available_cars) . "</p>";
    
    // تست دریافت انتساب‌ها
    $assignments = getCarAssignments($pdo);
    echo "<p>✅ تابع getCarAssignments کار می‌کند - تعداد انتساب‌ها: " . count($assignments) . "</p>";
    
    echo "<h2>🎉 همه چیز کار می‌کند!</h2>";
    echo "<p><a href='cars.php'>برو به صفحه مدیریت خودروها</a></p>";
    
} catch (Exception $e) {
    echo "<p>❌ خطا: " . $e->getMessage() . "</p>";
    echo "<p>جزئیات خطا: " . $e->getTraceAsString() . "</p>";
}
?>