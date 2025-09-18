<?php
/**
 * get_user_permissions.php - دریافت دسترسی‌های کاربر
 */

session_start();
require_once 'config.php';

// بررسی دسترسی ادمین
if (!hasPermission('*')) {
    http_response_code(403);
    echo json_encode(['error' => 'دسترسی غیرمجاز']);
    exit();
}

$user_id = (int)($_GET['user_id'] ?? 0);

if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'شناسه کاربر نامعتبر']);
    exit();
}

try {
    // دریافت نقش کاربر
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['permissions' => []]);
        exit();
    }
    
    $role = $user['role'];
    
    // اگر نقش سفارشی است، دسترسی‌های سفارشی را دریافت کن
    if ($role === 'سفارشی') {
        $stmt = $pdo->prepare("SELECT permissions FROM custom_roles WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $custom_role = $stmt->fetch();
        
        if ($custom_role && $custom_role['permissions']) {
            $permissions = json_decode($custom_role['permissions'], true);
            echo json_encode(['permissions' => $permissions ?: []]);
        } else {
            echo json_encode(['permissions' => []]);
        }
    } else {
        // برای نقش‌های استاندارد، دسترسی‌های پیش‌فرض را برگردان
        $default_permissions = [
            'ادمین' => ['*'],
            'مدیر عملیات' => ['operations.*', 'users.view', 'reports.*'],
            'تکنسین' => ['maintenance.*', 'assets.*', 'customers.view'],
            'اپراتور' => ['tickets.*', 'customers.*', 'messages.*'],
            'پشتیبانی' => ['tickets.view', 'tickets.create', 'customers.view', 'messages.*'],
            'کاربر عادی' => ['dashboard.view']
        ];
        
        $permissions = $default_permissions[$role] ?? [];
        echo json_encode(['permissions' => $permissions]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'خطا در دریافت دسترسی‌ها']);
}
?>