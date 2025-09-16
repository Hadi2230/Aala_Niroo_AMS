<?php
// config.php - نسخه کامل و اصلاح شده برای SQLite

// شروع session اگر شروع نشده
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تنظیمات امنیتی
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php-errors.log');

// تنظیمات دیتابیس
$db_host = 'localhost';
$db_port = '3306';
$db_name = 'aala_niroo_ams';
$db_user = 'root';
$db_pass = '';

// تنظیمات زمانzone
date_default_timezone_set('Asia/Tehran');

try {
    // ابتدا بدون انتخاب دیتابیس متصل می‌شویم
    $pdo = new PDO("mysql:host=$db_host;port=$db_port;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
    // بررسی وجود دیتابیس
    $stmt = $pdo->query("SHOW DATABASES LIKE '$db_name'");
    $db_exists = $stmt->fetch();
    
    if (!$db_exists) {
        // ایجاد دیتابیس اگر وجود ندارد
        $pdo->exec("CREATE DATABASE IF NOT EXISTS $db_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }
    
    // انتخاب دیتابیس
    $pdo->exec("USE $db_name");
    
} catch (PDOException $e) {
    error_log("[" . date('Y-m-d H:i:s') . "] خطا در اتصال به دیتابیس: " . $e->getMessage());
    die("<div style='text-align: center; padding: 50px; font-family: Tahoma; background: #f8f9fa;'>
        <div style='background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 600px; margin: 0 auto;'>
            <h2 style='color: #e74c3c;'>⚠️ خطا در اتصال به سیستم</h2>
            <p>لطفاً مطمئن شوید که:</p>
            <ul style='text-align: right; color: #666;'>
                <li>XAMPP یا WAMP در حال اجرا است</li>
                <li>MySQL سرویس فعال است</li>
                <li>تنظیمات اتصال صحیح است</li>
            </ul>
            <p><small style='color: #999;'>خطای سیستمی: " . $e->getMessage() . "</small></p>
            <a href='setup_database.php' style='display: inline-block; background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-top: 15px;'>🔧 راه‌اندازی دیتابیس</a>
        </div>
        </div>");
}

// تولید token برای جلوگیری از CSRF اگر وجود ندارد
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// تابع بررسی دسترسی‌ها
function hasPermission($permission) {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    // مدیران همه دسترسی‌ها را دارند
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'مدیر') {
        return true;
    }
    
    // بررسی دسترسی‌های سفارشی
    if (isset($_SESSION['permissions']) && is_array($_SESSION['permissions'])) {
        return in_array($permission, $_SESSION['permissions']) || in_array('*', $_SESSION['permissions']);
    }
    
    return false;
}

// ایجاد جداول فقط یک بار در طول session
if (!isset($_SESSION['tables_created'])) {
    createDatabaseTables($pdo);
    $_SESSION['tables_created'] = true;
}

/**
 * ایجاد جداول دیتابیس
 */
function createDatabaseTables($pdo) {
    // ایجاد پوشه logs اگر وجود ندارد
    if (!is_dir(__DIR__ . '/logs')) {
        mkdir(__DIR__ . '/logs', 0755, true);
    }
    
    // ایجاد پوشه uploads اگر وجود ندارد
    if (!is_dir(__DIR__ . '/uploads')) {
        mkdir(__DIR__ . '/uploads', 0755, true);
        mkdir(__DIR__ . '/uploads/installations', 0755, true);
        mkdir(__DIR__ . '/uploads/assets', 0755, true);
        mkdir(__DIR__ . '/uploads/filters', 0755, true);
        
        // ایجاد فایل htaccess برای محافظت از پوشه uploads
        file_put_contents(__DIR__ . '/uploads/.htaccess', 
            "Order deny,allow\nDeny from all\n<Files ~ \"\.(jpg|jpeg|png|gif)$\">\nAllow from all\n</Files>");
    }
    
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
            
            -- فیلدهای عمومی
            brand VARCHAR(255),
            model VARCHAR(255),
            power_capacity VARCHAR(100),
            engine_type VARCHAR(100),
            consumable_type VARCHAR(100),
            
            -- فیلدهای خاص ژنراتور
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
            
            -- فیلدهای پارت نامبر
            oil_filter_part VARCHAR(100),
            fuel_filter_part VARCHAR(100),
            water_fuel_filter_part VARCHAR(100),
            air_filter_part VARCHAR(100),
            water_filter_part VARCHAR(100),
            
            -- فیلدهای جدید
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
        
        // جدول جزئیات انتساب
        "CREATE TABLE IF NOT EXISTS assignment_details (
            id INT AUTO_INCREMENT PRIMARY KEY,
            assignment_id INT NOT NULL,
            installation_date DATE,
            delivery_person VARCHAR(255),
            installation_address TEXT,
            warranty_start_date DATE,
            warranty_end_date DATE,
            warranty_conditions TEXT,
            employer_name VARCHAR(255),
            employer_phone VARCHAR(20),
            recipient_name VARCHAR(255),
            recipient_phone VARCHAR(20),
            installer_name VARCHAR(255),
            installation_start_date DATE,
            installation_end_date DATE,
            temporary_delivery_date DATE,
            permanent_delivery_date DATE,
            first_service_date DATE,
            post_installation_commitments TEXT,
            notes TEXT,
            installation_photo VARCHAR(500),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (assignment_id) REFERENCES asset_assignments(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // جدول سرویس‌های دارایی
        "CREATE TABLE IF NOT EXISTS asset_services (
            id INT AUTO_INCREMENT PRIMARY KEY,
            asset_id INT NOT NULL,
            service_date DATE,
            service_type VARCHAR(255),
            performed_by VARCHAR(255),
            summary TEXT,
            cost DECIMAL(10,2),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // جدول تسک‌های نگهداری
        "CREATE TABLE IF NOT EXISTS maintenance_tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            asset_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            assigned_to VARCHAR(255),
            planned_date DATE,
            status VARCHAR(20) DEFAULT 'pending',
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // جدول مکاتبات دارایی
        "CREATE TABLE IF NOT EXISTS asset_correspondence (
            id INT AUTO_INCREMENT PRIMARY KEY,
            asset_id INT NOT NULL,
            letter_date DATE,
            subject VARCHAR(500),
            notes TEXT,
            file_path VARCHAR(500),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE
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
        
        // جدول برنامه تعمیرات دوره‌ای
        "CREATE TABLE IF NOT EXISTS maintenance_schedules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            asset_id INT NOT NULL,
            assignment_id INT,
            maintenance_type VARCHAR(50) DEFAULT 'تعمیر دوره‌ای',
            schedule_date DATE NOT NULL,
            interval_days INT DEFAULT 90,
            status VARCHAR(50) DEFAULT 'برنامه‌ریزی شده',
            assigned_to INT,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            completed_at TIMESTAMP NULL,
            FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
            FOREIGN KEY (assignment_id) REFERENCES asset_assignments(id) ON DELETE SET NULL,
            FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // جدول اعلان‌ها
        "CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            type VARCHAR(20) DEFAULT 'سیستم',
            priority VARCHAR(20) DEFAULT 'متوسط',
            is_read BOOLEAN DEFAULT 0,
            related_id INT,
            related_type VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            read_at TIMESTAMP NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // جدول پیام‌های داخلی
        "CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sender_id INT NOT NULL,
            receiver_id INT NOT NULL,
            subject TEXT,
            message TEXT NOT NULL,
            attachment_path TEXT,
            attachment_name TEXT,
            attachment_type TEXT,
            is_read BOOLEAN DEFAULT 0,
            related_ticket_id INT,
            related_maintenance_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            read_at TIMESTAMP NULL,
            FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (related_ticket_id) REFERENCES tickets(id) ON DELETE SET NULL,
            FOREIGN KEY (related_maintenance_id) REFERENCES maintenance_schedules(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // جدول تنظیمات اعلان‌ها
        "CREATE TABLE IF NOT EXISTS notification_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL UNIQUE,
            email_notifications BOOLEAN DEFAULT 1,
            sms_notifications BOOLEAN DEFAULT 0,
            in_app_notifications BOOLEAN DEFAULT 1,
            ticket_notifications BOOLEAN DEFAULT 1,
            maintenance_notifications BOOLEAN DEFAULT 1,
            system_notifications BOOLEAN DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
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
        
        // جدول لاگ پیامک‌ها
        "CREATE TABLE IF NOT EXISTS sms_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            phone VARCHAR(20) NOT NULL,
            message TEXT NOT NULL,
            status VARCHAR(20) DEFAULT 'sent',
            message_id VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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
        
        // جدول مدارک تامین‌کنندگان
        "CREATE TABLE IF NOT EXISTS supplier_documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            supplier_id INT NOT NULL,
            document_type VARCHAR(50) NOT NULL,
            document_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_size INT,
            file_type VARCHAR(100),
            upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            description TEXT,
            is_active BOOLEAN DEFAULT 1,
            FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // جدول مکاتبات تامین‌کنندگان
        "CREATE TABLE IF NOT EXISTS supplier_correspondences (
            id INT AUTO_INCREMENT PRIMARY KEY,
            supplier_id INT NOT NULL,
            correspondence_type VARCHAR(50) NOT NULL,
            subject VARCHAR(500) NOT NULL,
            content TEXT,
            correspondence_date DATE NOT NULL,
            file_path VARCHAR(500),
            file_name VARCHAR(255),
            file_size INT,
            file_type VARCHAR(100),
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            is_important BOOLEAN DEFAULT 0,
            FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
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
        
        // جدول تماس‌های تامین‌کنندگان
        "CREATE TABLE IF NOT EXISTS supplier_contacts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            supplier_id INT NOT NULL,
            contact_name VARCHAR(255) NOT NULL,
            contact_title VARCHAR(100),
            contact_phone VARCHAR(20),
            contact_email VARCHAR(255),
            is_primary BOOLEAN DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // جدول محصولات تامین‌کنندگان
        "CREATE TABLE IF NOT EXISTS supplier_products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            supplier_id INT NOT NULL,
            product_name VARCHAR(255) NOT NULL,
            product_category VARCHAR(100),
            description TEXT,
            price DECIMAL(10,2),
            currency VARCHAR(10) DEFAULT 'IRR',
            is_active BOOLEAN DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // جدول ارزیابی تامین‌کنندگان
        "CREATE TABLE IF NOT EXISTS supplier_evaluations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            supplier_id INT NOT NULL,
            evaluation_date DATE NOT NULL,
            quality_score INT DEFAULT 0,
            delivery_score INT DEFAULT 0,
            service_score INT DEFAULT 0,
            price_score INT DEFAULT 0,
            overall_score DECIMAL(3,2) DEFAULT 0.00,
            comments TEXT,
            evaluated_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
            FOREIGN KEY (evaluated_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];
    
    foreach ($tables as $table) {
        try {
            $pdo->exec($table);
        } catch (PDOException $e) {
            error_log("[" . date('Y-m-d H:i:s') . "] خطا در ایجاد جدول: " . $e->getMessage());
        }
    }
    
    // درج داده‌های اولیه اگر وجود ندارند
    $check_types = $pdo->query("SELECT COUNT(*) as count FROM asset_types")->fetch();
    if ($check_types['count'] == 0) {
        $initial_data = [
            // درج انواع دارایی
            "INSERT INTO asset_types (name, display_name) VALUES 
            ('generator', 'ژنراتور'),
            ('power_motor', 'موتور برق'),
            ('consumable', 'اقلام مصرفی')",
            
            // درج کاربر پیش فرض (admin/admin)
            "INSERT INTO users (username, password, full_name, role) VALUES
            ('admin', '" . password_hash('admin', PASSWORD_DEFAULT) . "', 'مدیر سیستم', 'ادمین')",
            
            // درج فیلدهای سفارشی برای ژنراتور
            "INSERT INTO asset_fields (type_id, field_name, field_type, is_required) VALUES
            (1, 'ظرفیت توان (کیلووات)', 'number', 1),
            (1, 'نوع سوخت', 'select', 1),
            (1, 'تعداد فاز', 'select', 1)",
            
            // درج فیلدهای سفارشی برای موتور برق
            "INSERT INTO asset_fields (type_id, field_name, field_type, is_required) VALUES
            (2, 'قدرت (اسب بخار)', 'number', 1),
            (2, 'ولتاژ کاری', 'select', 1),
            (2, 'نوع خنک‌کننده', 'select', 1)",
            
            // درج فیلدهای سفارشی برای اقلام مصرفی
            "INSERT INTO asset_fields (type_id, field_name, field_type, is_required) VALUES
            (3, 'نوع کالا', 'select', 1),
            (3, 'تعداد/مقدار', 'number', 1),
            (3, 'واحد اندازه‌گیری', 'select', 1)",
            
            // درج قالب‌های پیش‌فرض اطلاع‌رسانی
            "INSERT INTO notification_templates (template_type, template_name, subject, content) VALUES
            ('email', 'خوش‌آمدگویی مشتری', 'خوش‌آمدید به شرکت اعلا نیرو', 'سلام {customer_name}،\n\nخوش‌آمدید به سیستم مدیریت دارایی‌های شرکت اعلا نیرو.\n\nنام کاربری: {username}\nایمیل: {email}\n\nلینک ورود: {app_link}\n\nبا تشکر'),
            ('sms', 'خوش‌آمدگویی مشتری', '', 'سلام {customer_name}، خوش‌آمدید به شرکت اعلا نیرو. نام کاربری: {username}'),
            ('email', 'اطلاع‌رسانی مدیر', 'ثبت مشتری جدید', 'مشتری جدیدی در سیستم ثبت شد:\nنام: {customer_name}\nنوع: {customer_type}\nتاریخ: {date}'),
            ('sms', 'اطلاع‌رسانی مدیر', '', 'مشتری جدید: {customer_name} ({customer_type}) در تاریخ {date} ثبت شد')"
        ];
        
        foreach ($initial_data as $query) {
            try {
                $pdo->exec($query);
            } catch (PDOException $e) {
                error_log("[" . date('Y-m-d H:i:s') . "] خطا در درج داده اولیه: " . $e->getMessage());
            }
        }
    }
}

/**
 * توابع کمکی
 */

// پاکسازی و اعتبارسنجی ورودی‌ها
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// اعتبارسنجی شماره تلفن
function validatePhone($phone) {
    return preg_match('/^[0-9]{10,11}$/', $phone);
}

// اعتبارسنجی ایمیل
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// هدایت به صفحه دیگر
function redirect($url, $statusCode = 303) {
    header('Location: ' . $url, true, $statusCode);
    exit();
}

// بررسی درخواست AJAX
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

// پاسخ JSON
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

// ثبت لاگ سیستم
function logAction($pdo, $action, $description = '', $severity = 'info', $module = null, $request_data = null, $response_data = null) {
    $user_id = $_SESSION['user_id'] ?? null;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // تبدیل داده‌ها به JSON اگر آرایه باشند
    if (is_array($request_data)) {
        $request_data = json_encode($request_data, JSON_UNESCAPED_UNICODE);
    }
    if (is_array($response_data)) {
        $response_data = json_encode($response_data, JSON_UNESCAPED_UNICODE);
    }
    
    $stmt = $pdo->prepare("INSERT INTO system_logs (user_id, action, description, ip_address, user_agent, request_data, response_data, severity, module) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $action, $description, $ip_address, $user_agent, $request_data, $response_data, $severity, $module]);
}

// نام مستعار برای logAction
function log_action($action, $description = '') {
    global $pdo;
    logAction($pdo, $action, $description);
}

// تابع تبدیل تاریخ میلادی به شمسی
function jalali_format($date) {
    if (empty($date) || $date === '-') {
        return '-';
    }
    
    try {
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return $date;
        }
        
        // تبدیل ساده به شمسی (برای نمایش)
        $year = date('Y', $timestamp);
        $month = date('m', $timestamp);
        $day = date('d', $timestamp);
        
        // تبدیل تقریبی (برای نمایش بهتر)
        $jalali_year = $year - 621;
        if ($month > 3) {
            $jalali_year++;
        }
        
        return $jalali_year . '/' . $month . '/' . $day;
    } catch (Exception $e) {
        return $date;
    }
}

// آپلود فایل با اعتبارسنجی
function uploadFile($file, $target_dir, $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'pdf']) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('خطا در آپلود فایل');
    }
    
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_ext, $allowed_types)) {
        throw new Exception('نوع فایل مجاز نیست');
    }
    
    if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
        throw new Exception('حجم فایل بیش از حد مجاز است');
    }
    
    $file_name = time() . '_' . uniqid() . '.' . $file_ext;
    $target_file = $target_dir . $file_name;
    
    if (!move_uploaded_file($file['tmp_name'], $target_file)) {
        throw new Exception('خطا در ذخیره فایل');
    }
    
    return $target_file;
}

// بررسی CSRF token
function verifyCsrfToken() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            die('درخواست نامعتبر است - CSRF Token validation failed');
        }
    }
}

// تولید فیلد CSRF
function csrf_field() {
    echo '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
}

// بررسی دسترسی کاربر
function checkPermission($required_role = 'کاربر عادی') {
    if (!isset($_SESSION['user_id'])) {
        redirect('login.php');
    }
    
    $roles = ['کاربر عادی' => 1, 'اپراتور' => 2, 'ادمین' => 3];
    
    if (isset($roles[$_SESSION['role']]) && isset($roles[$required_role])) {
        if ($roles[$_SESSION['role']] < $roles[$required_role]) {
            die('دسترسی غیرمجاز - شما مجوز دسترسی به این بخش را ندارید');
        }
    }
}

// بررسی احراز هویت (نام مستعار برای checkPermission)
function require_auth($required_role = 'کاربر عادی') {
    checkPermission($required_role);
}

// فرمت تاریخ شمسی
function jalaliDate($date = null) {
    if (!$date) $date = time();
    $date = is_numeric($date) ? $date : strtotime($date);
    
    $year = date('Y', $date);
    $month = date('m', $date);
    $day = date('d', $date);
    
    // تبدیل تاریخ میلادی به شمسی (ساده شده)
    $jalali = gregorian_to_jalali($year, $month, $day);
    return $jalali[0] . '/' . $jalali[1] . '/' . $jalali[2];
}

// تابع تبدیل تاریخ میلادی به شمسی
function gregorian_to_jalali($gy, $gm, $gd) {
    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + ((int)(($gy2 + 3) / 4)) - ((int)(($gy2 + 99) / 100)) + ((int)(($gy2 + 399) / 400)) + $gd + $g_d_m[$gm - 1];
    $jy = -1595 + (33 * ((int)($days / 12053)));
    $days %= 12053;
    $jy += 4 * ((int)($days / 1461));
    $days %= 1461;
    if ($days > 365) {
        $jy += (int)(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    $jm = ($days < 186) ? 1 + (int)($days / 31) : 7 + (int)(($days - 186) / 30);
    $jd = 1 + (($days < 186) ? ($days % 31) : (($days - 186) % 30));
    return [$jy, $jm, $jd];
}

/**
 * توابع مدیریت Workflow و اعلان‌ها
 */

// ارسال اعلان
function sendNotification($pdo, $user_id, $title, $message, $type = 'سیستم', $priority = 'متوسط', $related_id = null, $related_type = null) {
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, priority, related_id, related_type) 
                          VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $title, $message, $type, $priority, $related_id, $related_type]);
    
    // اگر user_id مشخص باشد، اعلان‌های اضافی ارسال کن
    if ($user_id) {
        $settings = getUserNotificationSettings($pdo, $user_id);
        if ($settings) {
            // ارسال ایمیل
            if ($settings['email_notifications']) {
                sendEmailNotification($user_id, $title, $message);
            }
            
            // ارسال SMS
            if ($settings['sms_notifications']) {
                sendSMSNotification($user_id, $title, $message);
            }
        }
    }
}

// دریافت تنظیمات اعلان کاربر
function getUserNotificationSettings($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM notification_settings WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

// ایجاد تنظیمات اعلان برای کاربر جدید
function createUserNotificationSettings($pdo, $user_id) {
    $stmt = $pdo->prepare("INSERT INTO notification_settings (user_id) VALUES (?)");
    $stmt->execute([$user_id]);
}

// ارسال ایمیل (پیاده‌سازی ساده)
function sendEmailNotification($user_id, $title, $message) {
    // اینجا می‌توانید از PHPMailer یا کتابخانه‌های دیگر استفاده کنید
    error_log("Email notification to user {$user_id}: {$title} - {$message}");
}

// ارسال SMS (پیاده‌سازی ساده)
function sendSMSNotification($user_id, $title, $message) {
    try {
        // دریافت شماره تلفن کاربر
        global $pdo;
        $stmt = $pdo->prepare("SELECT phone FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if ($user && !empty($user['phone'])) {
            // بارگذاری فایل SMS
            require_once __DIR__ . '/sms.php';
            
            // ارسال پیامک
            $result = send_sms($user['phone'], $title . "\n" . $message);
            
            // ثبت در لاگ
            if ($result['success']) {
                logAction($pdo, 'SMS_SENT', "پیامک به کاربر $user_id ارسال شد", 'info', 'sms', [
                    'user_id' => $user_id,
                    'phone' => $user['phone'],
                    'message_id' => $result['message_id'] ?? null
                ]);
            } else {
                logAction($pdo, 'SMS_ERROR', "خطا در ارسال پیامک به کاربر $user_id: " . $result['error'], 'error', 'sms', [
                    'user_id' => $user_id,
                    'phone' => $user['phone'],
                    'error' => $result['error']
                ]);
            }
            
            return $result;
        } else {
            error_log("SMS notification failed: No phone number for user {$user_id}");
            return ['success' => false, 'error' => 'شماره تلفن کاربر یافت نشد'];
        }
    } catch (Exception $e) {
        error_log("SMS notification error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// ایجاد برنامه تعمیرات دوره‌ای
function createMaintenanceSchedule($pdo, $asset_id, $assignment_id, $schedule_date, $interval_days = 90, $maintenance_type = 'تعمیر دوره‌ای', $assigned_to = null) {
    $stmt = $pdo->prepare("INSERT INTO maintenance_schedules (asset_id, assignment_id, maintenance_type, schedule_date, interval_days, assigned_to) 
                          VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$asset_id, $assignment_id, $maintenance_type, $schedule_date, $interval_days, $assigned_to]);
    
    $maintenance_id = $pdo->lastInsertId();
    
    // ارسال اعلان
    sendNotification($pdo, $assigned_to, 'برنامه تعمیرات جدید', 
                    "برنامه تعمیرات دوره‌ای برای تاریخ " . jalaliDate($schedule_date) . " ایجاد شد", 
                    'تعمیرات', 'متوسط', $maintenance_id, 'maintenance');
    
    return $maintenance_id;
}

// بررسی تعمیرات دوره‌ای نزدیک
function checkUpcomingMaintenance($pdo, $days_ahead = 7) {
    $future_date = date('Y-m-d', strtotime("+{$days_ahead} days"));
    
    $stmt = $pdo->prepare("
        SELECT ms.*, a.name as asset_name, c.full_name as customer_name, u.full_name as assigned_user
        FROM maintenance_schedules ms
        LEFT JOIN assets a ON ms.asset_id = a.id
        LEFT JOIN asset_assignments aa ON ms.assignment_id = aa.id
        LEFT JOIN customers c ON aa.customer_id = c.id
        LEFT JOIN users u ON ms.assigned_to = u.id
        WHERE ms.schedule_date <= ? AND ms.status = 'برنامه‌ریزی شده'
        ORDER BY ms.schedule_date ASC
    ");
    $stmt->execute([$future_date]);
    return $stmt->fetchAll();
}

// ارسال پیام داخلی
function sendInternalMessage($pdo, $sender_id, $receiver_id, $subject, $message, $related_ticket_id = null, $related_maintenance_id = null) {
    $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, subject, message, related_ticket_id, related_maintenance_id) 
                          VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$sender_id, $receiver_id, $subject, $message, $related_ticket_id, $related_maintenance_id]);
    
    // ارسال اعلان
    sendNotification($pdo, $receiver_id, 'پیام جدید', $subject, 'پیام', 'متوسط', $pdo->lastInsertId(), 'message');
    
    return $pdo->lastInsertId();
}

// دریافت اعلان‌های خوانده نشده
function getUnreadNotifications($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? AND is_read = false 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

// علامت‌گذاری اعلان به عنوان خوانده شده
function markNotificationAsRead($pdo, $notification_id) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = true, read_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$notification_id]);
    return $stmt->rowCount() > 0;
}

// دریافت پیام‌های خوانده نشده
function getUnreadMessages($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT m.*, u.full_name as sender_name 
        FROM messages m
        LEFT JOIN users u ON m.sender_id = u.id
        WHERE m.receiver_id = ? AND m.is_read = false 
        ORDER BY m.created_at DESC
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

// ثبت تاریخچه ابزار
function logToolHistory($pdo, $tool_id, $action_type, $action_description, $performed_by, $old_values = null, $new_values = null, $notes = null) {
    $stmt = $pdo->prepare("INSERT INTO tool_history (tool_id, action_type, action_description, performed_by, old_values, new_values, notes) 
                          VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $tool_id, 
        $action_type, 
        $action_description, 
        $performed_by,
        $old_values ? json_encode($old_values, JSON_UNESCAPED_UNICODE) : null,
        $new_values ? json_encode($new_values, JSON_UNESCAPED_UNICODE) : null,
        $notes
    ]);
}

// ایجاد تیکت
function createTicket($pdo, $customer_id, $asset_id, $title, $description, $priority, $created_by) {
    // تولید شماره تیکت منحصر به فرد
    $ticket_number = 'TK' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    $stmt = $pdo->prepare("INSERT INTO tickets (ticket_number, customer_id, asset_id, title, description, priority, created_by) 
                          VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$ticket_number, $customer_id, $asset_id, $title, $description, $priority, $created_by]);
    
    $ticket_id = $pdo->lastInsertId();
    
    // ثبت در تاریخچه
    $stmt = $pdo->prepare("INSERT INTO ticket_history (ticket_id, action, performed_by, notes) 
                          VALUES (?, ?, ?, ?)");
    $stmt->execute([$ticket_id, 'ایجاد تیکت', $created_by, 'تیکت جدید ایجاد شد']);
    
    return $ticket_id;
}

?>