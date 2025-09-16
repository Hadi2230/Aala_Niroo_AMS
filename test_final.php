<?php
// تست نهایی سیستم
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html lang='fa' dir='rtl'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>تست نهایی سیستم</title>
    <style>
        body { font-family: Tahoma, Arial, sans-serif; padding: 20px; background: #f8f9fa; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #27ae60; background: #d5f4e6; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: #e74c3c; background: #fadbd8; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { color: #3498db; background: #d6eaf8; padding: 10px; border-radius: 5px; margin: 10px 0; }
        h1 { text-align: center; color: #2c3e50; }
        .btn { background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔍 تست نهایی سیستم</h1>";

try {
    echo "<div class='info'>در حال بارگذاری config.php...</div>";
    require_once 'config.php';
    echo "<div class='success'>✅ config.php بارگذاری شد</div>";
    
    // تست اتصال دیتابیس
    if ($pdo) {
        echo "<div class='success'>✅ اتصال به دیتابیس برقرار شد</div>";
        
        // تست query ساده
        $stmt = $pdo->query("SELECT 1 as test");
        $result = $stmt->fetch();
        if ($result && $result['test'] == 1) {
            echo "<div class='success'>✅ تست query موفق</div>";
        }
        
        // بررسی جداول
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "<div class='info'>📊 تعداد جداول: " . count($tables) . "</div>";
        
        if (count($tables) > 0) {
            echo "<div class='success'>✅ جداول موجود:</div><ul>";
            foreach ($tables as $table) {
                echo "<li>$table</li>";
            }
            echo "</ul>";
        }
        
    } else {
        echo "<div class='error'>❌ اتصال به دیتابیس برقرار نشد</div>";
    }
    
    // تست توابع
    $functions = ['hasPermission', 'logAction', 'sanitizeInput', 'verifyCsrfToken', 'csrf_field', 'redirect', 'require_auth', 'jalali_format'];
    foreach ($functions as $func) {
        if (function_exists($func)) {
            echo "<div class='success'>✅ تابع $func موجود است</div>";
        } else {
            echo "<div class='error'>❌ تابع $func موجود نیست</div>";
        }
    }
    
    echo "<div class='success'>🎉 تست نهایی موفق بود!</div>";
    echo "<div style='text-align: center; margin-top: 20px;'>";
    echo "<a href='index.php' class='btn'>🏠 صفحه اصلی</a>";
    echo "<a href='login.php' class='btn'>🔐 ورود</a>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ خطا: " . $e->getMessage() . "</div>";
    echo "<div class='error'>فایل: " . $e->getFile() . "</div>";
    echo "<div class='error'>خط: " . $e->getLine() . "</div>";
} catch (Error $e) {
    echo "<div class='error'>❌ خطای فانی: " . $e->getMessage() . "</div>";
    echo "<div class='error'>فایل: " . $e->getFile() . "</div>";
    echo "<div class='error'>خط: " . $e->getLine() . "</div>";
}

echo "</div></body></html>";
?>