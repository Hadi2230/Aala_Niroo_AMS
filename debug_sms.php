<?php
// debug_sms.php - تست و دیباگ سیستم SMS
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html lang='fa' dir='rtl'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>دیباگ سیستم SMS</title>
    <style>
        body { font-family: Tahoma, Arial, sans-serif; padding: 20px; background: #f8f9fa; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #27ae60; background: #d5f4e6; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: #e74c3c; background: #fadbd8; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { color: #3498db; background: #d6eaf8; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .warning { color: #f39c12; background: #fef9e7; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .btn { background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px; }
        h1 { text-align: center; color: #2c3e50; }
        .code { background: #f8f9fa; padding: 10px; border-radius: 5px; font-family: monospace; margin: 10px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔍 دیباگ سیستم SMS</h1>";

try {
    require_once 'sms.php';
    
    echo "<div class='info'>در حال تست سیستم SMS...</div>";
    
    // تست با شماره موقت
    $test_phone = '09123456789';
    $test_message = 'تست ارسال پیامک از سیستم مدیریت دارایی‌ها - ' . date('Y-m-d H:i:s');
    
    echo "<div class='info'><strong>شماره تست:</strong> $test_phone</div>";
    echo "<div class='info'><strong>پیام:</strong> $test_message</div>";
    
    // تست تابع نرمال‌سازی
    echo "<h3>1. تست نرمال‌سازی شماره تلفن:</h3>";
    $normalized = normalize_phone_number($test_phone);
    echo "<div class='code'>شماره اصلی: $test_phone<br>شماره نرمال‌سازی شده: " . ($normalized ?: 'نامعتبر') . "</div>";
    
    if (!$normalized) {
        echo "<div class='error'>❌ شماره تلفن نامعتبر است!</div>";
        exit;
    }
    
    // تست ارسال پیامک واقعی
    echo "<h3>2. تست ارسال پیامک واقعی:</h3>";
    $result = send_sms($test_phone, $test_message);
    
    echo "<div class='code'>";
    echo "نتیجه: " . ($result['success'] ? 'موفق' : 'ناموفق') . "<br>";
    echo "پیام: " . ($result['error'] ?? 'بدون خطا') . "<br>";
    if (isset($result['response'])) {
        echo "پاسخ سرور: " . json_encode($result['response'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "<br>";
    }
    echo "</div>";
    
    if ($result['success']) {
        echo "<div class='success'>✅ پیامک با موفقیت ارسال شد!</div>";
        echo "<div class='info'>Message ID: " . ($result['message_id'] ?? 'نامشخص') . "</div>";
    } else {
        echo "<div class='error'>❌ خطا در ارسال پیامک: " . $result['error'] . "</div>";
        
        // بررسی جزئیات خطا
        if (isset($result['response'])) {
            echo "<h4>جزئیات خطا:</h4>";
            echo "<div class='code'>" . json_encode($result['response'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "</div>";
        }
    }
    
    // تست تابع mock
    echo "<h3>3. تست تابع Mock:</h3>";
    $mock_result = send_sms_mock($test_phone, $test_message);
    
    echo "<div class='code'>";
    echo "نتیجه Mock: " . ($mock_result['success'] ? 'موفق' : 'ناموفق') . "<br>";
    echo "پیام Mock: " . ($mock_result['error'] ?? 'بدون خطا') . "<br>";
    echo "</div>";
    
    if ($mock_result['success']) {
        echo "<div class='success'>✅ تست Mock موفق بود!</div>";
    } else {
        echo "<div class='error'>❌ تست Mock ناموفق: " . $mock_result['error'] . "</div>";
    }
    
    // بررسی تنظیمات API
    echo "<h3>4. بررسی تنظیمات API:</h3>";
    echo "<div class='code'>";
    echo "API URL: https://api2.ippanel.com/api/v1/sms/send/webservice/single<br>";
    echo "API Key: " . substr('OWZlNDE1MjctZWViMi00ZjM2LThjMDItMTAyMTc3NTI3OGFiOWFlNjkzYzIzYTk0OTRhNzVjNzIzMWRlZTY4MTE1Yzc=', 0, 20) . "...<br>";
    echo "Line Number: 5000125475<br>";
    echo "</div>";
    
    // تست اتصال به API
    echo "<h3>5. تست اتصال به API:</h3>";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api2.ippanel.com/api/v1/sms/send/webservice/single');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'recipient' => $normalized,
        'message' => 'تست اتصال',
        'sender' => '5000125475'
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Bearer OWZlNDE1MjctZWViMi00ZjM2LThjMDItMTAyMTc3NTI3OGFiOWFlNjkzYzIzYTk0OTRhNzVjNzIzMWRlZTY4MTE1Yzc='
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    echo "<div class='code'>";
    echo "HTTP Code: $http_code<br>";
    echo "CURL Error: " . ($curl_error ?: 'بدون خطا') . "<br>";
    echo "Response: " . ($response ?: 'بدون پاسخ') . "<br>";
    echo "</div>";
    
    if ($http_code == 200) {
        echo "<div class='success'>✅ اتصال به API موفق است</div>";
    } else {
        echo "<div class='error'>❌ خطا در اتصال به API: $http_code</div>";
    }
    
    // پیشنهادات
    echo "<h3>6. پیشنهادات:</h3>";
    echo "<div class='warning'>";
    echo "اگر SMS ارسال نمی‌شود، احتمالاً یکی از مشکلات زیر است:<br>";
    echo "1. API Key اشتباه یا منقضی شده<br>";
    echo "2. شماره خط غیرفعال یا نامعتبر<br>";
    echo "3. اعتبار حساب تمام شده<br>";
    echo "4. شماره تلفن گیرنده نامعتبر<br>";
    echo "5. محدودیت‌های پنل پیامکی<br>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ خطا: " . $e->getMessage() . "</div>";
    echo "<div class='error'>فایل: " . $e->getFile() . "</div>";
    echo "<div class='error'>خط: " . $e->getLine() . "</div>";
}

echo "<div style='text-align: center; margin-top: 20px;'>
    <a href='test_sms.php' class='btn'>🔄 تست ساده SMS</a>
    <a href='customers.php' class='btn'>👥 مدیریت مشتریان</a>
    <a href='index.php' class='btn'>🏠 صفحه اصلی</a>
</div>";

echo "</div></body></html>";
?>