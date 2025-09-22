<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// بررسی دسترسی
checkPermission('کاربر عادی');

// دریافت اعلان‌های خوانده نشده
$unread_notifications = getUnreadNotifications($pdo, $_SESSION['user_id']);
$unread_messages = getUnreadMessages($pdo, $_SESSION['user_id']);

// پردازش فرم‌ها
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_maintenance':
                $asset_id = sanitizeInput($_POST['asset_id']);
                $assignment_id = sanitizeInput($_POST['assignment_id']);
                $maintenance_type = sanitizeInput($_POST['maintenance_type']);
                $schedule_date = jalaliToGregorianForDB(sanitizeInput($_POST['schedule_date']));
                $interval_days = sanitizeInput($_POST['interval_days']);
                $assigned_to = sanitizeInput($_POST['assigned_to']);
                $notes = sanitizeInput($_POST['notes']);
                
                if ($asset_id && $schedule_date) {
                    $maintenance_id = createMaintenanceSchedule($pdo, $asset_id, $assignment_id, $schedule_date, $interval_days, $maintenance_type, $assigned_to);
                    logAction($pdo, 'create_maintenance', "برنامه تعمیرات جدید با شناسه {$maintenance_id} ایجاد شد");
                    $success_message = "برنامه تعمیرات با موفقیت ایجاد شد";
                } else {
                    $error_message = "لطفاً تمام فیلدهای ضروری را پر کنید";
                }
                break;
                
            case 'update_status':
                $maintenance_id = sanitizeInput($_POST['maintenance_id']);
                $new_status = sanitizeInput($_POST['new_status']);
                $notes = sanitizeInput($_POST['notes']);
                
                $stmt = $pdo->prepare("UPDATE maintenance_schedules SET status = ?, notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                if ($stmt->execute([$new_status, $notes, $maintenance_id])) {
                    logAction($pdo, 'update_maintenance_status', "وضعیت تعمیرات {$maintenance_id} به {$new_status} تغییر یافت");
                    $success_message = "وضعیت تعمیرات با موفقیت به‌روزرسانی شد";
                } else {
                    $error_message = "خطا در به‌روزرسانی وضعیت تعمیرات";
                }
                break;
        }
    }
}

// دریافت برنامه‌های تعمیرات
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$type_filter = isset($_GET['type']) ? sanitizeInput($_GET['type']) : '';

$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(a.name LIKE ? OR c.full_name LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($status_filter) {
    $where_conditions[] = "ms.status = ?";
    $params[] = $status_filter;
}

if ($type_filter) {
    $where_conditions[] = "ms.maintenance_type = ?";
    $params[] = $type_filter;
}

$where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";

$maintenance_query = "
    SELECT ms.*, a.name as asset_name, c.full_name as customer_name, u.full_name as assigned_user
    FROM maintenance_schedules ms
    LEFT JOIN assets a ON ms.asset_id = a.id
    LEFT JOIN asset_assignments aa ON ms.assignment_id = aa.id
    LEFT JOIN customers c ON aa.customer_id = c.id
    LEFT JOIN users u ON ms.assigned_to = u.id
    {$where_clause}
    ORDER BY ms.schedule_date ASC
";

$stmt = $pdo->prepare($maintenance_query);
$stmt->execute($params);
$maintenance_schedules = $stmt->fetchAll();

// دریافت دارایی‌ها و انتساب‌ها برای فرم
$assets = $pdo->query("SELECT id, name FROM assets ORDER BY name")->fetchAll();
$assignments = $pdo->query("
    SELECT aa.id, a.name as asset_name, c.full_name as customer_name 
    FROM asset_assignments aa
    LEFT JOIN assets a ON aa.asset_id = a.id
    LEFT JOIN customers c ON aa.customer_id = c.id
    WHERE aa.assignment_status = 'فعال'
    ORDER BY a.name
")->fetchAll();
$users = $pdo->query("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name")->fetchAll();
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت تعمیرات دوره‌ای - شرکت اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <style>
        body { font-family: Vazirmatn, sans-serif; background-color: #f8f9fa; padding-top: 80px; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .card-header { background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); color: white; border-radius: 12px 12px 0 0 !important; font-weight: 600; }
        .btn-primary { background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); border: none; }
        .status-badge { font-size: 0.75rem; padding: 2px 6px; }
        .type-badge { font-size: 0.75rem; padding: 2px 6px; }
        .maintenance-item { border-left: 4px solid #3498db; }
        .maintenance-item.overdue { border-left-color: #e74c3c; }
        .maintenance-item.upcoming { border-left-color: #f39c12; }
        .maintenance-item.completed { border-left-color: #27ae60; }
        .maintenance-item.planned { border-left-color: #3498db; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fa fa-tools"></i> مدیریت تعمیرات دوره‌ای</h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createMaintenanceModal">
                        <i class="fa fa-plus"></i> برنامه جدید
                    </button>
                </div>

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

                <!-- فیلترها -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <input type="text" class="form-control" name="search" placeholder="جستجو در تعمیرات..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="status">
                                    <option value="">همه وضعیت‌ها</option>
                                    <option value="برنامه‌ریزی شده" <?php echo $status_filter === 'برنامه‌ریزی شده' ? 'selected' : ''; ?>>برنامه‌ریزی شده</option>
                                    <option value="در انتظار" <?php echo $status_filter === 'در انتظار' ? 'selected' : ''; ?>>در انتظار</option>
                                    <option value="در حال انجام" <?php echo $status_filter === 'در حال انجام' ? 'selected' : ''; ?>>در حال انجام</option>
                                    <option value="تکمیل شده" <?php echo $status_filter === 'تکمیل شده' ? 'selected' : ''; ?>>تکمیل شده</option>
                                    <option value="لغو شده" <?php echo $status_filter === 'لغو شده' ? 'selected' : ''; ?>>لغو شده</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="type">
                                    <option value="">همه انواع</option>
                                    <option value="تعمیر دوره‌ای" <?php echo $type_filter === 'تعمیر دوره‌ای' ? 'selected' : ''; ?>>تعمیر دوره‌ای</option>
                                    <option value="سرویس" <?php echo $type_filter === 'سرویس' ? 'selected' : ''; ?>>سرویس</option>
                                    <option value="بازرسی" <?php echo $type_filter === 'بازرسی' ? 'selected' : ''; ?>>بازرسی</option>
                                    <option value="کالیبراسیون" <?php echo $type_filter === 'کالیبراسیون' ? 'selected' : ''; ?>>کالیبراسیون</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-outline-primary w-100">فیلتر</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- لیست برنامه‌های تعمیرات -->
                <div class="card">
                    <div class="card-header">
                        <i class="fa fa-list"></i> لیست برنامه‌های تعمیرات (<?php echo count($maintenance_schedules); ?> مورد)
                    </div>
                    <div class="card-body">
                        <?php if ($maintenance_schedules): ?>
                            <div class="list-group">
                                <?php foreach ($maintenance_schedules as $maintenance): ?>
                                    <?php
                                    $schedule_date = new DateTime($maintenance['schedule_date']);
                                    $today = new DateTime();
                                    $diff = $today->diff($schedule_date);
                                    $days_diff = $diff->days;
                                    
                                    $item_class = 'maintenance-item';
                                    if ($maintenance['status'] === 'تکمیل شده') {
                                        $item_class .= ' completed';
                                    } elseif ($schedule_date < $today && $maintenance['status'] === 'برنامه‌ریزی شده') {
                                        $item_class .= ' overdue';
                                    } elseif ($days_diff <= 7 && $maintenance['status'] === 'برنامه‌ریزی شده') {
                                        $item_class .= ' upcoming';
                                    } else {
                                        $item_class .= ' planned';
                                    }
                                    ?>
                                    <div class="list-group-item <?php echo $item_class; ?>">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($maintenance['asset_name']); ?></h6>
                                                    <div>
                                                        <span class="badge type-badge bg-info">
                                                            <?php echo $maintenance['maintenance_type']; ?>
                                                        </span>
                                                        <span class="badge status-badge bg-<?php echo $maintenance['status'] === 'تکمیل شده' ? 'success' : ($maintenance['status'] === 'در حال انجام' ? 'warning' : 'primary'); ?>">
                                                            <?php echo $maintenance['status']; ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <small class="text-muted">
                                                            <i class="fa fa-building"></i> مشتری: <?php echo htmlspecialchars($maintenance['customer_name'] ?? 'نامشخص'); ?><br>
                                                            <i class="fa fa-user-tie"></i> تخصیص یافته به: <?php echo htmlspecialchars($maintenance['assigned_user'] ?? 'تخصیص نیافته'); ?><br>
                                                            <i class="fa fa-calendar"></i> تاریخ برنامه: <?php echo jalaliDate($maintenance['schedule_date']); ?>
                                                        </small>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <small class="text-muted">
                                                            <i class="fa fa-clock"></i> فاصله: هر <?php echo $maintenance['interval_days']; ?> روز<br>
                                                            <i class="fa fa-calendar-plus"></i> تاریخ ایجاد: <?php echo jalaliDate($maintenance['created_at']); ?><br>
                                                            <?php if ($maintenance['completed_at']): ?>
                                                                <i class="fa fa-check-circle"></i> تکمیل شده: <?php echo jalaliDate($maintenance['completed_at']); ?>
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                </div>
                                                <?php if ($maintenance['notes']): ?>
                                                    <div class="mt-2">
                                                        <small class="text-muted">
                                                            <i class="fa fa-sticky-note"></i> یادداشت: <?php echo htmlspecialchars($maintenance['notes']); ?>
                                                        </small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="ms-3">
                                                <div class="btn-group-vertical" role="group">
                                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#updateStatusModal" 
                                                            data-maintenance-id="<?php echo $maintenance['id']; ?>" data-current-status="<?php echo $maintenance['status']; ?>">
                                                        <i class="fa fa-edit"></i> تغییر وضعیت
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fa fa-tools fa-3x text-muted mb-3"></i>
                                <p class="text-muted">هیچ برنامه تعمیراتی یافت نشد.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal ایجاد برنامه تعمیرات جدید -->
    <div class="modal fade" id="createMaintenanceModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form class="modal-content" method="post">
                <div class="modal-header">
                    <h5 class="modal-title">ایجاد برنامه تعمیرات جدید</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_maintenance">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">دارایی *</label>
                            <select class="form-select" name="asset_id" required>
                                <option value="">انتخاب دارایی</option>
                                <?php foreach ($assets as $asset): ?>
                                    <option value="<?php echo $asset['id']; ?>"><?php echo htmlspecialchars($asset['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">انتساب</label>
                            <select class="form-select" name="assignment_id">
                                <option value="">انتخاب انتساب (اختیاری)</option>
                                <?php foreach ($assignments as $assignment): ?>
                                    <option value="<?php echo $assignment['id']; ?>"><?php echo htmlspecialchars($assignment['asset_name'] . ' - ' . $assignment['customer_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">نوع تعمیرات *</label>
                            <select class="form-select" name="maintenance_type" required>
                                <option value="تعمیر دوره‌ای">تعمیر دوره‌ای</option>
                                <option value="سرویس">سرویس</option>
                                <option value="بازرسی">بازرسی</option>
                                <option value="کالیبراسیون">کالیبراسیون</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">تاریخ برنامه‌ریزی *</label>
                            <input type="text" class="form-control jalali-date" name="schedule_date" required readonly>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">فاصله تکرار (روز)</label>
                            <input type="number" class="form-control" name="interval_days" value="90" min="1">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">تخصیص به کاربر</label>
                            <select class="form-select" name="assigned_to">
                                <option value="">انتخاب کاربر (اختیاری)</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['full_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">یادداشت</label>
                        <textarea class="form-control" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" class="btn btn-primary">ایجاد برنامه</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal تغییر وضعیت -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" method="post">
                <div class="modal-header">
                    <h5 class="modal-title">تغییر وضعیت تعمیرات</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="maintenance_id" id="updateMaintenanceId">
                    
                    <div class="mb-3">
                        <label class="form-label">وضعیت جدید</label>
                        <select class="form-select" name="new_status" required>
                            <option value="برنامه‌ریزی شده">برنامه‌ریزی شده</option>
                            <option value="در انتظار">در انتظار</option>
                            <option value="در حال انجام">در حال انجام</option>
                            <option value="تکمیل شده">تکمیل شده</option>
                            <option value="لغو شده">لغو شده</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">یادداشت</label>
                        <textarea class="form-control" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" class="btn btn-primary">به‌روزرسانی</button>
                </div>
            </form>
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
        // تنظیم مقادیر در modal تغییر وضعیت
        document.getElementById('updateStatusModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const maintenanceId = button.getAttribute('data-maintenance-id');
            const currentStatus = button.getAttribute('data-current-status');
            
            document.getElementById('updateMaintenanceId').value = maintenanceId;
            document.querySelector('select[name="new_status"]').value = currentStatus;
        });
    </script>
</body>
</html>