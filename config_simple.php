<?php
// نسخه ساده config.php برای تست

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
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function logAction($pdo, $action, $description = '') {
    // ساده شده - فقط لاگ می‌کنه
    error_log("Action: $action - $description");
}

function verifyCsrfToken($token = null) {
    $token = $token ?? ($_POST['csrf_token'] ?? '');
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        die('CSRF Token validation failed');
    }
}

function jalali_format($datetime, $format = 'Y/m/d H:i') {
    // ساده شده - فقط تاریخ میلادی برمی‌گردونه
    return date('Y/m/d H:i', strtotime($datetime));
}

// توابع مدیریت خودروها
function addCompanyCar($pdo, $data) {
    $stmt = $pdo->prepare("INSERT INTO company_cars (car_plate, car_name, car_model, status) VALUES (?, ?, ?, ?)");
    $stmt->execute([$data['car_plate'], $data['car_name'], $data['car_model'], 'فعال']);
    return $pdo->lastInsertId();
}

function getCarStatistics($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM company_cars");
    return ['total_cars' => $stmt->fetch()['total'] ?? 0];
}

function getAvailableCars($pdo, $start_date, $end_date) {
    $stmt = $pdo->query("SELECT * FROM company_cars WHERE status = 'فعال'");
    return $stmt->fetchAll();
}

function getCarAssignments($pdo, $filters = []) {
    $stmt = $pdo->query("SELECT * FROM car_assignments ORDER BY assignment_date DESC");
    return $stmt->fetchAll();
}
?>