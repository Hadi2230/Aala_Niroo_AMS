<?php
// mysql_fix_simple.php - حل ساده مشکل MySQL

echo "<h2>حل مشکل دسترسی MySQL</h2>";

// تست اتصال‌های مختلف
$configs = [
    ['host' => 'localhost', 'port' => '3306', 'username' => 'root', 'password' => ''],
    ['host' => 'localhost', 'port' => '3306', 'username' => 'root', 'password' => 'root'],
    ['host' => '127.0.0.1', 'port' => '3306', 'username' => 'root', 'password' => ''],
];

$working_config = null;

foreach ($configs as $i => $config) {
    echo "<h3>تنظیمات " . ($i + 1) . ":</h3>";
    echo "Host: " . $config['host'] . " | Port: " . $config['port'] . " | User: " . $config['username'] . " | Pass: " . ($config['password'] ? '***' : '(خالی)') . "<br>";
    
    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        echo "<span style='color: green;'>✅ اتصال موفق!</span><br>";
        
        // لیست دیتابیس‌ها
        $stmt = $pdo->query("SHOW DATABASES");
        $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "<strong>دیتابیس‌های Aala:</strong><br>";
        foreach ($databases as $db) {
            if (strpos($db, 'aala') !== false) {
                echo "<span style='color: blue;'>📁 $db</span><br>";
            }
        }
        
        $working_config = $config;
        break;
        
    } catch (Exception $e) {
        echo "<span style='color: red;'>❌ خطا: " . $e->getMessage() . "</span><br>";
    }
    
    echo "<hr>";
}

if ($working_config) {
    echo "<h3>✅ راه‌حل پیدا شد!</h3>";
    echo "<p>تنظیمات صحیح:</p>";
    echo "<ul>";
    echo "<li>Host: " . $working_config['host'] . "</li>";
    echo "<li>Port: " . $working_config['port'] . "</li>";
    echo "<li>Username: " . $working_config['username'] . "</li>";
    echo "<li>Password: " . ($working_config['password'] ? '***' : '(خالی)') . "</li>";
    echo "</ul>";
    
    echo "<h3>مراحل بعدی:</h3>";
    echo "<ol>";
    echo "<li>فایل <code>config.php</code> را باز کنید</li>";
    echo "<li>تنظیمات دیتابیس را به موارد بالا تغییر دهید</li>";
    echo "<li>نام دیتابیس را به <code>aala_niroo_ams</code> تغییر دهید</li>";
    echo "<li>صفحه لاگین را تست کنید</li>";
    echo "</ol>";
    
} else {
    echo "<h3>❌ هیچ تنظیمات صحیحی یافت نشد</h3>";
    echo "<h4>راه‌حل‌های پیشنهادی:</h4>";
    echo "<ol>";
    echo "<li><strong>XAMPP را بررسی کنید:</strong> مطمئن شوید MySQL Start شده</li>";
    echo "<li><strong>در phpMyAdmin:</strong> روی تب SQL کلیک کنید و این دستور را اجرا کنید:</li>";
    echo "<pre>ALTER USER 'root'@'localhost' IDENTIFIED BY '';</pre>";
    echo "<li><strong>ریست XAMPP:</strong> XAMPP را Stop و Start کنید</li>";
    echo "</ol>";
}

echo "<hr>";
echo "<p><a href='login.php'>برو به صفحه لاگین</a></p>";
?>