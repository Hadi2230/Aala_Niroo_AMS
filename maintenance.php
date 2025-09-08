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
                $schedule_date = sanitizeInput($_POST['schedule_date']);
                $interval_days = sanitizeInput($_POST['interval_days']);
                $maintenance_type = sanitizeInput($_POST['maintenance_type']);
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

// دریافت تعمیرات
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

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$maintenance_query = "
    SELECT ms.*, a.name as asset_name, a.serial_number, c.full_name as customer_name, 
           u.full_name as assigned_user_name, aa.assignment_date
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
$maintenances = $stmt->fetchAll();

// دریافت دارایی‌ها و کاربران برای فرم
$assets = $pdo->query("SELECT id, name, serial_number FROM assets WHERE status = 'فعال' ORDER BY name")->fetchAll();
$assignments = $pdo->query("
    SELECT aa.id, a.name as asset_name, c.full_name as customer_name 
    FROM asset_assignments aa
    LEFT JOIN assets a ON aa.asset_id = a.id
    LEFT JOIN customers c ON aa.customer_id = c.id
    WHERE aa.assignment_status = 'فعال'
    ORDER BY aa.assignment_date DESC
")->fetchAll();
$users = $pdo->query("SELECT id, full_name, role FROM users WHERE is_active = true ORDER BY full_name")->fetchAll();

// بررسی تعمیرات نزدیک
$upcoming_maintenance = checkUpcomingMaintenance($pdo, 7);
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعمیرات دوره‌ای - سامانه مدیریت اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">اعلا نیرو</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">داشبورد</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="assets.php">مدیریت دارایی‌ها</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="customers.php">مدیریت مشتریان</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="assignments.php">انتساب دستگاه</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="tickets.php">تیکت‌ها</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="maintenance.php">تعمیرات دوره‌ای</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">گزارش‌ها</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-bell"></i>
                            <?php if (count($unread_notifications) > 0): ?>
                                <span class="badge bg-danger"><?php echo count($unread_notifications); ?></span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu">
                            <?php if (empty($unread_notifications)): ?>
                                <li><span class="dropdown-item-text">اعلان جدیدی وجود ندارد</span></li>
                            <?php else: ?>
                                <?php foreach ($unread_notifications as $notification): ?>
                                    <li>
                                        <a class="dropdown-item" href="#" onclick="markAsRead(<?php echo $notification['id']; ?>)">
                                            <strong><?php echo htmlspecialchars($notification['title']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($notification['message']); ?></small>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">خروج</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-wrench"></i> تعمیرات دوره‌ای</h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createMaintenanceModal">
                        <i class="fas fa-plus"></i> برنامه تعمیرات جدید
                    </button>
                </div>

                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- هشدار تعمیرات نزدیک -->
                <?php if (!empty($upcoming_maintenance)): ?>
                    <div class="alert alert-warning">
                        <h5><i class="fas fa-exclamation-triangle"></i> تعمیرات نزدیک</h5>
                        <p>تعداد <?php echo count($upcoming_maintenance); ?> تعمیرات در 7 روز آینده برنامه‌ریزی شده است.</p>
                        <ul class="mb-0">
                            <?php foreach (array_slice($upcoming_maintenance, 0, 3) as $maintenance): ?>
                                <li>
                                    <strong><?php echo htmlspecialchars($maintenance['asset_name']); ?></strong> - 
                                    <?php echo htmlspecialchars($maintenance['customer_name']); ?> - 
                                    <?php echo jalaliDate($maintenance['schedule_date']); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
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
                                <button type="submit" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-search"></i> جستجو
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- جدول تعمیرات -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>دستگاه</th>
                                        <th>مشتری</th>
                                        <th>نوع تعمیرات</th>
                                        <th>تاریخ برنامه‌ریزی</th>
                                        <th>وضعیت</th>
                                        <th>تخصیص یافته به</th>
                                        <th>عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($maintenances)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted">هیچ برنامه تعمیراتی یافت نشد</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($maintenances as $maintenance): ?>
                                            <tr>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($maintenance['asset_name']); ?></strong><br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($maintenance['serial_number']); ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($maintenance['customer_name']): ?>
                                                        <?php echo htmlspecialchars($maintenance['customer_name']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">نامشخص</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo $maintenance['maintenance_type']; ?></span>
                                                </td>
                                                <td>
                                                    <?php echo jalaliDate($maintenance['schedule_date']); ?>
                                                    <?php if (strtotime($maintenance['schedule_date']) < time() && $maintenance['status'] !== 'تکمیل شده'): ?>
                                                        <br><small class="text-danger">تأخیر</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $status_colors = [
                                                        'برنامه‌ریزی شده' => 'primary',
                                                        'در انتظار' => 'warning',
                                                        'در حال انجام' => 'info',
                                                        'تکمیل شده' => 'success',
                                                        'لغو شده' => 'danger'
                                                    ];
                                                    $color = $status_colors[$maintenance['status']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $color; ?>"><?php echo $maintenance['status']; ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($maintenance['assigned_user_name']): ?>
                                                        <?php echo htmlspecialchars($maintenance['assigned_user_name']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">تخصیص نیافته</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button class="btn btn-sm btn-outline-primary" onclick="viewMaintenance(<?php echo $maintenance['id']; ?>)">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-warning" onclick="editMaintenance(<?php echo $maintenance['id']; ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-info" onclick="updateStatus(<?php echo $maintenance['id']; ?>)">
                                                            <i class="fas fa-cog"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- مودال ایجاد برنامه تعمیرات -->
    <div class="modal fade" id="createMaintenanceModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">ایجاد برنامه تعمیرات جدید</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_maintenance">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">دستگاه *</label>
                                <select class="form-select" name="asset_id" required onchange="loadAssignments(this.value)">
                                    <option value="">انتخاب دستگاه</option>
                                    <?php foreach ($assets as $asset): ?>
                                        <option value="<?php echo $asset['id']; ?>">
                                            <?php echo htmlspecialchars($asset['name'] . ' - ' . $asset['serial_number']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">انتساب (اختیاری)</label>
                                <select class="form-select" name="assignment_id" id="assignment_select">
                                    <option value="">انتخاب انتساب</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">تاریخ برنامه‌ریزی *</label>
                                <input type="date" class="form-control" name="schedule_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">نوع تعمیرات</label>
                                <select class="form-select" name="maintenance_type">
                                    <option value="تعمیر دوره‌ای">تعمیر دوره‌ای</option>
                                    <option value="سرویس">سرویس</option>
                                    <option value="بازرسی">بازرسی</option>
                                    <option value="کالیبراسیون">کالیبراسیون</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">فاصله زمانی (روز)</label>
                                <input type="number" class="form-control" name="interval_days" value="90" min="1">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">تخصیص یافته به</label>
                                <select class="form-select" name="assigned_to">
                                    <option value="">انتخاب کارمند</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user['id']; ?>">
                                            <?php echo htmlspecialchars($user['full_name'] . ' - ' . $user['role']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">یادداشت‌ها</label>
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function markAsRead(notificationId) {
            fetch('mark_notification_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'notification_id=' + notificationId
            }).then(() => {
                location.reload();
            });
        }
        
        function loadAssignments(assetId) {
            // بارگذاری انتساب‌های مربوط به دستگاه انتخاب شده
            const assignmentSelect = document.getElementById('assignment_select');
            assignmentSelect.innerHTML = '<option value="">در حال بارگذاری...</option>';
            
            // اینجا می‌توانید AJAX call برای بارگذاری انتساب‌ها اضافه کنید
            assignmentSelect.innerHTML = '<option value="">انتخاب انتساب</option>';
        }
        
        function viewMaintenance(maintenanceId) {
            // پیاده‌سازی مشاهده جزئیات تعمیرات
            alert('مشاهده تعمیرات: ' + maintenanceId);
        }
        
        function editMaintenance(maintenanceId) {
            // پیاده‌سازی ویرایش تعمیرات
            alert('ویرایش تعمیرات: ' + maintenanceId);
        }
        
        function updateStatus(maintenanceId) {
            // پیاده‌سازی تغییر وضعیت
            alert('تغییر وضعیت تعمیرات: ' + maintenanceId);
        }
    </script>
</body>
</html>