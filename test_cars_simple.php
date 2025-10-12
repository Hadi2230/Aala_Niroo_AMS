<?php
// فعال کردن نمایش خطاها
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>تست ساده cars.php</h1>";

try {
    // شبیه‌سازی session
    session_start();
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'admin';
    $_SESSION['role'] = 'ادمین';
    $_SESSION['csrf_token'] = 'test123';
    
    echo "<p>✅ Session تنظیم شد</p>";
    
    // تست config
    include 'config.php';
    echo "<p>✅ config.php بارگذاری شد</p>";
    
    // تست توابع
    $stats = getCarStatistics($pdo);
    echo "<p>✅ تابع getCarStatistics کار کرد</p>";
    echo "<p>تعداد خودروها: " . $stats['total_cars'] . "</p>";
    
} catch (Exception $e) {
    echo "<p>❌ خطا: " . $e->getMessage() . "</p>";
    echo "<p>فایل: " . $e->getFile() . "</p>";
    echo "<p>خط: " . $e->getLine() . "</p>";
    echo "<p>جزئیات: " . $e->getTraceAsString() . "</p>";
}
?>