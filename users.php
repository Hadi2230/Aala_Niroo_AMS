<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// بررسی دسترسی ادمین
if (!hasPermission('*')) {
    die('دسترسی غیرمجاز - فقط ادمین می‌تواند کاربران را مدیریت کند');
}

// دریافت نقش‌ها و مجوزها
$roles = getRoles();
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
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    return $user ? $user['password'] : null;
}

// پردازش فرم‌ها
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
                    try {
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
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $error_message = "خطا در ایجاد کاربر: " . $e->getMessage();
                    }
                } else {
                    $error_message = "لطفاً تمام فیلدهای ضروری را پر کنید";
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
                
                if ($user_id && $username && $full_name && $role) {
                    try {
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
                        
                        // به‌روزرسانی کاربر
                        $stmt = $pdo->prepare("UPDATE users SET username = ?, full_name = ?, email = ?, role = ?, is_active = ? WHERE id = ?");
                        $stmt->execute([$username, $full_name, $email, $role, $is_active, $user_id]);
                        
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
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $error_message = "خطا در به‌روزرسانی کاربر: " . $e->getMessage();
                    }
                } else {
                    $error_message = "لطفاً تمام فیلدهای ضروری را پر کنید";
                }
                break;
                
            case 'delete_user':
                $user_id = sanitizeInput($_POST['user_id']);
                
                if ($user_id && $user_id != $_SESSION['user_id']) { // نمی‌توان خود را حذف کرد
                    try {
                        $pdo->beginTransaction();
                        
                        // حذف دسترسی‌های سفارشی
                        $stmt = $pdo->prepare("DELETE FROM custom_roles WHERE user_id = ?");
                        $stmt->execute([$user_id]);
                        
                        // حذف کاربر
                        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                        $stmt->execute([$user_id]);
                        
                        $pdo->commit();
                        $success_message = "کاربر با موفقیت حذف شد";
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $error_message = "خطا در حذف کاربر: " . $e->getMessage();
                    }
                } else {
                    $error_message = "نمی‌توانید خود را حذف کنید";
                }
                break;
                
            case 'toggle_user_status':
                $user_id = sanitizeInput($_POST['user_id']);
                
                if ($user_id && $user_id != $_SESSION['user_id']) { // نمی‌توان خود را غیرفعال کرد
                    try {
                        $stmt = $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
                        $stmt->execute([$user_id]);
                        
                        $success_message = "وضعیت کاربر با موفقیت تغییر یافت";
                    } catch (Exception $e) {
                        $error_message = "خطا در تغییر وضعیت کاربر: " . $e->getMessage();
                    }
                } else {
                    $error_message = "نمی‌توانید وضعیت خود را تغییر دهید";
                }
                break;
        }
    }
}

// دریافت لیست کاربران
$users = $pdo->query("SELECT id, username, full_name, email, role, is_active, last_login, created_at FROM users ORDER BY created_at DESC")->fetchAll();
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت کاربران - شرکت اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <style>
        body { font-family: Vazirmatn, sans-serif; background-color: #f8f9fa; padding-top: 80px; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .card-header { background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); color: white; border-radius: 12px 12px 0 0 !important; font-weight: 600; }
        .permission-group { background: #f8f9fa; border-radius: 8px; padding: 15px; margin-bottom: 15px; }
        .permission-item { margin-bottom: 10px; }
        .btn-group-sm .btn { margin-left: 2px; }
        .input-icon { position: relative; }
        .input-icon i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #6c757d; z-index: 5; }
        .input-icon .form-control { padding-left: 45px; border-radius: 10px; border: 2px solid #e1e5eb; padding: 16px 20px; transition: 0.3s; }
        .input-icon .form-control:focus { border-color: #3498db; box-shadow: 0 0 0 0.25rem rgba(52,152,219,0.25); }
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
        .password-toggle:hover { color: #3498db; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h2><i class="fa fa-users"></i> مدیریت کاربران</h2>
                
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error_message; ?>
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
                                        <label class="form-label">نام کاربری</label>
                                        <input type="text" class="form-control" name="username" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">رمز عبور</label>
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
                                        <label class="form-label">نام کامل</label>
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
                                        <label class="form-label">نقش</label>
                                        <select class="form-select" name="role" id="roleSelect" required>
                                            <option value="">انتخاب کنید</option>
                                            <?php foreach ($roles as $role_key => $role_data): ?>
                                                <option value="<?php echo $role_key; ?>"><?php echo $role_key; ?></option>
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
                        <i class="fa fa-list"></i> لیست کاربران
                    </div>
                    <div class="card-body">
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
                                                <?php echo $user['last_login'] ? jalaliDate($user['last_login']) : 'هرگز'; ?>
                                            </td>
                                            <td><?php echo jalaliDate($user['created_at']); ?></td>
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
                                                    
                                                    <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#permissionsModal" 
                                                            data-user-id="<?php echo $user['id']; ?>" 
                                                            data-user-name="<?php echo htmlspecialchars($user['full_name']); ?>">
                                                        <i class="fa fa-user-shield"></i>
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
                    </div>
                </div>
            </div>
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

        // تابع نمایش/مخفی کردن رمز عبور فعلی در ویرایش
        function toggleCurrentPassword() {
            const passwordInput = document.getElementById('currentPassword');
            const passwordToggle = document.getElementById('currentPasswordToggle');
            
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
            
            // بارگذاری رمز عبور فعلی
            loadCurrentPassword(userId);
            
            // نمایش دسترسی‌های سفارشی اگر نقش سفارشی باشد
            if (role === 'سفارشی') {
                document.getElementById('editCustomPermissions').style.display = 'block';
                // بارگذاری دسترسی‌های فعلی کاربر
                loadUserPermissions(userId);
            } else {
                document.getElementById('editCustomPermissions').style.display = 'none';
            }
        });

        // بارگذاری رمز عبور فعلی
        function loadCurrentPassword(userId) {
            fetch('get_user_password.php?user_id=' + userId)
                .then(response => response.json())
                .then(data => {
                    if (data.password) {
                        document.getElementById('currentPassword').value = data.password;
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        // بارگذاری دسترسی‌های کاربر
        function loadUserPermissions(userId) {
            fetch('get_user_permissions.php?user_id=' + userId)
                .then(response => response.json())
                .then(data => {
                    // پاک کردن همه چک‌باکس‌ها
                    document.querySelectorAll('#editCustomPermissions input[type="checkbox"]').forEach(checkbox => {
                        checkbox.checked = false;
                    });
                    
                    // انتخاب دسترسی‌های کاربر
                    if (data.permissions) {
                        data.permissions.forEach(permission => {
                            const checkbox = document.querySelector(`#editCustomPermissions input[value="${permission}"]`);
                            if (checkbox) {
                                checkbox.checked = true;
                            }
                        });
                    }
                })
                .catch(error => console.error('Error:', error));
        }

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
    $username = trim($_POST['username']);
    $role = $_POST['role'];
    $email = trim($_POST['email']);
    $full_name = trim($_POST['full_name']);
    $password = $_POST['password'];
    
    $errors = [];
    
    // بررسی تکراری نبودن username (به جز برای کاربر فعلی)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmt->execute([$username, $user_id]);
    if ($stmt->fetch()) {
        $errors[] = "نام کاربری already exists.";
    }
    
    if (empty($errors)) {
        if (!empty($password)) {
            // اگر رمز عبور جدید ارائه شده
            if (strlen($password) < 6) {
                $errors[] = "رمز عبور باید حداقل ۶ کاراکتر باشد.";
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, role = ?, email = ?, full_name = ? WHERE id = ?");
                $stmt->execute([$username, $password_hash, $role, $email, $full_name, $user_id]);
            }
        } else {
            // بدون تغییر رمز عبور
            $stmt = $pdo->prepare("UPDATE users SET username = ?, role = ?, email = ?, full_name = ? WHERE id = ?");
            $stmt->execute([$username, $role, $email, $full_name, $user_id]);
        }
        
        if (empty($errors)) {
            $_SESSION['success'] = "کاربر با موفقیت ویرایش شد!";
            header('Location: users.php');
            exit();
        }
    }
}

// دریافت کلیه کاربران
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

if (!empty($search)) {
    $stmt = $pdo->prepare("SELECT * FROM users 
                          WHERE username LIKE ? OR full_name LIKE ? OR email LIKE ? 
                          ORDER BY id DESC 
                          LIMIT :limit OFFSET :offset");
    $search_term = "%$search%";
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute([$search_term, $search_term, $search_term]);
    
    // تعداد کل نتایج
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM users 
                                WHERE username LIKE ? OR full_name LIKE ? OR email LIKE ?");
    $count_stmt->execute([$search_term, $search_term, $search_term]);
    $total_users = $count_stmt->fetchColumn();
} else {
    $stmt = $pdo->prepare("SELECT * FROM users ORDER BY id DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
}

$users = $stmt->fetchAll();
$total_pages = ceil($total_users / $per_page);

// نمایش پیام‌ها
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت کاربران - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .table-hover tbody tr:hover {
            background-color: rgba(52, 152, 219, 0.1);
        }
        .badge-admin {
            background-color: #dc3545;
        }
        .badge-user {
            background-color: #28a745;
        }
        .search-box {
            max-width: 300px;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <h2 class="text-center mb-4">مدیریت کاربران سیستم</h2>

        <!-- نمایش پیام‌ها -->
        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- جستجو -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>جستجو و فیلتر</span>
                <span class="badge bg-info">تعداد کاربران: <?php echo $total_users; ?></span>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-8">
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" placeholder="جستجو بر اساس نام کاربری، نام کامل یا ایمیل" value="<?php echo htmlspecialchars($search); ?>">
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-search"></i> جستجو
                            </button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <?php if (!empty($search)): ?>
                            <a href="users.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i> پاک کردن فیلتر
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- فرم افزودن کاربر جدید -->
        <div class="card mb-4">
            <div class="card-header">افزودن کاربر جدید</div>
            <div class="card-body">
                <form method="POST" id="addUserForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="username" class="form-label">نام کاربری *</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password" class="form-label">رمز عبور *</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <small class="form-text text-muted">حداقل ۶ کاراکتر</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="full_name" class="form-label">نام کامل</label>
                                <input type="text" class="form-control" id="full_name" name="full_name">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">ایمیل</label>
                                <input type="email" class="form-control" id="email" name="email">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="role" class="form-label">نقش *</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="کاربر عادی">کاربر عادی</option>
                                    <option value="ادمین">ادمین</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" name="add_user" class="btn btn-primary">
                        <i class="fas fa-plus"></i> افزودن کاربر
                    </button>
                </form>
            </div>
        </div>

        <!-- جدول نمایش کاربران -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>لیست کاربران</span>
                <div>
                    <span class="badge bg-secondary">صفحه <?php echo $page; ?> از <?php echo $total_pages; ?></span>
                </div>
            </div>
            <div class="card-body">
                <?php if (count($users) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>نام کاربری</th>
                                    <th>نام کامل</th>
                                    <th>ایمیل</th>
                                    <th>نقش</th>
                                    <th>تاریخ ایجاد</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['full_name'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($user['email'] ?? '-'); ?></td>
                                    <td>
                                        <span class="badge <?php echo $user['role'] == 'ادمین' ? 'badge-admin' : 'badge-user'; ?>">
                                            <?php echo $user['role']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $user['created_at']; ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-warning" data-bs-toggle="modal" 
                                                    data-bs-target="#editUserModal" 
                                                    data-user-id="<?php echo $user['id']; ?>"
                                                    data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                    data-full-name="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>"
                                                    data-email="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                                                    data-role="<?php echo $user['role']; ?>"
                                                    title="ویرایش">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <a href="users.php?delete_id=<?php echo $user['id']; ?>" 
                                               class="btn btn-danger" 
                                               title="حذف"
                                               onclick="return confirm('آیا از حذف کاربر \"<?php echo addslashes($user['username']); ?>\" مطمئن هستید؟')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <p class="text-muted">هیچ کاربری یافت نشد.</p>
                        <?php if (!empty($search)): ?>
                            <a href="users.php" class="btn btn-primary">مشاهده همه کاربران</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal ویرایش کاربر -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">ویرایش کاربر</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="editUserForm">
                    <div class="modal-body">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <input type="hidden" name="edit_user" value="1">
                        
                        <div class="mb-3">
                            <label for="edit_username" class="form-label">نام کاربری *</label>
                            <input type="text" class="form-control" id="edit_username" name="username" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_full_name" class="form-label">نام کامل</label>
                            <input type="text" class="form-control" id="edit_full_name" name="full_name">
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">ایمیل</label>
                            <input type="email" class="form-control" id="edit_email" name="email">
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_role" class="form-label">نقش *</label>
                            <select class="form-select" id="edit_role" name="role" required>
                                <option value="کاربر عادی">کاربر عادی</option>
                                <option value="ادمین">ادمین</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_password" class="form-label">رمز عبور جدید (اختیاری)</label>
                            <input type="password" class="form-control" id="edit_password" name="password">
                            <small class="form-text text-muted">در صورت تغییر رمز عبور، حداقل ۶ کاراکتر</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" class="btn btn-primary">ذخیره تغییرات</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // مدیریت modal ویرایش
        const editUserModal = document.getElementById('editUserModal');
        editUserModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const userId = button.getAttribute('data-user-id');
            const username = button.getAttribute('data-username');
            const fullName = button.getAttribute('data-full-name');
            const email = button.getAttribute('data-email');
            const role = button.getAttribute('data-role');
            
            document.getElementById('edit_user_id').value = userId;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_full_name').value = fullName;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_role').value = role;
        });

        // اعتبارسنجی فرم‌ها
        document.getElementById('addUserForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            if (password.length < 6) {
                e.preventDefault();
                alert('رمز عبور باید حداقل ۶ کاراکتر باشد.');
                document.getElementById('password').focus();
            }
        });

        document.getElementById('editUserForm').addEventListener('submit', function(e) {
            const password = document.getElementById('edit_password').value;
            if (password && password.length < 6) {
                e.preventDefault();
                alert('رمز عبور باید حداقل ۶ کاراکتر باشد.');
                document.getElementById('edit_password').focus();
            }
        });
    </script>
</body>
</html>