<?php
// تست dashboard
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>تست Dashboard</h1>";

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
    
    // تست جداول dashboard
    $total_assets = $pdo->query("SELECT COUNT(*) as total FROM assets")->fetch()['total'];
    echo "<p>✅ جدول assets: $total_assets مورد</p>";
    
    $total_customers = $pdo->query("SELECT COUNT(*) as total FROM customers")->fetch()['total'];
    echo "<p>✅ جدول customers: $total_customers مورد</p>";
    
    $total_users = $pdo->query("SELECT COUNT(*) as total FROM users")->fetch()['total'];
    echo "<p>✅ جدول users: $total_users مورد</p>";
    
    $total_assignments = $pdo->query("SELECT COUNT(*) as total FROM asset_assignments")->fetch()['total'];
    echo "<p>✅ جدول asset_assignments: $total_assignments مورد</p>";
    
    echo "<h2>🎉 همه جداول کار می‌کنند!</h2>";
    echo "<p><a href='dashboard.php'>برو به داشبورد</a></p>";
    
} catch (Exception $e) {
    echo "<p>❌ خطا: " . $e->getMessage() . "</p>";
    echo "<p>فایل: " . $e->getFile() . "</p>";
    echo "<p>خط: " . $e->getLine() . "</p>";
    echo "<p>جزئیات: " . $e->getTraceAsString() . "</p>";
}
?>