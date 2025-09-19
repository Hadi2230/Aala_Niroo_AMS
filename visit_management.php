<?php
require_once 'config.php';

if (!hasPermission('visit_management')) {
    die('دسترسی غیرمجاز');
}

$page_title = 'مدیریت بازدید کارخانه';

// ثبت لاگ مشاهده صفحه
logAction($pdo, 'VIEW_VISIT_MANAGEMENT', 'مشاهده صفحه مدیریت بازدید کارخانه');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_visit') {
    try {
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
            'priority' => sanitizeInput($_POST['priority'])
        ];
        
        $visit_id = createVisitRequest($pdo, $visit_data);
        $_SESSION['success_message'] = 'درخواست بازدید با موفقیت ثبت شد';
        
        // ثبت لاگ موفقیت
        logAction($pdo, 'ADD_VISIT_REQUEST', "افزودن درخواست بازدید جدید: {$visit_data['company_name']} (ID: $visit_id)", 'info', 'VISITS', [
            'visit_id' => $visit_id,
            'company_name' => $visit_data['company_name'],
            'contact_person' => $visit_data['contact_person']
        ]);
        
        redirect('visit_management.php');
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'خطا: ' . $e->getMessage();
        
        // ثبت لاگ خطا
        logAction($pdo, 'ADD_VISIT_REQUEST_ERROR', "خطا در افزودن درخواست بازدید: " . $e->getMessage(), 'error', 'VISITS', [
            'company_name' => $visit_data['company_name'] ?? '',
            'error' => $e->getMessage()
        ]);
    }
}

$filters = [
    'status' => $_GET['status'] ?? '',
    'visit_type' => $_GET['visit_type'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'company_name' => $_GET['company_name'] ?? ''
];

try {
    $visit_requests = getVisitRequests($pdo, $filters);
} catch (Exception $e) {
    $visit_requests = [];
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

                <!-- تب‌ها -->
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
                            ثبت درخواست جدید
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
                                    فیلترها
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
                                        <input type="text" class="form-control jalali-date" id="date_from" name="date_from" readonly 
                                               value="<?php echo $filters['date_from']; ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label for="date_to" class="form-label">تا تاریخ</label>
                                        <input type="text" class="form-control jalali-date" id="date_to" name="date_to" readonly 
                                               value="<?php echo $filters['date_to']; ?>">
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
                                        <a href="visit_management.php" class="btn btn-outline-secondary">
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
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- تب ثبت درخواست جدید -->
                    <div class="tab-pane fade <?php echo $current_tab === 'new' ? 'show active' : ''; ?>" 
                         id="new" role="tabpanel">
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-plus me-2"></i>
                                    ثبت درخواست بازدید جدید
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="create_visit">
                                    <?php echo csrf_field(); ?>
                                    
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

                                    <div class="mb-3">
                                        <label for="preferred_dates" class="form-label">تاریخ‌های پیشنهادی</label>
                                        <div id="preferred_dates_container">
                                            <div class="row mb-2">
                                                <div class="col-md-6">
                                                    <input type="text" class="form-control jalali-date" name="preferred_date_1" required readonly>
                                                </div>
                                                <div class="col-md-4">
                                                    <input type="time" class="form-control" name="preferred_time_1" required>
                                                </div>
                                                <div class="col-md-2">
                                                    <button type="button" class="btn btn-outline-danger" onclick="removeDate(this)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="addDate()">
                                            <i class="fas fa-plus me-1"></i>
                                            افزودن تاریخ
                                        </button>
                                    </div>

                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="requires_nda" name="requires_nda">
                                            <label class="form-check-label" for="requires_nda">
                                                نیاز به امضای قرارداد محرمانگی (NDA)
                                            </label>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="special_requirements" class="form-label">نیازهای خاص</label>
                                        <textarea class="form-control" id="special_requirements" name="special_requirements" 
                                                  rows="3" placeholder="نیازهای خاص بازدیدکننده..."></textarea>
                                    </div>

                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="fas fa-save me-2"></i>
                                            ثبت درخواست
                                        </button>
                                    </div>
                                </form>
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
            altField: '.jalali-date-alt',
            altFormat: 'YYYY/MM/DD',
            observer: true,
            timePicker: {
                enabled: false
            }
        });
    });
    </script>
    <script>
        function addDate() {
            const container = document.getElementById('preferred_dates_container');
            const count = container.children.length + 1;
            
            const div = document.createElement('div');
            div.className = 'row mb-2';
            div.innerHTML = `
                <div class="col-md-6">
                    <input type="text" class="form-control jalali-date" name="preferred_date_${count}" required readonly>
                </div>
                <div class="col-md-4">
                    <input type="time" class="form-control" name="preferred_time_${count}" required>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-outline-danger" onclick="removeDate(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
            
            container.appendChild(div);
        }
        
        function removeDate(button) {
            if (document.getElementById('preferred_dates_container').children.length > 1) {
                button.closest('.row').remove();
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const dateInputs = document.querySelectorAll('input[name^="preferred_date_"]');
            const timeInputs = document.querySelectorAll('input[name^="preferred_time_"]');
            
            dateInputs.forEach((dateInput, index) => {
                dateInput.addEventListener('change', updatePreferredDates);
            });
            
            timeInputs.forEach((timeInput, index) => {
                timeInput.addEventListener('change', updatePreferredDates);
            });
            
            function updatePreferredDates() {
                const dates = [];
                dateInputs.forEach((dateInput, index) => {
                    if (dateInput.value && timeInputs[index] && timeInputs[index].value) {
                        dates.push({
                            date: dateInput.value,
                            time: timeInputs[index].value
                        });
                    }
                });
                
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'preferred_dates';
                hiddenInput.value = JSON.stringify(dates);
                
                const existingInput = document.querySelector('input[name="preferred_dates"]');
                if (existingInput) {
                    existingInput.remove();
                }
                
                document.querySelector('form').appendChild(hiddenInput);
            }
        });
    </script>
</body>
</html>