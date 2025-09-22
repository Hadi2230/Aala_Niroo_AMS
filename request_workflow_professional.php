<?php
/**
 * request_workflow_professional.php - سیستم حرفه‌ای مدیریت گردش کار درخواست‌ها
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'config.php';

$page_title = 'سیستم حرفه‌ای مدیریت گردش کار درخواست‌ها';

// پردازش عملیات
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_workflow_status') {
        try {
            $request_id = (int)$_POST['request_id'];
            $status = sanitizeInput($_POST['status']);
            $comments = sanitizeInput($_POST['comments']);
            $priority = sanitizeInput($_POST['priority'] ?? '');
            
            // بررسی دسترسی کاربر
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM request_workflow 
                WHERE request_id = ? AND assigned_to = ? AND status IN ('ارجاع شده', 'در انتظار', 'در حال بررسی')
            ");
            $stmt->execute([$request_id, $_SESSION['user_id']]);
            $has_access = $stmt->fetchColumn() > 0;
            
            if (!$has_access && !is_admin()) {
                throw new Exception('شما دسترسی لازم برای این درخواست را ندارید');
            }
            
            // به‌روزرسانی workflow
            $stmt = $pdo->prepare("
                UPDATE request_workflow 
                SET status = ?, comments = ?, action_date = NOW(), updated_at = NOW()
                WHERE request_id = ? AND assigned_to = ?
            ");
            $stmt->execute([$status, $comments, $request_id, $_SESSION['user_id']]);
            
            // به‌روزرسانی وضعیت کلی درخواست
            $stmt = $pdo->prepare("
                UPDATE requests 
                SET status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$status, $request_id]);
            
            // به‌روزرسانی اولویت
            if ($priority) {
                $stmt = $pdo->prepare("
                    UPDATE requests 
                    SET priority = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$priority, $request_id]);
            }
            
            // ایجاد اعلان برای درخواست‌کننده
            $stmt = $pdo->prepare("SELECT requester_id, request_number FROM requests WHERE id = ?");
            $stmt->execute([$request_id]);
            $request_info = $stmt->fetch();
            
            if ($request_info) {
                createRequestNotification($pdo, $request_id, $request_info['requester_id'], 'status_update', 
                    "وضعیت درخواست {$request_info['request_number']} به '{$status}' تغییر یافت");
            }
            
            $_SESSION['success_message'] = 'وضعیت درخواست با موفقیت به‌روزرسانی شد!';
            header('Location: request_workflow_professional.php');
            exit();
        } catch (Exception $e) {
            $error_message = 'خطا در به‌روزرسانی: ' . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'assign_request') {
        try {
            $request_id = (int)$_POST['request_id'];
            $assigned_to = (int)$_POST['assigned_to'];
            $comments = sanitizeInput($_POST['comments'] ?? '');
            
            // بررسی دسترسی ادمین
            if (!is_admin()) {
                throw new Exception('فقط ادمین می‌تواند درخواست‌ها را ارجاع دهد');
            }
            
            // ارجاع درخواست
            $stmt = $pdo->prepare("
                INSERT INTO request_workflow (request_id, assigned_to, status, comments, created_at)
                VALUES (?, ?, 'ارجاع شده', ?, NOW())
            ");
            $stmt->execute([$request_id, $assigned_to, $comments]);
            
            // به‌روزرسانی وضعیت درخواست
            $stmt = $pdo->prepare("
                UPDATE requests 
                SET status = 'ارجاع شده', updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$request_id]);
            
            // ایجاد اعلان برای کاربر ارجاع شده
            createRequestNotification($pdo, $request_id, $assigned_to, 'assignment', 
                'درخواست جدید به شما ارجاع شده است');
            
            $_SESSION['success_message'] = 'درخواست با موفقیت ارجاع شد!';
            header('Location: request_workflow_professional.php');
            exit();
        } catch (Exception $e) {
            $error_message = 'خطا در ارجاع درخواست: ' . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'delete_request') {
        try {
            $request_id = (int)$_POST['request_id'];
            
            // بررسی دسترسی ادمین
            if (!is_admin()) {
                throw new Exception('فقط ادمین می‌تواند درخواست‌ها را حذف کند');
            }
            
            // حذف درخواست
            $stmt = $pdo->prepare("DELETE FROM requests WHERE id = ?");
            $stmt->execute([$request_id]);
            
            $_SESSION['success_message'] = 'درخواست با موفقیت حذف شد!';
            header('Location: request_workflow_professional.php');
            exit();
        } catch (Exception $e) {
            $error_message = 'خطا در حذف درخواست: ' . $e->getMessage();
        }
    }
}

// دریافت درخواست‌ها با فیلترهای پیشرفته
$requests = [];
$filters = [
    'search' => $_GET['search'] ?? '',
    'status' => $_GET['status'] ?? '',
    'priority' => $_GET['priority'] ?? '',
    'type' => $_GET['type'] ?? '',
    'date' => $_GET['date'] ?? ''
];

try {
    $where_conditions = [];
    $params = [];
    
    // فیلتر جستجو
    if (!empty($filters['search'])) {
        $where_conditions[] = "(r.request_number LIKE ? OR r.item_name LIKE ? OR r.description LIKE ?)";
        $search_term = "%{$filters['search']}%";
        $params = array_merge($params, [$search_term, $search_term, $search_term]);
    }
    
    // فیلتر وضعیت
    if (!empty($filters['status'])) {
        $where_conditions[] = "r.status = ?";
        $params[] = $filters['status'];
    }
    
    // فیلتر اولویت
    if (!empty($filters['priority'])) {
        $where_conditions[] = "r.priority = ?";
        $params[] = $filters['priority'];
    }
    
    // فیلتر نوع
    if ($filters['type'] === 'my_requests') {
        $where_conditions[] = "r.requester_id = ?";
        $params[] = $_SESSION['user_id'];
    } elseif ($filters['type'] === 'assigned_to_me') {
        $where_conditions[] = "EXISTS (SELECT 1 FROM request_workflow rw2 WHERE rw2.request_id = r.id AND rw2.assigned_to = ?)";
        $params[] = $_SESSION['user_id'];
    } else {
        // همه درخواست‌ها
        $where_conditions[] = "(r.requester_id = ? OR EXISTS (SELECT 1 FROM request_workflow rw2 WHERE rw2.request_id = r.id AND rw2.assigned_to = ?))";
        $params = array_merge($params, [$_SESSION['user_id'], $_SESSION['user_id']]);
    }
    
    // فیلتر تاریخ
    if (!empty($filters['date'])) {
        $date_condition = "";
        switch ($filters['date']) {
            case 'today':
                $date_condition = "DATE(r.created_at) = CURDATE()";
                break;
            case 'week':
                $date_condition = "r.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $date_condition = "r.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                break;
        }
        if ($date_condition) {
            $where_conditions[] = $date_condition;
        }
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    $sql = "
        SELECT r.*, 
               u.username as requester_name,
               u.full_name as requester_full_name,
               COUNT(DISTINCT rf.id) as file_count,
               COUNT(DISTINCT rw.id) as workflow_count,
               GROUP_CONCAT(DISTINCT rw.assigned_to) as assigned_users,
               GROUP_CONCAT(DISTINCT u2.full_name) as assigned_names,
               (SELECT status FROM request_workflow WHERE request_id = r.id ORDER BY created_at DESC LIMIT 1) as current_workflow_status,
               (SELECT comments FROM request_workflow WHERE request_id = r.id ORDER BY created_at DESC LIMIT 1) as last_comments
        FROM requests r
        LEFT JOIN users u ON r.requester_id = u.id
        LEFT JOIN request_files rf ON r.id = rf.request_id
        LEFT JOIN request_workflow rw ON r.id = rw.request_id
        LEFT JOIN users u2 ON rw.assigned_to = u2.id
        $where_clause
        GROUP BY r.id
        ORDER BY r.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $requests = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching requests: " . $e->getMessage());
    $requests = [];
}

// محاسبه آمار
$stats = [
    'total' => count($requests),
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'completed' => 0,
    'assigned_to_me' => 0,
    'urgent' => 0
];

foreach ($requests as $request) {
    switch ($request['status']) {
        case 'در انتظار تأیید':
        case 'در حال بررسی':
            $stats['pending']++;
            break;
        case 'تأیید شده':
            $stats['approved']++;
            break;
        case 'رد شده':
            $stats['rejected']++;
            break;
        case 'تکمیل شده':
            $stats['completed']++;
            break;
    }
    
    // بررسی درخواست‌های ارجاع شده
    if (isset($request['assigned_users']) && $request['assigned_users']) {
        $assigned_users = explode(',', $request['assigned_users']);
        if (in_array($_SESSION['user_id'], $assigned_users)) {
            $stats['assigned_to_me']++;
        }
    }
    
    if ($request['priority'] === 'فوری' || $request['priority'] === 'بالا') {
        $stats['urgent']++;
    }
}

// دریافت کاربران برای ارجاع
$users = [];
try {
    $stmt = $pdo->query("SELECT id, username, full_name, role, department FROM users WHERE status = 'active' ORDER BY full_name, username");
    $users = $stmt->fetchAll();
} catch (Exception $e) {
    $users = [];
}

// توابع کمکی
function is_admin() {
    return isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'ادمین');
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function createRequestNotification($pdo, $request_id, $user_id, $type, $message) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO request_notifications (request_id, user_id, type, message, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$request_id, $user_id, $type, $message]);
        return true;
    } catch (Exception $e) {
        error_log("Error creating notification: " . $e->getMessage());
        return false;
    }
}

function getStatusColor($status) {
    switch ($status) {
        case 'در انتظار تأیید':
            return 'warning';
        case 'تأیید شده':
            return 'success';
        case 'رد شده':
            return 'danger';
        case 'ارجاع شده':
            return 'info';
        case 'در حال بررسی':
            return 'primary';
        case 'تکمیل شده':
            return 'secondary';
        default:
            return 'light';
    }
}

function getPriorityColor($priority) {
    switch ($priority) {
        case 'فوری':
            return 'danger';
        case 'بالا':
            return 'warning';
        case 'متوسط':
            return 'primary';
        case 'کم':
            return 'success';
        default:
            return 'secondary';
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - اعلا نیرو</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Persian Font -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <!-- Persian DatePicker -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css">
    
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #56ab2f;
            --warning-color: #f093fb;
            --danger-color: #ff416c;
            --info-color: #4facfe;
            --dark-color: #2c3e50;
            --light-color: #f8f9fa;
        }

        body {
            font-family: Vazirmatn, Tahoma, Arial, sans-serif;
            background-color: var(--light-color);
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 15px;
        }

        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
            border: none;
            text-align: center;
        }

        .stats-card:hover {
            transform: translateY(-5px);
        }

        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin: 0 auto 1rem;
        }

        .request-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
            border: none;
            transition: transform 0.3s ease;
        }

        .request-card:hover {
            transform: translateY(-2px);
        }

        .request-card.urgent {
            border-left: 5px solid var(--danger-color);
        }

        .request-card.assigned {
            border-left: 5px solid var(--warning-color);
        }

        .request-card.pending {
            border-left: 5px solid var(--info-color);
        }

        .workflow-timeline {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 15px;
        }

        .timeline-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding: 15px;
            background: white;
            border-radius: 8px;
            border-left: 4px solid var(--primary-color);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .timeline-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 15px;
            font-size: 1.2rem;
        }

        .timeline-content {
            flex: 1;
        }

        .timeline-title {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .timeline-meta {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .timeline-comments {
            margin-top: 8px;
            padding: 10px;
            background: #e9ecef;
            border-radius: 6px;
            font-size: 0.9rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success-color), #56ab2f);
            border: none;
            border-radius: 10px;
            padding: 0.5rem 1.5rem;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .btn-warning {
            background: linear-gradient(135deg, var(--warning-color), #f093fb);
            border: none;
            border-radius: 10px;
            padding: 0.5rem 1.5rem;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger-color), #ff6b6b);
            border: none;
            border-radius: 10px;
            padding: 0.5rem 1.5rem;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .btn-info {
            background: linear-gradient(135deg, var(--info-color), #4facfe);
            border: none;
            border-radius: 10px;
            padding: 0.5rem 1.5rem;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.9rem;
        }

        .priority-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .search-filter {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 10px 15px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
        }

        .no-requests {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .no-requests i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .page-title {
                font-size: 2rem;
            }
            
            .stats-card {
                margin-bottom: 20px;
            }
            
            .request-meta {
                flex-direction: column;
                gap: 8px;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <!-- Page Header -->
        <div class="page-header">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="mb-0">
                            <i class="fas fa-cogs me-3"></i><?php echo $page_title; ?>
                        </h1>
                        <p class="mb-0 mt-2">مدیریت پیشرفته و پیگیری کامل گردش کار درخواست‌ها</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <button class="btn btn-primary" onclick="location.reload()">
                            <i class="fas fa-sync-alt me-2"></i>تازه‌سازی
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="stats-card">
                    <div class="stats-icon" style="background: linear-gradient(135deg, var(--info-color), #4facfe);">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <h3 class="mb-1"><?php echo $stats['total']; ?></h3>
                    <p class="text-muted mb-0">کل درخواست‌ها</p>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="stats-card">
                    <div class="stats-icon" style="background: linear-gradient(135deg, var(--warning-color), #f093fb);">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <h3 class="mb-1"><?php echo $stats['assigned_to_me']; ?></h3>
                    <p class="text-muted mb-0">ارجاع شده به من</p>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="stats-card">
                    <div class="stats-icon" style="background: linear-gradient(135deg, var(--info-color), #4facfe);">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3 class="mb-1"><?php echo $stats['pending']; ?></h3>
                    <p class="text-muted mb-0">در انتظار</p>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="stats-card">
                    <div class="stats-icon" style="background: linear-gradient(135deg, var(--success-color), #56ab2f);">
                        <i class="fas fa-check"></i>
                    </div>
                    <h3 class="mb-1"><?php echo $stats['approved']; ?></h3>
                    <p class="text-muted mb-0">تأیید شده</p>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="stats-card">
                    <div class="stats-icon" style="background: linear-gradient(135deg, var(--danger-color), #ff6b6b);">
                        <i class="fas fa-times"></i>
                    </div>
                    <h3 class="mb-1"><?php echo $stats['rejected']; ?></h3>
                    <p class="text-muted mb-0">رد شده</p>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="stats-card">
                    <div class="stats-icon" style="background: linear-gradient(135deg, #ff6b6b, #ff8e8e);">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h3 class="mb-1"><?php echo $stats['urgent']; ?></h3>
                    <p class="text-muted mb-0">اولویت بالا</p>
                </div>
            </div>
        </div>

        <!-- Search and Filter -->
        <div class="search-filter">
            <form method="GET" id="filterForm">
                <div class="row">
                    <div class="col-md-3">
                        <label class="form-label">جستجو در درخواست‌ها</label>
                        <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($filters['search']); ?>" 
                               placeholder="جستجو بر اساس نام آیتم یا شماره درخواست...">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">فیلتر بر اساس وضعیت</label>
                        <select class="form-control" name="status">
                            <option value="">همه وضعیت‌ها</option>
                            <option value="در انتظار تأیید" <?php echo $filters['status'] === 'در انتظار تأیید' ? 'selected' : ''; ?>>در انتظار تأیید</option>
                            <option value="در حال بررسی" <?php echo $filters['status'] === 'در حال بررسی' ? 'selected' : ''; ?>>در حال بررسی</option>
                            <option value="تأیید شده" <?php echo $filters['status'] === 'تأیید شده' ? 'selected' : ''; ?>>تأیید شده</option>
                            <option value="رد شده" <?php echo $filters['status'] === 'رد شده' ? 'selected' : ''; ?>>رد شده</option>
                            <option value="تکمیل شده" <?php echo $filters['status'] === 'تکمیل شده' ? 'selected' : ''; ?>>تکمیل شده</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">فیلتر بر اساس اولویت</label>
                        <select class="form-control" name="priority">
                            <option value="">همه اولویت‌ها</option>
                            <option value="فوری" <?php echo $filters['priority'] === 'فوری' ? 'selected' : ''; ?>>فوری</option>
                            <option value="بالا" <?php echo $filters['priority'] === 'بالا' ? 'selected' : ''; ?>>بالا</option>
                            <option value="متوسط" <?php echo $filters['priority'] === 'متوسط' ? 'selected' : ''; ?>>متوسط</option>
                            <option value="کم" <?php echo $filters['priority'] === 'کم' ? 'selected' : ''; ?>>کم</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">نوع درخواست</label>
                        <select class="form-control" name="type">
                            <option value="">همه</option>
                            <option value="my_requests" <?php echo $filters['type'] === 'my_requests' ? 'selected' : ''; ?>>درخواست‌های من</option>
                            <option value="assigned_to_me" <?php echo $filters['type'] === 'assigned_to_me' ? 'selected' : ''; ?>>ارجاع شده به من</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">تاریخ</label>
                        <select class="form-control" name="date">
                            <option value="">همه تاریخ‌ها</option>
                            <option value="today" <?php echo $filters['date'] === 'today' ? 'selected' : ''; ?>>امروز</option>
                            <option value="week" <?php echo $filters['date'] === 'week' ? 'selected' : ''; ?>>این هفته</option>
                            <option value="month" <?php echo $filters['date'] === 'month' ? 'selected' : ''; ?>>این ماه</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-1">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="clearFilters()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Requests List -->
        <div class="row">
            <?php if (empty($requests)): ?>
            <div class="col-12">
                <div class="no-requests">
                    <i class="fas fa-inbox"></i>
                    <h4>هیچ درخواستی یافت نشد</h4>
                    <p>با فیلترهای انتخابی هیچ درخواستی یافت نشد.</p>
                    <a href="request_management_final.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>ایجاد درخواست جدید
                    </a>
                </div>
            </div>
            <?php else: ?>
                <?php foreach ($requests as $request): ?>
                <?php
                // بررسی آیا درخواست به کاربر ارجاع شده
                $is_assigned = false;
                if (isset($request['assigned_users']) && $request['assigned_users']) {
                    $assigned_users = explode(',', $request['assigned_users']);
                    $is_assigned = in_array($_SESSION['user_id'], $assigned_users);
                }
                ?>
                <div class="col-12">
                    <div class="request-card <?php echo $request['priority'] === 'فوری' ? 'urgent' : ($is_assigned ? 'assigned' : 'pending'); ?>">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <div class="d-flex align-items-center mb-2">
                                    <h5 class="mb-0 me-3"><?php echo htmlspecialchars($request['request_number']); ?></h5>
                                    <span class="status-badge bg-<?php echo getStatusColor($request['status']); ?>">
                                        <?php echo htmlspecialchars($request['status']); ?>
                                    </span>
                                    <span class="priority-badge bg-<?php echo getPriorityColor($request['priority']); ?> ms-2">
                                        <?php echo htmlspecialchars($request['priority']); ?>
                                    </span>
                                    <?php if ($is_assigned): ?>
                                        <span class="badge bg-warning ms-2">ارجاع شده به من</span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-muted mb-1">
                                    <i class="fas fa-user me-1"></i>
                                    درخواست‌کننده: <?php echo htmlspecialchars($request['requester_full_name'] ?: $request['requester_name']); ?>
                                    <span class="ms-3">
                                        <i class="fas fa-calendar me-1"></i>
                                        تاریخ ایجاد: <?php echo jalali_format($request['created_at']); ?>
                                    </span>
                                </p>
                                <p class="mb-0">
                                    <strong>آیتم:</strong> <?php echo htmlspecialchars($request['item_name']); ?>
                                    <span class="ms-3"><strong>تعداد:</strong> <?php echo number_format($request['quantity']); ?></span>
                                    <span class="ms-3"><strong>قیمت:</strong> <?php echo number_format($request['price']); ?> تومان</span>
                                </p>
                                <?php if ($request['description']): ?>
                                <p class="text-muted mt-2 mb-0">
                                    <i class="fas fa-comment me-1"></i><?php echo htmlspecialchars($request['description']); ?>
                                </p>
                                <?php endif; ?>
                                <?php if (isset($request['assigned_names']) && $request['assigned_names']): ?>
                                <p class="text-info mt-2 mb-0">
                                    <i class="fas fa-users me-1"></i>ارجاع شده به: <?php echo htmlspecialchars($request['assigned_names']); ?>
                                </p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4 text-end">
                                <div class="btn-group" role="group">
                                    <button class="btn btn-info btn-sm" onclick="viewWorkflow(<?php echo $request['id']; ?>)">
                                        <i class="fas fa-route me-1"></i>گردش کار
                                    </button>
                                    <?php if ($is_assigned): ?>
                                        <button class="btn btn-warning btn-sm" onclick="updateStatus(<?php echo $request['id']; ?>, '<?php echo $request['status']; ?>')">
                                            <i class="fas fa-edit me-1"></i>اقدام
                                        </button>
                                    <?php endif; ?>
                                    <?php if (is_admin()): ?>
                                        <button class="btn btn-primary btn-sm" onclick="assignRequest(<?php echo $request['id']; ?>)">
                                            <i class="fas fa-user-plus me-1"></i>ارجاع
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="deleteRequest(<?php echo $request['id']; ?>, '<?php echo htmlspecialchars($request['request_number']); ?>')">
                                            <i class="fas fa-trash me-1"></i>حذف
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>به‌روزرسانی وضعیت درخواست
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_workflow_status">
                        <input type="hidden" name="request_id" id="update_request_id">
                        
                        <div class="mb-3">
                            <label class="form-label">وضعیت جدید</label>
                            <select class="form-control" name="status" id="update_status" required>
                                <option value="در حال بررسی">در حال بررسی</option>
                                <option value="تأیید شده">تأیید شده</option>
                                <option value="رد شده">رد شده</option>
                                <option value="تکمیل شده">تکمیل شده</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">اولویت</label>
                            <select class="form-control" name="priority">
                                <option value="">تغییر اولویت (اختیاری)</option>
                                <option value="کم">کم</option>
                                <option value="متوسط">متوسط</option>
                                <option value="بالا">بالا</option>
                                <option value="فوری">فوری</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">توضیحات</label>
                            <textarea class="form-control" name="comments" rows="4" 
                                      placeholder="توضیحات خود را وارد کنید..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>ذخیره تغییرات
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Assign Request Modal -->
    <div class="modal fade" id="assignRequestModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus me-2"></i>ارجاع درخواست
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="assign_request">
                        <input type="hidden" name="request_id" id="assign_request_id">
                        
                        <div class="mb-3">
                            <label class="form-label">ارجاع به</label>
                            <select class="form-control" name="assigned_to" required>
                                <option value="">انتخاب کاربر...</option>
                                <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?> 
                                    (<?php echo htmlspecialchars($user['role'] ?? ''); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">توضیحات</label>
                            <textarea class="form-control" name="comments" rows="3" 
                                      placeholder="توضیحات مربوط به ارجاع..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-paper-plane me-2"></i>ارجاع درخواست
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Workflow Timeline Modal -->
    <div class="modal fade" id="workflowModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-route me-2"></i>گردش کار درخواست
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="workflow-content">
                    <!-- محتوای گردش کار اینجا نمایش داده می‌شود -->
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js"></script>
    
    <script>
        function clearFilters() {
            document.getElementById('filterForm').reset();
            window.location.href = 'request_workflow_professional.php';
        }

        function updateStatus(requestId, currentStatus) {
            document.getElementById('update_request_id').value = requestId;
            document.getElementById('update_status').value = currentStatus;
            new bootstrap.Modal(document.getElementById('updateStatusModal')).show();
        }

        function assignRequest(requestId) {
            document.getElementById('assign_request_id').value = requestId;
            new bootstrap.Modal(document.getElementById('assignRequestModal')).show();
        }

        function viewWorkflow(requestId) {
            // نمایش گردش کار درخواست
            fetch(`get_request_workflow.php?id=${requestId}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('workflow-content').innerHTML = data;
                    new bootstrap.Modal(document.getElementById('workflowModal')).show();
                })
                .catch(error => {
                    // اگر فایل get_request_workflow.php وجود ندارد، نمایش ساده
                    document.getElementById('workflow-content').innerHTML = `
                        <div class="workflow-timeline">
                            <div class="timeline-item">
                                <div class="timeline-icon">
                                    <i class="fas fa-info-circle"></i>
                                </div>
                                <div class="timeline-content">
                                    <div class="timeline-title">درخواست ایجاد شد</div>
                                    <div class="timeline-meta">تاریخ: ${new Date().toLocaleDateString('fa-IR')}</div>
                                </div>
                            </div>
                        </div>
                    `;
                    new bootstrap.Modal(document.getElementById('workflowModal')).show();
                });
        }

        function deleteRequest(requestId, requestNumber) {
            if (confirm('آیا مطمئن هستید که می‌خواهید درخواست "' + requestNumber + '" را حذف کنید؟\n\nاین عمل قابل بازگشت نیست!')) {
                const formData = new FormData();
                formData.append('action', 'delete_request');
                formData.append('request_id', requestId);
                
                fetch('request_workflow_professional.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(data => {
                    if (data.includes('success_message')) {
                        alert('درخواست با موفقیت حذف شد!');
                        location.reload();
                    } else {
                        alert('خطا در حذف درخواست');
                    }
                })
                .catch(error => {
                    alert('خطا در حذف درخواست: ' + error);
                });
            }
        }

        // Initialize Persian DatePicker
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
</body>
</html>
