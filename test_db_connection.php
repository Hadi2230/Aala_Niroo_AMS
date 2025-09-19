<?php
/**
 * test_db_connection.php - تست اتصال به دیتابیس
 */

// فعال کردن نمایش خطاها
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>تست اتصال به دیتابیس</h2>";

try {
    // تنظیمات دیتابیس
    $host = 'localhost:3306';
    $dbname = 'aala_niroo_ams';
    $username = 'root';
    $password = '';
    
    echo "<p>در حال اتصال به دیتابیس: $dbname</p>";
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_persian_ci"
    ]);
    
    echo "<p style='color: green;'>✅ اتصال به دیتابیس موفق</p>";
    
    // بررسی وجود جداول
    $tables = ['users', 'requests', 'request_files', 'request_workflow', 'request_notifications'];
    
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        $exists = $stmt->fetch();
        
        if ($exists) {
            echo "<p style='color: green;'>✅ جدول $table وجود دارد</p>";
        } else {
            echo "<p style='color: red;'>❌ جدول $table وجود ندارد</p>";
        }
    }
    
    // اگر جدول users وجود دارد، کاربران را نمایش بده
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    $users_exists = $stmt->fetch();
    
    if ($users_exists) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $user_count = $stmt->fetch()['count'];
        echo "<p>تعداد کاربران: $user_count</p>";
        
        if ($user_count > 0) {
            $stmt = $pdo->query("SELECT id, username, full_name, role FROM users LIMIT 5");
            $users = $stmt->fetchAll();
            
            echo "<h3>نمونه کاربران:</h3>";
            echo "<ul>";
            foreach ($users as $user) {
                echo "<li>{$user['full_name']} ({$user['username']}) - {$user['role']}</li>";
            }
            echo "</ul>";
        }
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ خطا در اتصال به دیتابیس:</p>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
    
    // پیشنهاد ایجاد دیتابیس
    echo "<h3>راه‌حل:</h3>";
    echo "<p>1. دیتابیس 'aala_niroo_ams' را در MySQL ایجاد کنید</p>";
    echo "<p>2. یا نام دیتابیس را در config.php تغییر دهید</p>";
    
    // تست اتصال بدون نام دیتابیس
    try {
        $pdo_no_db = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        echo "<h3>دیتابیس‌های موجود:</h3>";
        $stmt = $pdo_no_db->query("SHOW DATABASES");
        $databases = $stmt->fetchAll();
        
        echo "<ul>";
        foreach ($databases as $db) {
            echo "<li>" . $db['Database'] . "</li>";
        }
        echo "</ul>";
        
    } catch (Exception $e2) {
        echo "<p style='color: red;'>خطا در اتصال به MySQL: " . $e2->getMessage() . "</p>";
    }
}
?>