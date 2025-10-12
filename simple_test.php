<?php
echo "<h1>تست ساده</h1>";
echo "<p>اگر این متن را می‌بینید، PHP کار می‌کند!</p>";

// تست اتصال به دیتابیس
try {
    $host = 'localhost:3306';
    $dbname = 'aala_niroo';
    $username = 'root';
    $password = '';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "<p>✅ اتصال به دیتابیس موفق</p>";
    
    // تست وجود جدول company_cars
    $stmt = $pdo->query("SHOW TABLES LIKE 'company_cars'");
    if ($stmt->rowCount() > 0) {
        echo "<p>✅ جدول company_cars موجود است</p>";
    } else {
        echo "<p>❌ جدول company_cars موجود نیست</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ خطا در اتصال: " . $e->getMessage() . "</p>";
}
?>