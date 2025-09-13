<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// بررسی دسترسی ادمین
$is_admin = ($_SESSION['role'] === 'ادمین' || $_SESSION['role'] === 'admin' || $_SESSION['role'] === 'administrator');
if (!$is_admin) {
    die('دسترسی غیرمجاز - فقط ادمین می‌تواند قالب‌های اطلاع‌رسانی را مدیریت کند');
}

// توابع کمکی
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function sanitize($s){ return trim(strip_tags($s ?? '')); }
function generate_csrf(){ if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(24)); return $_SESSION['csrf_token']; }
function require_csrf($t){ if (empty($t) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $t)) throw new RuntimeException('خطای CSRF — درخواست نامعتبر'); }

$success = '';
$error = '';

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
            throw new RuntimeException('نوع، نام و محتوا الزامی هستند');
        }
        
        if (!in_array($type, ['email', 'sms'])) {
            throw new RuntimeException('نوع قالب نامعتبر است');
        }
        
        $stmt = $pdo->prepare("INSERT INTO notification_templates (type, name, subject, content, is_active) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$type, $name, $subject, $content, $is_active]);
        
        $success = 'قالب با موفقیت اضافه شد';
    }
    
    // ویرایش قالب
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_template'])) {
        require_csrf($_POST['csrf_token'] ?? '');
        
        $id = (int)$_POST['template_id'];
        $type = sanitize($_POST['type'] ?? '');
        $name = sanitize($_POST['name'] ?? '');
        $subject = sanitize($_POST['subject'] ?? '');
        $content = sanitize($_POST['content'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if ($id <= 0) {
            throw new RuntimeException('شناسه قالب نامعتبر است');
        }
        
        if (empty($type) || empty($name) || empty($content)) {
            throw new RuntimeException('نوع، نام و محتوا الزامی هستند');
        }
        
        if (!in_array($type, ['email', 'sms'])) {
            throw new RuntimeException('نوع قالب نامعتبر است');
        }
        
        $stmt = $pdo->prepare("UPDATE notification_templates SET type = ?, name = ?, subject = ?, content = ?, is_active = ? WHERE id = ?");
        $stmt->execute([$type, $name, $subject, $content, $is_active, $id]);
        
        $success = 'قالب با موفقیت به‌روزرسانی شد';
    }
    
    // حذف قالب
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_template'])) {
        require_csrf($_POST['csrf_token'] ?? '');
        
        $id = (int)$_POST['template_id'];
        if ($id <= 0) {
            throw new RuntimeException('شناسه قالب نامعتبر است');
        }
        
        $stmt = $pdo->prepare("DELETE FROM notification_templates WHERE id = ?");
        $stmt->execute([$id]);
        
        $success = 'قالب با موفقیت حذف شد';
    }
    
    // تغییر وضعیت فعال/غیرفعال
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
        require_csrf($_POST['csrf_token'] ?? '');
        
        $id = (int)$_POST['template_id'];
        if ($id <= 0) {
            throw new RuntimeException('شناسه قالب نامعتبر است');
        }
        
        $stmt = $pdo->prepare("UPDATE notification_templates SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$id]);
        
        $success = 'وضعیت قالب تغییر یافت';
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
}

// دریافت قالب‌ها
$templates = $pdo->query("SELECT * FROM notification_templates ORDER BY type, name")->fetchAll(PDO::FETCH_ASSOC);

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
        .template-inactive { opacity: 0.6; background: #f8f9fa; }
        .variable-tag { background: #e9ecef; color: #495057; padding: 2px 6px; border-radius: 4px; font-size: 12px; margin: 2px; display: inline-block; }
        .required::after { content: " *"; color:#dc3545; }
    </style>
</head>
<body>
<?php if (file_exists('navbar.php')) include 'navbar.php'; ?>

<div class="container center-card">
    <div class="card card-modern">
        <div class="card-header bg-primary text-white">
            <h4 class="m-0"><i class="fas fa-cog me-2"></i>مدیریت قالب‌های اطلاع‌رسانی</h4>
        </div>
        
        <div class="card-body">
            <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?= h($success) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?= h($error) ?></div><?php endif; ?>
            
            <!-- دکمه افزودن قالب جدید -->
            <div class="mb-4">
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addTemplateModal">
                    <i class="fas fa-plus me-1"></i> افزودن قالب جدید
                </button>
            </div>
            
            <!-- لیست قالب‌ها -->
            <div class="row">
                <?php foreach ($templates as $template): ?>
                    <div class="col-md-6 mb-3">
                        <div class="template-card template-<?= $template['type'] ?> <?= !$template['is_active'] ? 'template-inactive' : '' ?>">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="mb-0">
                                    <i class="fas fa-<?= $template['type'] === 'email' ? 'envelope' : 'sms' ?> me-1"></i>
                                    <?= h($template['name']) ?>
                                </h6>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-warning" onclick="editTemplate(<?= htmlspecialchars(json_encode($template)) ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('آیا مطمئن هستید؟');">
                                        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                        <input type="hidden" name="delete_template" value="1">
                                        <input type="hidden" name="template_id" value="<?= $template['id'] ?>">
                                        <button class="btn btn-outline-danger" type="submit">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            
                            <div class="mb-2">
                                <span class="badge bg-<?= $template['type'] === 'email' ? 'success' : 'info' ?>">
                                    <?= $template['type'] === 'email' ? 'ایمیل' : 'SMS' ?>
                                </span>
                                <span class="badge bg-<?= $template['is_active'] ? 'success' : 'secondary' ?>">
                                    <?= $template['is_active'] ? 'فعال' : 'غیرفعال' ?>
                                </span>
                            </div>
                            
                            <?php if ($template['subject']): ?>
                                <div class="mb-2">
                                    <strong>موضوع:</strong> <?= h($template['subject']) ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mb-2">
                                <strong>محتوا:</strong>
                                <div class="bg-light p-2 rounded mt-1" style="max-height: 100px; overflow-y: auto;">
                                    <?= nl2br(h($template['content'])) ?>
                                </div>
                            </div>
                            
                            <div class="text-end">
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                    <input type="hidden" name="toggle_status" value="1">
                                    <input type="hidden" name="template_id" value="<?= $template['id'] ?>">
                                    <button class="btn btn-sm btn-<?= $template['is_active'] ? 'warning' : 'success' ?>" type="submit">
                                        <i class="fas fa-<?= $template['is_active'] ? 'pause' : 'play' ?> me-1"></i>
                                        <?= $template['is_active'] ? 'غیرفعال' : 'فعال' ?>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- راهنمای متغیرها -->
            <div class="mt-4">
                <h6 class="text-primary">متغیرهای قابل استفاده در قالب‌ها:</h6>
                <div class="bg-light p-3 rounded">
                    <span class="variable-tag">{full_name}</span>
                    <span class="variable-tag">{company}</span>
                    <span class="variable-tag">{phone}</span>
                    <span class="variable-tag">{company_phone}</span>
                    <span class="variable-tag">{responsible_name}</span>
                    <span class="variable-tag">{responsible_phone}</span>
                    <span class="variable-tag">{email}</span>
                    <span class="variable-tag">{company_email}</span>
                    <span class="variable-tag">{address}</span>
                    <span class="variable-tag">{operator_name}</span>
                    <span class="variable-tag">{operator_phone}</span>
                    <span class="variable-tag">{notes}</span>
                    <span class="variable-tag">{date}</span>
                    <span class="variable-tag">{time}</span>
                </div>
            </div>
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
                            <label class="form-label required">محتوا</label>
                            <textarea name="content" class="form-control" rows="6" required placeholder="محتوای قالب..."></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
                                <label class="form-check-label" for="is_active">فعال</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" class="btn btn-primary">ذخیره</button>
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
                            <label class="form-label required">محتوا</label>
                            <textarea name="content" id="edit_content" class="form-control" rows="6" required></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active">
                                <label class="form-check-label" for="edit_is_active">فعال</label>
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
</script>
</body>
</html>