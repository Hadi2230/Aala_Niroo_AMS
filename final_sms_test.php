<?php
// final_sms_test.php - تست نهایی سیستم SMS
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html lang='fa' dir='rtl'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>تست نهایی سیستم SMS</title>
    <style>
        body { font-family: Tahoma, Arial, sans-serif; padding: 20px; background: #f8f9fa; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #27ae60; background: #d5f4e6; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: #e74c3c; background: #fadbd8; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { color: #3498db; background: #d6eaf8; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .btn { background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px; }
        h1 { text-align: center; color: #2c3e50; }
        .code { background: #f8f9fa; padding: 10px; border-radius: 5px; font-family: monospace; margin: 10px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🎯 تست نهایی سیستم SMS</h1>";

try {
    // بررسی وجود فایل sms.php
    if (!file_exists('sms.php')) {
        echo "<div class='error'>❌ فایل sms.php یافت نشد!</div>";
        exit;
    }
    
    require_once 'sms.php';
    echo "<div class='success'>✅ فایل sms.php با موفقیت بارگذاری شد</div>";
    
    // بررسی وجود توابع
    $functions = ['normalize_phone_number', 'send_sms_mock', 'send_sms_real', 'send_sms'];
    foreach ($functions as $func) {
        if (function_exists($func)) {
            echo "<div class='success'>✅ تابع $func تعریف شده است</div>";
        } else {
            echo "<div class='error'>❌ تابع $func تعریف نشده است!</div>";
        }
    }
    
    // تست با شماره موقت
    $test_phone = '09123456789';
    $test_message = 'تست نهایی سیستم SMS - ' . date('Y-m-d H:i:s');
    
    echo "<div class='info'><strong>شماره تست:</strong> $test_phone</div>";
    echo "<div class='info'><strong>پیام:</strong> $test_message</div>";
    
    // تست نرمال‌سازی
    echo "<h3>1. تست نرمال‌سازی شماره تلفن:</h3>";
    $normalized = normalize_phone_number($test_phone);
    echo "<div class='code'>شماره اصلی: $test_phone<br>شماره نرمال‌سازی شده: " . ($normalized ?: 'نامعتبر') . "</div>";
    
    if (!$normalized) {
        echo "<div class='error'>❌ شماره تلفن نامعتبر است!</div>";
        exit;
    }
    
    // تست تابع mock
    echo "<h3>2. تست تابع Mock:</h3>";
    $mock_result = send_sms_mock($test_phone, $test_message);
    
    echo "<div class='code'>";
    echo "نتیجه Mock: " . ($mock_result['success'] ? 'موفق' : 'ناموفق') . "<br>";
    echo "پیام Mock: " . ($mock_result['error'] ?? 'بدون خطا') . "<br>";
    if (isset($mock_result['message_id'])) {
        echo "Message ID: " . $mock_result['message_id'] . "<br>";
    }
    echo "</div>";
    
    if ($mock_result['success']) {
        echo "<div class='success'>✅ تست Mock موفق بود!</div>";
    } else {
        echo "<div class='error'>❌ تست Mock ناموفق: " . $mock_result['error'] . "</div>";
    }
    
    // تست تابع اصلی
    echo "<h3>3. تست تابع اصلی send_sms:</h3>";
    $result = send_sms($test_phone, $test_message);
    
    echo "<div class='code'>";
    echo "نتیجه: " . ($result['success'] ? 'موفق' : 'ناموفق') . "<br>";
    echo "پیام: " . ($result['error'] ?? 'بدون خطا') . "<br>";
    if (isset($result['message_id'])) {
        echo "Message ID: " . $result['message_id'] . "<br>";
    }
    echo "</div>";
    
    if ($result['success']) {
        echo "<div class='success'>✅ تابع اصلی send_sms موفق بود!</div>";
    } else {
        echo "<div class='error'>❌ تابع اصلی send_sms ناموفق: " . $result['error'] . "</div>";
    }
    
    // تست API واقعی
    echo "<h3>4. تست API واقعی:</h3>";
    $real_result = send_sms_real($test_phone, $test_message);
    
    echo "<div class='code'>";
    echo "نتیجه API واقعی: " . ($real_result['success'] ? 'موفق' : 'ناموفق') . "<br>";
    echo "پیام API واقعی: " . ($real_result['error'] ?? 'بدون خطا') . "<br>";
    if (isset($real_result['message_id'])) {
        echo "Message ID: " . $real_result['message_id'] . "<br>";
    }
    echo "</div>";
    
    if ($real_result['success']) {
        echo "<div class='success'>✅ API واقعی موفق بود!</div>";
    } else {
        echo "<div class='error'>❌ API واقعی ناموفق: " . $real_result['error'] . "</div>";
    }
    
    // خلاصه نتایج
    echo "<h3>5. خلاصه نتایج:</h3>";
    echo "<div class='info'>";
    echo "✅ نرمال‌سازی: " . ($normalized ? 'موفق' : 'ناموفق') . "<br>";
    echo "✅ Mock: " . ($mock_result['success'] ? 'موفق' : 'ناموفق') . "<br>";
    echo "✅ تابع اصلی: " . ($result['success'] ? 'موفق' : 'ناموفق') . "<br>";
    echo "✅ API واقعی: " . ($real_result['success'] ? 'موفق' : 'ناموفق') . "<br>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ خطا: " . $e->getMessage() . "</div>";
    echo "<div class='error'>فایل: " . $e->getFile() . "</div>";
    echo "<div class='error'>خط: " . $e->getLine() . "</div>";
}

echo "<div style='text-align: center; margin-top: 20px;'>
    <a href='test_sms.php' class='btn'>🔄 تست ساده</a>
    <a href='debug_sms.php' class='btn'>🔍 دیباگ کامل</a>
    <a href='customers.php' class='btn'>👥 مدیریت مشتریان</a>
    <a href='index.php' class='btn'>🏠 صفحه اصلی</a>
</div>";

echo "</div></body></html>";
?>