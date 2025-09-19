<?php
/**
 * test_request_debug.php - تست و دیباگ request_management_final.php
 */

// فعال کردن نمایش خطاها
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<h2>تست سیستم مدیریت درخواست‌ها</h2>";

try {
    echo "<h3>1. تست اتصال به دیتابیس...</h3>";
    
    // تنظیمات دیتابیس
    $host = 'localhost:3306';
    $dbname = 'aala_niroo';
    $username = 'root';
    $password = '';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_persian_ci"
    ]);
    
    echo "<p style='color: green;'>✅ اتصال به دیتابیس موفق</p>";
    
    echo "<h3>2. تست جدول users...</h3>";
    
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
    }
    
    echo "<h3>3. تست جدول requests...</h3>";
    
    // بررسی وجود جدول requests
    $stmt = $pdo->query("SHOW TABLES LIKE 'requests'");
    $requests_table = $stmt->fetch();
    
    if ($requests_table) {
        echo "<p style='color: green;'>✅ جدول requests وجود دارد</p>";
    } else {
        echo "<p style='color: red;'>❌ جدول requests وجود ندارد</p>";
        
        // ایجاد جدول requests
        echo "<h4>ایجاد جدول requests...</h4>";
        $sql_requests = "
        CREATE TABLE IF NOT EXISTS requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            request_number VARCHAR(50) NOT NULL UNIQUE,
            requester_id INT NOT NULL,
            requester_name VARCHAR(255) NOT NULL,
            item_name VARCHAR(255) NOT NULL,
            quantity INT NOT NULL,
            price DECIMAL(15,2),
            description TEXT,
            priority ENUM('کم', 'متوسط', 'بالا', 'فوری') DEFAULT 'متوسط',
            status ENUM('در انتظار تأیید', 'در حال بررسی', 'تأیید شده', 'رد شده', 'تکمیل شده') DEFAULT 'در انتظار تأیید',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci";
        
        $pdo->exec($sql_requests);
        echo "<p style='color: green;'>✅ جدول requests ایجاد شد</p>";
    }
    
    echo "<h3>4. تست جدول‌های مرتبط...</h3>";
    
    // ایجاد جدول‌های مرتبط
    $tables = [
        'request_files' => "
        CREATE TABLE IF NOT EXISTS request_files (
            id INT AUTO_INCREMENT PRIMARY KEY,
            request_id INT NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_type VARCHAR(100),
            file_size INT,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",
        
        'request_workflow' => "
        CREATE TABLE IF NOT EXISTS request_workflow (
            id INT AUTO_INCREMENT PRIMARY KEY,
            request_id INT NOT NULL,
            step_order INT NOT NULL,
            assigned_to INT,
            department VARCHAR(255),
            status ENUM('در انتظار', 'در حال بررسی', 'تأیید شده', 'رد شده') DEFAULT 'در انتظار',
            comments TEXT,
            action_date TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",
        
        'request_notifications' => "
        CREATE TABLE IF NOT EXISTS request_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            request_id INT NOT NULL,
            user_id INT NOT NULL,
            notification_type VARCHAR(50) NOT NULL,
            message TEXT NOT NULL,
            is_read BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci"
    ];
    
    foreach ($tables as $table_name => $sql) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table_name'");
        $table_exists = $stmt->fetch();
        
        if (!$table_exists) {
            $pdo->exec($sql);
            echo "<p style='color: green;'>✅ جدول $table_name ایجاد شد</p>";
        } else {
            echo "<p style='color: blue;'>ℹ️ جدول $table_name از قبل وجود دارد</p>";
        }
    }
    
    echo "<h3>5. ایجاد کاربر نمونه...</h3>";
    
    // ایجاد کاربر نمونه
    $sample_users = [
        ['username' => 'admin', 'password' => 'admin123', 'full_name' => 'مدیر سیستم', 'role' => 'admin'],
        ['username' => 'user1', 'password' => 'user123', 'full_name' => 'کاربر اول', 'role' => 'user'],
        ['username' => 'user2', 'password' => 'user123', 'full_name' => 'کاربر دوم', 'role' => 'user']
    ];
    
    foreach ($sample_users as $user_data) {
        $hashed_password = password_hash($user_data['password'], PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("
            INSERT INTO users (username, password, full_name, role, is_active) 
            VALUES (?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE 
            password = VALUES(password),
            full_name = VALUES(full_name),
            role = VALUES(role),
            is_active = 1
        ");
        
        try {
            $stmt->execute([
                $user_data['username'],
                $hashed_password,
                $user_data['full_name'],
                $user_data['role']
            ]);
            echo "<p style='color: green;'>✅ کاربر '{$user_data['full_name']}' آماده است</p>";
        } catch (Exception $e) {
            echo "<p style='color: orange;'>⚠️ کاربر '{$user_data['full_name']}' از قبل وجود دارد</p>";
        }
    }
    
    echo "<h3>6. تست نهایی...</h3>";
    
    // تست تابع getUsersForAssignment
    $stmt = $pdo->query("
        SELECT id, username, full_name, role, is_active 
        FROM users 
        WHERE is_active = 1 
        ORDER BY full_name, username
    ");
    $users = $stmt->fetchAll();
    
    echo "<p style='color: green;'>✅ تعداد کاربران فعال: " . count($users) . "</p>";
    
    if (count($users) > 0) {
        echo "<h4>لیست کاربران:</h4>";
        echo "<ul>";
        foreach ($users as $user) {
            echo "<li>{$user['full_name']} ({$user['username']}) - {$user['role']}</li>";
        }
        echo "</ul>";
    }
    
    echo "<h3 style='color: green;'>✅ همه تست‌ها موفق بود! سیستم آماده است.</h3>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ خطا:</h3>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
    echo "<h4>Stack trace:</h4>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>