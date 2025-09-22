<?php
/**
 * install.php - نصب کننده سیستم Aala Niroo AMS
 * این فایل برای نصب اولیه سیستم روی سرور استفاده می‌شود
 */

// شروع session
session_start();

// تنظیمات امنیتی
error_reporting(E_ALL);
ini_set('display_errors', 1);

$step = $_GET['step'] ?? 1;
$error = '';
$success = '';

// تنظیمات دیتابیس پیش‌فرض
$db_config = [
    'host' => 'localhost',
    'port' => '3306',
    'username' => 'root',
    'password' => '',
    'database' => 'aala_niroo_ams'
];

?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نصب سیستم Aala Niroo AMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <style>
        body { font-family: 'Vazirmatn', 'Tahoma', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .install-container { background: white; border-radius: 15px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
        .step-indicator { display: flex; justify-content: center; margin-bottom: 30px; }
        .step { width: 40px; height: 40px; border-radius: 50%; background: #e9ecef; display: flex; align-items: center; justify-content: center; margin: 0 10px; font-weight: bold; }
        .step.active { background: #007bff; color: white; }
        .step.completed { background: #28a745; color: white; }
        .form-control:focus { border-color: #007bff; box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25); }
        .btn-primary { background: linear-gradient(45deg, #007bff, #0056b3); border: none; }
        .btn-success { background: linear-gradient(45deg, #28a745, #1e7e34); border: none; }
        .alert { border-radius: 10px; }
        .card { border: none; border-radius: 10px; }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="install-container p-5">
                    <div class="text-center mb-4">
                        <h1 class="display-4 text-primary">
                            <i class="fas fa-cogs me-3"></i>
                            نصب سیستم Aala Niroo AMS
                        </h1>
                        <p class="lead text-muted">سیستم مدیریت دارایی‌ها و انتساب دستگاه‌ها</p>
                    </div>

                    <!-- Step Indicator -->
                    <div class="step-indicator">
                        <div class="step <?php echo $step >= 1 ? 'active' : ''; ?>">1</div>
                        <div class="step <?php echo $step >= 2 ? 'active' : ''; ?>">2</div>
                        <div class="step <?php echo $step >= 3 ? 'active' : ''; ?>">3</div>
                        <div class="step <?php echo $step >= 4 ? 'active' : ''; ?>">4</div>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo $success; ?>
                        </div>
                    <?php endif; ?>

                    <?php
                    switch ($step) {
                        case 1:
                            include 'install_steps/step1_requirements.php';
                            break;
                        case 2:
                            include 'install_steps/step2_database.php';
                            break;
                        case 3:
                            include 'install_steps/step3_config.php';
                            break;
                        case 4:
                            include 'install_steps/step4_complete.php';
                            break;
                        default:
                            include 'install_steps/step1_requirements.php';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>