<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$visit_id = (int)($_GET['id'] ?? 0);
if (!$visit_id) {
    header("Location: visit_management.php");
    exit();
}

// دریافت اطلاعات بازدید
$visit = null;
try {
    $stmt = $pdo->prepare("
        SELECT vr.*, 
               u1.full_name as created_by_name,
               u2.full_name as assigned_to_name,
               u3.full_name as host_name,
               u4.full_name as security_officer_name
        FROM visit_requests vr
        LEFT JOIN users u1 ON vr.created_by = u1.id
        LEFT JOIN users u2 ON vr.assigned_to = u2.id
        LEFT JOIN users u3 ON vr.host_id = u3.id
        LEFT JOIN users u4 ON vr.security_officer_id = u4.id
        WHERE vr.id = ?
    ");
    $stmt->execute([$visit_id]);
    $visit = $stmt->fetch();
} catch (Exception $e) {
    // جدول وجود ندارد
    $visit = null;
}

if (!$visit) {
    header("Location: visit_management.php");
    exit();
}

// دریافت مدارک
$stmt = $pdo->prepare("SELECT * FROM visit_documents WHERE visit_request_id = ? ORDER BY created_at DESC");
$stmt->execute([$visit_id]);
$documents = $stmt->fetchAll();

// دریافت عکس‌ها
$stmt = $pdo->prepare("SELECT * FROM visit_photos WHERE visit_request_id = ? ORDER BY taken_at DESC");
$stmt->execute([$visit_id]);
$photos = $stmt->fetchAll();

// دریافت چک‌لیست‌ها
$stmt = $pdo->prepare("SELECT * FROM visit_checklists WHERE visit_request_id = ? ORDER BY checklist_type, created_at");
$stmt->execute([$visit_id]);
$checklists = $stmt->fetchAll();

// دریافت گزارش‌ها
$stmt = $pdo->prepare("SELECT * FROM visit_reports WHERE visit_request_id = ? ORDER BY created_at DESC");
$stmt->execute([$visit_id]);
$reports = $stmt->fetchAll();

// دریافت تاریخچه
$stmt = $pdo->prepare("
    SELECT vh.*, u.full_name as performed_by_name
    FROM visit_history vh
    LEFT JOIN users u ON vh.performed_by = u.id
    WHERE vh.visit_request_id = ? 
    ORDER BY vh.created_at DESC
");
$stmt->execute([$visit_id]);
$history = $stmt->fetchAll();

// دریافت دستگاه‌های رزرو شده
$stmt = $pdo->prepare("
    SELECT dr.*, a.name as asset_name, at.display_name as type_name
    FROM device_reservations dr
    LEFT JOIN assets a ON dr.asset_id = a.id
    LEFT JOIN asset_types at ON a.type_id = at.id
    WHERE dr.visit_request_id = ?
    ORDER BY dr.reserved_from
");
$stmt->execute([$visit_id]);
$reservations = $stmt->fetchAll();

// پردازش عملیات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['update_status'])) {
            $new_status = sanitizeInput($_POST['new_status']);
            $notes = sanitizeInput($_POST['status_notes']);
            updateVisitStatus($pdo, $visit_id, $new_status, $notes);
            $_SESSION['success_message'] = "وضعیت به‌روزرسانی شد";
            header("Location: visit_details.php?id=$visit_id");
            exit();
        }
        
        if (isset($_POST['upload_document'])) {
            $file = $_FILES['document_file'];
            $document_type = sanitizeInput($_POST['document_type']);
            $document_name = sanitizeInput($_POST['document_name']);
            
            uploadVisitDocument($pdo, $visit_id, $file, $document_type, $document_name);
            $_SESSION['success_message'] = "مدرک آپلود شد";
            header("Location: visit_details.php?id=$visit_id");
            exit();
        }
        
        if (isset($_POST['upload_photo'])) {
            $file = $_FILES['photo_file'];
            $photo_type = sanitizeInput($_POST['photo_type']);
            $caption = sanitizeInput($_POST['photo_caption']);
            
            uploadVisitPhoto($pdo, $visit_id, $file, $photo_type, $caption);
            $_SESSION['success_message'] = "عکس آپلود شد";
            header("Location: visit_details.php?id=$visit_id");
            exit();
        }
        
        if (isset($_POST['complete_checklist'])) {
            $checklist_id = (int)$_POST['checklist_id'];
            $notes = sanitizeInput($_POST['checklist_notes']);
            completeChecklistItem($pdo, $checklist_id, $notes);
            $_SESSION['success_message'] = "آیتم چک‌لیست تکمیل شد";
            header("Location: visit_details.php?id=$visit_id");
            exit();
        }
        
        if (isset($_POST['create_report'])) {
            $report_data = [
                'title' => sanitizeInput($_POST['report_title']),
                'content' => sanitizeInput($_POST['report_content']),
                'equipment_tested' => $_POST['equipment_tested'] ?? [],
                'visitor_feedback' => sanitizeInput($_POST['visitor_feedback']),
                'recommendations' => sanitizeInput($_POST['recommendations']),
                'follow_up_required' => isset($_POST['follow_up_required']),
                'follow_up_date' => $_POST['follow_up_date'] ?: null
            ];
            
            createVisitReport($pdo, $visit_id, $_POST['report_type'], $report_data);
            $_SESSION['success_message'] = "گزارش ایجاد شد";
            header("Location: visit_details.php?id=$visit_id");
            exit();
        }
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "خطا: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>جزئیات بازدید - <?php echo htmlspecialchars($visit['request_number']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <style>
        body { font-family: Vazirmatn, sans-serif; background: #f8f9fa; padding-top: 80px; }
        .visit-details { max-width: 1200px; margin: 0 auto; }
        .visit-header { background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%); color: white; padding: 25px; border-radius: 15px 15px 0 0; }
        .visit-body { background: white; padding: 30px; border-radius: 0 0 15px 15px; box-shadow: 0 5px 25px rgba(0,0,0,.1); }
        .info-card { background: #f8f9fa; border-radius: 10px; padding: 20px; margin-bottom: 20px; }
        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: bold; }
        .status-new { background: #e3f2fd; color: #1976d2; }
        .status-documents_required { background: #fff3e0; color: #f57c00; }
        .status-reviewed { background: #f3e5f5; color: #7b1fa2; }
        .status-scheduled { background: #e8f5e8; color: #388e3c; }
        .status-reserved { background: #e1f5fe; color: #0288d1; }
        .status-ready_for_visit { background: #f1f8e9; color: #689f38; }
        .status-checked_in { background: #e0f2f1; color: #00796b; }
        .status-onsite { background: #fff8e1; color: #f9a825; }
        .status-completed { background: #e8f5e8; color: #2e7d32; }
        .status-cancelled { background: #ffebee; color: #c62828; }
        .timeline { position: relative; padding: 20px 0; }
        .timeline-item { position: relative; padding: 15px 0 15px 30px; border-left: 2px solid #e0e0e0; }
        .timeline-item::before { content: ''; position: absolute; left: -6px; top: 20px; width: 10px; height: 10px; border-radius: 50%; background: #3498db; }
        .timeline-item:last-child { border-left: none; }
        .document-item { background: #f8f9fa; border-radius: 8px; padding: 15px; margin-bottom: 10px; }
        .photo-gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; }
        .photo-item { position: relative; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.1); }
        .photo-item img { width: 100%; height: 150px; object-fit: cover; }
        .checklist-item { background: #f8f9fa; border-radius: 8px; padding: 15px; margin-bottom: 10px; }
        .checklist-completed { background: #e8f5e8; border-left: 4px solid #27ae60; }
    </style>
</head>
<body>
    <?php if (file_exists('navbar.php')) include 'navbar.php'; ?>
    
    <div class="container">
        <div class="visit-details">
            <div class="visit-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2><i class="bi bi-building"></i> <?php echo htmlspecialchars($visit['request_number']); ?></h2>
                        <h4><?php echo htmlspecialchars($visit['company_name']); ?></h4>
                        <p class="mb-0"><?php echo htmlspecialchars($visit['contact_person']); ?> - <?php echo htmlspecialchars($visit['contact_phone']); ?></p>
                    </div>
                    <div class="col-md-4 text-end">
                        <span class="status-badge status-<?php echo $visit['status']; ?>">
                            <?php 
                            $status_labels = [
                                'new' => 'جدید', 'documents_required' => 'نیاز به مدارک', 'reviewed' => 'بررسی شده',
                                'scheduled' => 'برنامه‌ریزی شده', 'reserved' => 'رزرو شده', 'ready_for_visit' => 'آماده بازدید',
                                'checked_in' => 'وارد شده', 'onsite' => 'در حال بازدید', 'completed' => 'تکمیل شده',
                                'cancelled' => 'لغو شده', 'archived' => 'آرشیو شده'
                            ];
                            echo $status_labels[$visit['status']] ?? $visit['status'];
                            ?>
                        </span>
                        <br>
                        <small class="text-light">اولویت: <?php echo $visit['priority']; ?></small>
                    </div>
                </div>
            </div>
            
            <div class="visit-body">
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="bi bi-check-circle-fill"></i>
                        <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="bi bi-exclamation-circle-fill"></i>
                        <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- تب‌ها -->
                <ul class="nav nav-tabs mb-4" id="visitTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button">
                            <i class="bi bi-info-circle"></i> اطلاعات کلی
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents" type="button">
                            <i class="bi bi-file-earmark"></i> مدارک
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="photos-tab" data-bs-toggle="tab" data-bs-target="#photos" type="button">
                            <i class="bi bi-camera"></i> عکس‌ها
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="checklist-tab" data-bs-toggle="tab" data-bs-target="#checklist" type="button">
                            <i class="bi bi-list-check"></i> چک‌لیست
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="reports-tab" data-bs-toggle="tab" data-bs-target="#reports" type="button">
                            <i class="bi bi-file-text"></i> گزارش‌ها
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button">
                            <i class="bi bi-clock-history"></i> تاریخچه
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="visitTabsContent">
                    <!-- اطلاعات کلی -->
                    <div class="tab-pane fade show active" id="info">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-card">
                                    <h5><i class="bi bi-building"></i> اطلاعات شرکت</h5>
                                    <p><strong>نام شرکت:</strong> <?php echo htmlspecialchars($visit['company_name']); ?></p>
                                    <p><strong>شخص تماس:</strong> <?php echo htmlspecialchars($visit['contact_person']); ?></p>
                                    <p><strong>تلفن:</strong> <?php echo htmlspecialchars($visit['contact_phone']); ?></p>
                                    <p><strong>ایمیل:</strong> <?php echo htmlspecialchars($visit['contact_email'] ?: '-'); ?></p>
                                    <p><strong>تعداد بازدیدکننده:</strong> <?php echo $visit['visitor_count']; ?> نفر</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-card">
                                    <h5><i class="bi bi-calendar"></i> جزئیات بازدید</h5>
                                    <p><strong>نوع بازدید:</strong> <?php echo $visit['visit_type']; ?></p>
                                    <p><strong>هدف بازدید:</strong> <?php echo $visit['visit_purpose']; ?></p>
                                    <p><strong>روش درخواست:</strong> <?php echo $visit['request_method']; ?></p>
                                    <p><strong>مدت بازدید:</strong> <?php echo $visit['visit_duration']; ?> دقیقه</p>
                                    <p><strong>نیاز به NDA:</strong> <?php echo $visit['requires_nda'] ? 'بله' : 'خیر'; ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($visit['special_requirements']): ?>
                            <div class="info-card">
                                <h5><i class="bi bi-exclamation-triangle"></i> نیازهای خاص</h5>
                                <p><?php echo nl2br(htmlspecialchars($visit['special_requirements'])); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($reservations)): ?>
                            <div class="info-card">
                                <h5><i class="bi bi-gear"></i> دستگاه‌های رزرو شده</h5>
                                <?php foreach ($reservations as $reservation): ?>
                                    <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                                        <div>
                                            <strong><?php echo htmlspecialchars($reservation['asset_name']); ?></strong>
                                            <small class="text-muted d-block"><?php echo htmlspecialchars($reservation['type_name']); ?></small>
                                        </div>
                                        <div class="text-end">
                                            <small class="text-muted">
                                                <?php echo jalali_format($reservation['reserved_from']); ?><br>
                                                تا <?php echo jalali_format($reservation['reserved_to']); ?>
                                            </small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- تغییر وضعیت -->
                        <div class="info-card">
                            <h5><i class="bi bi-arrow-repeat"></i> تغییر وضعیت</h5>
                            <form method="POST" class="row g-3">
                                <input type="hidden" name="update_status" value="1">
                                <?php echo csrf_field(); ?>
                                <div class="col-md-4">
                                    <select name="new_status" class="form-select" required>
                                        <option value="new" <?php echo $visit['status'] === 'new' ? 'selected' : ''; ?>>جدید</option>
                                        <option value="documents_required" <?php echo $visit['status'] === 'documents_required' ? 'selected' : ''; ?>>نیاز به مدارک</option>
                                        <option value="reviewed" <?php echo $visit['status'] === 'reviewed' ? 'selected' : ''; ?>>بررسی شده</option>
                                        <option value="scheduled" <?php echo $visit['status'] === 'scheduled' ? 'selected' : ''; ?>>برنامه‌ریزی شده</option>
                                        <option value="reserved" <?php echo $visit['status'] === 'reserved' ? 'selected' : ''; ?>>رزرو شده</option>
                                        <option value="ready_for_visit" <?php echo $visit['status'] === 'ready_for_visit' ? 'selected' : ''; ?>>آماده بازدید</option>
                                        <option value="checked_in" <?php echo $visit['status'] === 'checked_in' ? 'selected' : ''; ?>>وارد شده</option>
                                        <option value="onsite" <?php echo $visit['status'] === 'onsite' ? 'selected' : ''; ?>>در حال بازدید</option>
                                        <option value="completed" <?php echo $visit['status'] === 'completed' ? 'selected' : ''; ?>>تکمیل شده</option>
                                        <option value="cancelled" <?php echo $visit['status'] === 'cancelled' ? 'selected' : ''; ?>>لغو شده</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <input type="text" name="status_notes" class="form-control" placeholder="یادداشت (اختیاری)">
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary w-100">تغییر وضعیت</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- مدارک -->
                    <div class="tab-pane fade" id="documents">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5><i class="bi bi-file-earmark"></i> مدارک</h5>
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#uploadDocumentModal">
                                <i class="bi bi-plus"></i> افزودن مدرک
                            </button>
                        </div>
                        
                        <?php if (empty($documents)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-file-earmark display-1 text-muted"></i>
                                <p class="text-muted mt-3">هیچ مدرکی آپلود نشده است</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($documents as $doc): ?>
                                <div class="document-item">
                                    <div class="row align-items-center">
                                        <div class="col-md-6">
                                            <h6><?php echo htmlspecialchars($doc['document_name']); ?></h6>
                                            <small class="text-muted">
                                                نوع: <?php echo $doc['document_type']; ?> | 
                                                اندازه: <?php echo formatFileSize($doc['file_size']); ?> | 
                                                تاریخ: <?php echo jalali_format($doc['created_at']); ?>
                                            </small>
                                        </div>
                                        <div class="col-md-3">
                                            <?php if ($doc['is_verified']): ?>
                                                <span class="badge bg-success">تایید شده</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">در انتظار تایید</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-3 text-end">
                                            <a href="<?php echo $doc['file_path']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-download"></i> دانلود
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- عکس‌ها -->
                    <div class="tab-pane fade" id="photos">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5><i class="bi bi-camera"></i> عکس‌ها</h5>
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#uploadPhotoModal">
                                <i class="bi bi-plus"></i> افزودن عکس
                            </button>
                        </div>
                        
                        <?php if (empty($photos)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-camera display-1 text-muted"></i>
                                <p class="text-muted mt-3">هیچ عکسی آپلود نشده است</p>
                            </div>
                        <?php else: ?>
                            <div class="photo-gallery">
                                <?php foreach ($photos as $photo): ?>
                                    <div class="photo-item">
                                        <img src="<?php echo $photo['file_path']; ?>" alt="<?php echo htmlspecialchars($photo['caption']); ?>">
                                        <div class="p-2">
                                            <small class="text-muted"><?php echo htmlspecialchars($photo['caption'] ?: 'بدون توضیح'); ?></small>
                                            <br>
                                            <small class="text-muted"><?php echo jalali_format($photo['taken_at']); ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- چک‌لیست -->
                    <div class="tab-pane fade" id="checklist">
                        <h5><i class="bi bi-list-check"></i> چک‌لیست بازدید</h5>
                        
                        <?php 
                        $checklist_types = ['pre_visit' => 'قبل از بازدید', 'onsite' => 'حین بازدید', 'post_visit' => 'بعد از بازدید'];
                        foreach ($checklist_types as $type => $label):
                            $type_items = array_filter($checklists, function($item) use ($type) { return $item['checklist_type'] === $type; });
                        ?>
                            <?php if (!empty($type_items)): ?>
                                <div class="mb-4">
                                    <h6><?php echo $label; ?></h6>
                                    <?php foreach ($type_items as $item): ?>
                                        <div class="checklist-item <?php echo $item['is_completed'] ? 'checklist-completed' : ''; ?>">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" <?php echo $item['is_completed'] ? 'checked' : ''; ?> disabled>
                                                <label class="form-check-label">
                                                    <?php echo htmlspecialchars($item['item_name']); ?>
                                                </label>
                                            </div>
                                            <?php if ($item['is_completed']): ?>
                                                <small class="text-muted">
                                                    تکمیل شده توسط: <?php echo $item['completed_by']; ?> | 
                                                    تاریخ: <?php echo jalali_format($item['completed_at']); ?>
                                                </small>
                                                <?php if ($item['notes']): ?>
                                                    <br><small class="text-muted">یادداشت: <?php echo htmlspecialchars($item['notes']); ?></small>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- گزارش‌ها -->
                    <div class="tab-pane fade" id="reports">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5><i class="bi bi-file-text"></i> گزارش‌ها</h5>
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createReportModal">
                                <i class="bi bi-plus"></i> ایجاد گزارش
                            </button>
                        </div>
                        
                        <?php if (empty($reports)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-file-text display-1 text-muted"></i>
                                <p class="text-muted mt-3">هیچ گزارشی ایجاد نشده است</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($reports as $report): ?>
                                <div class="info-card">
                                    <h6><?php echo htmlspecialchars($report['title']); ?></h6>
                                    <p class="text-muted">نوع: <?php echo $report['report_type']; ?> | تاریخ: <?php echo jalali_format($report['created_at']); ?></p>
                                    <p><?php echo nl2br(htmlspecialchars($report['content'])); ?></p>
                                    <?php if ($report['pdf_path']): ?>
                                        <a href="<?php echo $report['pdf_path']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-download"></i> دانلود PDF
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- تاریخچه -->
                    <div class="tab-pane fade" id="history">
                        <h5><i class="bi bi-clock-history"></i> تاریخچه عملیات</h5>
                        
                        <div class="timeline">
                            <?php foreach ($history as $item): ?>
                                <div class="timeline-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6><?php echo htmlspecialchars($item['action']); ?></h6>
                                            <p class="mb-1"><?php echo htmlspecialchars($item['description']); ?></p>
                                            <?php if ($item['old_status'] && $item['new_status']): ?>
                                                <small class="text-muted">
                                                    تغییر از "<?php echo $item['old_status']; ?>" به "<?php echo $item['new_status']; ?>"
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-end">
                                            <small class="text-muted"><?php echo jalali_format($item['created_at']); ?></small>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($item['performed_by_name']); ?></small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal آپلود مدرک -->
    <div class="modal fade" id="uploadDocumentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">آپلود مدرک</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="upload_document" value="1">
                        <?php echo csrf_field(); ?>
                        <div class="mb-3">
                            <label class="form-label">نوع مدرک</label>
                            <select name="document_type" class="form-select" required>
                                <option value="company_registration">شرکتنامه</option>
                                <option value="introduction_letter">معرفی‌نامه</option>
                                <option value="permit">مجوز</option>
                                <option value="nda">NDA</option>
                                <option value="id_copy">کپی شناسنامه</option>
                                <option value="other">سایر</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">نام مدرک</label>
                            <input type="text" name="document_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">فایل</label>
                            <input type="file" name="document_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" class="btn btn-success">آپلود</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal آپلود عکس -->
    <div class="modal fade" id="uploadPhotoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">آپلود عکس</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="upload_photo" value="1">
                        <?php echo csrf_field(); ?>
                        <div class="mb-3">
                            <label class="form-label">نوع عکس</label>
                            <select name="photo_type" class="form-select" required>
                                <option value="check_in">Check-in</option>
                                <option value="onsite">حین بازدید</option>
                                <option value="equipment">تجهیزات</option>
                                <option value="visitor">بازدیدکننده</option>
                                <option value="signature">امضا</option>
                                <option value="other">سایر</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">توضیح</label>
                            <input type="text" name="photo_caption" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">فایل</label>
                            <input type="file" name="photo_file" class="form-control" accept=".jpg,.jpeg,.png" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" class="btn btn-success">آپلود</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal ایجاد گزارش -->
    <div class="modal fade" id="createReportModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">ایجاد گزارش</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="create_report" value="1">
                        <?php echo csrf_field(); ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">نوع گزارش</label>
                                <select name="report_type" class="form-select" required>
                                    <option value="onsite">حین بازدید</option>
                                    <option value="final">نهایی</option>
                                    <option value="technical">فنی</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">عنوان</label>
                                <input type="text" name="report_title" class="form-control" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">محتوای گزارش</label>
                                <textarea name="report_content" class="form-control" rows="5" required></textarea>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">بازخورد بازدیدکننده</label>
                                <textarea name="visitor_feedback" class="form-control" rows="3"></textarea>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">توصیه‌ها</label>
                                <textarea name="recommendations" class="form-control" rows="3"></textarea>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input type="checkbox" name="follow_up_required" class="form-check-input" id="follow_up_required">
                                    <label class="form-check-label" for="follow_up_required">نیاز به پیگیری</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">تاریخ پیگیری</label>
                                <input type="date" name="follow_up_date" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" class="btn btn-success">ایجاد گزارش</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>