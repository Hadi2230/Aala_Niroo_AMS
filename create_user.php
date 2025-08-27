<?php
include 'config.php';

// آپدیت پسورد کاربر admin
try {
    $password_hash = password_hash('123456', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
    $stmt->execute([$password_hash]);
    
    echo "✅ پسورد کاربر admin با موفقیت ریست شد!";
    echo "<br>📝 نام کاربری: admin";
    echo "<br>🔑 رمز عبور جدید: 123456";
    echo "<br><br>⚠️ این فایل را حذف کنید (برای امنیت)";
    
    // حذف خودکار فایل پس از 5 ثانیه
    echo "<meta http-equiv='refresh' content='5;url=login.php'>";
    
} catch (PDOException $e) {
    echo "❌ خطا در آپدیت پسورد: " . $e->getMessage();
}
?>