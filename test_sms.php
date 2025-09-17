<?php
// تست ارسال پیامک
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html lang='fa' dir='rtl'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>تست ارسال پیامک</title>
    <style>
        body { font-family: Tahoma, Arial, sans-serif; padding: 20px; background: #f8f9fa; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #27ae60; background: #d5f4e6; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: #e74c3c; background: #fadbd8; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { color: #3498db; background: #d6eaf8; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .btn { background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px; }
        h1 { text-align: center; color: #2c3e50; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>📱 تست ارسال پیامک</h1>";

try {
    require_once 'sms.php';
    
    echo "<div class='info'>در حال تست ارسال پیامک...</div>";
    
    // تست با شماره موقت
    $test_phone = '09123456789';
    $test_message = 'تست ارسال پیامک از سیستم مدیریت دارایی‌ها - ' . date('Y-m-d H:i:s');
    
    echo "<div class='info'>شماره تست: $test_phone</div>";
    echo "<div class='info'>پیام: $test_message</div>";
    
    // تست تابع نرمال‌سازی
    $normalized = normalize_phone_number($test_phone);
    echo "<div class='info'>شماره نرمال‌سازی شده: $normalized</div>";
    
    // تست ارسال پیامک
    $result = send_sms($test_phone, $test_message);
    
    if ($result['success']) {
        echo "<div class='success'>✅ پیامک با موفقیت ارسال شد!</div>";
        echo "<div class='info'>Message ID: " . ($result['message_id'] ?? 'نامشخص') . "</div>";
    } else {
        echo "<div class='error'>❌ خطا در ارسال پیامک: " . $result['error'] . "</div>";
        
        if (isset($result['response'])) {
            echo "<div class='error'>پاسخ سرور: " . json_encode($result['response'], JSON_UNESCAPED_UNICODE) . "</div>";
        }
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ خطا: " . $e->getMessage() . "</div>";
    echo "<div class='error'>فایل: " . $e->getFile() . "</div>";
    echo "<div class='error'>خط: " . $e->getLine() . "</div>";
}

echo "<div style='text-align: center; margin-top: 20px;'>
    <a href='index.php' class='btn'>🏠 صفحه اصلی</a>
    <a href='login.php' class='btn'>🔐 ورود</a>
</div>";

echo "</div></body></html>";
?>