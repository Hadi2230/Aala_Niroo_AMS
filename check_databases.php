<?php
// check_databases.php - بررسی دیتابیس‌های موجود

echo "<h2>بررسی دیتابیس‌های موجود</h2>";

// تنظیمات مختلف برای اتصال
$configs = [
    ['host' => 'localhost', 'port' => '3306', 'username' => 'root', 'password' => ''],
    ['host' => 'localhost', 'port' => '3306', 'username' => 'root', 'password' => 'root'],
    ['host' => 'localhost', 'port' => '3306', 'username' => 'root', 'password' => 'password'],
    ['host' => '127.0.0.1', 'port' => '3306', 'username' => 'root', 'password' => ''],
];

foreach ($configs as $i => $config) {
    echo "<h3>تنظیمات " . ($i + 1) . ":</h3>";
    echo "Host: " . $config['host'] . "<br>";
    echo "Port: " . $config['port'] . "<br>";
    echo "Username: " . $config['username'] . "<br>";
    echo "Password: " . ($config['password'] ? '***' : '(خالی)') . "<br>";
    
    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        echo "<span style='color: green;'>✅ اتصال موفق</span><br>";
        
        // لیست دیتابیس‌ها
        $stmt = $pdo->query("SHOW DATABASES");
        $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "<strong>دیتابیس‌های موجود:</strong><br>";
        foreach ($databases as $db) {
            if (strpos($db, 'aala') !== false) {
                echo "<span style='color: blue;'>📁 $db</span><br>";
            } else {
                echo "📁 $db<br>";
            }
        }
        
        // بررسی وجود جدول users در هر دیتابیس aala
        foreach ($databases as $db) {
            if (strpos($db, 'aala') !== false) {
                try {
                    $pdo->exec("USE `$db`");
                    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
                    if ($stmt->rowCount() > 0) {
                        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
                        $count = $stmt->fetch()['count'];
                        echo "<span style='color: green;'>✅ جدول users در $db: $count کاربر</span><br>";
                    }
                } catch (Exception $e) {
                    echo "<span style='color: red;'>❌ خطا در $db: " . $e->getMessage() . "</span><br>";
                }
            }
        }
        
    } catch (Exception $e) {
        echo "<span style='color: red;'>❌ خطا: " . $e->getMessage() . "</span><br>";
    }
    
    echo "<hr>";
}

echo "<h3>نتیجه‌گیری:</h3>";
echo "<p>لطفاً تنظیمات صحیح را انتخاب کنید و دیتابیس مورد نظر را مشخص کنید.</p>";
?>