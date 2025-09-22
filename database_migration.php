<?php
/**
 * database_migration.php - سیستم بروزرسانی دیتابیس
 * برای بروزرسانی ساختار دیتابیس بدون از دست دادن داده‌ها
 */

require_once 'config.php';

// جدول version control
$version_table = "CREATE TABLE IF NOT EXISTS schema_versions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    version VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    rollback_sql TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci";

try {
    $pdo->exec($version_table);
    echo "✅ جدول schema_versions ایجاد شد\n";
} catch (Exception $e) {
    echo "❌ خطا در ایجاد جدول schema_versions: " . $e->getMessage() . "\n";
}

// لیست migration ها
$migrations = [
    '001_initial_setup' => [
        'description' => 'راه‌اندازی اولیه سیستم',
        'sql' => "
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                full_name VARCHAR(255),
                role ENUM('ادمین', 'کاربر عادی') DEFAULT 'کاربر عادی',
                department VARCHAR(100),
                status ENUM('active', 'inactive') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
        ",
        'rollback' => "DROP TABLE IF EXISTS users"
    ],
    
    '002_assets_table' => [
        'description' => 'ایجاد جدول دارایی‌ها',
        'sql' => "
            CREATE TABLE IF NOT EXISTS assets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                serial_number VARCHAR(255) UNIQUE,
                model VARCHAR(255),
                purchase_date DATE,
                price DECIMAL(10,2),
                status ENUM('فعال', 'غیرفعال', 'در حال تعمیر') DEFAULT 'فعال',
                location VARCHAR(255),
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
        ",
        'rollback' => "DROP TABLE IF EXISTS assets"
    ],
    
    '003_customers_table' => [
        'description' => 'ایجاد جدول مشتریان',
        'sql' => "
            CREATE TABLE IF NOT EXISTS customers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                full_name VARCHAR(255) NOT NULL,
                phone VARCHAR(20),
                email VARCHAR(255),
                company VARCHAR(255),
                address TEXT,
                city VARCHAR(100),
                postal_code VARCHAR(20),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
        ",
        'rollback' => "DROP TABLE IF EXISTS customers"
    ],
    
    '004_requests_table' => [
        'description' => 'ایجاد جدول درخواست‌ها',
        'sql' => "
            CREATE TABLE IF NOT EXISTS requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                request_number VARCHAR(50) UNIQUE NOT NULL,
                requester_id INT,
                requester_name VARCHAR(255) NOT NULL,
                item_name VARCHAR(255) NOT NULL,
                quantity INT DEFAULT 1,
                price DECIMAL(10,2),
                description TEXT,
                priority ENUM('کم', 'متوسط', 'زیاد', 'فوری') DEFAULT 'متوسط',
                status ENUM('در انتظار تأیید', 'تأیید شده', 'در حال انجام', 'تکمیل شده', 'رد شده') DEFAULT 'در انتظار تأیید',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
        ",
        'rollback' => "DROP TABLE IF EXISTS requests"
    ],
    
    '005_workflow_table' => [
        'description' => 'ایجاد جدول گردش کار',
        'sql' => "
            CREATE TABLE IF NOT EXISTS request_workflow (
                id INT AUTO_INCREMENT PRIMARY KEY,
                request_id INT NOT NULL,
                assigned_to INT NOT NULL,
                status ENUM('ارجاع شده', 'در انتظار', 'در حال بررسی', 'تأیید شده', 'رد شده', 'تکمیل شده') DEFAULT 'ارجاع شده',
                comments TEXT,
                action_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
                FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
        ",
        'rollback' => "DROP TABLE IF EXISTS request_workflow"
    ],
    
    '006_add_indexes' => [
        'description' => 'اضافه کردن ایندکس‌ها برای بهبود عملکرد',
        'sql' => "
            ALTER TABLE requests ADD INDEX idx_status (status);
            ALTER TABLE requests ADD INDEX idx_priority (priority);
            ALTER TABLE requests ADD INDEX idx_created_at (created_at);
            ALTER TABLE request_workflow ADD INDEX idx_assigned_to (assigned_to);
            ALTER TABLE request_workflow ADD INDEX idx_status (status);
        ",
        'rollback' => "
            ALTER TABLE requests DROP INDEX idx_status;
            ALTER TABLE requests DROP INDEX idx_priority;
            ALTER TABLE requests DROP INDEX idx_created_at;
            ALTER TABLE request_workflow DROP INDEX idx_assigned_to;
            ALTER TABLE request_workflow DROP INDEX idx_status;
        "
    ]
];

// تابع اجرای migration
function runMigration($pdo, $version, $migration) {
    try {
        // بررسی اینکه آیا migration قبلاً اجرا شده یا نه
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM schema_versions WHERE version = ?");
        $stmt->execute([$version]);
        $exists = $stmt->fetchColumn() > 0;
        
        if ($exists) {
            echo "⏭️ Migration $version قبلاً اجرا شده است\n";
            return true;
        }
        
        echo "🔄 اجرای migration $version: {$migration['description']}\n";
        
        // اجرای SQL
        $pdo->exec($migration['sql']);
        
        // ثبت migration
        $stmt = $pdo->prepare("INSERT INTO schema_versions (version, description, rollback_sql) VALUES (?, ?, ?)");
        $stmt->execute([$version, $migration['description'], $migration['rollback']]);
        
        echo "✅ Migration $version با موفقیت اجرا شد\n";
        return true;
        
    } catch (Exception $e) {
        echo "❌ خطا در اجرای migration $version: " . $e->getMessage() . "\n";
        return false;
    }
}

// تابع rollback migration
function rollbackMigration($pdo, $version) {
    try {
        // دریافت اطلاعات migration
        $stmt = $pdo->prepare("SELECT rollback_sql FROM schema_versions WHERE version = ?");
        $stmt->execute([$version]);
        $rollback_sql = $stmt->fetchColumn();
        
        if (!$rollback_sql) {
            echo "❌ Migration $version یافت نشد\n";
            return false;
        }
        
        echo "🔄 Rollback migration $version\n";
        
        // اجرای rollback SQL
        $pdo->exec($rollback_sql);
        
        // حذف رکورد migration
        $stmt = $pdo->prepare("DELETE FROM schema_versions WHERE version = ?");
        $stmt->execute([$version]);
        
        echo "✅ Rollback migration $version با موفقیت انجام شد\n";
        return true;
        
    } catch (Exception $e) {
        echo "❌ خطا در rollback migration $version: " . $e->getMessage() . "\n";
        return false;
    }
}

// اجرای migration ها
if (isset($argv[1]) && $argv[1] === 'rollback' && isset($argv[2])) {
    // Rollback mode
    $version = $argv[2];
    rollbackMigration($pdo, $version);
} else {
    // Normal migration mode
    echo "🚀 شروع اجرای migration ها...\n\n";
    
    $success_count = 0;
    $total_count = count($migrations);
    
    foreach ($migrations as $version => $migration) {
        if (runMigration($pdo, $version, $migration)) {
            $success_count++;
        }
        echo "\n";
    }
    
    echo "📊 خلاصه: $success_count از $total_count migration با موفقیت اجرا شد\n";
    
    if ($success_count === $total_count) {
        echo "🎉 تمام migration ها با موفقیت اجرا شدند!\n";
    } else {
        echo "⚠️ برخی migration ها ناموفق بودند. لطفاً خطاها را بررسی کنید.\n";
    }
}

// نمایش migration های موجود
echo "\n📋 Migration های موجود:\n";
$stmt = $pdo->query("SELECT version, description, applied_at FROM schema_versions ORDER BY applied_at");
$migrations = $stmt->fetchAll();

if (empty($migrations)) {
    echo "هیچ migration یافت نشد\n";
} else {
    foreach ($migrations as $migration) {
        echo "- {$migration['version']}: {$migration['description']} ({$migration['applied_at']})\n";
    }
}

echo "\n💡 برای rollback از دستور زیر استفاده کنید:\n";
echo "php database_migration.php rollback VERSION\n";
?>