<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once __DIR__ . '/config.php';

echo "<h2>تست نمایش دارایی‌ها</h2>";

// بررسی دارایی‌ها
try {
    $query = "SELECT a.*, at.display_name as type_display_name, at.name as type_name
              FROM assets a 
              JOIN asset_types at ON a.type_id = at.id 
              ORDER BY a.created_at DESC LIMIT 10";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $assets = $stmt->fetchAll();
    
    echo "<h3>دارایی‌های موجود:</h3>";
    if (count($assets) > 0) {
        echo "<p style='color: green;'>✅ " . count($assets) . " دارایی یافت شد</p>";
        echo "<table class='table table-striped'>";
        echo "<thead><tr><th>نام</th><th>نوع</th><th>سریال</th><th>وضعیت</th></tr></thead>";
        echo "<tbody>";
        foreach ($assets as $asset) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($asset['name']) . "</td>";
            echo "<td>" . htmlspecialchars($asset['type_display_name']) . "</td>";
            echo "<td>" . htmlspecialchars($asset['serial_number'] ?: '-') . "</td>";
            echo "<td>" . htmlspecialchars($asset['status']) . "</td>";
            echo "</tr>";
        }
        echo "</tbody></table>";
    } else {
        echo "<p style='color: red;'>❌ هیچ دارایی یافت نشد</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ خطا در دریافت دارایی‌ها: " . $e->getMessage() . "</p>";
}

// تست پارامترهای جستجو
echo "<h3>تست پارامترهای جستجو:</h3>";
$search = $_GET['search'] ?? '';
$search_type = $_GET['search_type'] ?? 'all';
$type_filter = $_GET['type_filter'] ?? '';
$status_filter = $_GET['status_filter'] ?? '';

echo "<p><strong>search:</strong> '$search'</p>";
echo "<p><strong>search_type:</strong> '$search_type'</p>";
echo "<p><strong>type_filter:</strong> '$type_filter'</p>";
echo "<p><strong>status_filter:</strong> '$status_filter'</p>";

// تست شرط نمایش
$show_assets = (!empty($search) && ($search_type === 'all' || $search_type === 'assets')) || ($search_type === 'assets' && empty($search));
echo "<p><strong>شرط نمایش دارایی‌ها:</strong> " . ($show_assets ? 'true' : 'false') . "</p>";

// تست کوئری با پارامترها
if ($search_type === 'all' || $search_type === 'assets') {
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
    $query .= " ORDER BY a.created_at DESC";
    
    echo "<h4>کوئری نهایی:</h4>";
    echo "<pre>" . htmlspecialchars($query) . "</pre>";
    echo "<h4>پارامترها:</h4>";
    echo "<pre>" . print_r($params, true) . "</pre>";
    
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $filtered_assets = $stmt->fetchAll();
        echo "<p style='color: green;'>✅ کوئری موفق: " . count($filtered_assets) . " دارایی</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ خطا در کوئری: " . $e->getMessage() . "</p>";
    }
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <title>تست نمایش دارایی‌ها</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h3>تست‌های مختلف:</h3>
        <div class="d-flex gap-2 mb-3">
            <a href="test_assets_display.php" class="btn btn-primary">بدون پارامتر</a>
            <a href="test_assets_display.php?search_type=assets" class="btn btn-info">نوع: دارایی‌ها</a>
            <a href="test_assets_display.php?search_type=assets&search=تست" class="btn btn-warning">جستجو: تست</a>
            <a href="assets.php" class="btn btn-success">برو به صفحه اصلی</a>
        </div>
    </div>
</body>
</html>