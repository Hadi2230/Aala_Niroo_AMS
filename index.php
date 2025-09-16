<?php
// صفحه اصلی - هدایت به login یا setup
session_start();

// بررسی وجود دیتابیس و جداول
$db_host = 'localhost';
$db_port = '3306';
$db_name = 'aala_niroo_ams';
$db_user = 'root';
$db_pass = '';

try {
    // اتصال به MySQL
    $pdo = new PDO("mysql:host=$db_host;port=$db_port;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
    // بررسی وجود دیتابیس
    $stmt = $pdo->query("SHOW DATABASES LIKE '$db_name'");
    $db_exists = $stmt->fetch();
    
    if ($db_exists) {
        // انتخاب دیتابیس و بررسی جداول
        $pdo->exec("USE $db_name");
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($tables) > 0) {
            // دیتابیس و جداول موجود است - هدایت به login
            header("Location: login.php");
            exit();
        } else {
            // دیتابیس موجود است اما جداول نیست - هدایت به setup
            header("Location: setup_database.php");
            exit();
        }
    } else {
        // دیتابیس موجود نیست - هدایت به setup
        header("Location: setup_database.php");
        exit();
    }
    
} catch (PDOException $e) {
    // خطا در اتصال - نمایش صفحه راهنما
    ?>
    <!DOCTYPE html>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>راه‌اندازی سیستم</title>
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
            .container {
                background: white;
                padding: 40px;
                border-radius: 15px;
                box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                max-width: 600px;
                text-align: center;
            }
            .error-icon {
                font-size: 4em;
                color: #e74c3c;
                margin-bottom: 20px;
            }
            h1 {
                color: #2c3e50;
                margin-bottom: 20px;
            }
            .error-message {
                color: #e74c3c;
                background: #fadbd8;
                padding: 15px;
                border-radius: 8px;
                margin: 20px 0;
            }
            .setup-button {
                background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
                color: white;
                border: none;
                padding: 15px 30px;
                font-size: 18px;
                border-radius: 8px;
                cursor: pointer;
                text-decoration: none;
                display: inline-block;
                margin: 10px;
                transition: all 0.3s ease;
            }
            .setup-button:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 20px rgba(46, 204, 113, 0.3);
            }
            .info-box {
                background: #f8f9fa;
                border: 1px solid #e9ecef;
                border-radius: 8px;
                padding: 20px;
                margin: 20px 0;
                text-align: right;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="error-icon">⚠️</div>
            <h1>خطا در اتصال به دیتابیس</h1>
            <div class="error-message">
                <strong>خطا:</strong> <?php echo htmlspecialchars($e->getMessage()); ?>
            </div>
            
            <div class="info-box">
                <h3>لطفاً مطمئن شوید که:</h3>
                <ul>
                    <li>XAMPP یا WAMP در حال اجرا است</li>
                    <li>MySQL سرویس فعال است</li>
                    <li>پورت 3306 در دسترس است</li>
                </ul>
            </div>
            
            <a href="setup_database.php" class="setup-button">
                🔧 راه‌اندازی دیتابیس
            </a>
            
            <a href="test_db_connection.php" class="setup-button" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);">
                🔍 تست اتصال
            </a>
        </div>
    </body>
    </html>
    <?php
}
?>