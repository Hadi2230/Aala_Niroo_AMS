<?php
// sms.php - سیستم ارسال پیامک

/**
 * تابع نرمال‌سازی شماره تلفن
 */
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

/**
 * تابع شبیه‌سازی ارسال پیامک برای محیط تست
 */
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

/**
 * تابع ارسال پیامک واقعی
 */
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

/**
 * تابع اصلی ارسال پیامک
 * در حال حاضر از mock استفاده می‌کند
 */
function send_sms($phone_number, $message) {
    // موقتاً از تابع mock استفاده می‌کنیم
    return send_sms_mock($phone_number, $message);
    
    // برای استفاده از API واقعی، خط زیر را uncomment کنید:
    // return send_sms_real($phone_number, $message);
}
?>