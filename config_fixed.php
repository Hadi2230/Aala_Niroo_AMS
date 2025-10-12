<?php
// نسخه ساده و بدون خطا config.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تنظیمات دیتابیس
$host = 'localhost:3306';
$dbname = 'aala_niroo';
$username = 'root';
$password = '';

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
        $stmt = $pdo->prepare("INSERT INTO system_logs (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $description, $ip_address, $user_agent]);
    } catch (Exception $e) {
        // اگر جدول system_logs وجود نداشت، فقط لاگ کن
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
    $stmt = $pdo->prepare("INSERT INTO company_cars (car_plate, car_name, car_model, status) VALUES (?, ?, ?, ?)");
    $stmt->execute([$data['car_plate'], $data['car_name'], $data['car_model'], 'فعال']);
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
        $stmt = $pdo->query("SELECT * FROM company_cars WHERE status = 'فعال'");
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function getCarAssignments($pdo, $filters = []) {
    try {
        $stmt = $pdo->query("SELECT * FROM car_assignments ORDER BY assignment_date DESC");
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function assignCar($pdo, $data) {
    try {
        $stmt = $pdo->prepare("INSERT INTO car_assignments (car_id, assigned_to, assignment_type, assignment_date, assignment_status, assigned_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$data['car_id'], $data['assigned_to'], $data['assignment_type'], $data['assignment_date'], 'فعال', $_SESSION['user_id']]);
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
        $stmt->execute([$data['car_id'], $data['maintenance_type'], $data['maintenance_date'], $data['description'], 'برنامه‌ریزی شده']);
        return $pdo->lastInsertId();
    } catch (Exception $e) {
        throw $e;
    }
}

function addFuelRecord($pdo, $data) {
    try {
        $stmt = $pdo->prepare("INSERT INTO car_fuel_records (car_id, fuel_date, fuel_type, fuel_amount, fuel_cost) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$data['car_id'], $data['fuel_date'], $data['fuel_type'], $data['fuel_amount'], $data['fuel_cost']]);
        return $pdo->lastInsertId();
    } catch (Exception $e) {
        throw $e;
    }
}

function reportCarIncident($pdo, $data) {
    try {
        $stmt = $pdo->prepare("INSERT INTO car_incidents (car_id, incident_date, incident_type, incident_description, reported_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$data['car_id'], $data['incident_date'], $data['incident_type'], $data['incident_description'], $_SESSION['user_id']]);
        return $pdo->lastInsertId();
    } catch (Exception $e) {
        throw $e;
    }
}

function registerDriver($pdo, $data) {
    try {
        $stmt = $pdo->prepare("INSERT INTO car_drivers (user_id, driver_license_number, license_type, license_expiry_date) VALUES (?, ?, ?, ?)");
        $stmt->execute([$data['user_id'], $data['driver_license_number'], $data['license_type'], $data['license_expiry_date']]);
        return $pdo->lastInsertId();
    } catch (Exception $e) {
        throw $e;
    }
}

function requestCarReservation($pdo, $data) {
    try {
        $stmt = $pdo->prepare("INSERT INTO car_reservations (car_id, requested_by, reservation_date, start_date, end_date, purpose) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$data['car_id'], $data['requested_by'], $data['reservation_date'], $data['start_date'], $data['end_date'], $data['purpose']]);
        return $pdo->lastInsertId();
    } catch (Exception $e) {
        throw $e;
    }
}
?>