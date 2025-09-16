<?php
// تست اتصال به دیتابیس
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html lang='fa' dir='rtl'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>تست اتصال دیتابیس</title>
    <style>
        body { font-family: Tahoma, Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #27ae60; background: #d5f4e6; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: #e74c3c; background: #fadbd8; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { color: #3498db; background: #d6eaf8; padding: 10px; border-radius: 5px; margin: 10px 0; }
        h1 { text-align: center; color: #2c3e50; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔍 تست اتصال به دیتابیس</h1>";

// تنظیمات دیتابیس
$db_host = 'localhost';
$db_port = '3306';
$db_user = 'root';
$db_pass = '';

try {
    echo "<div class='info'>در حال تلاش برای اتصال به MySQL...</div>";
    
    // اتصال به MySQL بدون انتخاب دیتابیس خاص
    $pdo = new PDO("mysql:host=$db_host;port=$db_port;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
    echo "<div class='success'>✅ اتصال به MySQL برقرار شد</div>";
    
    // بررسی وجود دیتابیس
    $stmt = $pdo->query("SHOW DATABASES LIKE 'aala_niroo_ams'");
    $db_exists = $stmt->fetch();
    
    if ($db_exists) {
        echo "<div class='success'>✅ دیتابیس aala_niroo_ams موجود است</div>";
        
        // انتخاب دیتابیس و بررسی جداول
        $pdo->exec("USE aala_niroo_ams");
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "<div class='info'>📊 تعداد جداول موجود: " . count($tables) . "</div>";
        
        if (count($tables) > 0) {
            echo "<div class='success'>✅ جداول موجود:</div><ul>";
            foreach ($tables as $table) {
                echo "<li>$table</li>";
            }
            echo "</ul>";
        } else {
            echo "<div class='error'>❌ هیچ جدولی در دیتابیس وجود ندارد</div>";
            echo "<div class='info'>💡 برای ایجاد جداول، فایل <a href='setup_database.php'>setup_database.php</a> را اجرا کنید</div>";
        }
        
    } else {
        echo "<div class='error'>❌ دیتابیس aala_niroo_ams وجود ندارد</div>";
        echo "<div class='info'>💡 برای ایجاد دیتابیس، فایل <a href='setup_database.php'>setup_database.php</a> را اجرا کنید</div>";
    }
    
    // تست query ساده
    $stmt = $pdo->query("SELECT VERSION() as version");
    $version = $stmt->fetch();
    echo "<div class='info'>🔧 نسخه MySQL: " . $version['version'] . "</div>";
    
} catch (PDOException $e) {
    echo "<div class='error'>❌ خطا در اتصال به دیتابیس: " . $e->getMessage() . "</div>";
    echo "<div class='info'>💡 لطفاً مطمئن شوید که:</div>";
    echo "<ul>";
    echo "<li>XAMPP یا WAMP در حال اجرا است</li>";
    echo "<li>MySQL سرویس فعال است</li>";
    echo "<li>تنظیمات اتصال صحیح است</li>";
    echo "</ul>";
} catch (Exception $e) {
    echo "<div class='error'>❌ خطای عمومی: " . $e->getMessage() . "</div>";
}

echo "</div></body></html>";
?>