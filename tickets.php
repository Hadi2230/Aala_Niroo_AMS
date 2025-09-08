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

$where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";

$tickets_query = "
    SELECT t.*, c.full_name as customer_name, a.name as asset_name, u.full_name as assigned_user, u2.full_name as created_by_name
    FROM tickets t
    LEFT JOIN customers c ON t.customer_id = c.id
    LEFT JOIN assets a ON t.asset_id = a.id
    LEFT JOIN users u ON t.assigned_to = u.id
    LEFT JOIN users u2 ON t.created_by = u2.id
    {$where_clause}
    ORDER BY t.created_at DESC
";

$stmt = $pdo->prepare($tickets_query);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

// دریافت مشتریان و دارایی‌ها برای فرم
$customers = $pdo->query("SELECT id, full_name FROM customers ORDER BY full_name")->fetchAll();
$assets = $pdo->query("SELECT id, name FROM assets ORDER BY name")->fetchAll();
$users = $pdo->query("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name")->fetchAll();
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت تیکت‌ها - شرکت اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <style>
        body { font-family: Vazirmatn, sans-serif; background-color: #f8f9fa; padding-top: 80px; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .card-header { background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); color: white; border-radius: 12px 12px 0 0 !important; font-weight: 600; }
        .btn-primary { background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); border: none; }
        .priority-badge { font-size: 0.75rem; padding: 2px 6px; }
        .status-badge { font-size: 0.75rem; padding: 2px 6px; }
        .ticket-item { border-left: 4px solid #3498db; }
        .ticket-item.urgent { border-left-color: #e74c3c; }
        .ticket-item.high { border-left-color: #f39c12; }
        .ticket-item.medium { border-left-color: #3498db; }
        .ticket-item.low { border-left-color: #95a5a6; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fa fa-ticket-alt"></i> مدیریت تیکت‌ها</h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTicketModal">
                        <i class="fa fa-plus"></i> تیکت جدید
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
                                    <option value="فوری" <?php echo $priority_filter === 'فوری' ? 'selected' : ''; ?>>فوری</option>
                                    <option value="بالا" <?php echo $priority_filter === 'بالا' ? 'selected' : ''; ?>>بالا</option>
                                    <option value="متوسط" <?php echo $priority_filter === 'متوسط' ? 'selected' : ''; ?>>متوسط</option>
                                    <option value="کم" <?php echo $priority_filter === 'کم' ? 'selected' : ''; ?>>کم</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-outline-primary w-100">فیلتر</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- لیست تیکت‌ها -->
                <div class="card">
                    <div class="card-header">
                        <i class="fa fa-list"></i> لیست تیکت‌ها (<?php echo count($tickets); ?> مورد)
                    </div>
                    <div class="card-body">
                        <?php if ($tickets): ?>
                            <div class="list-group">
                                <?php foreach ($tickets as $ticket): ?>
                                    <div class="list-group-item ticket-item <?php echo strtolower($ticket['priority']); ?>">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($ticket['title']); ?></h6>
                                                    <div>
                                                        <span class="badge priority-badge bg-<?php echo $ticket['priority'] === 'فوری' ? 'danger' : ($ticket['priority'] === 'بالا' ? 'warning' : ($ticket['priority'] === 'متوسط' ? 'info' : 'secondary')); ?>">
                                                            <?php echo $ticket['priority']; ?>
                                                        </span>
                                                        <span class="badge status-badge bg-<?php echo $ticket['status'] === 'جدید' ? 'primary' : ($ticket['status'] === 'تکمیل شده' ? 'success' : 'secondary'); ?>">
                                                            <?php echo $ticket['status']; ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <p class="text-muted mb-2"><?php echo htmlspecialchars($ticket['description']); ?></p>
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <small class="text-muted">
                                                            <i class="fa fa-user"></i> مشتری: <?php echo htmlspecialchars($ticket['customer_name']); ?><br>
                                                            <i class="fa fa-cube"></i> دارایی: <?php echo htmlspecialchars($ticket['asset_name'] ?? 'نامشخص'); ?><br>
                                                            <i class="fa fa-user-tie"></i> تخصیص یافته به: <?php echo htmlspecialchars($ticket['assigned_user'] ?? 'تخصیص نیافته'); ?>
                                                        </small>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <small class="text-muted">
                                                            <i class="fa fa-hashtag"></i> شماره تیکت: <?php echo htmlspecialchars($ticket['ticket_number']); ?><br>
                                                            <i class="fa fa-calendar"></i> تاریخ ایجاد: <?php echo jalaliDate($ticket['created_at']); ?><br>
                                                            <i class="fa fa-user-plus"></i> ایجاد کننده: <?php echo htmlspecialchars($ticket['created_by_name']); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="ms-3">
                                                <div class="btn-group-vertical" role="group">
                                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#updateStatusModal" 
                                                            data-ticket-id="<?php echo $ticket['id']; ?>" data-current-status="<?php echo $ticket['status']; ?>">
                                                        <i class="fa fa-edit"></i> تغییر وضعیت
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#assignTicketModal" 
                                                            data-ticket-id="<?php echo $ticket['id']; ?>" data-current-assigned="<?php echo $ticket['assigned_to']; ?>">
                                                        <i class="fa fa-user-plus"></i> تخصیص
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fa fa-ticket-alt fa-3x text-muted mb-3"></i>
                                <p class="text-muted">هیچ تیکتی یافت نشد.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal ایجاد تیکت جدید -->
    <div class="modal fade" id="createTicketModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form class="modal-content" method="post">
                <div class="modal-header">
                    <h5 class="modal-title">ایجاد تیکت جدید</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_ticket">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">مشتری *</label>
                            <select class="form-select" name="customer_id" required>
                                <option value="">انتخاب مشتری</option>
                                <?php foreach ($customers as $customer): ?>
                                    <option value="<?php echo $customer['id']; ?>"><?php echo htmlspecialchars($customer['full_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">دارایی</label>
                            <select class="form-select" name="asset_id">
                                <option value="">انتخاب دارایی (اختیاری)</option>
                                <?php foreach ($assets as $asset): ?>
                                    <option value="<?php echo $asset['id']; ?>"><?php echo htmlspecialchars($asset['name']); ?></option>
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

    <!-- Modal تغییر وضعیت -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" method="post">
                <div class="modal-header">
                    <h5 class="modal-title">تغییر وضعیت تیکت</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="ticket_id" id="updateTicketId">
                    
                    <div class="mb-3">
                        <label class="form-label">وضعیت جدید</label>
                        <select class="form-select" name="new_status" required>
                            <option value="جدید">جدید</option>
                            <option value="در انتظار">در انتظار</option>
                            <option value="در حال بررسی">در حال بررسی</option>
                            <option value="در انتظار قطعه">در انتظار قطعه</option>
                            <option value="تکمیل شده">تکمیل شده</option>
                            <option value="لغو شده">لغو شده</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">دلیل تغییر</label>
                        <textarea class="form-control" name="reason" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" class="btn btn-primary">به‌روزرسانی</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal تخصیص تیکت -->
    <div class="modal fade" id="assignTicketModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" method="post">
                <div class="modal-header">
                    <h5 class="modal-title">تخصیص تیکت</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="assign_ticket">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="ticket_id" id="assignTicketId">
                    
                    <div class="mb-3">
                        <label class="form-label">تخصیص به کاربر</label>
                        <select class="form-select" name="assigned_to" required>
                            <option value="">انتخاب کاربر</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" class="btn btn-primary">تخصیص</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // تنظیم مقادیر در modal تغییر وضعیت
        document.getElementById('updateStatusModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const ticketId = button.getAttribute('data-ticket-id');
            const currentStatus = button.getAttribute('data-current-status');
            
            document.getElementById('updateTicketId').value = ticketId;
            document.querySelector('select[name="new_status"]').value = currentStatus;
        });

        // تنظیم مقادیر در modal تخصیص
        document.getElementById('assignTicketModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const ticketId = button.getAttribute('data-ticket-id');
            const currentAssigned = button.getAttribute('data-current-assigned');
            
            document.getElementById('assignTicketId').value = ticketId;
            if (currentAssigned) {
                document.querySelector('select[name="assigned_to"]').value = currentAssigned;
            }
        });
    </script>
</body>
</html>