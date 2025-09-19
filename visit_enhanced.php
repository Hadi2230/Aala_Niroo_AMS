<?php
require_once 'config.php';

if (!hasPermission('visit_management')) {
    die('دسترسی غیرمجاز');
}

$page_title = 'مدیریت پیشرفته بازدید کارخانه';

// Enhanced visit management with additional features
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create_visit_enhanced':
                    $visit_data = [
                        'company_name' => sanitizeInput($_POST['company_name']),
                        'contact_person' => sanitizeInput($_POST['contact_person']),
                        'contact_phone' => sanitizeInput($_POST['contact_phone']),
                        'contact_email' => sanitizeInput($_POST['contact_email']),
                        'visitor_count' => (int)$_POST['visitor_count'],
                        'visit_purpose' => sanitizeInput($_POST['visit_purpose']),
                        'visit_type' => sanitizeInput($_POST['visit_type']),
                        'request_method' => sanitizeInput($_POST['request_method']),
                        'preferred_dates' => json_decode($_POST['preferred_dates'], true),
                        'visit_duration' => (int)$_POST['visit_duration'],
                        'requires_nda' => isset($_POST['requires_nda']),
                        'special_requirements' => sanitizeInput($_POST['special_requirements']),
                        'priority' => sanitizeInput($_POST['priority']),
                        'host_preference' => sanitizeInput($_POST['host_preference'] ?? ''),
                        'security_clearance' => sanitizeInput($_POST['security_clearance'] ?? ''),
                        'equipment_requirements' => sanitizeInput($_POST['equipment_requirements'] ?? ''),
                        'catering_required' => isset($_POST['catering_required']),
                        'transportation_required' => isset($_POST['transportation_required']),
                        'accommodation_required' => isset($_POST['accommodation_required'])
                    ];
                    
                    $visit_id = createVisitRequest($pdo, $visit_data);
                    
                    // Create enhanced checklist
                    createEnhancedChecklist($pdo, $visit_id);
                    
                    $_SESSION['success_message'] = 'درخواست بازدید پیشرفته با موفقیت ثبت شد';
                    redirect('visit_enhanced.php');
                    break;
                    
                case 'upload_documents':
                    $visit_id = (int)$_POST['visit_id'];
                    if (!empty($_FILES['documents']['name'][0])) {
                        $upload_dir = __DIR__ . '/uploads/visit_documents/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        foreach ($_FILES['documents']['name'] as $key => $name) {
                            if (!empty($name)) {
                                $file = [
                                    'name' => $name,
                                    'type' => $_FILES['documents']['type'][$key],
                                    'tmp_name' => $_FILES['documents']['tmp_name'][$key],
                                    'error' => $_FILES['documents']['error'][$key],
                                    'size' => $_FILES['documents']['size'][$key]
                                ];
                                
                                $document_type = sanitizeInput($_POST['document_types'][$key]);
                                uploadVisitDocument($pdo, $visit_id, $file, $document_type, $name);
                            }
                        }
                        $_SESSION['success_message'] = 'مدارک با موفقیت آپلود شدند';
                    }
                    break;
                    
                case 'complete_checklist_item':
                    $checklist_id = (int)$_POST['checklist_id'];
                    $notes = sanitizeInput($_POST['notes']);
                    completeChecklistItem($pdo, $checklist_id, $notes);
                    $_SESSION['success_message'] = 'آیتم چک‌لیست تکمیل شد';
                    break;
            }
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'خطا: ' . $e->getMessage();
    }
}

// Get visit requests with enhanced filters
$filters = [
    'status' => $_GET['status'] ?? '',
    'visit_type' => $_GET['visit_type'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'company_name' => $_GET['company_name'] ?? '',
    'priority' => $_GET['priority'] ?? '',
    'host_id' => $_GET['host_id'] ?? ''
];

try {
    $visit_requests = getVisitRequests($pdo, $filters);
    $available_devices = getAvailableDevices($pdo, date('Y-m-d'), date('Y-m-d', strtotime('+30 days')));
    $hosts = getAvailableHosts($pdo);
} catch (Exception $e) {
    $visit_requests = [];
    $available_devices = [];
    $hosts = [];
}

$current_tab = $_GET['tab'] ?? 'list';
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css">
    <style>
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        .status-new { background: #e3f2fd; color: #1976d2; }
        .status-documents_required { background: #fff3e0; color: #f57c00; }
        .status-reviewed { background: #f3e5f5; color: #7b1fa2; }
        .status-scheduled { background: #e8f5e8; color: #388e3c; }
        .status-reserved { background: #fff8e1; color: #f9a825; }
        .status-ready_for_visit { background: #e0f2f1; color: #00695c; }
        .status-checked_in { background: #e1f5fe; color: #0277bd; }
        .status-onsite { background: #fce4ec; color: #c2185b; }
        .status-completed { background: #e8f5e8; color: #2e7d32; }
        .status-cancelled { background: #ffebee; color: #d32f2f; }
        .status-archived { background: #f5f5f5; color: #616161; }
        .visit-card {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        .visit-card:hover {
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .enhanced-feature {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .checklist-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid #007bff;
        }
        .checklist-item.completed {
            border-left-color: #28a745;
            background: #d4edda;
        }
        .calendar-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0">
                        <i class="fas fa-building me-2"></i>
                        <?php echo $page_title; ?>
                    </h1>
                    <div>
                        <a href="visit_dashboard.php" class="btn btn-outline-primary">
                            <i class="fas fa-tachometer-alt me-1"></i>
                            داشبورد
                        </a>
                        <a href="visit_management.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-right me-1"></i>
                            نسخه ساده
                        </a>
                    </div>
                </div>

                <!-- Enhanced Features Overview -->
                <div class="enhanced-feature">
                    <h4 class="mb-3">
                        <i class="fas fa-star me-2"></i>
                        ویژگی‌های پیشرفته سیستم مدیریت بازدید
                    </h4>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="text-center">
                                <i class="fas fa-clipboard-list fa-2x mb-2"></i>
                                <h6>چک‌لیست‌های هوشمند</h6>
                                <small>قبل، حین و بعد از بازدید</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <i class="fas fa-camera fa-2x mb-2"></i>
                                <h6>آپلود عکس و امضا</h6>
                                <small>ثبت مستندات کامل</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <i class="fas fa-calendar-alt fa-2x mb-2"></i>
                                <h6>تقویم پیشرفته</h6>
                                <small>برنامه‌ریزی و مدیریت زمان</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <i class="fas fa-chart-line fa-2x mb-2"></i>
                                <h6>گزارش‌گیری کامل</h6>
                                <small>آمار و تحلیل‌های مدیریتی</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- پیام‌های سیستم -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- تب‌های پیشرفته -->
                <ul class="nav nav-tabs" id="visitTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $current_tab === 'list' ? 'active' : ''; ?>" 
                                id="list-tab" data-bs-toggle="tab" data-bs-target="#list" type="button" role="tab">
                            <i class="fas fa-list me-2"></i>
                            لیست درخواست‌ها
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $current_tab === 'new' ? 'active' : ''; ?>" 
                                id="new-tab" data-bs-toggle="tab" data-bs-target="#new" type="button" role="tab">
                            <i class="fas fa-plus me-2"></i>
                            ثبت درخواست پیشرفته
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $current_tab === 'calendar' ? 'active' : ''; ?>" 
                                id="calendar-tab" data-bs-toggle="tab" data-bs-target="#calendar" type="button" role="tab">
                            <i class="fas fa-calendar me-2"></i>
                            تقویم بازدیدها
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $current_tab === 'reports' ? 'active' : ''; ?>" 
                                id="reports-tab" data-bs-toggle="tab" data-bs-target="#reports" type="button" role="tab">
                            <i class="fas fa-chart-bar me-2"></i>
                            گزارش‌ها و آمار
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="visitTabsContent">
                    <!-- تب لیست درخواست‌ها -->
                    <div class="tab-pane fade <?php echo $current_tab === 'list' ? 'show active' : ''; ?>" 
                         id="list" role="tabpanel">
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-filter me-2"></i>
                                    فیلترهای پیشرفته
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="GET" class="row g-3">
                                    <div class="col-md-3">
                                        <label for="status" class="form-label">وضعیت</label>
                                        <select class="form-select" id="status" name="status">
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
                                    <div class="col-md-3">
                                        <label for="visit_type" class="form-label">نوع بازدید</label>
                                        <select class="form-select" id="visit_type" name="visit_type">
                                            <option value="">همه</option>
                                            <option value="مشتری" <?php echo $filters['visit_type'] === 'مشتری' ? 'selected' : ''; ?>>مشتری</option>
                                            <option value="ارگان" <?php echo $filters['visit_type'] === 'ارگان' ? 'selected' : ''; ?>>ارگان</option>
                                            <option value="داخلی" <?php echo $filters['visit_type'] === 'داخلی' ? 'selected' : ''; ?>>داخلی</option>
                                            <option value="تامین_کننده" <?php echo $filters['visit_type'] === 'تامین_کننده' ? 'selected' : ''; ?>>تامین‌کننده</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="date_from" class="form-label">از تاریخ</label>
                                        <input type="text" class="form-control jalali-date" id="date_from" name="date_from" 
                                               value="<?php echo $filters['date_from']; ?>" readonly>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="date_to" class="form-label">تا تاریخ</label>
                                        <input type="text" class="form-control jalali-date" id="date_to" name="date_to" 
                                               value="<?php echo $filters['date_to']; ?>" readonly>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="company_name" class="form-label">نام شرکت</label>
                                        <input type="text" class="form-control" id="company_name" name="company_name" 
                                               value="<?php echo $filters['company_name']; ?>" placeholder="جستجو...">
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-search me-1"></i>
                                            جستجو
                                        </button>
                                        <a href="visit_enhanced.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-times me-1"></i>
                                            پاک کردن
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- لیست درخواست‌ها -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-list me-2"></i>
                                    درخواست‌های بازدید (<?php echo count($visit_requests); ?> مورد)
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($visit_requests)): ?>
                                    <div class="text-center text-muted py-5">
                                        <i class="fas fa-inbox fa-3x mb-3"></i>
                                        <p>هیچ درخواستی یافت نشد</p>
                                    </div>
                                <?php else: ?>
                                    <div class="row">
                                        <?php foreach ($visit_requests as $request): ?>
                                            <div class="col-md-6 col-lg-4 mb-3">
                                                <div class="visit-card">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <h6 class="mb-0"><?php echo htmlspecialchars($request['company_name']); ?></h6>
                                                        <span class="status-badge status-<?php echo $request['status']; ?>">
                                                            <?php echo $request['status']; ?>
                                                        </span>
                                                    </div>
                                                    <p class="text-muted mb-1">
                                                        <i class="fas fa-user me-1"></i>
                                                        <?php echo htmlspecialchars($request['contact_person']); ?>
                                                    </p>
                                                    <p class="text-muted mb-1">
                                                        <i class="fas fa-phone me-1"></i>
                                                        <?php echo htmlspecialchars($request['contact_phone']); ?>
                                                    </p>
                                                    <p class="text-muted mb-2">
                                                        <i class="fas fa-calendar me-1"></i>
                                                        <?php echo jalali_format($request['created_at'], 'Y/m/d H:i'); ?>
                                                    </p>
                                                    <div class="d-flex gap-2">
                                                        <a href="visit_details.php?id=<?php echo $request['id']; ?>" 
                                                           class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-eye me-1"></i>
                                                            جزئیات
                                                        </a>
                                                        <button class="btn btn-sm btn-outline-info" 
                                                                onclick="showChecklist(<?php echo $request['id']; ?>)">
                                                            <i class="fas fa-clipboard-list me-1"></i>
                                                            چک‌لیست
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- تب ثبت درخواست پیشرفته -->
                    <div class="tab-pane fade <?php echo $current_tab === 'new' ? 'show active' : ''; ?>" 
                         id="new" role="tabpanel">
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-plus me-2"></i>
                                    ثبت درخواست بازدید پیشرفته
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="create_visit_enhanced">
                                    <?php echo csrf_field(); ?>
                                    
                                    <!-- اطلاعات اصلی -->
                                    <h6 class="text-primary mb-3">
                                        <i class="fas fa-info-circle me-2"></i>
                                        اطلاعات اصلی
                                    </h6>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-floating mb-3">
                                                <input type="text" class="form-control" id="company_name" name="company_name" 
                                                       required placeholder="نام شرکت">
                                                <label for="company_name">نام شرکت *</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-floating mb-3">
                                                <input type="text" class="form-control" id="contact_person" name="contact_person" 
                                                       required placeholder="نام تماس‌گیرنده">
                                                <label for="contact_person">نام تماس‌گیرنده *</label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-floating mb-3">
                                                <input type="tel" class="form-control" id="contact_phone" name="contact_phone" 
                                                       required placeholder="شماره تماس">
                                                <label for="contact_phone">شماره تماس *</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-floating mb-3">
                                                <input type="email" class="form-control" id="contact_email" name="contact_email" 
                                                       placeholder="ایمیل">
                                                <label for="contact_email">ایمیل</label>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- اطلاعات بازدید -->
                                    <h6 class="text-primary mb-3 mt-4">
                                        <i class="fas fa-calendar me-2"></i>
                                        اطلاعات بازدید
                                    </h6>
                                    
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-floating mb-3">
                                                <input type="number" class="form-control" id="visitor_count" name="visitor_count" 
                                                       value="1" min="1" max="20" placeholder="تعداد بازدیدکنندگان">
                                                <label for="visitor_count">تعداد بازدیدکنندگان</label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-floating mb-3">
                                                <select class="form-select" id="visit_purpose" name="visit_purpose" required>
                                                    <option value="">انتخاب کنید</option>
                                                    <option value="دیداری">دیداری</option>
                                                    <option value="تست">تست</option>
                                                    <option value="خرید">خرید</option>
                                                    <option value="بازرسی">بازرسی</option>
                                                    <option value="آموزش">آموزش</option>
                                                    <option value="سایر">سایر</option>
                                                </select>
                                                <label for="visit_purpose">هدف بازدید *</label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-floating mb-3">
                                                <select class="form-select" id="visit_type" name="visit_type" required>
                                                    <option value="">انتخاب کنید</option>
                                                    <option value="مشتری">مشتری</option>
                                                    <option value="ارگان">ارگان</option>
                                                    <option value="داخلی">داخلی</option>
                                                    <option value="تامین_کننده">تامین‌کننده</option>
                                                </select>
                                                <label for="visit_type">نوع بازدید *</label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-floating mb-3">
                                                <select class="form-select" id="request_method" name="request_method" required>
                                                    <option value="">انتخاب کنید</option>
                                                    <option value="تماس">تماس</option>
                                                    <option value="ایمیل">ایمیل</option>
                                                    <option value="حضوری">حضوری</option>
                                                    <option value="آنلاین">آنلاین</option>
                                                </select>
                                                <label for="request_method">روش درخواست *</label>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- نیازهای خاص -->
                                    <h6 class="text-primary mb-3 mt-4">
                                        <i class="fas fa-cogs me-2"></i>
                                        نیازهای خاص و تنظیمات
                                    </h6>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-floating mb-3">
                                                <input type="number" class="form-control" id="visit_duration" name="visit_duration" 
                                                       value="60" min="30" max="480" placeholder="مدت بازدید (دقیقه)">
                                                <label for="visit_duration">مدت بازدید (دقیقه)</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-floating mb-3">
                                                <select class="form-select" id="priority" name="priority" required>
                                                    <option value="متوسط">متوسط</option>
                                                    <option value="کم">کم</option>
                                                    <option value="بالا">بالا</option>
                                                    <option value="فوری">فوری</option>
                                                </select>
                                                <label for="priority">اولویت</label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-floating mb-3">
                                                <select class="form-select" id="host_preference" name="host_preference">
                                                    <option value="">انتخاب میزبان</option>
                                                    <?php foreach ($hosts as $host): ?>
                                                        <option value="<?php echo $host['id']; ?>">
                                                            <?php echo htmlspecialchars($host['full_name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <label for="host_preference">میزبان ترجیحی</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-floating mb-3">
                                                <select class="form-select" id="security_clearance" name="security_clearance">
                                                    <option value="">سطح امنیتی</option>
                                                    <option value="عادی">عادی</option>
                                                    <option value="محرمانه">محرمانه</option>
                                                    <option value="خیلی محرمانه">خیلی محرمانه</option>
                                                </select>
                                                <label for="security_clearance">سطح امنیتی</label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="equipment_requirements" class="form-label">نیازهای تجهیزاتی</label>
                                        <textarea class="form-control" id="equipment_requirements" name="equipment_requirements" 
                                                  rows="2" placeholder="تجهیزات مورد نیاز برای بازدید..."></textarea>
                                    </div>

                                    <!-- سرویس‌های اضافی -->
                                    <h6 class="text-primary mb-3 mt-4">
                                        <i class="fas fa-concierge-bell me-2"></i>
                                        سرویس‌های اضافی
                                    </h6>
                                    
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="requires_nda" name="requires_nda">
                                                <label class="form-check-label" for="requires_nda">
                                                    نیاز به امضای قرارداد محرمانگی (NDA)
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="catering_required" name="catering_required">
                                                <label class="form-check-label" for="catering_required">
                                                    پذیرایی
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="transportation_required" name="transportation_required">
                                                <label class="form-check-label" for="transportation_required">
                                                    حمل و نقل
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row mt-2">
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="accommodation_required" name="accommodation_required">
                                                <label class="form-check-label" for="accommodation_required">
                                                    اقامت
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3 mt-4">
                                        <label for="special_requirements" class="form-label">نیازهای خاص</label>
                                        <textarea class="form-control" id="special_requirements" name="special_requirements" 
                                                  rows="3" placeholder="نیازهای خاص بازدیدکننده..."></textarea>
                                    </div>

                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="fas fa-save me-2"></i>
                                            ثبت درخواست پیشرفته
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- تب تقویم -->
                    <div class="tab-pane fade <?php echo $current_tab === 'calendar' ? 'show active' : ''; ?>" 
                         id="calendar" role="tabpanel">
                        <div class="calendar-container mt-3">
                            <h5 class="mb-3">
                                <i class="fas fa-calendar me-2"></i>
                                تقویم بازدیدها
                            </h5>
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-calendar-alt fa-3x mb-3"></i>
                                <p>تقویم پیشرفته بازدیدها در نسخه‌های آینده اضافه خواهد شد</p>
                                <p class="small">امکانات: نمایش بازدیدها، رزرو دستگاه‌ها، مدیریت تقویم، drag & drop</p>
                            </div>
                        </div>
                    </div>

                    <!-- تب گزارش‌ها -->
                    <div class="tab-pane fade <?php echo $current_tab === 'reports' ? 'show active' : ''; ?>" 
                         id="reports" role="tabpanel">
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-chart-bar me-2"></i>
                                    گزارش‌ها و آمار
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="text-center text-muted py-5">
                                    <i class="fas fa-chart-line fa-3x mb-3"></i>
                                    <p>گزارش‌های پیشرفته در نسخه‌های آینده اضافه خواهد شد</p>
                                    <p class="small">امکانات: آمار بازدیدها، گزارش‌های مدیریتی، تحلیل‌های آماری</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/persian-date@1.1.0/dist/persian-date.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.jalali-date').persianDatepicker({
                format: 'YYYY/MM/DD',
                observer: true,
                timePicker: {
                    enabled: false
                }
            });
        });
        
        function showChecklist(visitId) {
            // نمایش چک‌لیست بازدید
            alert('چک‌لیست بازدید ID: ' + visitId + ' - در نسخه‌های آینده اضافه خواهد شد');
        }
    </script>
</body>
</html>