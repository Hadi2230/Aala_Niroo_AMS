<?php
// test_api_key.php - تست API Key
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html lang='fa' dir='rtl'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>تست API Key</title>
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
        .form-group input { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔑 تست API Key</h1>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $api_key = $_POST['api_key'] ?? '';
    $line_number = $_POST['line_number'] ?? '';
    $test_phone = $_POST['test_phone'] ?? '';
    $test_message = $_POST['test_message'] ?? '';
    
    if ($api_key && $line_number && $test_phone && $test_message) {
        echo "<div class='info'>در حال تست API Key...</div>";
        
        // نرمال‌سازی شماره تلفن
        $phone = preg_replace('/[^0-9]/', '', $test_phone);
        if (strlen($phone) == 11 && substr($phone, 0, 2) == '09') {
            $phone = '98' . substr($phone, 1);
        }
        
        echo "<div class='code'>";
        echo "API Key: " . substr($api_key, 0, 20) . "...<br>";
        echo "Line Number: $line_number<br>";
        echo "Phone: $phone<br>";
        echo "Message: $test_message<br>";
        echo "</div>";
        
        // تست API
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api2.ippanel.com/api/v1/sms/send/webservice/single');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'recipient' => $phone,
            'message' => $test_message,
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
                echo "<div class='success'>✅ API Key معتبر است! SMS ارسال شد.</div>";
                echo "<div class='info'>Message ID: " . ($response_data['messageId'] ?? 'نامشخص') . "</div>";
            } else {
                echo "<div class='error'>❌ API Key معتبر است اما خطا در ارسال: " . ($response_data['message'] ?? 'خطای نامشخص') . "</div>";
            }
        } elseif ($http_code == 401) {
            echo "<div class='error'>❌ API Key نامعتبر است! لطفاً کلید جدید دریافت کنید.</div>";
        } elseif ($http_code == 403) {
            echo "<div class='error'>❌ دسترسی غیرمجاز! ممکن است API Key منقضی شده باشد.</div>";
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
                <label>API Key:</label>
                <input type='text' name='api_key' value='OWZlNDE1MjctZWViMi00ZjM2LThjMDItMTAyMTc3NTI3OGFiOWFlNjkzYzIzYTk0OTRhNzVjNzIzMWRlZTY4MTE1Yzc=' placeholder='API Key' required>
            </div>
            
            <div class='form-group'>
                <label>شماره خط:</label>
                <input type='text' name='line_number' value='5000125475' placeholder='5000125475' required>
            </div>
            
            <div class='form-group'>
                <label>شماره تلفن تست:</label>
                <input type='text' name='test_phone' value='09123456789' placeholder='09123456789' required>
            </div>
            
            <div class='form-group'>
                <label>متن پیام:</label>
                <input type='text' name='test_message' value='تست API Key' placeholder='متن پیام' required>
            </div>
            
            <button type='submit' class='btn'>تست API Key</button>
        </form>
        
        <div class='info'>
            <h3>راهنمای دریافت API Key:</h3>
            <ol>
                <li>وارد سایت <a href='https://ippanel.com' target='_blank'>ippanel.com</a> شوید</li>
                <li>وارد پنل کاربری شوید</li>
                <li>به بخش API → کلیدهای API بروید</li>
                <li>کلید جدید ایجاد کنید</li>
                <li>کلید را در فیلد بالا وارد کنید</li>
            </ol>
        </div>
        
        <div style='text-align: center; margin-top: 20px;'>
            <a href='test_sms_final.php' class='btn'>🔄 تست کامل SMS</a>
            <a href='customers.php' class='btn'>👥 مدیریت مشتریان</a>
        </div>
    </div>
</body>
</html>";
?>