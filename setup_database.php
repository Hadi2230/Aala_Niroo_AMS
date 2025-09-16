<?php
// اسکریپت ایجاد دیتابیس و جداول
session_start();

// تنظیمات دیتابیس
$db_host = 'localhost';
$db_name = 'aala_niroo_ams';
$db_user = 'root';
$db_pass = '';

echo "<h2>راه‌اندازی دیتابیس سیستم مدیریت دارایی‌ها</h2>";

try {
    // اتصال بدون انتخاب دیتابیس
    $pdo = new PDO("mysql:host=$db_host;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
    echo "<p style='color: green;'>✅ اتصال به سرور MariaDB موفق بود</p>";
    
    // ایجاد دیتابیس
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<p style='color: green;'>✅ دیتابیس '$db_name' ایجاد شد</p>";
    
    // انتخاب دیتابیس
    $pdo->exec("USE `$db_name`");
    echo "<p style='color: green;'>✅ دیتابیس '$db_name' انتخاب شد</p>";
    
    // ایجاد جداول
    $tables = [
        // جدول انواع دارایی‌ها
        "CREATE TABLE IF NOT EXISTS asset_types (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL UNIQUE,
            display_name VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // جدول مشتریان
        "CREATE TABLE IF NOT EXISTS customers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(255) NOT NULL,
            phone VARCHAR(20) NOT NULL UNIQUE,
            company VARCHAR(255),
            customer_type ENUM('حقیقی', 'حقوقی') DEFAULT 'حقیقی',
            company_phone VARCHAR(20),
            responsible_phone VARCHAR(20),
            address TEXT,
            city VARCHAR(100),
            email VARCHAR(255),
            company_email VARCHAR(255),
            notification_type VARCHAR(20) DEFAULT 'none',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_phone (phone),
            INDEX idx_company (company),
            INDEX idx_city (city),
            INDEX idx_customer_type (customer_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // جدول کاربران
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(255),
            role ENUM('ادمین', 'کاربر عادی', 'اپراتور') DEFAULT 'کاربر عادی',
            is_active BOOLEAN DEFAULT true,
            last_login TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_username (username),
            INDEX idx_role (role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // جدول دارایی‌ها
        "CREATE TABLE IF NOT EXISTS assets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            type_id INT NOT NULL,
            serial_number VARCHAR(255) UNIQUE,
            purchase_date DATE,
            status VARCHAR(50) DEFAULT 'فعال',
            brand VARCHAR(255),
            model VARCHAR(255),
            power_capacity VARCHAR(100),
            engine_type VARCHAR(100),
            consumable_type VARCHAR(100),
            location VARCHAR(255),
            quantity INT DEFAULT 0,
            supplier_name VARCHAR(255),
            supplier_contact VARCHAR(255),
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (type_id) REFERENCES asset_types(id) ON DELETE CASCADE,
            INDEX idx_type_id (type_id),
            INDEX idx_status (status),
            INDEX idx_serial (serial_number),
            INDEX idx_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // جدول انتساب‌ها
        "CREATE TABLE IF NOT EXISTS asset_assignments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            asset_id INT NOT NULL,
            customer_id INT NOT NULL,
            assignment_date DATE,
            notes TEXT,
            assignment_status VARCHAR(20) DEFAULT 'فعال',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
            INDEX idx_asset_id (asset_id),
            INDEX idx_customer_id (customer_id),
            INDEX idx_assignment_date (assignment_date),
            INDEX idx_status (assignment_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // جدول نظرسنجی‌ها
        "CREATE TABLE IF NOT EXISTS surveys (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            is_active BOOLEAN DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_is_active (is_active),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // جدول سوالات نظرسنجی
        "CREATE TABLE IF NOT EXISTS survey_questions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            survey_id INT NOT NULL,
            question_text TEXT NOT NULL,
            question_type ENUM('text', 'textarea', 'radio', 'checkbox', 'select', 'number', 'date', 'yes_no', 'rating') DEFAULT 'text',
            is_required BOOLEAN DEFAULT 1,
            options TEXT,
            order_index INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE,
            INDEX idx_survey_id (survey_id),
            INDEX idx_order (order_index)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // جدول ارسال‌های نظرسنجی
        "CREATE TABLE IF NOT EXISTS survey_submissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            survey_id INT NOT NULL,
            customer_id INT NOT NULL,
            asset_id INT,
            started_by INT NOT NULL,
            submitted_by INT,
            status ENUM('در حال تکمیل', 'تکمیل شده', 'لغو شده') DEFAULT 'در حال تکمیل',
            sms_sent BOOLEAN DEFAULT 0,
            sms_sent_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
            FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE SET NULL,
            FOREIGN KEY (started_by) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_survey_id (survey_id),
            INDEX idx_customer_id (customer_id),
            INDEX idx_asset_id (asset_id),
            INDEX idx_started_by (started_by),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // جدول پاسخ‌های نظرسنجی
        "CREATE TABLE IF NOT EXISTS survey_responses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            survey_id INT NOT NULL,
            question_id INT NOT NULL,
            customer_id INT NOT NULL,
            asset_id INT,
            response_text TEXT,
            responded_by INT NOT NULL,
            submission_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE,
            FOREIGN KEY (question_id) REFERENCES survey_questions(id) ON DELETE CASCADE,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
            FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE SET NULL,
            FOREIGN KEY (responded_by) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (submission_id) REFERENCES survey_submissions(id) ON DELETE CASCADE,
            INDEX idx_survey_id (survey_id),
            INDEX idx_question_id (question_id),
            INDEX idx_customer_id (customer_id),
            INDEX idx_asset_id (asset_id),
            INDEX idx_submission_id (submission_id),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // جدول تیکت‌ها
        "CREATE TABLE IF NOT EXISTS tickets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ticket_number VARCHAR(20) UNIQUE NOT NULL,
            customer_id INT NOT NULL,
            asset_id INT,
            title VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            priority ENUM('فوری', 'بالا', 'متوسط', 'کم') DEFAULT 'متوسط',
            status ENUM('جدید', 'در انتظار', 'در حال بررسی', 'در انتظار قطعه', 'تکمیل شده', 'لغو شده') DEFAULT 'جدید',
            assigned_to INT,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            resolved_at TIMESTAMP NULL,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
            FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE SET NULL,
            FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_customer_id (customer_id),
            INDEX idx_asset_id (asset_id),
            INDEX idx_assigned_to (assigned_to),
            INDEX idx_status (status),
            INDEX idx_priority (priority),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // جدول لاگ سیستم
        "CREATE TABLE IF NOT EXISTS system_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            action VARCHAR(100) NOT NULL,
            description TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            request_data TEXT,
            response_data TEXT,
            severity VARCHAR(20) DEFAULT 'info',
            module VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_action (action),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];
    
    $created_tables = 0;
    foreach ($tables as $table) {
        try {
            $pdo->exec($table);
            $created_tables++;
        } catch (PDOException $e) {
            echo "<p style='color: orange;'>⚠️ خطا در ایجاد جدول: " . $e->getMessage() . "</p>";
        }
    }
    
    echo "<p style='color: green;'>✅ $created_tables جدول ایجاد شد</p>";
    
    // درج داده‌های اولیه
    echo "<h3>درج داده‌های اولیه:</h3>";
    
    // بررسی وجود کاربر admin
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE username = 'admin'");
    $admin_exists = $stmt->fetch()['count'] > 0;
    
    if (!$admin_exists) {
        $pdo->exec("INSERT INTO users (username, password, full_name, role) VALUES ('admin', '" . password_hash('admin', PASSWORD_DEFAULT) . "', 'مدیر سیستم', 'ادمین')");
        echo "<p style='color: green;'>✅ کاربر admin ایجاد شد (رمز: admin)</p>";
    } else {
        echo "<p style='color: blue;'>ℹ️ کاربر admin قبلاً وجود دارد</p>";
    }
    
    // بررسی وجود انواع دارایی
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM asset_types");
    $types_count = $stmt->fetch()['count'];
    
    if ($types_count == 0) {
        $pdo->exec("INSERT INTO asset_types (name, display_name) VALUES 
            ('generator', 'ژنراتور'),
            ('power_motor', 'موتور برق'),
            ('consumable', 'اقلام مصرفی')");
        echo "<p style='color: green;'>✅ انواع دارایی اضافه شد</p>";
    } else {
        echo "<p style='color: blue;'>ℹ️ انواع دارایی قبلاً وجود دارد ($types_count نوع)</p>";
    }
    
    // بررسی وجود نظرسنجی
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM surveys");
    $surveys_count = $stmt->fetch()['count'];
    
    if ($surveys_count == 0) {
        $pdo->exec("INSERT INTO surveys (title, description, is_active) VALUES 
            ('نظرسنجی رضایت مشتریان', 'نظرسنجی عمومی رضایت مشتریان از خدمات', 1)");
        echo "<p style='color: green;'>✅ نظرسنجی نمونه ایجاد شد</p>";
    } else {
        echo "<p style='color: blue;'>ℹ️ نظرسنجی قبلاً وجود دارد ($surveys_count نظرسنجی)</p>";
    }
    
    echo "<h3 style='color: green;'>🎉 راه‌اندازی دیتابیس با موفقیت تکمیل شد!</h3>";
    echo "<p><strong>اطلاعات ورود:</strong></p>";
    echo "<ul>";
    echo "<li>نام کاربری: admin</li>";
    echo "<li>رمز عبور: admin</li>";
    echo "</ul>";
    echo "<p><a href='login.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>ورود به سیستم</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ خطا در اتصال به دیتابیس: " . $e->getMessage() . "</p>";
    echo "<p>لطفاً مطمئن شوید که:</p>";
    echo "<ul>";
    echo "<li>سرور MariaDB/MySQL در حال اجرا است</li>";
    echo "<li>نام کاربری و رمز عبور صحیح است</li>";
    echo "<li>دسترسی‌های لازم برای ایجاد دیتابیس وجود دارد</li>";
    echo "</ul>";
}
?>