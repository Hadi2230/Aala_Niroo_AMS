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
    require_once 'config.php';
}

// بررسی احراز هویت
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// بررسی اتصال دیتابیس
if (!$pdo) {
    die('خطا در اتصال به دیتابیس');
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد - سیستم مدیریت دارایی‌ها</title>
    <style>
        body {
            font-family: 'Tahoma', Arial, sans-serif;
            background: #f8f9fa;
            margin: 0;
            padding: 20px;
        }
        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            color: #2c3e50;
            margin: 0;
        }
        .user-info {
            color: #7f8c8d;
        }
        .logout-btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .logout-btn:hover {
            background: #c0392b;
        }
        .success-message {
            background: #d5f4e6;
            color: #27ae60;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        .card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .card-icon {
            font-size: 3em;
            margin-bottom: 15px;
        }
        .card h3 {
            color: #2c3e50;
            margin: 0 0 10px 0;
        }
        .card p {
            color: #7f8c8d;
            margin: 0 0 20px 0;
        }
        .card-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .card-btn:hover {
            background: #2980b9;
        }
        .status-info {
            background: #e8f4fd;
            color: #2980b9;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🎉 خوش‌آمدید به سیستم مدیریت دارایی‌های اعلا نیرو</h1>
        <div class="user-info">
            <strong><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></strong>
            <span>(<?php echo htmlspecialchars($_SESSION['role']); ?>)</span>
            <a href="logout.php" class="logout-btn">خروج</a>
        </div>
    </div>
    
    <div class="success-message">
        ✅ سیستم با موفقیت راه‌اندازی شد! دیتابیس و جداول ایجاد شدند.
    </div>
    
    <div class="status-info">
        <strong>وضعیت سیستم:</strong>
        <?php
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
            $user_count = $stmt->fetch()['count'];
            echo "کاربران: $user_count | ";
            
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM customers");
            $customer_count = $stmt->fetch()['count'];
            echo "مشتریان: $customer_count | ";
            
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM assets");
            $asset_count = $stmt->fetch()['count'];
            echo "دارایی‌ها: $asset_count";
        } catch (Exception $e) {
            echo "خطا در دریافت آمار";
        }
        ?>
    </div>
    
    <div class="cards">
        <div class="card">
            <div class="card-icon">🏢</div>
            <h3>مدیریت مشتریان</h3>
            <p>ثبت و مدیریت اطلاعات مشتریان</p>
            <a href="customers.php" class="card-btn">ورود</a>
        </div>
        
        <div class="card">
            <div class="card-icon">⚙️</div>
            <h3>مدیریت دارایی‌ها</h3>
            <p>ثبت و مدیریت دارایی‌های شرکت</p>
            <a href="assets.php" class="card-btn">ورود</a>
        </div>
        
        <div class="card">
            <div class="card-icon">👥</div>
            <h3>مدیریت کاربران</h3>
            <p>مدیریت کاربران و دسترسی‌ها</p>
            <a href="users.php" class="card-btn">ورود</a>
        </div>
        
        <div class="card">
            <div class="card-icon">🎫</div>
            <h3>تیکت‌ها</h3>
            <p>مدیریت تیکت‌های پشتیبانی</p>
            <a href="tickets.php" class="card-btn">ورود</a>
        </div>
        
        <div class="card">
            <div class="card-icon">📊</div>
            <h3>نظرسنجی‌ها</h3>
            <p>ایجاد و مدیریت نظرسنجی‌ها</p>
            <a href="survey_list.php" class="card-btn">ورود</a>
        </div>
        
        <div class="card">
            <div class="card-icon">🏭</div>
            <h3>تامین‌کنندگان</h3>
            <p>مدیریت اطلاعات تامین‌کنندگان</p>
            <a href="suppliers.php" class="card-btn">ورود</a>
        </div>
        
        <div class="card">
            <div class="card-icon">🔧</div>
            <h3>ابزارها</h3>
            <p>مدیریت ابزارها و تجهیزات</p>
            <a href="tools.php" class="card-btn">ورود</a>
        </div>
        
        <div class="card">
            <div class="card-icon">📈</div>
            <h3>گزارشات</h3>
            <p>مشاهده گزارشات و آمار</p>
            <a href="system_logs.php" class="card-btn">ورود</a>
        </div>
    </div>
</body>
</html>