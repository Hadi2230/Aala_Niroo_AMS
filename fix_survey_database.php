<?php
require_once 'config.php';

echo "<h2>اصلاح دیتابیس نظرسنجی</h2>";

try {
    // بررسی وجود جدول survey_submissions
    $tables = $pdo->query("SHOW TABLES LIKE 'survey_submissions'")->fetchAll();
    
    if (empty($tables)) {
        echo "<p style='color: red;'>❌ جدول survey_submissions وجود ندارد!</p>";
        
        // ایجاد جدول
        $pdo->exec("CREATE TABLE survey_submissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            survey_id INT NOT NULL,
            customer_id INT NULL,
            asset_id INT NULL,
            status ENUM('draft', 'completed', 'pending') DEFAULT 'draft',
            submitted_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
            FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE SET NULL,
            FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        echo "<p style='color: green;'>✅ جدول survey_submissions ایجاد شد</p>";
    } else {
        echo "<p style='color: blue;'>ℹ️ جدول survey_submissions موجود است</p>";
        
        // بررسی ساختار جدول
        $columns = $pdo->query("DESCRIBE survey_submissions")->fetchAll();
        echo "<h3>ستون‌های موجود:</h3><ul>";
        foreach ($columns as $col) {
            echo "<li>{$col['Field']} - {$col['Type']}</li>";
        }
        echo "</ul>";
        
        // بررسی وجود ستون submitted_by
        $has_submitted_by = false;
        foreach ($columns as $col) {
            if ($col['Field'] === 'submitted_by') {
                $has_submitted_by = true;
                break;
            }
        }
        
        if (!$has_submitted_by) {
            echo "<p style='color: orange;'>⚠️ ستون submitted_by وجود ندارد. اضافه می‌کنم...</p>";
            $pdo->exec("ALTER TABLE survey_submissions ADD COLUMN submitted_by INT NULL AFTER status");
            $pdo->exec("ALTER TABLE survey_submissions ADD FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE SET NULL");
            echo "<p style='color: green;'>✅ ستون submitted_by اضافه شد</p>";
        } else {
            echo "<p style='color: green;'>✅ ستون submitted_by موجود است</p>";
        }
    }
    
    // بررسی سایر جداول
    $required_tables = ['surveys', 'survey_questions', 'survey_responses'];
    
    foreach ($required_tables as $table) {
        $tables = $pdo->query("SHOW TABLES LIKE '$table'")->fetchAll();
        if (empty($tables)) {
            echo "<p style='color: red;'>❌ جدول $table وجود ندارد!</p>";
        } else {
            echo "<p style='color: green;'>✅ جدول $table موجود است</p>";
        }
    }
    
    // تست کوئری
    echo "<h3>تست کوئری:</h3>";
    try {
        $st = $pdo->query("SELECT s.id, s.created_at, s.status, sv.title AS survey_title,
                                  c.full_name AS customer_name, c.company AS customer_company,
                                  a.name AS asset_name, a.serial_number,
                                  u.username AS submitted_by_name
                           FROM survey_submissions s
                           JOIN surveys sv ON sv.id = s.survey_id
                           LEFT JOIN customers c ON c.id = s.customer_id
                           LEFT JOIN assets a ON a.id = s.asset_id
                           LEFT JOIN users u ON u.id = s.submitted_by
                           ORDER BY s.id DESC LIMIT 5");
        $results = $st->fetchAll();
        echo "<p style='color: green;'>✅ کوئری با موفقیت اجرا شد. تعداد نتایج: " . count($results) . "</p>";
        
        if (!empty($results)) {
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr><th>ID</th><th>نظرسنجی</th><th>مشتری</th><th>وضعیت</th><th>تاریخ</th><th>ثبت کننده</th></tr>";
            foreach ($results as $row) {
                echo "<tr>";
                echo "<td>{$row['id']}</td>";
                echo "<td>{$row['survey_title']}</td>";
                echo "<td>" . ($row['customer_name'] ? $row['customer_name'] : ($row['customer_company'] ? $row['customer_company'] : '-')) . "</td>";
                echo "<td>{$row['status']}</td>";
                echo "<td>{$row['created_at']}</td>";
                echo "<td>" . ($row['submitted_by_name'] ? $row['submitted_by_name'] : '-') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ خطا در تست کوئری: " . $e->getMessage() . "</p>";
    }
    
    echo "<h3 style='color: green;'>✅ اصلاح دیتابیس تکمیل شد!</h3>";
    echo "<p><a href='survey_list.php'>برو به لیست نظرسنجی‌ها</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ خطا: " . $e->getMessage() . "</p>";
}
?>