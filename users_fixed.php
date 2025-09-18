<?php
/**
 * users_fixed.php - مدیریت کاربران (نسخه اصلاح شده)
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'config.php';

// بررسی دسترسی ادمین
if (!hasPermission('*')) {
    die('دسترسی غیرمجاز - فقط ادمین می‌تواند کاربران را مدیریت کند');
}

$success_message = '';
$error_message = '';

// تعریف نقش‌ها
$roles = [
    'ادمین' => 'ادمین',
    'کاربر عادی' => 'کاربر عادی',
    'اپراتور' => 'اپراتور',
    'مدیر عملیات' => 'مدیر عملیات',
    'تکنسین' => 'تکنسین',
    'پشتیبانی' => 'پشتیبانی'
];

// تعریف مجوزها
$all_permissions = [
    'tickets.view' => 'مشاهده تیکت‌ها',
    'tickets.create' => 'ایجاد تیکت',
    'tickets.edit' => 'ویرایش تیکت',
    'tickets.assign' => 'تخصیص تیکت',
    'tickets.resolve' => 'حل تیکت',
    'maintenance.view' => 'مشاهده تعمیرات',
    'maintenance.create' => 'ایجاد تعمیرات',
    'maintenance.edit' => 'ویرایش تعمیرات',
    'maintenance.assign' => 'تخصیص تعمیرات',
    'maintenance.complete' => 'تکمیل تعمیرات',
    'customers.view' => 'مشاهده مشتریان',
    'customers.create' => 'ایجاد مشتری',
    'customers.edit' => 'ویرایش مشتری',
    'reports.view' => 'مشاهده گزارش‌ها',
    'messages.send' => 'ارسال پیام',
    'messages.receive' => 'دریافت پیام'
];

// تابع برای دریافت رمز عبور کاربر
function getUserPassword($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        return $user ? $user['password'] : null;
    } catch (Exception $e) {
        return null;
    }
}

// تابع برای ایجاد تنظیمات اعلان کاربر
function createUserNotificationSettings($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("INSERT IGNORE INTO notification_settings (user_id, email_notifications, sms_notifications) VALUES (?, 1, 1)");
        $stmt->execute([$user_id]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// تابع برای ارسال ایمیل خوش‌آمدگویی
function sendWelcomeEmail($username, $email, $full_name, $password) {
    try {
        // اینجا می‌توانید از سیستم ایمیل موجود استفاده کنید
        return true; // برای تست true برمی‌گردانیم
    } catch (Exception $e) {
        return false;
    }
}

// تابع برای ارسال اطلاع‌رسانی به مدیر
function sendAdminNotification($username, $email, $full_name) {
    try {
        // اینجا می‌توانید اطلاع‌رسانی به مدیر ارسال کنید
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// پردازش فرم‌ها
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCsrfToken();
        
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create_user':
                    $username = sanitizeInput($_POST['username']);
                    $password = sanitizeInput($_POST['password']);
                    $full_name = sanitizeInput($_POST['full_name']);
                    $email = sanitizeInput($_POST['email']);
                    $role = sanitizeInput($_POST['role']);
                    $selected_permissions = $_POST['permissions'] ?? [];
                    
                    if ($username && $password && $full_name && $role) {
                        $pdo->beginTransaction();
                        
                        // بررسی تکراری نبودن نام کاربری
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                        $stmt->execute([$username]);
                        if ($stmt->fetch()) {
                            throw new Exception('نام کاربری قبلاً استفاده شده است');
                        }
                        
                        // بررسی تکراری نبودن ایمیل
                        if ($email) {
                            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                            $stmt->execute([$email]);
                            if ($stmt->fetch()) {
                                throw new Exception('ایمیل قبلاً استفاده شده است');
                            }
                        }
                        
                        // ایجاد کاربر
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$username, $hashed_password, $full_name, $email, $role]);
                        $user_id = $pdo->lastInsertId();
                        
                        // ذخیره دسترسی‌های سفارشی
                        if ($role === 'سفارشی' && !empty($selected_permissions)) {
                            $custom_role_name = 'custom_' . $user_id;
                            $stmt = $pdo->prepare("INSERT INTO custom_roles (user_id, role_name, permissions) VALUES (?, ?, ?)");
                            $stmt->execute([$user_id, $custom_role_name, json_encode($selected_permissions)]);
                        }
                        
                        // ایجاد تنظیمات اعلان برای کاربر جدید
                        createUserNotificationSettings($pdo, $user_id);
                        
                        $pdo->commit();
                        $success_message = "کاربر با موفقیت ایجاد شد";
                        
                    } else {
                        throw new Exception('لطفاً تمام فیلدهای ضروری را پر کنید');
                    }
                    break;
                    
                case 'edit_user':
                    $user_id = sanitizeInput($_POST['user_id']);
                    $username = sanitizeInput($_POST['username']);
                    $full_name = sanitizeInput($_POST['full_name']);
                    $email = sanitizeInput($_POST['email']);
                    $role = sanitizeInput($_POST['role']);
                    $is_active = isset($_POST['is_active']) ? 1 : 0;
                    $selected_permissions = $_POST['permissions'] ?? [];
                    $new_password = sanitizeInput($_POST['new_password'] ?? '');
                    $confirm_password = sanitizeInput($_POST['confirm_password'] ?? '');
                    
                    if ($user_id && $username && $full_name && $role) {
                        $pdo->beginTransaction();
                        
                        // بررسی تکراری نبودن نام کاربری (به جز خود کاربر)
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                        $stmt->execute([$username, $user_id]);
                        if ($stmt->fetch()) {
                            throw new Exception('نام کاربری قبلاً استفاده شده است');
                        }
                        
                        // بررسی تکراری نبودن ایمیل (به جز خود کاربر)
                        if ($email) {
                            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                            $stmt->execute([$email, $user_id]);
                            if ($stmt->fetch()) {
                                throw new Exception('ایمیل قبلاً استفاده شده است');
                            }
                        }
                        
                        // بررسی تغییر رمز عبور
                        if (!empty($new_password)) {
                            if (strlen($new_password) < 6) {
                                throw new Exception('رمز عبور جدید باید حداقل ۶ کاراکتر باشد');
                            }
                            
                            if ($new_password !== $confirm_password) {
                                throw new Exception('رمز عبور جدید و تأیید آن مطابقت ندارند');
                            }
                            
                            // به‌روزرسانی کاربر با رمز عبور جدید
                            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, full_name = ?, email = ?, role = ?, is_active = ? WHERE id = ?");
                            $stmt->execute([$username, $hashed_password, $full_name, $email, $role, $is_active, $user_id]);
                        } else {
                            // به‌روزرسانی کاربر بدون تغییر رمز عبور
                            $stmt = $pdo->prepare("UPDATE users SET username = ?, full_name = ?, email = ?, role = ?, is_active = ? WHERE id = ?");
                            $stmt->execute([$username, $full_name, $email, $role, $is_active, $user_id]);
                        }
                        
                        // به‌روزرسانی دسترسی‌های سفارشی
                        $stmt = $pdo->prepare("DELETE FROM custom_roles WHERE user_id = ?");
                        $stmt->execute([$user_id]);
                        
                        if ($role === 'سفارشی' && !empty($selected_permissions)) {
                            $custom_role_name = 'custom_' . $user_id;
                            $stmt = $pdo->prepare("INSERT INTO custom_roles (user_id, role_name, permissions) VALUES (?, ?, ?)");
                            $stmt->execute([$user_id, $custom_role_name, json_encode($selected_permissions)]);
                        }
                        
                        $pdo->commit();
                        $success_message = "کاربر با موفقیت به‌روزرسانی شد";
                        if (!empty($new_password)) {
                            $success_message .= " و رمز عبور تغییر یافت";
                        }
                    } else {
                        throw new Exception('لطفاً تمام فیلدهای ضروری را پر کنید');
                    }
                    break;
                    
                case 'delete_user':
                    $user_id = sanitizeInput($_POST['user_id']);
                    
                    if ($user_id && $user_id != $_SESSION['user_id']) {
                        $pdo->beginTransaction();
                        
                        // حذف دسترسی‌های سفارشی
                        $stmt = $pdo->prepare("DELETE FROM custom_roles WHERE user_id = ?");
                        $stmt->execute([$user_id]);
                        
                        // حذف کاربر
                        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                        $stmt->execute([$user_id]);
                        
                        $pdo->commit();
                        $success_message = "کاربر با موفقیت حذف شد";
                    } else {
                        throw new Exception('نمی‌توانید خود را حذف کنید');
                    }
                    break;
                    
                case 'toggle_user_status':
                    $user_id = sanitizeInput($_POST['user_id']);
                    
                    if ($user_id && $user_id != $_SESSION['user_id']) {
                        $stmt = $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
                        $stmt->execute([$user_id]);
                        $success_message = "وضعیت کاربر با موفقیت تغییر یافت";
                    } else {
                        throw new Exception('نمی‌توانید وضعیت خود را تغییر دهید');
                    }
                    break;
            }
        }
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error_message = "خطا: " . $e->getMessage();
    }
}

// دریافت لیست کاربران
try {
    $users = $pdo->query("SELECT id, username, full_name, email, role, is_active, last_login, created_at FROM users ORDER BY created_at DESC")->fetchAll();
} catch (Exception $e) {
    $users = [];
    $error_message = "خطا در دریافت لیست کاربران: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت کاربران - شرکت اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { 
            font-family: 'Tahoma', sans-serif; 
            background-color: #f8f9fa; 
            padding-top: 80px; 
        }
        .card { 
            border: none; 
            border-radius: 12px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.1); 
            margin-bottom: 20px; 
        }
        .card-header { 
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); 
            color: white; 
            border-radius: 12px 12px 0 0 !important; 
            font-weight: 600; 
        }
        .permission-group { 
            background: #f8f9fa; 
            border-radius: 8px; 
            padding: 15px; 
            margin-bottom: 15px; 
        }
        .permission-item { 
            margin-bottom: 10px; 
        }
        .btn-group-sm .btn { 
            margin-left: 2px; 
        }
        .input-icon { 
            position: relative; 
        }
        .input-icon i { 
            position: absolute; 
            left: 15px; 
            top: 50%; 
            transform: translateY(-50%); 
            color: #6c757d; 
            z-index: 5; 
        }
        .input-icon .form-control { 
            padding-left: 45px; 
            border-radius: 10px; 
            border: 2px solid #e1e5eb; 
            padding: 16px 20px; 
            transition: 0.3s; 
        }
        .input-icon .form-control:focus { 
            border-color: #3498db; 
            box-shadow: 0 0 0 0.25rem rgba(52,152,219,0.25); 
        }
        .password-toggle { 
            position: absolute; 
            right: 15px; 
            top: 50%; 
            transform: translateY(-50%); 
            color: #6c757d; 
            cursor: pointer; 
            z-index: 6; 
            transition: color 0.3s ease;
            padding: 5px;
        }
        .password-toggle:hover { 
            color: #3498db; 
        }
        .password-change-section {
            background: linear-gradient(135deg, #f8f9ff 0%, #e8f2ff 100%);
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-top: 15px;
            transition: all 0.3s ease;
        }
        .password-change-section.show {
            animation: slideDown 0.3s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .form-check-label {
            font-weight: 600;
            color: #2c3e50;
        }
        .form-check-input:checked {
            background-color: #3498db;
            border-color: #3498db;
        }
    </style>
</head>
<body>
    <?php 
    if (file_exists('navbar.php')) {
        include 'navbar.php'; 
    }
    ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h2><i class="fa fa-users"></i> مدیریت کاربران</h2>
                
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- فرم ایجاد کاربر -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fa fa-user-plus"></i> ایجاد کاربر جدید
                    </div>
                    <div class="card-body">
                        <form method="post" id="createUserForm">
                            <input type="hidden" name="action" value="create_user">
                            <?php echo csrf_field(); ?>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">نام کاربری *</label>
                                        <input type="text" class="form-control" name="username" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">رمز عبور *</label>
                                        <div class="input-icon">
                                            <i class="fas fa-lock"></i>
                                            <input type="password" name="password" id="createPasswordInput" class="form-control" required>
                                            <i class="fas fa-eye password-toggle" id="createPasswordToggle" onclick="toggleCreatePassword()"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">نام کامل *</label>
                                        <input type="text" class="form-control" name="full_name" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">ایمیل</label>
                                        <input type="email" class="form-control" name="email">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">نقش *</label>
                                        <select class="form-select" name="role" id="roleSelect" required>
                                            <option value="">انتخاب کنید</option>
                                            <?php foreach ($roles as $role_key => $role_name): ?>
                                                <option value="<?php echo htmlspecialchars($role_key); ?>"><?php echo htmlspecialchars($role_name); ?></option>
                                            <?php endforeach; ?>
                                            <option value="سفارشی">سفارشی</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- بخش دسترسی‌های سفارشی -->
                            <div id="customPermissions" style="display: none;">
                                <h6 class="text-primary mb-3">دسترسی‌های سفارشی</h6>
                                
                                <div class="permission-group">
                                    <h6 class="text-secondary">تیکت‌ها</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="permission-item">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="tickets.view" id="tickets_view">
                                                    <label class="form-check-label" for="tickets_view">مشاهده تیکت‌ها</label>
                                                </div>
                                            </div>
                                            <div class="permission-item">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="tickets.create" id="tickets_create">
                                                    <label class="form-check-label" for="tickets_create">ایجاد تیکت</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="permission-item">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="tickets.edit" id="tickets_edit">
                                                    <label class="form-check-label" for="tickets_edit">ویرایش تیکت</label>
                                                </div>
                                            </div>
                                            <div class="permission-item">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="tickets.assign" id="tickets_assign">
                                                    <label class="form-check-label" for="tickets_assign">تخصیص تیکت</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="permission-group">
                                    <h6 class="text-secondary">تعمیرات</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="permission-item">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="maintenance.view" id="maintenance_view">
                                                    <label class="form-check-label" for="maintenance_view">مشاهده تعمیرات</label>
                                                </div>
                                            </div>
                                            <div class="permission-item">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="maintenance.create" id="maintenance_create">
                                                    <label class="form-check-label" for="maintenance_create">ایجاد تعمیرات</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="permission-item">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="maintenance.edit" id="maintenance_edit">
                                                    <label class="form-check-label" for="maintenance_edit">ویرایش تعمیرات</label>
                                                </div>
                                            </div>
                                            <div class="permission-item">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="maintenance.assign" id="maintenance_assign">
                                                    <label class="form-check-label" for="maintenance_assign">تخصیص تعمیرات</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="permission-group">
                                    <h6 class="text-secondary">مشتریان</h6>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="permission-item">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="customers.view" id="customers_view">
                                                    <label class="form-check-label" for="customers_view">مشاهده مشتریان</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="permission-item">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="customers.create" id="customers_create">
                                                    <label class="form-check-label" for="customers_create">ایجاد مشتری</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="permission-item">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="customers.edit" id="customers_edit">
                                                    <label class="form-check-label" for="customers_edit">ویرایش مشتری</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="permission-group">
                                    <h6 class="text-secondary">سایر</h6>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="permission-item">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="reports.view" id="reports_view">
                                                    <label class="form-check-label" for="reports_view">مشاهده گزارش‌ها</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="permission-item">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="messages.send" id="messages_send">
                                                    <label class="form-check-label" for="messages_send">ارسال پیام</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="permission-item">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="messages.receive" id="messages_receive">
                                                    <label class="form-check-label" for="messages_receive">دریافت پیام</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fa fa-user-plus"></i> ایجاد کاربر
                            </button>
                        </form>
                    </div>
                </div>

                <!-- لیست کاربران -->
                <div class="card">
                    <div class="card-header">
                        <i class="fa fa-list"></i> لیست کاربران (<?php echo count($users); ?> کاربر)
                    </div>
                    <div class="card-body">
                        <?php if (empty($users)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">هیچ کاربری یافت نشد</h5>
                                <p class="text-muted">برای شروع، کاربر جدیدی اضافه کنید</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>نام کاربری</th>
                                            <th>نام کامل</th>
                                            <th>ایمیل</th>
                                            <th>نقش</th>
                                            <th>وضعیت</th>
                                            <th>آخرین ورود</th>
                                            <th>تاریخ ایجاد</th>
                                            <th>عملیات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo htmlspecialchars($user['role']); ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($user['is_active']): ?>
                                                        <span class="badge bg-success">فعال</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">غیرفعال</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo $user['last_login'] ? jalali_format($user['last_login'], 'Y/m/d H:i') : 'هرگز'; ?>
                                                </td>
                                                <td><?php echo jalali_format($user['created_at'], 'Y/m/d'); ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#editUserModal" 
                                                                data-user-id="<?php echo $user['id']; ?>"
                                                                data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                                data-full-name="<?php echo htmlspecialchars($user['full_name']); ?>"
                                                                data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                                                data-role="<?php echo htmlspecialchars($user['role']); ?>"
                                                                data-is-active="<?php echo $user['is_active']; ?>">
                                                            <i class="fa fa-edit"></i>
                                                        </button>
                                                        
                                                        <button class="btn btn-<?php echo $user['is_active'] ? 'secondary' : 'success'; ?>" 
                                                                onclick="toggleUserStatus(<?php echo $user['id']; ?>)">
                                                            <i class="fa fa-<?php echo $user['is_active'] ? 'ban' : 'check'; ?>"></i>
                                                        </button>
                                                        
                                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                            <button class="btn btn-danger" onclick="deleteUser(<?php echo $user['id']; ?>)">
                                                                <i class="fa fa-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal ویرایش کاربر -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" method="post" id="editUserForm">
                <div class="modal-header">
                    <h5 class="modal-title">ویرایش کاربر</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_user">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="user_id" id="editUserId">
                    
                    <div class="mb-3">
                        <label class="form-label">نام کاربری *</label>
                        <input type="text" class="form-control" name="username" id="editUsername" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">نام کامل *</label>
                        <input type="text" class="form-control" name="full_name" id="editFullName" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">ایمیل</label>
                        <input type="email" class="form-control" name="email" id="editEmail">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">نقش *</label>
                        <select class="form-select" name="role" id="editRole" required>
                            <?php foreach ($roles as $role_key => $role_name): ?>
                                <option value="<?php echo htmlspecialchars($role_key); ?>"><?php echo htmlspecialchars($role_name); ?></option>
                            <?php endforeach; ?>
                            <option value="سفارشی">سفارشی</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" id="editIsActive" value="1">
                            <label class="form-check-label" for="editIsActive">فعال</label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="changePasswordCheck" onchange="togglePasswordChange()">
                            <label class="form-check-label" for="changePasswordCheck">
                                <i class="fas fa-key me-1"></i>تغییر رمز عبور
                            </label>
                        </div>
                    </div>
                    
                    <div id="passwordChangeSection" class="password-change-section" style="display: none;">
                        <h6 class="text-primary mb-3">
                            <i class="fas fa-key me-2"></i>تغییر رمز عبور
                        </h6>
                        <div class="mb-3">
                            <label class="form-label">رمز عبور جدید</label>
                            <div class="input-icon">
                                <i class="fas fa-lock"></i>
                                <input type="password" name="new_password" id="newPasswordInput" class="form-control" placeholder="رمز عبور جدید را وارد کنید">
                                <i class="fas fa-eye password-toggle" id="newPasswordToggle" onclick="toggleNewPassword()"></i>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">تأیید رمز عبور جدید</label>
                            <div class="input-icon">
                                <i class="fas fa-lock"></i>
                                <input type="password" name="confirm_password" id="confirmPasswordInput" class="form-control" placeholder="رمز عبور جدید را مجدداً وارد کنید">
                                <i class="fas fa-eye password-toggle" id="confirmPasswordToggle" onclick="toggleConfirmPassword()"></i>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>نکته:</strong> رمز عبور جدید باید حداقل ۶ کاراکتر باشد و با تأیید آن مطابقت داشته باشد.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" class="btn btn-primary">ذخیره تغییرات</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // تابع نمایش/مخفی کردن رمز عبور در ایجاد کاربر
        function toggleCreatePassword() {
            const passwordInput = document.getElementById('createPasswordInput');
            const passwordToggle = document.getElementById('createPasswordToggle');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordToggle.classList.remove('fa-eye');
                passwordToggle.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                passwordToggle.classList.remove('fa-eye-slash');
                passwordToggle.classList.add('fa-eye');
            }
        }

        // تابع نمایش/مخفی کردن رمز عبور جدید
        function toggleNewPassword() {
            const passwordInput = document.getElementById('newPasswordInput');
            const passwordToggle = document.getElementById('newPasswordToggle');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordToggle.classList.remove('fa-eye');
                passwordToggle.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                passwordToggle.classList.remove('fa-eye-slash');
                passwordToggle.classList.add('fa-eye');
            }
        }

        // تابع نمایش/مخفی کردن تأیید رمز عبور
        function toggleConfirmPassword() {
            const passwordInput = document.getElementById('confirmPasswordInput');
            const passwordToggle = document.getElementById('confirmPasswordToggle');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordToggle.classList.remove('fa-eye');
                passwordToggle.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                passwordToggle.classList.remove('fa-eye-slash');
                passwordToggle.classList.add('fa-eye');
            }
        }

        // تابع نمایش/مخفی کردن بخش تغییر رمز عبور
        function togglePasswordChange() {
            const changePasswordCheck = document.getElementById('changePasswordCheck');
            const passwordChangeSection = document.getElementById('passwordChangeSection');
            
            if (changePasswordCheck.checked) {
                passwordChangeSection.style.display = 'block';
                passwordChangeSection.classList.add('show');
                document.getElementById('newPasswordInput').required = true;
                document.getElementById('confirmPasswordInput').required = true;
            } else {
                passwordChangeSection.classList.remove('show');
                setTimeout(() => {
                    passwordChangeSection.style.display = 'none';
                }, 300);
                document.getElementById('newPasswordInput').required = false;
                document.getElementById('confirmPasswordInput').required = false;
                document.getElementById('newPasswordInput').value = '';
                document.getElementById('confirmPasswordInput').value = '';
            }
        }

        // مدیریت نمایش دسترسی‌های سفارشی
        document.getElementById('roleSelect').addEventListener('change', function() {
            const customPermissions = document.getElementById('customPermissions');
            if (this.value === 'سفارشی') {
                customPermissions.style.display = 'block';
            } else {
                customPermissions.style.display = 'none';
                document.querySelectorAll('#customPermissions input[type="checkbox"]').forEach(checkbox => {
                    checkbox.checked = false;
                });
            }
        });

        // مدیریت نمایش دسترسی‌های سفارشی در ویرایش
        document.getElementById('editRole').addEventListener('change', function() {
            const editCustomPermissions = document.getElementById('editCustomPermissions');
            if (this.value === 'سفارشی') {
                editCustomPermissions.style.display = 'block';
            } else {
                editCustomPermissions.style.display = 'none';
                document.querySelectorAll('#editCustomPermissions input[type="checkbox"]').forEach(checkbox => {
                    checkbox.checked = false;
                });
            }
        });

        // پر کردن فرم ویرایش
        document.getElementById('editUserModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const userId = button.getAttribute('data-user-id');
            const username = button.getAttribute('data-username');
            const fullName = button.getAttribute('data-full-name');
            const email = button.getAttribute('data-email');
            const role = button.getAttribute('data-role');
            const isActive = button.getAttribute('data-is-active');
            
            document.getElementById('editUserId').value = userId;
            document.getElementById('editUsername').value = username;
            document.getElementById('editFullName').value = fullName;
            document.getElementById('editEmail').value = email;
            document.getElementById('editRole').value = role;
            document.getElementById('editIsActive').checked = isActive == 1;
        });

        // تغییر وضعیت کاربر
        function toggleUserStatus(userId) {
            if (confirm('آیا مطمئن هستید که می‌خواهید وضعیت این کاربر را تغییر دهید؟')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="toggle_user_status">
                    <input type="hidden" name="user_id" value="${userId}">
                    ${document.querySelector('input[name="csrf_token"]').outerHTML}
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // حذف کاربر
        function deleteUser(userId) {
            if (confirm('آیا مطمئن هستید که می‌خواهید این کاربر را حذف کنید؟ این عمل قابل بازگشت نیست.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" value="${userId}">
                    ${document.querySelector('input[name="csrf_token"]').outerHTML}
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>