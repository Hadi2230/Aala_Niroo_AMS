<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';
include 'email_config.php';

// بررسی دسترسی ادمین
if (!hasPermission('*')) {
    die('دسترسی غیرمجاز - فقط ادمین می‌تواند تنظیمات ایمیل را مدیریت کند');
}

$success_message = '';
$error_message = '';

// پردازش فرم تنظیمات ایمیل
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_email_settings') {
    verifyCsrfToken();
    
    $smtp_host = sanitizeInput($_POST['smtp_host']);
    $smtp_port = (int)$_POST['smtp_port'];
    $smtp_username = sanitizeInput($_POST['smtp_username']);
    $smtp_password = sanitizeInput($_POST['smtp_password']);
    $smtp_encryption = sanitizeInput($_POST['smtp_encryption']);
    $from_email = sanitizeInput($_POST['from_email']);
    $from_name = sanitizeInput($_POST['from_name']);
    $admin_email = sanitizeInput($_POST['admin_email']);
    $email_enabled = isset($_POST['email_enabled']) ? 1 : 0;
    
    try {
        // ذخیره تنظیمات در فایل config
        $config_content = "<?php\n";
        $config_content .= "// تنظیمات SMTP\n";
        $config_content .= "define('SMTP_HOST', '$smtp_host');\n";
        $config_content .= "define('SMTP_PORT', $smtp_port);\n";
        $config_content .= "define('SMTP_USERNAME', '$smtp_username');\n";
        $config_content .= "define('SMTP_PASSWORD', '$smtp_password');\n";
        $config_content .= "define('SMTP_ENCRYPTION', '$smtp_encryption');\n";
        $config_content .= "define('SMTP_FROM_EMAIL', '$from_email');\n";
        $config_content .= "define('SMTP_FROM_NAME', '$from_name');\n";
        $config_content .= "define('ADMIN_EMAIL', '$admin_email');\n";
        $config_content .= "define('EMAIL_ENABLED', " . ($email_enabled ? 'true' : 'false') . ");\n";
        $config_content .= "define('EMAIL_DEBUG', false);\n";
        $config_content .= "?>";
        
        file_put_contents('email_config.php', $config_content);
        
        $success_message = "تنظیمات ایمیل با موفقیت به‌روزرسانی شد";
        
    } catch (Exception $e) {
        $error_message = "خطا در به‌روزرسانی تنظیمات: " . $e->getMessage();
    }
}

// تست ارسال ایمیل
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'test_email') {
    verifyCsrfToken();
    
    $test_email = sanitizeInput($_POST['test_email']);
    
    if (!empty($test_email) && filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        $test_result = sendEmail($test_email, 'تست ایمیل - ' . APP_NAME, 
            '<h2>تست ایمیل موفقیت‌آمیز بود!</h2><p>این ایمیل برای تست سیستم ارسال ایمیل ارسال شده است.</p>');
        
        if ($test_result) {
            $success_message = "ایمیل تست با موفقیت ارسال شد";
        } else {
            $error_message = "ارسال ایمیل تست ناموفق بود. لطفاً تنظیمات SMTP را بررسی کنید";
        }
    } else {
        $error_message = "لطفاً یک ایمیل معتبر وارد کنید";
    }
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تنظیمات ایمیل - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <style>
        body { font-family: Vazirmatn, sans-serif; background-color: #f8f9fa; padding-top: 80px; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .card-header { background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); color: white; border-radius: 12px 12px 0 0 !important; font-weight: 600; }
        .form-control:focus { border-color: #3498db; box-shadow: 0 0 0 0.2rem rgba(52,152,219,0.25); }
        .btn-primary { background: linear-gradient(135deg, #3498db, #2980b9); border: none; }
        .btn-success { background: linear-gradient(135deg, #27ae60, #229954); border: none; }
        .btn-warning { background: linear-gradient(135deg, #f39c12, #e67e22); border: none; }
        .test-section { background: linear-gradient(135deg, #f8f9ff 0%, #e8f2ff 100%); border-radius: 10px; padding: 20px; margin-top: 20px; }
        .status-badge { font-size: 12px; padding: 4px 8px; border-radius: 12px; }
        .status-enabled { background: #d4edda; color: #155724; }
        .status-disabled { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h2><i class="fas fa-envelope"></i> تنظیمات سیستم ایمیل</h2>
                
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- تنظیمات SMTP -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-cog"></i> تنظیمات SMTP
                        <span class="status-badge <?php echo EMAIL_ENABLED ? 'status-enabled' : 'status-disabled'; ?> float-end">
                            <?php echo EMAIL_ENABLED ? 'فعال' : 'غیرفعال'; ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <form method="post" id="emailSettingsForm">
                            <input type="hidden" name="action" value="update_email_settings">
                            <?php echo csrf_field(); ?>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">سرور SMTP</label>
                                        <input type="text" class="form-control" name="smtp_host" value="<?php echo SMTP_HOST; ?>" required>
                                        <small class="text-muted">مثال: smtp.gmail.com</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">پورت</label>
                                        <input type="number" class="form-control" name="smtp_port" value="<?php echo SMTP_PORT; ?>" required>
                                        <small class="text-muted">معمولاً 587 برای TLS یا 465 برای SSL</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">نام کاربری</label>
                                        <input type="text" class="form-control" name="smtp_username" value="<?php echo SMTP_USERNAME; ?>" required>
                                        <small class="text-muted">ایمیل فرستنده</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">رمز عبور</label>
                                        <input type="password" class="form-control" name="smtp_password" value="<?php echo SMTP_PASSWORD; ?>" required>
                                        <small class="text-muted">رمز عبور ایمیل یا رمز عبور برنامه</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">نوع رمزنگاری</label>
                                        <select class="form-select" name="smtp_encryption" required>
                                            <option value="tls" <?php echo SMTP_ENCRYPTION === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                            <option value="ssl" <?php echo SMTP_ENCRYPTION === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">ایمیل فرستنده</label>
                                        <input type="email" class="form-control" name="from_email" value="<?php echo SMTP_FROM_EMAIL; ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">نام فرستنده</label>
                                        <input type="text" class="form-control" name="from_name" value="<?php echo SMTP_FROM_NAME; ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">ایمیل مدیر سیستم</label>
                                        <input type="email" class="form-control" name="admin_email" value="<?php echo ADMIN_EMAIL; ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="email_enabled" id="emailEnabled" <?php echo EMAIL_ENABLED ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="emailEnabled">
                                        <i class="fas fa-envelope me-1"></i>فعال کردن سیستم ایمیل
                                    </label>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> ذخیره تنظیمات
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- تست ایمیل -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-paper-plane"></i> تست ارسال ایمیل
                    </div>
                    <div class="card-body">
                        <div class="test-section">
                            <h5 class="text-primary mb-3">
                                <i class="fas fa-vial"></i> ارسال ایمیل تست
                            </h5>
                            <p class="text-muted">برای اطمینان از صحت تنظیمات، یک ایمیل تست ارسال کنید:</p>
                            
                            <form method="post" class="d-flex gap-3">
                                <input type="hidden" name="action" value="test_email">
                                <?php echo csrf_field(); ?>
                                
                                <div class="flex-grow-1">
                                    <input type="email" class="form-control" name="test_email" placeholder="ایمیل تست را وارد کنید" required>
                                </div>
                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-paper-plane"></i> ارسال تست
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- راهنمای تنظیمات -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-question-circle"></i> راهنمای تنظیمات
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-primary">Gmail</h6>
                                <ul class="list-unstyled">
                                    <li><strong>سرور:</strong> smtp.gmail.com</li>
                                    <li><strong>پورت:</strong> 587 (TLS) یا 465 (SSL)</li>
                                    <li><strong>رمز عبور:</strong> رمز عبور برنامه (App Password)</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-primary">Yahoo</h6>
                                <ul class="list-unstyled">
                                    <li><strong>سرور:</strong> smtp.mail.yahoo.com</li>
                                    <li><strong>پورت:</strong> 587 (TLS) یا 465 (SSL)</li>
                                    <li><strong>رمز عبور:</strong> رمز عبور برنامه</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>نکته مهم:</strong> برای Gmail باید "رمز عبور برنامه" (App Password) ایجاد کنید و از آن استفاده کنید.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>