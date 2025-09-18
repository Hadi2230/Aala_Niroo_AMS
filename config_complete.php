<?php
/**
 * config_complete.php - نسخه کامل و حرفه‌ای config.php
 */

// شروع session اگر شروع نشده
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تنظیمات امنیتی
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php-errors.log');

// ایجاد پوشه logs اگر وجود ندارد
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

// ایجاد پوشه uploads اگر وجود ندارد
if (!is_dir(__DIR__ . '/uploads')) {
    mkdir(__DIR__ . '/uploads', 0755, true);
    mkdir(__DIR__ . '/uploads/requests', 0755, true);
    mkdir(__DIR__ . '/uploads/files', 0755, true);
}

// تنظیمات دیتابیس
$host = 'localhost:3306';
$dbname = 'aala_niroo_ams';
$username = 'root';
$password = '';

// تنظیمات زمانzone
date_default_timezone_set('Asia/Tehran');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_persian_ci"
    ]);
} catch (PDOException $e) {
    error_log("[" . date('Y-m-d H:i:s') . "] خطا در اتصال به دیتابیس: " . $e->getMessage());
    die("<div style='text-align: center; padding: 50px; font-family: Tahoma;'>
        <h2>خطا در اتصال به سیستم</h2>
        <p>لطفاً چند دقیقه دیگر تلاش کنید یا با پشتیبانی تماس بگیرید.</p>
        <p><small>خطای سیستمی: " . $e->getMessage() . "</small></p>
        <p><a href='setup_database.php' class='btn btn-primary'>راه‌اندازی دیتابیس</a></p>
        </div>");
}

// تولید token برای جلوگیری از CSRF اگر وجود ندارد
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * تولید شماره درخواست خودکار
 */
function generateRequestNumber($pdo) {
    $today = date('Ymd');
    $prefix = "REQ-{$today}-";
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM requests WHERE request_number LIKE ?");
        $stmt->execute([$prefix . '%']);
        $result = $stmt->fetch();
        
        $count = ($result['count'] ?? 0) + 1;
        return $prefix . str_pad($count, 3, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        error_log("Error generating request number: " . $e->getMessage());
        return $prefix . '001';
    }
}

/**
 * ایجاد درخواست جدید
 */
function createRequest($pdo, $data) {
    try {
        $request_number = generateRequestNumber($pdo);
        
        $stmt = $pdo->prepare("
            INSERT INTO requests (request_number, requester_id, requester_name, item_name, quantity, price, description, priority, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'در انتظار تأیید')
        ");
        
        $stmt->execute([
            $request_number,
            $data['requester_id'],
            $data['requester_name'],
            $data['item_name'],
            $data['quantity'],
            $data['price'],
            $data['description'],
            $data['priority']
        ]);
        
        return $pdo->lastInsertId();
    } catch (Exception $e) {
        error_log("Error creating request: " . $e->getMessage());
        return false;
    }
}

/**
 * آپلود فایل درخواست
 */
function uploadRequestFile($pdo, $request_id, $file, $upload_dir = 'uploads/requests/') {
    try {
        // ایجاد پوشه آپلود اگر وجود ندارد
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $file_name = uniqid() . '.' . $file_extension;
        $file_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            $stmt = $pdo->prepare("
                INSERT INTO request_files (request_id, file_name, file_path, file_type, file_size) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $request_id,
                $file['name'],
                $file_path,
                $file['type'],
                $file['size']
            ]);
            
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Error uploading file: " . $e->getMessage());
        return false;
    }
}

/**
 * ایجاد گردش کار درخواست
 */
function createRequestWorkflow($pdo, $request_id, $assignments) {
    try {
        foreach ($assignments as $index => $assignment) {
            $stmt = $pdo->prepare("
                INSERT INTO request_workflow (request_id, step_order, assigned_to, department, status) 
                VALUES (?, ?, ?, ?, 'در انتظار')
            ");
            
            $stmt->execute([
                $request_id,
                $index + 1,
                $assignment['user_id'],
                $assignment['department']
            ]);
            
            // ایجاد اعلان
            createRequestNotification($pdo, $assignment['user_id'], $request_id, 'assignment', 
                'درخواست جدید به شما اختصاص یافت');
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error creating workflow: " . $e->getMessage());
        return false;
    }
}

/**
 * دریافت کاربران برای انتساب
 */
function getUsersForAssignment($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT id, username, full_name, role, is_active 
            FROM users 
            WHERE is_active = 1 
            ORDER BY full_name, username
        ");
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error fetching users: " . $e->getMessage());
        return [];
    }
}

/**
 * ایجاد اعلان درخواست
 */
function createRequestNotification($pdo, $user_id, $request_id, $type, $message) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO request_notifications (request_id, user_id, notification_type, message) 
            VALUES (?, ?, ?, ?)
        ");
        
        $stmt->execute([$request_id, $user_id, $type, $message]);
        return true;
    } catch (Exception $e) {
        error_log("Error creating notification: " . $e->getMessage());
        return false;
    }
}

/**
 * تابع sanitizeInput
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * تابع hasPermission
 */
function hasPermission($permission) {
    // برای تست، همیشه true برمی‌گرداند
    return true;
}

/**
 * بررسی ادمین بودن کاربر
 */
function is_admin($user_id = null) {
    global $pdo;
    
    if ($user_id === null) {
        $user_id = $_SESSION['user_id'] ?? 0;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $role = $stmt->fetchColumn();
        return ($role === 'admin');
    } catch (Exception $e) {
        error_log("Error checking admin status: " . $e->getMessage());
        return false;
    }
}

/**
 * ایجاد جداول دیتابیس
 */
function createDatabaseTables($pdo) {
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
        
        return true;
    } catch (Exception $e) {
        error_log("Error creating tables: " . $e->getMessage());
        return false;
    }
}

// ایجاد جداول اگر وجود ندارند
try {
    // بررسی وجود جدول requests
    $stmt = $pdo->query("SELECT COUNT(*) FROM requests");
} catch (Exception $e) {
    // ایجاد جداول
    createDatabaseTables($pdo);
}

// ایجاد کاربر ادمین پیش‌فرض اگر وجود ندارد
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    $admin_count = $stmt->fetchColumn();
    
    if ($admin_count == 0) {
        $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO users (username, password, full_name, email, role, is_active) 
            VALUES ('admin', ?, 'مدیر سیستم', 'admin@example.com', 'admin', 1)
        ");
        $stmt->execute([$admin_password]);
    }
} catch (Exception $e) {
    error_log("Error creating admin user: " . $e->getMessage());
}
?>