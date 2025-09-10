<?php
// sms.php - سیستم ارسال پیامک

/**
 * ارسال پیامک
 * @param string $phone شماره تلفن
 * @param string $message متن پیام
 * @return array نتیجه ارسال
 */
function send_sms($phone, $message) {
    // پاکسازی شماره تلفن
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // بررسی فرمت شماره
    if (strlen($phone) < 10) {
        return [
            'success' => false,
            'message' => 'شماره تلفن نامعتبر است'
        ];
    }
    
    // اگر شماره با 0 شروع می‌شود، 98 اضافه کن
    if (substr($phone, 0, 1) === '0') {
        $phone = '98' . substr($phone, 1);
    }
    
    // اگر شماره با 98 شروع نمی‌شود، اضافه کن
    if (substr($phone, 0, 2) !== '98') {
        $phone = '98' . $phone;
    }
    
    try {
        // در اینجا باید API واقعی پیامک را پیاده‌سازی کنید
        // برای مثال از Kavenegar، پیامک، یا سایر سرویس‌ها
        
        // شبیه‌سازی ارسال پیامک (برای تست)
        $result = simulate_sms_sending($phone, $message);
        
        if ($result) {
            // ثبت در لاگ
            error_log("SMS sent successfully to {$phone}: {$message}");
            
            return [
                'success' => true,
                'message' => 'پیامک با موفقیت ارسال شد',
                'phone' => $phone,
                'message_id' => uniqid()
            ];
        } else {
            return [
                'success' => false,
                'message' => 'خطا در ارسال پیامک'
            ];
        }
        
    } catch (Exception $e) {
        error_log("SMS sending error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'خطا در ارسال پیامک: ' . $e->getMessage()
        ];
    }
}

/**
 * شبیه‌سازی ارسال پیامک (برای تست)
 * در محیط تولید باید با API واقعی جایگزین شود
 */
function simulate_sms_sending($phone, $message) {
    // شبیه‌سازی تاخیر شبکه
    usleep(500000); // 0.5 ثانیه
    
    // شبیه‌سازی موفقیت (90% موفقیت)
    return rand(1, 10) <= 9;
}

/**
 * اعتبارسنجی شماره تلفن
 */
function validate_phone_number($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // بررسی طول شماره
    if (strlen($phone) < 10 || strlen($phone) > 15) {
        return false;
    }
    
    // بررسی فرمت شماره ایرانی
    if (strlen($phone) === 11 && substr($phone, 0, 1) === '0') {
        return true;
    }
    
    if (strlen($phone) === 13 && substr($phone, 0, 2) === '98') {
        return true;
    }
    
    return false;
}

/**
 * فرمت کردن شماره تلفن برای نمایش
 */
function format_phone_number($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    if (strlen($phone) === 11 && substr($phone, 0, 1) === '0') {
        return $phone;
    }
    
    if (strlen($phone) === 13 && substr($phone, 0, 2) === '98') {
        return '0' . substr($phone, 2);
    }
    
    return $phone;
}

/**
 * بررسی وضعیت ارسال پیامک
 */
function check_sms_status($message_id) {
    // در اینجا باید API سرویس پیامک را فراخوانی کنید
    // برای شبیه‌سازی:
    return [
        'status' => 'delivered',
        'delivery_time' => date('Y-m-d H:i:s')
    ];
}

/**
 * دریافت گزارش ارسال پیامک‌ها
 */
function get_sms_report($start_date = null, $end_date = null) {
    global $pdo;
    
    $where_conditions = [];
    $params = [];
    
    if ($start_date) {
        $where_conditions[] = "created_at >= ?";
        $params[] = $start_date;
    }
    
    if ($end_date) {
        $where_conditions[] = "created_at <= ?";
        $params[] = $end_date;
    }
    
    $where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    $sql = "SELECT * FROM sms_logs {$where_clause} ORDER BY created_at DESC";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("SMS report error: " . $e->getMessage());
        return [];
    }
}

/**
 * ثبت لاگ ارسال پیامک
 */
function log_sms($phone, $message, $status, $message_id = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO sms_logs (phone, message, status, message_id, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$phone, $message, $status, $message_id]);
    } catch (PDOException $e) {
        error_log("SMS log error: " . $e->getMessage());
    }
}
?>