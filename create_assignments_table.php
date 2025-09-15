<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once __DIR__ . '/config.php';

echo "<h2>ایجاد جدول assignments</h2>";

try {
    // ایجاد جدول assignments
    $create_assignments = "
    CREATE TABLE IF NOT EXISTS assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        asset_id INT NOT NULL,
        assignment_date DATE NOT NULL,
        installation_date DATE NULL,
        warranty_start_date DATE NULL,
        warranty_end_date DATE NULL,
        installation_address TEXT NULL,
        installation_notes TEXT NULL,
        warranty_terms TEXT NULL,
        status ENUM('فعال', 'پایان یافته', 'لغو شده') DEFAULT 'فعال',
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_customer_id (customer_id),
        INDEX idx_asset_id (asset_id),
        INDEX idx_status (status),
        INDEX idx_assignment_date (assignment_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($create_assignments);
    echo "<p style='color: green;'>✅ جدول assignments ایجاد شد</p>";
    
    // اضافه کردن داده‌های نمونه
    $sample_data = "
    INSERT INTO assignments (customer_id, asset_id, assignment_date, installation_date, warranty_start_date, warranty_end_date, installation_address, installation_notes, warranty_terms, status, notes) VALUES
    (1, 1, '2024-01-15', '2024-01-20', '2024-01-20', '2025-01-20', 'تهران، خیابان ولیعصر، پلاک 123', 'نصب موفقیت‌آمیز انجام شد', 'گارانتی 1 ساله', 'فعال', 'انتساب موفق'),
    (2, 2, '2024-02-10', '2024-02-15', '2024-02-15', '2025-02-15', 'اصفهان، خیابان چهارباغ، پلاک 456', 'نصب با موفقیت انجام شد', 'گارانتی 2 ساله', 'فعال', 'انتساب موفقیت‌آمیز')";
    
    $pdo->exec($sample_data);
    echo "<p style='color: green;'>✅ داده‌های نمونه اضافه شد</p>";
    
    // بررسی جدول
    $result = $pdo->query("SELECT COUNT(*) as count FROM assignments")->fetch();
    echo "<p style='color: blue;'>ℹ️ تعداد رکوردهای assignments: " . $result['count'] . "</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ خطا در ایجاد جدول: " . $e->getMessage() . "</p>";
}

// بررسی تمام جداول
echo "<h3>بررسی تمام جداول:</h3>";
$tables = ['assets', 'customers', 'assignments', 'suppliers', 'asset_types'];
foreach ($tables as $table) {
    try {
        $result = $pdo->query("SHOW TABLES LIKE '$table'")->fetch();
        if ($result) {
            $count = $pdo->query("SELECT COUNT(*) as count FROM $table")->fetch()['count'];
            echo "<p style='color: green;'>✅ جدول $table موجود است ($count رکورد)</p>";
        } else {
            echo "<p style='color: red;'>❌ جدول $table وجود ندارد</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ خطا در بررسی جدول $table: " . $e->getMessage() . "</p>";
    }
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <title>ایجاد جدول assignments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <a href="assets.php" class="btn btn-primary">برو به صفحه دارایی‌ها</a>
        <a href="debug_assets.php" class="btn btn-info">بررسی مجدد</a>
    </div>
</body>
</html>