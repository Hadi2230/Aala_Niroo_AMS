<?php
// test_real_sms.php - تست API واقعی SMS
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html lang='fa' dir='rtl'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>تست API واقعی SMS</title>
    <style>
        body { font-family: Tahoma, Arial, sans-serif; padding: 20px; background: #f8f9fa; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #27ae60; background: #d5f4e6; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: #e74c3c; background: #fadbd8; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { color: #3498db; background: #d6eaf8; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .btn { background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px; }
        h1 { text-align: center; color: #2c3e50; }
        .code { background: #f8f9fa; padding: 10px; border-radius: 5px; font-family: monospace; margin: 10px 0; }
        .form-group { margin: 15px 0; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>📱 تست API واقعی SMS</h1>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = $_POST['phone'] ?? '';
    $message = $_POST['message'] ?? '';
    $api_key = $_POST['api_key'] ?? '';
    $line_number = $_POST['line_number'] ?? '';
    
    if ($phone && $message && $api_key && $line_number) {
        echo "<div class='info'>در حال تست ارسال SMS...</div>";
        
        // نرمال‌سازی شماره تلفن
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) == 11 && substr($phone, 0, 2) == '09') {
            $phone = '98' . substr($phone, 1);
        }
        
        echo "<div class='code'>شماره نرمال‌سازی شده: $phone</div>";
        
        // تست API واقعی
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api2.ippanel.com/api/v1/sms/send/webservice/single');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'recipient' => $phone,
            'message' => $message,
            'sender' => $line_number
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $api_key
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
            $response_data = json_decode($response, true);
            if ($response_data && isset($response_data['status']) && $response_data['status'] == 'success') {
                echo "<div class='success'>✅ SMS با موفقیت ارسال شد!</div>";
                echo "<div class='info'>Message ID: " . ($response_data['messageId'] ?? 'نامشخص') . "</div>";
            } else {
                echo "<div class='error'>❌ خطا در ارسال SMS: " . ($response_data['message'] ?? 'خطای نامشخص') . "</div>";
            }
        } else {
            echo "<div class='error'>❌ خطای HTTP: $http_code</div>";
        }
    } else {
        echo "<div class='error'>❌ لطفاً تمام فیلدها را پر کنید</div>";
    }
}

echo "
        <form method='post'>
            <div class='form-group'>
                <label>شماره تلفن:</label>
                <input type='text' name='phone' value='09123456789' placeholder='09123456789' required>
            </div>
            
            <div class='form-group'>
                <label>متن پیام:</label>
                <textarea name='message' rows='3' placeholder='متن پیامک...' required>تست ارسال پیامک از سیستم مدیریت دارایی‌ها</textarea>
            </div>
            
            <div class='form-group'>
                <label>API Key:</label>
                <input type='text' name='api_key' value='OWZlNDE1MjctZWViMi00ZjM2LThjMDItMTAyMTc3NTI3OGFiOWFlNjkzYzIzYTk0OTRhNzVjNzIzMWRlZTY4MTE1Yzc=' placeholder='API Key' required>
            </div>
            
            <div class='form-group'>
                <label>شماره خط:</label>
                <input type='text' name='line_number' value='5000125475' placeholder='5000125475' required>
            </div>
            
            <button type='submit' class='btn'>ارسال SMS</button>
        </form>
        
        <div style='text-align: center; margin-top: 20px;'>
            <a href='debug_sms.php' class='btn'>🔍 دیباگ کامل</a>
            <a href='test_sms.php' class='btn'>🔄 تست ساده</a>
            <a href='customers.php' class='btn'>👥 مدیریت مشتریان</a>
        </div>
    </div>
</body>
</html>";
?>