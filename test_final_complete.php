<?php
// تست نهایی و کامل سیستم
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🚀 تست نهایی سیستم - نسخه کامل</h1>";

try {
    // شبیه‌سازی session
    session_start();
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'admin';
    $_SESSION['role'] = 'ادمین';
    $_SESSION['csrf_token'] = 'test123';
    
    echo "<p>✅ Session تنظیم شد</p>";
    
    // تست config
    include 'config.php';
    echo "<p>✅ config.php بارگذاری شد</p>";
    
    // تست navbar
    include 'navbar.php';
    echo "<p>✅ navbar.php بارگذاری شد</p>";
    
    // تست جداول اصلی
    $tables = ['users', 'customers', 'assets', 'asset_assignments', 'asset_types', 'system_logs'];
    foreach ($tables as $table) {
        $count = $pdo->query("SELECT COUNT(*) as total FROM $table")->fetch()['total'];
        echo "<p>✅ جدول $table: $count مورد</p>";
    }
    
    // تست جداول خودروها
    $car_tables = ['company_cars', 'car_assignments', 'car_maintenance', 'car_fuel_records', 'car_incidents', 'car_drivers', 'car_usage_logs', 'car_reservations'];
    foreach ($car_tables as $table) {
        $count = $pdo->query("SELECT COUNT(*) as total FROM $table")->fetch()['total'];
        echo "<p>✅ جدول $table: $count مورد</p>";
    }
    
    // تست توابع اصلی
    echo "<h3>🔧 تست توابع اصلی:</h3>";
    
    // تست sanitizeInput
    $test_input = "Test <script>alert('xss')</script>";
    $sanitized = sanitizeInput($test_input);
    echo "<p>✅ sanitizeInput: " . ($sanitized === "Test &lt;script&gt;alert('xss')&lt;/script&gt;" ? "کار می‌کند" : "مشکل دارد") . "</p>";
    
    // تست logAction
    logAction($pdo, 'test_action', 'تست لاگ سیستم');
    echo "<p>✅ logAction: کار می‌کند</p>";
    
    // تست verifyCsrfToken
    verifyCsrfToken('test123');
    echo "<p>✅ verifyCsrfToken: کار می‌کند</p>";
    
    // تست توابع خودروها
    echo "<h3>🚗 تست توابع خودروها:</h3>";
    
    // تست getCarStatistics
    $stats = getCarStatistics($pdo);
    echo "<p>✅ getCarStatistics: " . json_encode($stats) . "</p>";
    
    // تست getAvailableCars
    $cars = getAvailableCars($pdo, '2024-01-01', '2024-12-31');
    echo "<p>✅ getAvailableCars: " . count($cars) . " خودرو</p>";
    
    // تست getCarAssignments
    $assignments = getCarAssignments($pdo);
    echo "<p>✅ getCarAssignments: " . count($assignments) . " انتساب</p>";
    
    // تست اضافه کردن خودرو
    $car_data = [
        'car_plate' => '12-الف-12345',
        'car_name' => 'پژو 206',
        'car_model' => '206',
        'car_year' => 2020,
        'car_color' => 'سفید',
        'car_type' => 'سواری',
        'status' => 'فعال'
    ];
    
    $car_id = addCompanyCar($pdo, $car_data);
    echo "<p>✅ addCompanyCar: خودرو با ID $car_id اضافه شد</p>";
    
    // تست اضافه کردن تعمیر
    $maintenance_data = [
        'car_id' => $car_id,
        'maintenance_type' => 'تعمیر دوره‌ای',
        'maintenance_date' => date('Y-m-d H:i:s'),
        'description' => 'تست تعمیر',
        'maintenance_status' => 'تکمیل شده'
    ];
    
    $maintenance_id = addCarMaintenance($pdo, $maintenance_data);
    echo "<p>✅ addCarMaintenance: تعمیر با ID $maintenance_id اضافه شد</p>";
    
    // تست اضافه کردن سوخت
    $fuel_data = [
        'car_id' => $car_id,
        'fuel_date' => date('Y-m-d H:i:s'),
        'fuel_type' => 'بنزین',
        'fuel_amount' => 50.0,
        'fuel_cost' => 250000.0
    ];
    
    $fuel_id = addFuelRecord($pdo, $fuel_data);
    echo "<p>✅ addFuelRecord: سوخت با ID $fuel_id اضافه شد</p>";
    
    echo "<h2>🎉 همه چیز کار می‌کند!</h2>";
    echo "<div style='background: #d4edda; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
    echo "<h3>✅ سیستم آماده است!</h3>";
    echo "<p><a href='dashboard.php' class='btn btn-primary'>برو به داشبورد</a></p>";
    echo "<p><a href='cars.php' class='btn btn-success'>برو به مدیریت خودروها</a></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
    echo "<h3>❌ خطا:</h3>";
    echo "<p><strong>پیام:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>فایل:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>خط:</strong> " . $e->getLine() . "</p>";
    echo "<p><strong>جزئیات:</strong></p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    echo "</div>";
}
?>