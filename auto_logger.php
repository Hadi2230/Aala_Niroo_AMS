<?php
/**
 * Auto Logger - سیستم لاگ‌گیری خودکار و پیشرفته
 * تمام فعالیت‌های سیستم را به صورت خودکار ثبت می‌کند
 */

// ثبت لاگ ورود کاربر
function logUserLogin($pdo, $user_id, $username, $success = true) {
    $severity = $success ? 'info' : 'warning';
    $description = $success ? 
        "ورود موفق کاربر: $username" : 
        "تلاش ورود ناموفق برای: $username";
    
    logAction($pdo, 'USER_LOGIN', $description, $severity, 'AUTH', [
        'username' => $username,
        'success' => $success,
        'login_time' => date('Y-m-d H:i:s')
    ]);
}

// ثبت لاگ خروج کاربر
function logUserLogout($pdo, $user_id, $username) {
    logAction($pdo, 'USER_LOGOUT', "خروج کاربر: $username", 'info', 'AUTH', [
        'username' => $username,
        'logout_time' => date('Y-m-d H:i:s')
    ]);
}

// ثبت لاگ تغییرات دارایی
function logAssetChange($pdo, $action, $asset_id, $asset_name, $details = []) {
    $descriptions = [
        'ADD_ASSET' => "افزودن دارایی جدید: $asset_name",
        'EDIT_ASSET' => "ویرایش دارایی: $asset_name",
        'DELETE_ASSET' => "حذف دارایی: $asset_name",
        'VIEW_ASSET' => "مشاهده دارایی: $asset_name"
    ];
    
    logAction($pdo, $action, $descriptions[$action] ?? $action, 'info', 'ASSETS', [
        'asset_id' => $asset_id,
        'asset_name' => $asset_name,
        'details' => $details
    ]);
}

// ثبت لاگ تغییرات مشتری
function logCustomerChange($pdo, $action, $customer_id, $customer_name, $details = []) {
    $descriptions = [
        'ADD_CUSTOMER' => "افزودن مشتری جدید: $customer_name",
        'EDIT_CUSTOMER' => "ویرایش مشتری: $customer_name",
        'DELETE_CUSTOMER' => "حذف مشتری: $customer_name",
        'VIEW_CUSTOMER' => "مشاهده مشتری: $customer_name"
    ];
    
    logAction($pdo, $action, $descriptions[$action] ?? $action, 'info', 'CUSTOMERS', [
        'customer_id' => $customer_id,
        'customer_name' => $customer_name,
        'details' => $details
    ]);
}

// ثبت لاگ تغییرات انتساب
function logAssignmentChange($pdo, $action, $assignment_id, $details = []) {
    $descriptions = [
        'ADD_ASSIGNMENT' => "افزودن انتساب جدید",
        'EDIT_ASSIGNMENT' => "ویرایش انتساب",
        'DELETE_ASSIGNMENT' => "حذف انتساب",
        'VIEW_ASSIGNMENT' => "مشاهده انتساب"
    ];
    
    logAction($pdo, $action, $descriptions[$action] ?? $action, 'info', 'ASSIGNMENTS', [
        'assignment_id' => $assignment_id,
        'details' => $details
    ]);
}

// ثبت لاگ تغییرات انبار
function logInventoryChange($pdo, $action, $asset_id, $asset_name, $quantity_change, $details = []) {
    $descriptions = [
        'INVENTORY_ADD' => "افزایش موجودی: $asset_name (+$quantity_change)",
        'INVENTORY_REMOVE' => "کاهش موجودی: $asset_name ($quantity_change)",
        'INVENTORY_VIEW' => "مشاهده موجودی: $asset_name"
    ];
    
    logAction($pdo, $action, $descriptions[$action] ?? $action, 'info', 'INVENTORY', [
        'asset_id' => $asset_id,
        'asset_name' => $asset_name,
        'quantity_change' => $quantity_change,
        'details' => $details
    ]);
}

// ثبت لاگ خطاهای سیستم
function logSystemError($pdo, $error_message, $file = null, $line = null, $context = []) {
    $description = "خطای سیستم: $error_message";
    if ($file && $line) {
        $description .= " در فایل $file خط $line";
    }
    
    logAction($pdo, 'SYSTEM_ERROR', $description, 'error', 'SYSTEM', [
        'error_message' => $error_message,
        'file' => $file,
        'line' => $line,
        'context' => $context
    ]);
}

// ثبت لاگ امنیتی
function logSecurityEvent($pdo, $event, $description, $severity = 'warning', $details = []) {
    logAction($pdo, 'SECURITY_' . $event, $description, $severity, 'SECURITY', $details);
}

// ثبت لاگ دسترسی
function logAccessAttempt($pdo, $resource, $success = true, $reason = '') {
    $severity = $success ? 'info' : 'warning';
    $description = $success ? 
        "دسترسی موفق به: $resource" : 
        "دسترسی ناموفق به: $resource - $reason";
    
    logAction($pdo, 'ACCESS_ATTEMPT', $description, $severity, 'ACCESS', [
        'resource' => $resource,
        'success' => $success,
        'reason' => $reason
    ]);
}

// ثبت لاگ فایل
function logFileOperation($pdo, $operation, $file_path, $success = true, $details = []) {
    $severity = $success ? 'info' : 'error';
    $description = $success ? 
        "عملیات موفق فایل: $operation - $file_path" : 
        "عملیات ناموفق فایل: $operation - $file_path";
    
    logAction($pdo, 'FILE_' . $operation, $description, $severity, 'FILES', [
        'file_path' => $file_path,
        'success' => $success,
        'details' => $details
    ]);
}

// ثبت لاگ دیتابیس
function logDatabaseOperation($pdo, $operation, $table, $record_id = null, $details = []) {
    $description = "عملیات دیتابیس: $operation روی جدول $table";
    if ($record_id) {
        $description .= " (ID: $record_id)";
    }
    
    logAction($pdo, 'DB_' . $operation, $description, 'info', 'DATABASE', [
        'table' => $table,
        'record_id' => $record_id,
        'details' => $details
    ]);
}

// ثبت لاگ API
function logApiCall($pdo, $endpoint, $method, $status_code, $response_time = null, $details = []) {
    $severity = $status_code >= 400 ? 'error' : 'info';
    $description = "API Call: $method $endpoint - Status: $status_code";
    
    logAction($pdo, 'API_CALL', $description, $severity, 'API', [
        'endpoint' => $endpoint,
        'method' => $method,
        'status_code' => $status_code,
        'response_time' => $response_time,
        'details' => $details
    ]);
}

// ثبت لاگ عملکرد
function logPerformance($pdo, $operation, $execution_time, $memory_usage, $details = []) {
    $severity = $execution_time > 5 ? 'warning' : 'info';
    $description = "عملکرد: $operation - زمان: {$execution_time}s - حافظه: " . formatBytes($memory_usage);
    
    logAction($pdo, 'PERFORMANCE', $description, $severity, 'PERFORMANCE', [
        'operation' => $operation,
        'execution_time' => $execution_time,
        'memory_usage' => $memory_usage,
        'details' => $details
    ]);
}

// تابع کمکی برای فرمت کردن بایت
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

// ثبت لاگ خودکار برای تمام درخواست‌ها
function autoLogRequest($pdo) {
    $start_time = microtime(true);
    $start_memory = memory_get_usage(true);
    
    // ثبت شروع درخواست
    logAction($pdo, 'REQUEST_START', 'شروع درخواست', 'info', 'SYSTEM', [
        'uri' => $_SERVER['REQUEST_URI'] ?? '',
        'method' => $_SERVER['REQUEST_METHOD'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
    ]);
    
    // ثبت پایان درخواست در shutdown
    register_shutdown_function(function() use ($pdo, $start_time, $start_memory) {
        $end_time = microtime(true);
        $end_memory = memory_get_usage(true);
        $execution_time = $end_time - $start_time;
        $memory_used = $end_memory - $start_memory;
        
        logAction($pdo, 'REQUEST_END', 'پایان درخواست', 'info', 'SYSTEM', [
            'execution_time' => $execution_time,
            'memory_used' => $memory_used,
            'peak_memory' => memory_get_peak_usage(true)
        ]);
    });
}

// فعال‌سازی لاگ‌گیری خودکار
if (isset($pdo)) {
    autoLogRequest($pdo);
}
?>