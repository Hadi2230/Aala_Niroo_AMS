<?php
// config.php - نسخه تمیز و بدون توابع تکراری

// شروع session اگر شروع نشده
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تنظیمات امنیتی
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php-errors.log');

// تنظیمات دیتابیس
$db_path = __DIR__ . '/aala_niroo_ams.db';

// تنظیمات زمانzone
date_default_timezone_set('Asia/Tehran');

try {
    $pdo = new PDO("sqlite:$db_path", null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    error_log("[" . date('Y-m-d H:i:s') . "] خطا در اتصال به دیتابیس: " . $e->getMessage());
    die("<div style='text-align: center; padding: 50px; font-family: Tahoma;'>
        <h2>خطا در اتصال به سیستم</h2>
        <p>لطفاً چند دقیقه دیگر تلاش کنید یا با پشتیبانی تماس بگیرید.</p>
        <p><small>خطای سیستمی: " . $e->getMessage() . "</small></p>
        </div>");
}

// تولید token برای جلوگیری از CSRF اگر وجود ندارد
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// تابع بررسی دسترسی‌ها
function hasPermission($permission) {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    // مدیران همه دسترسی‌ها را دارند
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'مدیر') {
        return true;
    }
    
    // بررسی دسترسی‌های سفارشی
    if (isset($_SESSION['permissions']) && is_array($_SESSION['permissions'])) {
        return in_array($permission, $_SESSION['permissions']) || in_array('*', $_SESSION['permissions']);
    }
    
    return false;
}

// ایجاد جداول فقط یک بار در طول session
if (!isset($_SESSION['tables_created'])) {
    createDatabaseTables($pdo);
    $_SESSION['tables_created'] = true;
}

/**
 * ایجاد جداول دیتابیس
 */
function createDatabaseTables($pdo) {
    try {
        // جدول کاربران
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(100) NOT NULL,
            email VARCHAR(100),
            role VARCHAR(20) DEFAULT 'کاربر',
            is_active BOOLEAN DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_login DATETIME
        )");

        // جدول مشتریان
        $pdo->exec("CREATE TABLE IF NOT EXISTS customers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            full_name VARCHAR(100) NOT NULL,
            phone VARCHAR(20),
            company VARCHAR(100),
            address TEXT,
            email VARCHAR(100),
            company_email VARCHAR(100),
            notification_type VARCHAR(20) DEFAULT 'none',
            customer_type ENUM('حقیقی', 'حقوقی') DEFAULT 'حقیقی',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // جدول دارایی‌ها
        $pdo->exec("CREATE TABLE IF NOT EXISTS assets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(100) NOT NULL,
            type VARCHAR(50),
            model VARCHAR(100),
            serial_number VARCHAR(100),
            location VARCHAR(100),
            status VARCHAR(50) DEFAULT 'فعال',
            purchase_date DATE,
            warranty_expiry DATE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // جدول تخصیص‌ها
        $pdo->exec("CREATE TABLE IF NOT EXISTS assignments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            asset_id INTEGER,
            customer_id INTEGER,
            assignment_date DATE,
            return_date DATE,
            status VARCHAR(50) DEFAULT 'فعال',
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (asset_id) REFERENCES assets(id),
            FOREIGN KEY (customer_id) REFERENCES customers(id)
        )");

        // جدول تیکت‌ها
        $pdo->exec("CREATE TABLE IF NOT EXISTS tickets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            customer_id INTEGER NOT NULL,
            asset_id INTEGER,
            title VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            priority VARCHAR(20) DEFAULT 'متوسط',
            status VARCHAR(50) DEFAULT 'جدید',
            ticket_number VARCHAR(50) UNIQUE NOT NULL,
            created_by INTEGER NOT NULL,
            assigned_to INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES customers(id),
            FOREIGN KEY (asset_id) REFERENCES assets(id),
            FOREIGN KEY (created_by) REFERENCES users(id),
            FOREIGN KEY (assigned_to) REFERENCES users(id)
        )");

        // جدول تاریخچه تیکت‌ها
        $pdo->exec("CREATE TABLE IF NOT EXISTS ticket_history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ticket_id INTEGER NOT NULL,
            action VARCHAR(100) NOT NULL,
            old_value TEXT,
            new_value TEXT,
            performed_by INTEGER NOT NULL,
            performed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            notes TEXT,
            FOREIGN KEY (ticket_id) REFERENCES tickets(id),
            FOREIGN KEY (performed_by) REFERENCES users(id)
        )");

        // جدول اعلان‌ها
        $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            type VARCHAR(50) DEFAULT 'سیستم',
            priority VARCHAR(20) DEFAULT 'متوسط',
            is_read BOOLEAN DEFAULT 0,
            read_at DATETIME,
            related_id INTEGER,
            related_type VARCHAR(50),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )");

        // جدول پیام‌ها
        $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sender_id INTEGER NOT NULL,
            receiver_id INTEGER NOT NULL,
            subject VARCHAR(500) DEFAULT '',
            message TEXT NOT NULL,
            attachment_path VARCHAR(500),
            attachment_name VARCHAR(255),
            attachment_type VARCHAR(50),
            is_read BOOLEAN DEFAULT 0,
            read_at DATETIME,
            related_ticket_id INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sender_id) REFERENCES users(id),
            FOREIGN KEY (receiver_id) REFERENCES users(id)
        )");

        // جدول لاگ سیستم
        $pdo->exec("CREATE TABLE IF NOT EXISTS system_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            action VARCHAR(100) NOT NULL,
            description TEXT,
            severity VARCHAR(20) DEFAULT 'info',
            module VARCHAR(50),
            ip_address VARCHAR(45),
            user_agent TEXT,
            request_data TEXT,
            response_data TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )");

        // ایجاد کاربر مدیر پیش‌فرض
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'مدیر'");
        $stmt->execute();
        $admin_count = $stmt->fetchColumn();
        
        if ($admin_count == 0) {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT), 'مدیر سیستم', 'admin@example.com', 'مدیر']);
        }

    } catch (Exception $e) {
        error_log("Error creating database tables: " . $e->getMessage());
    }
}

/**
 * تابع لاگ‌گیری عملیات
 */
function logAction($pdo, $action, $description = '', $severity = 'info', $module = null, $request_data = null, $response_data = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO system_logs (user_id, action, description, severity, module, ip_address, user_agent, request_data, response_data) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $action,
            $description,
            $severity,
            $module,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $request_data ? json_encode($request_data) : null,
            $response_data ? json_encode($response_data) : null
        ]);
    } catch (Exception $e) {
        error_log("Error logging action: " . $e->getMessage());
    }
}

/**
 * تابع تبدیل تاریخ میلادی به شمسی
 */
function jalali_format($date) {
    if (empty($date) || $date === '-') {
        return '-';
    }
    
    try {
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return $date;
        }
        
        // تبدیل ساده به شمسی (برای نمایش)
        $year = date('Y', $timestamp);
        $month = date('m', $timestamp);
        $day = date('d', $timestamp);
        
        // تبدیل تقریبی (برای نمایش بهتر)
        $jalali_year = $year - 621;
        if ($month > 3) {
            $jalali_year++;
        }
        
        return $jalali_year . '/' . $month . '/' . $day;
    } catch (Exception $e) {
        return $date;
    }
}

// آپلود فایل با اعتبارسنجی
function uploadFile($file, $target_dir, $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'pdf']) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('خطا در آپلود فایل');
    }
    
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_ext, $allowed_types)) {
        throw new Exception('نوع فایل مجاز نیست');
    }
    
    if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
        throw new Exception('حجم فایل بیش از حد مجاز است');
    }
    
    $file_name = time() . '_' . uniqid() . '.' . $file_ext;
    $target_file = $target_dir . $file_name;
    
    if (!move_uploaded_file($file['tmp_name'], $target_file)) {
        throw new Exception('خطا در ذخیره فایل');
    }
    
    return $target_file;
}

// بررسی CSRF token
function verifyCsrfToken() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            die('درخواست نامعتبر است - CSRF Token validation failed');
        }
    }
}

// تولید فیلد CSRF
function csrf_field() {
    echo '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
}

// پاکسازی ورودی
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// ارسال اعلان
function sendNotification($pdo, $user_id, $title, $message, $type = 'سیستم', $priority = 'متوسط', $related_id = null, $related_type = null) {
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, priority, related_id, related_type) 
                              VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $title, $message, $type, $priority, $related_id, $related_type]);
    } catch (Exception $e) {
        error_log("Error sending notification: " . $e->getMessage());
    }
}

// دریافت اعلان‌های خوانده نشده
function getUnreadNotifications($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM notifications 
            WHERE user_id = ? AND is_read = 0 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error getting notifications: " . $e->getMessage());
        return [];
    }
}

// علامت‌گذاری اعلان به عنوان خوانده شده
function markNotificationAsRead($pdo, $notification_id) {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1, read_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$notification_id]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("Error marking notification as read: " . $e->getMessage());
        return false;
    }
}

// دریافت پیام‌های خوانده نشده
function getUnreadMessages($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT m.*, u.full_name as sender_name 
            FROM messages m
            LEFT JOIN users u ON m.sender_id = u.id
            WHERE m.receiver_id = ? AND m.is_read = 0 
            ORDER BY m.created_at DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error getting messages: " . $e->getMessage());
        return [];
    }
}

?>