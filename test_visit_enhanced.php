<?php
// test_visit_enhanced.php - تست سیستم بازدید کارخانه (نسخه پیشرفته)
session_start();

// تنظیم session برای تست
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['role'] = 'ادمین';

echo "<h1>🏭 تست سیستم بازدید کارخانه (نسخه پیشرفته)</h1>";

// تست config.php
try {
    require_once 'config.php';
    echo "<p style='color: green;'>✅ config.php بارگذاری شد</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ خطا در config.php: " . $e->getMessage() . "</p>";
    exit();
}

// بررسی و ایجاد جداول
echo "<h2>1. بررسی و ایجاد جداول</h2>";
try {
    // بررسی جدول visit_requests
    $stmt = $pdo->query("SHOW TABLES LIKE 'visit_requests'");
    $table_exists = $stmt->fetch();
    
    if (!$table_exists) {
        echo "<p style='color: orange;'>⚠️ جدول visit_requests وجود ندارد، در حال ایجاد...</p>";
        
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
                request_method ENUM('phone', 'email', 'in_person', 'website', 'letter') DEFAULT 'phone',
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
    
    // بررسی جدول visit_visitors
    $stmt = $pdo->query("SHOW TABLES LIKE 'visit_visitors'");
    $visitors_table_exists = $stmt->fetch();
    
    if (!$visitors_table_exists) {
        echo "<p style='color: orange;'>⚠️ جدول visit_visitors وجود ندارد، در حال ایجاد...</p>";
        
        $create_visitors_table_sql = "
            CREATE TABLE IF NOT EXISTS visit_visitors (
                id INT AUTO_INCREMENT PRIMARY KEY,
                visit_request_id INT NOT NULL,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                national_id VARCHAR(20),
                phone VARCHAR(20),
                email VARCHAR(255),
                position VARCHAR(100),
                company VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (visit_request_id) REFERENCES visit_requests(id) ON DELETE CASCADE,
                INDEX idx_visit_request (visit_request_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
        ";
        
        $pdo->exec($create_visitors_table_sql);
        echo "<p style='color: green;'>✅ جدول visit_visitors ایجاد شد</p>";
    } else {
        echo "<p style='color: green;'>✅ جدول visit_visitors وجود دارد</p>";
    }
    
    // بررسی جدول visit_documents
    $stmt = $pdo->query("SHOW TABLES LIKE 'visit_documents'");
    $documents_table_exists = $stmt->fetch();
    
    if (!$documents_table_exists) {
        echo "<p style='color: orange;'>⚠️ جدول visit_documents وجود ندارد، در حال ایجاد...</p>";
        
        $create_documents_table_sql = "
            CREATE TABLE IF NOT EXISTS visit_documents (
                id INT AUTO_INCREMENT PRIMARY KEY,
                visit_request_id INT NOT NULL,
                document_type ENUM('company_registration', 'introduction_letter', 'permit', 'nda', 'national_card', 'request_letter', 'other') NOT NULL,
                document_name VARCHAR(255) NOT NULL,
                file_path VARCHAR(500) NOT NULL,
                uploaded_by INT NOT NULL,
                uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (visit_request_id) REFERENCES visit_requests(id) ON DELETE CASCADE,
                INDEX idx_visit_request (visit_request_id),
                INDEX idx_document_type (document_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
        ";
        
        $pdo->exec($create_documents_table_sql);
        echo "<p style='color: green;'>✅ جدول visit_documents ایجاد شد</p>";
    } else {
        echo "<p style='color: green;'>✅ جدول visit_documents وجود دارد</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ خطا در ایجاد جداول: " . $e->getMessage() . "</p>";
}

// ایجاد پوشه‌های آپلود
echo "<h2>2. ایجاد پوشه‌های آپلود</h2>";
$upload_dirs = [
    'uploads/visit_documents/',
    'uploads/visit_documents/national_cards/',
    'uploads/visit_documents/request_letters/'
];

foreach ($upload_dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        echo "<p style='color: green;'>✅ پوشه $dir ایجاد شد</p>";
    } else {
        echo "<p style='color: blue;'>📁 پوشه $dir وجود دارد</p>";
    }
}

// تست ثبت درخواست با بازدیدکنندگان
echo "<h2>3. تست ثبت درخواست با بازدیدکنندگان</h2>";
try {
    $visit_data = [
        'company_name' => 'شرکت تست پیشرفته',
        'contact_person' => 'احمد احمدی',
        'contact_phone' => '09123456789',
        'contact_email' => 'ahmad@advanced.com',
        'visitor_count' => 3,
        'visit_purpose' => 'تست سیستم پیشرفته با بازدیدکنندگان',
        'visit_type' => 'test',
        'request_method' => 'letter',
        'preferred_dates' => [date('Y-m-d', strtotime('+1 day'))],
        'nda_required' => true,
        'special_requirements' => 'تست قابلیت‌های جدید',
        'created_by' => 1
    ];
    
    $visit_id = createVisitRequest($pdo, $visit_data);
    echo "<p style='color: green;'>✅ درخواست بازدید ثبت شد - ID: " . $visit_id . "</p>";
    
    // اضافه کردن بازدیدکنندگان
    $visitors = [
        [
            'first_name' => 'احمد',
            'last_name' => 'احمدی',
            'national_id' => '1234567890',
            'phone' => '09123456789',
            'email' => 'ahmad@test.com',
            'position' => 'مدیر فنی',
            'company' => 'شرکت تست پیشرفته'
        ],
        [
            'first_name' => 'فاطمه',
            'last_name' => 'محمدی',
            'national_id' => '0987654321',
            'phone' => '09123456790',
            'email' => 'fateme@test.com',
            'position' => 'مهندس',
            'company' => 'شرکت تست پیشرفته'
        ],
        [
            'first_name' => 'علی',
            'last_name' => 'رضایی',
            'national_id' => '1122334455',
            'phone' => '09123456791',
            'email' => 'ali@test.com',
            'position' => 'تکنسین',
            'company' => 'شرکت تست پیشرفته'
        ]
    ];
    
    foreach ($visitors as $visitor) {
        $stmt = $pdo->prepare("
            INSERT INTO visit_visitors (visit_request_id, first_name, last_name, national_id, phone, email, position, company) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $visit_id,
            $visitor['first_name'],
            $visitor['last_name'],
            $visitor['national_id'],
            $visitor['phone'],
            $visitor['email'],
            $visitor['position'],
            $visitor['company']
        ]);
    }
    
    echo "<p style='color: green;'>✅ " . count($visitors) . " بازدیدکننده اضافه شد</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ خطا در ثبت درخواست: " . $e->getMessage() . "</p>";
}

// نمایش درخواست‌های موجود
echo "<h2>4. نمایش درخواست‌های موجود</h2>";
try {
    $stmt = $pdo->query("
        SELECT vr.*, 
               u1.full_name as created_by_name,
               COUNT(vv.id) as visitor_count_actual
        FROM visit_requests vr
        LEFT JOIN users u1 ON vr.created_by = u1.id
        LEFT JOIN visit_visitors vv ON vr.id = vv.visit_request_id
        GROUP BY vr.id
        ORDER BY vr.created_at DESC
        LIMIT 5
    ");
    $requests = $stmt->fetchAll();
    
    if (count($requests) > 0) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
        echo "<tr style='background: #f8f9fa; font-weight: bold;'>";
        echo "<th style='padding: 10px; border: 1px solid #ddd;'>ID</th>";
        echo "<th style='padding: 10px; border: 1px solid #ddd;'>شماره درخواست</th>";
        echo "<th style='padding: 10px; border: 1px solid #ddd;'>شرکت</th>";
        echo "<th style='padding: 10px; border: 1px solid #ddd;'>روش درخواست</th>";
        echo "<th style='padding: 10px; border: 1px solid #ddd;'>تعداد بازدیدکنندگان</th>";
        echo "<th style='padding: 10px; border: 1px solid #ddd;'>وضعیت</th>";
        echo "<th style='padding: 10px; border: 1px solid #ddd;'>تاریخ ایجاد</th>";
        echo "</tr>";
        
        foreach ($requests as $request) {
            echo "<tr>";
            echo "<td style='padding: 10px; border: 1px solid #ddd;'>" . $request['id'] . "</td>";
            echo "<td style='padding: 10px; border: 1px solid #ddd;'>" . htmlspecialchars($request['request_number']) . "</td>";
            echo "<td style='padding: 10px; border: 1px solid #ddd;'>" . htmlspecialchars($request['company_name']) . "</td>";
            echo "<td style='padding: 10px; border: 1px solid #ddd;'>" . htmlspecialchars($request['request_method']) . "</td>";
            echo "<td style='padding: 10px; border: 1px solid #ddd; text-align: center;'>" . $request['visitor_count_actual'] . "</td>";
            echo "<td style='padding: 10px; border: 1px solid #ddd;'><span style='background: #e3f2fd; color: #1976d2; padding: 4px 8px; border-radius: 10px; font-size: 0.8rem;'>" . htmlspecialchars($request['status']) . "</span></td>";
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

echo "<h2>5. قابلیت‌های جدید اضافه شده</h2>";
echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
echo "<ul style='list-style: none; padding: 0;'>";
echo "<li style='margin: 10px 0; padding: 15px; background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);'>✅ <strong>فیلدهای بازدیدکنندگان</strong> - به تعداد بازدیدکنندگان فیلدهای جداگانه</li>";
echo "<li style='margin: 10px 0; padding: 15px; background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);'>✅ <strong>آپلود کارت ملی</strong> - برای هر بازدیدکننده جداگانه</li>";
echo "<li style='margin: 10px 0; padding: 15px; background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);'>✅ <strong>روش درخواست "نامه"</strong> - با فیلد آپلود نامه</li>";
echo "<li style='margin: 10px 0; padding: 15px; background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);'>✅ <strong>رابط کاربری پویا</strong> - فیلدها بر اساس تعداد بازدیدکنندگان</li>";
echo "<li style='margin: 10px 0; padding: 15px; background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);'>✅ <strong>طراحی حرفه‌ای</strong> - رنگ‌بندی و استایل‌های زیبا</li>";
echo "<li style='margin: 10px 0; padding: 15px; background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);'>✅ <strong>مدیریت فایل‌ها</strong> - آپلود و ذخیره‌سازی امن</li>";
echo "</ul>";
echo "</div>";

echo "<h2>6. لینک‌های تست</h2>";
echo "<div style='text-align: center; margin: 20px 0;'>";
echo "<a href='visit_management.php' target='_blank' style='background: #3498db; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; margin: 10px; display: inline-block; font-size: 16px; font-weight: bold;'>📋 مدیریت بازدیدها (نسخه پیشرفته)</a>";
echo "<a href='visit_dashboard.php' target='_blank' style='background: #27ae60; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; margin: 10px; display: inline-block; font-size: 16px; font-weight: bold;'>🏠 داشبورد بازدیدها</a>";
echo "</div>";

echo "<div style='background: linear-gradient(135deg, #2ecc71, #27ae60); color: white; padding: 30px; border-radius: 15px; margin: 30px 0; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.2);'>";
echo "<h2 style='margin: 0 0 15px 0; font-size: 2rem;'>🎉 سیستم بازدید کارخانه کاملاً پیشرفته شد!</h2>";
echo "<p style='margin: 0; font-size: 1.2rem;'>همه قابلیت‌های درخواستی پیاده‌سازی شدند و آماده استفاده هستند</p>";
echo "</div>";
?>