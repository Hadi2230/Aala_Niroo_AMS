<?php
// config.php - نسخه کامل و اصلاح شده

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
$host = 'localhost:3306';
$dbname = 'aala_niroo';
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
        
        // جدول فیلدهای سفارشی برای هر نوع دارایی
        "CREATE TABLE IF NOT EXISTS asset_fields (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type_id INT,
            field_name VARCHAR(100),
            field_type ENUM('text', 'number', 'date', 'select', 'file'),
            is_required BOOLEAN DEFAULT false,
            options TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (type_id) REFERENCES asset_types(id) ON DELETE CASCADE,
            INDEX idx_type_id (type_id)
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
            FOREIGN KEY (type_id) REFERENCES asset_types(id) ON DELETE CASCADE,
            INDEX idx_type_id (type_id),
            INDEX idx_status (status),
            INDEX idx_serial (serial_number),
            INDEX idx_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",
        
        // جدول تصاویر دارایی‌ها
        "CREATE TABLE IF NOT EXISTS asset_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            asset_id INT NOT NULL,
            field_name VARCHAR(100) NOT NULL,
            image_path VARCHAR(500) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
            INDEX idx_asset_id (asset_id),
            INDEX idx_field_name (field_name)
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
        
        // جدول جزئیات انتساب
        "CREATE TABLE IF NOT EXISTS assignment_details (
            id INT AUTO_INCREMENT PRIMARY KEY,
            assignment_id INT NOT NULL,
            installation_date DATE,
            delivery_person VARCHAR(255),
            installation_address TEXT,
            warranty_start_date DATE,
            warranty_end_date DATE,
            warranty_conditions TEXT,
            employer_name VARCHAR(255),
            employer_phone VARCHAR(20),
            recipient_name VARCHAR(255),
            recipient_phone VARCHAR(20),
            installer_name VARCHAR(255),
            installation_start_date DATE,
            installation_end_date DATE,
            temporary_delivery_date DATE,
            permanent_delivery_date DATE,
            first_service_date DATE,
            post_installation_commitments TEXT,
            notes TEXT,
            installation_photo VARCHAR(500),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (assignment_id) REFERENCES asset_assignments(id) ON DELETE CASCADE,
            INDEX idx_assignment_id (assignment_id),
            INDEX idx_installation_date (installation_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",
        
        // جدول گزارشات و لاگ‌ها
        "CREATE TABLE IF NOT EXISTS system_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            action VARCHAR(100) NOT NULL,
            description TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_action (action),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",
        
        // جدول تیکت‌های مشتری
        "CREATE TABLE IF NOT EXISTS tickets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ticket_number VARCHAR(20) UNIQUE NOT NULL,
            customer_id INT NOT NULL,
            asset_id INT,
            title VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            priority ENUM('کم', 'متوسط', 'بالا', 'فوری') DEFAULT 'متوسط',
            status ENUM('جدید', 'در انتظار', 'در حال بررسی', 'در انتظار قطعه', 'تکمیل شده', 'لغو شده') DEFAULT 'جدید',
            assigned_to INT,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            resolved_at TIMESTAMP NULL,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
            FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE SET NULL,
            FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_customer_id (customer_id),
            INDEX idx_asset_id (asset_id),
            INDEX idx_assigned_to (assigned_to),
            INDEX idx_status (status),
            INDEX idx_priority (priority),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",
        
        // جدول برنامه تعمیرات دوره‌ای
        "CREATE TABLE IF NOT EXISTS maintenance_schedules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            asset_id INT NOT NULL,
            assignment_id INT,
            maintenance_type ENUM('تعمیر دوره‌ای', 'سرویس', 'بازرسی', 'کالیبراسیون') DEFAULT 'تعمیر دوره‌ای',
            schedule_date DATE NOT NULL,
            interval_days INT DEFAULT 90,
            status ENUM('برنامه‌ریزی شده', 'در انتظار', 'در حال انجام', 'تکمیل شده', 'لغو شده') DEFAULT 'برنامه‌ریزی شده',
            assigned_to INT,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            completed_at TIMESTAMP NULL,
            FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
            FOREIGN KEY (assignment_id) REFERENCES asset_assignments(id) ON DELETE SET NULL,
            FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_asset_id (asset_id),
            INDEX idx_schedule_date (schedule_date),
            INDEX idx_status (status),
            INDEX idx_assigned_to (assigned_to)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",
        
        // جدول اعلان‌ها
        "CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            type ENUM('تیکت', 'تعمیرات', 'سیستم', 'پیام') DEFAULT 'سیستم',
            priority ENUM('کم', 'متوسط', 'بالا', 'فوری') DEFAULT 'متوسط',
            is_read BOOLEAN DEFAULT false,
            related_id INT,
            related_type VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            read_at TIMESTAMP NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_is_read (is_read),
            INDEX idx_type (type),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",
        
        // جدول پیام‌های داخلی
        "CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sender_id INT NOT NULL,
            receiver_id INT NOT NULL,
            subject VARCHAR(255),
            message TEXT NOT NULL,
            is_read BOOLEAN DEFAULT false,
            related_ticket_id INT,
            related_maintenance_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            read_at TIMESTAMP NULL,
            FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (related_ticket_id) REFERENCES tickets(id) ON DELETE SET NULL,
            FOREIGN KEY (related_maintenance_id) REFERENCES maintenance_schedules(id) ON DELETE SET NULL,
            INDEX idx_sender_id (sender_id),
            INDEX idx_receiver_id (receiver_id),
            INDEX idx_is_read (is_read),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",
        
        // جدول تاریخچه وضعیت تیکت‌ها
        "CREATE TABLE IF NOT EXISTS ticket_status_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ticket_id INT NOT NULL,
            old_status VARCHAR(50),
            new_status VARCHAR(50) NOT NULL,
            changed_by INT,
            change_reason TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
            FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_ticket_id (ticket_id),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",
        
        // جدول تنظیمات اعلان‌ها
        "CREATE TABLE IF NOT EXISTS notification_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            email_notifications BOOLEAN DEFAULT true,
            sms_notifications BOOLEAN DEFAULT false,
            in_app_notifications BOOLEAN DEFAULT true,
            ticket_notifications BOOLEAN DEFAULT true,
            maintenance_notifications BOOLEAN DEFAULT true,
            system_notifications BOOLEAN DEFAULT true,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_settings (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",
        
        // جدول نقش‌های سفارشی
        "CREATE TABLE IF NOT EXISTS custom_roles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            role_name VARCHAR(100) NOT NULL,
            permissions TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_role (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",
        
        // جدول نظرسنجی‌ها
        "CREATE TABLE IF NOT EXISTS surveys (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            is_active BOOLEAN DEFAULT true,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_is_active (is_active),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",
        
        // جدول سوالات نظرسنجی
        "CREATE TABLE IF NOT EXISTS survey_questions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            survey_id INT NOT NULL,
            question_text TEXT NOT NULL,
            question_type ENUM('text', 'yes_no', 'rating', 'multiple_choice') DEFAULT 'text',
            is_required BOOLEAN DEFAULT true,
            options TEXT,
            order_index INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE,
            INDEX idx_survey_id (survey_id),
            INDEX idx_order (order_index)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",
        
        // جدول ارسال‌های نظرسنجی
        "CREATE TABLE IF NOT EXISTS survey_submissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            survey_id INT NOT NULL,
            customer_id INT NOT NULL,
            asset_id INT,
            started_by INT NOT NULL,
            status ENUM('در حال تکمیل', 'تکمیل شده', 'لغو شده') DEFAULT 'در حال تکمیل',
            sms_sent BOOLEAN DEFAULT false,
            sms_sent_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
            FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE SET NULL,
            FOREIGN KEY (started_by) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_survey_id (survey_id),
            INDEX idx_customer_id (customer_id),
            INDEX idx_asset_id (asset_id),
            INDEX idx_started_by (started_by),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",
        
        // جدول پاسخ‌های نظرسنجی
        "CREATE TABLE IF NOT EXISTS survey_responses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            survey_id INT NOT NULL,
            question_id INT NOT NULL,
            customer_id INT NOT NULL,
            asset_id INT,
            response_text TEXT,
            responded_by INT NOT NULL,
            submission_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE,
            FOREIGN KEY (question_id) REFERENCES survey_questions(id) ON DELETE CASCADE,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
            FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE SET NULL,
            FOREIGN KEY (responded_by) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (submission_id) REFERENCES survey_submissions(id) ON DELETE CASCADE,
            INDEX idx_survey_id (survey_id),
            INDEX idx_question_id (question_id),
            INDEX idx_customer_id (customer_id),
            INDEX idx_asset_id (asset_id),
            INDEX idx_submission_id (submission_id),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",
        
        // جدول لاگ پیامک‌ها
        "CREATE TABLE IF NOT EXISTS sms_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            phone VARCHAR(20) NOT NULL,
            message TEXT NOT NULL,
            status ENUM('sent', 'delivered', 'failed') DEFAULT 'sent',
            message_id VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_phone (phone),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at)
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
    $check_types = $pdo->query("SELECT COUNT(*) as count FROM asset_types")->fetch();
    if ($check_types['count'] == 0) {
        $initial_data = [
            // درج انواع دارایی
            "INSERT INTO asset_types (name, display_name) VALUES 
            ('generator', 'ژنراتور'),
            ('power_motor', 'موتور برق'),
            ('consumable', 'اقلام مصرفی')",
            
            // درج کاربر پیش فرض (admin/admin)
            "INSERT INTO users (username, password, full_name, role) VALUES
            ('admin', '" . password_hash('admin', PASSWORD_DEFAULT) . "', 'مدیر سیستم', 'ادمین')",
            
            // درج فیلدهای سفارشی برای ژنراتور
            "INSERT INTO asset_fields (type_id, field_name, field_type, is_required) VALUES
            (1, 'ظرفیت توان (کیلووات)', 'number', true),
            (1, 'نوع سوخت', 'select', true),
            (1, 'تعداد فاز', 'select', true)",
            
            // درج فیلدهای سفارشی برای موتور برق
            "INSERT INTO asset_fields (type_id, field_name, field_type, is_required) VALUES
            (2, 'قدرت (اسب بخار)', 'number', true),
            (2, 'ولتاژ کاری', 'select', true),
            (2, 'نوع خنک‌کننده', 'select', true)",
            
            // درج فیلدهای سفارشی برای اقلام مصرفی
            "INSERT INTO asset_fields (type_id, field_name, field_type, is_required) VALUES
            (3, 'نوع کالا', 'select', true),
            (3, 'تعداد/مقدار', 'number', true),
            (3, 'واحد اندازه‌گیری', 'select', true)"
        ];
        
        foreach ($initial_data as $query) {
            try {
                $pdo->exec($query);
            } catch (PDOException $e) {
                error_log("[" . date('Y-m-d H:i:s') . "] خطا در درج داده اولیه: " . $e->getMessage());
            }
        }
    }
}

/**
 * توابع کمکی اصلی
 */

// پاکسازی و اعتبارسنجی ورودی‌ها
if (!function_exists('sanitizeInput')) {
    function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map('sanitizeInput', $data);
        }
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
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
function logAction($pdo, $action, $description = '') {
    $user_id = $_SESSION['user_id'] ?? null;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $stmt = $pdo->prepare("INSERT INTO system_logs (user_id, action, description, ip_address, user_agent) 
                          VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $action, $description, $ip_address, $user_agent]);
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
if (!function_exists('verifyCsrfToken')) {
    function verifyCsrfToken($token = null) {
        if ($token === null) {
            $token = $_POST['csrf_token'] ?? '';
        }
        if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
            die("Invalid CSRF token");
        }
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
if (!function_exists('csrf_field')) {
    function csrf_field() {
        return '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
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

// بررسی دسترسی کاربر
function hasPermission($permission) {
    if (!isset($_SESSION['user_id'])) return false;
    
    $user_role = $_SESSION['role'] ?? 'کاربر عادی';
    
    // نقش ادمین دسترسی کامل دارد
    if ($user_role === 'ادمین') return true;
    
    // نقش‌های دیگر بر اساس نیاز تعریف می‌شوند
    $permissions = [
        'مدیر عملیات' => ['tickets.*', 'maintenance.*', 'customers.*', 'reports.*'],
        'تکنسین' => ['tickets.view', 'tickets.edit', 'maintenance.*'],
        'پشتیبانی' => ['tickets.*', 'customers.*'],
        'کاربر عادی' => ['tickets.view']
    ];
    
    if (!isset($permissions[$user_role])) return false;
    
    $user_permissions = $permissions[$user_role];
    
    // بررسی دسترسی کامل
    foreach ($user_permissions as $perm) {
        if ($perm === '*') return true;
        if (strpos($perm, '*') !== false) {
            $base_perm = str_replace('.*', '', $perm);
            if (strpos($permission, $base_perm) === 0) return true;
        }
        if ($perm === $permission) return true;
    }
    
    return false;
}

// تولید کد تصادفی
function generateRandomCode($length = 8) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}

// تولید شماره سریال
function generateSerialNumber($prefix = 'SN') {
    return $prefix . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

// فرمت کردن شماره تلفن
function formatPhoneNumber($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    if (strlen($phone) == 11 && substr($phone, 0, 1) == '0') {
        return substr($phone, 0, 4) . '-' . substr($phone, 4, 3) . '-' . substr($phone, 7, 4);
    }
    
    if (strlen($phone) == 10) {
        return '0' . substr($phone, 0, 3) . '-' . substr($phone, 3, 3) . '-' . substr($phone, 6, 4);
    }
    
    return $phone;
}

// تبدیل حجم فایل به فرمت خوانا
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, 2) . ' ' . $units[$pow];
}

// دریافت IP واقعی کاربر
function getRealUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

// بررسی اینکه آیا کاربر از موبایل استفاده می‌کند
function isMobile() {
    return preg_match('/(android|iphone|ipad|mobile)/i', $_SERVER['HTTP_USER_AGENT'] ?? '');
}

// تولید QR Code
function generateQRCode($text, $size = 200) {
    return "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data=" . urlencode($text);
}

// پاک کردن فایل‌های قدیمی
function cleanOldFiles($directory, $days = 30) {
    $files = glob($directory . '/*');
    $now = time();
    
    foreach ($files as $file) {
        if (is_file($file) && ($now - filemtime($file)) > ($days * 24 * 60 * 60)) {
            unlink($file);
        }
    }
}

?>