<?php
/**
 * notification_templates.php - مدیریت قالب‌های اطلاع‌رسانی
 * Notification Templates Management
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';

// بررسی دسترسی
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// بررسی دسترسی ادمین
if (!hasPermission('*')) {
    die('دسترسی غیرمجاز - فقط ادمین می‌تواند به این بخش دسترسی داشته باشد');
}

$success = $_GET['success'] ?? '';
$error = '';

// توابع کمکی
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function sanitize($s){ return trim(strip_tags($s ?? '')); }
function generate_csrf(){ if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(24)); return $_SESSION['csrf_token']; }
function require_csrf($t){ if (empty($t) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $t)) throw new RuntimeException('خطای CSRF — درخواست نامعتبر'); }

// پردازش فرم‌ها
try {
    // افزودن قالب جدید
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_template'])) {
        require_csrf($_POST['csrf_token'] ?? '');
        
        $type = sanitize($_POST['type'] ?? '');
        $name = sanitize($_POST['name'] ?? '');
        $subject = sanitize($_POST['subject'] ?? '');
        $content = sanitize($_POST['content'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($type) || empty($name) || empty($content)) {
            throw new RuntimeException('تمام فیلدهای الزامی باید پر شوند');
        }
        
        if (!in_array($type, ['email', 'sms'])) {
            throw new RuntimeException('نوع قالب باید ایمیل یا SMS باشد');
        }
        
        $stmt = $pdo->prepare("INSERT INTO notification_templates (type, name, subject, content, is_active) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$type, $name, $subject, $content, $is_active]);
        
        header('Location: notification_templates.php?success=' . urlencode('قالب جدید با موفقیت اضافه شد'));
        exit();
    }
    
    // ویرایش قالب
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_template'])) {
        require_csrf($_POST['csrf_token'] ?? '');
        
        $id = (int)($_POST['template_id'] ?? 0);
        $type = sanitize($_POST['type'] ?? '');
        $name = sanitize($_POST['name'] ?? '');
        $subject = sanitize($_POST['subject'] ?? '');
        $content = sanitize($_POST['content'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if ($id <= 0 || empty($type) || empty($name) || empty($content)) {
            throw new RuntimeException('تمام فیلدهای الزامی باید پر شوند');
        }
        
        if (!in_array($type, ['email', 'sms'])) {
            throw new RuntimeException('نوع قالب باید ایمیل یا SMS باشد');
        }
        
        $stmt = $pdo->prepare("UPDATE notification_templates SET type=?, name=?, subject=?, content=?, is_active=? WHERE id=?");
        $stmt->execute([$type, $name, $subject, $content, $is_active, $id]);
        
        header('Location: notification_templates.php?success=' . urlencode('قالب با موفقیت ویرایش شد'));
        exit();
    }
    
    // حذف قالب
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_template'])) {
        require_csrf($_POST['csrf_token'] ?? '');
        
        $id = (int)($_POST['template_id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM notification_templates WHERE id = ?");
            $stmt->execute([$id]);
            header('Location: notification_templates.php?success=' . urlencode('قالب حذف شد'));
            exit();
        }
    }
    
    // تغییر وضعیت فعال/غیرفعال
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
        require_csrf($_POST['csrf_token'] ?? '');
        
        $id = (int)($_POST['template_id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE notification_templates SET is_active = NOT is_active WHERE id = ?");
            $stmt->execute([$id]);
            header('Location: notification_templates.php?success=' . urlencode('وضعیت قالب تغییر کرد'));
            exit();
        }
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
}

// دریافت قالب‌ها
try {
    $stmt = $pdo->prepare("SELECT * FROM notification_templates ORDER BY type, name");
    $stmt->execute();
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // اگر جدول وجود ندارد، آن را ایجاد کن
    try {
        $create_table = "CREATE TABLE IF NOT EXISTS notification_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type ENUM('email', 'sms') NOT NULL,
            name VARCHAR(255) NOT NULL,
            subject VARCHAR(500) DEFAULT NULL,
            content TEXT NOT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci";
        
        $pdo->exec($create_table);
        
        // حالا دوباره تلاش کن
        $stmt = $pdo->prepare("SELECT * FROM notification_templates ORDER BY type, name");
        $stmt->execute();
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // اگر جدول خالی است، قالب‌های نمونه اضافه کن
        if (empty($templates)) {
            $sample_templates = [
                [
                    'type' => 'email',
                    'name' => 'خوش‌آمدگویی مشتری جدید',
                    'subject' => 'خوش‌آمدید به سیستم مدیریت دارایی‌های آلا نیرو',
                    'content' => 'سلام {full_name} عزیز،\n\nخوش‌آمدید به سیستم مدیریت دارایی‌های آلا نیرو.\n\nاطلاعات حساب شما:\nنام: {full_name}\nشرکت: {company}\nتلفن: {phone}\n\nبا تشکر\nتیم پشتیبانی آلا نیرو',
                    'is_active' => 1
                ],
                [
                    'type' => 'sms',
                    'name' => 'اطلاع‌رسانی تعمیرات',
                    'subject' => '',
                    'content' => 'سلام {full_name} عزیز،\nتعمیرات دستگاه شما در تاریخ {date} انجام خواهد شد.\n\nآلا نیرو',
                    'is_active' => 1
                ],
                [
                    'type' => 'email',
                    'name' => 'گزارش تعمیرات',
                    'subject' => 'گزارش تعمیرات دستگاه - {company}',
                    'content' => 'سلام {full_name} عزیز،\n\nتعمیرات دستگاه شما با موفقیت انجام شد.\n\nجزئیات:\n- تاریخ: {date}\n- اپراتور: {operator_name}\n- وضعیت: تکمیل شده\n\nبا تشکر\nتیم فنی آلا نیرو',
                    'is_active' => 1
                ]
            ];
            
            foreach ($sample_templates as $template) {
                $stmt = $pdo->prepare("INSERT INTO notification_templates (type, name, subject, content, is_active) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$template['type'], $template['name'], $template['subject'], $template['content'], $template['is_active']]);
            }
            
            // دوباره دریافت کن
            $stmt = $pdo->prepare("SELECT * FROM notification_templates ORDER BY type, name");
            $stmt->execute();
            $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e2) {
        $error = "خطا در ایجاد جدول: " . $e2->getMessage();
        $templates = [];
    }
}

$csrf = generate_csrf();
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>مدیریت قالب‌های اطلاع‌رسانی</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <style>
        body { background:#f6f8fb; font-family: 'Vazirmatn', Tahoma, sans-serif; }
        .center-card { max-width:1200px; margin:60px auto 30px; }
        .card-modern { border-radius:12px; box-shadow:0 14px 40px rgba(8,24,48,0.06); overflow:visible; }
        .template-card { border: 1px solid #e9ecef; border-radius: 8px; padding: 15px; margin-bottom: 15px; }
        .template-email { border-left: 4px solid #28a745; }
        .template-sms { border-left: 4px solid #17a2b8; }
        .required::after { content: " *"; color:#dc3545; }
        .fade-in { animation: fadeIn .28s ease; }
        @keyframes fadeIn { from{ opacity:0; transform: translateY(6px);} to{opacity:1; transform:none;} }
        .preview-box { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px; padding: 10px; margin-top: 10px; }
        .variable-tag { background: #e3f2fd; color: #1976d2; padding: 2px 6px; border-radius: 3px; font-size: 0.8em; margin: 0 2px; }
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
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="m-0"><i class="fas fa-envelope me-2"></i>مدیریت قالب‌های اطلاع‌رسانی</h4>
            <div>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addTemplateModal">
                    <i class="fas fa-plus me-1"></i>افزودن قالب جدید
                </button>
                <a href="customers.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-right me-1"></i>بازگشت به مشتریان
                </a>
            </div>
        </div>

        <div class="card-body">
            <?php if (!empty($success)): ?><div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?= h($success) ?></div><?php endif; ?>
            <?php if (!empty($error)): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?= h($error) ?></div><?php endif; ?>

            <!-- راهنمای متغیرها -->
            <div class="alert alert-info">
                <h6><i class="fas fa-info-circle me-2"></i>متغیرهای قابل استفاده در قالب‌ها:</h6>
                <div class="row">
                    <div class="col-md-6">
                        <strong>اطلاعات مشتری:</strong><br>
                        <span class="variable-tag">{full_name}</span> - نام و نام خانوادگی<br>
                        <span class="variable-tag">{company}</span> - نام شرکت<br>
                        <span class="variable-tag">{phone}</span> - شماره تلفن<br>
                        <span class="variable-tag">{email}</span> - ایمیل<br>
                        <span class="variable-tag">{address}</span> - آدرس
                    </div>
                    <div class="col-md-6">
                        <strong>اطلاعات اضافی:</strong><br>
                        <span class="variable-tag">{responsible_name}</span> - نام مسئول<br>
                        <span class="variable-tag">{operator_name}</span> - نام اپراتور<br>
                        <span class="variable-tag">{date}</span> - تاریخ امروز<br>
                        <span class="variable-tag">{time}</span> - زمان فعلی
                    </div>
                </div>
            </div>

            <!-- لیست قالب‌ها -->
            <div class="row">
                <?php foreach ($templates as $template): ?>
                    <div class="col-md-6 mb-3">
                        <div class="template-card template-<?= $template['type'] ?>">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h6 class="mb-1">
                                        <i class="fas fa-<?= $template['type'] === 'email' ? 'envelope' : 'sms' ?> me-1"></i>
                                        <?= h($template['name']) ?>
                                    </h6>
                                    <span class="badge bg-<?= $template['type'] === 'email' ? 'success' : 'info' ?>">
                                        <?= $template['type'] === 'email' ? 'ایمیل' : 'SMS' ?>
                                    </span>
                                    <?php if ($template['is_active']): ?>
                                        <span class="badge bg-success">فعال</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">غیرفعال</span>
                                    <?php endif; ?>
                                </div>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary" onclick="editTemplate(<?= htmlspecialchars(json_encode($template)) ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-outline-<?= $template['is_active'] ? 'warning' : 'success' ?>" 
                                            onclick="toggleStatus(<?= $template['id'] ?>)">
                                        <i class="fas fa-<?= $template['is_active'] ? 'pause' : 'play' ?>"></i>
                                    </button>
                                    <button class="btn btn-outline-danger" onclick="deleteTemplate(<?= $template['id'] ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <?php if ($template['subject']): ?>
                                <div class="mb-2">
                                    <strong>موضوع:</strong> <?= h($template['subject']) ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="preview-box">
                                <strong>متن قالب:</strong><br>
                                <?= nl2br(h($template['content'])) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (empty($templates)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-envelope fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">هیچ قالب اطلاع‌رسانی تعریف نشده است</h5>
                    <p class="text-muted">برای شروع، قالب جدیدی اضافه کنید</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal افزودن قالب -->
<div class="modal fade" id="addTemplateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">افزودن قالب جدید</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="add_template" value="1">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label required">نوع قالب</label>
                            <select name="type" class="form-select" required>
                                <option value="">انتخاب کنید</option>
                                <option value="email">ایمیل</option>
                                <option value="sms">SMS</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">نام قالب</label>
                            <input type="text" name="name" class="form-control" required placeholder="نام قالب">
                        </div>
                        <div class="col-12">
                            <label class="form-label">موضوع (فقط برای ایمیل)</label>
                            <input type="text" name="subject" class="form-control" placeholder="موضوع ایمیل">
                        </div>
                        <div class="col-12">
                            <label class="form-label required">متن قالب</label>
                            <textarea name="content" class="form-control" rows="6" required placeholder="متن قالب..."></textarea>
                            <div class="form-text">می‌توانید از متغیرهای بالا استفاده کنید</div>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
                                <label class="form-check-label" for="is_active">
                                    قالب فعال است
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" class="btn btn-success">ذخیره قالب</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal ویرایش قالب -->
<div class="modal fade" id="editTemplateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">ویرایش قالب</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="edit_template" value="1">
                    <input type="hidden" name="template_id" id="edit_template_id">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label required">نوع قالب</label>
                            <select name="type" id="edit_type" class="form-select" required>
                                <option value="">انتخاب کنید</option>
                                <option value="email">ایمیل</option>
                                <option value="sms">SMS</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">نام قالب</label>
                            <input type="text" name="name" id="edit_name" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">موضوع (فقط برای ایمیل)</label>
                            <input type="text" name="subject" id="edit_subject" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label required">متن قالب</label>
                            <textarea name="content" id="edit_content" class="form-control" rows="6" required></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active">
                                <label class="form-check-label" for="edit_is_active">
                                    قالب فعال است
                                </label>
                            </div>
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
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function editTemplate(template) {
    document.getElementById('edit_template_id').value = template.id;
    document.getElementById('edit_type').value = template.type;
    document.getElementById('edit_name').value = template.name;
    document.getElementById('edit_subject').value = template.subject || '';
    document.getElementById('edit_content').value = template.content;
    document.getElementById('edit_is_active').checked = template.is_active == 1;
    
    new bootstrap.Modal(document.getElementById('editTemplateModal')).show();
}

function toggleStatus(id) {
    if (confirm('آیا از تغییر وضعیت این قالب اطمینان دارید؟')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="csrf_token" value="<?= h($csrf) ?>"><input type="hidden" name="toggle_status" value="1"><input type="hidden" name="template_id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteTemplate(id) {
    if (confirm('آیا از حذف این قالب اطمینان دارید؟ این عمل قابل بازگشت نیست.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="csrf_token" value="<?= h($csrf) ?>"><input type="hidden" name="delete_template" value="1"><input type="hidden" name="template_id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
</body>
</html>