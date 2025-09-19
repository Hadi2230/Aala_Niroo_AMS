<?php
/**
 * fix_database_conflict.php - حل مشکل تداخل دیتابیس‌ها
 */

// فعال کردن نمایش خطاها
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔧 حل مشکل تداخل دیتابیس‌ها</h2>";

// بررسی دیتابیس‌های موجود
echo "<h3>1. بررسی دیتابیس‌های موجود</h3>";

$test_configs = [
    ['host' => 'localhost', 'port' => '3306', 'user' => 'root', 'pass' => ''],
    ['host' => 'localhost', 'port' => '3306', 'user' => 'root', 'pass' => 'root'],
    ['host' => '127.0.0.1', 'port' => '3306', 'user' => 'root', 'pass' => ''],
];

$working_config = null;
$databases = [];

foreach ($test_configs as $config) {
    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        echo "<p style='color: green;'>✅ اتصال موفق: {$config['host']}:{$config['port']}</p>";
        
        // دریافت لیست دیتابیس‌ها
        $stmt = $pdo->query("SHOW DATABASES");
        $dbs = $stmt->fetchAll();
        
        echo "<h4>دیتابیس‌های موجود:</h4>";
        echo "<ul>";
        foreach ($dbs as $db) {
            $db_name = $db['Database'];
            $is_target = (strpos($db_name, 'aala_niroo') !== false);
            $style = $is_target ? "color: green; font-weight: bold;" : "";
            echo "<li style='$style'>$db_name</li>";
            
            if ($is_target) {
                $databases[] = $db_name;
            }
        }
        echo "</ul>";
        
        $working_config = $config;
        break;
        
    } catch (PDOException $e) {
        echo "<p style='color: red;'>❌ خطا: " . $e->getMessage() . "</p>";
    }
}

if (!$working_config) {
    echo "<div style='background: #f8d7da; padding: 20px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4 style='color: #721c24; margin: 0 0 15px 0;'>❌ هیچ اتصالی کار نکرد!</h4>";
    echo "<p>لطفاً XAMPP را بررسی کنید و MySQL را Start کنید.</p>";
    echo "</div>";
    exit;
}

// بررسی محتوای دیتابیس‌ها
echo "<h3>2. بررسی محتوای دیتابیس‌ها</h3>";

foreach ($databases as $db_name) {
    echo "<h4>دیتابیس: $db_name</h4>";
    
    try {
        $pdo->exec("USE $db_name");
        
        // بررسی جداول
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll();
        
        if (empty($tables)) {
            echo "<p style='color: orange;'>⚠️ هیچ جدولی ندارد</p>";
        } else {
            echo "<p style='color: green;'>✅ جداول موجود:</p>";
            echo "<ul>";
            foreach ($tables as $table) {
                $table_name = $table['Tables_in_' . $db_name];
                echo "<li>$table_name</li>";
            }
            echo "</ul>";
            
            // بررسی جدول users
            if (in_array('users', array_column($tables, 'Tables_in_' . $db_name))) {
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
                $user_count = $stmt->fetch()['count'];
                echo "<p style='color: blue;'>👥 تعداد کاربران: $user_count</p>";
                
                if ($user_count > 0) {
                    $stmt = $pdo->query("SELECT username, full_name, role FROM users LIMIT 5");
                    $users = $stmt->fetchAll();
                    
                    echo "<p>نمونه کاربران:</p>";
                    echo "<ul>";
                    foreach ($users as $user) {
                        echo "<li>{$user['username']} - {$user['full_name']} - {$user['role']}</li>";
                    }
                    echo "</ul>";
                }
            }
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ خطا در بررسی دیتابیس: " . $e->getMessage() . "</p>";
    }
    
    echo "<hr>";
}

// راه‌حل پیشنهادی
echo "<h3>3. راه‌حل پیشنهادی</h3>";

if (count($databases) > 1) {
    echo "<div style='background: #fff3cd; padding: 20px; border: 1px solid #ffeaa7; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4 style='color: #856404; margin: 0 0 15px 0;'>⚠️ مشکل: چندین دیتابیس مشابه</h4>";
    echo "<p>شما چندین دیتابیس مشابه دارید که باعث سردرگمی سیستم می‌شود.</p>";
    echo "<h5>راه‌حل:</h5>";
    echo "<ol style='margin: 10px 0; padding-right: 20px;'>";
    echo "<li><strong>یکی از دیتابیس‌ها را انتخاب کنید</strong> (ترجیحاً aala_niroo_ams)</li>";
    echo "<li><strong>دیتابیس دیگر را حذف کنید</strong> یا نام آن را تغییر دهید</li>";
    echo "<li><strong>config.php را به‌روزرسانی کنید</strong></li>";
    echo "</ol>";
    echo "</div>";
    
    // ایجاد دیتابیس یکپارچه
    echo "<h4>ایجاد دیتابیس یکپارچه</h4>";
    
    try {
        $pdo->exec("CREATE DATABASE IF NOT EXISTS aala_niroo_ams_final CHARACTER SET utf8mb4 COLLATE utf8mb4_persian_ci");
        $pdo->exec("USE aala_niroo_ams_final");
        
        // ایجاد جدول users
        $sql_users = "
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(255),
            email VARCHAR(255),
            role VARCHAR(50) DEFAULT 'user',
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci";
        
        $pdo->exec($sql_users);
        
        // ایجاد کاربر admin
        $admin_password = password_hash('admin', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO users (username, password, full_name, role, is_active) 
            VALUES (?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE 
            password = VALUES(password),
            full_name = VALUES(full_name),
            role = VALUES(role),
            is_active = 1
        ");
        
        $stmt->execute(['admin', $admin_password, 'مدیر سیستم', 'admin']);
        
        echo "<p style='color: green;'>✅ دیتابیس یکپارچه aala_niroo_ams_final ایجاد شد</p>";
        echo "<p style='color: green;'>✅ کاربر admin ایجاد شد (رمز: admin)</p>";
        
        // به‌روزرسانی config.php
        echo "<h4>به‌روزرسانی config.php</h4>";
        
        $config_content = file_get_contents('config.php');
        $new_config_content = str_replace("'dbname' => 'aala_niroo_ams'", "'dbname' => 'aala_niroo_ams_final'", $config_content);
        
        if (file_put_contents('config.php', $new_config_content)) {
            echo "<p style='color: green;'>✅ config.php به‌روزرسانی شد</p>";
        } else {
            echo "<p style='color: red;'>❌ خطا در به‌روزرسانی config.php</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ خطا در ایجاد دیتابیس یکپارچه: " . $e->getMessage() . "</p>";
    }
    
} else if (count($databases) == 1) {
    $db_name = $databases[0];
    echo "<div style='background: #d4edda; padding: 20px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4 style='color: #155724; margin: 0 0 15px 0;'>✅ دیتابیس $db_name انتخاب شد</h4>";
    echo "<p>config.php را به‌روزرسانی می‌کنیم...</p>";
    echo "</div>";
    
    // به‌روزرسانی config.php
    $config_content = file_get_contents('config.php');
    $new_config_content = str_replace("'dbname' => 'aala_niroo_ams'", "'dbname' => '$db_name'", $config_content);
    
    if (file_put_contents('config.php', $new_config_content)) {
        echo "<p style='color: green;'>✅ config.php به‌روزرسانی شد</p>";
    } else {
        echo "<p style='color: red;'>❌ خطا در به‌روزرسانی config.php</p>";
    }
    
} else {
    echo "<div style='background: #f8d7da; padding: 20px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4 style='color: #721c24; margin: 0 0 15px 0;'>❌ هیچ دیتابیس aala_niroo یافت نشد</h4>";
    echo "<p>لطفاً دیتابیس را در phpMyAdmin ایجاد کنید.</p>";
    echo "</div>";
}

// تست نهایی
echo "<h3>4. تست نهایی</h3>";

try {
    $pdo->exec("USE aala_niroo_ams_final");
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $user_count = $stmt->fetch()['count'];
    
    if ($user_count > 0) {
        echo "<p style='color: green;'>✅ دیتابیس آماده است</p>";
        echo "<p style='color: green;'>✅ تعداد کاربران: $user_count</p>";
        
        echo "<div style='background: #d4edda; padding: 20px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 20px 0;'>";
        echo "<h4 style='color: #155724; margin: 0 0 15px 0;'>🎉 مشکل حل شد!</h4>";
        echo "<p style='margin: 10px 0;'><strong>دیتابیس:</strong> aala_niroo_ams_final</p>";
        echo "<p style='margin: 10px 0;'><strong>کاربر:</strong> admin</p>";
        echo "<p style='margin: 10px 0;'><strong>رمز:</strong> admin</p>";
        echo "<p style='margin: 15px 0 0 0;'>";
        echo "<a href='login.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-left: 10px;'>برو به لاگین</a>";
        echo "<a href='test_login_debug.php' style='background: #17a2b8; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-left: 10px;'>تست لاگین</a>";
        echo "</p>";
        echo "</div>";
        
    } else {
        echo "<p style='color: red;'>❌ هیچ کاربری در دیتابیس وجود ندارد</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ خطا در تست نهایی: " . $e->getMessage() . "</p>";
}
?>