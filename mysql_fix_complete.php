<?php
/**
 * mysql_fix_complete.php - حل کامل مشکل MySQL
 */

// فعال کردن نمایش خطاها
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔧 حل کامل مشکل MySQL</h2>";

// بررسی وضعیت XAMPP
echo "<h3>1. بررسی وضعیت XAMPP</h3>";

// تست اتصال به پورت‌های مختلف
$ports = [3306, 3307, 3308, 3309];
$working_ports = [];

foreach ($ports as $port) {
    $connection = @fsockopen('localhost', $port, $errno, $errstr, 5);
    if ($connection) {
        $working_ports[] = $port;
        fclose($connection);
        echo "<p style='color: green;'>✅ پورت $port در دسترس است</p>";
    } else {
        echo "<p style='color: red;'>❌ پورت $port در دسترس نیست</p>";
    }
}

if (empty($working_ports)) {
    echo "<div style='background: #f8d7da; padding: 20px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4 style='color: #721c24; margin: 0 0 15px 0;'>❌ MySQL در حال اجرا نیست!</h4>";
    echo "<ol style='margin: 10px 0; padding-right: 20px;'>";
    echo "<li>XAMPP Control Panel را باز کنید</li>";
    echo "<li>MySQL را Start کنید</li>";
    echo "<li>اگر خطا داد، Apache را هم Stop و Start کنید</li>";
    echo "<li>این صفحه را Refresh کنید</li>";
    echo "</ol>";
    echo "</div>";
    exit;
}

// تست تنظیمات مختلف
echo "<h3>2. تست تنظیمات مختلف</h3>";

$test_configs = [
    // تنظیمات بدون رمز
    ['host' => 'localhost', 'port' => '3306', 'user' => 'root', 'pass' => '', 'desc' => 'localhost:3306 - root - خالی'],
    ['host' => '127.0.0.1', 'port' => '3306', 'user' => 'root', 'pass' => '', 'desc' => '127.0.0.1:3306 - root - خالی'],
    
    // تنظیمات با رمزهای مختلف
    ['host' => 'localhost', 'port' => '3306', 'user' => 'root', 'pass' => 'root', 'desc' => 'localhost:3306 - root - root'],
    ['host' => 'localhost', 'port' => '3306', 'user' => 'root', 'pass' => 'password', 'desc' => 'localhost:3306 - root - password'],
    ['host' => 'localhost', 'port' => '3306', 'user' => 'root', 'pass' => 'admin', 'desc' => 'localhost:3306 - root - admin'],
    ['host' => 'localhost', 'port' => '3306', 'user' => 'root', 'pass' => '123456', 'desc' => 'localhost:3306 - root - 123456'],
    
    // تنظیمات با پورت‌های مختلف
    ['host' => 'localhost', 'port' => '3307', 'user' => 'root', 'pass' => '', 'desc' => 'localhost:3307 - root - خالی'],
    ['host' => 'localhost', 'port' => '3307', 'user' => 'root', 'pass' => 'root', 'desc' => 'localhost:3307 - root - root'],
    
    // تنظیمات با کاربران مختلف
    ['host' => 'localhost', 'port' => '3306', 'user' => 'admin', 'pass' => '', 'desc' => 'localhost:3306 - admin - خالی'],
    ['host' => 'localhost', 'port' => '3306', 'user' => 'admin', 'pass' => 'admin', 'desc' => 'localhost:3306 - admin - admin'],
];

$working_config = null;

foreach ($test_configs as $i => $config) {
    echo "<h4>تست " . ($i + 1) . ": {$config['desc']}</h4>";
    
    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 5
        ]);
        
        echo "<p style='color: green;'>✅ اتصال موفق!</p>";
        
        // تست ایجاد دیتابیس
        try {
            $pdo->exec("CREATE DATABASE IF NOT EXISTS aala_niroo_ams CHARACTER SET utf8mb4 COLLATE utf8mb4_persian_ci");
            echo "<p style='color: green;'>✅ دیتابیس aala_niroo_ams ایجاد شد</p>";
            
            $pdo->exec("USE aala_niroo_ams");
            echo "<p style='color: green;'>✅ اتصال به دیتابیس موفق</p>";
            
            // این تنظیمات کار می‌کند
            $working_config = $config;
            echo "<div style='background: #d4edda; padding: 20px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 20px 0;'>";
            echo "<h4 style='color: #155724; margin: 0 0 15px 0;'>🎉 تنظیمات صحیح پیدا شد!</h4>";
            echo "<p style='margin: 5px 0;'><strong>Host:</strong> {$config['host']}</p>";
            echo "<p style='margin: 5px 0;'><strong>Port:</strong> {$config['port']}</p>";
            echo "<p style='margin: 5px 0;'><strong>Username:</strong> {$config['user']}</p>";
            echo "<p style='margin: 5px 0;'><strong>Password:</strong> " . ($config['pass'] ?: 'خالی') . "</p>";
            echo "<p style='margin: 5px 0;'><strong>Database:</strong> aala_niroo_ams</p>";
            echo "<p style='margin: 15px 0 0 0;'>";
            echo "<a href='login.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-left: 10px;'>برو به لاگین</a>";
            echo "<a href='test_login_debug.php' style='background: #17a2b8; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-left: 10px;'>تست لاگین</a>";
            echo "</p>";
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

// اگر هیچ تنظیماتی کار نکرد
if (!$working_config) {
    echo "<div style='background: #f8d7da; padding: 20px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4 style='color: #721c24; margin: 0 0 15px 0;'>❌ هیچ تنظیماتی کار نکرد!</h4>";
    echo "<p style='margin: 10px 0;'>احتمالاً MySQL درست نصب نشده یا تنظیمات اشتباه است.</p>";
    echo "<h5>راه‌حل‌های پیشنهادی:</h5>";
    echo "<ol style='margin: 10px 0; padding-right: 20px;'>";
    echo "<li><strong>XAMPP را کاملاً حذف و دوباره نصب کنید</strong></li>";
    echo "<li><strong>XAMPP را به عنوان Administrator اجرا کنید</strong></li>";
    echo "<li><strong>Windows Firewall را موقتاً غیرفعال کنید</strong></li>";
    echo "<li><strong>Antivirus را موقتاً غیرفعال کنید</strong></li>";
    echo "<li><strong>پورت 3306 را در Windows Firewall باز کنید</strong></li>";
    echo "</ol>";
    echo "</div>";
    
    // راهنمای نصب مجدد
    echo "<div style='background: #fff3cd; padding: 20px; border: 1px solid #ffeaa7; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4 style='color: #856404; margin: 0 0 15px 0;'>راهنمای نصب مجدد XAMPP:</h4>";
    echo "<ol style='margin: 10px 0; padding-right: 20px;'>";
    echo "<li>XAMPP را کاملاً Uninstall کنید</li>";
    echo "<li>پوشه C:\\xampp را حذف کنید</li>";
    echo "<li>Registry را پاک کنید (CCleaner استفاده کنید)</li>";
    echo "<li>XAMPP جدید از <a href='https://www.apachefriends.org' target='_blank'>apachefriends.org</a> دانلود کنید</li>";
    echo "<li>به عنوان Administrator نصب کنید</li>";
    echo "<li>MySQL را Start کنید</li>";
    echo "</ol>";
    echo "</div>";
}

// تست با mysqli
echo "<h3>3. تست با mysqli</h3>";
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

// بررسی فایل‌های XAMPP
echo "<h3>4. بررسی فایل‌های XAMPP</h3>";
$xampp_paths = [
    'C:\\xampp\\mysql\\bin\\mysqld.exe',
    'C:\\xampp\\mysql\\data\\',
    'C:\\xampp\\mysql\\my.ini',
    'C:\\xampp\\mysql\\bin\\mysql.exe'
];

foreach ($xampp_paths as $path) {
    if (file_exists($path)) {
        echo "<p style='color: green;'>✅ $path</p>";
    } else {
        echo "<p style='color: red;'>❌ $path</p>";
    }
}

// راهنمای نهایی
echo "<div style='background: #e2e3e5; padding: 20px; border: 1px solid #d6d8db; border-radius: 5px; margin: 20px 0;'>";
echo "<h4 style='color: #383d41; margin: 0 0 15px 0;'>📋 خلاصه راه‌حل‌ها:</h4>";
echo "<ol style='margin: 10px 0; padding-right: 20px;'>";
echo "<li><strong>XAMPP را Restart کنید</strong></li>";
echo "<li><strong>MySQL را Stop و Start کنید</strong></li>";
echo "<li><strong>Apache را هم Stop و Start کنید</strong></li>";
echo "<li><strong>XAMPP را به عنوان Administrator اجرا کنید</strong></li>";
echo "<li><strong>Windows Firewall را بررسی کنید</strong></li>";
echo "<li><strong>اگر هیچ‌کدام کار نکرد، XAMPP را دوباره نصب کنید</strong></li>";
echo "</ol>";
echo "</div>";
?>