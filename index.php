<?php
// صفحه اصلی سیستم مدیریت دارایی‌های شرکت اعلا نیرو
session_start();

// بررسی اینکه آیا کاربر وارد شده است یا نه
if (isset($_SESSION['user_id'])) {
    // اگر کاربر وارد شده، به داشبورد هدایت کن
    header('Location: dashboard.php');
    exit();
} else {
    // اگر کاربر وارد نشده، به صفحه ورود هدایت کن
    header('Location: login.php');
    exit();
}
?>