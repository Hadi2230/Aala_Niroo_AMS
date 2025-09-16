<?php
session_start();

// بررسی احراز هویت
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// اتصال به دیتابیس
try {
    $db_host = 'localhost';
    $db_name = 'aala_niroo_ams';
    $db_user = 'root';
    $db_pass = '';
    
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    // دریافت آمار
    $stats = [
        'customers' => $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn(),
        'assets' => $pdo->query("SELECT COUNT(*) FROM assets")->fetchColumn(),
        'tickets' => $pdo->query("SELECT COUNT(*) FROM tickets")->fetchColumn(),
        'surveys' => $pdo->query("SELECT COUNT(*) FROM surveys")->fetchColumn()
    ];
    
} catch (PDOException $e) {
    $error_message = 'خطا در اتصال به دیتابیس: ' . $e->getMessage();
    $stats = ['customers' => 0, 'assets' => 0, 'tickets' => 0, 'surveys' => 0];
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد - شرکت اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <style>
        body {
            font-family: Vazirmatn, sans-serif;
            background-color: #f8f9fa;
            padding-top: 80px;
        }
        .navbar {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .stats-card {
            text-align: center;
            padding: 2rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
        }
        .stats-number {
            font-size: 3rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        .stats-label {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        .menu-card {
            cursor: pointer;
            transition: all 0.3s;
        }
        .menu-card:hover {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
        }
        .menu-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <!-- نوار ناوبری -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-cogs"></i> سیستم مدیریت دارایی‌ها
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?>
                </span>
                <a class="nav-link" href="logout.php">
                    <i class="fas fa-sign-out-alt"></i> خروج
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="text-center mb-4">
                    <i class="fas fa-tachometer-alt"></i> داشبورد مدیریت
                </h1>
            </div>
        </div>
        
        <!-- آمار کلی -->
        <div class="row mb-5">
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $stats['customers']; ?></div>
                    <div class="stats-label">مشتریان</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $stats['assets']; ?></div>
                    <div class="stats-label">دارایی‌ها</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $stats['tickets']; ?></div>
                    <div class="stats-label">تیکت‌ها</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $stats['surveys']; ?></div>
                    <div class="stats-label">نظرسنجی‌ها</div>
                </div>
            </div>
        </div>
        
        <!-- منوی اصلی -->
        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card menu-card h-100" onclick="window.location.href='customers.php'">
                    <div class="card-body text-center">
                        <i class="fas fa-users menu-icon text-primary"></i>
                        <h5 class="card-title">مدیریت مشتریان</h5>
                        <p class="card-text">ثبت و مدیریت اطلاعات مشتریان</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="card menu-card h-100" onclick="window.location.href='assets.php'">
                    <div class="card-body text-center">
                        <i class="fas fa-cube menu-icon text-success"></i>
                        <h5 class="card-title">مدیریت دارایی‌ها</h5>
                        <p class="card-text">ثبت و مدیریت دارایی‌های شرکت</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="card menu-card h-100" onclick="window.location.href='tickets.php'">
                    <div class="card-body text-center">
                        <i class="fas fa-ticket-alt menu-icon text-warning"></i>
                        <h5 class="card-title">مدیریت تیکت‌ها</h5>
                        <p class="card-text">پیگیری و مدیریت درخواست‌ها</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="card menu-card h-100" onclick="window.location.href='survey.php'">
                    <div class="card-body text-center">
                        <i class="fas fa-poll menu-icon text-info"></i>
                        <h5 class="card-title">نظرسنجی</h5>
                        <p class="card-text">ایجاد و مدیریت نظرسنجی‌ها</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="card menu-card h-100" onclick="window.location.href='suppliers.php'">
                    <div class="card-body text-center">
                        <i class="fas fa-truck menu-icon text-secondary"></i>
                        <h5 class="card-title">مدیریت تامین‌کنندگان</h5>
                        <p class="card-text">ثبت و مدیریت تامین‌کنندگان</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="card menu-card h-100" onclick="window.location.href='users.php'">
                    <div class="card-body text-center">
                        <i class="fas fa-user-cog menu-icon text-dark"></i>
                        <h5 class="card-title">مدیریت کاربران</h5>
                        <p class="card-text">مدیریت کاربران سیستم</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>