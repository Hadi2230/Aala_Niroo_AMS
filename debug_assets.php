<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once __DIR__ . '/config.php';

echo "<h2>Debug Assets Page - بررسی صفحه دارایی‌ها</h2>";

// بررسی جداول
$tables = ['assets', 'customers', 'assignments', 'suppliers', 'asset_types'];
echo "<h3>بررسی جداول:</h3>";
foreach ($tables as $table) {
    try {
        $result = $pdo->query("SHOW TABLES LIKE '$table'")->fetch();
        if ($result) {
            echo "<p style='color: green;'>✅ جدول $table موجود است</p>";
        } else {
            echo "<p style='color: red;'>❌ جدول $table وجود ندارد</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ خطا در بررسی جدول $table: " . $e->getMessage() . "</p>";
    }
}

// بررسی کوئری‌های جستجو
echo "<h3>تست کوئری‌های جستجو:</h3>";

// تست جستجو در دارایی‌ها
try {
    $query = "SELECT a.*, at.display_name as type_display_name, at.name as type_name
              FROM assets a 
              JOIN asset_types at ON a.type_id = at.id 
              WHERE 1=1 LIMIT 5";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $assets = $stmt->fetchAll();
    echo "<p style='color: green;'>✅ کوئری دارایی‌ها موفق: " . count($assets) . " رکورد</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ خطا در کوئری دارایی‌ها: " . $e->getMessage() . "</p>";
}

// تست جستجو در مشتریان
try {
    $query = "SELECT * FROM customers WHERE 1=1 LIMIT 5";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $customers = $stmt->fetchAll();
    echo "<p style='color: green;'>✅ کوئری مشتریان موفق: " . count($customers) . " رکورد</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ خطا در کوئری مشتریان: " . $e->getMessage() . "</p>";
}

// تست جستجو در انتساب‌ها
try {
    $query = "SELECT a.*, c.full_name as customer_name, c.company as customer_company, 
              ast.name as asset_name, ast.serial_number as asset_serial
              FROM assignments a 
              LEFT JOIN customers c ON a.customer_id = c.id 
              LEFT JOIN assets ast ON a.asset_id = ast.id 
              WHERE 1=1 LIMIT 5";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $assignments = $stmt->fetchAll();
    echo "<p style='color: green;'>✅ کوئری انتساب‌ها موفق: " . count($assignments) . " رکورد</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ خطا در کوئری انتساب‌ها: " . $e->getMessage() . "</p>";
}

// تست جستجو در تامین‌کنندگان
try {
    $query = "SELECT * FROM suppliers WHERE 1=1 LIMIT 5";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $suppliers = $stmt->fetchAll();
    echo "<p style='color: green;'>✅ کوئری تامین‌کنندگان موفق: " . count($suppliers) . " رکورد</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ خطا در کوئری تامین‌کنندگان: " . $e->getMessage() . "</p>";
}

// بررسی خطاهای PHP
echo "<h3>خطاهای PHP:</h3>";
$errors = error_get_last();
if ($errors) {
    echo "<pre style='color: red;'>";
    print_r($errors);
    echo "</pre>";
} else {
    echo "<p style='color: green;'>✅ هیچ خطای PHP یافت نشد</p>";
}

// تست ساده assets.php
echo "<h3>تست ساده assets.php:</h3>";
try {
    $search = '';
    $search_type = 'all';
    $type_filter = '';
    $status_filter = '';
    
    // فقط دارایی‌ها
    $query = "SELECT a.*, at.display_name as type_display_name, at.name as type_name
              FROM assets a 
              JOIN asset_types at ON a.type_id = at.id 
              WHERE 1=1";
    $params = [];
    if (!empty($search)) {
        $query .= " AND (a.name LIKE ? OR a.serial_number LIKE ? OR a.model LIKE ? OR a.brand LIKE ? OR a.device_identifier LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term; $params[] = $search_term; $params[] = $search_term; $params[] = $search_term; $params[] = $search_term;
    }
    if (!empty($type_filter)) {
        $query .= " AND a.type_id = ?";
        $params[] = $type_filter;
    }
    if (!empty($status_filter)) {
        $query .= " AND a.status = ?";
        $params[] = $status_filter;
    }
    $query .= " ORDER BY a.created_at DESC LIMIT 10";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $assets = $stmt->fetchAll();
    
    echo "<p style='color: green;'>✅ تست کامل موفق: " . count($assets) . " دارایی یافت شد</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ خطا در تست کامل: " . $e->getMessage() . "</p>";
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <title>Debug Assets</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <a href="assets.php" class="btn btn-primary">برو به صفحه دارایی‌ها</a>
    </div>
</body>
</html>