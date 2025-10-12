<?php
// config.php - نسخه ساده و کارآمد

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تنظیمات امنیتی
error_reporting(E_ALL);
ini_set('display_errors', 1); // موقتاً فعال برای دیباگ
ini_set('log_errors', 1);

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
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("خطا در اتصال به دیتابیس: " . $e->getMessage());
}

// CSRF Token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ایجاد جداول ساده
try {
    // جدول خودروها
    $pdo->exec("CREATE TABLE IF NOT EXISTS company_cars (
        id INT AUTO_INCREMENT PRIMARY KEY,
        car_plate VARCHAR(20) UNIQUE NOT NULL,
        car_name VARCHAR(255) NOT NULL,
        car_model VARCHAR(255) NOT NULL,
        car_year INT,
        car_color VARCHAR(50),
        car_type ENUM('سواری','وانت','کامیون','مینی‌بوس','اتوبوس','سایر') DEFAULT 'سواری',
        status ENUM('فعال','غیرفعال','در تعمیر','آماده تحویل','تحویل داده شده','برگشت داده شده') DEFAULT 'فعال',
        current_mileage INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");

    // جدول انتساب‌ها
    $pdo->exec("CREATE TABLE IF NOT EXISTS car_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        car_id INT NOT NULL,
        assigned_to INT NOT NULL,
        assignment_type ENUM('تحویل','برگشت','تعمیر','سرویس','سایر') DEFAULT 'تحویل',
        assignment_date DATETIME NOT NULL,
        return_date DATETIME NULL,
        assignment_status ENUM('فعال','تکمیل شده','لغو شده','تاخیر') DEFAULT 'فعال',
        assigned_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (car_id) REFERENCES company_cars(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");

    // جدول تعمیرات
    $pdo->exec("CREATE TABLE IF NOT EXISTS car_maintenance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        car_id INT NOT NULL,
        maintenance_type ENUM('تعمیر دوره‌ای','تعمیر اضطراری','سرویس','تعویض روغن','تعویض لاستیک','کالیبراسیون','سایر') NOT NULL,
        maintenance_date DATETIME NOT NULL,
        description TEXT NOT NULL,
        maintenance_status ENUM('برنامه‌ریزی شده','در حال انجام','تکمیل شده','لغو شده') DEFAULT 'برنامه‌ریزی شده',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (car_id) REFERENCES company_cars(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");

    // جدول سوخت
    $pdo->exec("CREATE TABLE IF NOT EXISTS car_fuel_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        car_id INT NOT NULL,
        fuel_date DATETIME NOT NULL,
        fuel_type ENUM('بنزین','گازوئیل','گاز') NOT NULL,
        fuel_amount DECIMAL(8,2) NOT NULL,
        fuel_cost DECIMAL(10,2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (car_id) REFERENCES company_cars(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");

    // جدول حوادث
    $pdo->exec("CREATE TABLE IF NOT EXISTS car_incidents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        car_id INT NOT NULL,
        incident_date DATETIME NOT NULL,
        incident_type ENUM('تصادف','خرابی','دزدی','آتش‌سوزی','طبیعی','سایر') NOT NULL,
        incident_description TEXT NOT NULL,
        incident_status ENUM('گزارش شده','در حال بررسی','تکمیل شده','بسته شده') DEFAULT 'گزارش شده',
        reported_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (car_id) REFERENCES company_cars(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");

    // جدول رانندگان
    $pdo->exec("CREATE TABLE IF NOT EXISTS car_drivers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        driver_license_number VARCHAR(50) UNIQUE NOT NULL,
        license_type ENUM('A','B','C','D','E') NOT NULL,
        license_expiry_date DATE NOT NULL,
        is_active BOOLEAN DEFAULT true,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");

    // جدول رزروها
    $pdo->exec("CREATE TABLE IF NOT EXISTS car_reservations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        car_id INT NOT NULL,
        requested_by INT NOT NULL,
        start_date DATETIME NOT NULL,
        end_date DATETIME NOT NULL,
        purpose VARCHAR(255),
        reservation_status ENUM('در انتظار تایید','تایید شده','رد شده','لغو شده','تکمیل شده') DEFAULT 'در انتظار تایید',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (car_id) REFERENCES company_cars(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");

} catch (Exception $e) {
    error_log("خطا در ایجاد جداول: " . $e->getMessage());
}

// توابع ساده
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
        
        // اگر جدول system_logs وجود داشت
        $stmt = $pdo->prepare("INSERT INTO system_logs (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $description, $ip_address, $user_agent]);
    } catch (Exception $e) {
        // اگر جدول وجود نداشت، فقط لاگ کن
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

// توابع مدیریت خودروها
function addCompanyCar($pdo, $data) {
    $stmt = $pdo->prepare("INSERT INTO company_cars (car_plate, car_name, car_model, car_year, car_color, car_type, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $data['car_plate'], 
        $data['car_name'], 
        $data['car_model'], 
        $data['car_year'] ?? null,
        $data['car_color'] ?? null,
        $data['car_type'] ?? 'سواری',
        $data['status'] ?? 'فعال'
    ]);
    return $pdo->lastInsertId();
}

function getCarStatistics($pdo) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM company_cars");
        $result = $stmt->fetch();
        return ['total_cars' => $result['total'] ?? 0];
    } catch (Exception $e) {
        return ['total_cars' => 0];
    }
}

function getAvailableCars($pdo, $start_date, $end_date) {
    try {
        $stmt = $pdo->query("SELECT * FROM company_cars WHERE status = 'فعال' ORDER BY car_name");
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function getCarAssignments($pdo, $filters = []) {
    try {
        $stmt = $pdo->query("SELECT ca.*, cc.car_plate, cc.car_name, cc.car_model FROM car_assignments ca LEFT JOIN company_cars cc ON ca.car_id = cc.id ORDER BY ca.assignment_date DESC");
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function assignCar($pdo, $data) {
    try {
        $stmt = $pdo->prepare("INSERT INTO car_assignments (car_id, assigned_to, assignment_type, assignment_date, assignment_status, assigned_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['car_id'], 
            $data['assigned_to'], 
            $data['assignment_type'], 
            $data['assignment_date'], 
            'فعال', 
            $_SESSION['user_id']
        ]);
        return $pdo->lastInsertId();
    } catch (Exception $e) {
        throw $e;
    }
}

function returnCar($pdo, $assignment_id, $data) {
    try {
        $stmt = $pdo->prepare("UPDATE car_assignments SET return_date = ?, assignment_status = 'تکمیل شده' WHERE id = ?");
        $stmt->execute([$data['return_date'], $assignment_id]);
        return true;
    } catch (Exception $e) {
        throw $e;
    }
}

function addCarMaintenance($pdo, $data) {
    try {
        $stmt = $pdo->prepare("INSERT INTO car_maintenance (car_id, maintenance_type, maintenance_date, description, maintenance_status) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['car_id'], 
            $data['maintenance_type'], 
            $data['maintenance_date'], 
            $data['description'], 
            'برنامه‌ریزی شده'
        ]);
        return $pdo->lastInsertId();
    } catch (Exception $e) {
        throw $e;
    }
}

function addFuelRecord($pdo, $data) {
    try {
        $stmt = $pdo->prepare("INSERT INTO car_fuel_records (car_id, fuel_date, fuel_type, fuel_amount, fuel_cost) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['car_id'], 
            $data['fuel_date'], 
            $data['fuel_type'], 
            $data['fuel_amount'], 
            $data['fuel_cost']
        ]);
        return $pdo->lastInsertId();
    } catch (Exception $e) {
        throw $e;
    }
}

function reportCarIncident($pdo, $data) {
    try {
        $stmt = $pdo->prepare("INSERT INTO car_incidents (car_id, incident_date, incident_type, incident_description, reported_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['car_id'], 
            $data['incident_date'], 
            $data['incident_type'], 
            $data['incident_description'], 
            $_SESSION['user_id']
        ]);
        return $pdo->lastInsertId();
    } catch (Exception $e) {
        throw $e;
    }
}

function registerDriver($pdo, $data) {
    try {
        $stmt = $pdo->prepare("INSERT INTO car_drivers (user_id, driver_license_number, license_type, license_expiry_date) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $data['user_id'], 
            $data['driver_license_number'], 
            $data['license_type'], 
            $data['license_expiry_date']
        ]);
        return $pdo->lastInsertId();
    } catch (Exception $e) {
        throw $e;
    }
}

function requestCarReservation($pdo, $data) {
    try {
        $stmt = $pdo->prepare("INSERT INTO car_reservations (car_id, requested_by, start_date, end_date, purpose) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['car_id'], 
            $data['requested_by'], 
            $data['start_date'], 
            $data['end_date'], 
            $data['purpose']
        ]);
        return $pdo->lastInsertId();
    } catch (Exception $e) {
        throw $e;
    }
}
?>