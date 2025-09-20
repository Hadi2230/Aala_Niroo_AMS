<?php
// test_connection.php - تست اتصال به دیتابیس

echo "<h2>تست اتصال به دیتابیس</h2>";

try {
    require_once 'config.php';
    
    if ($pdo) {
        echo "<p style='color: green;'>✅ اتصال به دیتابیس موفق!</p>";
        
        // تست جدول users
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $user_count = $stmt->fetch()['count'];
        echo "<p>تعداد کاربران: $user_count</p>";
        
        // نمایش کاربران
        if ($user_count > 0) {
            $stmt = $pdo->query("SELECT username, full_name, role FROM users LIMIT 5");
            $users = $stmt->fetchAll();
            echo "<h3>کاربران موجود:</h3>";
            echo "<ul>";
            foreach ($users as $user) {
                echo "<li>{$user['username']} - {$user['full_name']} ({$user['role']})</li>";
            }
            echo "</ul>";
        }
        
        // تست لاگین
        echo "<h3>تست لاگین:</h3>";
        echo "<form method='post' style='border: 1px solid #ccc; padding: 20px; margin: 20px 0;'>";
        echo "<p>نام کاربری: <input type='text' name='username' value='admin'></p>";
        echo "<p>رمز عبور: <input type='password' name='password' value='admin'></p>";
        echo "<p><input type='submit' name='test_login' value='تست لاگین'></p>";
        echo "</form>";
        
        if (isset($_POST['test_login'])) {
            $username = $_POST['username'];
            $password = $_POST['password'];
            
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                echo "<p style='color: green;'>✅ لاگین موفق! کاربر: {$user['full_name']}</p>";
            } else {
                echo "<p style='color: red;'>❌ لاگین ناموفق!</p>";
            }
        }
        
    } else {
        echo "<p style='color: red;'>❌ اتصال به دیتابیس ناموفق!</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ خطا: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='login.php'>برو به صفحه لاگین</a></p>";
echo "<p><a href='check_databases.php'>بررسی دیتابیس‌های موجود</a></p>";
?>