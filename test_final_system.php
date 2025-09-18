<?php
/**
 * test_final_system.php - تست کامل سیستم درخواست‌ها
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'admin';
}

require_once 'config_complete.php';

echo "<!DOCTYPE html>
<html dir='rtl' lang='fa'>
<head>
    <meta charset='UTF-8'>
    <title>تست کامل سیستم درخواست‌ها</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        body { font-family: 'Tahoma', sans-serif; }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
    </style>
</head>
<body>
<div class='container mt-4'>";

echo "<h1 class='text-center mb-4'>تست کامل سیستم درخواست‌ها</h1>";

// 1. تست اتصال دیتابیس
echo "<div class='test-section'>
    <h3>1. تست اتصال دیتابیس</h3>";
try {
    $stmt = $pdo->query("SELECT 1");
    echo "<p class='success'>✅ اتصال به دیتابیس موفق</p>";
} catch (Exception $e) {
    echo "<p class='error'>❌ خطا در اتصال: " . $e->getMessage() . "</p>";
}
echo "</div>";

// 2. تست ایجاد جداول
echo "<div class='test-section'>
    <h3>2. تست ایجاد جداول</h3>";
$tables = ['users', 'requests', 'request_files', 'request_workflow', 'request_notifications'];
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
        $count = $stmt->fetchColumn();
        echo "<p class='success'>✅ جدول $table: $count رکورد</p>";
    } catch (Exception $e) {
        echo "<p class='error'>❌ جدول $table: " . $e->getMessage() . "</p>";
    }
}
echo "</div>";

// 3. تست ایجاد کاربران
echo "<div class='test-section'>
    <h3>3. تست ایجاد کاربران</h3>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $user_count = $stmt->fetchColumn();
    
    if ($user_count == 0) {
        $test_users = [
            ['username' => 'admin', 'password' => password_hash('admin123', PASSWORD_DEFAULT), 'full_name' => 'مدیر سیستم', 'email' => 'admin@example.com', 'role' => 'admin'],
            ['username' => 'user1', 'password' => password_hash('user123', PASSWORD_DEFAULT), 'full_name' => 'کاربر اول', 'email' => 'user1@example.com', 'role' => 'user'],
            ['username' => 'user2', 'password' => password_hash('user123', PASSWORD_DEFAULT), 'full_name' => 'کاربر دوم', 'email' => 'user2@example.com', 'role' => 'user']
        ];
        
        foreach ($test_users as $user) {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, role, is_active) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->execute([$user['username'], $user['password'], $user['full_name'], $user['email'], $user['role']]);
        }
        echo "<p class='success'>✅ کاربران نمونه ایجاد شدند</p>";
    } else {
        echo "<p class='warning'>⚠️ $user_count کاربر موجود است</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ خطا در ایجاد کاربران: " . $e->getMessage() . "</p>";
}
echo "</div>";

// 4. تست ایجاد درخواست
echo "<div class='test-section'>
    <h3>4. تست ایجاد درخواست</h3>";
try {
    $test_data = [
        'requester_id' => 1,
        'requester_name' => 'admin',
        'item_name' => 'تست سیستم نهایی',
        'quantity' => 1,
        'price' => 50000,
        'description' => 'این یک درخواست تست برای بررسی سیستم نهایی است',
        'priority' => 'بالا'
    ];
    
    $request_id = createRequest($pdo, $test_data);
    if ($request_id) {
        echo "<p class='success'>✅ درخواست تست ایجاد شد (ID: $request_id)</p>";
        
        // ایجاد گردش کار
        $assignments = [
            ['user_id' => 2, 'department' => 'فنی'],
            ['user_id' => 3, 'department' => 'مدیریت']
        ];
        
        if (createRequestWorkflow($pdo, $request_id, $assignments)) {
            echo "<p class='success'>✅ گردش کار ایجاد شد</p>";
        } else {
            echo "<p class='error'>❌ خطا در ایجاد گردش کار</p>";
        }
    } else {
        echo "<p class='error'>❌ خطا در ایجاد درخواست تست</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ خطا در تست درخواست: " . $e->getMessage() . "</p>";
}
echo "</div>";

// 5. تست تابع is_admin
echo "<div class='test-section'>
    <h3>5. تست تابع is_admin</h3>";
try {
    $is_admin = is_admin(1);
    echo "<p class='success'>✅ تابع is_admin کار می‌کند: " . ($is_admin ? 'کاربر 1 ادمین است' : 'کاربر 1 ادمین نیست') . "</p>";
} catch (Exception $e) {
    echo "<p class='error'>❌ خطا در تست is_admin: " . $e->getMessage() . "</p>";
}
echo "</div>";

// 6. تست نمایش درخواست‌ها
echo "<div class='test-section'>
    <h3>6. تست نمایش درخواست‌ها</h3>";
try {
    $stmt = $pdo->prepare("
        SELECT r.*, 
               COUNT(DISTINCT rf.id) as file_count,
               COUNT(DISTINCT rw.id) as workflow_count,
               GROUP_CONCAT(DISTINCT rw.assigned_to) as assigned_users
        FROM requests r
        LEFT JOIN request_files rf ON r.id = rf.request_id
        LEFT JOIN request_workflow rw ON r.id = rw.request_id
        WHERE r.requester_id = ? OR EXISTS (
            SELECT 1 FROM request_workflow rw2 
            WHERE rw2.request_id = r.id AND rw2.assigned_to = ?
        )
        GROUP BY r.id
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([1, 1]); // کاربر 1
    $requests = $stmt->fetchAll();
    
    if (empty($requests)) {
        echo "<p class='warning'>⚠️ هیچ درخواستی یافت نشد</p>";
    } else {
        echo "<p class='success'>✅ " . count($requests) . " درخواست یافت شد</p>";
        foreach ($requests as $request) {
            echo "<div class='alert alert-info'>
                <strong>درخواست #{$request['id']}:</strong> {$request['request_number']}<br>
                <strong>آیتم:</strong> {$request['item_name']}<br>
                <strong>وضعیت:</strong> {$request['status']}<br>
                <strong>ارجاع شده به:</strong> {$request['assigned_users']}<br>
            </div>";
        }
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ خطا در نمایش درخواست‌ها: " . $e->getMessage() . "</p>";
}
echo "</div>";

// 7. لینک‌های تست
echo "<div class='test-section'>
    <h3>7. لینک‌های تست</h3>
    <div class='d-grid gap-2 d-md-block'>
        <a href='request_management_final.php' class='btn btn-primary'>صفحه ایجاد درخواست</a>
        <a href='request_workflow_professional.php' class='btn btn-success'>سیستم حرفه‌ای</a>
        <a href='request_tracking_final.php' class='btn btn-info'>پیگیری درخواست‌ها</a>
        <a href='request_reports.php' class='btn btn-warning'>گزارش‌های درخواست‌ها</a>
    </div>
</div>";

echo "</div></body></html>";
?>