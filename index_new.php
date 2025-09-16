<?php
// صفحه اصلی - نسخه ساده و بدون خطا

// بررسی وجود فایل config
if (!file_exists('config.php')) {
    // اگر config.php وجود ندارد، از config_new.php استفاده کن
    if (file_exists('config_new.php')) {
        require_once 'config_new.php';
    } else {
        die('فایل تنظیمات یافت نشد!');
    }
} else {
    require_once 'config.php';
}

// بررسی اتصال دیتابیس
if (!$pdo) {
    ?>
    <!DOCTYPE html>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>خطا در اتصال</title>
        <style>
            body { font-family: Tahoma, Arial, sans-serif; background: #f8f9fa; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; }
            .error { color: #e74c3c; background: #fadbd8; padding: 15px; border-radius: 8px; margin: 20px 0; }
            .btn { background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>⚠️ خطا در اتصال به دیتابیس</h1>
            <div class="error">
                <p>لطفاً مطمئن شوید که:</p>
                <ul style="text-align: right;">
                    <li>XAMPP یا WAMP در حال اجرا است</li>
                    <li>MySQL سرویس فعال است</li>
                    <li>پورت 3306 در دسترس است</li>
                </ul>
            </div>
            <a href="test.php" class="btn">🔍 تست اتصال</a>
            <a href="setup_database.php" class="btn">🔧 راه‌اندازی دیتابیس</a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// بررسی احراز هویت
if (isset($_SESSION['user_id'])) {
    // کاربر وارد شده - هدایت به داشبورد
    header("Location: dashboard.php");
    exit();
} else {
    // کاربر وارد نشده - هدایت به ورود
    header("Location: login.php");
    exit();
}
?>