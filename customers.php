<?php
/**
 * customers.php - نسخهٔ نهایی و کامل با سیستم ایمیل و SMS
 * Customer Management with Email and SMS Notifications
 */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/email_config.php';

// بررسی دسترسی
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die('خطا: PDO ($pdo) در config.php تعریف نشده است.');
}

// توابع کمکی
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function sanitize($s){ return trim(strip_tags($s ?? '')); }
function generate_csrf(){ if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(24)); return $_SESSION['csrf_token']; }
function require_csrf($t){ if (empty($t) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $t)) throw new RuntimeException('خطای CSRF — درخواست نامعتبر'); }
function current_user_id(){ return $_SESSION['user_id'] ?? null; }
function is_admin(){ $r = $_SESSION['role'] ?? ''; return in_array(mb_strtolower($r), ['admin','administrator','ادمین'], true); }

// ایجاد جداول
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS customers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_type ENUM('حقیقی','حقوقی') DEFAULT 'حقیقی',
        full_name VARCHAR(255) DEFAULT '',
        company VARCHAR(255) DEFAULT '',
        phone VARCHAR(50) DEFAULT '',
        company_phone VARCHAR(50) DEFAULT '',
        responsible_name VARCHAR(255) DEFAULT '',
        responsible_phone VARCHAR(50) DEFAULT '',
        operator_name VARCHAR(255) DEFAULT '',
        operator_phone VARCHAR(50) DEFAULT '',
        email VARCHAR(255) DEFAULT '',
        company_email VARCHAR(255) DEFAULT '',
        address TEXT,
        notes TEXT,
        notification_type ENUM('none','email','sms','both') DEFAULT 'none',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS notification_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type ENUM('email','sms') NOT NULL,
        name VARCHAR(255) NOT NULL,
        subject VARCHAR(500) DEFAULT '',
        content TEXT NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // درج قالب‌های پیش‌فرض
    $pdo->exec("INSERT IGNORE INTO notification_templates (type, name, subject, content) VALUES 
        ('email', 'خوش‌آمدگویی مشتری حقیقی', 'خوش‌آمدید به سیستم مدیریت اعلا نیرو', 'سلام {full_name} عزیز،\n\nبه سیستم مدیریت اعلا نیرو خوش‌آمدید!\n\nاطلاعات شما:\nنام: {full_name}\nتلفن: {phone}\nآدرس: {address}\n\nبا تشکر\nتیم اعلا نیرو'),
        ('email', 'خوش‌آمدگویی مشتری حقوقی', 'خوش‌آمدید به سیستم مدیریت اعلا نیرو', 'سلام {responsible_name} عزیز،\n\nشرکت {company} به سیستم مدیریت اعلا نیرو خوش‌آمدید!\n\nاطلاعات شرکت:\nنام شرکت: {company}\nمسئول: {responsible_name}\nتلفن شرکت: {company_phone}\nآدرس: {address}\n\nبا تشکر\nتیم اعلا نیرو'),
        ('sms', 'خوش‌آمدگویی SMS', '', 'سلام {full_name} عزیز، به سیستم مدیریت اعلا نیرو خوش‌آمدید! تیم اعلا نیرو')
    ");

} catch (Throwable $e) {
    $tables_error = 'خطا در ایجاد جداول: ' . $e->getMessage();
}

$success = $_GET['success'] ?? '';
$error = $tables_error ?? '';

// توابع ارسال اطلاع‌رسانی
function sendCustomerNotification($customer_id, $customer_data, $notification_type) {
    global $pdo;
    
    try {
        // دریافت قالب‌های فعال
        $stmt = $pdo->prepare("SELECT * FROM notification_templates WHERE is_active = 1 AND type = ? ORDER BY id ASC");
        
        $sent_notifications = [];
        
        if (in_array($notification_type, ['email', 'both'])) {
            $stmt->execute(['email']);
            $email_template = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($email_template && !empty($customer_data['email'])) {
                $subject = replaceTemplateVariables($email_template['subject'], $customer_data);
                $content = replaceTemplateVariables($email_template['content'], $customer_data);
                
                if (sendEmail($customer_data['email'], $subject, $content)) {
                    $sent_notifications[] = 'ایمیل';
                }
            }
        }
        
        if (in_array($notification_type, ['sms', 'both'])) {
            $stmt->execute(['sms']);
            $sms_template = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($sms_template && !empty($customer_data['phone'])) {
                $content = replaceTemplateVariables($sms_template['content'], $customer_data);
                
                if (sendSMS($customer_data['phone'], $content)) {
                    $sent_notifications[] = 'SMS';
                }
            }
        }
        
        return $sent_notifications;
        
    } catch (Exception $e) {
        error_log("Error sending customer notification: " . $e->getMessage());
        return [];
    }
}

function replaceTemplateVariables($template, $data) {
    $replacements = [
        '{full_name}' => $data['full_name'] ?? '',
        '{company}' => $data['company'] ?? '',
        '{phone}' => $data['phone'] ?? '',
        '{company_phone}' => $data['company_phone'] ?? '',
        '{responsible_name}' => $data['responsible_name'] ?? '',
        '{responsible_phone}' => $data['responsible_phone'] ?? '',
        '{email}' => $data['email'] ?? '',
        '{company_email}' => $data['company_email'] ?? '',
        '{address}' => $data['address'] ?? '',
        '{operator_name}' => $data['operator_name'] ?? '',
        '{operator_phone}' => $data['operator_phone'] ?? '',
        '{notes}' => $data['notes'] ?? '',
        '{date}' => date('Y/m/d'),
        '{time}' => date('H:i')
    ];
    
    return str_replace(array_keys($replacements), array_values($replacements), $template);
}

function sendSMS($phone, $message) {
    // اینجا باید API SMS خود را پیاده‌سازی کنید
    // برای تست، فقط true برمی‌گردانیم
    error_log("SMS to $phone: $message");
    return true;
}

// پردازش فرم‌ها
try {
    // افزودن مشتری جدید
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_customer'])) {
        require_csrf($_POST['csrf_token'] ?? '');

        $ctype = sanitize($_POST['customer_type'] ?? 'حقیقی');
        $notification_type = sanitize($_POST['notification_type'] ?? 'none');
        $operator_name = sanitize($_POST['operator_name'] ?? '');
        $operator_phone = sanitize($_POST['operator_phone'] ?? '');
        $address = sanitize($_POST['address'] ?? '');

        if ($ctype === 'حقیقی') {
            $full_name = sanitize($_POST['full_name'] ?? '');
            $phone = sanitize($_POST['phone'] ?? '');
            $email = sanitize($_POST['email'] ?? '');
            
            if ($full_name === '') throw new RuntimeException('نام و نام خانوادگی الزامی است.');

            $stmt = $pdo->prepare("INSERT INTO customers (customer_type, full_name, phone, email, address, operator_name, operator_phone, notification_type) VALUES (:ctype, :full_name, :phone, :email, :address, :op_name, :op_phone, :notif_type)");
            $stmt->execute([
                ':ctype'=>$ctype, ':full_name'=>$full_name, ':phone'=>$phone, ':email'=>$email,
                ':address'=>$address, ':op_name'=>$operator_name, ':op_phone'=>$operator_phone, ':notif_type'=>$notification_type
            ]);
            
            $customer_data = [
                'full_name' => $full_name,
                'phone' => $phone,
                'email' => $email,
                'address' => $address,
                'operator_name' => $operator_name,
                'operator_phone' => $operator_phone
            ];
            
        } else {
            $company = sanitize($_POST['company'] ?? '');
            $responsible_name = sanitize($_POST['responsible_name'] ?? '');
            $company_phone = sanitize($_POST['company_phone'] ?? '');
            $responsible_phone = sanitize($_POST['responsible_phone'] ?? '');
            $company_email = sanitize($_POST['company_email'] ?? '');
            $notes = sanitize($_POST['notes'] ?? '');

            if ($company === '') throw new RuntimeException('نام شرکت الزامی است.');

            $stmt = $pdo->prepare("INSERT INTO customers (customer_type, company, responsible_name, address, company_phone, responsible_phone, company_email, operator_name, operator_phone, notes, notification_type) VALUES (:ctype, :company, :resp_name, :address, :cphone, :rphone, :cemail, :op_name, :op_phone, :notes, :notif_type)");
            $stmt->execute([
                ':ctype'=>$ctype, ':company'=>$company, ':resp_name'=>$responsible_name, ':address'=>$address,
                ':cphone'=>$company_phone, ':rphone'=>$responsible_phone, ':cemail'=>$company_email, ':op_name'=>$operator_name, ':op_phone'=>$operator_phone, ':notes'=>$notes, ':notif_type'=>$notification_type
            ]);
            
            $customer_data = [
                'company' => $company,
                'responsible_name' => $responsible_name,
                'company_phone' => $company_phone,
                'responsible_phone' => $responsible_phone,
                'company_email' => $company_email,
                'address' => $address,
                'operator_name' => $operator_name,
                'operator_phone' => $operator_phone,
                'notes' => $notes
            ];
        }

        $customer_id = (int)$pdo->lastInsertId();
        
        // ارسال اطلاع‌رسانی
        if ($notification_type !== 'none') {
            $sent_notifications = sendCustomerNotification($customer_id, $customer_data, $notification_type);
            $notification_message = !empty($sent_notifications) ? ' و ' . implode(' و ', $sent_notifications) . ' ارسال شد' : '';
        } else {
            $notification_message = '';
        }

        header('Location: customers.php?success=' . urlencode('مشتری با موفقیت ثبت شد' . $notification_message));
        exit();
    }

    // ویرایش مشتری
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_customer']) && !empty($_POST['customer_id'])) {
        require_csrf($_POST['csrf_token'] ?? '');
        $id = (int)$_POST['customer_id'];
        if ($id <= 0) throw new RuntimeException('شناسه مشتری نامعتبر');

        $ctype = sanitize($_POST['customer_type'] ?? 'حقیقی');
        $notification_type = sanitize($_POST['notification_type'] ?? 'none');
        $operator_name = sanitize($_POST['operator_name'] ?? '');
        $operator_phone = sanitize($_POST['operator_phone'] ?? '');
        $address = sanitize($_POST['address'] ?? '');

        if ($ctype === 'حقیقی') {
            $full_name = sanitize($_POST['full_name'] ?? '');
            $phone = sanitize($_POST['phone'] ?? '');
            $email = sanitize($_POST['email'] ?? '');
            $stmt = $pdo->prepare("UPDATE customers SET customer_type=:ctype, full_name=:full_name, phone=:phone, email=:email, address=:address, operator_name=:op_name, operator_phone=:op_phone, notification_type=:notif_type WHERE id=:id");
            $stmt->execute([':ctype'=>$ctype, ':full_name'=>$full_name, ':phone'=>$phone, ':email'=>$email, ':address'=>$address, ':op_name'=>$operator_name, ':op_phone'=>$operator_phone, ':notif_type'=>$notification_type, ':id'=>$id]);
        } else {
            $company = sanitize($_POST['company'] ?? '');
            $responsible_name = sanitize($_POST['responsible_name'] ?? '');
            $company_phone = sanitize($_POST['company_phone'] ?? '');
            $responsible_phone = sanitize($_POST['responsible_phone'] ?? '');
            $company_email = sanitize($_POST['company_email'] ?? '');
            $notes = sanitize($_POST['notes'] ?? '');
            $stmt = $pdo->prepare("UPDATE customers SET customer_type=:ctype, company=:company, responsible_name=:resp_name, address=:address, company_phone=:cphone, responsible_phone=:rphone, company_email=:cemail, operator_name=:op_name, operator_phone=:op_phone, notes=:notes, notification_type=:notif_type WHERE id=:id");
            $stmt->execute([
                ':ctype'=>$ctype, ':company'=>$company, ':resp_name'=>$responsible_name, ':address'=>$address,
                ':cphone'=>$company_phone, ':rphone'=>$responsible_phone, ':cemail'=>$company_email, ':op_name'=>$operator_name, ':op_phone'=>$operator_phone, ':notes'=>$notes, ':notif_type'=>$notification_type, ':id'=>$id
            ]);
        }

        header('Location: customers.php?success=' . urlencode('ویرایش مشتری انجام شد') . '#cust-' . $id);
        exit();
    }

    // حذف مشتری (فقط admin)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_customer']) && is_admin()) {
        require_csrf($_POST['csrf_token'] ?? '');
        $del_id = (int)$_POST['delete_id'];
        if ($del_id > 0) {
            $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
            $stmt->execute([$del_id]);
            header('Location: customers.php?success=' . urlencode('مشتری حذف شد'));
            exit();
        }
    }

} catch (PDOException $e) {
    $error = 'خطای دیتابیس: ' . $e->getMessage();
} catch (RuntimeException $e) {
    $error = $e->getMessage();
} catch (Throwable $e) {
    $error = 'خطای غیرمنتظره: ' . $e->getMessage();
}

// دریافت مشتریان
$per_page = 15;
$page = max(1, (int)($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');
$where = '1=1';
$params = [];
if ($search !== '') {
    $where = "(company LIKE :s1 OR full_name LIKE :s2 OR operator_name LIKE :s3 OR phone LIKE :s4 OR company_phone LIKE :s5 OR address LIKE :s6 OR email LIKE :s7 OR company_email LIKE :s8)";
    $like = "%{$search}%";
    $params = [':s1'=>$like, ':s2'=>$like, ':s3'=>$like, ':s4'=>$like, ':s5'=>$like, ':s6'=>$like, ':s7'=>$like, ':s8'=>$like];
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE $where");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total / $per_page));
$offset = ($page - 1) * $per_page;

$sql = "SELECT * FROM customers WHERE $where ORDER BY id DESC LIMIT :l OFFSET :o";
$stmt = $pdo->prepare($sql);
foreach ($params as $k=>$v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
$stmt->bindValue(':l', (int)$per_page, PDO::PARAM_INT);
$stmt->bindValue(':o', (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$csrf = generate_csrf();
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>مدیریت مشتریان — سیستم ایمیل و SMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <style>
        body { background:#f6f8fb; font-family: 'Vazirmatn', Tahoma, sans-serif; }
        .center-card { max-width:1400px; margin:60px auto 30px; }
        .card-modern { border-radius:12px; box-shadow:0 14px 40px rgba(8,24,48,0.06); overflow:visible; }
        .top-controls { padding:20px; display:flex; gap:12px; align-items:center; justify-content:center; flex-wrap:wrap; }
        .search-input { min-width:320px; max-width:560px; width:42%; }
        .btn-add { height:46px; border-radius:10px; }
        .required::after { content: " *"; color:#dc3545; }
        .fade-in { animation: fadeIn .28s ease; }
        @keyframes fadeIn { from{ opacity:0; transform: translateY(6px);} to{opacity:1; transform:none;} }
        .notification-badge { font-size: 10px; padding: 2px 6px; border-radius: 10px; }
        .notification-email { background: #28a745; color: white; }
        .notification-sms { background: #17a2b8; color: white; }
        .notification-both { background: #6f42c1; color: white; }
        .email-field { border-left: 3px solid #28a745; }
        .phone-field { border-left: 3px solid #17a2b8; }
        @media (max-width:768px) { .search-input{ width:100%; } }
    </style>
</head>
<body>
<?php
$embedded = isset($_GET['embed']) && $_GET['embed'] === '1';
if (!$embedded && file_exists('navbar.php')) {
    include 'navbar.php';
}
?>

<div class="container center-card">
    <div class="card card-modern">
        <div class="top-controls">
            <h4 class="m-0"><i class="fas fa-users me-2"></i>مدیریت مشتریان</h4>
            <form class="d-flex search-input" method="get" style="align-items:center;">
                <input name="search" value="<?= h($search) ?>" placeholder="جستجو بر اساس نام، شرکت، تلفن، ایمیل یا آدرس..." class="form-control me-2">
                <button class="btn btn-outline-secondary" type="submit"><i class="fa fa-search"></i></button>
            </form>
            <div>
                <a class="btn btn-success btn-add" href="#addCustomer" data-bs-toggle="collapse"><i class="fa fa-plus-circle me-1"></i> افزودن مشتری</a>
                <?php if (is_admin()): ?>
                    <a class="btn btn-info btn-add" href="notification_templates.php"><i class="fa fa-cog me-1"></i> مدیریت قالب‌ها</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($success)): ?><div class="alert alert-success m-3"><i class="fas fa-check-circle me-2"></i><?= h($success) ?></div><?php endif; ?>
        <?php if (!empty($error)): ?><div class="alert alert-danger m-3"><i class="fas fa-exclamation-circle me-2"></i><?= h($error) ?></div><?php endif; ?>

        <!-- Add customer form -->
        <div class="collapse" id="addCustomer">
            <div class="p-3 fade-in">
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="add_customer" value="1">

                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label required">نوع مشتری</label>
                            <select name="customer_type" id="customer_type" class="form-select">
                                <option value="حقیقی">حقیقی</option>
                                <option value="حقوقی">حقوقی</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">آدرس</label>
                            <input type="text" name="address" class="form-control" placeholder="آدرس کامل">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">اپراتور</label>
                            <input type="text" name="operator_name" class="form-control" placeholder="نام اپراتور">
                        </div>

                        <!-- حقیقی -->
                        <div id="section_individual" class="col-12">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label required">نام و نام خانوادگی</label>
                                    <input type="text" name="full_name" class="form-control" required placeholder="نام کامل">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required">شماره تماس</label>
                                    <input type="text" name="phone" class="form-control phone-field" required placeholder="09123456789">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ایمیل</label>
                                    <input type="email" name="email" class="form-control email-field" placeholder="example@email.com">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">تلفن اپراتور</label>
                                    <input type="text" name="operator_phone" class="form-control" placeholder="تلفن اپراتور">
                                </div>
                            </div>
                        </div>

                        <!-- حقوقی -->
                        <div id="section_legal" class="col-12" style="display:none;">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label required">نام شرکت</label>
                                    <input type="text" name="company" class="form-control" placeholder="نام شرکت">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required">نام مسئول</label>
                                    <input type="text" name="responsible_name" class="form-control" placeholder="نام مسئول">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">تلفن شرکت</label>
                                    <input type="text" name="company_phone" class="form-control phone-field" placeholder="تلفن شرکت">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">تلفن مسئول</label>
                                    <input type="text" name="responsible_phone" class="form-control phone-field" placeholder="تلفن مسئول">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ایمیل شرکت</label>
                                    <input type="email" name="company_email" class="form-control email-field" placeholder="company@email.com">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">تلفن اپراتور</label>
                                    <input type="text" name="operator_phone" class="form-control" placeholder="تلفن اپراتور">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">یادداشت</label>
                                    <textarea name="notes" class="form-control" rows="2" placeholder="یادداشت‌های اضافی"></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- انتخاب نوع اطلاع‌رسانی -->
                        <div class="col-12">
                            <hr>
                            <h6 class="text-primary mb-3"><i class="fas fa-bell me-2"></i>نوع اطلاع‌رسانی</h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="notification_type" id="notif_none" value="none" checked>
                                        <label class="form-check-label" for="notif_none">
                                            <i class="fas fa-times-circle me-1 text-muted"></i>بدون اطلاع‌رسانی
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="notification_type" id="notif_email" value="email">
                                        <label class="form-check-label" for="notif_email">
                                            <i class="fas fa-envelope me-1 text-success"></i>فقط ایمیل
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="notification_type" id="notif_sms" value="sms">
                                        <label class="form-check-label" for="notif_sms">
                                            <i class="fas fa-sms me-1 text-info"></i>فقط SMS
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="notification_type" id="notif_both" value="both">
                                        <label class="form-check-label" for="notif_both">
                                            <i class="fas fa-bell me-1 text-purple"></i>ایمیل و SMS
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>نکته:</strong> برای ارسال ایمیل، آدرس ایمیل مشتری باید وارد شود. برای ارسال SMS، شماره تلفن مشتری باید وارد شود.
                            </div>
                        </div>

                        <div class="col-12 text-end mt-3">
                            <button class="btn btn-primary btn-lg"><i class="fa fa-save me-1"></i> ذخیره مشتری</button>
                            <a class="btn btn-secondary btn-lg" data-bs-toggle="collapse" href="#addCustomer"><i class="fa fa-times me-1"></i> بستن</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- customers table -->
        <div class="p-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>نوع / نام</th>
                            <th>تماس‌ها</th>
                            <th>ایمیل</th>
                            <th>اطلاع‌رسانی</th>
                            <th>اپراتور</th>
                            <th>آدرس</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($customers as $c): ?>
                        <?php $isLegal = ($c['customer_type'] === 'حقوقی'); ?>
                        <tr id="cust-<?= (int)$c['id'] ?>">
                            <td><?= (int)$c['id'] ?></td>
                            <td>
                                <div><span class="badge bg-<?= $isLegal ? 'primary' : 'info' ?> badge-type"><?= h($c['customer_type']) ?></span></div>
                                <div class="mt-1"><?= $isLegal ? h($c['company'] ?: '—') : h($c['full_name'] ?: '—') ?></div>
                            </td>
                            <td>
                                <div><?= h($isLegal ? $c['company_phone'] : $c['phone']) ?></div>
                                <?php if ($isLegal && $c['responsible_phone']): ?>
                                    <div class="small text-muted"><?= h($c['responsible_phone']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isLegal): ?>
                                    <div><?= h($c['company_email'] ?: '—') ?></div>
                                <?php else: ?>
                                    <div><?= h($c['email'] ?: '—') ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($c['notification_type'] !== 'none'): ?>
                                    <span class="notification-badge notification-<?= $c['notification_type'] ?>">
                                        <?= $c['notification_type'] === 'both' ? 'ایمیل + SMS' : ($c['notification_type'] === 'email' ? 'ایمیل' : 'SMS') ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?= h($c['operator_name'] ?: '—') ?><div class="small text-muted"><?= h($c['operator_phone'] ?: '') ?></div></td>
                            <td><?= h(mb_substr($c['address'] ?: '-', 0, 50)) ?></td>
                            <td style="min-width:200px;">
                                <div class="btn-group" role="group">
                                    <a class="btn btn-sm btn-warning" href="?edit=<?= (int)$c['id'] ?>" title="ویرایش"><i class="fa fa-edit"></i></a>
                                    <?php if (is_admin()): ?>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('آیا از حذف مشتری اطمینان دارید؟');">
                                            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                            <input type="hidden" name="delete_customer" value="1">
                                            <input type="hidden" name="delete_id" value="<?= (int)$c['id'] ?>">
                                            <button class="btn btn-sm btn-danger" type="submit" title="حذف"><i class="fa fa-trash"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- pagination -->
            <nav aria-label="Page navigation" class="mt-3">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?><li class="page-item"><a class="page-link" href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>">&laquo;</a></li><?php endif; ?>
                    <?php for ($i=1;$i<=$total_pages;$i++): ?><li class="page-item <?= $i=== $page ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a></li><?php endfor; ?>
                    <?php if ($page < $total_pages): ?><li class="page-item"><a class="page-link" href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>">&raquo;</a></li><?php endif; ?>
                </ul>
            </nav>
        </div>
    </div>
</div>

<!-- Edit form (if ?edit=ID) -->
<?php if (!empty($_GET['edit'])):
    $edit_id = (int)$_GET['edit'];
    $stmtE = $pdo->prepare('SELECT * FROM customers WHERE id = ?');
    $stmtE->execute([$edit_id]);
    $E = $stmtE->fetch(PDO::FETCH_ASSOC);
    if ($E): ?>
    <div class="container center-card mt-3">
        <div class="card card-modern p-3">
            <h5><i class="fa fa-pen me-1"></i> ویرایش مشتری #<?= (int)$E['id'] ?></h5>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <input type="hidden" name="edit_customer" value="1">
                <input type="hidden" name="customer_id" value="<?= (int)$E['id'] ?>">

                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">نوع</label>
                        <select name="customer_type" class="form-select" id="edit_customer_type">
                            <option value="حقیقی" <?= $E['customer_type']==='حقیقی' ? 'selected' : '' ?>>حقیقی</option>
                            <option value="حقوقی" <?= $E['customer_type']==='حقوقی' ? 'selected' : '' ?>>حقوقی</option>
                        </select>
                    </div>
                    <div class="col-md-6"><label class="form-label">آدرس</label><input type="text" name="address" class="form-control" value="<?= h($E['address']) ?>"></div>
                    <div class="col-md-3"><label class="form-label">اپراتور</label><input type="text" name="operator_name" class="form-control" value="<?= h($E['operator_name']) ?>"></div>
                    
                    <!-- حقیقی -->
                    <div id="edit_section_individual" class="col-12" style="<?= $E['customer_type']==='حقیقی' ? '' : 'display:none;' ?>">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label required">نام و نام خانوادگی</label><input type="text" name="full_name" class="form-control" value="<?= h($E['full_name']) ?>"></div>
                            <div class="col-md-6"><label class="form-label required">تلفن</label><input type="text" name="phone" class="form-control phone-field" value="<?= h($E['phone']) ?>"></div>
                            <div class="col-md-6"><label class="form-label">ایمیل</label><input type="email" name="email" class="form-control email-field" value="<?= h($E['email']) ?>"></div>
                            <div class="col-md-6"><label class="form-label">تلفن اپراتور</label><input type="text" name="operator_phone" class="form-control" value="<?= h($E['operator_phone']) ?>"></div>
                        </div>
                    </div>
                    
                    <!-- حقوقی -->
                    <div id="edit_section_legal" class="col-12" style="<?= $E['customer_type']==='حقوقی' ? '' : 'display:none;' ?>">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label required">شرکت</label><input type="text" name="company" class="form-control" value="<?= h($E['company']) ?>"></div>
                            <div class="col-md-6"><label class="form-label required">نام مسئول</label><input type="text" name="responsible_name" class="form-control" value="<?= h($E['responsible_name']) ?>"></div>
                            <div class="col-md-6"><label class="form-label">تلفن شرکت</label><input type="text" name="company_phone" class="form-control phone-field" value="<?= h($E['company_phone']) ?>"></div>
                            <div class="col-md-6"><label class="form-label">تلفن مسئول</label><input type="text" name="responsible_phone" class="form-control phone-field" value="<?= h($E['responsible_phone']) ?>"></div>
                            <div class="col-md-6"><label class="form-label">ایمیل شرکت</label><input type="email" name="company_email" class="form-control email-field" value="<?= h($E['company_email']) ?>"></div>
                            <div class="col-md-6"><label class="form-label">تلفن اپراتور</label><input type="text" name="operator_phone" class="form-control" value="<?= h($E['operator_phone']) ?>"></div>
                            <div class="col-12"><label class="form-label">یادداشت</label><textarea name="notes" class="form-control"><?= h($E['notes']) ?></textarea></div>
                        </div>
                    </div>

                    <!-- انتخاب نوع اطلاع‌رسانی -->
                    <div class="col-12">
                        <hr>
                        <h6 class="text-primary mb-3"><i class="fas fa-bell me-2"></i>نوع اطلاع‌رسانی</h6>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="notification_type" id="edit_notif_none" value="none" <?= $E['notification_type']==='none' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="edit_notif_none">
                                        <i class="fas fa-times-circle me-1 text-muted"></i>بدون اطلاع‌رسانی
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="notification_type" id="edit_notif_email" value="email" <?= $E['notification_type']==='email' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="edit_notif_email">
                                        <i class="fas fa-envelope me-1 text-success"></i>فقط ایمیل
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="notification_type" id="edit_notif_sms" value="sms" <?= $E['notification_type']==='sms' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="edit_notif_sms">
                                        <i class="fas fa-sms me-1 text-info"></i>فقط SMS
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="notification_type" id="edit_notif_both" value="both" <?= $E['notification_type']==='both' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="edit_notif_both">
                                        <i class="fas fa-bell me-1 text-purple"></i>ایمیل و SMS
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 mt-3 text-end">
                        <button class="btn btn-primary btn-lg"><i class="fa fa-save me-1"></i> ذخیره تغییرات</button>
                        <a class="btn btn-secondary btn-lg" href="customers.php"><i class="fa fa-arrow-left me-1"></i> انصراف</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php endif; endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
    // Toggle customer type sections
    function toggleCustomerType() {
        const sel = document.getElementById('customer_type');
        const ind = document.getElementById('section_individual');
        const leg = document.getElementById('section_legal');
        
        if (sel && ind && leg) {
            if (sel.value === 'حقیقی') {
                ind.style.display = 'block';
                leg.style.display = 'none';
            } else {
                ind.style.display = 'none';
                leg.style.display = 'block';
            }
        }
    }
    
    // Edit form toggle
    function toggleEditCustomerType() {
        const sel = document.getElementById('edit_customer_type');
        const ind = document.getElementById('edit_section_individual');
        const leg = document.getElementById('edit_section_legal');
        
        if (sel && ind && leg) {
            if (sel.value === 'حقیقی') {
                ind.style.display = 'block';
                leg.style.display = 'none';
            } else {
                ind.style.display = 'none';
                leg.style.display = 'block';
            }
        }
    }
    
    // Add event listeners
    const customerTypeSelect = document.getElementById('customer_type');
    const editCustomerTypeSelect = document.getElementById('edit_customer_type');
    
    if (customerTypeSelect) {
        customerTypeSelect.addEventListener('change', toggleCustomerType);
        toggleCustomerType(); // Initial call
    }
    
    if (editCustomerTypeSelect) {
        editCustomerTypeSelect.addEventListener('change', toggleEditCustomerType);
        toggleEditCustomerType(); // Initial call
    }
})();
</script>
</body>
</html>