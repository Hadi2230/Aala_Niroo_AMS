<?php
session_start();
require_once 'config.php';

// بررسی احراز هویت
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// دریافت فیلترها
$filters = [
    'status' => $_GET['status'] ?? '',
    'visit_type' => $_GET['visit_type'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'company_name' => $_GET['company_name'] ?? ''
];

// دریافت درخواست‌های بازدید
$visit_requests = [];
try {
    if (function_exists('getVisitRequests')) {
        $visit_requests = getVisitRequests($pdo, $filters);
    }
} catch (Exception $e) {
    // جدول وجود ندارد
}

// دریافت آمار
$stats = ['total_requests' => 0, 'by_status' => [], 'by_type' => []];
try {
    if (function_exists('getVisitStatistics')) {
        $stats = getVisitStatistics($pdo);
    }
} catch (Exception $e) {
    // جدول وجود ندارد
}

// دریافت دستگاه‌های در دسترس
$available_devices = [];
try {
    if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
        if (function_exists('getAvailableDevices')) {
            $available_devices = getAvailableDevices($pdo, $filters['date_from'], $filters['date_to']);
        }
    }
} catch (Exception $e) {
    // جدول وجود ندارد
}

// پردازش عملیات
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['create_visit_request'])) {
            $visit_data = [
                'company_name' => sanitizeInput($_POST['company_name']),
                'contact_person' => sanitizeInput($_POST['contact_person']),
                'contact_phone' => sanitizeInput($_POST['contact_phone']),
                'contact_email' => sanitizeInput($_POST['contact_email']),
                'visitor_count' => (int)$_POST['visitor_count'],
                'visit_purpose' => sanitizeInput($_POST['visit_purpose']),
                'visit_type' => sanitizeInput($_POST['visit_type']),
                'request_method' => sanitizeInput($_POST['request_method']),
                'preferred_dates' => $_POST['preferred_dates'] ?? [],
                'nda_required' => isset($_POST['nda_required']),
                'special_requirements' => sanitizeInput($_POST['special_requirements']),
                'created_by' => $_SESSION['user_id']
            ];
            
            $visit_id = createVisitRequest($pdo, $visit_data);
            $message = 'درخواست بازدید با موفقیت ثبت شد. شماره درخواست: ' . $visit_id;
            $message_type = 'success';
        }
        
        if (isset($_POST['edit_visit_request'])) {
            $visit_id = (int)$_POST['visit_id'];
            $update_data = [
                'company_name' => sanitizeInput($_POST['company_name']),
                'contact_person' => sanitizeInput($_POST['contact_person']),
                'contact_phone' => sanitizeInput($_POST['contact_phone']),
                'contact_email' => sanitizeInput($_POST['contact_email']),
                'visitor_count' => (int)$_POST['visitor_count'],
                'visit_purpose' => sanitizeInput($_POST['visit_purpose']),
                'visit_type' => sanitizeInput($_POST['visit_type']),
                'request_method' => sanitizeInput($_POST['request_method']),
                'preferred_dates' => $_POST['preferred_dates'] ?? [],
                'nda_required' => isset($_POST['nda_required']),
                'special_requirements' => sanitizeInput($_POST['special_requirements'])
            ];
            
            $stmt = $pdo->prepare("
                UPDATE visit_requests SET 
                    company_name = ?, contact_person = ?, contact_phone = ?, 
                    contact_email = ?, visitor_count = ?, visit_purpose = ?, 
                    visit_type = ?, request_method = ?, preferred_dates = ?, 
                    nda_required = ?, special_requirements = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $update_data['company_name'], $update_data['contact_person'], 
                $update_data['contact_phone'], $update_data['contact_email'], 
                $update_data['visitor_count'], $update_data['visit_purpose'], 
                $update_data['visit_type'], $update_data['request_method'], 
                json_encode($update_data['preferred_dates']), 
                $update_data['nda_required'] ? 1 : 0, 
                $update_data['special_requirements'], $visit_id
            ]);
            
            $message = 'درخواست بازدید با موفقیت ویرایش شد';
            $message_type = 'success';
        }
        
        if (isset($_POST['delete_visit_request'])) {
            $visit_id = (int)$_POST['visit_id'];
            
            $stmt = $pdo->prepare("DELETE FROM visit_requests WHERE id = ?");
            $stmt->execute([$visit_id]);
            
            $message = 'درخواست بازدید با موفقیت حذف شد';
            $message_type = 'success';
        }
        
        if (isset($_POST['upload_document'])) {
            $visit_id = (int)$_POST['visit_id'];
            $document_type = sanitizeInput($_POST['document_type']);
            $document_name = sanitizeInput($_POST['document_name']);
            
            if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/visit_documents/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_extension = pathinfo($_FILES['document_file']['name'], PATHINFO_EXTENSION);
                $file_name = 'visit_' . $visit_id . '_' . time() . '.' . $file_extension;
                $file_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['document_file']['tmp_name'], $file_path)) {
                    $stmt = $pdo->prepare("
                        INSERT INTO visit_documents (visit_request_id, document_type, document_name, file_path, uploaded_by, uploaded_at) 
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$visit_id, $document_type, $document_name, $file_path, $_SESSION['user_id']]);
                    
                    $message = 'مدرک با موفقیت آپلود شد';
                    $message_type = 'success';
                } else {
                    $message = 'خطا در آپلود فایل';
                    $message_type = 'error';
                }
            } else {
                $message = 'لطفاً فایل را انتخاب کنید';
                $message_type = 'error';
            }
        }
        
        if (isset($_POST['create_visit_result'])) {
            $visit_id = (int)$_POST['visit_id'];
            $result_data = [
                'visit_duration' => (int)$_POST['visit_duration'],
                'satisfaction_rating' => (int)$_POST['satisfaction_rating'],
                'equipment_tested' => $_POST['equipment_tested'] ?? [],
                'recommendations' => sanitizeInput($_POST['recommendations']),
                'follow_up_required' => isset($_POST['follow_up_required']),
                'next_visit_date' => $_POST['next_visit_date'] ?: null,
                'conversion_probability' => (int)$_POST['conversion_probability'],
                'notes' => sanitizeInput($_POST['notes'])
            ];
            
            $stmt = $pdo->prepare("
                INSERT INTO visit_reports (visit_request_id, report_type, report_data, created_by, created_at) 
                VALUES (?, 'visit_result', ?, ?, NOW())
            ");
            $stmt->execute([$visit_id, json_encode($result_data), $_SESSION['user_id']]);
            
            // تغییر وضعیت به تکمیل شده
            updateVisitStatus($pdo, $visit_id, 'completed', 'نتیجه بازدید ثبت شد');
            
            $message = 'نتیجه بازدید با موفقیت ثبت شد';
            $message_type = 'success';
        }
        
        if (isset($_POST['update_status'])) {
            $visit_id = (int)$_POST['visit_id'];
            $new_status = sanitizeInput($_POST['new_status']);
            $notes = sanitizeInput($_POST['status_notes']);
            
            if (function_exists('updateVisitStatus')) {
                updateVisitStatus($pdo, $visit_id, $new_status, $notes);
                $message = 'وضعیت بازدید با موفقیت به‌روزرسانی شد';
                $message_type = 'success';
            } else {
                $message = 'تابع updateVisitStatus در دسترس نیست';
                $message_type = 'error';
            }
        }
        
        if (isset($_POST['reserve_device'])) {
            $visit_id = (int)$_POST['visit_id'];
            $asset_id = (int)$_POST['asset_id'];
            $reserved_from = $_POST['reserved_from'];
            $reserved_to = $_POST['reserved_to'];
            
            if (function_exists('reserveDeviceForVisit')) {
                reserveDeviceForVisit($pdo, $visit_id, $asset_id, $reserved_from, $reserved_to);
                $message = 'دستگاه با موفقیت رزرو شد';
                $message_type = 'success';
            } else {
                $message = 'تابع reserveDeviceForVisit در دسترس نیست';
                $message_type = 'error';
            }
        }
        
    } catch (Exception $e) {
        $message = 'خطا در پردازش درخواست: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// دریافت لیست دستگاه‌ها
$assets = [];
try {
    $stmt = $pdo->query("SELECT id, name, type_id FROM assets WHERE status = 'فعال' ORDER BY name");
    $assets = $stmt->fetchAll();
} catch (Exception $e) {
    // جدول وجود ندارد
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت بازدید کارخانه - اعلا نیرو</title>
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
        
        .btn-success {
            background: var(--success-color);
            border: none;
            border-radius: 8px;
        }
        
        .btn-warning {
            background: var(--warning-color);
            border: none;
            border-radius: 8px;
        }
        
        .btn-danger {
            background: var(--danger-color);
            border: none;
            border-radius: 8px;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .status-new { background: #e3f2fd; color: #1976d2; }
        .status-documents_required { background: #fff3e0; color: #f57c00; }
        .status-reviewed { background: #f1f8e9; color: #689f38; }
        .status-scheduled { background: #e0f2f1; color: #00796b; }
        .status-reserved { background: #fff8e1; color: #f9a825; }
        .status-ready_for_visit { background: #e8f5e8; color: #2e7d32; }
        .status-checked_in { background: #e1f5fe; color: #0277bd; }
        .status-onsite { background: #fce4ec; color: #c2185b; }
        .status-completed { background: #e8f5e8; color: #2e7d32; }
        .status-cancelled { background: #ffebee; color: #c62828; }
        .status-archived { background: #f3e5f5; color: #7b1fa2; }
        
        .priority-badge {
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 0.7rem;
            font-weight: bold;
        }
        
        .priority-فوری { background: #ffebee; color: #c62828; }
        .priority-بالا { background: #fff3e0; color: #f57c00; }
        .priority-متوسط { background: #e3f2fd; color: #1976d2; }
        .priority-کم { background: #e8f5e8; color: #2e7d32; }
        
        .table {
            border-radius: 10px;
            overflow: hidden;
        }
        
        .table thead th {
            background: var(--light-bg);
            border: none;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .table tbody tr {
            transition: all .3s ease;
        }
        
        .table tbody tr:hover {
            background: #f8f9fa;
            transform: scale(1.01);
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
        
        .filter-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,.08);
        }
        
        .stats-row {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,.08);
        }
        
        .stat-item {
            text-align: center;
            padding: 15px;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 20px; }
            .table-responsive { font-size: 0.8rem; }
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
                        <h1><i class="bi bi-building"></i> مدیریت بازدید کارخانه</h1>
                        <p class="mb-0">ثبت و مدیریت درخواست‌های بازدید از کارخانه</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#createVisitModal">
                            <i class="bi bi-plus-circle"></i> درخواست جدید
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
            
            <!-- آمار کلی -->
            <div class="stats-row">
                <div class="row">
                    <div class="col-md-3">
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $stats['total_requests']; ?></div>
                            <div class="stat-label">کل درخواست‌ها</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-item">
                            <div class="stat-number">
                                <?php 
                                $pending = array_filter($stats['by_status'], function($s) { return $s['status'] === 'documents_required'; });
                                echo $pending ? $pending[0]['count'] : 0;
                                ?>
                            </div>
                            <div class="stat-label">نیاز به مدارک</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-item">
                            <div class="stat-number">
                                <?php 
                                $scheduled = array_filter($stats['by_status'], function($s) { return $s['status'] === 'scheduled'; });
                                echo $scheduled ? $scheduled[0]['count'] : 0;
                                ?>
                            </div>
                            <div class="stat-label">برنامه‌ریزی شده</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-item">
                            <div class="stat-number">
                                <?php 
                                $completed = array_filter($stats['by_status'], function($s) { return $s['status'] === 'completed'; });
                                echo $completed ? $completed[0]['count'] : 0;
                                ?>
                            </div>
                            <div class="stat-label">تکمیل شده</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- فیلترها -->
            <div class="filter-section">
                <h5><i class="bi bi-funnel"></i> فیلترها</h5>
                <form method="GET" class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label">وضعیت</label>
                        <select name="status" class="form-select">
                            <option value="">همه</option>
                            <option value="new" <?php echo $filters['status'] === 'new' ? 'selected' : ''; ?>>جدید</option>
                            <option value="documents_required" <?php echo $filters['status'] === 'documents_required' ? 'selected' : ''; ?>>نیاز به مدارک</option>
                            <option value="reviewed" <?php echo $filters['status'] === 'reviewed' ? 'selected' : ''; ?>>بررسی شده</option>
                            <option value="scheduled" <?php echo $filters['status'] === 'scheduled' ? 'selected' : ''; ?>>برنامه‌ریزی شده</option>
                            <option value="reserved" <?php echo $filters['status'] === 'reserved' ? 'selected' : ''; ?>>رزرو شده</option>
                            <option value="ready_for_visit" <?php echo $filters['status'] === 'ready_for_visit' ? 'selected' : ''; ?>>آماده بازدید</option>
                            <option value="checked_in" <?php echo $filters['status'] === 'checked_in' ? 'selected' : ''; ?>>وارد شده</option>
                            <option value="onsite" <?php echo $filters['status'] === 'onsite' ? 'selected' : ''; ?>>در حال بازدید</option>
                            <option value="completed" <?php echo $filters['status'] === 'completed' ? 'selected' : ''; ?>>تکمیل شده</option>
                            <option value="cancelled" <?php echo $filters['status'] === 'cancelled' ? 'selected' : ''; ?>>لغو شده</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">نوع بازدید</label>
                        <select name="visit_type" class="form-select">
                            <option value="">همه</option>
                            <option value="meeting" <?php echo $filters['visit_type'] === 'meeting' ? 'selected' : ''; ?>>جلسه</option>
                            <option value="test" <?php echo $filters['visit_type'] === 'test' ? 'selected' : ''; ?>>تست</option>
                            <option value="purchase" <?php echo $filters['visit_type'] === 'purchase' ? 'selected' : ''; ?>>خرید</option>
                            <option value="inspection" <?php echo $filters['visit_type'] === 'inspection' ? 'selected' : ''; ?>>بازرسی</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">از تاریخ</label>
                        <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($filters['date_from']); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">تا تاریخ</label>
                        <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($filters['date_to']); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">نام شرکت</label>
                        <input type="text" name="company_name" class="form-control" value="<?php echo htmlspecialchars($filters['company_name']); ?>" placeholder="جستجو...">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search"></i> جستجو
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- جدول درخواست‌ها -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-list-ul"></i> لیست درخواست‌های بازدید</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($visit_requests)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-inbox display-1 text-muted"></i>
                            <h5 class="text-muted mt-3">هیچ درخواست بازدیدی یافت نشد</h5>
                            <p class="text-muted">برای شروع، یک درخواست جدید ایجاد کنید</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>شماره درخواست</th>
                                        <th>شرکت</th>
                                        <th>تماس</th>
                                        <th>نوع بازدید</th>
                                        <th>تاریخ پیشنهادی</th>
                                        <th>وضعیت</th>
                                        <th>اولویت</th>
                                        <th>عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($visit_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($request['request_number']); ?></strong>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($request['company_name']); ?>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($request['contact_person']); ?></small>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($request['contact_phone']); ?>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($request['contact_email']); ?></small>
                                            </td>
                                            <td>
                                                <?php 
                                                $type_labels = [
                                                    'meeting' => 'جلسه',
                                                    'test' => 'تست',
                                                    'purchase' => 'خرید',
                                                    'inspection' => 'بازرسی'
                                                ];
                                                echo $type_labels[$request['visit_type']] ?? $request['visit_type'];
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $preferred_dates = json_decode($request['preferred_dates'], true);
                                                if ($preferred_dates && count($preferred_dates) > 0) {
                                                    echo date('Y-m-d', strtotime($preferred_dates[0]));
                                                } else {
                                                    echo '-';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?php echo $request['status']; ?>">
                                                    <?php 
                                                    $status_labels = [
                                                        'new' => 'جدید',
                                                        'documents_required' => 'نیاز به مدارک',
                                                        'reviewed' => 'بررسی شده',
                                                        'scheduled' => 'برنامه‌ریزی شده',
                                                        'reserved' => 'رزرو شده',
                                                        'ready_for_visit' => 'آماده بازدید',
                                                        'checked_in' => 'وارد شده',
                                                        'onsite' => 'در حال بازدید',
                                                        'completed' => 'تکمیل شده',
                                                        'cancelled' => 'لغو شده',
                                                        'archived' => 'آرشیو شده'
                                                    ];
                                                    echo $status_labels[$request['status']] ?? $request['status'];
                                                    ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="priority-badge priority-<?php echo $request['priority']; ?>">
                                                    <?php echo $request['priority']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="visit_details.php?id=<?php echo $request['id']; ?>" class="btn btn-sm btn-outline-primary" title="مشاهده جزئیات">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <button class="btn btn-sm btn-outline-warning" title="ویرایش" onclick="openEditModal(<?php echo $request['id']; ?>, '<?php echo htmlspecialchars($request['company_name']); ?>', '<?php echo htmlspecialchars($request['contact_person']); ?>', '<?php echo htmlspecialchars($request['contact_phone']); ?>', '<?php echo htmlspecialchars($request['contact_email']); ?>', <?php echo $request['visitor_count']; ?>, '<?php echo htmlspecialchars($request['visit_purpose']); ?>', '<?php echo $request['visit_type']; ?>', '<?php echo $request['request_method']; ?>', '<?php echo $request['nda_required'] ? 'true' : 'false'; ?>', '<?php echo htmlspecialchars($request['special_requirements']); ?>')">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger" title="حذف" onclick="deleteVisit(<?php echo $request['id']; ?>)">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-info" title="ارسال مدرک" onclick="openDocumentModal(<?php echo $request['id']; ?>)">
                                                        <i class="bi bi-file-earmark-arrow-up"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-success" title="نتیجه بازدید" onclick="openResultModal(<?php echo $request['id']; ?>)">
                                                        <i class="bi bi-clipboard-check"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-secondary" title="تغییر وضعیت" onclick="openStatusModal(<?php echo $request['id']; ?>, '<?php echo $request['status']; ?>')">
                                                        <i class="bi bi-arrow-repeat"></i>
                                                    </button>
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

    <!-- مودال ایجاد درخواست جدید -->
    <div class="modal fade" id="createVisitModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> ایجاد درخواست بازدید جدید</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="create_visit_request" value="1">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">نام شرکت *</label>
                                <input type="text" name="company_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">شخص تماس *</label>
                                <input type="text" name="contact_person" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">شماره تماس *</label>
                                <input type="tel" name="contact_phone" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ایمیل</label>
                                <input type="email" name="contact_email" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">تعداد بازدیدکنندگان *</label>
                                <input type="number" name="visitor_count" class="form-control" min="1" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">نوع بازدید *</label>
                                <select name="visit_type" class="form-select" required>
                                    <option value="">انتخاب کنید</option>
                                    <option value="meeting">جلسه</option>
                                    <option value="test">تست</option>
                                    <option value="purchase">خرید</option>
                                    <option value="inspection">بازرسی</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">هدف بازدید *</label>
                                <textarea name="visit_purpose" class="form-control" rows="3" required></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">روش درخواست</label>
                                <select name="request_method" class="form-select">
                                    <option value="phone">تلفن</option>
                                    <option value="email">ایمیل</option>
                                    <option value="in_person">حضوری</option>
                                    <option value="website">وب‌سایت</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">تاریخ‌های پیشنهادی</label>
                                <input type="date" name="preferred_dates[]" class="form-control">
                            </div>
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input type="checkbox" name="nda_required" class="form-check-input" id="nda_required">
                                    <label class="form-check-label" for="nda_required">
                                        نیاز به امضای قرارداد محرمانگی (NDA)
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">نیازهای خاص</label>
                                <textarea name="special_requirements" class="form-control" rows="2" placeholder="در صورت وجود نیازهای خاص، اینجا ذکر کنید"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" class="btn btn-primary">ایجاد درخواست</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- مودال تغییر وضعیت -->
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-arrow-repeat"></i> تغییر وضعیت بازدید</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="update_status" value="1">
                        <input type="hidden" name="visit_id" id="status_visit_id">
                        <div class="mb-3">
                            <label class="form-label">وضعیت جدید</label>
                            <select name="new_status" class="form-select" required>
                                <option value="new">جدید</option>
                                <option value="documents_required">نیاز به مدارک</option>
                                <option value="reviewed">بررسی شده</option>
                                <option value="scheduled">برنامه‌ریزی شده</option>
                                <option value="reserved">رزرو شده</option>
                                <option value="ready_for_visit">آماده بازدید</option>
                                <option value="checked_in">وارد شده</option>
                                <option value="onsite">در حال بازدید</option>
                                <option value="completed">تکمیل شده</option>
                                <option value="cancelled">لغو شده</option>
                                <option value="archived">آرشیو شده</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">یادداشت</label>
                            <textarea name="status_notes" class="form-control" rows="3" placeholder="توضیح تغییر وضعیت..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" class="btn btn-primary">تغییر وضعیت</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- مودال ویرایش -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> ویرایش درخواست بازدید</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="edit_visit_request" value="1">
                        <input type="hidden" name="visit_id" id="edit_visit_id">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">نام شرکت *</label>
                                <input type="text" name="company_name" id="edit_company_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">شخص تماس *</label>
                                <input type="text" name="contact_person" id="edit_contact_person" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">شماره تماس *</label>
                                <input type="tel" name="contact_phone" id="edit_contact_phone" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ایمیل</label>
                                <input type="email" name="contact_email" id="edit_contact_email" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">تعداد بازدیدکنندگان *</label>
                                <input type="number" name="visitor_count" id="edit_visitor_count" class="form-control" min="1" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">نوع بازدید *</label>
                                <select name="visit_type" id="edit_visit_type" class="form-select" required>
                                    <option value="">انتخاب کنید</option>
                                    <option value="meeting">جلسه</option>
                                    <option value="test">تست</option>
                                    <option value="purchase">خرید</option>
                                    <option value="inspection">بازرسی</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">هدف بازدید *</label>
                                <textarea name="visit_purpose" id="edit_visit_purpose" class="form-control" rows="3" required></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">روش درخواست</label>
                                <select name="request_method" id="edit_request_method" class="form-select">
                                    <option value="phone">تلفن</option>
                                    <option value="email">ایمیل</option>
                                    <option value="in_person">حضوری</option>
                                    <option value="website">وب‌سایت</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">تاریخ‌های پیشنهادی</label>
                                <input type="date" name="preferred_dates[]" class="form-control">
                            </div>
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input type="checkbox" name="nda_required" id="edit_nda_required" class="form-check-input">
                                    <label class="form-check-label" for="edit_nda_required">
                                        نیاز به امضای قرارداد محرمانگی (NDA)
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">نیازهای خاص</label>
                                <textarea name="special_requirements" id="edit_special_requirements" class="form-control" rows="2" placeholder="در صورت وجود نیازهای خاص، اینجا ذکر کنید"></textarea>
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

    <!-- مودال آپلود مدرک -->
    <div class="modal fade" id="documentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-file-earmark-arrow-up"></i> آپلود مدرک</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="upload_document" value="1">
                        <input type="hidden" name="visit_id" id="document_visit_id">
                        <div class="mb-3">
                            <label class="form-label">نوع مدرک</label>
                            <select name="document_type" class="form-select" required>
                                <option value="">انتخاب کنید</option>
                                <option value="company_registration">ثبت شرکت</option>
                                <option value="introduction_letter">نامه معرفی</option>
                                <option value="permit">مجوز</option>
                                <option value="nda">قرارداد محرمانگی</option>
                                <option value="other">سایر</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">نام مدرک</label>
                            <input type="text" name="document_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">فایل</label>
                            <input type="file" name="document_file" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                            <small class="text-muted">فرمت‌های مجاز: PDF, DOC, DOCX, JPG, PNG (حداکثر 10MB)</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" class="btn btn-primary">آپلود مدرک</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- مودال نتیجه بازدید -->
    <div class="modal fade" id="resultModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-clipboard-check"></i> ثبت نتیجه بازدید</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="create_visit_result" value="1">
                        <input type="hidden" name="visit_id" id="result_visit_id">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">مدت زمان بازدید (دقیقه) *</label>
                                <input type="number" name="visit_duration" class="form-control" min="1" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">امتیاز رضایت (1-10) *</label>
                                <select name="satisfaction_rating" class="form-select" required>
                                    <option value="">انتخاب کنید</option>
                                    <?php for($i = 1; $i <= 10; $i++): ?>
                                        <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">تجهیزات تست شده</label>
                                <div class="row">
                                    <?php foreach ($assets as $asset): ?>
                                        <div class="col-md-6">
                                            <div class="form-check">
                                                <input type="checkbox" name="equipment_tested[]" value="<?php echo $asset['id']; ?>" class="form-check-input" id="equipment_<?php echo $asset['id']; ?>">
                                                <label class="form-check-label" for="equipment_<?php echo $asset['id']; ?>">
                                                    <?php echo htmlspecialchars($asset['name']); ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">توصیه‌ها</label>
                                <textarea name="recommendations" class="form-control" rows="3" placeholder="توصیه‌های شما برای مشتری..."></textarea>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input type="checkbox" name="follow_up_required" class="form-check-input" id="follow_up_required">
                                    <label class="form-check-label" for="follow_up_required">
                                        نیاز به پیگیری دارد
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">تاریخ بازدید بعدی</label>
                                <input type="date" name="next_visit_date" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">احتمال تبدیل (درصد)</label>
                                <select name="conversion_probability" class="form-select">
                                    <option value="0">0%</option>
                                    <option value="25">25%</option>
                                    <option value="50">50%</option>
                                    <option value="75">75%</option>
                                    <option value="100">100%</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">یادداشت‌های تکمیلی</label>
                                <textarea name="notes" class="form-control" rows="3" placeholder="یادداشت‌های اضافی..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" class="btn btn-success">ثبت نتیجه</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- مودال رزرو دستگاه -->
    <div class="modal fade" id="reserveModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-gear"></i> رزرو دستگاه</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="reserve_device" value="1">
                        <input type="hidden" name="visit_id" id="reserve_visit_id">
                        <div class="mb-3">
                            <label class="form-label">انتخاب دستگاه</label>
                            <select name="asset_id" class="form-select" required>
                                <option value="">انتخاب کنید</option>
                                <?php foreach ($assets as $asset): ?>
                                    <option value="<?php echo $asset['id']; ?>"><?php echo htmlspecialchars($asset['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">از تاریخ و زمان</label>
                            <input type="datetime-local" name="reserved_from" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">تا تاریخ و زمان</label>
                            <input type="datetime-local" name="reserved_to" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" class="btn btn-primary">رزرو دستگاه</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openStatusModal(visitId, currentStatus) {
            document.getElementById('status_visit_id').value = visitId;
            document.querySelector('#statusModal select[name="new_status"]').value = currentStatus;
            new bootstrap.Modal(document.getElementById('statusModal')).show();
        }
        
        function openReserveModal(visitId) {
            document.getElementById('reserve_visit_id').value = visitId;
            new bootstrap.Modal(document.getElementById('reserveModal')).show();
        }
        
        function openEditModal(visitId, companyName, contactPerson, contactPhone, contactEmail, visitorCount, visitPurpose, visitType, requestMethod, ndaRequired, specialRequirements) {
            document.getElementById('edit_visit_id').value = visitId;
            document.getElementById('edit_company_name').value = companyName;
            document.getElementById('edit_contact_person').value = contactPerson;
            document.getElementById('edit_contact_phone').value = contactPhone;
            document.getElementById('edit_contact_email').value = contactEmail;
            document.getElementById('edit_visitor_count').value = visitorCount;
            document.getElementById('edit_visit_purpose').value = visitPurpose;
            document.getElementById('edit_visit_type').value = visitType;
            document.getElementById('edit_request_method').value = requestMethod;
            document.getElementById('edit_nda_required').checked = ndaRequired === 'true';
            document.getElementById('edit_special_requirements').value = specialRequirements;
            new bootstrap.Modal(document.getElementById('editModal')).show();
        }
        
        function openDocumentModal(visitId) {
            document.getElementById('document_visit_id').value = visitId;
            new bootstrap.Modal(document.getElementById('documentModal')).show();
        }
        
        function openResultModal(visitId) {
            document.getElementById('result_visit_id').value = visitId;
            new bootstrap.Modal(document.getElementById('resultModal')).show();
        }
        
        function deleteVisit(visitId) {
            if (confirm('آیا مطمئن هستید که می‌خواهید این درخواست بازدید را حذف کنید؟')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="delete_visit_request" value="1">
                    <input type="hidden" name="visit_id" value="${visitId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Auto-refresh هر 5 دقیقه
        setInterval(function() {
            window.location.reload();
        }, 300000);
    </script>
</body>
</html>