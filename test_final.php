<?php
// تست نهایی
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>تست نهایی سیستم</h1>";

try {
    // شبیه‌سازی session
    session_start();
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'admin';
    $_SESSION['role'] = 'ادمین';
    $_SESSION['csrf_token'] = 'test123';
    
    echo "<p>✅ Session تنظیم شد</p>";
    
    // تست config جدید
    include 'config.php';
    echo "<p>✅ config.php بارگذاری شد</p>";
    
    // تست توابع
    $stats = getCarStatistics($pdo);
    echo "<p>✅ تابع getCarStatistics کار کرد</p>";
    echo "<p>تعداد خودروها: " . $stats['total_cars'] . "</p>";
    
    $cars = getAvailableCars($pdo, date('Y-m-d'), date('Y-m-d'));
    echo "<p>✅ تابع getAvailableCars کار کرد</p>";
    echo "<p>تعداد خودروهای موجود: " . count($cars) . "</p>";
    
    echo "<h2>🎉 همه چیز کار می‌کند!</h2>";
    echo "<p><a href='dashboard.php'>برو به داشبورد</a></p>";
    echo "<p><a href='cars.php'>برو به مدیریت خودروها</a></p>";
    
} catch (Exception $e) {
    echo "<p>❌ خطا: " . $e->getMessage() . "</p>";
    echo "<p>فایل: " . $e->getFile() . "</p>";
    echo "<p>خط: " . $e->getLine() . "</p>";
}
?>