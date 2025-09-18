<?php
/**
 * setup_requests.php - ایجاد جداول سیستم درخواست‌ها
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'test_user';
}

require_once 'config.php';

// فعال کردن نمایش خطاها
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>ایجاد جداول سیستم درخواست‌ها</h2>";

try {
    // ایجاد جدول requests
    echo "<h3>1. ایجاد جدول requests:</h3>";
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
        INDEX idx_status (status),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci";
    
    $pdo->exec($sql_requests);
    echo "✅ جدول requests ایجاد شد<br>";

    // ایجاد جدول request_files
    echo "<h3>2. ایجاد جدول request_files:</h3>";
    $sql_files = "
    CREATE TABLE IF NOT EXISTS request_files (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_id INT NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        file_type VARCHAR(100),
        file_size INT,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
        INDEX idx_request_id (request_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci";
    
    $pdo->exec($sql_files);
    echo "✅ جدول request_files ایجاد شد<br>";

    // ایجاد جدول request_workflow
    echo "<h3>3. ایجاد جدول request_workflow:</h3>";
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
        FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
        INDEX idx_request_id (request_id),
        INDEX idx_assigned_to (assigned_to),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci";
    
    $pdo->exec($sql_workflow);
    echo "✅ جدول request_workflow ایجاد شد<br>";

    // ایجاد جدول request_notifications
    echo "<h3>4. ایجاد جدول request_notifications:</h3>";
    $sql_notifications = "
    CREATE TABLE IF NOT EXISTS request_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_id INT NOT NULL,
        user_id INT NOT NULL,
        notification_type VARCHAR(50) NOT NULL,
        message TEXT NOT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
        INDEX idx_request_id (request_id),
        INDEX idx_user_id (user_id),
        INDEX idx_is_read (is_read)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci";
    
    $pdo->exec($sql_notifications);
    echo "✅ جدول request_notifications ایجاد شد<br>";

    // ایجاد جدول users اگر وجود ندارد
    echo "<h3>5. بررسی جدول users:</h3>";
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        $user_count = $stmt->fetchColumn();
        echo "✅ جدول users موجود با $user_count کاربر<br>";
    } catch (Exception $e) {
        echo "⚠️ جدول users وجود ندارد، در حال ایجاد...<br>";
        $sql_users = "
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(255),
            email VARCHAR(255),
            role VARCHAR(50) DEFAULT 'user',
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci";
        
        $pdo->exec($sql_users);
        
        // اضافه کردن کاربر پیش‌فرض
        $hashed_password = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)");
        $stmt->execute(['admin', $hashed_password, 'مدیر سیستم', 'admin']);
        
        echo "✅ جدول users ایجاد شد و کاربر پیش‌فرض اضافه شد<br>";
    }

    echo "<h3>✅ همه جداول با موفقیت ایجاد شدند!</h3>";
    
    // تست ایجاد درخواست
    echo "<h3>6. تست ایجاد درخواست:</h3>";
    if (function_exists('createRequest')) {
        $data = [
            'requester_id' => 1,
            'requester_name' => 'test_user',
            'item_name' => 'تست آیتم',
            'quantity' => 1,
            'price' => 1000,
            'description' => 'تست توضیحات',
            'priority' => 'کم'
        ];
        
        $request_id = createRequest($pdo, $data);
        if ($request_id) {
            echo "✅ تست ایجاد درخواست: موفق (ID: $request_id)<br>";
        } else {
            echo "❌ تست ایجاد درخواست: ناموفق<br>";
        }
    } else {
        echo "❌ تابع createRequest یافت نشد<br>";
    }

} catch (Exception $e) {
    echo "❌ خطا: " . $e->getMessage() . "<br>";
}

echo "<br><a href='request_management.php'>بازگشت به صفحه اصلی</a>";
?>