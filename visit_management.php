<?php
session_start();
require_once 'config.php';

// بررسی احراز هویت
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// بررسی دسترسی
if (!hasPermission('visit_management')) {
    die('دسترسی غیرمجاز - شما مجوز دسترسی به این بخش را ندارید');
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
$visit_requests = getVisitRequests($pdo, $filters);

// دریافت آمار
$stats = getVisitStatistics($pdo);

// دریافت دستگاه‌های در دسترس
$available_devices = [];
if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
    $available_devices = getAvailableDevices($pdo, $filters['date_from'], $filters['date_to']);
}

// پردازش عملیات
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
                'visit_duration' => (int)$_POST['visit_duration'],
                'requires_nda' => isset($_POST['requires_nda']),
                'special_requirements' => sanitizeInput($_POST['special_requirements']),
                'priority' => sanitizeInput($_POST['priority'])
            ];
            
            $visit_request_id = createVisitRequest($pdo, $visit_data);
            
            // آپلود مدارک
            if (!empty($_FILES['documents']['name'][0])) {
                foreach ($_FILES['documents']['name'] as $key => $name) {
                    if (!empty($name)) {
                        $file = [
                            'name' => $_FILES['documents']['name'][$key],
                            'type' => $_FILES['documents']['type'][$key],
                            'tmp_name' => $_FILES['documents']['tmp_name'][$key],
                            'error' => $_FILES['documents']['error'][$key],
                            'size' => $_FILES['documents']['size'][$key]
                        ];
                        
                        $document_type = sanitizeInput($_POST['document_types'][$key]);
                        uploadVisitDocument($pdo, $visit_request_id, $file, $document_type, $name);
                    }
                }
            }
            
            $_SESSION['success_message'] = "درخواست بازدید با موفقیت ثبت شد! شماره درخواست: " . generateVisitRequestNumber($pdo);
            header("Location: visit_management.php");
            exit();
        }
        
        if (isset($_POST['update_status'])) {
            $visit_request_id = (int)$_POST['visit_request_id'];
            $new_status = sanitizeInput($_POST['new_status']);
            $notes = sanitizeInput($_POST['status_notes']);
            
            updateVisitStatus($pdo, $visit_request_id, $new_status, $notes);
            $_SESSION['success_message'] = "وضعیت درخواست به‌روزرسانی شد";
            header("Location: visit_management.php");
            exit();
        }
        
        if (isset($_POST['reserve_device'])) {
            $visit_request_id = (int)$_POST['visit_request_id'];
            $asset_id = (int)$_POST['asset_id'];
            $reserved_from = $_POST['reserved_from'];
            $reserved_to = $_POST['reserved_to'];
            
            reserveDeviceForVisit($pdo, $visit_request_id, $asset_id, $reserved_from, $reserved_to);
            $_SESSION['success_message'] = "دستگاه با موفقیت رزرو شد";
            header("Location: visit_management.php");
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
    <title>مدیریت بازدید کارخانه - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --light-bg: #f8f9fa;
            --dark-bg: #343a40;
        }
        
        body {
            font-family: Vazirmatn, sans-serif;
            background-color: var(--light-bg);
            padding-top: 80px;
        }
        
        .visit-container {
            max-width: 1400px;
            margin: 0 auto;
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 5px 25px rgba(0,0,0,.1);
            overflow: hidden;
        }
        
        .visit-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: #fff;
            padding: 25px;
            text-align: center;
        }
        
        .visit-body {
            padding: 30px;
        }
        
        .stats-card {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,.05);
            transition: all .3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,.1);
        }
        
        .stats-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--secondary-color);
        }
        
        .visit-card {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,.05);
            transition: all .3s ease;
        }
        
        .visit-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,.1);
            transform: translateY(-2px);
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: bold;
        }
        
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
        .status-archived { background: #f5f5f5; color: #616161; }
        
        .priority-badge {
            padding: 3px 8px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: bold;
        }
        
        .priority-کم { background: #e8f5e8; color: #2e7d32; }
        .priority-متوسط { background: #fff3e0; color: #f57c00; }
        .priority-بالا { background: #ffebee; color: #d32f2f; }
        .priority-فوری { background: #fce4ec; color: #c2185b; }
        
        .btn-visit {
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--primary-color) 100%);
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            transition: all .3s ease;
        }
        
        .btn-visit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,.2);
            color: white;
        }
        
        .filter-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #ced4da;
            padding: 10px 15px;
            transition: all .3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 .25rem rgba(52,152,219,.25);
        }
        
        .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,.2);
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: 15px 15px 0 0;
        }
        
        .timeline {
            position: relative;
            padding: 20px 0;
        }
        
        .timeline-item {
            position: relative;
            padding: 15px 0 15px 30px;
            border-left: 2px solid #e0e0e0;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -6px;
            top: 20px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--secondary-color);
        }
        
        .timeline-item:last-child {
            border-left: none;
        }
        
        .calendar-container {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,.05);
        }
        
        .device-card {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            transition: all .3s ease;
        }
        
        .device-card:hover {
            border-color: var(--secondary-color);
            box-shadow: 0 2px 8px rgba(0,0,0,.1);
        }
        
        .device-available {
            border-left: 4px solid var(--success-color);
        }
        
        .device-reserved {
            border-left: 4px solid var(--warning-color);
        }
        
        .device-in-use {
            border-left: 4px solid var(--accent-color);
        }
        
        @media (max-width: 768px) {
            .visit-body {
                padding: 20px;
            }
            
            .stats-card {
                margin-bottom: 15px;
            }
            
            .visit-card {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <?php if (file_exists('navbar.php')) include 'navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="visit-container">
            <div class="visit-header">
                <h2><i class="bi bi-building"></i> مدیریت بازدید کارخانه</h2>
                <p class="mb-0">سیستم جامع مدیریت بازدیدها و رزرو دستگاه‌ها</p>
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
                
                <!-- آمار کلی -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card text-center">
                            <div class="stats-number"><?php echo $stats['total_requests']; ?></div>
                            <div class="text-muted">کل درخواست‌ها</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card text-center">
                            <div class="stats-number text-warning">
                                <?php 
                                $pending = array_filter($stats['by_status'], function($s) { return $s['status'] === 'new'; });
                                echo $pending ? $pending[0]['count'] : 0;
                                ?>
                            </div>
                            <div class="text-muted">در انتظار بررسی</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card text-center">
                            <div class="stats-number text-success">
                                <?php 
                                $today = array_filter($stats['by_status'], function($s) { return $s['status'] === 'scheduled'; });
                                echo $today ? $today[0]['count'] : 0;
                                ?>
                            </div>
                            <div class="text-muted">برنامه‌ریزی شده</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card text-center">
                            <div class="stats-number text-info">
                                <?php 
                                $completed = array_filter($stats['by_status'], function($s) { return $s['status'] === 'completed'; });
                                echo $completed ? $completed[0]['count'] : 0;
                                ?>
                            </div>
                            <div class="text-muted">تکمیل شده</div>
                        </div>
                    </div>
                </div>
                
                <!-- فیلترها -->
                <div class="filter-section">
                    <h5><i class="bi bi-funnel"></i> فیلتر و جستجو</h5>
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
                                <option value="مشتری" <?php echo $filters['visit_type'] === 'مشتری' ? 'selected' : ''; ?>>مشتری</option>
                                <option value="ارگان" <?php echo $filters['visit_type'] === 'ارگان' ? 'selected' : ''; ?>>ارگان</option>
                                <option value="داخلی" <?php echo $filters['visit_type'] === 'داخلی' ? 'selected' : ''; ?>>داخلی</option>
                                <option value="تامین_کننده" <?php echo $filters['visit_type'] === 'تامین_کننده' ? 'selected' : ''; ?>>تامین‌کننده</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">از تاریخ</label>
                            <input type="date" name="date_from" class="form-control" value="<?php echo $filters['date_from']; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">تا تاریخ</label>
                            <input type="date" name="date_to" class="form-control" value="<?php echo $filters['date_to']; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">نام شرکت</label>
                            <input type="text" name="company_name" class="form-control" placeholder="جستجو در نام شرکت..." value="<?php echo $filters['company_name']; ?>">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- دکمه‌های عملیات -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#createVisitModal">
                            <i class="bi bi-plus-circle"></i> درخواست بازدید جدید
                        </button>
                        <button class="btn btn-info me-2" data-bs-toggle="modal" data-bs-target="#calendarModal">
                            <i class="bi bi-calendar3"></i> تقویم بازدیدها
                        </button>
                        <button class="btn btn-warning me-2" data-bs-toggle="modal" data-bs-target="#devicesModal">
                            <i class="bi bi-gear"></i> مدیریت دستگاه‌ها
                        </button>
                        <button class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#checkinModal">
                            <i class="bi bi-qr-code-scan"></i> Check-in
                        </button>
                    </div>
                </div>
                
                <!-- لیست درخواست‌ها -->
                <div class="row">
                    <div class="col-md-12">
                        <h5><i class="bi bi-list-ul"></i> درخواست‌های بازدید</h5>
                        <?php if (empty($visit_requests)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-inbox display-1 text-muted"></i>
                                <p class="text-muted mt-3">هیچ درخواست بازدیدی یافت نشد</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($visit_requests as $request): ?>
                                <div class="visit-card">
                                    <div class="row align-items-center">
                                        <div class="col-md-2">
                                            <div class="fw-bold"><?php echo htmlspecialchars($request['request_number']); ?></div>
                                            <div class="text-muted small"><?php echo jalali_format($request['created_at']); ?></div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="fw-bold"><?php echo htmlspecialchars($request['company_name']); ?></div>
                                            <div class="text-muted small"><?php echo htmlspecialchars($request['contact_person']); ?></div>
                                            <div class="text-muted small"><?php echo htmlspecialchars($request['contact_phone']); ?></div>
                                        </div>
                                        <div class="col-md-2">
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
                                            <br>
                                            <span class="priority-badge priority-<?php echo $request['priority']; ?>">
                                                <?php echo $request['priority']; ?>
                                            </span>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="text-muted small">نوع: <?php echo $request['visit_type']; ?></div>
                                            <div class="text-muted small">هدف: <?php echo $request['visit_purpose']; ?></div>
                                            <div class="text-muted small">تعداد: <?php echo $request['visitor_count']; ?> نفر</div>
                                        </div>
                                        <div class="col-md-2">
                                            <?php if ($request['confirmed_date']): ?>
                                                <div class="text-muted small">تاریخ: <?php echo jalali_format($request['confirmed_date']); ?></div>
                                            <?php endif; ?>
                                            <?php if ($request['host_name']): ?>
                                                <div class="text-muted small">Host: <?php echo htmlspecialchars($request['host_name']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-1">
                                            <div class="btn-group-vertical">
                                                <button class="btn btn-sm btn-outline-primary" onclick="viewVisitDetails(<?php echo $request['id']; ?>)">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-success" onclick="editVisit(<?php echo $request['id']; ?>)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal ایجاد درخواست بازدید -->
    <div class="modal fade" id="createVisitModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> درخواست بازدید جدید</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="create_visit_request" value="1">
                        <?php echo csrf_field(); ?>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">نام شرکت <span class="text-danger">*</span></label>
                                <input type="text" name="company_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">شخص تماس <span class="text-danger">*</span></label>
                                <input type="text" name="contact_person" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">شماره تماس <span class="text-danger">*</span></label>
                                <input type="tel" name="contact_phone" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ایمیل</label>
                                <input type="email" name="contact_email" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">تعداد بازدیدکننده</label>
                                <input type="number" name="visitor_count" class="form-control" value="1" min="1">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">نوع بازدید</label>
                                <select name="visit_type" class="form-select">
                                    <option value="مشتری">مشتری</option>
                                    <option value="ارگان">ارگان</option>
                                    <option value="داخلی">داخلی</option>
                                    <option value="تامین_کننده">تامین‌کننده</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">هدف بازدید</label>
                                <select name="visit_purpose" class="form-select">
                                    <option value="دیداری">دیداری</option>
                                    <option value="تست">تست</option>
                                    <option value="خرید">خرید</option>
                                    <option value="بازرسی">بازرسی</option>
                                    <option value="آموزش">آموزش</option>
                                    <option value="سایر">سایر</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">روش درخواست</label>
                                <select name="request_method" class="form-select">
                                    <option value="تماس">تماس</option>
                                    <option value="ایمیل">ایمیل</option>
                                    <option value="حضوری">حضوری</option>
                                    <option value="آنلاین">آنلاین</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">مدت بازدید (دقیقه)</label>
                                <input type="number" name="visit_duration" class="form-control" value="60" min="30">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">اولویت</label>
                                <select name="priority" class="form-select">
                                    <option value="کم">کم</option>
                                    <option value="متوسط" selected>متوسط</option>
                                    <option value="بالا">بالا</option>
                                    <option value="فوری">فوری</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">تاریخ‌های پیشنهادی</label>
                                <div class="row" id="preferred_dates">
                                    <div class="col-md-6">
                                        <input type="date" name="preferred_dates[]" class="form-control mb-2">
                                    </div>
                                    <div class="col-md-6">
                                        <input type="date" name="preferred_dates[]" class="form-control mb-2">
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="addPreferredDate()">
                                    <i class="bi bi-plus"></i> افزودن تاریخ
                                </button>
                            </div>
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input type="checkbox" name="requires_nda" class="form-check-input" id="requires_nda">
                                    <label class="form-check-label" for="requires_nda">
                                        نیاز به NDA (قرارداد عدم افشاء)
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">نیازهای خاص</label>
                                <textarea name="special_requirements" class="form-control" rows="3" placeholder="نیازهای خاص بازدید..."></textarea>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">مدارک</label>
                                <div id="documents">
                                    <div class="row mb-2">
                                        <div class="col-md-4">
                                            <select name="document_types[]" class="form-select">
                                                <option value="company_registration">شرکتنامه</option>
                                                <option value="introduction_letter">معرفی‌نامه</option>
                                                <option value="permit">مجوز</option>
                                                <option value="id_copy">کپی شناسنامه</option>
                                                <option value="other">سایر</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <input type="file" name="documents[]" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                        </div>
                                        <div class="col-md-2">
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeDocument(this)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="addDocument()">
                                    <i class="bi bi-plus"></i> افزودن مدرک
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" class="btn btn-success">ایجاد درخواست</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal تقویم -->
    <div class="modal fade" id="calendarModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-calendar3"></i> تقویم بازدیدها</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="calendar-container">
                        <div id="calendar"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal مدیریت دستگاه‌ها -->
    <div class="modal fade" id="devicesModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-gear"></i> مدیریت دستگاه‌ها</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>دستگاه‌های در دسترس</h6>
                            <div id="available_devices">
                                <?php foreach ($available_devices as $device): ?>
                                    <div class="device-card device-available">
                                        <div class="fw-bold"><?php echo htmlspecialchars($device['name']); ?></div>
                                        <div class="text-muted small"><?php echo htmlspecialchars($device['type_name']); ?></div>
                                        <div class="text-muted small">سریال: <?php echo htmlspecialchars($device['serial_number'] ?? '-'); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6>رزرو دستگاه</h6>
                            <form method="POST" id="reserveDeviceForm">
                                <input type="hidden" name="reserve_device" value="1">
                                <?php echo csrf_field(); ?>
                                <div class="mb-3">
                                    <label class="form-label">درخواست بازدید</label>
                                    <select name="visit_request_id" class="form-select" required>
                                        <option value="">انتخاب کنید...</option>
                                        <?php foreach ($visit_requests as $request): ?>
                                            <option value="<?php echo $request['id']; ?>">
                                                <?php echo htmlspecialchars($request['request_number'] . ' - ' . $request['company_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">دستگاه</label>
                                    <select name="asset_id" class="form-select" required>
                                        <option value="">انتخاب کنید...</option>
                                        <?php foreach ($available_devices as $device): ?>
                                            <option value="<?php echo $device['id']; ?>">
                                                <?php echo htmlspecialchars($device['name'] . ' (' . $device['type_name'] . ')'); ?>
                                            </option>
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
                                <button type="submit" class="btn btn-success">رزرو دستگاه</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Check-in -->
    <div class="modal fade" id="checkinModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-qr-code-scan"></i> Check-in بازدید</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center">
                        <div class="mb-3">
                            <i class="bi bi-qr-code display-1 text-primary"></i>
                        </div>
                        <p>QR Code را اسکن کنید یا شماره درخواست را وارد کنید:</p>
                        <div class="input-group">
                            <input type="text" class="form-control" placeholder="شماره درخواست یا QR Code" id="checkin_code">
                            <button class="btn btn-primary" onclick="processCheckin()">
                                <i class="bi bi-search"></i> جستجو
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    <script>
        // افزودن تاریخ پیشنهادی
        function addPreferredDate() {
            const container = document.getElementById('preferred_dates');
            const newDate = document.createElement('div');
            newDate.className = 'col-md-6';
            newDate.innerHTML = '<input type="date" name="preferred_dates[]" class="form-control mb-2">';
            container.appendChild(newDate);
        }
        
        // افزودن مدرک
        function addDocument() {
            const container = document.getElementById('documents');
            const newDoc = document.createElement('div');
            newDoc.className = 'row mb-2';
            newDoc.innerHTML = `
                <div class="col-md-4">
                    <select name="document_types[]" class="form-select">
                        <option value="company_registration">شرکتنامه</option>
                        <option value="introduction_letter">معرفی‌نامه</option>
                        <option value="permit">مجوز</option>
                        <option value="id_copy">کپی شناسنامه</option>
                        <option value="other">سایر</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <input type="file" name="documents[]" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeDocument(this)">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            `;
            container.appendChild(newDoc);
        }
        
        // حذف مدرک
        function removeDocument(button) {
            button.closest('.row').remove();
        }
        
        // مشاهده جزئیات بازدید
        function viewVisitDetails(visitId) {
            // پیاده‌سازی مشاهده جزئیات
            alert('مشاهده جزئیات بازدید: ' + visitId);
        }
        
        // ویرایش بازدید
        function editVisit(visitId) {
            // پیاده‌سازی ویرایش بازدید
            alert('ویرایش بازدید: ' + visitId);
        }
        
        // پردازش Check-in
        function processCheckin() {
            const code = document.getElementById('checkin_code').value;
            if (code) {
                // پیاده‌سازی Check-in
                alert('Check-in برای: ' + code);
            }
        }
        
        // تقویم
        document.addEventListener('DOMContentLoaded', function() {
            const calendarEl = document.getElementById('calendar');
            if (calendarEl) {
                const calendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: 'dayGridMonth',
                    locale: 'fa',
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,timeGridWeek,timeGridDay'
                    },
                    events: [
                        // رویدادهای تقویم
                    ]
                });
                calendar.render();
            }
        });
    </script>
</body>
</html>