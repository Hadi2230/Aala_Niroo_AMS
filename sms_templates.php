<?php
session_start();
require_once 'config.php';

// بررسی احراز هویت
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$message = '';
$message_type = '';

// پردازش عملیات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['create_template'])) {
            $stmt = $pdo->prepare("
                INSERT INTO sms_templates (template_name, template_type, template_text, variables, created_by) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                sanitizeInput($_POST['template_name']),
                sanitizeInput($_POST['template_type']),
                sanitizeInput($_POST['template_text']),
                json_encode($_POST['variables'] ?? []),
                $_SESSION['user_id']
            ]);
            
            $message = 'قالب SMS با موفقیت ایجاد شد';
            $message_type = 'success';
        }
        
        if (isset($_POST['update_template'])) {
            $stmt = $pdo->prepare("
                UPDATE sms_templates SET 
                    template_name = ?, template_type = ?, template_text = ?, 
                    variables = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                sanitizeInput($_POST['template_name']),
                sanitizeInput($_POST['template_type']),
                sanitizeInput($_POST['template_text']),
                json_encode($_POST['variables'] ?? []),
                (int)$_POST['template_id']
            ]);
            
            $message = 'قالب SMS با موفقیت به‌روزرسانی شد';
            $message_type = 'success';
        }
        
        if (isset($_POST['delete_template'])) {
            $stmt = $pdo->prepare("DELETE FROM sms_templates WHERE id = ?");
            $stmt->execute([(int)$_POST['template_id']]);
            
            $message = 'قالب SMS با موفقیت حذف شد';
            $message_type = 'success';
        }
        
    } catch (Exception $e) {
        $message = 'خطا در پردازش درخواست: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// دریافت قالب‌ها
$templates = [];
try {
    $stmt = $pdo->query("
        SELECT st.*, u.full_name as created_by_name
        FROM sms_templates st
        LEFT JOIN users u ON st.created_by = u.id
        ORDER BY st.created_at DESC
    ");
    $templates = $stmt->fetchAll();
} catch (Exception $e) {
    // جدول وجود ندارد
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت قالب‌های SMS - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --info-color: #17a2b8;
            --light-bg: #f8f9fa;
        }
        
        body {
            font-family: Vazirmatn, sans-serif;
            background: var(--light-bg);
            padding-top: 80px;
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 25px rgba(0,0,0,.1);
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,.08);
            margin-bottom: 20px;
        }
        
        .card-header {
            background: var(--primary-color);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 20px;
            border: none;
        }
        
        .btn-primary {
            background: var(--secondary-color);
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
        }
        
        .btn-primary:hover {
            background: #2980b9;
        }
        
        .template-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 3px 10px rgba(0,0,0,.1);
            transition: all .3s ease;
        }
        
        .template-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0,0,0,.15);
        }
        
        .template-type {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .type-visit_confirmation { background: #e3f2fd; color: #1976d2; }
        .type-visit_reminder { background: #fff3e0; color: #f57c00; }
        .type-visit_cancellation { background: #ffebee; color: #c62828; }
        .type-visit_completion { background: #e8f5e8; color: #2e7d32; }
        
        .variable-tag {
            background: #e9ecef;
            color: #495057;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            margin: 2px;
            display: inline-block;
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #ddd;
            padding: 10px 15px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .modal-content {
            border-radius: 15px;
            border: none;
        }
        
        .modal-header {
            background: var(--primary-color);
            color: white;
            border-radius: 15px 15px 0 0;
        }
        
        .modal-header .btn-close {
            filter: invert(1);
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 20px; }
        }
    </style>
</head>
<body>
    <?php if (file_exists('navbar.php')) include 'navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="container">
            <!-- هدر صفحه -->
            <div class="page-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1><i class="bi bi-chat-text"></i> مدیریت قالب‌های SMS</h1>
                        <p class="mb-0">ایجاد و مدیریت قالب‌های پیامک برای بازدیدهای کارخانه</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#createTemplateModal">
                            <i class="bi bi-plus-circle"></i> قالب جدید
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- پیام‌ها -->
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
                    <i class="bi bi-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- لیست قالب‌ها -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-list-ul"></i> لیست قالب‌های SMS</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($templates)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-chat-text display-1 text-muted"></i>
                            <h5 class="text-muted mt-3">هیچ قالب SMS یافت نشد</h5>
                            <p class="text-muted">برای شروع، یک قالب جدید ایجاد کنید</p>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($templates as $template): ?>
                                <div class="col-md-6">
                                    <div class="template-card">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <h6 class="mb-0"><?php echo htmlspecialchars($template['template_name']); ?></h6>
                                            <span class="template-type type-<?php echo $template['template_type']; ?>">
                                                <?php 
                                                $type_labels = [
                                                    'visit_confirmation' => 'تایید بازدید',
                                                    'visit_reminder' => 'یادآوری بازدید',
                                                    'visit_cancellation' => 'لغو بازدید',
                                                    'visit_completion' => 'تکمیل بازدید'
                                                ];
                                                echo $type_labels[$template['template_type']] ?? $template['template_type'];
                                                ?>
                                            </span>
                                        </div>
                                        
                                        <p class="text-muted mb-3"><?php echo htmlspecialchars($template['template_text']); ?></p>
                                        
                                        <?php if (!empty($template['variables'])): ?>
                                            <div class="mb-3">
                                                <small class="text-muted">متغیرهای موجود:</small><br>
                                                <?php 
                                                $variables = json_decode($template['variables'], true);
                                                foreach ($variables as $variable): 
                                                ?>
                                                    <span class="variable-tag">{<?php echo $variable; ?>}</span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                ایجاد شده توسط: <?php echo htmlspecialchars($template['created_by_name'] ?? 'نامشخص'); ?>
                                                <br>
                                                <?php echo date('Y-m-d H:i', strtotime($template['created_at'])); ?>
                                            </small>
                                            
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" onclick="editTemplate(<?php echo $template['id']; ?>, '<?php echo addslashes($template['template_name']); ?>', '<?php echo $template['template_type']; ?>', '<?php echo addslashes($template['template_text']); ?>', '<?php echo addslashes($template['variables']); ?>')">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteTemplate(<?php echo $template['id']; ?>)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- مودال ایجاد قالب جدید -->
    <div class="modal fade" id="createTemplateModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> ایجاد قالب SMS جدید</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="create_template" value="1">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">نام قالب *</label>
                                <input type="text" name="template_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">نوع قالب *</label>
                                <select name="template_type" class="form-select" required>
                                    <option value="">انتخاب کنید</option>
                                    <option value="visit_confirmation">تایید بازدید</option>
                                    <option value="visit_reminder">یادآوری بازدید</option>
                                    <option value="visit_cancellation">لغو بازدید</option>
                                    <option value="visit_completion">تکمیل بازدید</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">متن قالب *</label>
                                <textarea name="template_text" class="form-control" rows="6" required placeholder="متن قالب SMS را اینجا بنویسید..."></textarea>
                                <small class="text-muted">می‌توانید از متغیرهای {visitor_name}, {visit_type}, {visit_date}, {request_number} استفاده کنید</small>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">متغیرهای مورد استفاده</label>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input type="checkbox" name="variables[]" value="visitor_name" class="form-check-input" id="var_visitor_name">
                                            <label class="form-check-label" for="var_visitor_name">{visitor_name}</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input type="checkbox" name="variables[]" value="visit_type" class="form-check-input" id="var_visit_type">
                                            <label class="form-check-label" for="var_visit_type">{visit_type}</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input type="checkbox" name="variables[]" value="visit_date" class="form-check-input" id="var_visit_date">
                                            <label class="form-check-label" for="var_visit_date">{visit_date}</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input type="checkbox" name="variables[]" value="request_number" class="form-check-input" id="var_request_number">
                                            <label class="form-check-label" for="var_request_number">{request_number}</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" class="btn btn-primary">ایجاد قالب</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- مودال ویرایش قالب -->
    <div class="modal fade" id="editTemplateModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> ویرایش قالب SMS</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="update_template" value="1">
                        <input type="hidden" name="template_id" id="edit_template_id">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">نام قالب *</label>
                                <input type="text" name="template_name" id="edit_template_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">نوع قالب *</label>
                                <select name="template_type" id="edit_template_type" class="form-select" required>
                                    <option value="visit_confirmation">تایید بازدید</option>
                                    <option value="visit_reminder">یادآوری بازدید</option>
                                    <option value="visit_cancellation">لغو بازدید</option>
                                    <option value="visit_completion">تکمیل بازدید</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">متن قالب *</label>
                                <textarea name="template_text" id="edit_template_text" class="form-control" rows="6" required></textarea>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">متغیرهای مورد استفاده</label>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input type="checkbox" name="variables[]" value="visitor_name" class="form-check-input" id="edit_var_visitor_name">
                                            <label class="form-check-label" for="edit_var_visitor_name">{visitor_name}</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input type="checkbox" name="variables[]" value="visit_type" class="form-check-input" id="edit_var_visit_type">
                                            <label class="form-check-label" for="edit_var_visit_type">{visit_type}</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input type="checkbox" name="variables[]" value="visit_date" class="form-check-input" id="edit_var_visit_date">
                                            <label class="form-check-label" for="edit_var_visit_date">{visit_date}</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input type="checkbox" name="variables[]" value="request_number" class="form-check-input" id="edit_var_request_number">
                                            <label class="form-check-label" for="edit_var_request_number">{request_number}</label>
                                        </div>
                                    </div>
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
        function editTemplate(id, name, type, text, variables) {
            document.getElementById('edit_template_id').value = id;
            document.getElementById('edit_template_name').value = name;
            document.getElementById('edit_template_type').value = type;
            document.getElementById('edit_template_text').value = text;
            
            // پاک کردن چک‌باکس‌ها
            document.querySelectorAll('#editTemplateModal input[type="checkbox"]').forEach(cb => cb.checked = false);
            
            // تیک زدن متغیرهای موجود
            if (variables) {
                const vars = JSON.parse(variables);
                vars.forEach(variable => {
                    const checkbox = document.getElementById('edit_var_' + variable);
                    if (checkbox) checkbox.checked = true;
                });
            }
            
            new bootstrap.Modal(document.getElementById('editTemplateModal')).show();
        }
        
        function deleteTemplate(id) {
            if (confirm('آیا مطمئن هستید که می‌خواهید این قالب را حذف کنید؟')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="delete_template" value="1">
                    <input type="hidden" name="template_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>