<?php
echo "PHP کار می‌کند!<br>";

// تست ساده اتصال به دیتابیس
$host = 'localhost:3306';
$dbname = 'aala_niroo';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    echo "اتصال به دیتابیس موفق<br>";
    
    // چک کردن جداول
    $tables = ['company_cars', 'car_assignments', 'car_maintenance'];
    foreach ($tables as $table) {
        $result = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($result->rowCount() > 0) {
            echo "✅ جدول $table موجود است<br>";
        } else {
            echo "❌ جدول $table موجود نیست<br>";
        }
    }
    
} catch (Exception $e) {
    echo "خطا: " . $e->getMessage();
}
?>