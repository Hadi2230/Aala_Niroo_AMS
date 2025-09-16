<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// ایجاد جدول‌های مورد نیاز
try {
    // جدول تیکت‌ها
    $pdo->exec("CREATE TABLE IF NOT EXISTS tickets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        asset_id INT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        priority ENUM('فوری', 'بالا', 'متوسط', 'کم') DEFAULT 'متوسط',
        status ENUM('جدید', 'در انتظار', 'در حال بررسی', 'در انتظار قطعه', 'تکمیل شده', 'لغو شده') DEFAULT 'جدید',
        ticket_number VARCHAR(50) UNIQUE NOT NULL,
        created_by INT NOT NULL,
        assigned_to INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_customer_id (customer_id),
        INDEX idx_asset_id (asset_id),
        INDEX idx_status (status),
        INDEX idx_priority (priority),
        INDEX idx_created_by (created_by),
        INDEX idx_assigned_to (assigned_to),
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
        FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE SET NULL,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // جدول تاریخچه تیکت‌ها
    $pdo->exec("CREATE TABLE IF NOT EXISTS ticket_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ticket_id INT NOT NULL,
        action VARCHAR(100) NOT NULL,
        old_value TEXT,
        new_value TEXT,
        performed_by INT NOT NULL,
        performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        notes TEXT,
        INDEX idx_ticket_id (ticket_id),
        INDEX idx_performed_by (performed_by),
        INDEX idx_performed_at (performed_at),
        FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
        FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {
    error_log("Error creating tickets tables: " . $e->getMessage());
}

// تابع ایجاد تیکت
function createTicket($pdo, $customer_id, $asset_id, $title, $description, $priority, $created_by) {
    try {
        // تولید شماره تیکت
        $ticket_number = 'TKT-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        $stmt = $pdo->prepare("
            INSERT INTO tickets (customer_id, asset_id, title, description, priority, created_by, ticket_number, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'جدید', CURRENT_TIMESTAMP)
        ");
        
        $result = $stmt->execute([$customer_id, $asset_id, $title, $description, $priority, $created_by, $ticket_number]);
        
        if ($result) {
            return $pdo->lastInsertId();
        }
        return false;
    } catch (Exception $e) {
        error_log("Error creating ticket: " . $e->getMessage());
        return false;
    }
}

// تابع به‌روزرسانی وضعیت تیکت
function updateTicketStatus($pdo, $ticket_id, $new_status, $updated_by, $reason = '') {
    try {
        $stmt = $pdo->prepare("
            UPDATE tickets 
            SET status = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        
        $result = $stmt->execute([$new_status, $ticket_id]);
        
        if ($result) {
            // ثبت تاریخچه تغییر وضعیت
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO ticket_history (ticket_id, action, old_value, new_value, performed_by, performed_at, notes) 
                    VALUES (?, 'تغییر وضعیت', (SELECT status FROM tickets WHERE id = ?), ?, ?, CURRENT_TIMESTAMP, ?)
                ");
                $stmt->execute([$ticket_id, $ticket_id, $new_status, $updated_by, $reason]);
            } catch (Exception $e) {
                // اگر جدول تاریخچه وجود نداشت، خطا را نادیده بگیر
                error_log("Error logging ticket history: " . $e->getMessage());
            }
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("Error updating ticket status: " . $e->getMessage());
        return false;
    }
}

// تابع تخصیص تیکت
function assignTicket($pdo, $ticket_id, $assigned_to, $assigned_by) {
    try {
        $stmt = $pdo->prepare("
            UPDATE tickets 
            SET assigned_to = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        
        $result = $stmt->execute([$assigned_to, $ticket_id]);
        
        if ($result) {
            // ثبت تاریخچه تخصیص
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO ticket_history (ticket_id, action, new_value, performed_by, performed_at) 
                    VALUES (?, 'تخصیص', ?, ?, CURRENT_TIMESTAMP)
                ");
                $stmt->execute([$ticket_id, $assigned_to, $assigned_by]);
            } catch (Exception $e) {
                // اگر جدول تاریخچه وجود نداشت، خطا را نادیده بگیر
                error_log("Error logging ticket assignment: " . $e->getMessage());
            }
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("Error assigning ticket: " . $e->getMessage());
        return false;
    }
}



// تولید CSRF Token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// بررسی دسترسی
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'مدیر') {
    // برای کاربران غیر مدیر، بررسی دسترسی‌های سفارشی
    if (!hasPermission('tickets.view')) {
        header('Location: dashboard.php');
        exit();
    }
}

// پردازش درخواست‌ها
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // بررسی CSRF Token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = 'درخواست نامعتبر است - CSRF Token validation failed';
    } else {
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_ticket':
                try {
                    $ticket_id = createTicket($pdo, 
                        (int)$_POST['customer_id'],
                        !empty($_POST['asset_id']) ? (int)$_POST['asset_id'] : null,
                        sanitizeInput($_POST['title']),
                        sanitizeInput($_POST['description']),
                        sanitizeInput($_POST['priority']),
                        $_SESSION['user_id']
                    );
                    
                    if ($ticket_id) {
                        $success_message = 'تیکت با موفقیت ایجاد شد. شماره تیکت: ' . $ticket_id;
                    } else {
                        $error_message = 'خطا در ایجاد تیکت';
                    }
                } catch (Exception $e) {
                    $error_message = 'خطا در ایجاد تیکت: ' . $e->getMessage();
                }
                break;
                
            case 'update_status':
                try {
                    $result = updateTicketStatus($pdo, (int)$_POST['ticket_id'], sanitizeInput($_POST['new_status']), $_SESSION['user_id'], sanitizeInput($_POST['reason'] ?? ''));
                    if ($result) {
                        $success_message = 'وضعیت تیکت با موفقیت به‌روزرسانی شد';
                    } else {
                        $error_message = 'خطا در به‌روزرسانی وضعیت';
                    }
                } catch (Exception $e) {
                    $error_message = 'خطا در به‌روزرسانی وضعیت: ' . $e->getMessage();
                }
                break;
                
            case 'assign_ticket':
                try {
                    $result = assignTicket($pdo, (int)$_POST['ticket_id'], (int)$_POST['assigned_to'], $_SESSION['user_id']);
                    if ($result) {
                        $success_message = 'تیکت با موفقیت تخصیص یافت';
                    } else {
                        $error_message = 'خطا در تخصیص تیکت';
                    }
                } catch (Exception $e) {
                    $error_message = 'خطا در تخصیص تیکت: ' . $e->getMessage();
                }
                break;
        }
    }
    }
}

// دریافت فیلترها
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$priority_filter = $_GET['priority'] ?? '';

// ساخت کوئری
$where_conditions = [];
$params = [];

if ($filter !== 'all') {
    switch ($filter) {
        case 'open':
            $where_conditions[] = "t.status IN ('جدید', 'در انتظار', 'در حال بررسی')";
            break;
        case 'urgent':
            $where_conditions[] = "t.priority = 'فوری'";
            break;
        case 'assigned':
            $where_conditions[] = "t.assigned_to IS NOT NULL";
            break;
        case 'unassigned':
            $where_conditions[] = "t.assigned_to IS NULL";
            break;
        case 'completed':
            $where_conditions[] = "t.status = 'تکمیل شده'";
            break;
    }
}

if (!empty($search)) {
    $where_conditions[] = "(t.title LIKE ? OR t.description LIKE ? OR c.full_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($status_filter)) {
    $where_conditions[] = "t.status = ?";
    $params[] = $status_filter;
}

if (!empty($priority_filter)) {
    $where_conditions[] = "t.priority = ?";
    $params[] = $priority_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// دریافت تیکت‌ها
try {
    $tickets_query = "
        SELECT t.*, 
               c.full_name as customer_name,
               a.name as asset_name,
               u.full_name as assigned_user,
               creator.full_name as created_by_name
        FROM tickets t
        LEFT JOIN customers c ON t.customer_id = c.id
        LEFT JOIN assets a ON t.asset_id = a.id
        LEFT JOIN users u ON t.assigned_to = u.id
        LEFT JOIN users creator ON t.created_by = creator.id
        $where_clause
        ORDER BY t.created_at DESC
    ";
    
    $stmt = $pdo->prepare($tickets_query);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll();
} catch (Exception $e) {
    $error_message = 'خطا در دریافت تیکت‌ها: ' . $e->getMessage();
    $tickets = [];
}

// دریافت آمار
try {
    $stats = [
        'total' => $pdo->query("SELECT COUNT(*) FROM tickets")->fetchColumn(),
        'open' => $pdo->query("SELECT COUNT(*) FROM tickets WHERE status IN ('جدید', 'در انتظار', 'در حال بررسی')")->fetchColumn(),
        'urgent' => $pdo->query("SELECT COUNT(*) FROM tickets WHERE priority = 'فوری'")->fetchColumn(),
        'assigned' => $pdo->query("SELECT COUNT(*) FROM tickets WHERE assigned_to IS NOT NULL")->fetchColumn(),
        'unassigned' => $pdo->query("SELECT COUNT(*) FROM tickets WHERE assigned_to IS NULL")->fetchColumn(),
        'completed' => $pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'تکمیل شده'")->fetchColumn()
    ];
} catch (Exception $e) {
    $stats = ['total' => 0, 'open' => 0, 'urgent' => 0, 'assigned' => 0, 'unassigned' => 0, 'completed' => 0];
}

// دریافت داده‌های مورد نیاز برای فرم‌ها
try {
    $customers = $pdo->query("SELECT id, full_name FROM customers ORDER BY full_name")->fetchAll();
    $assets = $pdo->query("SELECT id, name FROM assets ORDER BY name")->fetchAll();
    $users = $pdo->query("SELECT id, full_name FROM users ORDER BY full_name")->fetchAll();
} catch (Exception $e) {
    $customers = $assets = $users = [];
}
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
        .dark-mode { background-color: #1a1a1a !important; color: #ffffff !important; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .dark-mode .card { background-color: #2d3748; }
        .card-header { background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); color: white; border-radius: 12px 12px 0 0 !important; font-weight: 600; }
        .btn-primary { background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); border: none; border-radius: 6px; padding: 6px 18px; transition: all 0.3s; font-size: 0.85rem; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(52,152,219,0.3); }
        .priority-badge { font-size: 0.75rem; padding: 2px 6px; }
        .status-badge { font-size: 0.75rem; padding: 2px 6px; }
        .ticket-item { border-left: 4px solid #3498db; }
        .ticket-item.urgent { border-left-color: #e74c3c; }
        .ticket-item.high { border-left-color: #f39c12; }
        .ticket-item.medium { border-left-color: #3498db; }
        .ticket-item.low { border-left-color: #95a5a6; }
        .stats-card { text-align: center; padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 12px; }
        .stats-number { font-size: 2rem; font-weight: bold; margin-bottom: 5px; }
        .stats-label { font-size: 0.9rem; opacity: 0.9; }
        .filter-buttons .btn { margin-left: 5px; margin-bottom: 5px; }
        .search-box { max-width: 300px; }
        .ticket-actions { margin-top: 10px; }
        .ticket-actions .btn { margin-left: 5px; }
    </style>
</head>
<body class="<?php echo isset($_COOKIE['theme']) && $_COOKIE['theme']==='dark' ? 'dark-mode' : ''; ?>">
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-ticket-alt"></i> مدیریت تیکت‌ها</span>
                        <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#createTicketModal">
                            <i class="fas fa-plus"></i> تیکت جدید
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (isset($success_message)): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($error_message)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- آمار تیکت‌ها -->
                        <div class="row mb-4">
                            <div class="col-md-2">
                                <div class="stats-card">
                                    <div class="stats-number"><?php echo $stats['total']; ?></div>
                                    <div class="stats-label">کل تیکت‌ها</div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stats-card">
                                    <div class="stats-number"><?php echo $stats['open']; ?></div>
                                    <div class="stats-label">باز</div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stats-card">
                                    <div class="stats-number"><?php echo $stats['urgent']; ?></div>
                                    <div class="stats-label">فوری</div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stats-card">
                                    <div class="stats-number"><?php echo $stats['assigned']; ?></div>
                                    <div class="stats-label">تخصیص یافته</div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stats-card">
                                    <div class="stats-number"><?php echo $stats['unassigned']; ?></div>
                                    <div class="stats-label">تخصیص نیافته</div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stats-card">
                                    <div class="stats-number"><?php echo $stats['completed']; ?></div>
                                    <div class="stats-label">تکمیل شده</div>
                                </div>
                            </div>
                        </div>

                        <!-- فیلترهای سریع -->
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <div class="filter-buttons">
                                    <a href="?filter=all" class="btn btn-outline-primary <?php echo $filter == 'all' ? 'active' : ''; ?>">
                                        <i class="fas fa-list"></i> همه
                                    </a>
                                    <a href="?filter=open" class="btn btn-outline-warning <?php echo $filter == 'open' ? 'active' : ''; ?>">
                                        <i class="fas fa-folder-open"></i> باز
                                    </a>
                                    <a href="?filter=urgent" class="btn btn-outline-danger <?php echo $filter == 'urgent' ? 'active' : ''; ?>">
                                        <i class="fas fa-exclamation-circle"></i> فوری
                                    </a>
                                    <a href="?filter=assigned" class="btn btn-outline-info <?php echo $filter == 'assigned' ? 'active' : ''; ?>">
                                        <i class="fas fa-user-check"></i> تخصیص یافته
                                    </a>
                                    <a href="?filter=unassigned" class="btn btn-outline-secondary <?php echo $filter == 'unassigned' ? 'active' : ''; ?>">
                                        <i class="fas fa-user-times"></i> تخصیص نیافته
                                    </a>
                                    <a href="?filter=completed" class="btn btn-outline-success <?php echo $filter == 'completed' ? 'active' : ''; ?>">
                                        <i class="fas fa-check"></i> تکمیل شده
                                    </a>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <form method="GET" class="d-flex">
                                    <input type="hidden" name="filter" value="<?php echo $filter; ?>">
                                    <input type="text" class="form-control search-box" name="search" placeholder="جستجو در تیکت‌ها..." value="<?php echo htmlspecialchars($search); ?>">
                                    <button type="submit" class="btn btn-outline-secondary">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- فیلترهای پیشرفته -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <form method="GET" class="row g-3">
                                    <input type="hidden" name="filter" value="<?php echo $filter; ?>">
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
                        <div class="ticket-list">
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
                                                                <i class="fa fa-calendar"></i> تاریخ ایجاد: <?php echo date('Y/m/d H:i', strtotime($ticket['created_at'])); ?><br>
                                                                <i class="fa fa-user-plus"></i> ایجاد کننده: <?php echo htmlspecialchars($ticket['created_by_name']); ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="ticket-actions">
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

            // Auto-hide alerts
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        </script>
    </body>
    </html>