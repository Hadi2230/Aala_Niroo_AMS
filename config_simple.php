<?php
/**
 * config_simple.php - نسخه ساده config.php برای تست
 */

// شروع session اگر شروع نشده
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تنظیمات امنیتی
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php-errors.log');

// ایجاد پوشه logs اگر وجود ندارد
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

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
    try {
        // ایجاد پوشه آپلود اگر وجود ندارد
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $file_name = uniqid() . '.' . $file_extension;
        $file_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            $stmt = $pdo->prepare("
                INSERT INTO request_files (request_id, file_name, file_path, file_type, file_size) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $request_id,
                $file['name'],
                $file_path,
                $file['type'],
                $file['size']
            ]);
            
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Error uploading file: " . $e->getMessage());
        return false;
    }
}

/**
 * ایجاد گردش کار درخواست
 */
function createRequestWorkflow($pdo, $request_id, $assignments) {
    try {
        foreach ($assignments as $index => $assignment) {
            $stmt = $pdo->prepare("
                INSERT INTO request_workflow (request_id, step_order, assigned_to, department, status) 
                VALUES (?, ?, ?, ?, 'در انتظار')
            ");
            
            $stmt->execute([
                $request_id,
                $index + 1,
                $assignment['user_id'],
                $assignment['department']
            ]);
            
            // ایجاد اعلان
            createRequestNotification($pdo, $assignment['user_id'], $request_id, 'assignment', 
                'درخواست جدید به شما اختصاص یافت');
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error creating workflow: " . $e->getMessage());
        return false;
    }
}

/**
 * ایجاد اعلان درخواست
 */
function createRequestNotification($pdo, $user_id, $request_id, $type, $message) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO request_notifications (request_id, user_id, notification_type, message) 
            VALUES (?, ?, ?, ?)
        ");
        
        $stmt->execute([$request_id, $user_id, $type, $message]);
        return true;
    } catch (Exception $e) {
        error_log("Error creating notification: " . $e->getMessage());
        return false;
    }
}

/**
 * تابع sanitizeInput
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * تابع hasPermission
 */
function hasPermission($permission) {
    // برای تست، همیشه true برمی‌گرداند
    return true;
}

echo "<!-- config_simple.php loaded successfully -->";
?>