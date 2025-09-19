<?php
/**
 * test_mysql_connection.php - تست اتصال به MySQL
 */

// فعال کردن نمایش خطاها
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>تست اتصال به MySQL</h2>";

// تنظیمات مختلف برای تست
$configs = [
    [
        'name' => 'تنظیمات 1: بدون رمز',
        'host' => 'localhost',
        'port' => '3306',
        'username' => 'root',
        'password' => ''
    ],
    [
        'name' => 'تنظیمات 2: با رمز خالی',
        'host' => 'localhost',
        'port' => '3306',
        'username' => 'root',
        'password' => ''
    ],
    [
        'name' => 'تنظیمات 3: پورت 3307',
        'host' => 'localhost',
        'port' => '3307',
        'username' => 'root',
        'password' => ''
    ],
    [
        'name' => 'تنظیمات 4: بدون پورت',
        'host' => 'localhost',
        'username' => 'root',
        'password' => ''
    ],
    [
        'name' => 'تنظیمات 5: 127.0.0.1',
        'host' => '127.0.0.1',
        'port' => '3306',
        'username' => 'root',
        'password' => ''
    ]
];

foreach ($configs as $config) {
    echo "<h3>{$config['name']}</h3>";
    
    try {
        $dsn = "mysql:host={$config['host']}";
        if (isset($config['port'])) {
            $dsn .= ";port={$config['port']}";
        }
        $dsn .= ";charset=utf8mb4";
        
        echo "<p>DSN: $dsn</p>";
        echo "<p>Username: {$config['username']}</p>";
        echo "<p>Password: " . (empty($config['password']) ? '(خالی)' : '(مشخص شده)') . "</p>";
        
        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        echo "<p style='color: green;'>✅ اتصال موفق</p>";
        
        // نمایش دیتابیس‌های موجود
        $stmt = $pdo->query("SHOW DATABASES");
        $databases = $stmt->fetchAll();
        
        echo "<h4>دیتابیس‌های موجود:</h4>";
        echo "<ul>";
        foreach ($databases as $db) {
            $db_name = $db['Database'];
            $is_target = ($db_name === 'aala_niroo_ams' || $db_name === 'aala_niroo');
            $style = $is_target ? "color: green; font-weight: bold;" : "";
            echo "<li style='$style'>$db_name</li>";
        }
        echo "</ul>";
        
        // تست ایجاد دیتابیس
        echo "<h4>تست ایجاد دیتابیس...</h4>";
        try {
            $pdo->exec("CREATE DATABASE IF NOT EXISTS aala_niroo_ams CHARACTER SET utf8mb4 COLLATE utf8mb4_persian_ci");
            echo "<p style='color: green;'>✅ دیتابیس aala_niroo_ams ایجاد شد</p>";
            
            // تست اتصال به دیتابیس
            $pdo->exec("USE aala_niroo_ams");
            echo "<p style='color: green;'>✅ اتصال به دیتابیس aala_niroo_ams موفق</p>";
            
            // این تنظیمات کار می‌کند
            echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0;'>";
            echo "<h4 style='color: #155724; margin: 0 0 10px 0;'>✅ تنظیمات صحیح پیدا شد!</h4>";
            echo "<p style='margin: 5px 0;'><strong>Host:</strong> {$config['host']}</p>";
            if (isset($config['port'])) {
                echo "<p style='margin: 5px 0;'><strong>Port:</strong> {$config['port']}</p>";
            }
            echo "<p style='margin: 5px 0;'><strong>Username:</strong> {$config['username']}</p>";
            echo "<p style='margin: 5px 0;'><strong>Password:</strong> " . (empty($config['password']) ? '(خالی)' : $config['password']) . "</p>";
            echo "<p style='margin: 5px 0;'><strong>Database:</strong> aala_niroo_ams</p>";
            echo "</div>";
            
            break; // اولین تنظیمات موفق را پیدا کردیم
            
        } catch (Exception $e) {
            echo "<p style='color: orange;'>⚠️ خطا در ایجاد دیتابیس: " . $e->getMessage() . "</p>";
        }
        
    } catch (PDOException $e) {
        echo "<p style='color: red;'>❌ خطا: " . $e->getMessage() . "</p>";
    }
    
    echo "<hr>";
}

// راهنمای حل مشکل
echo "<h3>راهنمای حل مشکل:</h3>";
echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>";
echo "<h4 style='color: #721c24; margin: 0 0 10px 0;'>اگر همه تنظیمات خطا دادند:</h4>";
echo "<ol>";
echo "<li>XAMPP را باز کنید</li>";
echo "<li>MySQL را Start کنید</li>";
echo "<li>phpMyAdmin را باز کنید: <a href='http://localhost/phpmyadmin' target='_blank'>http://localhost/phpmyadmin</a></li>";
echo "<li>اگر رمز عبور خواست، رمز را در config.php تغییر دهید</li>";
echo "<li>دیتابیس 'aala_niroo_ams' را ایجاد کنید</li>";
echo "</ol>";
echo "</div>";

// تست اتصال با mysqli
echo "<h3>تست با mysqli:</h3>";
try {
    $mysqli = new mysqli('localhost', 'root', '');
    if ($mysqli->connect_error) {
        echo "<p style='color: red;'>❌ mysqli خطا: " . $mysqli->connect_error . "</p>";
    } else {
        echo "<p style='color: green;'>✅ mysqli اتصال موفق</p>";
        $mysqli->close();
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ mysqli خطا: " . $e->getMessage() . "</p>";
}
?>