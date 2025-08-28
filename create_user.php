<?php
include 'config.php';

// ریست/ایجاد کاربر admin به صورت ایمن
// امکان تعیین رمز دلخواه از طریق ?pwd=YOURPASS (پیشفرض 123456)
$newPassword = isset($_GET['pwd']) && $_GET['pwd'] !== '' ? $_GET['pwd'] : '123456';

try {
    $password_hash = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // آیا کاربر admin وجود دارد؟
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute(['admin']);
    $admin = $stmt->fetch();
    
    if ($admin) {
        // آپدیت پسورد
        $stmt = $pdo->prepare("UPDATE users SET password = ?, is_active = 1 WHERE id = ?");
        $stmt->execute([$password_hash, $admin['id']]);
        $action = 'reset';
    } else {
        // ایجاد کاربر جدید admin
        $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role, is_active) VALUES (?, ?, ?, 'ادمین', 1)");
        $stmt->execute(['admin', $password_hash, 'مدیر سیستم']);
        $action = 'created';
    }
    
    echo "✅ کاربر admin با موفقیت " . ($action === 'created' ? 'ایجاد' : 'به‌روزرسانی') . " شد!";
    echo "<br>📝 نام کاربری: admin";
    echo "<br>🔑 رمز عبور جدید: " . htmlspecialchars($newPassword) . "";
    echo "<br><br>⚠️ حتماً پس از ورود، این فایل را حذف کنید (برای امنیت).";
    echo "<br>ℹ️ می‌توانید رمز دلخواه را با پارامتر ?pwd=NEWPASS تنظیم کنید.";
    
    echo "<br><br>⏳ هدایت خودکار به صفحه ورود...";
    echo "<meta http-equiv='refresh' content='5;url=login.php'>";
    
} catch (Throwable $e) {
    echo "❌ خطا: " . $e->getMessage();
}
?>