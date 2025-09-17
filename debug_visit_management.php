<?php
// debug_visit_management.php - دیباگ سیستم مدیریت بازدید
session_start();

// تنظیم session برای تست
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['role'] = 'ادمین';

echo "<h1>🔍 دیباگ سیستم مدیریت بازدید</h1>";

// تست config.php
try {
    require_once 'config.php';
    echo "<p style='color: green;'>✅ config.php بارگذاری شد</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ خطا در config.php: " . $e->getMessage() . "</p>";
    exit();
}

// تست اتصال دیتابیس
try {
    $stmt = $pdo->query("SELECT 1");
    echo "<p style='color: green;'>✅ اتصال دیتابیس موفق</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ خطا در اتصال دیتابیس: " . $e->getMessage() . "</p>";
}

// بررسی وجود جدول visit_requests
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'visit_requests'");
    $table_exists = $stmt->fetch();
    if ($table_exists) {
        echo "<p style='color: green;'>✅ جدول visit_requests وجود دارد</p>";
        
        // شمارش رکوردها
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM visit_requests");
        $count = $stmt->fetch()['count'];
        echo "<p style='color: blue;'>📊 تعداد درخواست‌ها: " . $count . "</p>";
        
        // نمایش آخرین 5 درخواست
        $stmt = $pdo->query("SELECT id, request_number, company_name, status, created_at FROM visit_requests ORDER BY created_at DESC LIMIT 5");
        $recent_requests = $stmt->fetchAll();
        
        echo "<h3>آخرین 5 درخواست:</h3>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>شماره درخواست</th><th>شرکت</th><th>وضعیت</th><th>تاریخ ایجاد</th></tr>";
        foreach ($recent_requests as $request) {
            echo "<tr>";
            echo "<td>" . $request['id'] . "</td>";
            echo "<td>" . htmlspecialchars($request['request_number']) . "</td>";
            echo "<td>" . htmlspecialchars($request['company_name']) . "</td>";
            echo "<td>" . htmlspecialchars($request['status']) . "</td>";
            echo "<td>" . $request['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
    } else {
        echo "<p style='color: red;'>❌ جدول visit_requests وجود ندارد</p>";
        
        // ایجاد جدول
        echo "<p style='color: orange;'>🔄 در حال ایجاد جدول...</p>";
        try {
            createDatabaseTables($pdo);
            echo "<p style='color: green;'>✅ جدول ایجاد شد</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ خطا در ایجاد جدول: " . $e->getMessage() . "</p>";
        }
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ خطا در بررسی جدول: " . $e->getMessage() . "</p>";
}

// تست ثبت درخواست جدید
echo "<h3>تست ثبت درخواست جدید:</h3>";
try {
    $visit_data = [
        'company_name' => 'شرکت تست دیباگ',
        'contact_person' => 'تست تستی',
        'contact_phone' => '09123456789',
        'contact_email' => 'test@debug.com',
        'visitor_count' => 1,
        'visit_purpose' => 'تست دیباگ سیستم',
        'visit_type' => 'test',
        'request_method' => 'phone',
        'preferred_dates' => [date('Y-m-d', strtotime('+1 day'))],
        'nda_required' => false,
        'special_requirements' => 'تست',
        'created_by' => 1
    ];
    
    if (function_exists('createVisitRequest')) {
        $visit_id = createVisitRequest($pdo, $visit_data);
        echo "<p style='color: green;'>✅ درخواست جدید ثبت شد - ID: " . $visit_id . "</p>";
    } else {
        echo "<p style='color: red;'>❌ تابع createVisitRequest یافت نشد</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ خطا در ثبت درخواست: " . $e->getMessage() . "</p>";
}

// تست دریافت درخواست‌ها
echo "<h3>تست دریافت درخواست‌ها:</h3>";
try {
    $stmt = $pdo->query("
        SELECT vr.*, 
               u1.full_name as created_by_name
        FROM visit_requests vr
        LEFT JOIN users u1 ON vr.created_by = u1.id
        ORDER BY vr.created_at DESC
        LIMIT 10
    ");
    $requests = $stmt->fetchAll();
    
    echo "<p style='color: green;'>✅ دریافت " . count($requests) . " درخواست موفق</p>";
    
    if (count($requests) > 0) {
        echo "<h4>درخواست‌های موجود:</h4>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>شماره</th><th>شرکت</th><th>تماس</th><th>نوع</th><th>وضعیت</th><th>تاریخ</th></tr>";
        foreach ($requests as $request) {
            echo "<tr>";
            echo "<td>" . $request['id'] . "</td>";
            echo "<td>" . htmlspecialchars($request['request_number']) . "</td>";
            echo "<td>" . htmlspecialchars($request['company_name']) . "</td>";
            echo "<td>" . htmlspecialchars($request['contact_person']) . "</td>";
            echo "<td>" . htmlspecialchars($request['visit_type']) . "</td>";
            echo "<td>" . htmlspecialchars($request['status']) . "</td>";
            echo "<td>" . $request['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ خطا در دریافت درخواست‌ها: " . $e->getMessage() . "</p>";
}

echo "<h3>🔗 لینک‌های تست:</h3>";
echo "<p><a href='visit_management.php' target='_blank' style='background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px; display: inline-block;'>📋 مدیریت بازدیدها</a></p>";
echo "<p><a href='visit_dashboard.php' target='_blank' style='background: #27ae60; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px; display: inline-block;'>🏠 داشبورد بازدیدها</a></p>";
?>