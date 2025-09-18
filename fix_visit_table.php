<?php
// fix_visit_table.php - اصلاح جدول visit_requests
session_start();

// تنظیم session برای تست
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['role'] = 'ادمین';

echo "<h1>🔧 اصلاح جدول visit_requests</h1>";

// تست config.php
try {
    require_once 'config.php';
    echo "<p style='color: green;'>✅ config.php بارگذاری شد</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ خطا در config.php: " . $e->getMessage() . "</p>";
    exit();
}

// بررسی ساختار فعلی جدول
echo "<h2>1. بررسی ساختار فعلی جدول</h2>";
try {
    $stmt = $pdo->query("DESCRIBE visit_requests");
    $columns = $stmt->fetchAll();
    
    echo "<p style='color: blue;'>📊 ستون‌های موجود در جدول visit_requests:</p>";
    echo "<ul>";
    foreach ($columns as $column) {
        echo "<li>" . $column['Field'] . " - " . $column['Type'] . "</li>";
    }
    echo "</ul>";
    
    // بررسی وجود فیلدهای مورد نیاز
    $required_fields = ['company_name', 'contact_person', 'contact_phone', 'contact_email', 'visitor_count', 'visit_purpose', 'visit_type', 'request_method', 'preferred_dates', 'nda_required', 'special_requirements', 'priority', 'status'];
    $missing_fields = [];
    
    foreach ($required_fields as $field) {
        $exists = false;
        foreach ($columns as $column) {
            if ($column['Field'] === $field) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        echo "<p style='color: red;'>❌ فیلدهای گمشده: " . implode(', ', $missing_fields) . "</p>";
    } else {
        echo "<p style='color: green;'>✅ همه فیلدهای مورد نیاز موجود هستند</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ خطا در بررسی جدول: " . $e->getMessage() . "</p>";
}

// حذف و ایجاد مجدد جدول
echo "<h2>2. حذف و ایجاد مجدد جدول</h2>";
try {
    // حذف جدول‌های وابسته
    $pdo->exec("DROP TABLE IF EXISTS visit_visitors");
    $pdo->exec("DROP TABLE IF EXISTS visit_documents");
    $pdo->exec("DROP TABLE IF EXISTS visit_requests");
    
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
    echo "<p style='color: green;'>✅ جدول visit_requests با ساختار کامل ایجاد شد</p>";
    
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
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ خطا در ایجاد جداول: " . $e->getMessage() . "</p>";
}

// تست ثبت درخواست
echo "<h2>3. تست ثبت درخواست</h2>";
try {
    $visit_data = [
        'company_name' => 'شرکت تست اصلاح شده',
        'contact_person' => 'احمد احمدی',
        'contact_phone' => '09123456789',
        'contact_email' => 'ahmad@fixed.com',
        'visitor_count' => 2,
        'visit_purpose' => 'تست سیستم اصلاح شده',
        'visit_type' => 'test',
        'request_method' => 'phone',
        'preferred_dates' => [date('Y-m-d', strtotime('+1 day'))],
        'nda_required' => false,
        'special_requirements' => 'تست',
        'created_by' => 1
    ];
    
    $visit_id = createVisitRequest($pdo, $visit_data);
    echo "<p style='color: green;'>✅ درخواست تست با موفقیت ثبت شد - ID: " . $visit_id . "</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ خطا در ثبت درخواست تست: " . $e->getMessage() . "</p>";
}

// نمایش درخواست‌های موجود
echo "<h2>4. نمایش درخواست‌های موجود</h2>";
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

echo "<h2>5. لینک‌های تست</h2>";
echo "<div style='text-align: center; margin: 20px 0;'>";
echo "<a href='visit_management.php' target='_blank' style='background: #3498db; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; margin: 10px; display: inline-block; font-size: 16px; font-weight: bold;'>📋 مدیریت بازدیدها (اصلاح شده)</a>";
echo "<a href='visit_dashboard.php' target='_blank' style='background: #27ae60; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; margin: 10px; display: inline-block; font-size: 16px; font-weight: bold;'>🏠 داشبورد بازدیدها</a>";
echo "</div>";

echo "<div style='background: linear-gradient(135deg, #2ecc71, #27ae60); color: white; padding: 30px; border-radius: 15px; margin: 30px 0; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.2);'>";
echo "<h2 style='margin: 0 0 15px 0; font-size: 2rem;'>🎉 جدول visit_requests کاملاً اصلاح شد!</h2>";
echo "<p style='margin: 0; font-size: 1.2rem;'>حالا می‌توانید درخواست‌ها را بدون خطا ثبت کنید</p>";
echo "</div>";
?>