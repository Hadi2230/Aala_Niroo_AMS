<?php
/**
 * config.php - نسخه کامل و حرفه‌ای سیستم مدیریت دارایی‌ها
 * اعلا نیرو - سیستم مدیریت دارایی‌ها و انتساب دستگاه‌ها
 */

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
        mkdir(__DIR__ . '/uploads/requests', 0755, true);
        mkdir(__DIR__ . '/uploads/visit_documents', 0755, true);
        mkdir(__DIR__ . '/uploads/visit_photos', 0755, true);
        
        // ایجاد فایل htaccess برای محافظت از پوشه uploads
        file_put_contents(__DIR__ . '/uploads/.htaccess', 
            "Order deny,allow\nDeny from all\n<Files ~ \"\.(jpg|jpeg|png|gif|pdf|doc|docx)$\">\nAllow from all\n</Files>");
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
            FOREIGN KEY (type_id) REFERENCES asset_types(id) ON DELETE CASCADE,
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",
        
        // جدول درخواست‌های کالا/خدمات
        "CREATE TABLE IF NOT EXISTS requests (
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
            FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_request_number (request_number),
            INDEX idx_requester_id (requester_id),
            INDEX idx_status (status),
            INDEX idx_priority (priority)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",
        
        // جدول فایل‌های ضمیمه درخواست‌ها
        "CREATE TABLE IF NOT EXISTS request_files (
            id INT AUTO_INCREMENT PRIMARY KEY,
            request_id INT NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_type VARCHAR(100),
            file_size INT,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
            INDEX idx_request_id (request_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",
        
        // جدول گردش کار درخواست‌ها
        "CREATE TABLE IF NOT EXISTS request_workflow (
            id INT AUTO_INCREMENT PRIMARY KEY,
            request_id INT NOT NULL,
            step_order INT NOT NULL,
            assigned_to INT NOT NULL,
            assigned_to_name VARCHAR(255) NOT NULL,
            department VARCHAR(100),
            status ENUM('در انتظار', 'تأیید شده', 'رد شده') DEFAULT 'در انتظار',
            comments TEXT,
            action_date TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
            FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_request_id (request_id),
            INDEX idx_assigned_to (assigned_to),
            INDEX idx_step_order (step_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",
        
        // جدول اعلان‌های درخواست‌ها
        "CREATE TABLE IF NOT EXISTS request_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            request_id INT NOT NULL,
            user_id INT NOT NULL,
            notification_type ENUM('درخواست جدید', 'تأیید', 'رد', 'تکمیل') NOT NULL,
            message TEXT NOT NULL,
            is_read BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_request_id (request_id),
            INDEX idx_user_id (user_id),
            INDEX idx_is_read (is_read)
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
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('خطا در آپلود فایل');
    }
    
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];
    
    if (!in_array($file_ext, $allowed_types)) {
        throw new Exception('نوع فایل مجاز نیست');
    }
    
    if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
        throw new Exception('حجم فایل بیش از حد مجاز است');
    }
    
    $file_name = time() . '_' . uniqid() . '.' . $file_ext;
    $target_file = $upload_dir . $file_name;
    
    if (!move_uploaded_file($file['tmp_name'], $target_file)) {
        throw new Exception('خطا در ذخیره فایل');
    }
    
    // ثبت در دیتابیس
    $stmt = $pdo->prepare("
        INSERT INTO request_files (request_id, file_name, file_path, file_type, file_size) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $request_id,
        $file['name'],
        $target_file,
        $file['type'],
        $file['size']
    ]);
    
    return $target_file;
}

/**
 * ایجاد گردش کار درخواست
 */
function createRequestWorkflow($pdo, $request_id, $assignments) {
    $step_order = 1;
    
    foreach ($assignments as $assignment) {
        $stmt = $pdo->prepare("
            INSERT INTO request_workflow (request_id, step_order, assigned_to, assigned_to_name, department, status) 
            VALUES (?, ?, ?, ?, ?, 'در انتظار')
        ");
        
        $stmt->execute([
            $request_id,
            $step_order,
            $assignment['user_id'],
            $assignment['name'],
            $assignment['department'] ?? 'عمومی',
        ]);
        
        $step_order++;
    }
}

/**
 * ایجاد اعلان درخواست
 */
function createRequestNotification($pdo, $request_id, $user_id, $type, $message) {
    $stmt = $pdo->prepare("
        INSERT INTO request_notifications (request_id, user_id, notification_type, message) 
        VALUES (?, ?, ?, ?)
    ");
    
    $stmt->execute([$request_id, $user_id, $type, $message]);
}

/**
 * دریافت کاربران برای انتساب
 */
function getUsersForAssignment($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, username, full_name, role FROM users WHERE is_active = 1 ORDER BY full_name");
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error getting users for assignment: " . $e->getMessage());
        return [];
    }
}

// توابع تاریخ شمسی
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

function jalaliToGregorianForDB($jalali_date) {
    if (empty($jalali_date)) return null;
    $parts = explode('/', $jalali_date);
    if (count($parts) != 3) return null;
    $jy = (int)$parts[0];
    $jm = (int)$parts[1];
    $jd = (int)$parts[2];
    $gregorian = jalali_to_gregorian($jy, $jm, $jd);
    return sprintf('%04d-%02d-%02d', $gregorian[0], $gregorian[1], $gregorian[2]);
}

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

function jalaliDate($date = null, $format = 'Y/m/d') {
    if (!$date) $date = time();
    $date = is_numeric($date) ? $date : strtotime($date);
    $year = date('Y', $date);
    $month = date('m', $date);
    $day = date('d', $date);
    $jalali = gregorian_to_jalali($year, $month, $day);
    return $jalali[0] . '/' . $jalali[1] . '/' . $jalali[2];
}

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

function en2fa_digits($input) {
    $en = array('0','1','2','3','4','5','6','7','8','9');
    $fa = array('۰','۱','۲','۳','۴','۵','۶','۷','۸','۹');
    return str_replace($en, $fa, (string)$input);
}

// توابع کمکی اضافی
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function validatePhone($phone) {
    return preg_match('/^[0-9]{10,11}$/', $phone);
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function redirect($url, $statusCode = 303) {
    header('Location: ' . $url, true, $statusCode);
    exit();
}

function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

function verifyCsrfToken($token = null) {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? '';
    }
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token");
    }
}

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

function require_auth($required_role = 'کاربر عادی') {
    checkPermission($required_role);
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
}

function hasPermission($permission) {
    if (!isset($_SESSION['user_id'])) return false;
    $user_role = $_SESSION['role'] ?? 'کاربر عادی';
    if ($user_role === 'ادمین') return true;
    $permissions = [
        'مدیر عملیات' => ['tickets.*', 'maintenance.*', 'customers.*', 'reports.*'],
        'تکنسین' => ['tickets.view', 'tickets.edit', 'maintenance.*'],
        'پشتیبانی' => ['tickets.*', 'customers.*'],
        'کاربر عادی' => ['tickets.view']
    ];
    if (!isset($permissions[$user_role])) return false;
    $user_permissions = $permissions[$user_role];
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

function generateRandomCode($length = 8) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}

function generateSerialNumber($prefix = 'SN') {
    return $prefix . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

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

function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

function getRealUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

function isMobile() {
    return preg_match('/(android|iphone|ipad|mobile)/i', $_SERVER['HTTP_USER_AGENT'] ?? '');
}

function generateQRCode($text, $size = 200) {
    return "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data=" . urlencode($text);
}

function cleanOldFiles($directory, $days = 30) {
    $files = glob($directory . '/*');
    $now = time();
    foreach ($files as $file) {
        if (is_file($file) && ($now - filemtime($file)) > ($days * 24 * 60 * 60)) {
            unlink($file);
        }
    }
}

// ثبت لاگ سیستم
function logAction($pdo, $action, $description = '', $severity = 'info', $module = 'SYSTEM', $data = []) {
    $user_id = $_SESSION['user_id'] ?? null;
    $ip_address = getRealUserIP();
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $session_id = session_id();
    $request_method = $_SERVER['REQUEST_METHOD'] ?? '';
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $file_path = $_SERVER['SCRIPT_NAME'] ?? '';
    $line_number = 0;
    $stack_trace = '';
    $execution_time = microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
    $memory_usage = memory_get_usage(true);
    $request_data = json_encode($_POST ?? []);
    $response_data = '';
    
    $stmt = $pdo->prepare("
        INSERT INTO system_logs (
            user_id, action, description, ip_address, user_agent, session_id, 
            request_method, request_uri, referer, file_path, line_number, 
            stack_trace, execution_time, memory_usage, severity, module, 
            request_data, response_data
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $user_id, $action, $description, $ip_address, $user_agent, $session_id,
        $request_method, $request_uri, $referer, $file_path, $line_number,
        $stack_trace, $execution_time, $memory_usage, $severity, $module,
        $request_data, $response_data
    ]);
}

// نام مستعار برای logAction
function log_action($action, $description = '') {
    global $pdo;
    logAction($pdo, $action, $description);
}

// Include auto logger
if (file_exists(__DIR__ . '/auto_logger.php')) {
    require_once __DIR__ . '/auto_logger.php';
}

?>