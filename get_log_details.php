<?php
/**
 * get_log_details.php - دریافت جزئیات کامل لاگ
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

// بررسی دسترسی ادمین
$rawRole = isset($_SESSION['role']) ? trim((string)$_SESSION['role']) : '';
$is_admin = ($rawRole === 'ادمین' || strcasecmp($rawRole, 'admin') === 0 || strcasecmp($rawRole, 'administrator') === 0);

if (empty($_SESSION['user_id']) || !$is_admin) {
    echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
    exit();
}

// دریافت ID لاگ
$log_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$log_id) {
    echo json_encode(['success' => false, 'message' => 'شناسه لاگ نامعتبر است']);
    exit();
}

try {
    // دریافت جزئیات کامل لاگ
    $stmt = $pdo->prepare("
        SELECT sl.*, u.username, u.full_name, u.role 
        FROM system_logs sl 
        LEFT JOIN users u ON sl.user_id = u.id 
        WHERE sl.id = ?
    ");
    $stmt->execute([$log_id]);
    $log = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$log) {
        echo json_encode(['success' => false, 'message' => 'لاگ مورد نظر یافت نشد']);
        exit();
    }
    
    // فرمت کردن تاریخ
    $log['created_at'] = jalali_format($log['created_at']);
    
    echo json_encode(['success' => true, 'log' => $log]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'خطا در دریافت جزئیات: ' . $e->getMessage()]);
}
?>