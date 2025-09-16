<?php
// config.php - نسخه کامل و حرفه‌ای

// شروع session اگر شروع نشده
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تنظیمات امنیتی
error_reporting(E_ALL);
ini_set('display_errors', 1); // برای عیب‌یابی
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php-errors.log');

// تنظیمات دیتابیس
$db_host = 'localhost';
$db_port = '3306';
$db_name = 'aala_niroo_ams';
$db_user = 'root';
$db_pass = '';

// تنظیمات زمانzone
date_default_timezone_set('Asia/Tehran');

// متغیر PDO
$pdo = null;

try {
    // اتصال به MySQL بدون انتخاب دیتابیس
    $pdo = new PDO("mysql:host=$db_host;port=$db_port;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
    // ایجاد دیتابیس اگر وجود ندارد
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $db_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // انتخاب دیتابیس
    $pdo->exec("USE $db_name");
    
} catch (PDOException $e) {
    // در صورت خطا، PDO را null می‌کنیم
    $pdo = null;
    error_log("Database connection error: " . $e->getMessage());
}

// تولید token برای جلوگیری از CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * توابع امنیتی و احراز هویت
 */

// تابع بررسی دسترسی‌ها
function hasPermission($permission) {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'مدیر') {
        return true;
    }
    
    if (isset($_SESSION['permissions']) && is_array($_SESSION['permissions'])) {
        return in_array($permission, $_SESSION['permissions']) || in_array('*', $_SESSION['permissions']);
    }
    
    return false;
}

// تابع بررسی احراز هویت
function require_auth($required_role = 'کاربر عادی') {
    if (!isset($_SESSION['user_id'])) {
        redirect('login.php');
    }
}

// تابع بررسی CSRF token
function verifyCsrfToken() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            die('درخواست نامعتبر است - CSRF Token validation failed');
        }
    }
}

// تابع تولید فیلد CSRF
function csrf_field() {
    echo '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
}

/**
 * توابع ورودی و خروجی
 */

// تابع پاکسازی ورودی
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// تابع اعتبارسنجی شماره تلفن
function validatePhone($phone) {
    return preg_match('/^[0-9]{10,11}$/', $phone);
}

// تابع اعتبارسنجی ایمیل
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// تابع هدایت
function redirect($url, $statusCode = 303) {
    header('Location: ' . $url, true, $statusCode);
    exit();
}

// تابع بررسی درخواست AJAX
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

// تابع پاسخ JSON
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * توابع لاگ و گزارش‌گیری
 */

// تابع ثبت لاگ
function logAction($pdo, $action, $description = '', $severity = 'info', $module = null, $request_data = null, $response_data = null) {
    if (!$pdo) return;
    
    try {
        $user_id = $_SESSION['user_id'] ?? null;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // تبدیل داده‌ها به JSON اگر آرایه باشند
        if (is_array($request_data)) {
            $request_data = json_encode($request_data, JSON_UNESCAPED_UNICODE);
        }
        if (is_array($response_data)) {
            $response_data = json_encode($response_data, JSON_UNESCAPED_UNICODE);
        }
        
        $stmt = $pdo->prepare("INSERT INTO system_logs (user_id, action, description, ip_address, user_agent, request_data, response_data, severity, module) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $description, $ip_address, $user_agent, $request_data, $response_data, $severity, $module]);
    } catch (Exception $e) {
        error_log("Log action error: " . $e->getMessage());
    }
}

// نام مستعار برای logAction
function log_action($action, $description = '') {
    global $pdo;
    logAction($pdo, $action, $description);
}

/**
 * توابع تاریخ و زمان
 */

// تابع تبدیل تاریخ میلادی به شمسی
function jalali_format($date) {
    if (empty($date) || $date === '-') {
        return '-';
    }
    
    try {
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return $date;
        }
        
        $year = date('Y', $timestamp);
        $month = date('m', $timestamp);
        $day = date('d', $timestamp);
        
        $jalali_year = $year - 621;
        if ($month > 3) {
            $jalali_year++;
        }
        
        return $jalali_year . '/' . $month . '/' . $day;
    } catch (Exception $e) {
        return $date;
    }
}

// فرمت تاریخ شمسی
function jalaliDate($date = null) {
    if (!$date) $date = time();
    $date = is_numeric($date) ? $date : strtotime($date);
    
    $year = date('Y', $date);
    $month = date('m', $date);
    $day = date('d', $date);
    
    // تبدیل تاریخ میلادی به شمسی (ساده شده)
    $jalali = gregorian_to_jalali($year, $month, $day);
    return $jalali[0] . '/' . $jalali[1] . '/' . $jalali[2];
}

// تابع تبدیل تاریخ میلادی به شمسی
function gregorian_to_jalali($gy, $gm, $gd) {
    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + ((int)(($gy2 + 3) / 4)) - ((int)(($gy2 + 99) / 100)) + ((int)(($gy2 + 399) / 400)) + $gd + $g_d_m[$gm - 1];
    $jy = -1595 + (33 * ((int)($days / 12053)));
    $days %= 12053;
    $jy += 4 * ((int)($days / 1461));
    $days %= 1461;
    if ($days > 365) {
        $jy += (int)(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    $jm = ($days < 186) ? 1 + (int)($days / 31) : 7 + (int)(($days - 186) / 30);
    $jd = 1 + (($days < 186) ? ($days % 31) : (($days - 186) % 30));
    return [$jy, $jm, $jd];
}

/**
 * توابع فایل و آپلود
 */

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

/**
 * توابع مدیریت Workflow و اعلان‌ها
 */

// ارسال اعلان
function sendNotification($pdo, $user_id, $title, $message, $type = 'سیستم', $priority = 'متوسط', $related_id = null, $related_type = null) {
    if (!$pdo) return;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, priority, related_id, related_type) 
                              VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $title, $message, $type, $priority, $related_id, $related_type]);
        
        // اگر user_id مشخص باشد، اعلان‌های اضافی ارسال کن
        if ($user_id) {
            $settings = getUserNotificationSettings($pdo, $user_id);
            if ($settings) {
                // ارسال ایمیل
                if ($settings['email_notifications']) {
                    sendEmailNotification($user_id, $title, $message);
                }
                
                // ارسال SMS
                if ($settings['sms_notifications']) {
                    sendSMSNotification($user_id, $title, $message);
                }
            }
        }
    } catch (Exception $e) {
        error_log("Send notification error: " . $e->getMessage());
    }
}

// دریافت تنظیمات اعلان کاربر
function getUserNotificationSettings($pdo, $user_id) {
    if (!$pdo) return null;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM notification_settings WHERE user_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Get notification settings error: " . $e->getMessage());
        return null;
    }
}

// ایجاد تنظیمات اعلان برای کاربر جدید
function createUserNotificationSettings($pdo, $user_id) {
    if (!$pdo) return false;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO notification_settings (user_id) VALUES (?)");
        $stmt->execute([$user_id]);
        return true;
    } catch (Exception $e) {
        error_log("Create notification settings error: " . $e->getMessage());
        return false;
    }
}

// ارسال ایمیل (پیاده‌سازی ساده)
function sendEmailNotification($user_id, $title, $message) {
    // اینجا می‌توانید از PHPMailer یا کتابخانه‌های دیگر استفاده کنید
    error_log("Email notification to user {$user_id}: {$title} - {$message}");
}

// ارسال SMS (پیاده‌سازی ساده)
function sendSMSNotification($user_id, $title, $message) {
    try {
        // دریافت شماره تلفن کاربر
        global $pdo;
        if (!$pdo) return ['success' => false, 'error' => 'Database connection not available'];
        
        $stmt = $pdo->prepare("SELECT phone FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if ($user && !empty($user['phone'])) {
            // بارگذاری فایل SMS
            if (file_exists(__DIR__ . '/sms.php')) {
                require_once __DIR__ . '/sms.php';
                
                // ارسال پیامک
                $result = send_sms($user['phone'], $title . "\n" . $message);
                
                // ثبت در لاگ
                if ($result['success']) {
                    logAction($pdo, 'SMS_SENT', "پیامک به کاربر $user_id ارسال شد", 'info', 'sms', [
                        'user_id' => $user_id,
                        'phone' => $user['phone'],
                        'message_id' => $result['message_id'] ?? null
                    ]);
                } else {
                    logAction($pdo, 'SMS_ERROR', "خطا در ارسال پیامک به کاربر $user_id: " . $result['error'], 'error', 'sms', [
                        'user_id' => $user_id,
                        'phone' => $user['phone'],
                        'error' => $result['error']
                    ]);
                }
                
                return $result;
            } else {
                error_log("SMS file not found");
                return ['success' => false, 'error' => 'SMS file not found'];
            }
        } else {
            error_log("SMS notification failed: No phone number for user {$user_id}");
            return ['success' => false, 'error' => 'شماره تلفن کاربر یافت نشد'];
        }
    } catch (Exception $e) {
        error_log("SMS notification error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * توابع مدیریت ابزارها
 */

// ثبت تاریخچه ابزار
function logToolHistory($pdo, $tool_id, $action_type, $action_description, $performed_by, $old_values = null, $new_values = null, $notes = null) {
    if (!$pdo) return;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO tool_history (tool_id, action_type, action_description, performed_by, old_values, new_values, notes) 
                              VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $tool_id, 
            $action_type, 
            $action_description, 
            $performed_by,
            $old_values ? json_encode($old_values, JSON_UNESCAPED_UNICODE) : null,
            $new_values ? json_encode($new_values, JSON_UNESCAPED_UNICODE) : null,
            $notes
        ]);
    } catch (Exception $e) {
        error_log("Log tool history error: " . $e->getMessage());
    }
}

/**
 * توابع مدیریت تیکت‌ها
 */

// ایجاد تیکت
function createTicket($pdo, $customer_id, $asset_id, $title, $description, $priority, $created_by) {
    if (!$pdo) return false;
    
    try {
        // تولید شماره تیکت منحصر به فرد
        $ticket_number = 'TK' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        $stmt = $pdo->prepare("INSERT INTO tickets (ticket_number, customer_id, asset_id, title, description, priority, created_by) 
                              VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$ticket_number, $customer_id, $asset_id, $title, $description, $priority, $created_by]);
        
        $ticket_id = $pdo->lastInsertId();
        
        // ثبت در تاریخچه
        $stmt = $pdo->prepare("INSERT INTO ticket_history (ticket_id, action, performed_by, notes) 
                              VALUES (?, ?, ?, ?)");
        $stmt->execute([$ticket_id, 'ایجاد تیکت', $created_by, 'تیکت جدید ایجاد شد']);
        
        return $ticket_id;
    } catch (Exception $e) {
        error_log("Create ticket error: " . $e->getMessage());
        return false;
    }
}

/**
 * توابع ایجاد جداول
 */

// تابع ایجاد جداول اولیه
function createInitialTables($pdo) {
    if (!$pdo) return false;
    
    try {
        // جدول کاربران
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(255),
            role VARCHAR(20) DEFAULT 'کاربر عادی',
            is_active BOOLEAN DEFAULT 1,
            last_login TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // جدول لاگ‌ها
        $pdo->exec("CREATE TABLE IF NOT EXISTS system_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            action VARCHAR(100) NOT NULL,
            description TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            request_data TEXT,
            response_data TEXT,
            severity VARCHAR(20) DEFAULT 'info',
            module VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // جدول مشتریان
        $pdo->exec("CREATE TABLE IF NOT EXISTS customers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(255) NOT NULL,
            phone VARCHAR(20) NOT NULL UNIQUE,
            company VARCHAR(255),
            customer_type VARCHAR(20) DEFAULT 'حقیقی',
            company_phone VARCHAR(20),
            responsible_phone VARCHAR(20),
            address TEXT,
            city VARCHAR(100),
            email VARCHAR(255),
            company_email VARCHAR(255),
            notification_type VARCHAR(20) DEFAULT 'none',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // جدول دارایی‌ها
        $pdo->exec("CREATE TABLE IF NOT EXISTS assets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            type_id INT DEFAULT 1,
            serial_number VARCHAR(255) UNIQUE,
            purchase_date DATE,
            status VARCHAR(50) DEFAULT 'فعال',
            brand VARCHAR(255),
            model VARCHAR(255),
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // جدول تیکت‌ها
        $pdo->exec("CREATE TABLE IF NOT EXISTS tickets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ticket_number VARCHAR(20) UNIQUE NOT NULL,
            customer_id INT NOT NULL,
            asset_id INT,
            title VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            priority VARCHAR(20) DEFAULT 'متوسط',
            status VARCHAR(20) DEFAULT 'جدید',
            assigned_to INT,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            resolved_at TIMESTAMP NULL,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
            FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE SET NULL,
            FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // جدول تاریخچه تیکت‌ها
        $pdo->exec("CREATE TABLE IF NOT EXISTS ticket_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ticket_id INT NOT NULL,
            action VARCHAR(100) NOT NULL,
            old_value TEXT,
            new_value TEXT,
            performed_by INT NOT NULL,
            performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            notes TEXT,
            FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
            FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // جدول ابزارها
        $pdo->exec("CREATE TABLE IF NOT EXISTS tools (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tool_code VARCHAR(50) UNIQUE NOT NULL,
            name VARCHAR(255) NOT NULL,
            category VARCHAR(50) NOT NULL,
            brand VARCHAR(100),
            model VARCHAR(100),
            serial_number VARCHAR(100),
            purchase_date DATE,
            purchase_price DECIMAL(10,2),
            supplier VARCHAR(255),
            location VARCHAR(255),
            status VARCHAR(50) DEFAULT 'موجود',
            condition_notes TEXT,
            maintenance_date DATE,
            next_maintenance_date DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // جدول تاریخچه ابزارها
        $pdo->exec("CREATE TABLE IF NOT EXISTS tool_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tool_id INT NOT NULL,
            action_type VARCHAR(50) NOT NULL,
            action_description TEXT NOT NULL,
            performed_by INT NOT NULL,
            performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            old_values TEXT,
            new_values TEXT,
            notes TEXT,
            FOREIGN KEY (tool_id) REFERENCES tools(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // جدول اعلان‌ها
        $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            type VARCHAR(20) DEFAULT 'سیستم',
            priority VARCHAR(20) DEFAULT 'متوسط',
            is_read BOOLEAN DEFAULT 0,
            related_id INT,
            related_type VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            read_at TIMESTAMP NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // جدول تنظیمات اعلان‌ها
        $pdo->exec("CREATE TABLE IF NOT EXISTS notification_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL UNIQUE,
            email_notifications BOOLEAN DEFAULT 1,
            sms_notifications BOOLEAN DEFAULT 0,
            in_app_notifications BOOLEAN DEFAULT 1,
            ticket_notifications BOOLEAN DEFAULT 1,
            maintenance_notifications BOOLEAN DEFAULT 1,
            system_notifications BOOLEAN DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // بررسی وجود کاربر admin
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE username = 'admin'");
        $admin_exists = $stmt->fetch()['count'] > 0;
        
        if (!$admin_exists) {
            $pdo->exec("INSERT INTO users (username, password, full_name, role) VALUES
                       ('admin', '" . password_hash('admin', PASSWORD_DEFAULT) . "', 'مدیر سیستم', 'مدیر')");
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Create tables error: " . $e->getMessage());
        return false;
    }
}

// ایجاد جداول اولیه
if ($pdo) {
    createInitialTables($pdo);
}

?>