<?php
// تست ساده PHP
echo "PHP کار می‌کند!<br>";
echo "نسخه PHP: " . phpversion() . "<br>";
echo "تاریخ: " . date('Y-m-d H:i:s') . "<br>";

// تست اتصال به MySQL
try {
    $pdo = new PDO("mysql:host=localhost;port=3306;charset=utf8mb4", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "✅ اتصال به MySQL موفق<br>";
    
    // تست ایجاد دیتابیس
    $pdo->exec("CREATE DATABASE IF NOT EXISTS aala_niroo_ams CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✅ دیتابیس ایجاد شد<br>";
    
    $pdo->exec("USE aala_niroo_ams");
    echo "✅ دیتابیس انتخاب شد<br>";
    
} catch (Exception $e) {
    echo "❌ خطا: " . $e->getMessage() . "<br>";
}
?>