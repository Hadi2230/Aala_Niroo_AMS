<?php
/**
 * config_no_db.php - نسخه بدون دیتابیس برای تست
 */

// شروع session اگر شروع نشده
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تنظیمات امنیتی
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php-errors.log');

// ایجاد پوشه logs اگر وجود ندارد
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

// تنظیمات زمانzone
date_default_timezone_set('Asia/Tehran');

// تولید token برای جلوگیری از CSRF اگر وجود ندارد
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// متغیرهای پیش‌فرض
$pdo = null;

// توابع پیش‌فرض
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function hasPermission($permission) {
    return true;
}

function is_admin($user_id = null) {
    return true;
}

// تابع ایجاد جداول (فقط برای نمایش)
function createDatabaseTables($pdo) {
    return true;
}

// تابع دریافت کاربران (فقط برای نمایش)
function getUsersForAssignment($pdo) {
    return [
        ['id' => 1, 'username' => 'admin', 'full_name' => 'مدیر سیستم', 'role' => 'admin', 'is_active' => 1],
        ['id' => 2, 'username' => 'user1', 'full_name' => 'کاربر اول', 'role' => 'user', 'is_active' => 1],
        ['id' => 3, 'username' => 'user2', 'full_name' => 'کاربر دوم', 'role' => 'user', 'is_active' => 1]
    ];
}

// تابع ایجاد درخواست (فقط برای نمایش)
function createRequest($pdo, $data) {
    return 1; // ID درخواست جعلی
}

// تابع تولید شماره درخواست (فقط برای نمایش)
function generateRequestNumber($pdo) {
    return 'REQ-' . date('Ymd') . '-001';
}

// تابع آپلود فایل (فقط برای نمایش)
function uploadRequestFile($pdo, $request_id, $file, $upload_dir = 'uploads/requests/') {
    return true;
}

// تابع ایجاد گردش کار (فقط برای نمایش)
function createRequestWorkflow($pdo, $request_id, $assignments) {
    return true;
}

// تابع ایجاد اعلان (فقط برای نمایش)
function createRequestNotification($pdo, $user_id, $request_id, $type, $message) {
    return true;
}

// تابع لاگ (فقط برای نمایش)
function logAction($pdo, $action, $description = '', $severity = 'info', $module = null, $request_data = null, $response_data = null) {
    return true;
}

// تابع تاریخ شمسی
function jalaliDate($date = null, $format = 'Y/m/d') {
    if ($date === null) {
        $date = date('Y-m-d');
    }
    
    // تبدیل ساده (فقط برای نمایش)
    $persian_months = [
        1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر',
        5 => 'مرداد', 6 => 'شهریور', 7 => 'مهر', 8 => 'آبان',
        9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند'
    ];
    
    $persian_days = [
        'Saturday' => 'شنبه', 'Sunday' => 'یکشنبه', 'Monday' => 'دوشنبه',
        'Tuesday' => 'سه‌شنبه', 'Wednesday' => 'چهارشنبه', 'Thursday' => 'پنج‌شنبه', 'Friday' => 'جمعه'
    ];
    
    $timestamp = is_string($date) ? strtotime($date) : $date;
    $year = date('Y', $timestamp);
    $month = date('n', $timestamp);
    $day = date('j', $timestamp);
    $day_name = date('l', $timestamp);
    
    switch ($format) {
        case 'Y/m/d':
            return $year . '/' . $month . '/' . $day;
        case 'j F Y':
            return $day . ' ' . $persian_months[$month] . ' ' . $year;
        case 'l j F Y':
            return $persian_days[$day_name] . ' ' . $day . ' ' . $persian_months[$month] . ' ' . $year;
        default:
            return $year . '/' . $month . '/' . $day;
    }
}

function gregorianToJalaliFromDB($gregorian_date) {
    return jalaliDate($gregorian_date);
}

function jalaliToGregorianForDB($jalali_date) {
    return date('Y-m-d');
}

// تابع تبدیل تاریخ میلادی به شمسی
function gregorian_to_jalali($gy, $gm, $gd) {
    return [$gy, $gm, $gd]; // ساده شده
}

// تابع تبدیل تاریخ شمسی به میلادی
function jalali_to_gregorian($jy, $jm, $jd) {
    return [$jy, $jm, $jd]; // ساده شده
}

// پیام هشدار
echo "<div style='background: #fff3cd; padding: 20px; border: 1px solid #ffeaa7; border-radius: 5px; margin: 20px 0; text-align: center;'>";
echo "<h3 style='color: #856404; margin: 0 0 15px 0;'>⚠️ حالت تست - بدون دیتابیس</h3>";
echo "<p style='margin: 10px 0;'>این نسخه فقط برای تست است. برای استفاده کامل، مشکل MySQL را حل کنید.</p>";
echo "<p style='margin: 10px 0;'><a href='mysql_fix_complete.php' style='background: #ffc107; color: #212529; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>حل مشکل MySQL</a></p>";
echo "</div>";
?>