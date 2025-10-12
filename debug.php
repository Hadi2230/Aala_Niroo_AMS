<?php
// فعال کردن نمایش خطاها
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<h1>تست PHP</h1>";
echo "<p>PHP کار می‌کند!</p>";

// تست اتصال به دیتابیس
$host = 'localhost:3306';
$dbname = 'aala_niroo';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    echo "<p>✅ اتصال به دیتابیس موفق</p>";
    
    // تست جداول
    $tables = ['company_cars', 'car_assignments', 'car_maintenance'];
    foreach ($tables as $table) {
        $result = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($result->rowCount() > 0) {
            echo "<p>✅ جدول $table موجود است</p>";
        } else {
            echo "<p>❌ جدول $table موجود نیست</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p>❌ خطا: " . $e->getMessage() . "</p>";
    echo "<p>جزئیات: " . $e->getTraceAsString() . "</p>";
}
?>