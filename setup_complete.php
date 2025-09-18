<?php
/**
 * setup_complete.php - راه‌اندازی کامل سیستم درخواست‌ها
 */

// شروع session
session_start();

// تنظیمات امنیتی
error_reporting(E_ALL);
ini_set('display_errors', 1);

// تنظیمات دیتابیس
$host = 'localhost:3306';
$dbname = 'aala_niroo_ams';
$username = 'root';
$password = '';

echo "<!DOCTYPE html>
<html dir='rtl' lang='fa'>
<head>
    <meta charset='UTF-8'>
    <title>راه‌اندازی سیستم درخواست‌ها</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        body { font-family: 'Tahoma', sans-serif; }
        .setup-section { margin: 20px 0; padding: 20px; border: 1px solid #ddd; border-radius: 10px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
    </style>
</head>
<body>
<div class='container mt-4'>";

echo "<h1 class='text-center mb-4'>راه‌اندازی کامل سیستم درخواست‌ها</h1>";

// 1. تست اتصال دیتابیس
echo "<div class='setup-section'>
    <h3>1. تست اتصال دیتابیس</h3>";

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo "<p class='success'>✅ اتصال به MySQL موفق</p>";
    
    // بررسی وجود دیتابیس
    $stmt = $pdo->query("SHOW DATABASES LIKE '$dbname'");
    if ($stmt->rowCount() == 0) {
        // ایجاد دیتابیس
        $pdo->exec("CREATE DATABASE $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_persian_ci");
        echo "<p class='success'>✅ دیتابیس $dbname ایجاد شد</p>";
    } else {
        echo "<p class='success'>✅ دیتابیس $dbname موجود است</p>";
    }
    
    // اتصال به دیتابیس
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo "<p class='success'>✅ اتصال به دیتابیس $dbname موفق</p>";
    
} catch (Exception $e) {
    echo "<p class='error'>❌ خطا در اتصال: " . $e->getMessage() . "</p>";
    echo "</div></body></html>";
    exit;
}
echo "</div>";

// 2. ایجاد جداول
echo "<div class='setup-section'>
    <h3>2. ایجاد جداول دیتابیس</h3>";

try {
    // جدول users
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
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_username (username),
        INDEX idx_role (role)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci";
    
    $pdo->exec($sql_users);
    echo "<p class='success'>✅ جدول users ایجاد شد</p>";
    
    // جدول requests
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
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_request_number (request_number),
        INDEX idx_requester_id (requester_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci";
    
    $pdo->exec($sql_requests);
    echo "<p class='success'>✅ جدول requests ایجاد شد</p>";
    
    // جدول request_files
    $sql_files = "
    CREATE TABLE IF NOT EXISTS request_files (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_id INT NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        file_type VARCHAR(100),
        file_size INT,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci";
    
    $pdo->exec($sql_files);
    echo "<p class='success'>✅ جدول request_files ایجاد شد</p>";
    
    // جدول request_workflow
    $sql_workflow = "
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci";
    
    $pdo->exec($sql_workflow);
    echo "<p class='success'>✅ جدول request_workflow ایجاد شد</p>";
    
    // جدول request_notifications
    $sql_notifications = "
    CREATE TABLE IF NOT EXISTS request_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_id INT NOT NULL,
        user_id INT NOT NULL,
        notification_type VARCHAR(50) NOT NULL,
        message TEXT NOT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci";
    
    $pdo->exec($sql_notifications);
    echo "<p class='success'>✅ جدول request_notifications ایجاد شد</p>";
    
} catch (Exception $e) {
    echo "<p class='error'>❌ خطا در ایجاد جداول: " . $e->getMessage() . "</p>";
}
echo "</div>";

// 3. ایجاد کاربران نمونه
echo "<div class='setup-section'>
    <h3>3. ایجاد کاربران نمونه</h3>";

try {
    // بررسی وجود کاربران
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $user_count = $stmt->fetchColumn();
    
    if ($user_count == 0) {
        $test_users = [
            ['username' => 'admin', 'password' => password_hash('admin123', PASSWORD_DEFAULT), 'full_name' => 'مدیر سیستم', 'email' => 'admin@example.com', 'role' => 'admin'],
            ['username' => 'user1', 'password' => password_hash('user123', PASSWORD_DEFAULT), 'full_name' => 'کاربر اول', 'email' => 'user1@example.com', 'role' => 'user'],
            ['username' => 'user2', 'password' => password_hash('user123', PASSWORD_DEFAULT), 'full_name' => 'کاربر دوم', 'email' => 'user2@example.com', 'role' => 'user'],
            ['username' => 'manager', 'password' => password_hash('manager123', PASSWORD_DEFAULT), 'full_name' => 'مدیر بخش', 'email' => 'manager@example.com', 'role' => 'manager']
        ];
        
        foreach ($test_users as $user) {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, role, is_active) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->execute([$user['username'], $user['password'], $user['full_name'], $user['email'], $user['role']]);
        }
        echo "<p class='success'>✅ کاربران نمونه ایجاد شدند</p>";
        echo "<div class='alert alert-info'>
            <h5>اطلاعات ورود:</h5>
            <ul>
                <li><strong>ادمین:</strong> admin / admin123</li>
                <li><strong>کاربر 1:</strong> user1 / user123</li>
                <li><strong>کاربر 2:</strong> user2 / user123</li>
                <li><strong>مدیر:</strong> manager / manager123</li>
            </ul>
        </div>";
    } else {
        echo "<p class='warning'>⚠️ $user_count کاربر موجود است</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ خطا در ایجاد کاربران: " . $e->getMessage() . "</p>";
}
echo "</div>";

// 4. ایجاد پوشه‌ها
echo "<div class='setup-section'>
    <h3>4. ایجاد پوشه‌های مورد نیاز</h3>";

$folders = ['logs', 'uploads', 'uploads/requests', 'uploads/files'];
foreach ($folders as $folder) {
    if (!is_dir($folder)) {
        if (mkdir($folder, 0755, true)) {
            echo "<p class='success'>✅ پوشه $folder ایجاد شد</p>";
        } else {
            echo "<p class='error'>❌ خطا در ایجاد پوشه $folder</p>";
        }
    } else {
        echo "<p class='success'>✅ پوشه $folder موجود است</p>";
    }
}
echo "</div>";

// 5. تست سیستم
echo "<div class='setup-section'>
    <h3>5. تست سیستم</h3>";

try {
    // تست ایجاد درخواست
    require_once 'config_complete.php';
    
    $test_data = [
        'requester_id' => 1,
        'requester_name' => 'admin',
        'item_name' => 'تست سیستم راه‌اندازی',
        'quantity' => 1,
        'price' => 10000,
        'description' => 'این یک درخواست تست برای بررسی سیستم است',
        'priority' => 'متوسط'
    ];
    
    $request_id = createRequest($pdo, $test_data);
    if ($request_id) {
        echo "<p class='success'>✅ تست ایجاد درخواست موفق</p>";
        
        // حذف درخواست تست
        $stmt = $pdo->prepare("DELETE FROM requests WHERE id = ?");
        $stmt->execute([$request_id]);
        echo "<p class='success'>✅ تست حذف درخواست موفق</p>";
    } else {
        echo "<p class='error'>❌ خطا در تست ایجاد درخواست</p>";
    }
    
} catch (Exception $e) {
    echo "<p class='error'>❌ خطا در تست سیستم: " . $e->getMessage() . "</p>";
}
echo "</div>";

// 6. لینک‌های دسترسی
echo "<div class='setup-section'>
    <h3>6. لینک‌های دسترسی</h3>
    <div class='row'>
        <div class='col-md-6'>
            <div class='card'>
                <div class='card-body'>
                    <h5 class='card-title'>سیستم اصلی</h5>
                    <ul class='list-unstyled'>
                        <li><a href='request_management_final.php' class='btn btn-primary btn-sm mb-2'>ایجاد درخواست</a></li>
                        <li><a href='request_workflow_professional.php' class='btn btn-success btn-sm mb-2'>سیستم حرفه‌ای</a></li>
                        <li><a href='request_tracking_final.php' class='btn btn-info btn-sm mb-2'>پیگیری درخواست‌ها</a></li>
                        <li><a href='request_reports.php' class='btn btn-warning btn-sm mb-2'>گزارش‌ها</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class='col-md-6'>
            <div class='card'>
                <div class='card-body'>
                    <h5 class='card-title'>تست و راه‌اندازی</h5>
                    <ul class='list-unstyled'>
                        <li><a href='test_final_system.php' class='btn btn-secondary btn-sm mb-2'>تست کامل سیستم</a></li>
                        <li><a href='setup_complete.php' class='btn btn-outline-primary btn-sm mb-2'>راه‌اندازی مجدد</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>";

echo "<div class='alert alert-success text-center'>
    <h4>🎉 راه‌اندازی کامل شد!</h4>
    <p>سیستم مدیریت درخواست‌ها آماده استفاده است.</p>
</div>";

echo "</div></body></html>";
?>