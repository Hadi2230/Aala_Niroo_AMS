<?php
// config.php - نسخه کامل و نهایی (یکپارچه و حرفه‌ای)

// شروع session اگر شروع نشده
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تنظیمات امنیتی
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php-errors.log');

// تنظیمات دیتابیس (با پورت 3306 برای XAMPP/MAMP معمول)
$host = 'localhost:3306';
$dbname = 'aala_niroo';
$username = 'root';
$password = '';

// تنظیمات زمانzone
date_default_timezone_set('Asia/Tehran');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_persian_ci"
    ]);
} catch (PDOException $e) {
    error_log("[" . date('Y-m-d H:i:s') . "] خطا در اتصال به دیتابیس: " . $e->getMessage());
    die("<div style='text-align: center; padding: 50px; font-family: Tahoma;'>
        <h2>خطا در اتصال به سیستم</h2>
        <p>لطفاً چند دقیقه دیگر تلاش کنید یا با پشتیبانی تماس بگیرید.</p>
        <p><small>خطای سیستمی: " . $e->getMessage() . "</small></p>
        </div>");
}

// تولید token برای جلوگیری از CSRF اگر وجود ندارد
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ایجاد جداول به صورت idempotent در هر درخواست (تا هر تغییر جدید اعمال شود)
createDatabaseTables($pdo);

/**
 * ایجاد ساختار دیتابیس و پوشه‌ها (Idempotent)
 */
function createDatabaseTables($pdo) {
    // ایجاد پوشه logs اگر وجود ندارد
    if (!is_dir(__DIR__ . '/logs')) {
        @mkdir(__DIR__ . '/logs', 0755, true);
    }

    // ایجاد پوشه uploads اگر وجود ندارد + .htaccess
    if (!is_dir(__DIR__ . '/uploads')) {
        @mkdir(__DIR__ . '/uploads', 0755, true);
        @mkdir(__DIR__ . '/uploads/installations', 0755, true);
        @mkdir(__DIR__ . '/uploads/assets', 0755, true);
        @mkdir(__DIR__ . '/uploads/filters', 0755, true);
        file_put_contents(
            __DIR__ . '/uploads/.htaccess',
            "Order deny,allow\nDeny from all\n<Files ~ \"\\.(jpg|jpeg|png|gif|webp|pdf|doc|docx|xls|xlsx|ppt|pptx|zip|mp4|webm|ogg)$\">\nAllow from all\n</Files>"
        );
    }

    // اطمینان از وجود دایرکتوری‌های ماژول آموزش
    $trainingDirs = [
        __DIR__ . '/uploads/training',
        __DIR__ . '/uploads/training/forms',
        __DIR__ . '/uploads/training/gallery',
        __DIR__ . '/uploads/training/gallery/thumbnails',
        __DIR__ . '/uploads/training/videos',
        __DIR__ . '/uploads/training/videos/thumbnails',
        __DIR__ . '/uploads/training/articles',
        __DIR__ . '/uploads/training/articles/images',
        __DIR__ . '/uploads/training/articles/pdfs',
    ];
    foreach ($trainingDirs as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        // تلاش برای تنظیم دسترسی نوشتن در محیط لوکال (بی‌خطر)
        @chmod($dir, 0775);
    }

    // دایرکتوری‌های ناوگان خودرو
    $fleetDirs = [
        __DIR__ . '/uploads/vehicles',
        __DIR__ . '/uploads/vehicles/docs',
        __DIR__ . '/uploads/vehicles/photos'
    ];
    foreach ($fleetDirs as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @chmod($dir, 0775);
    }

    // دایرکتوری‌های امور مالی
    $financeDirs = [
        __DIR__ . '/uploads/finance',
        __DIR__ . '/uploads/finance/receipts'
    ];
    foreach ($financeDirs as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @chmod($dir, 0775);
    }

    // دایرکتوری‌های قراردادها
    $contractsDirs = [
        __DIR__ . '/uploads/contracts',
        __DIR__ . '/uploads/contracts/versions',
        __DIR__ . '/uploads/contracts/files'
    ];
    foreach ($contractsDirs as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @chmod($dir, 0775);
    }

    // کوئری ساخت جداول (انواع سیستم + آموزش)
    $tables = [
        // انواع دارایی‌ها
        "CREATE TABLE IF NOT EXISTS asset_types (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL UNIQUE,
            display_name VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        // فیلدهای سفارشی دارایی
        "CREATE TABLE IF NOT EXISTS asset_fields (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type_id INT,
            field_name VARCHAR(100),
            field_type ENUM('text','number','date','select','file'),
            is_required BOOLEAN DEFAULT false,
            options TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (type_id) REFERENCES asset_types(id) ON DELETE CASCADE,
            INDEX idx_type_id (type_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        // دارایی‌ها (نسخه کامل)
        "CREATE TABLE IF NOT EXISTS assets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            type_id INT NOT NULL,
            serial_number VARCHAR(255) UNIQUE,
            purchase_date DATE,
            status ENUM('فعال','غیرفعال','در حال تعمیر','آماده بهره‌برداری') DEFAULT 'فعال',
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
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (type_id) REFERENCES asset_types(id) ON DELETE CASCADE,
            INDEX idx_type_id (type_id),
            INDEX idx_status (status),
            INDEX idx_serial (serial_number),
            INDEX idx_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        // تصاویر دارایی
        "CREATE TABLE IF NOT EXISTS asset_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            asset_id INT NOT NULL,
            field_name VARCHAR(100) NOT NULL,
            image_path VARCHAR(500) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
            INDEX idx_asset_id (asset_id),
            INDEX idx_field_name (field_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        // مشتریان (نسخه گسترده)
        "CREATE TABLE IF NOT EXISTS customers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(255) NOT NULL,
            phone VARCHAR(20) NOT NULL UNIQUE,
            company VARCHAR(255),
            customer_type ENUM('حقیقی','حقوقی') DEFAULT 'حقیقی',
            company_phone VARCHAR(20),
            responsible_phone VARCHAR(20),
            address TEXT,
            city VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_phone (phone),
            INDEX idx_company (company),
            INDEX idx_city (city),
            INDEX idx_customer_type (customer_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        // کاربران (با نقش‌های گسترده)
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(255),
            role ENUM('ادمین','کاربر عادی','اپراتور','مدیر عملیات','تکنسین','پشتیبانی') DEFAULT 'کاربر عادی',
            is_active BOOLEAN DEFAULT true,
            last_login TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_username (username),
            INDEX idx_role (role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        // انتساب‌ها
        "CREATE TABLE IF NOT EXISTS asset_assignments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            asset_id INT NOT NULL,
            customer_id INT NOT NULL,
            assignment_date DATE,
            notes TEXT,
            assignment_status ENUM('فعال','خاتمه یافته','موقت') DEFAULT 'فعال',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
            INDEX idx_asset_id (asset_id),
            INDEX idx_customer_id (customer_id),
            INDEX idx_assignment_date (assignment_date),
            INDEX idx_status (assignment_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        // جزئیات انتساب
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
            FOREIGN KEY (assignment_id) REFERENCES asset_assignments(id) ON DELETE CASCADE,
            INDEX idx_assignment_id (assignment_id),
            INDEX idx_installation_date (installation_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        // گزارشات لاگ سیستم
        "CREATE TABLE IF NOT EXISTS system_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            action VARCHAR(100) NOT NULL,
            description TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_action (action),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        // --- ماژول آموزش: دسته‌بندی‌ها، فرم‌ها، گالری، ویدیوها، مقالات، پیشرفت، نظرات ---
        "CREATE TABLE IF NOT EXISTS training_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(100) UNIQUE NOT NULL,
            description TEXT,
            icon VARCHAR(50),
            sort_order INT DEFAULT 0,
            is_active BOOLEAN DEFAULT true,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_slug (slug),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        "CREATE TABLE IF NOT EXISTS training_forms (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            file_path VARCHAR(500) NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_size BIGINT,
            file_type VARCHAR(100),
            category_id INT,
            download_count INT DEFAULT 0,
            uploaded_by INT,
            is_active BOOLEAN DEFAULT true,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES training_categories(id) ON DELETE SET NULL,
            FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_category (category_id),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        "CREATE TABLE IF NOT EXISTS training_gallery (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            image_path VARCHAR(500) NOT NULL,
            thumbnail_path VARCHAR(500),
            file_size BIGINT,
            width INT,
            height INT,
            category_id INT,
            view_count INT DEFAULT 0,
            download_count INT DEFAULT 0,
            uploaded_by INT,
            is_active BOOLEAN DEFAULT true,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES training_categories(id) ON DELETE SET NULL,
            FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_category (category_id),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        "CREATE TABLE IF NOT EXISTS training_videos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            video_path VARCHAR(500),
            video_url VARCHAR(500),
            thumbnail_path VARCHAR(500),
            duration INT,
            file_size BIGINT,
            video_type ENUM('upload','youtube','vimeo','aparat') DEFAULT 'upload',
            category_id INT,
            view_count INT DEFAULT 0,
            uploaded_by INT,
            is_featured BOOLEAN DEFAULT false,
            is_active BOOLEAN DEFAULT true,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES training_categories(id) ON DELETE SET NULL,
            FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_category (category_id),
            INDEX idx_featured (is_featured),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        "CREATE TABLE IF NOT EXISTS training_articles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) UNIQUE,
            summary TEXT,
            content LONGTEXT,
            featured_image VARCHAR(500),
            pdf_file VARCHAR(500),
            author VARCHAR(255),
            category_id INT,
            tags TEXT,
            view_count INT DEFAULT 0,
            download_count INT DEFAULT 0,
            reading_time INT,
            published_by INT,
            is_featured BOOLEAN DEFAULT false,
            is_active BOOLEAN DEFAULT true,
            published_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES training_categories(id) ON DELETE SET NULL,
            FOREIGN KEY (published_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_slug (slug),
            INDEX idx_category (category_id),
            INDEX idx_featured (is_featured),
            INDEX idx_active (is_active),
            INDEX idx_published (published_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        "CREATE TABLE IF NOT EXISTS training_progress (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            content_type ENUM('form','gallery','video','article') NOT NULL,
            content_id INT NOT NULL,
            progress_percent INT DEFAULT 0,
            is_completed BOOLEAN DEFAULT false,
            last_accessed TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_progress (user_id, content_type, content_id),
            INDEX idx_user (user_id),
            INDEX idx_content (content_type, content_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        "CREATE TABLE IF NOT EXISTS training_comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            content_type ENUM('form','gallery','video','article') NOT NULL,
            content_id INT NOT NULL,
            user_id INT NOT NULL,
            parent_id INT NULL,
            comment TEXT NOT NULL,
            is_approved BOOLEAN DEFAULT false,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (parent_id) REFERENCES training_comments(id) ON DELETE CASCADE,
            INDEX idx_content (content_type, content_id),
            INDEX idx_user (user_id),
            INDEX idx_approved (is_approved)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        // --- ماژول تیکت و نگهداری و اعلان‌ها و پیام‌ها ---
        "CREATE TABLE IF NOT EXISTS tickets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ticket_number VARCHAR(20) UNIQUE NOT NULL,
            customer_id INT NOT NULL,
            asset_id INT,
            title VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            priority ENUM('کم','متوسط','بالا','فوری') DEFAULT 'متوسط',
            status ENUM('جدید','در انتظار','در حال بررسی','در انتظار قطعه','تکمیل شده','لغو شده') DEFAULT 'جدید',
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        "CREATE TABLE IF NOT EXISTS maintenance_schedules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            asset_id INT NOT NULL,
            assignment_id INT,
            maintenance_type ENUM('تعمیر دوره‌ای','سرویس','بازرسی','کالیبراسیون') DEFAULT 'تعمیر دوره‌ای',
            schedule_date DATE NOT NULL,
            interval_days INT DEFAULT 90,
            status ENUM('برنامه‌ریزی شده','در انتظار','در حال انجام','تکمیل شده','لغو شده') DEFAULT 'برنامه‌ریزی شده',
            assigned_to INT,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            completed_at TIMESTAMP NULL,
            FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
            FOREIGN KEY (assignment_id) REFERENCES asset_assignments(id) ON DELETE SET NULL,
            FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_asset_id (asset_id),
            INDEX idx_schedule_date (schedule_date),
            INDEX idx_status (status),
            INDEX idx_assigned_to (assigned_to)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        "CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            type ENUM('تیکت','تعمیرات','سیستم','پیام') DEFAULT 'سیستم',
            priority ENUM('کم','متوسط','بالا','فوری') DEFAULT 'متوسط',
            is_read BOOLEAN DEFAULT false,
            related_id INT,
            related_type VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            read_at TIMESTAMP NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_is_read (is_read),
            INDEX idx_type (type),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        "CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sender_id INT NOT NULL,
            receiver_id INT NOT NULL,
            subject VARCHAR(255),
            message TEXT NOT NULL,
            is_read BOOLEAN DEFAULT false,
            related_ticket_id INT,
            related_maintenance_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            read_at TIMESTAMP NULL,
            FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (related_ticket_id) REFERENCES tickets(id) ON DELETE SET NULL,
            FOREIGN KEY (related_maintenance_id) REFERENCES maintenance_schedules(id) ON DELETE SET NULL,
            INDEX idx_sender_id (sender_id),
            INDEX idx_receiver_id (receiver_id),
            INDEX idx_is_read (is_read),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        "CREATE TABLE IF NOT EXISTS ticket_status_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ticket_id INT NOT NULL,
            old_status VARCHAR(50),
            new_status VARCHAR(50) NOT NULL,
            changed_by INT,
            change_reason TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
            FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_ticket_id (ticket_id),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        "CREATE TABLE IF NOT EXISTS notification_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            email_notifications BOOLEAN DEFAULT true,
            sms_notifications BOOLEAN DEFAULT false,
            in_app_notifications BOOLEAN DEFAULT true,
            ticket_notifications BOOLEAN DEFAULT true,
            maintenance_notifications BOOLEAN DEFAULT true,
            system_notifications BOOLEAN DEFAULT true,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_settings (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        "CREATE TABLE IF NOT EXISTS custom_roles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            role_name VARCHAR(100) NOT NULL,
            permissions TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_role (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        // --- ماژول نظرسنجی ---
        "CREATE TABLE IF NOT EXISTS surveys (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            is_active BOOLEAN DEFAULT true,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_is_active (is_active),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        "CREATE TABLE IF NOT EXISTS survey_questions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            survey_id INT NOT NULL,
            question_text TEXT NOT NULL,
            question_type ENUM('text','yes_no','rating','multiple_choice') DEFAULT 'text',
            is_required BOOLEAN DEFAULT true,
            options TEXT,
            order_index INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE,
            INDEX idx_survey_id (survey_id),
            INDEX idx_order (order_index)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        "CREATE TABLE IF NOT EXISTS survey_submissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            survey_id INT NOT NULL,
            customer_id INT NOT NULL,
            asset_id INT,
            started_by INT NOT NULL,
            status ENUM('در حال تکمیل','تکمیل شده','لغو شده') DEFAULT 'در حال تکمیل',
            sms_sent BOOLEAN DEFAULT false,
            sms_sent_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
            FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE SET NULL,
            FOREIGN KEY (started_by) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_survey_id (survey_id),
            INDEX idx_customer_id (customer_id),
            INDEX idx_asset_id (asset_id),
            INDEX idx_started_by (started_by),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        "CREATE TABLE IF NOT EXISTS sms_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            phone VARCHAR(20) NOT NULL,
            message TEXT NOT NULL,
            status ENUM('sent','delivered','failed') DEFAULT 'sent',
            message_id VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_phone (phone),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        // --- ماژول بازدید کارخانه (Visit Management) ---
        "CREATE TABLE IF NOT EXISTS visit_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            request_number VARCHAR(20) UNIQUE NOT NULL,
            company_name VARCHAR(255) NOT NULL,
            contact_person VARCHAR(255) NOT NULL,
            contact_phone VARCHAR(20) NOT NULL,
            contact_email VARCHAR(255),
            visitor_count INT DEFAULT 1,
            visit_purpose ENUM('دیداری','تست','خرید','بازرسی','آموزش','سایر') DEFAULT 'دیداری',
            visit_type ENUM('مشتری','ارگان','داخلی','تامین_کننده') DEFAULT 'مشتری',
            request_method ENUM('تماس','ایمیل','حضوری','آنلاین') DEFAULT 'تماس',
            preferred_dates JSON,
            confirmed_date DATETIME NULL,
            visit_duration INT DEFAULT 60,
            requires_nda BOOLEAN DEFAULT false,
            nda_signed BOOLEAN DEFAULT false,
            nda_signed_at TIMESTAMP NULL,
            special_requirements TEXT,
            status ENUM('new','documents_required','reviewed','scheduled','reserved','ready_for_visit','checked_in','onsite','completed','cancelled','archived') DEFAULT 'new',
            priority ENUM('کم','متوسط','بالا','فوری') DEFAULT 'متوسط',
            created_by INT NOT NULL,
            assigned_to INT NULL,
            host_id INT NULL,
            security_officer_id INT NULL,
            qr_code VARCHAR(100) NULL,
            check_in_time TIMESTAMP NULL,
            check_out_time TIMESTAMP NULL,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (host_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (security_officer_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_request_number (request_number),
            INDEX idx_company_name (company_name),
            INDEX idx_status (status),
            INDEX idx_priority (priority),
            INDEX idx_created_by (created_by),
            INDEX idx_assigned_to (assigned_to),
            INDEX idx_confirmed_date (confirmed_date),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        "CREATE TABLE IF NOT EXISTS visit_request_devices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            visit_request_id INT NOT NULL,
            asset_id INT NOT NULL,
            device_purpose TEXT,
            special_instructions TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (visit_request_id) REFERENCES visit_requests(id) ON DELETE CASCADE,
            FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
            INDEX idx_visit_request_id (visit_request_id),
            INDEX idx_asset_id (asset_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        "CREATE TABLE IF NOT EXISTS device_reservations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            visit_request_id INT NOT NULL,
            asset_id INT NOT NULL,
            reserved_from DATETIME NOT NULL,
            reserved_to DATETIME NOT NULL,
            status ENUM('reserved','in_use','completed','cancelled') DEFAULT 'reserved',
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (visit_request_id) REFERENCES visit_requests(id) ON DELETE CASCADE,
            FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_visit_request_id (visit_request_id),
            INDEX idx_asset_id (asset_id),
            INDEX idx_reserved_from (reserved_from),
            INDEX idx_reserved_to (reserved_to),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        "CREATE TABLE IF NOT EXISTS visit_documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            visit_request_id INT NOT NULL,
            document_type ENUM('company_registration','introduction_letter','permit','nda','id_copy','other') NOT NULL,
            document_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_size INT,
            mime_type VARCHAR(100),
            uploaded_by INT NOT NULL,
            is_verified BOOLEAN DEFAULT false,
            verified_by INT NULL,
            verified_at TIMESTAMP NULL,
            verification_notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (visit_request_id) REFERENCES visit_requests(id) ON DELETE CASCADE,
            FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_visit_request_id (visit_request_id),
            INDEX idx_document_type (document_type),
            INDEX idx_is_verified (is_verified)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        "CREATE TABLE IF NOT EXISTS visit_checklists (
            id INT AUTO_INCREMENT PRIMARY KEY,
            visit_request_id INT NOT NULL,
            checklist_type ENUM('pre_visit','onsite','post_visit') NOT NULL,
            item_name VARCHAR(255) NOT NULL,
            is_completed BOOLEAN DEFAULT false,
            completed_by INT NULL,
            completed_at TIMESTAMP NULL,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (visit_request_id) REFERENCES visit_requests(id) ON DELETE CASCADE,
            FOREIGN KEY (completed_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_visit_request_id (visit_request_id),
            INDEX idx_checklist_type (checklist_type),
            INDEX idx_is_completed (is_completed)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        "CREATE TABLE IF NOT EXISTS visit_photos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            visit_request_id INT NOT NULL,
            photo_type ENUM('check_in','onsite','equipment','visitor','signature','other') NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_size INT,
            mime_type VARCHAR(100),
            caption TEXT,
            taken_by INT NOT NULL,
            taken_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (visit_request_id) REFERENCES visit_requests(id) ON DELETE CASCADE,
            FOREIGN KEY (taken_by) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_visit_request_id (visit_request_id),
            INDEX idx_photo_type (photo_type),
            INDEX idx_taken_at (taken_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        "CREATE TABLE IF NOT EXISTS visit_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            visit_request_id INT NOT NULL,
            report_type ENUM('onsite','final','technical') NOT NULL,
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            equipment_tested JSON,
            visitor_feedback TEXT,
            recommendations TEXT,
            follow_up_required BOOLEAN DEFAULT false,
            follow_up_date DATE NULL,
            lead_created BOOLEAN DEFAULT false,
            lead_id INT NULL,
            pdf_path VARCHAR(500) NULL,
            signed_by_visitor BOOLEAN DEFAULT false,
            visitor_signature_path VARCHAR(500) NULL,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (visit_request_id) REFERENCES visit_requests(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_visit_request_id (visit_request_id),
            INDEX idx_report_type (report_type),
            INDEX idx_follow_up_required (follow_up_required),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        "CREATE TABLE IF NOT EXISTS visit_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            visit_request_id INT NOT NULL,
            action VARCHAR(100) NOT NULL,
            old_status VARCHAR(50) NULL,
            new_status VARCHAR(50) NULL,
            description TEXT,
            performed_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (visit_request_id) REFERENCES visit_requests(id) ON DELETE CASCADE,
            FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_visit_request_id (visit_request_id),
            INDEX idx_action (action),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        "CREATE TABLE IF NOT EXISTS visit_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT,
            description TEXT,
            updated_by INT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_setting_key (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        // --- ماژول امور مالی (پروژه‌ها و تراکنش‌ها) ---
        "CREATE TABLE IF NOT EXISTS finance_projects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_code VARCHAR(50) UNIQUE,
            name VARCHAR(255) NOT NULL,
            customer_id INT NULL,
            total_amount DECIMAL(18,2) DEFAULT 0,
            currency VARCHAR(10) DEFAULT 'IRR',
            prepayment_due DECIMAL(18,2) DEFAULT 0,
            midpayment_due DECIMAL(18,2) DEFAULT 0,
            settlement_due DECIMAL(18,2) DEFAULT 0,
            status ENUM('در حال انجام','تسویه شده','متوقف','لغو شده') DEFAULT 'در حال انجام',
            notes TEXT,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_code (project_code),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        "CREATE TABLE IF NOT EXISTS finance_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NOT NULL,
            tx_type ENUM('prepayment','midpayment','settlement','refund','adjustment') NOT NULL,
            amount DECIMAL(18,2) NOT NULL,
            tx_date DATE NOT NULL,
            method VARCHAR(50),
            reference_no VARCHAR(100),
            receipt_path VARCHAR(500),
            notes TEXT,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (project_id) REFERENCES finance_projects(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_project (project_id),
            INDEX idx_type_date (tx_type, tx_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        // --- ماژول قراردادها و نسخه‌ها و دسترسی‌ها ---
        "CREATE TABLE IF NOT EXISTS contracts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            contract_code VARCHAR(50) UNIQUE,
            customer_id INT NULL,
            project_id INT NULL,
            current_version INT DEFAULT 1,
            latest_version_id INT NULL,
            is_active BOOLEAN DEFAULT true,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
            FOREIGN KEY (project_id) REFERENCES finance_projects(id) ON DELETE SET NULL,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_title (title),
            INDEX idx_code (contract_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        "CREATE TABLE IF NOT EXISTS contract_versions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            contract_id INT NOT NULL,
            version INT NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_size BIGINT,
            mime_type VARCHAR(100),
            change_log TEXT,
            uploaded_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
            FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
            UNIQUE KEY uniq_contract_version (contract_id, version),
            INDEX idx_contract (contract_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        "CREATE TABLE IF NOT EXISTS contract_permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            contract_id INT NOT NULL,
            user_id INT NOT NULL,
            can_view BOOLEAN DEFAULT true,
            can_edit BOOLEAN DEFAULT false,
            can_delete BOOLEAN DEFAULT false,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY uniq_contract_user (contract_id, user_id),
            INDEX idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        // --- ماژول ناوگان خودرو (Fleet Management) ---
        "CREATE TABLE IF NOT EXISTS company_vehicles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(20) UNIQUE,
            license_plate VARCHAR(50) UNIQUE NOT NULL,
            vin VARCHAR(100),
            make VARCHAR(100),
            model VARCHAR(100),
            manufacture_year INT,
            color VARCHAR(50),
            fuel_type ENUM('gasoline','diesel','cng','hybrid','electric') DEFAULT 'gasoline',
            transmission ENUM('manual','automatic') DEFAULT 'automatic',
            seating_capacity INT DEFAULT 5,
            status ENUM('available','assigned','maintenance','inactive','reserved') DEFAULT 'available',
            current_odometer_km INT DEFAULT 0,
            insurance_expiry DATE NULL,
            inspection_expiry DATE NULL,
            registration_card_path VARCHAR(500) NULL,
            insurance_doc_path VARCHAR(500) NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_plate (license_plate),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        "CREATE TABLE IF NOT EXISTS vehicle_handovers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            vehicle_id INT NOT NULL,
            handover_to_user_id INT NULL,
            handover_to_name VARCHAR(255) NULL,
            handover_to_phone VARCHAR(20) NULL,
            handover_method ENUM('in_person','driver_pickup','delivery_service','other') DEFAULT 'in_person',
            handover_datetime DATETIME NOT NULL,
            expected_return_datetime DATETIME NULL,
            odometer_km_start INT NOT NULL,
            fuel_level_start_percent TINYINT DEFAULT 100,
            exterior_condition_start ENUM('excellent','good','fair','poor') DEFAULT 'good',
            interior_condition_start ENUM('excellent','good','fair','poor') DEFAULT 'good',
            notes TEXT,
            handover_photo_path VARCHAR(500) NULL,
            created_by INT,
            status ENUM('active','closed','cancelled') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (vehicle_id) REFERENCES company_vehicles(id) ON DELETE CASCADE,
            FOREIGN KEY (handover_to_user_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_vehicle_active (vehicle_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        "CREATE TABLE IF NOT EXISTS vehicle_returns (
            id INT AUTO_INCREMENT PRIMARY KEY,
            handover_id INT UNIQUE NOT NULL,
            return_datetime DATETIME NOT NULL,
            odometer_km_end INT NOT NULL,
            fuel_level_end_percent TINYINT DEFAULT 100,
            exterior_condition_end ENUM('excellent','good','fair','poor') DEFAULT 'good',
            interior_condition_end ENUM('excellent','good','fair','poor') DEFAULT 'good',
            damages TEXT,
            return_method ENUM('in_person','dropoff','towed','other') DEFAULT 'in_person',
            return_photo_path VARCHAR(500) NULL,
            additional_charges DECIMAL(12,2) DEFAULT 0,
            closed_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (handover_id) REFERENCES vehicle_handovers(id) ON DELETE CASCADE,
            FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        "CREATE TABLE IF NOT EXISTS vehicle_trips (
            id INT AUTO_INCREMENT PRIMARY KEY,
            handover_id INT NOT NULL,
            vehicle_id INT NOT NULL,
            driver_user_id INT NULL,
            purpose VARCHAR(255),
            origin VARCHAR(255),
            destination VARCHAR(255),
            start_time DATETIME NOT NULL,
            end_time DATETIME NULL,
            start_odometer_km INT NOT NULL,
            end_odometer_km INT NULL,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (handover_id) REFERENCES vehicle_handovers(id) ON DELETE CASCADE,
            FOREIGN KEY (vehicle_id) REFERENCES company_vehicles(id) ON DELETE CASCADE,
            FOREIGN KEY (driver_user_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_vehicle_time (vehicle_id, start_time)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        "CREATE TABLE IF NOT EXISTS vehicle_fuel_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            vehicle_id INT NOT NULL,
            handover_id INT NULL,
            log_date DATE NOT NULL,
            liters DECIMAL(10,2) NOT NULL,
            unit_price DECIMAL(10,2) DEFAULT 0,
            total_cost DECIMAL(12,2) DEFAULT 0,
            odometer_km INT,
            fuel_type ENUM('gasoline','diesel','cng') DEFAULT 'gasoline',
            station_name VARCHAR(255),
            receipt_path VARCHAR(500),
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (vehicle_id) REFERENCES company_vehicles(id) ON DELETE CASCADE,
            FOREIGN KEY (handover_id) REFERENCES vehicle_handovers(id) ON DELETE SET NULL,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_vehicle_date (vehicle_id, log_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        "CREATE TABLE IF NOT EXISTS vehicle_service_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            vehicle_id INT NOT NULL,
            service_date DATE NOT NULL,
            odometer_km INT,
            service_type ENUM('oil_change','tire_rotation','inspection','repair','other') DEFAULT 'inspection',
            description TEXT,
            cost DECIMAL(12,2) DEFAULT 0,
            invoice_path VARCHAR(500),
            performed_by_name VARCHAR(255),
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (vehicle_id) REFERENCES company_vehicles(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_vehicle_date (vehicle_id, service_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        "CREATE TABLE IF NOT EXISTS vehicle_incidents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            vehicle_id INT NOT NULL,
            handover_id INT NULL,
            incident_date DATE NOT NULL,
            severity ENUM('minor','major','critical') DEFAULT 'minor',
            description TEXT,
            reported_by VARCHAR(255),
            police_report_no VARCHAR(100),
            photo_path VARCHAR(500),
            cost_estimate DECIMAL(12,2) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (vehicle_id) REFERENCES company_vehicles(id) ON DELETE CASCADE,
            FOREIGN KEY (handover_id) REFERENCES vehicle_handovers(id) ON DELETE SET NULL,
            INDEX idx_vehicle_date (vehicle_id, incident_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci"
    ];

    foreach ($tables as $table) {
        try {
            $pdo->exec($table);
        } catch (PDOException $e) {
            error_log("[" . date('Y-m-d H:i:s') . "] خطا در ایجاد جدول: " . $e->getMessage());
        }
    }

    // درج داده‌های اولیه اگر وجود ندارند
    try {
        $check_types = $pdo->query("SELECT COUNT(*) as count FROM asset_types")->fetch();
        if (!empty($check_types) && (int)$check_types['count'] === 0) {
            $pdo->exec("INSERT INTO asset_types (name, display_name) VALUES 
                ('generator','ژنراتور'), ('power_motor','موتور برق'), ('consumable','اقلام مصرفی')");
            $pdo->exec("INSERT INTO users (username, password, full_name, role) VALUES
                ('admin','" . password_hash('admin', PASSWORD_DEFAULT) . "','مدیر سیستم','ادمین')");
        }
    } catch (PDOException $e) {
        error_log("[" . date('Y-m-d H:i:s') . "] خطا در درج داده اولیه: " . $e->getMessage());
    }

    // دسته‌بندی‌های آموزش
    try {
        $check_training_cats = $pdo->query("SELECT COUNT(*) as count FROM training_categories")->fetch();
        if (!empty($check_training_cats) && (int)$check_training_cats['count'] === 0) {
            $pdo->exec("INSERT INTO training_categories (name, slug, description, icon, sort_order) VALUES 
                ('فرم‌های شرکت','company-forms','فرم‌های رسمی و قراردادهای شرکت','fa-file-alt',1),
                ('تصاویر آموزشی','training-images','گالری تصاویر آموزشی و راهنما','fa-images',2),
                ('ویدیوهای آموزشی','training-videos','ویدیوهای آموزشی و دستورالعمل‌ها','fa-video',3),
                ('مقالات و نشریات','articles-publications','مقالات تخصصی و نشریات شرکت','fa-newspaper',4),
                ('راهنماها و مستندات','guides-docs','راهنماهای فنی و مستندات','fa-book',5),
                ('استانداردها','standards','استانداردها و مقررات صنعتی','fa-certificate',6)");
        }
    } catch (PDOException $e) {
        error_log("[" . date('Y-m-d H:i:s') . "] خطا در درج دسته‌بندی آموزشی: " . $e->getMessage());
    }

    // داده نمونه ناوگان خودرو
    try {
        $check_vehicles = $pdo->query("SELECT COUNT(*) as count FROM company_vehicles")->fetch();
        if (!empty($check_vehicles) && (int)$check_vehicles['count'] === 0) {
            $stmt = $pdo->prepare("INSERT INTO company_vehicles (code, license_plate, make, model, manufacture_year, color, fuel_type, transmission, seating_capacity, status, current_odometer_km, description)
                                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
            $seed = [
                ['VE' . date('Y') . '0001', 'IR11-123456', 'Peugeot', 'Pars', 2019, 'White', 'gasoline', 'manual', 5, 'available', 45000, 'خودرو سبک اداری'],
                ['VE' . date('Y') . '0002', 'IR22-654321', 'Samand', 'LX', 2018, 'Black', 'gasoline', 'manual', 5, 'available', 72000, 'خودرو ماموریتی'],
                ['VE' . date('Y') . '0003', 'IR99-112233', 'Renault', 'Tondar', 2017, 'Silver', 'gasoline', 'automatic', 5, 'maintenance', 98000, 'در حال سرویس دوره‌ای']
            ];
            foreach ($seed as $v) { $stmt->execute($v); }
        }
    } catch (PDOException $e) {
        error_log("[" . date('Y-m-d H:i:s') . "] خطا در درج داده ناوگان: " . $e->getMessage());
    }
}

/**
 * توابع کمکی اصلی و حرفه‌ای
 */

if (!function_exists('sanitizeInput')) {
    function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map('sanitizeInput', $data);
        }
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
}

function validatePhone($phone) {
    return preg_match('/^[0-9]{10,11}$/', (string)$phone);
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function redirect($url, $statusCode = 303) {
    header('Location: ' . $url, true, $statusCode);
    exit();
}

function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

function logAction($pdo, $action, $description = '') {
    $user_id = $_SESSION['user_id'] ?? null;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $stmt = $pdo->prepare("INSERT INTO system_logs (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $action, $description, $ip_address, $user_agent]);
}

function log_action($action, $description = '') {
    global $pdo;
    logAction($pdo, $action, $description);
}

// بررسی دسترسی قرارداد
function contractUserCan($pdo, $contract_id, $capability = 'view') {
    $user_id = $_SESSION['user_id'] ?? 0;
    if (!$user_id) return false;
    if (($_SESSION['role'] ?? '') === 'ادمین') return true;
    $stmt = $pdo->prepare("SELECT can_view, can_edit, can_delete FROM contract_permissions WHERE contract_id = ? AND user_id = ?");
    $stmt->execute([$contract_id, $user_id]);
    $row = $stmt->fetch();
    if (!$row) return false;
    if ($capability === 'view') return (bool)$row['can_view'];
    if ($capability === 'edit') return (bool)$row['can_edit'];
    if ($capability === 'delete') return (bool)$row['can_delete'];
    return false;
}

function uploadFile($file, $target_dir, $allowed_types = ['jpg','jpeg','png','gif','webp','pdf','doc','docx','xls','xlsx','ppt','pptx','zip','mp4','webm','ogg'], $max_size_bytes = 50 * 1024 * 1024) {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('خطا در آپلود فایل');
    }
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_ext, $allowed_types)) {
        throw new Exception('نوع فایل مجاز نیست');
    }
    if ($file['size'] > $max_size_bytes) {
        throw new Exception('حجم فایل بیش از حد مجاز است');
    }
    if (!is_dir($target_dir)) {
        @mkdir($target_dir, 0755, true);
    }
    if (substr($target_dir, -1) !== '/' && substr($target_dir, -1) !== '\\') {
        $target_dir .= '/';
    }
    $file_name = time() . '_' . bin2hex(random_bytes(6)) . '.' . $file_ext;
    $target_file = $target_dir . $file_name;
    if (!move_uploaded_file($file['tmp_name'], $target_file)) {
        throw new Exception('خطا در ذخیره فایل');
    }
    return $target_file;
}

if (!function_exists('verifyCsrfToken')) {
    function verifyCsrfToken($token = null) {
        $token = $token ?? ($_POST['csrf_token'] ?? '');
        if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
            die('درخواست نامعتبر است - CSRF Token validation failed');
        }
    }
}

function checkPermission($required_role = 'کاربر عادی') {
    if (!isset($_SESSION['user_id'])) {
        redirect('login.php');
    }
    $roles = ['کاربر عادی' => 1, 'اپراتور' => 2, 'ادمین' => 3, 'مدیر عملیات' => 4, 'تکنسین' => 2, 'پشتیبانی' => 2];
    $current = $_SESSION['role'] ?? 'کاربر عادی';
    if (isset($roles[$current]) && isset($roles[$required_role])) {
        if ($roles[$current] < $roles[$required_role]) {
            die('دسترسی غیرمجاز - شما مجوز دسترسی به این بخش را ندارید');
        }
    }
}

function require_auth($required_role = 'کاربر عادی') {
    checkPermission($required_role);
}

if (!function_exists('csrf_field')) {
    function csrf_field() {
        return '<input type="hidden" name="csrf_token" value="' . ($_SESSION['csrf_token'] ?? '') . '">';
    }
}

function jalaliDate($date = null) {
    if (!$date) $date = time();
    $date = is_numeric($date) ? $date : strtotime($date);
    $year = date('Y', $date);
    $month = date('m', $date);
    $day = date('d', $date);
    $jalali = gregorian_to_jalali($year, $month, $day);
    return $jalali[0] . '/' . $jalali[1] . '/' . $jalali[2];
}

function en2fa_digits($input) {
    $en = ['0','1','2','3','4','5','6','7','8','9'];
    $fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    return str_replace($en, $fa, (string)$input);
}

function gregorian_to_jalali($gy, $gm, $gd) {
    $g_d_m = [0,31,59,90,120,151,181,212,243,273,304,334];
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + intval(($gy2 + 3) / 4) - intval(($gy2 + 99) / 100) + intval(($gy2 + 399) / 400) + $gd + $g_d_m[$gm - 1];
    $jy = -1595 + (33 * intval($days / 12053));
    $days %= 12053;
    $jy += 4 * intval($days / 1461);
    $days %= 1461;
    if ($days > 365) {
        $jy += intval(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    if ($days < 186) {
        $jm = 1 + intval($days / 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + intval(($days - 186) / 30);
        $jd = 1 + (($days - 186) % 30);
    }
    return [$jy, $jm, $jd];
}

function jalali_format($datetime, $format = 'Y/m/d H:i', $use_fa_digits = true) {
    if (!$datetime || in_array($datetime, ['0000-00-00', '0000-00-00 00:00:00'])) return '';
    $ts = is_numeric($datetime) ? intval($datetime) : strtotime($datetime);
    if ($ts === false) return $datetime;
    $gy = intval(date('Y', $ts));
    $gm = intval(date('n', $ts));
    $gd = intval(date('j', $ts));
    list($jy, $jm, $jd) = gregorian_to_jalali($gy, $gm, $gd);
    $H = date('H', $ts); $i = date('i', $ts); $s = date('s', $ts);
    $map = [
        'Y' => $jy,
        'm' => sprintf('%02d', $jm),
        'n' => $jm,
        'd' => sprintf('%02d', $jd),
        'j' => $jd,
        'H' => $H,
        'i' => $i,
        's' => $s
    ];
    $result = preg_replace_callback('/Y|m|n|d|j|H|i|s/', function($m) use ($map) { return $map[$m[0]] ?? $m[0]; }, $format);
    return $use_fa_digits ? en2fa_digits($result) : $result;
}

function hasPermission($permission) {
    if (!isset($_SESSION['user_id'])) return false;
    $user_role = $_SESSION['role'] ?? 'کاربر عادی';
    if ($user_role === 'ادمین') return true;
    $permissions = [
        'مدیر عملیات' => ['tickets.*', 'maintenance.*', 'customers.*', 'reports.*'],
        'تکنسین'      => ['tickets.view', 'tickets.edit', 'maintenance.*'],
        'پشتیبانی'    => ['tickets.*', 'customers.*'],
        'کاربر عادی'  => ['tickets.view']
    ];
    if (!isset($permissions[$user_role])) return false;
    $user_permissions = $permissions[$user_role];
    foreach ($user_permissions as $perm) {
        if ($perm === '*') return true;
        if (strpos($perm, '*') !== false) {
            $base = str_replace('.*', '', $perm);
            if (strpos($permission, $base) === 0) return true;
        }
        if ($perm === $permission) return true;
    }
    return false;
}

function generateRandomCode($length = 8) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[random_int(0, strlen($characters) - 1)];
    }
    return $code;
}

function generateSerialNumber($prefix = 'SN') {
    return $prefix . date('Y') . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
}

function formatPhoneNumber($phone) {
    $phone = preg_replace('/[^0-9]/', '', (string)$phone);
    if (strlen($phone) === 11 && substr($phone, 0, 1) === '0') {
        return substr($phone, 0, 4) . '-' . substr($phone, 4, 3) . '-' . substr($phone, 7, 4);
    }
    if (strlen($phone) === 10) {
        return '0' . substr($phone, 0, 3) . '-' . substr($phone, 3, 3) . '-' . substr($phone, 6, 4);
    }
    return $phone;
}

function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max((int)$bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1024 ** $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

function getRealUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return $_SERVER['HTTP_X_FORWARDED_FOR'];
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

function isMobile() {
    return preg_match('/(android|iphone|ipad|mobile)/i', $_SERVER['HTTP_USER_AGENT'] ?? '') === 1;
}

function generateQRCode($text, $size = 200) {
    return "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data=" . urlencode($text);
}

function cleanOldFiles($directory, $days = 30) {
    $files = glob(rtrim($directory, '/\\') . '/*');
    $now = time();
    foreach ($files as $file) {
        if (is_file($file) && ($now - filemtime($file)) > ($days * 24 * 60 * 60)) {
            @unlink($file);
        }
    }
}

/**
 * توابع مدیریت بازدید کارخانه (Visit Management)
 */

function generateVisitRequestNumber($pdo) {
    $year = date('Y');
    $prefix = "VR" . $year;
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM visit_requests WHERE request_number LIKE ?");
    $stmt->execute([$prefix . "%"]);
    $count = ((int)$stmt->fetch()['count']) + 1;
    return $prefix . str_pad((string)$count, 4, '0', STR_PAD_LEFT);
}

function createVisitRequest($pdo, $data) {
    $request_number = generateVisitRequestNumber($pdo);
    $stmt = $pdo->prepare("INSERT INTO visit_requests (
        request_number, company_name, contact_person, contact_phone, contact_email,
        visitor_count, visit_purpose, visit_type, request_method, preferred_dates,
        visit_duration, requires_nda, special_requirements, status, priority, created_by
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $request_number,
        $data['company_name'],
        $data['contact_person'],
        $data['contact_phone'],
        $data['contact_email'] ?? null,
        $data['visitor_count'] ?? 1,
        $data['visit_purpose'] ?? 'دیداری',
        $data['visit_type'] ?? 'مشتری',
        $data['request_method'] ?? 'تماس',
        json_encode($data['preferred_dates'] ?? []),
        $data['visit_duration'] ?? 60,
        $data['requires_nda'] ?? false,
        $data['special_requirements'] ?? null,
        'new',
        $data['priority'] ?? 'متوسط',
        $_SESSION['user_id']
    ]);
    $visit_request_id = $pdo->lastInsertId();
    logVisitAction($pdo, $visit_request_id, 'created', 'درخواست بازدید ایجاد شد');
    return $visit_request_id;
}

function logVisitAction($pdo, $visit_request_id, $action, $description = '', $old_status = null, $new_status = null) {
    $stmt = $pdo->prepare("INSERT INTO visit_history (visit_request_id, action, description, old_status, new_status, performed_by) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$visit_request_id, $action, $description, $old_status, $new_status, $_SESSION['user_id']]);
}

function updateVisitStatus($pdo, $visit_request_id, $new_status, $notes = '') {
    $stmt = $pdo->prepare("SELECT status FROM visit_requests WHERE id = ?");
    $stmt->execute([$visit_request_id]);
    $visit = $stmt->fetch();
    if (!$visit) return false;
    $old_status = $visit['status'];
    $stmt = $pdo->prepare("UPDATE visit_requests SET status = ?, notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$new_status, $notes, $visit_request_id]);
    logVisitAction($pdo, $visit_request_id, 'status_changed', $notes, $old_status, $new_status);
    return true;
}

function reserveDeviceForVisit($pdo, $visit_request_id, $asset_id, $reserved_from, $reserved_to) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM device_reservations WHERE asset_id = ? AND status IN ('reserved','in_use') AND ((reserved_from <= ? AND reserved_to >= ?) OR (reserved_from <= ? AND reserved_to >= ?))");
    $stmt->execute([$asset_id, $reserved_from, $reserved_from, $reserved_to, $reserved_to]);
    if ((int)$stmt->fetch()['count'] > 0) {
        throw new Exception('دستگاه در این بازه زمانی قبلاً رزرو شده است');
    }
    $stmt = $pdo->prepare("INSERT INTO device_reservations (visit_request_id, asset_id, reserved_from, reserved_to, created_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$visit_request_id, $asset_id, $reserved_from, $reserved_to, $_SESSION['user_id']]);
    logVisitAction($pdo, $visit_request_id, 'device_reserved', 'دستگاه رزرو شد');
    return $pdo->lastInsertId();
}

function uploadVisitDocument($pdo, $visit_request_id, $file, $document_type, $document_name) {
    $upload_dir = __DIR__ . '/uploads/visit_documents/';
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0755, true);
    }
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_types = ['pdf','jpg','jpeg','png','doc','docx'];
    if (!in_array($file_ext, $allowed_types)) {
        throw new Exception('نوع فایل مجاز نیست');
    }
    if ($file['size'] > (10 * 1024 * 1024)) {
        throw new Exception('حجم فایل بیش از حد مجاز است');
    }
    $file_name = time() . '_' . bin2hex(random_bytes(6)) . '.' . $file_ext;
    $target_file = $upload_dir . $file_name;
    if (!move_uploaded_file($file['tmp_name'], $target_file)) {
        throw new Exception('خطا در ذخیره فایل');
    }
    $stmt = $pdo->prepare("INSERT INTO visit_documents (visit_request_id, document_type, document_name, file_path, file_size, mime_type, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$visit_request_id, $document_type, $document_name, $target_file, $file['size'], $file['type'], $_SESSION['user_id']]);
    logVisitAction($pdo, $visit_request_id, 'document_uploaded', "مدرک آپلود شد: $document_name");
    return $pdo->lastInsertId();
}

function verifyVisitDocument($pdo, $document_id, $is_verified, $verification_notes = '') {
    $stmt = $pdo->prepare("UPDATE visit_documents SET is_verified = ?, verified_by = ?, verified_at = CURRENT_TIMESTAMP, verification_notes = ? WHERE id = ?");
    $stmt->execute([$is_verified, $_SESSION['user_id'], $verification_notes, $document_id]);
    $stmt = $pdo->prepare("SELECT vr.id FROM visit_documents vd JOIN visit_requests vr ON vd.visit_request_id = vr.id WHERE vd.id = ?");
    $stmt->execute([$document_id]);
    $visit_request_id = $stmt->fetch()['id'] ?? null;
    $action = $is_verified ? 'document_verified' : 'document_rejected';
    $description = $is_verified ? 'مدرک تایید شد' : 'مدرک رد شد';
    if ($visit_request_id) logVisitAction($pdo, $visit_request_id, $action, $description);
    return true;
}

function createVisitChecklist($pdo, $visit_request_id, $checklist_type, $items) {
    foreach ((array)$items as $item) {
        $stmt = $pdo->prepare("INSERT INTO visit_checklists (visit_request_id, checklist_type, item_name) VALUES (?, ?, ?)");
        $stmt->execute([$visit_request_id, $checklist_type, $item]);
    }
    logVisitAction($pdo, $visit_request_id, 'checklist_created', "چک‌لیست $checklist_type ایجاد شد");
    return true;
}

function completeChecklistItem($pdo, $checklist_id, $notes = '') {
    $stmt = $pdo->prepare("UPDATE visit_checklists SET is_completed = true, completed_by = ?, completed_at = CURRENT_TIMESTAMP, notes = ? WHERE id = ?");
    $stmt->execute([$_SESSION['user_id'], $notes, $checklist_id]);
    $stmt = $pdo->prepare("SELECT vr.id FROM visit_checklists vc JOIN visit_requests vr ON vc.visit_request_id = vr.id WHERE vc.id = ?");
    $stmt->execute([$checklist_id]);
    $visit_request_id = $stmt->fetch()['id'] ?? null;
    if ($visit_request_id) logVisitAction($pdo, $visit_request_id, 'checklist_item_completed', 'آیتم چک‌لیست تکمیل شد');
    return true;
}

function uploadVisitPhoto($pdo, $visit_request_id, $file, $photo_type, $caption = '') {
    $upload_dir = __DIR__ . '/uploads/visit_photos/';
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0755, true);
    }
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_types = ['jpg','jpeg','png'];
    if (!in_array($file_ext, $allowed_types)) {
        throw new Exception('نوع فایل مجاز نیست');
    }
    if ($file['size'] > (5 * 1024 * 1024)) {
        throw new Exception('حجم فایل بیش از حد مجاز است');
    }
    $file_name = time() . '_' . bin2hex(random_bytes(6)) . '.' . $file_ext;
    $target_file = $upload_dir . $file_name;
    if (!move_uploaded_file($file['tmp_name'], $target_file)) {
        throw new Exception('خطا در ذخیره فایل');
    }
    $stmt = $pdo->prepare("INSERT INTO visit_photos (visit_request_id, photo_type, file_path, file_size, mime_type, caption, taken_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$visit_request_id, $photo_type, $target_file, $file['size'], $file['type'], $caption, $_SESSION['user_id']]);
    logVisitAction($pdo, $visit_request_id, 'photo_uploaded', "عکس آپلود شد: $photo_type");
    return $pdo->lastInsertId();
}

function createVisitReport($pdo, $visit_request_id, $report_type, $data) {
    $stmt = $pdo->prepare("INSERT INTO visit_reports (visit_request_id, report_type, title, content, equipment_tested, visitor_feedback, recommendations, follow_up_required, follow_up_date, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $visit_request_id,
        $report_type,
        $data['title'],
        $data['content'],
        json_encode($data['equipment_tested'] ?? []),
        $data['visitor_feedback'] ?? null,
        $data['recommendations'] ?? null,
        $data['follow_up_required'] ?? false,
        $data['follow_up_date'] ?? null,
        $_SESSION['user_id']
    ]);
    logVisitAction($pdo, $visit_request_id, 'report_created', "گزارش $report_type ایجاد شد");
    return $pdo->lastInsertId();
}

function checkInVisit($pdo, $visit_request_id, $qr_code = null) {
    $stmt = $pdo->prepare("UPDATE visit_requests SET check_in_time = CURRENT_TIMESTAMP, status = 'checked_in' WHERE id = ? AND (qr_code = ? OR ? IS NULL)");
    $stmt->execute([$visit_request_id, $qr_code, $qr_code]);
    if ($stmt->rowCount() > 0) {
        logVisitAction($pdo, $visit_request_id, 'checked_in', 'بازدیدکننده وارد شد');
        return true;
    }
    return false;
}

function checkOutVisit($pdo, $visit_request_id) {
    $stmt = $pdo->prepare("UPDATE visit_requests SET check_out_time = CURRENT_TIMESTAMP, status = 'completed' WHERE id = ?");
    $stmt->execute([$visit_request_id]);
    if ($stmt->rowCount() > 0) {
        logVisitAction($pdo, $visit_request_id, 'checked_out', 'بازدیدکننده خارج شد');
        return true;
    }
    return false;
}

function generateVisitQRCode($pdo, $visit_request_id) {
    $qr_code = 'VR' . $visit_request_id . '_' . time();
    $stmt = $pdo->prepare("UPDATE visit_requests SET qr_code = ? WHERE id = ?");
    $stmt->execute([$qr_code, $visit_request_id]);
    return $qr_code;
}

function getVisitStatistics($pdo, $date_from = null, $date_to = null) {
    $where_clause = '';
    $params = [];
    if ($date_from && $date_to) { $where_clause = 'WHERE created_at BETWEEN ? AND ?'; $params = [$date_from, $date_to]; }
    $stats = [];
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM visit_requests $where_clause");
    $stmt->execute($params); $stats['total_requests'] = $stmt->fetch()['total'] ?? 0;
    $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM visit_requests $where_clause GROUP BY status");
    $stmt->execute($params); $stats['by_status'] = $stmt->fetchAll();
    $stmt = $pdo->prepare("SELECT visit_type, COUNT(*) as count FROM visit_requests $where_clause GROUP BY visit_type");
    $stmt->execute($params); $stats['by_type'] = $stmt->fetchAll();
    $stmt = $pdo->prepare("SELECT visit_purpose, COUNT(*) as count FROM visit_requests $where_clause GROUP BY visit_purpose");
    $stmt->execute($params); $stats['by_purpose'] = $stmt->fetchAll();
    return $stats;
}

function getAvailableDevices($pdo, $date_from, $date_to) {
    $stmt = $pdo->prepare("SELECT a.*, at.display_name as type_name FROM assets a LEFT JOIN asset_types at ON a.type_id = at.id WHERE a.status = 'فعال' AND a.id NOT IN (SELECT asset_id FROM device_reservations WHERE status IN ('reserved','in_use') AND ((reserved_from <= ? AND reserved_to >= ?) OR (reserved_from <= ? AND reserved_to >= ?))) ORDER BY a.name");
    $stmt->execute([$date_from, $date_from, $date_to, $date_to]);
    return $stmt->fetchAll();
}

function getVisitRequests($pdo, $filters = []) {
    $where_conditions = [];
    $params = [];
    if (!empty($filters['status'])) { $where_conditions[] = "vr.status = ?"; $params[] = $filters['status']; }
    if (!empty($filters['visit_type'])) { $where_conditions[] = "vr.visit_type = ?"; $params[] = $filters['visit_type']; }
    if (!empty($filters['date_from'])) { $where_conditions[] = "vr.created_at >= ?"; $params[] = $filters['date_from']; }
    if (!empty($filters['date_to'])) { $where_conditions[] = "vr.created_at <= ?"; $params[] = $filters['date_to']; }
    if (!empty($filters['company_name'])) { $where_conditions[] = "vr.company_name LIKE ?"; $params[] = '%' . $filters['company_name'] . '%'; }
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    $stmt = $pdo->prepare("SELECT vr.*, u1.full_name as created_by_name, u2.full_name as assigned_to_name, u3.full_name as host_name FROM visit_requests vr LEFT JOIN users u1 ON vr.created_by = u1.id LEFT JOIN users u2 ON vr.assigned_to = u2.id LEFT JOIN users u3 ON vr.host_id = u3.id $where_clause ORDER BY vr.created_at DESC");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * مدیریت ناوگان خودرو (Fleet Management) - توابع کمکی
 */

function generateVehicleCode($pdo) {
    $year = date('Y');
    $prefix = 'VE' . $year;
    $stmt = $pdo->prepare("SELECT COUNT(*) as c FROM company_vehicles WHERE code LIKE ?");
    $stmt->execute([$prefix . '%']);
    $count = ((int)($stmt->fetch()['c'] ?? 0)) + 1;
    return $prefix . str_pad((string)$count, 4, '0', STR_PAD_LEFT);
}

function ensureVehicleAvailable($pdo, $vehicle_id) {
    $stmt = $pdo->prepare("SELECT status FROM company_vehicles WHERE id = ?");
    $stmt->execute([$vehicle_id]);
    $status = $stmt->fetch()['status'] ?? null;
    if (!$status) throw new Exception('خودرو یافت نشد');
    if (!in_array($status, ['available','reserved'])) throw new Exception('خودرو در دسترس نیست');
}

function createVehicleHandover($pdo, $data) {
    verifyCsrfToken($data['csrf_token'] ?? null);
    $vehicle_id = (int)$data['vehicle_id'];
    ensureVehicleAvailable($pdo, $vehicle_id);

    // جلوگیری از تحویل همزمان
    $stmt = $pdo->prepare("SELECT COUNT(*) as c FROM vehicle_handovers WHERE vehicle_id = ? AND status = 'active'");
    $stmt->execute([$vehicle_id]);
    if ((int)$stmt->fetch()['c'] > 0) throw new Exception('برای این خودرو تحویل فعال وجود دارد');

    // دریافت کیلومتر کنونی خودرو
    $stmt = $pdo->prepare("SELECT current_odometer_km FROM company_vehicles WHERE id = ?");
    $stmt->execute([$vehicle_id]);
    $current_odo = (int)($stmt->fetch()['current_odometer_km'] ?? 0);

    $odometer_km_start = (int)($data['odometer_km_start'] ?? 0);
    if ($odometer_km_start < $current_odo) throw new Exception('کیلومتر شروع نمی‌تواند کمتر از کیلومتر فعلی باشد');

    $handover_to_user_id = !empty($data['handover_to_user_id']) ? (int)$data['handover_to_user_id'] : null;
    $handover_to_name = sanitizeInput($data['handover_to_name'] ?? '');
    $handover_to_phone = sanitizeInput($data['handover_to_phone'] ?? '');
    $handover_method = sanitizeInput($data['handover_method'] ?? 'in_person');
    $handover_datetime = sanitizeInput($data['handover_datetime'] ?? date('Y-m-d H:i:s'));
    $expected_return_datetime = !empty($data['expected_return_datetime']) ? sanitizeInput($data['expected_return_datetime']) : null;
    $fuel_level_start_percent = max(0, min(100, (int)($data['fuel_level_start_percent'] ?? 100)));
    $exterior_condition_start = sanitizeInput($data['exterior_condition_start'] ?? 'good');
    $interior_condition_start = sanitizeInput($data['interior_condition_start'] ?? 'good');
    $notes = sanitizeInput($data['notes'] ?? '');

    $handover_photo_path = null;
    if (!empty($_FILES['handover_photo']['name'] ?? '')) {
        $handover_photo_path = uploadFile($_FILES['handover_photo'], __DIR__ . '/uploads/vehicles/photos');
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO vehicle_handovers (
            vehicle_id, handover_to_user_id, handover_to_name, handover_to_phone,
            handover_method, handover_datetime, expected_return_datetime,
            odometer_km_start, fuel_level_start_percent, exterior_condition_start,
            interior_condition_start, notes, handover_photo_path, created_by, status
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?, ?, 'active')");
        $stmt->execute([
            $vehicle_id, $handover_to_user_id, $handover_to_name ?: null, $handover_to_phone ?: null,
            $handover_method, $handover_datetime, $expected_return_datetime,
            $odometer_km_start, $fuel_level_start_percent, $exterior_condition_start,
            $interior_condition_start, $notes ?: null, $handover_photo_path ?: null,
            ($_SESSION['user_id'] ?? null)
        ]);
        $handover_id = (int)$pdo->lastInsertId();

        // بروزرسانی وضعیت و کیلومتر خودرو
        $stmt = $pdo->prepare("UPDATE company_vehicles SET status = 'assigned', current_odometer_km = GREATEST(current_odometer_km, ?) WHERE id = ?");
        $stmt->execute([$odometer_km_start, $vehicle_id]);

        logAction($pdo, 'FLEET_HANDOVER_CREATE', 'handover_id=' . $handover_id);
        $pdo->commit();
        return $handover_id;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function completeVehicleReturn($pdo, $handover_id, $data) {
    verifyCsrfToken($data['csrf_token'] ?? null);
    $handover_id = (int)$handover_id;
    $stmt = $pdo->prepare("SELECT vh.*, cv.current_odometer_km FROM vehicle_handovers vh JOIN company_vehicles cv ON vh.vehicle_id = cv.id WHERE vh.id = ?");
    $stmt->execute([$handover_id]);
    $handover = $stmt->fetch();
    if (!$handover) throw new Exception('تحویل یافت نشد');
    if ($handover['status'] !== 'active') throw new Exception('این تحویل فعال نیست');

    $odometer_km_end = (int)($data['odometer_km_end'] ?? 0);
    if ($odometer_km_end < (int)$handover['odometer_km_start']) throw new Exception('کیلومتر پایان کمتر از شروع است');

    $fuel_level_end_percent = max(0, min(100, (int)($data['fuel_level_end_percent'] ?? 100)));
    $exterior_condition_end = sanitizeInput($data['exterior_condition_end'] ?? 'good');
    $interior_condition_end = sanitizeInput($data['interior_condition_end'] ?? 'good');
    $damages = sanitizeInput($data['damages'] ?? '');
    $return_method = sanitizeInput($data['return_method'] ?? 'in_person');
    $additional_charges = (float)($data['additional_charges'] ?? 0);
    $return_datetime = sanitizeInput($data['return_datetime'] ?? date('Y-m-d H:i:s'));

    $return_photo_path = null;
    if (!empty($_FILES['return_photo']['name'] ?? '')) {
        $return_photo_path = uploadFile($_FILES['return_photo'], __DIR__ . '/uploads/vehicles/photos');
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO vehicle_returns (
            handover_id, return_datetime, odometer_km_end, fuel_level_end_percent,
            exterior_condition_end, interior_condition_end, damages, return_method,
            return_photo_path, additional_charges, closed_by
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $handover_id, $return_datetime, $odometer_km_end, $fuel_level_end_percent,
            $exterior_condition_end, $interior_condition_end, $damages ?: null, $return_method,
            $return_photo_path ?: null, $additional_charges, ($_SESSION['user_id'] ?? null)
        ]);

        $stmt = $pdo->prepare("UPDATE vehicle_handovers SET status = 'closed', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$handover_id]);

        $stmt = $pdo->prepare("UPDATE company_vehicles SET status = 'available', current_odometer_km = GREATEST(current_odometer_km, ?) WHERE id = ?");
        $stmt->execute([$odometer_km_end, (int)$handover['vehicle_id']]);

        logAction($pdo, 'FLEET_RETURN_COMPLETE', 'handover_id=' . $handover_id . ',km=' . $odometer_km_end);
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function getFleetStats($pdo, $date_from = null, $date_to = null) {
    $stats = [];
    $where = '';
    $params = [];
    if ($date_from && $date_to) { $where = 'WHERE vr.return_datetime BETWEEN ? AND ?'; $params = [$date_from, $date_to]; }

    // کیلومتر طی شده بر اساس عودت‌ها
    $stmt = $pdo->prepare("SELECT vh.vehicle_id, SUM(vr.odometer_km_end - vh.odometer_km_start) AS km_driven
                           FROM vehicle_returns vr JOIN vehicle_handovers vh ON vr.handover_id = vh.id
                           $where GROUP BY vh.vehicle_id");
    $stmt->execute($params);
    $stats['km_by_vehicle'] = $stmt->fetchAll();

    // سوخت مصرفی
    $where2 = '';
    $params2 = [];
    if ($date_from && $date_to) { $where2 = 'WHERE log_date BETWEEN ? AND ?'; $params2 = [$date_from, $date_to]; }
    $stmt = $pdo->prepare("SELECT vehicle_id, SUM(liters) as liters, SUM(total_cost) as cost FROM vehicle_fuel_logs $where2 GROUP BY vehicle_id");
    $stmt->execute($params2);
    $stats['fuel_by_vehicle'] = $stmt->fetchAll();

    // تحویل‌های باز
    $stmt = $pdo->query("SELECT vh.*, cv.license_plate, cv.make, cv.model FROM vehicle_handovers vh JOIN company_vehicles cv ON vh.vehicle_id = cv.id WHERE vh.status = 'active' ORDER BY vh.handover_datetime DESC");
    $stats['open_handovers'] = $stmt->fetchAll();

    // سررسید بیمه/معاینه
    $stmt = $pdo->query("SELECT * FROM company_vehicles WHERE (insurance_expiry IS NOT NULL AND insurance_expiry <= DATE_ADD(CURDATE(), INTERVAL 30 DAY))
                        OR (inspection_expiry IS NOT NULL AND inspection_expiry <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)) ORDER BY insurance_expiry, inspection_expiry");
    $stats['upcoming_expiries'] = $stmt->fetchAll();
    return $stats;
}

?>