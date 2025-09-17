<?php
// simple_sms_test.php - تست ساده SMS
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html lang='fa' dir='rtl'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>تست ساده SMS</title>
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
        <h1>📱 تست ساده SMS</h1>";

try {
    // تست تابع نرمال‌سازی دستی
    function normalize_phone($phone) {
        if (empty($phone)) return false;
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) == 11 && substr($phone, 0, 2) == '09') {
            return '98' . substr($phone, 1);
        }
        return $phone;
    }
    
    echo "<div class='info'>در حال تست سیستم SMS...</div>";
    
    // تست با شماره موقت
    $test_phone = '09123456789';
    $test_message = 'تست ارسال پیامک - ' . date('Y-m-d H:i:s');
    
    echo "<div class='info'>شماره تست: $test_phone</div>";
    echo "<div class='info'>پیام: $test_message</div>";
    
    // تست نرمال‌سازی
    $normalized = normalize_phone($test_phone);
    echo "<div class='info'>شماره نرمال‌سازی شده: $normalized</div>";
    
    // تست ارسال پیامک (mock)
    echo "<div class='info'>تست ارسال پیامک (Mock)...</div>";
    
    // شبیه‌سازی ارسال
    sleep(1); // تاخیر
    $success = rand(0, 100) > 20; // 80% احتمال موفقیت
    
    if ($success) {
        echo "<div class='success'>✅ پیامک با موفقیت ارسال شد! (Mock)</div>";
        echo "<div class='info'>Message ID: MSG_" . time() . "_" . rand(1000, 9999) . "</div>";
    } else {
        echo "<div class='error'>❌ خطا در ارسال پیامک (Mock)</div>";
    }
    
    // تست API واقعی
    echo "<div class='info'>تست API واقعی...</div>";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api2.ippanel.com/api/v1/sms/send/webservice/single');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'recipient' => $normalized,
        'message' => $test_message,
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
    
    echo "<div class='info'>HTTP Code: $http_code</div>";
    echo "<div class='info'>CURL Error: " . ($curl_error ?: 'بدون خطا') . "</div>";
    echo "<div class='info'>Response: " . ($response ?: 'بدون پاسخ') . "</div>";
    
    if ($http_code == 200) {
        $response_data = json_decode($response, true);
        if ($response_data && isset($response_data['status']) && $response_data['status'] == 'success') {
            echo "<div class='success'>✅ SMS واقعی با موفقیت ارسال شد!</div>";
        } else {
            echo "<div class='error'>❌ خطا در ارسال SMS واقعی: " . ($response_data['message'] ?? 'خطای نامشخص') . "</div>";
        }
    } else {
        echo "<div class='error'>❌ خطای HTTP: $http_code</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ خطا: " . $e->getMessage() . "</div>";
}

echo "<div style='text-align: center; margin-top: 20px;'>
    <a href='customers.php' class='btn'>👥 مدیریت مشتریان</a>
    <a href='index.php' class='btn'>🏠 صفحه اصلی</a>
</div>";

echo "</div></body></html>";
?>