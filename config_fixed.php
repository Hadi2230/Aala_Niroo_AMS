<?php
// config_fixed.php - نسخه اصلاح شده و ساده

// شروع session اگر شروع نشده
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تنظیمات امنیتی
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php-errors.log');

// تنظیمات دیتابیس - امتحان تنظیمات مختلف
$db_configs = [
    [
        'host' => 'localhost',
        'port' => '3306',
        'username' => 'root',
        'password' => '',
        'dbname' => 'aala_niroo_ams'
    ],
    [
        'host' => 'localhost',
        'port' => '3306',
        'username' => 'root',
        'password' => '',
        'dbname' => 'aala_niroo'
    ],
    [
        'host' => 'localhost',
        'port' => '3306',
        'username' => 'root',
        'password' => 'root',
        'dbname' => 'aala_niroo_ams'
    ],
    [
        'host' => '127.0.0.1',
        'port' => '3306',
        'username' => 'root',
        'password' => '',
        'dbname' => 'aala_niroo_ams'
    ]
];

$pdo = null;
$connection_success = false;

// امتحان اتصال با تنظیمات مختلف
foreach ($db_configs as $config) {
    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_persian_ci"
        ]);
        
        // تست اتصال
        $pdo->query("SELECT 1");
        $connection_success = true;
        break;
        
    } catch (PDOException $e) {
        // اگر اتصال ناموفق بود، ادامه بده
        continue;
    }
}

// اگر هیچ اتصالی موفق نبود
if (!$connection_success || !$pdo) {
    // ایجاد پوشه logs
    if (!is_dir(__DIR__ . '/logs')) {
        mkdir(__DIR__ . '/logs', 0755, true);
    }
    
    error_log("[" . date('Y-m-d H:i:s') . "] خطا در اتصال به دیتابیس - هیچ تنظیمات صحیحی یافت نشد");
    
    die("<div style='text-align: center; padding: 50px; font-family: Tahoma; direction: rtl;'>
        <h2>خطا در اتصال به سیستم</h2>
        <p>لطفاً چند دقیقه دیگر تلاش کنید یا با پشتیبانی تماس بگیرید.</p>
        <p><small>خطای سیستمی: نتوانستیم به دیتابیس متصل شویم</small></p>
        <p><a href='check_databases.php'>بررسی دیتابیس‌های موجود</a></p>
        </div>");
}

// تولید token برای جلوگیری از CSRF اگر وجود ندارد
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
    // ایجاد پوشه logs اگر وجود ندارد
    if (!is_dir(__DIR__ . '/logs')) {
        mkdir(__DIR__ . '/logs', 0755, true);
    }
    
    // ایجاد پوشه uploads اگر وجود ندارد
    if (!is_dir(__DIR__ . '/uploads')) {
        mkdir(__DIR__ . '/uploads', 0755, true);
        mkdir(__DIR__ . '/uploads/installations', 0755, true);
        mkdir(__DIR__ . '/uploads/assets', 0755, true);
        mkdir(__DIR__ . '/uploads/filters', 0755, true);
        
        // ایجاد فایل htaccess برای محافظت از پوشه uploads
        file_put_contents(__DIR__ . '/uploads/.htaccess', 
            "Order deny,allow\nDeny from all\n<Files ~ \"\.(jpg|jpeg|png|gif)$\">\nAllow from all\n</Files>");
    }
    
    $tables = [
        // جدول انواع دارایی‌ها
        "CREATE TABLE IF NOT EXISTS asset_types (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL UNIQUE,
            display_name VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",
        
        // جدول کاربران
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(255),
            role ENUM('ادمین', 'کاربر عادی', 'اپراتور', 'مدیر عملیات', 'تکنسین', 'پشتیبانی') DEFAULT 'کاربر عادی',
            is_active BOOLEAN DEFAULT true,
            last_login TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_username (username),
            INDEX idx_role (role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",
        
        // جدول اصلی دارایی‌ها
        "CREATE TABLE IF NOT EXISTS assets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            type_id INT NOT NULL,
            serial_number VARCHAR(255) UNIQUE,
            purchase_date DATE,
            status ENUM('فعال', 'غیرفعال', 'در حال تعمیر', 'آماده بهره‌برداری') DEFAULT 'فعال',
            brand VARCHAR(255),
            model VARCHAR(255),
            power_capacity VARCHAR(100),
            engine_type VARCHAR(100),
            consumable_type VARCHAR(100),
            engine_model VARCHAR(255),
            engine_serial VARCHAR(255),
            alternator_model VARCHAR(255),
            alternator_serial VARCHAR(255),
            device_model VARCHAR(255),
            device_serial VARCHAR(255),
            control_panel_model VARCHAR(255),
            breaker_model VARCHAR(255),
            fuel_tank_specs TEXT,
            battery VARCHAR(255),
            battery_charger VARCHAR(255),
            heater VARCHAR(255),
            oil_capacity VARCHAR(255),
            radiator_capacity VARCHAR(255),
            antifreeze VARCHAR(255),
            other_items TEXT,
            workshop_entry_date DATE,
            workshop_exit_date DATE,
            datasheet_link VARCHAR(500),
            engine_manual_link VARCHAR(500),
            alternator_manual_link VARCHAR(500),
            control_panel_manual_link VARCHAR(500),
            description TEXT,
            oil_filter_part VARCHAR(100),
            fuel_filter_part VARCHAR(100),
            water_fuel_filter_part VARCHAR(100),
            air_filter_part VARCHAR(100),
            water_filter_part VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_type_id (type_id),
            INDEX idx_status (status),
            INDEX idx_serial (serial_number),
            INDEX idx_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",
        
        // جدول مشتریان
        "CREATE TABLE IF NOT EXISTS customers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(255) NOT NULL,
            phone VARCHAR(20) NOT NULL UNIQUE,
            company VARCHAR(255),
            customer_type ENUM('حقیقی', 'حقوقی') DEFAULT 'حقیقی',
            company_phone VARCHAR(20),
            responsible_phone VARCHAR(20),
            address TEXT,
            city VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_phone (phone),
            INDEX idx_company (company),
            INDEX idx_city (city),
            INDEX idx_customer_type (customer_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",
        
        // جدول انتساب‌ها
        "CREATE TABLE IF NOT EXISTS asset_assignments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            asset_id INT NOT NULL,
            customer_id INT NOT NULL,
            assignment_date DATE,
            notes TEXT,
            assignment_status ENUM('فعال', 'خاتمه یافته', 'موقت') DEFAULT 'فعال',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
            INDEX idx_asset_id (asset_id),
            INDEX idx_customer_id (customer_id),
            INDEX idx_assignment_date (assignment_date),
            INDEX idx_status (assignment_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",
        
        // جدول گزارشات و لاگ‌ها
        "CREATE TABLE IF NOT EXISTS system_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            action VARCHAR(100) NOT NULL,
            description TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            session_id VARCHAR(255),
            request_method VARCHAR(10),
            request_uri TEXT,
            referer TEXT,
            file_path VARCHAR(500),
            line_number INT,
            stack_trace TEXT,
            execution_time DECIMAL(10,6),
            memory_usage BIGINT,
            severity ENUM('info', 'warning', 'error', 'critical') DEFAULT 'info',
            module VARCHAR(100),
            request_data TEXT,
            response_data TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_action (action),
            INDEX idx_created_at (created_at),
            INDEX idx_severity (severity),
            INDEX idx_module (module)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci"
    ];
    
    foreach ($tables as $table) {
        try {
            $pdo->exec($table);
        } catch (PDOException $e) {
            error_log("[" . date('Y-m-d H:i:s') . "] خطا در ایجاد جدول: " . $e->getMessage());
        }
    }
    
    // درج داده‌های اولیه اگر وجود ندارند
    $check_users = $pdo->query("SELECT COUNT(*) as count FROM users")->fetch();
    if ($check_users['count'] == 0) {
        try {
            // درج کاربر پیش فرض (admin/admin)
            $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)");
            $stmt->execute(['admin', password_hash('admin', PASSWORD_DEFAULT), 'مدیر سیستم', 'ادمین']);
            
            // درج انواع دارایی
            $stmt = $pdo->prepare("INSERT INTO asset_types (name, display_name) VALUES (?, ?)");
            $types = [
                ['generator', 'ژنراتور'],
                ['power_motor', 'موتور برق'],
                ['consumable', 'اقلام مصرفی']
            ];
            
            foreach ($types as $type) {
                $stmt->execute($type);
            }
            
        } catch (PDOException $e) {
            error_log("[" . date('Y-m-d H:i:s') . "] خطا در درج داده اولیه: " . $e->getMessage());
        }
    }
}

/**
 * توابع کمکی اصلی
 */

// پاکسازی و اعتبارسنجی ورودی‌ها
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// اعتبارسنجی شماره تلفن
function validatePhone($phone) {
    return preg_match('/^[0-9]{10,11}$/', $phone);
}

// اعتبارسنجی ایمیل
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// هدایت به صفحه دیگر
function redirect($url, $statusCode = 303) {
    header('Location: ' . $url, true, $statusCode);
    exit();
}

// بررسی درخواست AJAX
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

// پاسخ JSON
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

// ثبت لاگ سیستم
function logAction($pdo, $action, $description = '', $severity = 'info', $module = null, $request_data = null, $response_data = null) {
    try {
        $user_id = $_SESSION['user_id'] ?? null;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $session_id = session_id();
        $request_method = $_SERVER['REQUEST_METHOD'] ?? '';
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $referer = $_SERVER['HTTP_REFERER'] ?? null;
        
        // اطلاعات فایل و خط
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $file_path = $backtrace[1]['file'] ?? null;
        $line_number = $backtrace[1]['line'] ?? null;
        
        // Stack trace برای خطاها
        $stack_trace = null;
        if ($severity === 'error' || $severity === 'critical') {
            $stack_trace = json_encode($backtrace, JSON_UNESCAPED_UNICODE);
        }
        
        // زمان اجرا و حافظه
        $execution_time = microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
        $memory_usage = memory_get_usage(true);
        
        $stmt = $pdo->prepare("INSERT INTO system_logs 
            (user_id, action, description, ip_address, user_agent, session_id, 
             request_method, request_uri, referer, file_path, line_number, 
             stack_trace, execution_time, memory_usage, severity, module, 
             request_data, response_data) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $user_id, $action, $description, $ip_address, $user_agent, $session_id,
            $request_method, $request_uri, $referer, $file_path, $line_number,
            $stack_trace, $execution_time, $memory_usage, $severity, $module,
            $request_data ? json_encode($request_data, JSON_UNESCAPED_UNICODE) : null,
            $response_data ? json_encode($response_data, JSON_UNESCAPED_UNICODE) : null
        ]);
    } catch (Exception $e) {
        // اگر لاگ‌گیری خودش خطا داشته باشد، آن را در فایل لاگ بنویسیم
        error_log("خطا در logAction: " . $e->getMessage());
    }
}

// نام مستعار برای logAction
function log_action($action, $description = '') {
    global $pdo;
    logAction($pdo, $action, $description);
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
function verifyCsrfToken($token = null) {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? '';
    }
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token");
    }
}

// بررسی دسترسی کاربر
function checkPermission($required_role = 'کاربر عادی') {
    if (!isset($_SESSION['user_id'])) {
        redirect('login.php');
    }
    
    $roles = ['کاربر عادی' => 1, 'اپراتور' => 2, 'ادمین' => 3];
    
    if (isset($roles[$_SESSION['role']]) && isset($roles[$required_role])) {
        if ($roles[$_SESSION['role']] < $roles[$required_role]) {
            die('دسترسی غیرمجاز - شما مجوز دسترسی به این بخش را ندارید');
        }
    }
}

// بررسی احراز هویت (نام مستعار برای checkPermission)
function require_auth($required_role = 'کاربر عادی') {
    checkPermission($required_role);
}

// تولید فیلد CSRF
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
}

// فرمت تاریخ شمسی
function jalaliDate($date = null, $format = 'Y/m/d') {
    if (!$date) $date = time();
    $date = is_numeric($date) ? $date : strtotime($date);
    
    $year = date('Y', $date);
    $month = date('m', $date);
    $day = date('d', $date);
    
    // تبدیل تاریخ میلادی به شمسی (ساده شده)
    $jalali = gregorian_to_jalali($year, $month, $day);
    return $jalali[0] . '/' . $jalali[1] . '/' . $jalali[2];
}

// تبدیل اعداد انگلیسی به فارسی
function en2fa_digits($input) {
    $en = array('0','1','2','3','4','5','6','7','8','9');
    $fa = array('۰','۱','۲','۳','۴','۵','۶','۷','۸','۹');
    return str_replace($en, $fa, (string)$input);
}

// تبدیل تاریخ میلادی به شمسی
function gregorian_to_jalali($gy, $gm, $gd) {
    $g_d_m = array(0,31,59,90,120,151,181,212,243,273,304,334);
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + intval(($gy2 + 3) / 4) - intval(($gy2 + 99) / 100) + intval(($gy2 + 399) / 400) + $gd + $g_d_m[$gm - 1];
    $jy = -1595 + (33 * intval($days / 12053));
    $days %= 12053;
    $jy += 4 * intval($days / 1461);
    $days %= 1461;
    if ($days > 365) {
        $jy += intval(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    if ($days < 186) {
        $jm = 1 + intval($days / 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + intval(($days - 186) / 30);
        $jd = 1 + (($days - 186) % 30);
    }
    return array($jy, $jm, $jd);
}

// فرمت کردن تاریخ شمسی
function jalali_format($datetime, $format = 'Y/m/d H:i', $use_fa_digits = true) {
    if (!$datetime || in_array($datetime, array('0000-00-00', '0000-00-00 00:00:00'))) return '';
    $ts = is_numeric($datetime) ? intval($datetime) : strtotime($datetime);
    if ($ts === false) return $datetime;

    $gy = intval(date('Y', $ts));
    $gm = intval(date('n', $ts));
    $gd = intval(date('j', $ts));

    list($jy, $jm, $jd) = gregorian_to_jalali($gy, $gm, $gd);

    $H = date('H', $ts); $i = date('i', $ts); $s = date('s', $ts);

    $map = array(
        'Y' => $jy,
        'm' => sprintf('%02d', $jm),
        'n' => $jm,
        'd' => sprintf('%02d', $jd),
        'j' => $jd,
        'H' => $H,
        'i' => $i,
        's' => $s
    );

    $result = preg_replace_callback('/Y|m|n|d|j|H|i|s/', function($m) use ($map) {
        return isset($map[$m[0]]) ? $map[$m[0]] : $m[0];
    }, $format);

    if ($use_fa_digits) $result = en2fa_digits($result);
    return $result;
}

// تابع تبدیل تاریخ شمسی به میلادی
function jalali_to_gregorian($jy, $jm, $jd) {
    $jy += 1595;
    $days = -355668 + (365 * $jy) + ((int)($jy / 33)) * 8 + ((int)(((($jy % 33) + 3) / 4))) + $jd + (($jm < 7) ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);
    $gy = 400 * ((int)($days / 146097));
    $days %= 146097;
    if ($days > 36524) {
        $gy += 100 * ((int)(--$days / 36524));
        $days %= 36524;
        if ($days >= 365) $days++;
    }
    $gy += 4 * ((int)($days / 1461));
    $days %= 1461;
    if ($days > 365) {
        $gy += ((int)(($days - 1) / 365));
        $days = ($days - 1) % 365;
    }
    $gd = $days + 1;
    $sal_a = [0, 31, (($gy % 4 == 0 and $gy % 100 != 0) or ($gy % 400 == 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    for ($gm = 0; $gm < 13 and $gd > $sal_a[$gm]; $gm++) $gd -= $sal_a[$gm];
    return [$gy, $gm, $gd];
}

// تابع تبدیل تاریخ شمسی به فرمت میلادی برای دیتابیس
function jalaliToGregorianForDB($jalali_date) {
    if (empty($jalali_date)) return null;
    
    // تبدیل فرمت تاریخ شمسی به آرایه
    $parts = explode('/', $jalali_date);
    if (count($parts) != 3) return null;
    
    $jy = (int)$parts[0];
    $jm = (int)$parts[1];
    $jd = (int)$parts[2];
    
    $gregorian = jalali_to_gregorian($jy, $jm, $jd);
    return sprintf('%04d-%02d-%02d', $gregorian[0], $gregorian[1], $gregorian[2]);
}

// تابع تبدیل تاریخ میلادی دیتابیس به شمسی
function gregorianToJalaliFromDB($gregorian_date) {
    if (empty($gregorian_date)) return '--';
    
    $parts = explode('-', $gregorian_date);
    if (count($parts) != 3) return '--';
    
    $gy = (int)$parts[0];
    $gm = (int)$parts[1];
    $gd = (int)$parts[2];
    
    $jalali = gregorian_to_jalali($gy, $gm, $gd);
    return sprintf('%04d/%02d/%02d', $jalali[0], $jalali[1], $jalali[2]);
}

// تنظیمات زمانzone
date_default_timezone_set('Asia/Tehran');

?>