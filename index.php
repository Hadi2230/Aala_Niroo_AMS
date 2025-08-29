<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
include 'config.php';
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سامانه مدیریت اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-5">
        <h1 class="text-center">خوش آمدید به سامانه مدیریت اعلا نیرو</h1>
        <p class="text-center">این سیستم برای مدیریت دارایی‌ها و مشتریان شرکت طراحی شده است.</p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
کل پروژه صحیح را در GitHub ببین و نگه‌داری کن (دو راه):
با Git (پیشنهادی)
در ریشه پروژه (همان پوشه‌ای که فایل‌ها هستند) اجرا کن:
git config user.name "Your Name"
git config user.email "you@example.com"
git init
git add -A
git commit -m "Sync: fixed PHP headers, UI light theme, profiles, services/tasks"
git branch -M main
git remote remove origin 2>NUL || true
git remote add origin https://github.com/Hadi2230/Aala_Niroo_AMS.git
git push -u origin main