<?php
/**
 * test_login_debug.php - تست و دیباگ سیستم لاگین
 */

// فعال کردن نمایش خطاها
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<h2>تست سیستم لاگین</h2>";

try {
    echo "<h3>1. تست اتصال به دیتابیس...</h3>";
    
    // تنظیمات دیتابیس
    $host = 'localhost:3306';
    $dbname = 'aala_niroo_ams';
    $username = 'root';
    $password = '';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_persian_ci"
    ]);
    
    echo "<p style='color: green;'>✅ اتصال به دیتابیس موفق</p>";
    
    echo "<h3>2. بررسی جدول users...</h3>";
    
    // بررسی وجود جدول users
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    $users_table = $stmt->fetch();
    
    if ($users_table) {
        echo "<p style='color: green;'>✅ جدول users وجود دارد</p>";
        
        // بررسی ساختار جدول
        $stmt = $pdo->query("DESCRIBE users");
        $columns = $stmt->fetchAll();
        
        echo "<h4>ستون‌های جدول users:</h4>";
        echo "<ul>";
        foreach ($columns as $column) {
            echo "<li>{$column['Field']} - {$column['Type']}</li>";
        }
        echo "</ul>";
        
        // شمارش کاربران
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $user_count = $stmt->fetch()['count'];
        echo "<p>تعداد کاربران: $user_count</p>";
        
        if ($user_count > 0) {
            // نمایش کاربران
            $stmt = $pdo->query("SELECT id, username, full_name, role, is_active FROM users");
            $users = $stmt->fetchAll();
            
            echo "<h4>لیست کاربران:</h4>";
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr><th>ID</th><th>نام کاربری</th><th>نام کامل</th><th>نقش</th><th>وضعیت</th><th>رمز (hash)</th></tr>";
            
            foreach ($users as $user) {
                echo "<tr>";
                echo "<td>{$user['id']}</td>";
                echo "<td>{$user['username']}</td>";
                echo "<td>{$user['full_name']}</td>";
                echo "<td>{$user['role']}</td>";
                echo "<td>" . ($user['is_active'] ? 'فعال' : 'غیرفعال') . "</td>";
                echo "<td>" . substr($user['password'], 0, 20) . "...</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            // تست لاگین با admin
            echo "<h3>3. تست لاگین با admin...</h3>";
            
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
            $stmt->execute(['admin']);
            $admin_user = $stmt->fetch();
            
            if ($admin_user) {
                echo "<p style='color: green;'>✅ کاربر admin یافت شد</p>";
                echo "<p>نام کامل: {$admin_user['full_name']}</p>";
                echo "<p>نقش: {$admin_user['role']}</p>";
                echo "<p>وضعیت: " . ($admin_user['is_active'] ? 'فعال' : 'غیرفعال') . "</p>";
                
                // تست رمز عبور
                $test_password = 'admin';
                if (password_verify($test_password, $admin_user['password'])) {
                    echo "<p style='color: green;'>✅ رمز عبور 'admin' صحیح است</p>";
                } else {
                    echo "<p style='color: red;'>❌ رمز عبور 'admin' اشتباه است</p>";
                    
                    // تست رمزهای دیگر
                    $test_passwords = ['admin123', 'password', '123456', ''];
                    foreach ($test_passwords as $test_pass) {
                        if (password_verify($test_pass, $admin_user['password'])) {
                            echo "<p style='color: green;'>✅ رمز عبور صحیح: '$test_pass'</p>";
                            break;
                        }
                    }
                }
                
            } else {
                echo "<p style='color: red;'>❌ کاربر admin یافت نشد</p>";
                
                // ایجاد کاربر admin
                echo "<h4>ایجاد کاربر admin...</h4>";
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
                echo "<p style='color: green;'>✅ کاربر admin ایجاد شد</p>";
            }
            
        } else {
            echo "<p style='color: red;'>❌ هیچ کاربری در سیستم وجود ندارد</p>";
            
            // ایجاد کاربران نمونه
            echo "<h4>ایجاد کاربران نمونه...</h4>";
            $sample_users = [
                ['username' => 'admin', 'password' => 'admin', 'full_name' => 'مدیر سیستم', 'role' => 'admin'],
                ['username' => 'user1', 'password' => 'user123', 'full_name' => 'کاربر اول', 'role' => 'user'],
                ['username' => 'user2', 'password' => 'user123', 'full_name' => 'کاربر دوم', 'role' => 'user']
            ];
            
            foreach ($sample_users as $user_data) {
                $hashed_password = password_hash($user_data['password'], PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, password, full_name, role, is_active) 
                    VALUES (?, ?, ?, ?, 1)
                ");
                
                $stmt->execute([
                    $user_data['username'],
                    $hashed_password,
                    $user_data['full_name'],
                    $user_data['role']
                ]);
                
                echo "<p style='color: green;'>✅ کاربر '{$user_data['username']}' ایجاد شد (رمز: {$user_data['password']})</p>";
            }
        }
        
    } else {
        echo "<p style='color: red;'>❌ جدول users وجود ندارد</p>";
        
        // ایجاد جدول users
        echo "<h4>ایجاد جدول users...</h4>";
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
        echo "<p style='color: green;'>✅ جدول users ایجاد شد</p>";
        
        // ایجاد کاربر admin
        $admin_password = password_hash('admin', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO users (username, password, full_name, role, is_active) 
            VALUES (?, ?, ?, ?, 1)
        ");
        $stmt->execute(['admin', $admin_password, 'مدیر سیستم', 'admin']);
        echo "<p style='color: green;'>✅ کاربر admin ایجاد شد (رمز: admin)</p>";
    }
    
    echo "<h3 style='color: green;'>✅ تست کامل شد!</h3>";
    echo "<p><a href='login.php'>برو به صفحه لاگین</a></p>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ خطا:</h3>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
    echo "<h4>Stack trace:</h4>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>