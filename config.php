<?php
// config.php - نسخه نهایی و کامل (ترکیب قابلیت‌های اصلی + مدیریت خودروها)

// شروع session اگر شروع نشده
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تنظیمات امنیتی
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php-errors.log');

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

// ایجاد جداول به صورت idempotent در هر درخواست
createDatabaseTables($pdo);

/**
 * ایجاد ساختار دیتابیس و پوشه‌ها (Idempotent)
 */
function createDatabaseTables($pdo) {
    // ایجاد پوشه‌های ضروری
    $dirs = [
        __DIR__ . '/uploads',
        __DIR__ . '/uploads/installations',
        __DIR__ . '/uploads/assets',
        __DIR__ . '/uploads/filters',
        __DIR__ . '/uploads/training',
        __DIR__ . '/uploads/training/forms',
        __DIR__ . '/uploads/training/gallery',
        __DIR__ . '/uploads/training/videos',
        __DIR__ . '/uploads/training/articles',
        __DIR__ . '/uploads/finance',
        __DIR__ . '/uploads/contracts',
        __DIR__ . '/uploads/car_maintenance',
        __DIR__ . '/uploads/car_fuel',
        __DIR__ . '/uploads/car_incidents'
    ];
    
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }

    // ایجاد .htaccess برای امنیت
    if (!file_exists(__DIR__ . '/uploads/.htaccess')) {
        file_put_contents(
            __DIR__ . '/uploads/.htaccess',
            "Order deny,allow\nDeny from all\n<Files ~ \"\\.(jpg|jpeg|png|gif|webp|pdf|doc|docx|xls|xlsx|ppt|pptx|zip|mp4|webm|ogg)$\">\nAllow from all\n</Files>"
        );
    }

    // کوئری ساخت جداول
    $tables = [
        // انواع دارایی‌ها
        "CREATE TABLE IF NOT EXISTS asset_types (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL UNIQUE,
            display_name VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        // دارایی‌ها
        "CREATE TABLE IF NOT EXISTS assets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            type_id INT NOT NULL,
            serial_number VARCHAR(255) UNIQUE,
            status ENUM('فعال','غیرفعال','در حال تعمیر','آماده بهره‌برداری') DEFAULT 'فعال',
            brand VARCHAR(255),
            model VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (type_id) REFERENCES asset_types(id) ON DELETE CASCADE,
            INDEX idx_type_id (type_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        // مشتریان
        "CREATE TABLE IF NOT EXISTS customers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(255) NOT NULL,
            phone VARCHAR(20) NOT NULL UNIQUE,
            company VARCHAR(255),
            customer_type ENUM('حقیقی','حقوقی') DEFAULT 'حقیقی',
            address TEXT,
            city VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_phone (phone),
            INDEX idx_company (company)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        // کاربران
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(255),
            role ENUM('ادمین','کاربر عادی','اپراتور','مدیر عملیات','تکنسین','پشتیبانی') DEFAULT 'کاربر عادی',
            is_active BOOLEAN DEFAULT true,
            last_login TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_username (username),
            INDEX idx_role (role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        // انتساب‌ها
        "CREATE TABLE IF NOT EXISTS asset_assignments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            asset_id INT NOT NULL,
            customer_id INT NOT NULL,
            assignment_date DATE,
            assignment_status ENUM('فعال','خاتمه یافته','موقت') DEFAULT 'فعال',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
            INDEX idx_asset_id (asset_id),
            INDEX idx_customer_id (customer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        // لاگ سیستم
        "CREATE TABLE IF NOT EXISTS system_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            action VARCHAR(100) NOT NULL,
            description TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_action (action)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        // --- ماژول مدیریت خودروهای شرکتی ---
        "CREATE TABLE IF NOT EXISTS company_cars (
            id INT AUTO_INCREMENT PRIMARY KEY,
            car_plate VARCHAR(20) UNIQUE NOT NULL,
            car_name VARCHAR(255) NOT NULL,
            car_model VARCHAR(255) NOT NULL,
            car_year INT,
            car_color VARCHAR(50),
            car_type ENUM('سواری','وانت','کامیون','مینی‌بوس','اتوبوس','سایر') DEFAULT 'سواری',
            engine_number VARCHAR(100),
            chassis_number VARCHAR(100),
            insurance_number VARCHAR(100),
            insurance_expiry DATE,
            technical_inspection_expiry DATE,
            fuel_type ENUM('بنزین','گازوئیل','گاز','برقی','هیبرید') DEFAULT 'بنزین',
            fuel_capacity DECIMAL(5,2),
            current_mileage INT DEFAULT 0,
            max_mileage INT,
            purchase_date DATE,
            purchase_price DECIMAL(12,2),
            current_value DECIMAL(12,2),
            status ENUM('فعال','غیرفعال','در تعمیر','آماده تحویل','تحویل داده شده','برگشت داده شده') DEFAULT 'فعال',
            location VARCHAR(255),
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_car_plate (car_plate),
            INDEX idx_status (status),
            INDEX idx_car_type (car_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        "CREATE TABLE IF NOT EXISTS car_assignments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            car_id INT NOT NULL,
            assigned_to INT NOT NULL,
            assignment_type ENUM('تحویل','برگشت','تعمیر','سرویس','سایر') DEFAULT 'تحویل',
            assignment_date DATETIME NOT NULL,
            return_date DATETIME NULL,
            assignment_reason TEXT,
            assignment_location VARCHAR(255),
            destination VARCHAR(255),
            expected_return_date DATETIME,
            mileage_at_assignment INT,
            mileage_at_return INT,
            fuel_level_at_assignment ENUM('کم','متوسط','زیاد','پر') DEFAULT 'متوسط',
            fuel_level_at_return ENUM('کم','متوسط','زیاد','پر') DEFAULT 'متوسط',
            car_condition_at_assignment TEXT,
            car_condition_at_return TEXT,
            accessories_included JSON,
            accessories_returned JSON,
            assignment_status ENUM('فعال','تکمیل شده','لغو شده','تاخیر') DEFAULT 'فعال',
            assigned_by INT NOT NULL,
            approved_by INT NULL,
            approval_date DATETIME NULL,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (car_id) REFERENCES company_cars(id) ON DELETE CASCADE,
            FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_car_id (car_id),
            INDEX idx_assigned_to (assigned_to),
            INDEX idx_assignment_date (assignment_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        "CREATE TABLE IF NOT EXISTS car_maintenance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            car_id INT NOT NULL,
            maintenance_type ENUM('تعمیر دوره‌ای','تعمیر اضطراری','سرویس','تعویض روغن','تعویض لاستیک','کالیبراسیون','سایر') NOT NULL,
            maintenance_date DATETIME NOT NULL,
            maintenance_location VARCHAR(255),
            maintenance_provider VARCHAR(255),
            maintenance_cost DECIMAL(10,2) DEFAULT 0,
            mileage_at_maintenance INT,
            description TEXT NOT NULL,
            parts_replaced JSON,
            labor_hours DECIMAL(5,2),
            next_maintenance_date DATE,
            next_maintenance_mileage INT,
            maintenance_status ENUM('برنامه‌ریزی شده','در حال انجام','تکمیل شده','لغو شده') DEFAULT 'برنامه‌ریزی شده',
            performed_by INT NULL,
            approved_by INT NULL,
            approval_date DATETIME NULL,
            receipt_path VARCHAR(500),
            warranty_expiry DATE,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (car_id) REFERENCES company_cars(id) ON DELETE CASCADE,
            FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_car_id (car_id),
            INDEX idx_maintenance_date (maintenance_date),
            INDEX idx_status (maintenance_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        "CREATE TABLE IF NOT EXISTS car_fuel_records (
            id INT AUTO_INCREMENT PRIMARY KEY,
            car_id INT NOT NULL,
            fuel_date DATETIME NOT NULL,
            fuel_type ENUM('بنزین','گازوئیل','گاز') NOT NULL,
            fuel_amount DECIMAL(8,2) NOT NULL,
            fuel_cost DECIMAL(10,2) NOT NULL,
            fuel_station VARCHAR(255),
            driver_id INT NULL,
            mileage_at_fuel INT,
            fuel_efficiency DECIMAL(5,2),
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (car_id) REFERENCES company_cars(id) ON DELETE CASCADE,
            FOREIGN KEY (driver_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_car_id (car_id),
            INDEX idx_fuel_date (fuel_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        "CREATE TABLE IF NOT EXISTS car_incidents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            car_id INT NOT NULL,
            incident_date DATETIME NOT NULL,
            incident_type ENUM('تصادف','خرابی','دزدی','آتش‌سوزی','طبیعی','سایر') NOT NULL,
            incident_description TEXT NOT NULL,
            incident_location VARCHAR(255),
            damage_estimate DECIMAL(10,2),
            insurance_claim_number VARCHAR(100),
            police_report_number VARCHAR(100),
            incident_status ENUM('گزارش شده','در حال بررسی','تکمیل شده','بسته شده') DEFAULT 'گزارش شده',
            reported_by INT NOT NULL,
            assigned_to INT NULL,
            resolution_notes TEXT,
            photos JSON,
            documents JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (car_id) REFERENCES company_cars(id) ON DELETE CASCADE,
            FOREIGN KEY (reported_by) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_car_id (car_id),
            INDEX idx_incident_date (incident_date),
            INDEX idx_incident_type (incident_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        "CREATE TABLE IF NOT EXISTS car_drivers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            driver_license_number VARCHAR(50) UNIQUE NOT NULL,
            license_type ENUM('A','B','C','D','E') NOT NULL,
            license_expiry_date DATE NOT NULL,
            medical_certificate_expiry DATE,
            training_completed BOOLEAN DEFAULT false,
            is_active BOOLEAN DEFAULT true,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_license_number (driver_license_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        "CREATE TABLE IF NOT EXISTS car_usage_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            car_id INT NOT NULL,
            driver_id INT NOT NULL,
            usage_date DATETIME NOT NULL,
            start_mileage INT,
            end_mileage INT,
            distance_traveled INT,
            purpose VARCHAR(255),
            destination VARCHAR(255),
            fuel_consumed DECIMAL(5,2),
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (car_id) REFERENCES company_cars(id) ON DELETE CASCADE,
            FOREIGN KEY (driver_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_car_id (car_id),
            INDEX idx_driver_id (driver_id),
            INDEX idx_usage_date (usage_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        "CREATE TABLE IF NOT EXISTS car_reservations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            car_id INT NOT NULL,
            requested_by INT NOT NULL,
            start_date DATETIME NOT NULL,
            end_date DATETIME NOT NULL,
            purpose VARCHAR(255),
            destination VARCHAR(255),
            expected_passengers INT,
            special_requirements TEXT,
            reservation_status ENUM('در انتظار تایید','تایید شده','رد شده','لغو شده','تکمیل شده') DEFAULT 'در انتظار تایید',
            approved_by INT NULL,
            approval_date DATETIME NULL,
            rejection_reason TEXT,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (car_id) REFERENCES company_cars(id) ON DELETE CASCADE,
            FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_car_id (car_id),
            INDEX idx_requested_by (requested_by),
            INDEX idx_reservation_date (start_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci"
    ];

    // اجرای کوئری‌ها
    foreach ($tables as $table) {
        try {
            $pdo->exec($table);
        } catch (Exception $e) {
            error_log("خطا در ایجاد جدول: " . $e->getMessage());
        }
    }

    // درج داده‌های اولیه
    try {
        // بررسی وجود کاربر ادمین
        $check_users = $pdo->query("SELECT COUNT(*) as count FROM users")->fetch();
        if ($check_users['count'] == 0) {
            $pdo->exec("INSERT INTO users (username, password, full_name, role) VALUES ('admin', '" . password_hash('admin', PASSWORD_DEFAULT) . "', 'مدیر سیستم', 'ادمین')");
        }

        // بررسی وجود انواع دارایی
        $check_types = $pdo->query("SELECT COUNT(*) as count FROM asset_types")->fetch();
        if ($check_types['count'] == 0) {
            $pdo->exec("INSERT INTO asset_types (name, display_name) VALUES 
                ('generator','ژنراتور'), 
                ('power_motor','موتور برق'), 
                ('consumable','اقلام مصرفی'),
                ('car','خودرو'),
                ('equipment','تجهیزات')");
        }
    } catch (Exception $e) {
        error_log("خطا در درج داده‌های اولیه: " . $e->getMessage());
    }
}

// توابع اصلی سیستم
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function logAction($pdo, $action, $description = '') {
    try {
        $user_id = $_SESSION['user_id'] ?? null;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt = $pdo->prepare("INSERT INTO system_logs (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $description, $ip_address, $user_agent]);
    } catch (Exception $e) {
        error_log("Action: $action - $description");
    }
}

function verifyCsrfToken($token = null) {
    $token = $token ?? ($_POST['csrf_token'] ?? '');
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        die('CSRF Token validation failed');
    }
}

function jalali_format($datetime, $format = 'Y/m/d H:i') {
    if (!$datetime || in_array($datetime, ['0000-00-00', '0000-00-00 00:00:00'])) return '';
    $ts = is_numeric($datetime) ? intval($datetime) : strtotime($datetime);
    if ($ts === false) return $datetime;
    return date('Y/m/d H:i', $ts);
}

function checkPermission($required_role) {
    $user_role = $_SESSION['role'] ?? '';
    $role_hierarchy = ['کاربر عادی' => 1, 'اپراتور' => 2, 'تکنسین' => 3, 'پشتیبانی' => 4, 'مدیر عملیات' => 5, 'ادمین' => 6];
    
    $user_level = $role_hierarchy[$user_role] ?? 0;
    $required_level = $role_hierarchy[$required_role] ?? 0;
    
    return $user_level >= $required_level;
}

function hasPermission($permission) {
    $user_role = $_SESSION['role'] ?? '';
    $permissions = [
        'ادمین' => ['all'],
        'مدیر عملیات' => ['view_reports', 'manage_assets', 'manage_customers', 'manage_cars'],
        'تکنسین' => ['view_assets', 'manage_assignments', 'view_cars'],
        'پشتیبانی' => ['view_customers', 'view_assignments'],
        'اپراتور' => ['view_assets', 'view_customers'],
        'کاربر عادی' => ['view_own_data']
    ];
    
    $user_permissions = $permissions[$user_role] ?? [];
    return in_array('all', $user_permissions) || in_array($permission, $user_permissions);
}

function uploadFile($file, $upload_dir, $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx']) {
    if (!isset($file['error']) || is_array($file['error'])) {
        return false;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_extension, $allowed_types)) {
        return false;
    }

    $filename = uniqid() . '.' . $file_extension;
    $upload_path = $upload_dir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        return false;
    }

    return $filename;
}

// توابع مدیریت خودروها
function addCompanyCar($pdo, $data) {
    $stmt = $pdo->prepare("INSERT INTO company_cars (
        car_plate, car_name, car_model, car_year, car_color, car_type,
        engine_number, chassis_number, insurance_number, insurance_expiry,
        technical_inspection_expiry, fuel_type, fuel_capacity, current_mileage,
        max_mileage, purchase_date, purchase_price, current_value, status,
        location, notes
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->execute([
        $data['car_plate'], $data['car_name'], $data['car_model'], $data['car_year'], $data['car_color'],
        $data['car_type'], $data['engine_number'] ?? null, $data['chassis_number'] ?? null, 
        $data['insurance_number'] ?? null, $data['insurance_expiry'] ?? null,
        $data['technical_inspection_expiry'] ?? null, $data['fuel_type'] ?? 'بنزین', 
        $data['fuel_capacity'] ?? null, $data['current_mileage'] ?? 0,
        $data['max_mileage'] ?? null, $data['purchase_date'] ?? null, $data['purchase_price'] ?? null,
        $data['current_value'] ?? null, $data['status'] ?? 'فعال',
        $data['location'] ?? null, $data['notes'] ?? null
    ]);
    
    return $pdo->lastInsertId();
}

function assignCar($pdo, $data) {
    $pdo->beginTransaction();
    try {
        // اضافه کردن انتساب
        $stmt = $pdo->prepare("INSERT INTO car_assignments (
            car_id, assigned_to, assignment_type, assignment_date, assignment_reason,
            assignment_location, destination, expected_return_date, mileage_at_assignment,
            fuel_level_at_assignment, car_condition_at_assignment, accessories_included,
            assignment_status, assigned_by, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $data['car_id'], $data['assigned_to'], $data['assignment_type'], $data['assignment_date'],
            $data['assignment_reason'] ?? null, $data['assignment_location'] ?? null, 
            $data['destination'] ?? null, $data['expected_return_date'] ?? null,
            $data['mileage_at_assignment'] ?? null, $data['fuel_level_at_assignment'] ?? 'متوسط',
            $data['car_condition_at_assignment'] ?? null, 
            isset($data['accessories_included']) ? json_encode($data['accessories_included']) : null,
            'فعال', $_SESSION['user_id'], $data['notes'] ?? null
        ]);
        
        $assignment_id = $pdo->lastInsertId();
        
        // به‌روزرسانی وضعیت خودرو
        $stmt = $pdo->prepare("UPDATE company_cars SET status = 'تحویل داده شده', current_mileage = ? WHERE id = ?");
        $stmt->execute([$data['mileage_at_assignment'] ?? 0, $data['car_id']]);
        
        $pdo->commit();
        return $assignment_id;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function returnCar($pdo, $assignment_id, $data) {
    $pdo->beginTransaction();
    try {
        // به‌روزرسانی انتساب
        $stmt = $pdo->prepare("UPDATE car_assignments SET 
            return_date = ?, mileage_at_return = ?, fuel_level_at_return = ?,
            car_condition_at_return = ?, accessories_returned = ?, assignment_status = 'تکمیل شده',
            notes = CONCAT(IFNULL(notes, ''), '\n', ?)
            WHERE id = ?");
        
        $stmt->execute([
            $data['return_date'], $data['mileage_at_return'] ?? null, $data['fuel_level_at_return'] ?? 'متوسط',
            $data['car_condition_at_return'] ?? null, 
            isset($data['accessories_returned']) ? json_encode($data['accessories_returned']) : null,
            $data['notes'] ?? '', $assignment_id
        ]);
        
        // دریافت اطلاعات خودرو
        $stmt = $pdo->prepare("SELECT car_id FROM car_assignments WHERE id = ?");
        $stmt->execute([$assignment_id]);
        $car_id = $stmt->fetch()['car_id'];
        
        // به‌روزرسانی وضعیت خودرو
        $stmt = $pdo->prepare("UPDATE company_cars SET status = 'فعال', current_mileage = ? WHERE id = ?");
        $stmt->execute([$data['mileage_at_return'] ?? 0, $car_id]);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function addCarMaintenance($pdo, $data) {
    $stmt = $pdo->prepare("INSERT INTO car_maintenance (
        car_id, maintenance_type, maintenance_date, maintenance_location,
        maintenance_provider, maintenance_cost, mileage_at_maintenance, description,
        parts_replaced, labor_hours, next_maintenance_date, next_maintenance_mileage,
        maintenance_status, performed_by, approved_by, approval_date, receipt_path,
        warranty_expiry, notes
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->execute([
        $data['car_id'], $data['maintenance_type'], $data['maintenance_date'],
        $data['maintenance_location'] ?? null, $data['maintenance_provider'] ?? null,
        $data['maintenance_cost'] ?? 0, $data['mileage_at_maintenance'] ?? null,
        $data['description'], isset($data['parts_replaced']) ? json_encode($data['parts_replaced']) : null,
        $data['labor_hours'] ?? null, $data['next_maintenance_date'] ?? null,
        $data['next_maintenance_mileage'] ?? null, $data['maintenance_status'] ?? 'برنامه‌ریزی شده',
        $data['performed_by'] ?? null, $data['approved_by'] ?? null, $data['approval_date'] ?? null,
        $data['receipt_path'] ?? null, $data['warranty_expiry'] ?? null, $data['notes'] ?? null
    ]);
    
    return $pdo->lastInsertId();
}

function addFuelRecord($pdo, $data) {
    $stmt = $pdo->prepare("INSERT INTO car_fuel_records (
        car_id, fuel_date, fuel_type, fuel_amount, fuel_cost, fuel_station,
        driver_id, mileage_at_fuel, fuel_efficiency, notes
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->execute([
        $data['car_id'], $data['fuel_date'], $data['fuel_type'], $data['fuel_amount'],
        $data['fuel_cost'], $data['fuel_station'] ?? null, $data['driver_id'] ?? null,
        $data['mileage_at_fuel'] ?? null, $data['fuel_efficiency'] ?? null, $data['notes'] ?? null
    ]);
    
    return $pdo->lastInsertId();
}

function reportCarIncident($pdo, $data) {
    $stmt = $pdo->prepare("INSERT INTO car_incidents (
        car_id, incident_date, incident_type, incident_description, incident_location,
        damage_estimate, insurance_claim_number, police_report_number, incident_status,
        reported_by, assigned_to, resolution_notes, photos, documents
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->execute([
        $data['car_id'], $data['incident_date'], $data['incident_type'], $data['incident_description'],
        $data['incident_location'] ?? null, $data['damage_estimate'] ?? null,
        $data['insurance_claim_number'] ?? null, $data['police_report_number'] ?? null,
        $data['incident_status'] ?? 'گزارش شده', $_SESSION['user_id'], $data['assigned_to'] ?? null,
        $data['resolution_notes'] ?? null, 
        isset($data['photos']) ? json_encode($data['photos']) : null,
        isset($data['documents']) ? json_encode($data['documents']) : null
    ]);
    
    return $pdo->lastInsertId();
}

function registerDriver($pdo, $data) {
    $stmt = $pdo->prepare("INSERT INTO car_drivers (
        user_id, driver_license_number, license_type, license_expiry_date,
        medical_certificate_expiry, training_completed, is_active, notes
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->execute([
        $data['user_id'], $data['driver_license_number'], $data['license_type'],
        $data['license_expiry_date'], $data['medical_certificate_expiry'] ?? null,
        $data['training_completed'] ?? false, $data['is_active'] ?? true, $data['notes'] ?? null
    ]);
    
    return $pdo->lastInsertId();
}

function requestCarReservation($pdo, $data) {
    $stmt = $pdo->prepare("INSERT INTO car_reservations (
        car_id, requested_by, start_date, end_date, purpose, destination,
        expected_passengers, special_requirements, reservation_status, notes
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->execute([
        $data['car_id'], $data['requested_by'], $data['start_date'], $data['end_date'],
        $data['purpose'], $data['destination'] ?? null, $data['expected_passengers'] ?? null,
        $data['special_requirements'] ?? null, $data['reservation_status'] ?? 'در انتظار تایید',
        $data['notes'] ?? null
    ]);
    
    return $pdo->lastInsertId();
}

function getCarStatistics($pdo, $filters = []) {
    $where_clause = "";
    $params = [];
    
    if (!empty($filters['start_date'])) {
        $where_clause .= " AND created_at >= ?";
        $params[] = $filters['start_date'];
    }
    
    if (!empty($filters['end_date'])) {
        $where_clause .= " AND created_at <= ?";
        $params[] = $filters['end_date'];
    }
    
    if (!empty($filters['car_id'])) {
        $where_clause .= " AND car_id = ?";
        $params[] = $filters['car_id'];
    }
    
    $stats = [];
    
    // آمار کلی خودروها
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM company_cars");
    $stmt->execute();
    $stats['total_cars'] = $stmt->fetch()['total'] ?? 0;
    
    // آمار انتساب‌ها
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM car_assignments $where_clause");
    $stmt->execute($params);
    $stats['total_assignments'] = $stmt->fetch()['total'] ?? 0;
    
    // هزینه تعمیرات
    $stmt = $pdo->prepare("SELECT SUM(maintenance_cost) as total FROM car_maintenance $where_clause");
    $stmt->execute($params);
    $stats['maintenance_cost'] = $stmt->fetch()['total'] ?? 0;
    
    // هزینه سوخت
    $stmt = $pdo->prepare("SELECT SUM(fuel_cost) as total FROM car_fuel_records $where_clause");
    $stmt->execute($params);
    $stats['fuel_cost'] = $stmt->fetch()['total'] ?? 0;
    
    return $stats;
}

function getAvailableCars($pdo, $start_date, $end_date) {
    $stmt = $pdo->prepare("SELECT cc.* FROM company_cars cc 
        WHERE cc.status = 'فعال' 
        AND cc.id NOT IN (
            SELECT car_id FROM car_assignments 
            WHERE assignment_status = 'فعال' 
            AND ((assignment_date <= ? AND (return_date IS NULL OR return_date >= ?)) 
                 OR (assignment_date <= ? AND (return_date IS NULL OR return_date >= ?)))
        )
        ORDER BY cc.car_name");
    
    $stmt->execute([$end_date, $start_date, $end_date, $start_date]);
    return $stmt->fetchAll();
}

function getCarAssignments($pdo, $filters = []) {
    $where_clause = "WHERE 1=1";
    $params = [];
    
    if (!empty($filters['car_id'])) {
        $where_clause .= " AND ca.car_id = ?";
        $params[] = $filters['car_id'];
    }
    
    if (!empty($filters['assigned_to'])) {
        $where_clause .= " AND ca.assigned_to = ?";
        $params[] = $filters['assigned_to'];
    }
    
    if (!empty($filters['status'])) {
        $where_clause .= " AND ca.assignment_status = ?";
        $params[] = $filters['status'];
    }
    
    $stmt = $pdo->prepare("SELECT ca.*, cc.car_plate, cc.car_name, cc.car_model, 
        u1.full_name as assigned_to_name, u2.full_name as assigned_by_name, u3.full_name as approved_by_name
        FROM car_assignments ca
        LEFT JOIN company_cars cc ON ca.car_id = cc.id
        LEFT JOIN users u1 ON ca.assigned_to = u1.id
        LEFT JOIN users u2 ON ca.assigned_by = u2.id
        LEFT JOIN users u3 ON ca.approved_by = u3.id
        $where_clause
        ORDER BY ca.assignment_date DESC");
    
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function logCarUsage($pdo, $data) {
    $stmt = $pdo->prepare("INSERT INTO car_usage_logs (
        car_id, driver_id, usage_date, start_mileage, end_mileage,
        distance_traveled, purpose, destination, fuel_consumed, notes
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->execute([
        $data['car_id'], $data['driver_id'], $data['usage_date'],
        $data['start_mileage'] ?? null, $data['end_mileage'] ?? null,
        $data['distance_traveled'] ?? null, $data['purpose'] ?? null,
        $data['destination'] ?? null, $data['fuel_consumed'] ?? null, $data['notes'] ?? null
    ]);
    
    return $pdo->lastInsertId();
}

?>