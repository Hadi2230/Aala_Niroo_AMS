<?php
/**
 * Step 3: تنظیم فایل پیکربندی
 */

if ($_POST) {
    $admin_username = $_POST['admin_username'] ?? 'admin';
    $admin_password = $_POST['admin_password'] ?? '';
    $admin_email = $_POST['admin_email'] ?? '';
    $site_name = $_POST['site_name'] ?? 'Aala Niroo AMS';
    
    if (empty($admin_password)) {
        $error = 'رمز عبور ادمین نمی‌تواند خالی باشد.';
    } else {
        try {
            // ایجاد فایل config.php
            $db_config = $_SESSION['db_config'];
            $config_content = "<?php
/**
 * config.php - تنظیمات سیستم Aala Niroo AMS
 * این فایل به صورت خودکار ایجاد شده است.
 */

// شروع session اگر شروع نشده
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تنظیمات امنیتی
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php-errors.log');

// تنظیمات دیتابیس
\$host = '{$db_config['host']}:{$db_config['port']}';
\$dbname = '{$db_config['database']}';
\$username = '{$db_config['username']}';
\$password = '{$db_config['password']}';

// تنظیمات زمانzone
date_default_timezone_set('Asia/Tehran');

try {
    \$pdo = new PDO(\"mysql:host=\$host;dbname=\$dbname;charset=utf8mb4\", \$username, \$password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => \"SET NAMES utf8mb4 COLLATE utf8mb4_persian_ci\"
    ]);
} catch (PDOException \$e) {
    error_log(\"[\" . date('Y-m-d H:i:s') . \"] خطا در اتصال به دیتابیس: \" . \$e->getMessage());
    die(\"<div style='text-align: center; padding: 50px; font-family: Tahoma;'>
        <h2>خطا در اتصال به سیستم</h2>
        <p>لطفاً چند دقیقه دیگر تلاش کنید یا با پشتیبانی تماس بگیرید.</p>
        <p><small>خطای سیستمی: \" . \$e->getMessage() . \"</small></p>
        </div>\");
}

// تولید token برای جلوگیری از CSRF
if (!isset(\$_SESSION['csrf_token'])) {
    \$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// تنظیمات سایت
define('SITE_NAME', '$site_name');
define('SITE_URL', 'http://' . \$_SERVER['HTTP_HOST'] . dirname(\$_SERVER['SCRIPT_NAME']));

// ایجاد پوشه‌های مورد نیاز
if (!is_dir(__DIR__ . '/uploads')) {
    mkdir(__DIR__ . '/uploads', 0755, true);
    mkdir(__DIR__ . '/uploads/requests', 0755, true);
    mkdir(__DIR__ . '/uploads/assets', 0755, true);
    mkdir(__DIR__ . '/uploads/assignments', 0755, true);
    mkdir(__DIR__ . '/uploads/visit_documents', 0755, true);
    mkdir(__DIR__ . '/uploads/visit_photos', 0755, true);
}

if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

// ایجاد کاربر ادمین
try {
    \$stmt = \$pdo->prepare(\"SELECT COUNT(*) FROM users WHERE username = ?\");
    \$stmt->execute([\$admin_username]);
    
    if (\$stmt->fetchColumn() == 0) {
        \$hashed_password = password_hash('$admin_password', PASSWORD_DEFAULT);
        \$stmt = \$pdo->prepare(\"INSERT INTO users (username, password, full_name, role, status) VALUES (?, ?, ?, 'ادمین', 'active')\");
        \$stmt->execute([\$admin_username, \$hashed_password, 'مدیر سیستم']);
    }
} catch (Exception \$e) {
    error_log(\"خطا در ایجاد کاربر ادمین: \" . \$e->getMessage());
}

// توابع کمکی
function logAction(\$pdo, \$user_id, \$action, \$details = '') {
    try {
        \$stmt = \$pdo->prepare(\"INSERT INTO system_logs (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)\");
        \$stmt->execute([\$user_id, \$action, \$details, \$_SERVER['REMOTE_ADDR'] ?? '', \$_SERVER['HTTP_USER_AGENT'] ?? '']);
    } catch (Exception \$e) {
        error_log(\"خطا در ثبت لاگ: \" . \$e->getMessage());
    }
}

function is_admin() {
    return isset(\$_SESSION['user_role']) && \$_SESSION['user_role'] === 'ادمین';
}

function sanitizeInput(\$input) {
    return htmlspecialchars(trim(\$input), ENT_QUOTES, 'UTF-8');
}

// توابع تبدیل تاریخ
function gregorian_to_jalali(\$gy, \$gm, \$gd) {
    \$g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    \$jy = (\$gy <= 1600) ? 0 : 979;
    \$gy -= (\$gy <= 1600) ? 621 : 1600;
    \$gy2 = (\$gm > 2) ? (\$gy + 1) : \$gy;
    \$days = (365 * \$gy) + ((int)((\$gy2 + 3) / 4)) - ((int)((\$gy2 + 99) / 100)) + ((int)((\$gy2 + 399) / 400)) - 80 + \$gd + \$g_d_m[\$gm - 1];
    \$jy += 33 * ((int)(\$days / 12053));
    \$days %= 12053;
    \$jy += 4 * ((int)(\$days / 1461));
    \$days %= 1461;
    \$jy += ((int)((\$days - 1) / 365));
    if (\$days > 365) \$days = (\$days - 1) % 365;
    \$jm = (\$days < 186) ? 1 + (int)(\$days / 31) : 7 + (int)((\$days - 186) / 30);
    \$jd = 1 + ((\$days < 186) ? (\$days % 31) : ((\$days - 186) % 30));
    return [\$jy, \$jm, \$jd];
}

function jalali_to_gregorian(\$jy, \$jm, \$jd) {
    \$jy += 1595;
    \$days = -355668 + (365 * \$jy) + (((int)(\$jy / 33)) * 8) + ((int)((((\$jy % 33) + 3) / 4))) + \$jd + ((\$jm < 7) ? (\$jm - 1) * 31 : ((\$jm - 7) * 30) + 186);
    \$gy = 400 * ((int)(\$days / 146097));
    \$days %= 146097;
    if (\$days > 36524) {
        \$gy += 100 * ((int)(--\$days / 36524));
        \$days %= 36524;
        if (\$days >= 365) \$days++;
    }
    \$gy += 4 * ((int)(\$days / 1461));
    \$days %= 1461;
    if (\$days > 365) {
        \$gy += (int)((\$days - 1) / 365);
        \$days = (\$days - 1) % 365;
    }
    \$gd = \$days + 1;
    \$sal_a = [0, 31, ((\$gy % 4 == 0 && \$gy % 100 != 0) || (\$gy % 400 == 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    for (\$gm = 0; \$gm < 13 && \$gd > \$sal_a[\$gm]; \$gm++) \$gd -= \$sal_a[\$gm];
    return [\$gy, \$gm, \$gd];
}

function jalaliToGregorianForDB(\$jalali_date) {
    if (empty(\$jalali_date)) return null;
    \$parts = explode('/', \$jalali_date);
    if (count(\$parts) != 3) return null;
    list(\$jy, \$jm, \$jd) = array_map('intval', \$parts);
    list(\$gy, \$gm, \$gd) = jalali_to_gregorian(\$jy, \$jm, \$jd);
    return sprintf('%04d-%02d-%02d', \$gy, \$gm, \$gd);
}

function gregorianToJalaliFromDB(\$gregorian_date) {
    if (empty(\$gregorian_date)) return '';
    \$date = new DateTime(\$gregorian_date);
    list(\$jy, \$jm, \$jd) = gregorian_to_jalali(\$date->format('Y'), \$date->format('n'), \$date->format('j'));
    return sprintf('%04d/%02d/%02d', \$jy, \$jm, \$jd);
}

function jalaliDate(\$date = null, \$format = 'Y/m/d') {
    if (\$date === null) \$date = time();
    if (is_string(\$date)) \$date = strtotime(\$date);
    \$j_date = gregorian_to_jalali(date('Y', \$date), date('n', \$date), date('j', \$date));
    return sprintf('%04d/%02d/%02d', \$j_date[0], \$j_date[1], \$j_date[2]);
}

function jalali_format(\$datetime, \$format = 'Y/m/d H:i', \$use_fa_digits = true) {
    if (empty(\$datetime)) return '';
    \$date = new DateTime(\$datetime);
    list(\$jy, \$jm, \$jd) = gregorian_to_jalali(\$date->format('Y'), \$date->format('n'), \$date->format('j'));
    \$formatted = sprintf('%04d/%02d/%02d %02d:%02d', \$jy, \$jm, \$jd, \$date->format('H'), \$date->format('i'));
    return \$use_fa_digits ? en2fa_digits(\$formatted) : \$formatted;
}

function en2fa_digits(\$input) {
    \$en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    \$fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    return str_replace(\$en, \$fa, \$input);
}
?>";

            file_put_contents('config.php', $config_content);
            
            // ایجاد کاربر ادمین
            $pdo = new PDO("mysql:host={$db_config['host']};port={$db_config['port']};dbname={$db_config['database']};charset=utf8mb4", 
                          $db_config['username'], $db_config['password']);
            
            $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role, status) VALUES (?, ?, ?, 'ادمین', 'active')");
            $stmt->execute([$admin_username, $hashed_password, 'مدیر سیستم']);
            
            $_SESSION['install_complete'] = true;
            header('Location: ?step=4');
            exit();
            
        } catch (Exception $e) {
            $error = 'خطا در ایجاد فایل پیکربندی: ' . $e->getMessage();
        }
    }
}
?>

<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">
            <i class="fas fa-cog me-2"></i>
            مرحله 3: تنظیم پیکربندی سیستم
        </h5>
    </div>
    <div class="card-body">
        <form method="POST">
            <div class="mb-3">
                <label for="site_name" class="form-label">نام سایت</label>
                <input type="text" class="form-control" id="site_name" name="site_name" value="Aala Niroo AMS" required>
            </div>
            
            <div class="mb-3">
                <label for="admin_username" class="form-label">نام کاربری ادمین</label>
                <input type="text" class="form-control" id="admin_username" name="admin_username" value="admin" required>
            </div>
            
            <div class="mb-3">
                <label for="admin_password" class="form-label">رمز عبور ادمین</label>
                <input type="password" class="form-control" id="admin_password" name="admin_password" required>
                <div class="form-text">رمز عبور باید حداقل 6 کاراکتر باشد.</div>
            </div>
            
            <div class="mb-3">
                <label for="admin_email" class="form-label">ایمیل ادمین (اختیاری)</label>
                <input type="email" class="form-control" id="admin_email" name="admin_email">
            </div>
            
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <strong>نکته:</strong> این اطلاعات برای ایجاد حساب کاربری ادمین استفاده می‌شود.
            </div>
            
            <div class="text-center">
                <a href="?step=2" class="btn btn-secondary me-2">
                    <i class="fas fa-arrow-right me-2"></i>
                    مرحله قبل
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>
                    ایجاد پیکربندی
                </button>
            </div>
        </form>
    </div>
</div>