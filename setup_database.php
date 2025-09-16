<?php
// اسکریپت ایجاد دیتابیس و جداول
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "شروع ایجاد دیتابیس و جداول...\n\n";

// تنظیمات دیتابیس
$db_host = 'localhost';
$db_port = '3306';
$db_user = 'root';
$db_pass = '';

try {
    // اتصال به MySQL بدون انتخاب دیتابیس خاص
    $pdo = new PDO("mysql:host=$db_host;port=$db_port;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
    echo "✓ اتصال به MySQL برقرار شد\n";
    
    // ایجاد دیتابیس
    $pdo->exec("CREATE DATABASE IF NOT EXISTS aala_niroo_ams CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✓ دیتابیس aala_niroo_ams ایجاد شد\n";
    
    // انتخاب دیتابیس
    $pdo->exec("USE aala_niroo_ams");
    echo "✓ دیتابیس انتخاب شد\n";
    
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
        
        // جدول فیلدهای سفارشی برای هر نوع دارایی
        "CREATE TABLE IF NOT EXISTS asset_fields (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type_id INT,
            field_name VARCHAR(100),
            field_type VARCHAR(20) DEFAULT 'text',
            is_required BOOLEAN DEFAULT 0,
            options TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (type_id) REFERENCES asset_types(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // جدول اصلی دارایی‌ها
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
            engine_model VARCHAR(255),
            engine_serial VARCHAR(255),
            alternator_model VARCHAR(255),
            alternator_serial VARCHAR(255),
            device_model VARCHAR(255),
            device_serial VARCHAR(255),
            control_panel_model VARCHAR(255),
            breaker_model VARCHAR(255),
            fuel_tank_specs TEXT,
            battery VARCHAR(255),
            battery_charger VARCHAR(255),
            heater VARCHAR(255),
            oil_capacity VARCHAR(255),
            radiator_capacity VARCHAR(255),
            antifreeze VARCHAR(255),
            other_items TEXT,
            workshop_entry_date DATE,
            workshop_exit_date DATE,
            datasheet_link VARCHAR(500),
            engine_manual_link VARCHAR(500),
            alternator_manual_link VARCHAR(500),
            control_panel_manual_link VARCHAR(500),
            description TEXT,
            oil_filter_part VARCHAR(100),
            fuel_filter_part VARCHAR(100),
            water_fuel_filter_part VARCHAR(100),
            air_filter_part VARCHAR(100),
            water_filter_part VARCHAR(100),
            device_identifier VARCHAR(255),
            supply_method VARCHAR(255),
            location VARCHAR(255),
            quantity INT DEFAULT 0,
            supplier_name VARCHAR(255),
            supplier_contact VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (type_id) REFERENCES asset_types(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // جدول تصاویر دارایی‌ها
        "CREATE TABLE IF NOT EXISTS asset_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            asset_id INT NOT NULL,
            field_name VARCHAR(100) NOT NULL,
            image_path VARCHAR(500) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // جدول مشتریان
        "CREATE TABLE IF NOT EXISTS customers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(255) NOT NULL,
            phone VARCHAR(20) NOT NULL UNIQUE,
            company VARCHAR(255),
            customer_type VARCHAR(20) DEFAULT 'حقیقی',
            company_phone VARCHAR(20),
            responsible_phone VARCHAR(20),
            address TEXT,
            city VARCHAR(100),
            email VARCHAR(255),
            company_email VARCHAR(255),
            notification_type VARCHAR(20) DEFAULT 'none',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // جدول کاربران
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(255),
            role VARCHAR(20) DEFAULT 'کاربر عادی',
            is_active BOOLEAN DEFAULT 1,
            last_login TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // جدول انتساب‌ها (برای assets.php)
        "CREATE TABLE IF NOT EXISTS assignments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            asset_id INT NOT NULL,
            customer_id INT NOT NULL,
            assignment_date DATE,
            notes TEXT,
            assignment_status VARCHAR(20) DEFAULT 'فعال',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // جدول گزارشات و لاگ‌ها
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
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // جدول تیکت‌های مشتری
        "CREATE TABLE IF NOT EXISTS tickets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ticket_number VARCHAR(20) UNIQUE NOT NULL,
            customer_id INT NOT NULL,
            asset_id INT,
            title VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            priority VARCHAR(20) DEFAULT 'متوسط',
            status VARCHAR(20) DEFAULT 'جدید',
            assigned_to INT,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            resolved_at TIMESTAMP NULL,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
            FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE SET NULL,
            FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // جدول تاریخچه تیکت‌ها
        "CREATE TABLE IF NOT EXISTS ticket_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ticket_id INT NOT NULL,
            action VARCHAR(100) NOT NULL,
            old_value TEXT,
            new_value TEXT,
            performed_by INT NOT NULL,
            performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            notes TEXT,
            FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
            FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // جدول نظرسنجی‌ها
        "CREATE TABLE IF NOT EXISTS surveys (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            is_active BOOLEAN DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // جدول سوالات نظرسنجی
        "CREATE TABLE IF NOT EXISTS survey_questions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            survey_id INT NOT NULL,
            question_text TEXT NOT NULL,
            question_type VARCHAR(20) DEFAULT 'text',
            is_required BOOLEAN DEFAULT 1,
            options TEXT,
            order_index INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // جدول ارسال‌های نظرسنجی
        "CREATE TABLE IF NOT EXISTS survey_submissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            survey_id INT NOT NULL,
            customer_id INT NOT NULL,
            asset_id INT,
            started_by INT NOT NULL,
            submitted_by INT,
            status VARCHAR(20) DEFAULT 'در حال تکمیل',
            sms_sent BOOLEAN DEFAULT 0,
            sms_sent_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
            FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE SET NULL,
            FOREIGN KEY (started_by) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE CASCADE
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
            FOREIGN KEY (submission_id) REFERENCES survey_submissions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // جدول تامین‌کنندگان
        "CREATE TABLE IF NOT EXISTS suppliers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            supplier_code VARCHAR(50) UNIQUE NOT NULL,
            company_name VARCHAR(255) NOT NULL,
            person_name VARCHAR(255),
            supplier_type VARCHAR(20) NOT NULL,
            activity_field VARCHAR(255),
            logo_path VARCHAR(500),
            address TEXT,
            city VARCHAR(100),
            province VARCHAR(100),
            country VARCHAR(100),
            postal_code VARCHAR(20),
            landline VARCHAR(20),
            mobile VARCHAR(20),
            fax VARCHAR(20),
            email VARCHAR(255),
            website VARCHAR(255),
            linkedin VARCHAR(255),
            whatsapp VARCHAR(20),
            instagram VARCHAR(255),
            contact_person_name VARCHAR(255),
            contact_person_title VARCHAR(100),
            contact_person_phone VARCHAR(20),
            bank_account VARCHAR(50),
            iban VARCHAR(50),
            bank_name VARCHAR(255),
            bank_branch VARCHAR(255),
            economic_code VARCHAR(50),
            national_id VARCHAR(50),
            registration_number VARCHAR(50),
            vat_number VARCHAR(50),
            payment_terms VARCHAR(50),
            main_products TEXT,
            brands_offered TEXT,
            moq VARCHAR(100),
            lead_time VARCHAR(100),
            shipping_conditions TEXT,
            quality_score INT DEFAULT 0,
            cooperation_since INT,
            satisfaction_level INT DEFAULT 0,
            complaints_count INT DEFAULT 0,
            importance_level VARCHAR(20) DEFAULT 'Normal',
            internal_notes TEXT,
            is_active BOOLEAN DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // جدول ابزارها
        "CREATE TABLE IF NOT EXISTS tools (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tool_code VARCHAR(50) UNIQUE NOT NULL,
            name VARCHAR(255) NOT NULL,
            category VARCHAR(50) NOT NULL,
            brand VARCHAR(100),
            model VARCHAR(100),
            serial_number VARCHAR(100),
            purchase_date DATE,
            purchase_price DECIMAL(10,2),
            supplier VARCHAR(255),
            location VARCHAR(255),
            status VARCHAR(50) DEFAULT 'موجود',
            condition_notes TEXT,
            maintenance_date DATE,
            next_maintenance_date DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // جدول تحویل ابزارها
        "CREATE TABLE IF NOT EXISTS tool_issues (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tool_id INT NOT NULL,
            issued_to VARCHAR(255) NOT NULL,
            issued_by INT NOT NULL,
            issue_date DATE NOT NULL,
            expected_return_date DATE,
            actual_return_date DATE,
            purpose TEXT,
            condition_before TEXT,
            condition_after TEXT,
            notes TEXT,
            status VARCHAR(50) DEFAULT 'تحویل_داده_شده',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (tool_id) REFERENCES tools(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // جدول تاریخچه ابزارها
        "CREATE TABLE IF NOT EXISTS tool_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tool_id INT NOT NULL,
            action_type VARCHAR(50) NOT NULL,
            action_description TEXT NOT NULL,
            performed_by INT NOT NULL,
            performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            old_values TEXT,
            new_values TEXT,
            notes TEXT,
            FOREIGN KEY (tool_id) REFERENCES tools(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // جدول مکاتبات مشتریان
        "CREATE TABLE IF NOT EXISTS correspondences (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT NOT NULL,
            correspondence_type VARCHAR(50) NOT NULL,
            subject VARCHAR(500) NOT NULL,
            content TEXT,
            correspondence_date DATE NOT NULL,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // جدول فایل‌های مکاتبات
        "CREATE TABLE IF NOT EXISTS correspondence_files (
            id INT AUTO_INCREMENT PRIMARY KEY,
            correspondence_id INT NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_size INT,
            file_type VARCHAR(100),
            upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (correspondence_id) REFERENCES correspondences(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // جدول قالب‌های اطلاع‌رسانی
        "CREATE TABLE IF NOT EXISTS notification_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            template_type VARCHAR(20) NOT NULL,
            template_name VARCHAR(255) NOT NULL,
            subject VARCHAR(500),
            content TEXT NOT NULL,
            is_active BOOLEAN DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];
    
    $created_tables = 0;
    foreach ($tables as $table) {
        try {
            $pdo->exec($table);
            $created_tables++;
        } catch (PDOException $e) {
            echo "✗ خطا در ایجاد جدول: " . $e->getMessage() . "\n";
        }
    }
    
    echo "✓ $created_tables جدول ایجاد شد\n";
    
    // درج داده‌های اولیه
    $initial_data = [
        // درج انواع دارایی
        "INSERT IGNORE INTO asset_types (name, display_name) VALUES 
        ('generator', 'ژنراتور'),
        ('power_motor', 'موتور برق'),
        ('consumable', 'اقلام مصرفی')",
        
        // درج کاربر پیش فرض (admin/admin)
        "INSERT IGNORE INTO users (username, password, full_name, role) VALUES
        ('admin', '" . password_hash('admin', PASSWORD_DEFAULT) . "', 'مدیر سیستم', 'مدیر')",
        
        // درج قالب‌های پیش‌فرض اطلاع‌رسانی
        "INSERT IGNORE INTO notification_templates (template_type, template_name, subject, content) VALUES
        ('email', 'خوش‌آمدگویی مشتری', 'خوش‌آمدید به شرکت اعلا نیرو', 'سلام {customer_name}،\n\nخوش‌آمدید به سیستم مدیریت دارایی‌های شرکت اعلا نیرو.\n\nنام کاربری: {username}\nایمیل: {email}\n\nلینک ورود: {app_link}\n\nبا تشکر'),
        ('sms', 'خوش‌آمدگویی مشتری', '', 'سلام {customer_name}، خوش‌آمدید به شرکت اعلا نیرو. نام کاربری: {username}'),
        ('email', 'اطلاع‌رسانی مدیر', 'ثبت مشتری جدید', 'مشتری جدیدی در سیستم ثبت شد:\nنام: {customer_name}\nنوع: {customer_type}\nتاریخ: {date}'),
        ('sms', 'اطلاع‌رسانی مدیر', '', 'مشتری جدید: {customer_name} ({customer_type}) در تاریخ {date} ثبت شد')"
    ];
    
    $inserted_data = 0;
    foreach ($initial_data as $query) {
        try {
            $pdo->exec($query);
            $inserted_data++;
        } catch (PDOException $e) {
            echo "✗ خطا در درج داده اولیه: " . $e->getMessage() . "\n";
        }
    }
    
    echo "✓ $inserted_data مجموعه داده اولیه درج شد\n";
    
    echo "\n🎉 دیتابیس و جداول با موفقیت ایجاد شدند!\n";
    echo "نام کاربری: admin\n";
    echo "رمز عبور: admin\n";
    
} catch (PDOException $e) {
    echo "✗ خطا در اتصال به دیتابیس: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "✗ خطای عمومی: " . $e->getMessage() . "\n";
}
?>