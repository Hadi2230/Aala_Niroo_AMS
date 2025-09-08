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
            case 'create_ticket':
                $customer_id = sanitizeInput($_POST['customer_id']);
                $asset_id = sanitizeInput($_POST['asset_id']);
                $title = sanitizeInput($_POST['title']);
                $description = sanitizeInput($_POST['description']);
                $priority = sanitizeInput($_POST['priority']);
                
                if ($customer_id && $title && $description) {
                    $ticket_id = createTicket($pdo, $customer_id, $asset_id, $title, $description, $priority, $_SESSION['user_id']);
                    logAction($pdo, 'create_ticket', "تیکت جدید با شماره {$ticket_id} ایجاد شد");
                    $success_message = "تیکت با موفقیت ایجاد شد";
                } else {
                    $error_message = "لطفاً تمام فیلدهای ضروری را پر کنید";
                }
                break;
                
            case 'update_status':
                $ticket_id = sanitizeInput($_POST['ticket_id']);
                $new_status = sanitizeInput($_POST['new_status']);
                $reason = sanitizeInput($_POST['reason']);
                
                if (updateTicketStatus($pdo, $ticket_id, $new_status, $_SESSION['user_id'], $reason)) {
                    logAction($pdo, 'update_ticket_status', "وضعیت تیکت {$ticket_id} به {$new_status} تغییر یافت");
                    $success_message = "وضعیت تیکت با موفقیت به‌روزرسانی شد";
                } else {
                    $error_message = "خطا در به‌روزرسانی وضعیت تیکت";
                }
                break;
                
            case 'assign_ticket':
                $ticket_id = sanitizeInput($_POST['ticket_id']);
                $assigned_to = sanitizeInput($_POST['assigned_to']);
                
                if (assignTicket($pdo, $ticket_id, $assigned_to, $_SESSION['user_id'])) {
                    logAction($pdo, 'assign_ticket', "تیکت {$ticket_id} به کاربر {$assigned_to} تخصیص یافت");
                    $success_message = "تیکت با موفقیت تخصیص یافت";
                } else {
                    $error_message = "خطا در تخصیص تیکت";
                }
                break;
        }
    }
}

// دریافت تیکت‌ها
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$priority_filter = isset($_GET['priority']) ? sanitizeInput($_GET['priority']) : '';

$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(t.title LIKE ? OR t.ticket_number LIKE ? OR c.full_name LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($status_filter) {
    $where_conditions[] = "t.status = ?";
    $params[] = $status_filter;
}

if ($priority_filter) {
    $where_conditions[] = "t.priority = ?";
    $params[] = $priority_filter;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$tickets_query = "
    SELECT t.*, c.full_name as customer_name, c.phone as customer_phone, 
           a.name as asset_name, u.full_name as assigned_user_name
    FROM tickets t
    LEFT JOIN customers c ON t.customer_id = c.id
    LEFT JOIN assets a ON t.asset_id = a.id
    LEFT JOIN users u ON t.assigned_to = u.id
    {$where_clause}
    ORDER BY t.created_at DESC
";

$stmt = $pdo->prepare($tickets_query);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

// دریافت مشتریان و دارایی‌ها برای فرم
$customers = $pdo->query("SELECT id, full_name, phone FROM customers ORDER BY full_name")->fetchAll();
$assets = $pdo->query("SELECT id, name, serial_number FROM assets WHERE status = 'فعال' ORDER BY name")->fetchAll();
$users = $pdo->query("SELECT id, full_name, role FROM users WHERE is_active = true ORDER BY full_name")->fetchAll();
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت تیکت‌ها - سامانه مدیریت اعلا نیرو</title>
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
                        <a class="nav-link active" href="tickets.php">تیکت‌ها</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="maintenance.php">تعمیرات دوره‌ای</a>
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
                    <h2><i class="fas fa-ticket-alt"></i> مدیریت تیکت‌ها</h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTicketModal">
                        <i class="fas fa-plus"></i> تیکت جدید
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

                <!-- فیلترها -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <input type="text" class="form-control" name="search" placeholder="جستجو در تیکت‌ها..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="status">
                                    <option value="">همه وضعیت‌ها</option>
                                    <option value="جدید" <?php echo $status_filter === 'جدید' ? 'selected' : ''; ?>>جدید</option>
                                    <option value="در انتظار" <?php echo $status_filter === 'در انتظار' ? 'selected' : ''; ?>>در انتظار</option>
                                    <option value="در حال بررسی" <?php echo $status_filter === 'در حال بررسی' ? 'selected' : ''; ?>>در حال بررسی</option>
                                    <option value="در انتظار قطعه" <?php echo $status_filter === 'در انتظار قطعه' ? 'selected' : ''; ?>>در انتظار قطعه</option>
                                    <option value="تکمیل شده" <?php echo $status_filter === 'تکمیل شده' ? 'selected' : ''; ?>>تکمیل شده</option>
                                    <option value="لغو شده" <?php echo $status_filter === 'لغو شده' ? 'selected' : ''; ?>>لغو شده</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="priority">
                                    <option value="">همه اولویت‌ها</option>
                                    <option value="کم" <?php echo $priority_filter === 'کم' ? 'selected' : ''; ?>>کم</option>
                                    <option value="متوسط" <?php echo $priority_filter === 'متوسط' ? 'selected' : ''; ?>>متوسط</option>
                                    <option value="بالا" <?php echo $priority_filter === 'بالا' ? 'selected' : ''; ?>>بالا</option>
                                    <option value="فوری" <?php echo $priority_filter === 'فوری' ? 'selected' : ''; ?>>فوری</option>
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

                <!-- جدول تیکت‌ها -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>شماره تیکت</th>
                                        <th>مشتری</th>
                                        <th>عنوان</th>
                                        <th>اولویت</th>
                                        <th>وضعیت</th>
                                        <th>تخصیص یافته به</th>
                                        <th>تاریخ ایجاد</th>
                                        <th>عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($tickets)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">هیچ تیکتی یافت نشد</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($tickets as $ticket): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($ticket['ticket_number']); ?></strong>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($ticket['customer_name']); ?></strong><br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($ticket['customer_phone']); ?></small>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($ticket['title']); ?></td>
                                                <td>
                                                    <?php
                                                    $priority_colors = [
                                                        'کم' => 'success',
                                                        'متوسط' => 'warning',
                                                        'بالا' => 'danger',
                                                        'فوری' => 'dark'
                                                    ];
                                                    $color = $priority_colors[$ticket['priority']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $color; ?>"><?php echo $ticket['priority']; ?></span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $status_colors = [
                                                        'جدید' => 'primary',
                                                        'در انتظار' => 'warning',
                                                        'در حال بررسی' => 'info',
                                                        'در انتظار قطعه' => 'secondary',
                                                        'تکمیل شده' => 'success',
                                                        'لغو شده' => 'danger'
                                                    ];
                                                    $color = $status_colors[$ticket['status']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $color; ?>"><?php echo $ticket['status']; ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($ticket['assigned_user_name']): ?>
                                                        <?php echo htmlspecialchars($ticket['assigned_user_name']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">تخصیص نیافته</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo jalaliDate($ticket['created_at']); ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button class="btn btn-sm btn-outline-primary" onclick="viewTicket(<?php echo $ticket['id']; ?>)">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-warning" onclick="editTicket(<?php echo $ticket['id']; ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-info" onclick="assignTicket(<?php echo $ticket['id']; ?>)">
                                                            <i class="fas fa-user-plus"></i>
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

    <!-- مودال ایجاد تیکت -->
    <div class="modal fade" id="createTicketModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">ایجاد تیکت جدید</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_ticket">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">مشتری *</label>
                                <select class="form-select" name="customer_id" required>
                                    <option value="">انتخاب مشتری</option>
                                    <?php foreach ($customers as $customer): ?>
                                        <option value="<?php echo $customer['id']; ?>">
                                            <?php echo htmlspecialchars($customer['full_name'] . ' - ' . $customer['phone']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">دستگاه (اختیاری)</label>
                                <select class="form-select" name="asset_id">
                                    <option value="">انتخاب دستگاه</option>
                                    <?php foreach ($assets as $asset): ?>
                                        <option value="<?php echo $asset['id']; ?>">
                                            <?php echo htmlspecialchars($asset['name'] . ' - ' . $asset['serial_number']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">عنوان تیکت *</label>
                            <input type="text" class="form-control" name="title" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">توضیحات *</label>
                            <textarea class="form-control" name="description" rows="4" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">اولویت</label>
                            <select class="form-select" name="priority">
                                <option value="متوسط">متوسط</option>
                                <option value="کم">کم</option>
                                <option value="بالا">بالا</option>
                                <option value="فوری">فوری</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" class="btn btn-primary">ایجاد تیکت</button>
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
        
        function viewTicket(ticketId) {
            // پیاده‌سازی مشاهده جزئیات تیکت
            alert('مشاهده تیکت: ' + ticketId);
        }
        
        function editTicket(ticketId) {
            // پیاده‌سازی ویرایش تیکت
            alert('ویرایش تیکت: ' + ticketId);
        }
        
        function assignTicket(ticketId) {
            // پیاده‌سازی تخصیص تیکت
            alert('تخصیص تیکت: ' + ticketId);
        }
    </script>
</body>
</html>