<?php
/**
 * تنظیمات سیستم ایمیل
 * Email System Configuration
 */

// تنظیمات SMTP
define('SMTP_HOST', 'smtp.gmail.com'); // یا smtp.yourdomain.com
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com'); // ایمیل فرستنده
define('SMTP_PASSWORD', 'your-app-password'); // رمز عبور برنامه
define('SMTP_ENCRYPTION', 'tls'); // tls یا ssl
define('SMTP_FROM_EMAIL', 'your-email@gmail.com');
define('SMTP_FROM_NAME', 'سیستم مدیریت اعلا نیرو');

// تنظیمات عمومی
define('APP_NAME', 'سیستم مدیریت اعلا نیرو');
define('APP_URL', 'http://localhost/Aala_Niroo_AMS'); // آدرس اصلی برنامه
define('ADMIN_EMAIL', 'admin@aalaniroo.com'); // ایمیل مدیر سیستم

// تنظیمات ایمیل
define('EMAIL_ENABLED', true); // فعال/غیرفعال کردن ارسال ایمیل
define('EMAIL_DEBUG', false); // حالت دیباگ

/**
 * ارسال ایمیل با استفاده از PHPMailer
 */
function sendEmail($to, $subject, $body, $isHTML = true) {
    if (!EMAIL_ENABLED) {
        return false;
    }
    
    try {
        // بارگذاری PHPMailer
        require_once 'vendor/autoload.php';
        
        $mail = new PHPMailer(true);
        
        // تنظیمات SMTP
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';
        
        // تنظیمات فرستنده
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to);
        
        // تنظیمات ایمیل
        $mail->isHTML($isHTML);
        $mail->Subject = $subject;
        $mail->Body = $body;
        
        // ارسال ایمیل
        $result = $mail->send();
        
        if (EMAIL_DEBUG) {
            error_log("Email sent to: $to, Subject: $subject, Result: " . ($result ? 'Success' : 'Failed'));
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return false;
    }
}

/**
 * ارسال ایمیل خوش‌آمدگویی به کاربر جدید
 */
function sendWelcomeEmail($username, $email, $full_name, $password = null) {
    $subject = "خوش‌آمدید به " . APP_NAME;
    
    $login_url = APP_URL . "/login.php";
    $dashboard_url = APP_URL . "/dashboard.php";
    
    $body = getWelcomeEmailTemplate($username, $email, $full_name, $password, $login_url, $dashboard_url);
    
    return sendEmail($email, $subject, $body);
}

/**
 * قالب ایمیل خوش‌آمدگویی
 */
function getWelcomeEmailTemplate($username, $email, $full_name, $password, $login_url, $dashboard_url) {
    $password_info = $password ? "
        <div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #28a745;'>
            <h4 style='color: #28a745; margin: 0 0 10px 0;'>🔑 اطلاعات ورود شما:</h4>
            <p style='margin: 5px 0;'><strong>نام کاربری:</strong> $username</p>
            <p style='margin: 5px 0;'><strong>رمز عبور:</strong> $password</p>
            <p style='margin: 5px 0; color: #dc3545; font-size: 14px;'><strong>⚠️ لطفاً پس از ورود، رمز عبور خود را تغییر دهید.</strong></p>
        </div>
    " : "
        <div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #17a2b8;'>
            <h4 style='color: #17a2b8; margin: 0 0 10px 0;'>🔐 اطلاعات ورود:</h4>
            <p style='margin: 5px 0;'><strong>نام کاربری:</strong> $username</p>
            <p style='margin: 5px 0; color: #6c757d; font-size: 14px;'>رمز عبور قبلاً توسط مدیر سیستم تنظیم شده است.</p>
        </div>
    ";
    
    return "
    <!DOCTYPE html>
    <html dir='rtl' lang='fa'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>خوش‌آمدید به " . APP_NAME . "</title>
        <style>
            body { font-family: 'Vazirmatn', Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f4f4f4; }
            .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%); color: white; padding: 30px; text-align: center; }
            .header h1 { margin: 0; font-size: 28px; font-weight: bold; }
            .header p { margin: 10px 0 0 0; opacity: 0.9; font-size: 16px; }
            .content { padding: 30px; }
            .welcome-text { font-size: 18px; color: #2c3e50; margin-bottom: 25px; text-align: center; }
            .info-section { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
            .info-section h3 { color: #2c3e50; margin: 0 0 15px 0; font-size: 20px; }
            .info-item { margin: 10px 0; padding: 8px 0; border-bottom: 1px solid #e9ecef; }
            .info-item:last-child { border-bottom: none; }
            .info-label { font-weight: bold; color: #495057; display: inline-block; width: 120px; }
            .info-value { color: #2c3e50; }
            .button-container { text-align: center; margin: 30px 0; }
            .btn { display: inline-block; padding: 12px 30px; margin: 0 10px; text-decoration: none; border-radius: 25px; font-weight: bold; transition: all 0.3s ease; }
            .btn-primary { background: linear-gradient(135deg, #3498db, #2980b9); color: white; }
            .btn-success { background: linear-gradient(135deg, #27ae60, #229954); color: white; }
            .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
            .footer { background: #2c3e50; color: white; padding: 20px; text-align: center; font-size: 14px; }
            .footer a { color: #3498db; text-decoration: none; }
            .security-note { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 8px; margin: 20px 0; }
            .features { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 25px 0; }
            .feature { background: white; padding: 20px; border-radius: 8px; border: 1px solid #e9ecef; text-align: center; }
            .feature-icon { font-size: 30px; margin-bottom: 10px; }
            .feature h4 { color: #2c3e50; margin: 10px 0; }
            .feature p { color: #6c757d; font-size: 14px; margin: 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🎉 خوش‌آمدید!</h1>
                <p>حساب کاربری شما در " . APP_NAME . " ایجاد شد</p>
            </div>
            
            <div class='content'>
                <div class='welcome-text'>
                    سلام <strong>$full_name</strong> عزیز،<br>
                    به خانواده بزرگ " . APP_NAME . " خوش‌آمدید! 🚀
                </div>
                
                <div class='info-section'>
                    <h3>📋 اطلاعات حساب کاربری شما</h3>
                    <div class='info-item'>
                        <span class='info-label'>نام کامل:</span>
                        <span class='info-value'>$full_name</span>
                    </div>
                    <div class='info-item'>
                        <span class='info-label'>نام کاربری:</span>
                        <span class='info-value'>$username</span>
                    </div>
                    <div class='info-item'>
                        <span class='info-label'>ایمیل:</span>
                        <span class='info-value'>$email</span>
                    </div>
                    <div class='info-item'>
                        <span class='info-label'>تاریخ ایجاد:</span>
                        <span class='info-value'>" . date('Y/m/d H:i') . "</span>
                    </div>
                </div>
                
                $password_info
                
                <div class='features'>
                    <div class='feature'>
                        <div class='feature-icon'>🏢</div>
                        <h4>مدیریت دارایی‌ها</h4>
                        <p>ثبت و مدیریت ژنراتورها، موتور برق و قطعات</p>
                    </div>
                    <div class='feature'>
                        <div class='feature-icon'>🔧</div>
                        <h4>تعمیرات و نگهداری</h4>
                        <p>برنامه‌ریزی و پیگیری تعمیرات دستگاه‌ها</p>
                    </div>
                    <div class='feature'>
                        <div class='feature-icon'>📊</div>
                        <h4>گزارش‌گیری</h4>
                        <p>گزارش‌های جامع و تحلیل عملکرد</p>
                    </div>
                    <div class='feature'>
                        <div class='feature-icon'>💬</div>
                        <h4>سیستم پیام</h4>
                        <p>ارتباط داخلی و مدیریت تیکت‌ها</p>
                    </div>
                </div>
                
                <div class='button-container'>
                    <a href='$login_url' class='btn btn-primary'>🔐 ورود به سیستم</a>
                    <a href='$dashboard_url' class='btn btn-success'>📊 داشبورد</a>
                </div>
                
                <div class='security-note'>
                    <strong>🔒 نکات امنیتی:</strong><br>
                    • رمز عبور خود را محرمانه نگه دارید<br>
                    • از مرورگرهای به‌روز استفاده کنید<br>
                    • در صورت فراموشی رمز عبور، با مدیر سیستم تماس بگیرید<br>
                    • این ایمیل را در جای امن نگه دارید
                </div>
            </div>
            
            <div class='footer'>
                <p>این ایمیل به صورت خودکار ارسال شده است. لطفاً پاسخ ندهید.</p>
                <p>© " . date('Y') . " " . APP_NAME . " - تمامی حقوق محفوظ است</p>
                <p>برای پشتیبانی: <a href='mailto:" . ADMIN_EMAIL . "'>" . ADMIN_EMAIL . "</a></p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * ارسال ایمیل اطلاع‌رسانی به مدیر
 */
function sendAdminNotification($username, $email, $full_name) {
    $subject = "کاربر جدید در " . APP_NAME;
    
    $body = "
    <!DOCTYPE html>
    <html dir='rtl' lang='fa'>
    <head>
        <meta charset='UTF-8'>
        <title>اطلاع‌رسانی کاربر جدید</title>
        <style>
            body { font-family: 'Vazirmatn', Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .info-item { margin: 10px 0; padding: 8px 0; border-bottom: 1px solid #eee; }
            .info-label { font-weight: bold; color: #2c3e50; display: inline-block; width: 120px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>👤 کاربر جدید ثبت شد</h2>
            </div>
            <div class='content'>
                <p>کاربر جدیدی در سیستم ثبت شده است:</p>
                <div class='info-item'>
                    <span class='info-label'>نام کامل:</span>
                    <span>$full_name</span>
                </div>
                <div class='info-item'>
                    <span class='info-label'>نام کاربری:</span>
                    <span>$username</span>
                </div>
                <div class='info-item'>
                    <span class='info-label'>ایمیل:</span>
                    <span>$email</span>
                </div>
                <div class='info-item'>
                    <span class='info-label'>تاریخ ثبت:</span>
                    <span>" . date('Y/m/d H:i') . "</span>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail(ADMIN_EMAIL, $subject, $body);
}
?>