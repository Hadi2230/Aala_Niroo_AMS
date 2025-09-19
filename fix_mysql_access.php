<?php
/**
 * fix_mysql_access.php - حل مشکل دسترسی MySQL
 */

// فعال کردن نمایش خطاها
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>حل مشکل دسترسی MySQL</h2>";

// راهنمای حل مشکل
echo "<div style='background: #f8d7da; padding: 20px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 20px 0;'>";
echo "<h3 style='color: #721c24; margin: 0 0 15px 0;'>🔧 راه‌حل مشکل دسترسی MySQL</h3>";
echo "<p style='margin: 10px 0;'><strong>خطا:</strong> Access denied for user 'root'@'localhost'</p>";
echo "<p style='margin: 10px 0;'><strong>علت:</strong> MySQL در XAMPP رمز عبور دارد یا تنظیمات اشتباه است</p>";
echo "</div>";

echo "<h3>مراحل حل مشکل:</h3>";

echo "<div style='background: #d4edda; padding: 20px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 20px 0;'>";
echo "<h4 style='color: #155724; margin: 0 0 15px 0;'>مرحله 1: بررسی XAMPP</h4>";
echo "<ol style='margin: 10px 0; padding-right: 20px;'>";
echo "<li>XAMPP Control Panel را باز کنید</li>";
echo "<li>MySQL را <strong>Stop</strong> کنید</li>";
echo "<li>چند ثانیه صبر کنید</li>";
echo "<li>MySQL را <strong>Start</strong> کنید</li>";
echo "<li>اگر خطا داد، XAMPP را کاملاً ببندید و دوباره باز کنید</li>";
echo "</ol>";
echo "</div>";

echo "<div style='background: #d1ecf1; padding: 20px; border: 1px solid #bee5eb; border-radius: 5px; margin: 20px 0;'>";
echo "<h4 style='color: #0c5460; margin: 0 0 15px 0;'>مرحله 2: تست phpMyAdmin</h4>";
echo "<ol style='margin: 10px 0; padding-right: 20px;'>";
echo "<li><a href='http://localhost/phpmyadmin' target='_blank'>phpMyAdmin</a> را باز کنید</li>";
echo "<li>اگر رمز عبور خواست، رمزهای زیر را امتحان کنید:</li>";
echo "<ul style='margin: 10px 0; padding-right: 20px;'>";
echo "<li>رمز خالی (فقط Enter بزنید)</li>";
echo "<li>root</li>";
echo "<li>password</li>";
echo "<li>admin</li>";
echo "</ul>";
echo "<li>اگر وارد شدید، دیتابیس 'aala_niroo_ams' را ایجاد کنید</li>";
echo "</ol>";
echo "</div>";

echo "<div style='background: #fff3cd; padding: 20px; border: 1px solid #ffeaa7; border-radius: 5px; margin: 20px 0;'>";
echo "<h4 style='color: #856404; margin: 0 0 15px 0;'>مرحله 3: تنظیم مجدد رمز عبور</h4>";
echo "<p style='margin: 10px 0;'>اگر هنوز مشکل دارید، رمز عبور MySQL را تنظیم کنید:</p>";
echo "<ol style='margin: 10px 0; padding-right: 20px;'>";
echo "<li>Command Prompt را به عنوان Administrator باز کنید</li>";
echo "<li>به پوشه XAMPP بروید: <code>cd C:\\xampp\\mysql\\bin</code></li>";
echo "<li>دستور زیر را اجرا کنید: <code>mysqladmin -u root password</code></li>";
echo "<li>رمز جدید را وارد کنید (یا Enter بزنید برای رمز خالی)</li>";
echo "</ol>";
echo "</div>";

// تست تنظیمات مختلف
echo "<h3>تست تنظیمات مختلف:</h3>";

$test_configs = [
    ['host' => 'localhost', 'port' => '3306', 'user' => 'root', 'pass' => ''],
    ['host' => 'localhost', 'port' => '3306', 'user' => 'root', 'pass' => 'root'],
    ['host' => 'localhost', 'port' => '3306', 'user' => 'root', 'pass' => 'password'],
    ['host' => 'localhost', 'port' => '3306', 'user' => 'root', 'pass' => 'admin'],
    ['host' => '127.0.0.1', 'port' => '3306', 'user' => 'root', 'pass' => ''],
    ['host' => '127.0.0.1', 'port' => '3306', 'user' => 'root', 'pass' => 'root'],
    ['host' => 'localhost', 'port' => '3307', 'user' => 'root', 'pass' => ''],
    ['host' => 'localhost', 'port' => '3307', 'user' => 'root', 'pass' => 'root'],
];

foreach ($test_configs as $i => $config) {
    echo "<h4>تست " . ($i + 1) . ": {$config['host']}:{$config['port']} - {$config['user']} - " . ($config['pass'] ?: 'خالی') . "</h4>";
    
    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        echo "<p style='color: green;'>✅ اتصال موفق!</p>";
        
        // تست ایجاد دیتابیس
        try {
            $pdo->exec("CREATE DATABASE IF NOT EXISTS aala_niroo_ams CHARACTER SET utf8mb4 COLLATE utf8mb4_persian_ci");
            echo "<p style='color: green;'>✅ دیتابیس aala_niroo_ams ایجاد شد</p>";
            
            $pdo->exec("USE aala_niroo_ams");
            echo "<p style='color: green;'>✅ اتصال به دیتابیس موفق</p>";
            
            // این تنظیمات کار می‌کند
            echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0;'>";
            echo "<h4 style='color: #155724; margin: 0 0 10px 0;'>🎉 تنظیمات صحیح پیدا شد!</h4>";
            echo "<p style='margin: 5px 0;'><strong>Host:</strong> {$config['host']}</p>";
            echo "<p style='margin: 5px 0;'><strong>Port:</strong> {$config['port']}</p>";
            echo "<p style='margin: 5px 0;'><strong>Username:</strong> {$config['user']}</p>";
            echo "<p style='margin: 5px 0;'><strong>Password:</strong> " . ($config['pass'] ?: 'خالی') . "</p>";
            echo "<p style='margin: 5px 0;'><strong>Database:</strong> aala_niroo_ams</p>";
            echo "<p style='margin: 10px 0 0 0;'><a href='login.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>برو به لاگین</a></p>";
            echo "</div>";
            
            break;
            
        } catch (Exception $e) {
            echo "<p style='color: orange;'>⚠️ خطا در ایجاد دیتابیس: " . $e->getMessage() . "</p>";
        }
        
    } catch (PDOException $e) {
        echo "<p style='color: red;'>❌ خطا: " . $e->getMessage() . "</p>";
    }
    
    echo "<hr>";
}

// راهنمای نهایی
echo "<div style='background: #e2e3e5; padding: 20px; border: 1px solid #d6d8db; border-radius: 5px; margin: 20px 0;'>";
echo "<h4 style='color: #383d41; margin: 0 0 15px 0;'>اگر هیچ تنظیماتی کار نکرد:</h4>";
echo "<ol style='margin: 10px 0; padding-right: 20px;'>";
echo "<li>XAMPP را کاملاً ببندید</li>";
echo "<li>Task Manager را باز کنید</li>";
echo "<li>تمام فرآیندهای mysql و mysqld را End Task کنید</li>";
echo "<li>XAMPP را دوباره باز کنید</li>";
echo "<li>MySQL را Start کنید</li>";
echo "<li>این صفحه را Refresh کنید</li>";
echo "</ol>";
echo "</div>";

// تست با mysqli
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