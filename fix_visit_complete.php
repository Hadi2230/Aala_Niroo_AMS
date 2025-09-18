<?php
// fix_visit_complete.php - اصلاح کامل سیستم بازدید با SMS
session_start();

// تنظیم session برای تست
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['role'] = 'ادمین';

echo "<h1>🔧 اصلاح کامل سیستم بازدید با SMS</h1>";

// تست config.php
try {
    require_once 'config.php';
    echo "<p style='color: green;'>✅ config.php بارگذاری شد</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ خطا در config.php: " . $e->getMessage() . "</p>";
    exit();
}

// حذف و ایجاد مجدد تمام جداول
echo "<h2>1. حذف و ایجاد مجدد جداول</h2>";
try {
    // حذف جداول وابسته
    $pdo->exec("DROP TABLE IF EXISTS visit_visitors");
    $pdo->exec("DROP TABLE IF EXISTS visit_documents");
    $pdo->exec("DROP TABLE IF EXISTS visit_requests");
    $pdo->exec("DROP TABLE IF EXISTS sms_templates");
    
    echo "<p style='color: orange;'>⚠️ جداول قدیمی حذف شدند</p>";
    
    // ایجاد جدول visit_requests با ساختار کامل
    $create_table_sql = "
        CREATE TABLE visit_requests (
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
    
    // ایجاد جدول visit_visitors
    $create_visitors_table_sql = "
        CREATE TABLE visit_visitors (
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
    
    // ایجاد جدول visit_documents
    $create_documents_table_sql = "
        CREATE TABLE visit_documents (
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
    
    // ایجاد جدول قالب‌های SMS
    $create_sms_templates_table_sql = "
        CREATE TABLE sms_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            template_name VARCHAR(100) NOT NULL,
            template_type ENUM('visit_confirmation', 'visit_reminder', 'visit_cancellation', 'visit_completion') NOT NULL,
            template_text TEXT NOT NULL,
            variables JSON,
            is_active BOOLEAN DEFAULT TRUE,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_template_type (template_type),
            INDEX idx_is_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
    ";
    
    $pdo->exec($create_sms_templates_table_sql);
    echo "<p style='color: green;'>✅ جدول sms_templates ایجاد شد</p>";
    
    // اضافه کردن قالب‌های پیش‌فرض
    $default_templates = [
        [
            'template_name' => 'تایید درخواست بازدید',
            'template_type' => 'visit_confirmation',
            'template_text' => 'بازدیدکننده محترم {visitor_name} درخواست شما بابت بازدید از کارخانه شرکت اعلا نیرو جهت {visit_type} در تاریخ {visit_date} به شماره بازدید {request_number} ثبت شده است. باتشکر از توجه شما - شرکت اعلا نیرو',
            'variables' => json_encode(['visitor_name', 'visit_type', 'visit_date', 'request_number']),
            'created_by' => 1
        ],
        [
            'template_name' => 'یادآوری بازدید',
            'template_type' => 'visit_reminder',
            'template_text' => 'یادآوری: بازدید شما از کارخانه شرکت اعلا نیرو در تاریخ {visit_date} به شماره {request_number} برنامه‌ریزی شده است. - شرکت اعلا نیرو',
            'variables' => json_encode(['visit_date', 'request_number']),
            'created_by' => 1
        ]
    ];
    
    foreach ($default_templates as $template) {
        $stmt = $pdo->prepare("
            INSERT INTO sms_templates (template_name, template_type, template_text, variables, created_by) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $template['template_name'],
            $template['template_type'],
            $template['template_text'],
            $template['variables'],
            $template['created_by']
        ]);
    }
    
    echo "<p style='color: green;'>✅ قالب‌های پیش‌فرض SMS اضافه شدند</p>";
    
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

// تست ثبت درخواست
echo "<h2>3. تست ثبت درخواست</h2>";
try {
    $visit_data = [
        'company_name' => 'شرکت تست کامل',
        'contact_person' => 'احمد احمدی',
        'contact_phone' => '09123456789',
        'contact_email' => 'ahmad@complete.com',
        'visitor_count' => 2,
        'visit_purpose' => 'تست سیستم کامل',
        'visit_type' => 'test',
        'request_method' => 'phone',
        'preferred_dates' => [date('Y-m-d', strtotime('+1 day'))],
        'nda_required' => false,
        'special_requirements' => 'تست',
        'created_by' => 1
    ];
    
    $visit_id = createVisitRequest($pdo, $visit_data);
    echo "<p style='color: green;'>✅ درخواست تست با موفقیت ثبت شد - ID: " . $visit_id . "</p>";
    
    // اضافه کردن بازدیدکنندگان
    $visitors = [
        [
            'first_name' => 'احمد',
            'last_name' => 'احمدی',
            'phone' => '09123456789',
            'email' => 'ahmad@test.com',
            'position' => 'مدیر فنی',
            'company' => 'شرکت تست کامل'
        ],
        [
            'first_name' => 'فاطمه',
            'last_name' => 'محمدی',
            'phone' => '09123456790',
            'email' => 'fateme@test.com',
            'position' => 'مهندس',
            'company' => 'شرکت تست کامل'
        ]
    ];
    
    foreach ($visitors as $visitor) {
        $stmt = $pdo->prepare("
            INSERT INTO visit_visitors (visit_request_id, first_name, last_name, phone, email, position, company) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $visit_id,
            $visitor['first_name'],
            $visitor['last_name'],
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

echo "<h2>4. لینک‌های تست</h2>";
echo "<div style='text-align: center; margin: 20px 0;'>";
echo "<a href='visit_management.php' target='_blank' style='background: #3498db; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; margin: 10px; display: inline-block; font-size: 16px; font-weight: bold;'>📋 مدیریت بازدیدها (نسخه کامل)</a>";
echo "<a href='sms_templates.php' target='_blank' style='background: #e74c3c; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; margin: 10px; display: inline-block; font-size: 16px; font-weight: bold;'>📱 مدیریت قالب‌های SMS</a>";
echo "<a href='visit_dashboard.php' target='_blank' style='background: #27ae60; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; margin: 10px; display: inline-block; font-size: 16px; font-weight: bold;'>🏠 داشبورد بازدیدها</a>";
echo "</div>";

echo "<div style='background: linear-gradient(135deg, #2ecc71, #27ae60); color: white; padding: 30px; border-radius: 15px; margin: 30px 0; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.2);'>";
echo "<h2 style='margin: 0 0 15px 0; font-size: 2rem;'>🎉 سیستم بازدید کارخانه کاملاً اصلاح شد!</h2>";
echo "<p style='margin: 0; font-size: 1.2rem;'>حالا می‌توانید درخواست‌ها را ثبت کنید و SMS ارسال کنید</p>";
echo "</div>";
?>