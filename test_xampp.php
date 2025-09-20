<?php
// test_xampp.php - تست وضعیت XAMPP

echo "<h2>تست وضعیت XAMPP</h2>";

// بررسی وضعیت سرور
echo "<h3>1. وضعیت سرور:</h3>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'نامشخص') . "</p>";
echo "<p>Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'نامشخص') . "</p>";
echo "<p>Script Name: " . ($_SERVER['SCRIPT_NAME'] ?? 'نامشخص') . "</p>";

// بررسی پورت‌ها
echo "<h3>2. بررسی پورت‌ها:</h3>";
$ports = [80, 8080, 3306, 3307];
foreach ($ports as $port) {
    $connection = @fsockopen('localhost', $port, $errno, $errstr, 5);
    if (is_resource($connection)) {
        echo "<p style='color: green;'>✅ پورت $port: باز و در دسترس</p>";
        fclose($connection);
    } else {
        echo "<p style='color: red;'>❌ پورت $port: بسته یا غیرقابل دسترس</p>";
    }
}

// بررسی فایل‌های پروژه
echo "<h3>3. بررسی فایل‌های پروژه:</h3>";
$files = [
    'config.php',
    'login.php',
    'assets.php',
    'mysql_fix_simple.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "<p style='color: green;'>✅ $file: موجود</p>";
    } else {
        echo "<p style='color: red;'>❌ $file: یافت نشد</p>";
    }
}

// بررسی پوشه‌ها
echo "<h3>4. بررسی پوشه‌ها:</h3>";
$dirs = [
    'uploads',
    'logs',
    'Aala_Niroo_AMS'
];

foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        echo "<p style='color: green;'>✅ پوشه $dir: موجود</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ پوشه $dir: یافت نشد</p>";
    }
}

// تست اتصال به MySQL
echo "<h3>5. تست اتصال به MySQL:</h3>";
$mysql_configs = [
    ['host' => 'localhost', 'port' => 3306, 'user' => 'root', 'pass' => ''],
    ['host' => '127.0.0.1', 'port' => 3306, 'user' => 'root', 'pass' => ''],
    ['host' => 'localhost', 'port' => 3306, 'user' => 'root', 'pass' => 'root'],
];

$mysql_connected = false;
foreach ($mysql_configs as $config) {
    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        echo "<p style='color: green;'>✅ MySQL: متصل شد ({$config['host']}:{$config['port']})</p>";
        $mysql_connected = true;
        break;
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ MySQL: خطا ({$config['host']}:{$config['port']}) - " . $e->getMessage() . "</p>";
    }
}

if (!$mysql_connected) {
    echo "<p style='color: red;'><strong>❌ هیچ اتصال MySQL موفق نبود!</strong></p>";
}

// راهنمای حل مشکل
echo "<h3>6. راهنمای حل مشکل:</h3>";
echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<h4>اگر XAMPP کار نمی‌کند:</h4>";
echo "<ol>";
echo "<li>XAMPP Control Panel را باز کنید</li>";
echo "<li>Apache و MySQL را Start کنید</li>";
echo "<li>مطمئن شوید پورت 8080 آزاد است</li>";
echo "<li>فایروال را بررسی کنید</li>";
echo "<li>XAMPP را به عنوان Administrator اجرا کنید</li>";
echo "</ol>";
echo "</div>";

echo "<h3>7. لینک‌های مفید:</h3>";
echo "<p><a href='http://localhost:8080' target='_blank'>صفحه اصلی XAMPP</a></p>";
echo "<p><a href='http://localhost:8080/Aala_Niroo_AMS' target='_blank'>پروژه Aala Niroo</a></p>";
echo "<p><a href='mysql_fix_simple.php' target='_blank'>تست MySQL</a></p>";
echo "<p><a href='login.php' target='_blank'>صفحه لاگین</a></p>";

echo "<hr>";
echo "<p><strong>💡 نکته:</strong> اگر این صفحه را می‌بینید، یعنی Apache کار می‌کند!</p>";
?>