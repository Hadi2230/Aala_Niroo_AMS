<?php
// test_survey_error.php - تست خطای survey.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "شروع تست...\n";

try {
    echo "1. تست session_start...\n";
    session_start();
    echo "✅ session_start موفق\n";
    
    echo "2. تست require_once config.php...\n";
    require_once 'config.php';
    echo "✅ config.php بارگذاری شد\n";
    
    echo "3. تست PDO...\n";
    if (isset($pdo)) {
        echo "✅ PDO موجود است\n";
    } else {
        echo "❌ PDO موجود نیست\n";
    }
    
    echo "4. تست جداول...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS surveys (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        is_active BOOLEAN DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");
    echo "✅ جدول surveys ایجاد شد\n";
    
    echo "5. تست دریافت نظرسنجی‌ها...\n";
    $stmt = $pdo->query("SELECT * FROM surveys WHERE is_active = 1 ORDER BY id DESC");
    $surveys = $stmt->fetchAll();
    echo "✅ " . count($surveys) . " نظرسنجی یافت شد\n";
    
    echo "6. تست توابع...\n";
    if (function_exists('verifyCsrfToken')) {
        echo "✅ verifyCsrfToken موجود است\n";
    } else {
        echo "❌ verifyCsrfToken موجود نیست\n";
    }
    
    if (function_exists('csrf_field')) {
        echo "✅ csrf_field موجود است\n";
    } else {
        echo "❌ csrf_field موجود نیست\n";
    }
    
    echo "7. تست مشتریان...\n";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM customers");
    $customer_count = $stmt->fetch()['count'];
    echo "✅ $customer_count مشتری یافت شد\n";
    
    echo "8. تست دارایی‌ها...\n";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM assets");
    $asset_count = $stmt->fetch()['count'];
    echo "✅ $asset_count دارایی یافت شد\n";
    
    echo "\n🎉 همه تست‌ها موفق بود!\n";
    
} catch (Exception $e) {
    echo "❌ خطا: " . $e->getMessage() . "\n";
    echo "فایل: " . $e->getFile() . "\n";
    echo "خط: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
} catch (Error $e) {
    echo "❌ خطای PHP: " . $e->getMessage() . "\n";
    echo "فایل: " . $e->getFile() . "\n";
    echo "خط: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
?>