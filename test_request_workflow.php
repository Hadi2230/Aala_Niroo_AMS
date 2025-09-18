<?php
/**
 * test_request_workflow.php - تست سیستم گردش کار درخواست‌ها
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // برای تست
    $_SESSION['username'] = 'admin';
}

require_once 'config_simple.php';

echo "<h2>تست سیستم گردش کار درخواست‌ها</h2>";

// 1. بررسی وجود جداول
echo "<h3>1. بررسی جداول:</h3>";
$tables = ['requests', 'request_files', 'request_workflow', 'request_notifications'];
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
        $count = $stmt->fetchColumn();
        echo "✅ جدول $table: $count رکورد<br>";
    } catch (Exception $e) {
        echo "❌ جدول $table: " . $e->getMessage() . "<br>";
    }
}

// 2. بررسی درخواست‌های موجود
echo "<h3>2. درخواست‌های موجود:</h3>";
try {
    $stmt = $pdo->query("
        SELECT r.*, 
               COUNT(DISTINCT rf.id) as file_count,
               COUNT(DISTINCT rw.id) as workflow_count,
               GROUP_CONCAT(DISTINCT rw.assigned_to) as assigned_users
        FROM requests r
        LEFT JOIN request_files rf ON r.id = rf.request_id
        LEFT JOIN request_workflow rw ON r.id = rw.request_id
        GROUP BY r.id
        ORDER BY r.created_at DESC
    ");
    $requests = $stmt->fetchAll();
    
    if (empty($requests)) {
        echo "❌ هیچ درخواستی یافت نشد<br>";
    } else {
        foreach ($requests as $request) {
            echo "📋 درخواست #{$request['id']}: {$request['request_number']} - {$request['item_name']}<br>";
            echo "&nbsp;&nbsp;&nbsp;وضعیت: {$request['status']} | اولویت: {$request['priority']}<br>";
            echo "&nbsp;&nbsp;&nbsp;ارجاع شده به: {$request['assigned_users']}<br>";
            echo "&nbsp;&nbsp;&nbsp;فایل‌ها: {$request['file_count']} | گردش کار: {$request['workflow_count']}<br><br>";
        }
    }
} catch (Exception $e) {
    echo "❌ خطا در دریافت درخواست‌ها: " . $e->getMessage() . "<br>";
}

// 3. بررسی گردش کار
echo "<h3>3. گردش کار درخواست‌ها:</h3>";
try {
    $stmt = $pdo->query("
        SELECT rw.*, r.request_number, u.username, u.full_name
        FROM request_workflow rw
        LEFT JOIN requests r ON rw.request_id = r.id
        LEFT JOIN users u ON rw.assigned_to = u.id
        ORDER BY rw.request_id, rw.step_order
    ");
    $workflows = $stmt->fetchAll();
    
    if (empty($workflows)) {
        echo "❌ هیچ گردش کاری یافت نشد<br>";
    } else {
        foreach ($workflows as $workflow) {
            echo "🔄 درخواست {$workflow['request_number']}: مرحله {$workflow['step_order']}<br>";
            echo "&nbsp;&nbsp;&nbsp;ارجاع شده به: {$workflow['full_name']} ({$workflow['username']})<br>";
            echo "&nbsp;&nbsp;&nbsp;واحد: {$workflow['department']} | وضعیت: {$workflow['status']}<br>";
            if ($workflow['comments']) {
                echo "&nbsp;&nbsp;&nbsp;توضیحات: {$workflow['comments']}<br>";
            }
            echo "<br>";
        }
    }
} catch (Exception $e) {
    echo "❌ خطا در دریافت گردش کار: " . $e->getMessage() . "<br>";
}

// 4. بررسی کاربران
echo "<h3>4. کاربران موجود:</h3>";
try {
    $stmt = $pdo->query("SELECT id, username, full_name, role, is_active FROM users ORDER BY id");
    $users = $stmt->fetchAll();
    
    if (empty($users)) {
        echo "❌ هیچ کاربری یافت نشد<br>";
    } else {
        foreach ($users as $user) {
            $status = $user['is_active'] ? '✅ فعال' : '❌ غیرفعال';
            echo "👤 {$user['full_name']} ({$user['username']}) - {$user['role']} - $status<br>";
        }
    }
} catch (Exception $e) {
    echo "❌ خطا در دریافت کاربران: " . $e->getMessage() . "<br>";
}

// 5. تست ایجاد درخواست نمونه
echo "<h3>5. تست ایجاد درخواست نمونه:</h3>";
try {
    // ایجاد درخواست تست
    $test_data = [
        'requester_id' => $_SESSION['user_id'],
        'requester_name' => $_SESSION['username'],
        'item_name' => 'تست سیستم گردش کار',
        'quantity' => 1,
        'price' => 100000,
        'description' => 'این یک درخواست تست برای بررسی سیستم گردش کار است',
        'priority' => 'بالا'
    ];
    
    $request_id = createRequest($pdo, $test_data);
    if ($request_id) {
        echo "✅ درخواست تست با موفقیت ایجاد شد (ID: $request_id)<br>";
        
        // ایجاد گردش کار
        $assignments = [
            ['user_id' => 1, 'department' => 'مدیریت'],
            ['user_id' => 2, 'department' => 'فنی']
        ];
        
        if (createRequestWorkflow($pdo, $request_id, $assignments)) {
            echo "✅ گردش کار با موفقیت ایجاد شد<br>";
        } else {
            echo "❌ خطا در ایجاد گردش کار<br>";
        }
    } else {
        echo "❌ خطا در ایجاد درخواست تست<br>";
    }
} catch (Exception $e) {
    echo "❌ خطا در تست: " . $e->getMessage() . "<br>";
}

echo "<br><a href='request_tracking_final.php'>برو به صفحه پیگیری درخواست‌ها</a>";
?>