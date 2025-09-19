<?php
session_start();

// بررسی وجود فایل config
if (!file_exists('config.php')) {
    if (file_exists('config_new.php')) {
        require_once 'config_new.php';
    } else {
        die('فایل تنظیمات یافت نشد!');
    }
} else {
    try {
        require_once 'config.php';
    } catch (Exception $e) {
        // اگر config.php خطا داد، از نسخه بدون دیتابیس استفاده کن
        require_once 'config_no_db.php';
    }
}

$error = '';

// بررسی اتصال دیتابیس
if (!$pdo) {
    $error = 'خطا در اتصال به دیتابیس. لطفاً XAMPP را بررسی کنید.';
} else {
    // پردازش فرم ورود
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (!empty($username) && !empty($password)) {
            try {
                // دیباگ: نمایش اطلاعات کاربر
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // دیباگ: بررسی وضعیت کاربر
                    if (!$user['is_active']) {
                        $error = 'حساب کاربری شما غیرفعال است';
                    } else {
                        // دیباگ: بررسی رمز عبور
                        if (password_verify($password, $user['password'])) {
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['username'] = $user['username'];
                            $_SESSION['full_name'] = $user['full_name'];
                            $_SESSION['role'] = $user['role'];
                            
                            // ثبت لاگ ورود
                            if (function_exists('logAction')) {
                                logAction($pdo, 'LOGIN_SUCCESS', "ورود موفق کاربر: $username", 'info', 'auth');
                            }
                            
                            header("Location: dashboard.php");
                            exit();
                        } else {
                            $error = 'رمز عبور اشتباه است';
                        }
                    }
                } else {
                    $error = 'نام کاربری یافت نشد';
                }
            } catch (Exception $e) {
                $error = 'خطا در سیستم: ' . $e->getMessage();
                error_log("Login error: " . $e->getMessage());
            }
        } else {
            $error = 'لطفاً تمام فیلدها را پر کنید';
        }
    }
}

// اگر دیتابیس در دسترس نیست، لاگین تست را فعال کن
if (!$pdo) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (!empty($username) && !empty($password)) {
            // لاگین تست - هر کاربری با هر رمزی
            $_SESSION['user_id'] = 1;
            $_SESSION['username'] = $username;
            $_SESSION['full_name'] = 'کاربر تست';
            $_SESSION['role'] = 'admin';
            
            header("Location: dashboard.php");
            exit();
        } else {
            $error = 'لطفاً تمام فیلدها را پر کنید';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود به سیستم</title>
    <style>
        body {
            font-family: 'Tahoma', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 400px;
            width: 100%;
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo h1 {
            color: #2c3e50;
            margin: 0;
            font-size: 2em;
        }
        .logo p {
            color: #7f8c8d;
            margin: 5px 0 0 0;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #2c3e50;
            font-weight: bold;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            box-sizing: border-box;
        }
        .form-group input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.3);
        }
        .login-button {
            width: 100%;
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
            border: none;
            padding: 12px;
            font-size: 16px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .login-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(46, 204, 113, 0.3);
        }
        .error {
            background: #fadbd8;
            color: #e74c3c;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        .info {
            background: #d6eaf8;
            color: #2980b9;
            padding: 10px;
            border-radius: 8px;
            margin-top: 20px;
            text-align: center;
            font-size: 14px;
        }
        .btn {
            background: #3498db;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
            margin: 5px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>🚀 اعلا نیرو</h1>
            <p>سیستم مدیریت دارایی‌ها</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username">نام کاربری:</label>
                <input type="text" id="username" name="username" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="password">رمز عبور:</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="login-button">ورود به سیستم</button>
        </form>
        
        <div class="info">
            <strong>اطلاعات ورود پیش‌فرض:</strong><br>
            نام کاربری: admin<br>
            رمز عبور: admin
        </div>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="test.php" class="btn">🔍 تست اتصال</a>
            <a href="setup_database.php" class="btn">🔧 راه‌اندازی</a>
        </div>
    </div>
</body>
</html>