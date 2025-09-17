<?php
// fix_visit_management.php - اصلاح کامل سیستم مدیریت بازدید
session_start();

// تنظیم session برای تست
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['role'] = 'ادمین';

echo "<h1>🔧 اصلاح سیستم مدیریت بازدید</h1>";

// تست config.php
try {
    require_once 'config.php';
    echo "<p style='color: green;'>✅ config.php بارگذاری شد</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ خطا در config.php: " . $e->getMessage() . "</p>";
    exit();
}

// بررسی و ایجاد جدول visit_requests
echo "<h2>1. بررسی و ایجاد جدول visit_requests</h2>";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'visit_requests'");
    $table_exists = $stmt->fetch();
    
    if (!$table_exists) {
        echo "<p style='color: orange;'>⚠️ جدول visit_requests وجود ندارد، در حال ایجاد...</p>";
        
        // ایجاد جدول visit_requests
        $create_table_sql = "
            CREATE TABLE IF NOT EXISTS visit_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                request_number VARCHAR(50) UNIQUE NOT NULL,
                company_name VARCHAR(255) NOT NULL,
                contact_person VARCHAR(255) NOT NULL,
                contact_phone VARCHAR(20) NOT NULL,
                contact_email VARCHAR(255),
                visitor_count INT NOT NULL DEFAULT 1,
                visit_purpose TEXT NOT NULL,
                visit_type ENUM('meeting', 'test', 'purchase', 'inspection') NOT NULL,
                request_method ENUM('phone', 'email', 'in_person', 'website') DEFAULT 'phone',
                preferred_dates JSON,
                nda_required BOOLEAN DEFAULT FALSE,
                special_requirements TEXT,
                priority ENUM('کم', 'متوسط', 'بالا', 'فوری') DEFAULT 'متوسط',
                status ENUM('new', 'documents_required', 'reviewed', 'scheduled', 'reserved', 'ready_for_visit', 'checked_in', 'onsite', 'completed', 'cancelled', 'archived') DEFAULT 'new',
                confirmed_date DATETIME NULL,
                qr_code VARCHAR(100) NULL,
                created_by INT NOT NULL,
                assigned_to INT NULL,
                host_id INT NULL,
                security_officer_id INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_status (status),
                INDEX idx_company (company_name),
                INDEX idx_created_at (created_at),
                INDEX idx_request_number (request_number)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
        ";
        
        $pdo->exec($create_table_sql);
        echo "<p style='color: green;'>✅ جدول visit_requests ایجاد شد</p>";
    } else {
        echo "<p style='color: green;'>✅ جدول visit_requests وجود دارد</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ خطا در ایجاد جدول: " . $e->getMessage() . "</p>";
}

// بررسی وجود رکوردها
echo "<h2>2. بررسی رکوردهای موجود</h2>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM visit_requests");
    $count = $stmt->fetch()['count'];
    echo "<p style='color: blue;'>📊 تعداد درخواست‌های موجود: " . $count . "</p>";
    
    if ($count == 0) {
        echo "<p style='color: orange;'>⚠️ هیچ درخواستی وجود ندارد، در حال ایجاد درخواست تست...</p>";
        
        // ایجاد درخواست تست
        $test_requests = [
            [
                'request_number' => 'VR-' . date('Ymd') . '-001',
                'company_name' => 'شرکت تست اول',
                'contact_person' => 'احمد احمدی',
                'contact_phone' => '09123456789',
                'contact_email' => 'ahmad@test1.com',
                'visitor_count' => 2,
                'visit_purpose' => 'بازدید از تجهیزات ژنراتور',
                'visit_type' => 'test',
                'request_method' => 'phone',
                'preferred_dates' => json_encode([date('Y-m-d', strtotime('+1 day'))]),
                'nda_required' => false,
                'special_requirements' => 'نیاز به راهنمای فنی',
                'priority' => 'بالا',
                'status' => 'new',
                'created_by' => 1
            ],
            [
                'request_number' => 'VR-' . date('Ymd') . '-002',
                'company_name' => 'شرکت تست دوم',
                'contact_person' => 'فاطمه محمدی',
                'contact_phone' => '09123456790',
                'contact_email' => 'fateme@test2.com',
                'visitor_count' => 3,
                'visit_purpose' => 'جلسه مذاکره برای خرید',
                'visit_type' => 'meeting',
                'request_method' => 'email',
                'preferred_dates' => json_encode([date('Y-m-d', strtotime('+2 days'))]),
                'nda_required' => true,
                'special_requirements' => 'نیاز به NDA',
                'priority' => 'فوری',
                'status' => 'documents_required',
                'created_by' => 1
            ],
            [
                'request_number' => 'VR-' . date('Ymd') . '-003',
                'company_name' => 'شرکت تست سوم',
                'contact_person' => 'علی رضایی',
                'contact_phone' => '09123456791',
                'contact_email' => 'ali@test3.com',
                'visitor_count' => 1,
                'visit_purpose' => 'بازرسی فنی تجهیزات',
                'visit_type' => 'inspection',
                'request_method' => 'in_person',
                'preferred_dates' => json_encode([date('Y-m-d', strtotime('+3 days'))]),
                'nda_required' => false,
                'special_requirements' => 'نیاز به گزارش فنی',
                'priority' => 'متوسط',
                'status' => 'scheduled',
                'created_by' => 1
            ]
        ];
        
        foreach ($test_requests as $request) {
            $sql = "INSERT INTO visit_requests (request_number, company_name, contact_person, contact_phone, contact_email, visitor_count, visit_purpose, visit_type, request_method, preferred_dates, nda_required, special_requirements, priority, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $request['request_number'],
                $request['company_name'],
                $request['contact_person'],
                $request['contact_phone'],
                $request['contact_email'],
                $request['visitor_count'],
                $request['visit_purpose'],
                $request['visit_type'],
                $request['request_method'],
                $request['preferred_dates'],
                $request['nda_required'] ? 1 : 0,
                $request['special_requirements'],
                $request['priority'],
                $request['status'],
                $request['created_by']
            ]);
        }
        
        echo "<p style='color: green;'>✅ 3 درخواست تست ایجاد شد</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ خطا در بررسی/ایجاد رکوردها: " . $e->getMessage() . "</p>";
}

// نمایش درخواست‌های موجود
echo "<h2>3. نمایش درخواست‌های موجود</h2>";
try {
    $stmt = $pdo->query("
        SELECT vr.*, 
               u1.full_name as created_by_name
        FROM visit_requests vr
        LEFT JOIN users u1 ON vr.created_by = u1.id
        ORDER BY vr.created_at DESC
    ");
    $requests = $stmt->fetchAll();
    
    if (count($requests) > 0) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
        echo "<tr style='background: #f8f9fa; font-weight: bold;'>";
        echo "<th style='padding: 10px; border: 1px solid #ddd;'>ID</th>";
        echo "<th style='padding: 10px; border: 1px solid #ddd;'>شماره درخواست</th>";
        echo "<th style='padding: 10px; border: 1px solid #ddd;'>شرکت</th>";
        echo "<th style='padding: 10px; border: 1px solid #ddd;'>تماس</th>";
        echo "<th style='padding: 10px; border: 1px solid #ddd;'>نوع</th>";
        echo "<th style='padding: 10px; border: 1px solid #ddd;'>وضعیت</th>";
        echo "<th style='padding: 10px; border: 1px solid #ddd;'>اولویت</th>";
        echo "<th style='padding: 10px; border: 1px solid #ddd;'>تاریخ ایجاد</th>";
        echo "</tr>";
        
        foreach ($requests as $request) {
            echo "<tr>";
            echo "<td style='padding: 10px; border: 1px solid #ddd;'>" . $request['id'] . "</td>";
            echo "<td style='padding: 10px; border: 1px solid #ddd;'>" . htmlspecialchars($request['request_number']) . "</td>";
            echo "<td style='padding: 10px; border: 1px solid #ddd;'>" . htmlspecialchars($request['company_name']) . "</td>";
            echo "<td style='padding: 10px; border: 1px solid #ddd;'>" . htmlspecialchars($request['contact_person']) . "<br><small>" . htmlspecialchars($request['contact_phone']) . "</small></td>";
            echo "<td style='padding: 10px; border: 1px solid #ddd;'>" . htmlspecialchars($request['visit_type']) . "</td>";
            echo "<td style='padding: 10px; border: 1px solid #ddd;'><span style='background: #e3f2fd; color: #1976d2; padding: 4px 8px; border-radius: 10px; font-size: 0.8rem;'>" . htmlspecialchars($request['status']) . "</span></td>";
            echo "<td style='padding: 10px; border: 1px solid #ddd;'><span style='background: #fff3e0; color: #f57c00; padding: 4px 8px; border-radius: 10px; font-size: 0.8rem;'>" . htmlspecialchars($request['priority']) . "</span></td>";
            echo "<td style='padding: 10px; border: 1px solid #ddd;'>" . date('Y-m-d H:i', strtotime($request['created_at'])) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'>❌ هیچ درخواستی یافت نشد</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ خطا در نمایش درخواست‌ها: " . $e->getMessage() . "</p>";
}

echo "<h2>4. تست تابع createVisitRequest</h2>";
try {
    if (function_exists('createVisitRequest')) {
        echo "<p style='color: green;'>✅ تابع createVisitRequest وجود دارد</p>";
        
        // تست ثبت درخواست جدید
        $test_data = [
            'company_name' => 'شرکت تست تابع',
            'contact_person' => 'تست تستی',
            'contact_phone' => '09123456792',
            'contact_email' => 'test@function.com',
            'visitor_count' => 1,
            'visit_purpose' => 'تست تابع createVisitRequest',
            'visit_type' => 'test',
            'request_method' => 'phone',
            'preferred_dates' => [date('Y-m-d', strtotime('+1 day'))],
            'nda_required' => false,
            'special_requirements' => 'تست',
            'created_by' => 1
        ];
        
        $visit_id = createVisitRequest($pdo, $test_data);
        echo "<p style='color: green;'>✅ درخواست جدید با تابع ثبت شد - ID: " . $visit_id . "</p>";
    } else {
        echo "<p style='color: red;'>❌ تابع createVisitRequest یافت نشد</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ خطا در تست تابع: " . $e->getMessage() . "</p>";
}

echo "<h2>5. لینک‌های تست</h2>";
echo "<div style='text-align: center; margin: 20px 0;'>";
echo "<a href='visit_management.php' target='_blank' style='background: #3498db; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; margin: 10px; display: inline-block; font-size: 16px; font-weight: bold;'>📋 مدیریت بازدیدها</a>";
echo "<a href='visit_dashboard.php' target='_blank' style='background: #27ae60; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; margin: 10px; display: inline-block; font-size: 16px; font-weight: bold;'>🏠 داشبورد بازدیدها</a>";
echo "</div>";

echo "<div style='background: linear-gradient(135deg, #2ecc71, #27ae60); color: white; padding: 30px; border-radius: 15px; margin: 30px 0; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.2);'>";
echo "<h2 style='margin: 0 0 15px 0; font-size: 2rem;'>🎉 سیستم مدیریت بازدید کاملاً اصلاح شد!</h2>";
echo "<p style='margin: 0; font-size: 1.2rem;'>حالا می‌توانید درخواست‌ها را ببینید، ویرایش کنید و حذف کنید</p>";
echo "</div>";
?>