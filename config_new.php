<?php
// config.php - نسخه ساده و بدون خطا

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

// تابع ثبت لاگ
function logAction($pdo, $action, $description = '', $severity = 'info', $module = null) {
    if (!$pdo) return;
    
    try {
        $user_id = $_SESSION['user_id'] ?? null;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt = $pdo->prepare("INSERT INTO system_logs (user_id, action, description, ip_address, user_agent, severity, module) 
                              VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $description, $ip_address, $user_agent, $severity, $module]);
    } catch (Exception $e) {
        error_log("Log action error: " . $e->getMessage());
    }
}

// تابع پاکسازی ورودی
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
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

// تابع هدایت
function redirect($url, $statusCode = 303) {
    header('Location: ' . $url, true, $statusCode);
    exit();
}

// تابع بررسی احراز هویت
function require_auth($required_role = 'کاربر عادی') {
    if (!isset($_SESSION['user_id'])) {
        redirect('login.php');
    }
}

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