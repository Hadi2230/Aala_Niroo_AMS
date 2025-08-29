<?php
include 'config.php';

// ریست/ایجاد کاربر admin
// می‌توانید پسورد دلخواه بدهید: create_user.php?pwd=YourNewPassword
$newPassword = isset($_GET['pwd']) && $_GET['pwd'] !== '' ? $_GET['pwd'] : '123456';

try {
    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

    // اگر کاربر admin وجود دارد، آپدیت؛ وگرنه ایجاد
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute(['admin']);
    $admin = $stmt->fetch();

    if ($admin) {
        $stmt = $pdo->prepare("UPDATE users SET password = ?, is_active = 1, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$passwordHash, $admin['id']]);
        $action = 'reset';
    } else {
        $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role, is_active) VALUES (?, ?, ?, 'ادمین', 1)");
        $stmt->execute(['admin', $passwordHash, 'مدیر سیستم']);
        $action = 'created';
    }

    echo "✅ کاربر admin با موفقیت " . ($action === 'created' ? 'ایجاد' : 'به‌روزرسانی') . " شد!";
    echo "<br>📝 نام کاربری: admin";
    echo "<br>🔑 رمز عبور جدید: " . htmlspecialchars($newPassword, ENT_QUOTES, 'UTF-8');
    echo "<br><br>⚠️ پس از ورود، این فایل را حذف کنید (برای امنیت).";
    echo "<br>ℹ️ می‌توانید رمز دلخواه را با پارامتر ?pwd=NEWPASS تنظیم کنید.";

    // هدایت خودکار به صفحه ورود
    echo "<br><br>⏳ انتقال به صفحه ورود...";
    echo "<meta http-equiv='refresh' content='5;url=login.php'>";

} catch (Throwable $e) {
    echo "❌ خطا در انجام عملیات: " . $e->getMessage();
}