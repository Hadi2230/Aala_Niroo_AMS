<?php
// test_sms_final.php - تست نهایی و کامل سیستم SMS
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

// تعریف توابع در همین فایل
function normalize_phone_number($phone) {
    if (empty($phone)) {
        return false;
    }
    
    // حذف همه کاراکترهای غیر عددی
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // اگر شماره با 0 شروع می‌شود ولی 98 ندارد
    if (strlen($phone) == 11 && substr($phone, 0, 2) == '09') {
        $phone = '98' . substr($phone, 1);
    }
    // اگر شماره با 9 شروع می‌شود (بدون 0)
    elseif (strlen($phone) == 10 && substr($phone, 0, 1) == '9') {
        $phone = '98' . $phone;
    }
    
    // بررسی نهایی که شماره معتبر است
    if (strlen($phone) === 12 && substr($phone, 0, 2) === '98') {
        return $phone;
    }
    
    return false;
}

function send_sms_mock($phone_number, $message) {
    // شبیه‌سازی تاخیر در ارسال
    sleep(1);
    
    // شبیه‌سازی موفقیت آمیز بودن ارسال (80% احتمال موفقیت)
    $success = rand(0, 100) > 20;
    
    if ($success) {
        return [
            'success' => true,
            'response' => [
                'status' => 'success',
                'message' => 'پیامک با موفقیت ارسال شد',
                'messageId' => 'MSG_' . time() . '_' . rand(1000, 9999)
            ],
            'message_id' => 'MSG_' . time() . '_' . rand(1000, 9999)
        ];
    } else {
        return [
            'success' => false,
            'error' => 'خطا در ارسال پیامک به دلیل مشکل شبکه',
            'response' => [
                'status' => 'failed',
                'message' => 'ارتباط با سرویس پیامک برقرار نشد'
            ]
        ];
    }
}

function send_sms_real($phone_number, $message) {
    // پیکربندی پنل پیامکی
    $config = [
        'api_key' => 'OWZlNDE1MjctZWViMi00ZjM2LThjMDItMTAyMTc3NTI3OGFiOWFlNjkzYzIzYTk0OTRhNzVjNzIzMWRlZTY4MTE1Yzc=',
        'api_url' => 'https://api2.ippanel.com/api/v1/sms/send/webservice/single',
        'line_number' => '5000125475',
    ];
    
    try {
        // نرمال‌سازی شماره تلفن
        $phone_number = normalize_phone_number($phone_number);
        
        if (!$phone_number) {
            return [
                'success' => false,
                'error' => 'شماره تلفن معتبر نیست'
            ];
        }
        
        // پارامترهای API
        $params = [
            'recipient' => $phone_number,
            'message' => $message,
            'sender' => $config['line_number'],
        ];
        
        // ارسال درخواست
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $config['api_url']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $config['api_key']
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        // بررسی پاسخ
        if ($http_code == 200) {
            $response_data = json_decode($response, true);
            
            if ($response_data && isset($response_data['status']) && $response_data['status'] == 'success') {
                return [
                    'success' => true,
                    'response' => $response_data,
                    'message_id' => $response_data['messageId'] ?? null
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $response_data['message'] ?? 'خطا در ارسال پیامک',
                    'response' => $response_data
                ];
            }
        } else {
            return [
                'success' => false,
                'error' => "خطای HTTP: $http_code - $curl_error",
                'response' => $response
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'خطای سیستمی: ' . $e->getMessage()
        ];
    }
}

function send_sms($phone_number, $message) {
    // موقتاً از تابع mock استفاده می‌کنیم
    return send_sms_mock($phone_number, $message);
}

try {
    echo "<div class='info'>در حال تست سیستم SMS...</div>";
    
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
    
    // تست در سیستم مشتریان
    echo "<h3>6. تست در سیستم مشتریان:</h3>";
    echo "<div class='info'>برای تست کامل، یک مشتری جدید اضافه کنید و نوع اطلاع‌رسانی را SMS انتخاب کنید.</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ خطا: " . $e->getMessage() . "</div>";
    echo "<div class='error'>فایل: " . $e->getFile() . "</div>";
    echo "<div class='error'>خط: " . $e->getLine() . "</div>";
}

echo "<div style='text-align: center; margin-top: 20px;'>
    <a href='customers.php' class='btn'>👥 مدیریت مشتریان</a>
    <a href='index.php' class='btn'>🏠 صفحه اصلی</a>
</div>";

echo "</div></body></html>";
?>