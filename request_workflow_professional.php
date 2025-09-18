<?php
/**
 * request_workflow_professional.php - سیستم حرفه‌ای مدیریت درخواست‌ها
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'config_simple_fixed.php';

$page_title = 'سیستم حرفه‌ای مدیریت درخواست‌ها';

// پردازش عملیات
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_workflow') {
        try {
            $request_id = (int)$_POST['request_id'];
            $status = sanitizeInput($_POST['status']);
            $comments = sanitizeInput($_POST['comments']);
            $priority = sanitizeInput($_POST['priority'] ?? '');
            
            // بررسی دسترسی کاربر
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM request_workflow 
                WHERE request_id = ? AND assigned_to = ? AND status IN ('در انتظار', 'در حال بررسی')
            ");
            $stmt->execute([$request_id, $_SESSION['user_id']]);
            $has_access = $stmt->fetchColumn() > 0;
            
            if (!$has_access) {
                throw new Exception('شما دسترسی لازم برای این درخواست را ندارید');
            }
            
            // به‌روزرسانی گردش کار
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
            
            // ایجاد اعلان
            $stmt = $pdo->prepare("SELECT requester_id, request_number FROM requests WHERE id = ?");
            $stmt->execute([$request_id]);
            $request_info = $stmt->fetch();
            
            if ($request_info) {
                createRequestNotification($pdo, $request_info['requester_id'], $request_id, 'workflow_update', 
                    "وضعیت درخواست {$request_info['request_number']} به '{$status}' تغییر یافت");
            }
            
            $_SESSION['success_message'] = 'وضعیت درخواست با موفقیت به‌روزرسانی شد!';
            header('Location: request_workflow_professional.php');
            exit();
        } catch (Exception $e) {
            $error_message = 'خطا در به‌روزرسانی: ' . $e->getMessage();
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
               COUNT(DISTINCT rf.id) as file_count,
               COUNT(DISTINCT rw.id) as workflow_count,
               GROUP_CONCAT(DISTINCT rw.assigned_to) as assigned_users,
               GROUP_CONCAT(DISTINCT u.full_name) as assigned_names
        FROM requests r
        LEFT JOIN request_files rf ON r.id = rf.request_id
        LEFT JOIN request_workflow rw ON r.id = rw.request_id
        LEFT JOIN users u ON rw.assigned_to = u.id
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
    'assigned_to_me' => 0
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
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
            --dark-bg: #1a1a1a;
            --light-bg: #f8f9fa;
            --card-bg: #ffffff;
            --text-primary: #2c3e50;
            --text-secondary: #6c757d;
            --border-color: #e9ecef;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-hover: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        body {
            font-family: 'Vazirmatn', 'Tahoma', sans-serif;
            background: #f8f9fa;
            margin: 0;
            padding: 0;
            color: var(--text-primary);
        }

        .main-container {
            padding-top: 80px;
            min-height: 100vh;
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #34495e 100%);
            color: white;
            padding: 40px 0;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .page-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .card {
            background: var(--card-bg);
            border: none;
            border-radius: 15px;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            overflow: hidden;
            margin-bottom: 25px;
        }

        .card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
        }

        .card-header {
            background: linear-gradient(135deg, var(--secondary-color) 0%, #5dade2 100%);
            color: white;
            border: none;
            padding: 20px 25px;
            font-weight: 600;
            font-size: 1.2rem;
        }

        .card-body {
            padding: 25px;
        }

        .stats-card {
            background: linear-gradient(135deg, var(--primary-color) 0%, #34495e 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            transition: all 0.3s ease;
            height: 100%;
            cursor: pointer;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
        }

        .stats-card.assigned {
            background: linear-gradient(135deg, var(--warning-color) 0%, #f39c12 100%);
        }

        .stats-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .stats-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .stats-description {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .request-item {
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }

        .request-item:hover {
            border-color: var(--secondary-color);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.1);
        }

        .request-item.assigned {
            border-left: 5px solid var(--warning-color);
        }

        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .request-number {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .request-date {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .request-details {
            margin-bottom: 15px;
        }

        .request-item-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .request-meta {
            display: flex;
            gap: 15px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-approved {
            background: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .status-completed {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-in-progress {
            background: #cce5ff;
            color: #004085;
        }

        .priority-badge {
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .priority-low {
            background: #d4edda;
            color: #155724;
        }

        .priority-medium {
            background: #fff3cd;
            color: #856404;
        }

        .priority-high {
            background: #f8d7da;
            color: #721c24;
        }

        .priority-urgent {
            background: #f5c6cb;
            color: #721c24;
        }

        .request-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }

        .btn {
            border-radius: 8px;
            padding: 8px 16px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--secondary-color) 0%, #5dade2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success-color) 0%, #2ecc71 100%);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--accent-color) 0%, #e67e22 100%);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(231, 76, 60, 0.4);
        }

        .btn-info {
            background: linear-gradient(135deg, var(--info-color) 0%, #5dade2 100%);
            color: white;
        }

        .btn-info:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
        }

        .btn-warning {
            background: linear-gradient(135deg, var(--warning-color) 0%, #f39c12 100%);
            color: white;
        }

        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(245, 158, 11, 0.4);
        }

        .search-filter {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: var(--shadow);
        }

        .form-control {
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 10px 15px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: white;
            color: var(--text-primary) !important;
        }

        .form-control:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
            background: white;
            color: var(--text-primary) !important;
        }

        .form-label {
            font-weight: 600;
            color: var(--text-primary) !important;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }

        .no-requests {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }

        .no-requests i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
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
            padding: 10px;
            background: white;
            border-radius: 8px;
            border-left: 4px solid var(--secondary-color);
        }

        .timeline-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--secondary-color);
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

        .modal-content {
            border-radius: 15px;
            border: none;
        }

        .modal-header {
            background: linear-gradient(135deg, var(--secondary-color) 0%, #5dade2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
        }

        .modal-title {
            font-weight: 600;
        }

        .btn-close {
            filter: invert(1);
        }

        @media (max-width: 768px) {
            .page-title {
                font-size: 2rem;
            }
            
            .card-body {
                padding: 20px;
            }
            
            .stats-card {
                margin-bottom: 20px;
            }
            
            .request-meta {
                flex-direction: column;
                gap: 8px;
            }
            
            .request-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="main-container">
        <div class="container">
            <!-- Page Header -->
            <div class="page-header text-center">
                <h1 class="page-title">
                    <i class="fas fa-cogs me-3"></i>
                    سیستم حرفه‌ای مدیریت درخواست‌ها
                </h1>
                <p class="page-subtitle">
                    مدیریت پیشرفته و پیگیری کامل درخواست‌های کالا/خدمات
                </p>
            </div>

            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo $stats['total']; ?></div>
                        <div class="stats-title">کل درخواست‌ها</div>
                        <div class="stats-description">همه درخواست‌ها</div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="stats-card assigned">
                        <div class="stats-number"><?php echo $stats['assigned_to_me']; ?></div>
                        <div class="stats-title">ارجاع شده به من</div>
                        <div class="stats-description">نیاز به اقدام</div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo $stats['pending']; ?></div>
                        <div class="stats-title">در انتظار</div>
                        <div class="stats-description">در حال بررسی</div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo $stats['approved']; ?></div>
                        <div class="stats-title">تأیید شده</div>
                        <div class="stats-description">تأیید شده</div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo $stats['completed']; ?></div>
                        <div class="stats-title">تکمیل شده</div>
                        <div class="stats-description">تحویل داده شده</div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo $stats['rejected']; ?></div>
                        <div class="stats-title">رد شده</div>
                        <div class="stats-description">رد شده</div>
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
                                <option value="کم" <?php echo $filters['priority'] === 'کم' ? 'selected' : ''; ?>>کم</option>
                                <option value="متوسط" <?php echo $filters['priority'] === 'متوسط' ? 'selected' : ''; ?>>متوسط</option>
                                <option value="بالا" <?php echo $filters['priority'] === 'بالا' ? 'selected' : ''; ?>>بالا</option>
                                <option value="فوری" <?php echo $filters['priority'] === 'فوری' ? 'selected' : ''; ?>>فوری</option>
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
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-list me-2"></i>
                    لیست درخواست‌ها
                    <span class="badge bg-light text-dark ms-2"><?php echo count($requests); ?> درخواست</span>
                </div>
                <div class="card-body">
                    <?php if (empty($requests)): ?>
                        <div class="no-requests">
                            <i class="fas fa-inbox"></i>
                            <h4>هیچ درخواستی یافت نشد</h4>
                            <p>با فیلترهای انتخابی هیچ درخواستی یافت نشد.</p>
                            <a href="request_management_final.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>
                                ایجاد درخواست جدید
                            </a>
                        </div>
                    <?php else: ?>
                        <div id="requestsList">
                            <?php foreach ($requests as $request): ?>
                                <?php
                                // بررسی آیا درخواست به کاربر ارجاع شده
                                $is_assigned = false;
                                if (isset($request['assigned_users']) && $request['assigned_users']) {
                                    $assigned_users = explode(',', $request['assigned_users']);
                                    $is_assigned = in_array($_SESSION['user_id'], $assigned_users);
                                }
                                
                                // بررسی وضعیت گردش کار برای کاربر
                                if ($is_assigned) {
                                    try {
                                        $stmt = $pdo->prepare("
                                            SELECT status FROM request_workflow 
                                            WHERE request_id = ? AND assigned_to = ? 
                                            ORDER BY step_order DESC LIMIT 1
                                        ");
                                        $stmt->execute([$request['id'], $_SESSION['user_id']]);
                                        $workflow_status = $stmt->fetchColumn();
                                        $is_assigned = in_array($workflow_status, ['در انتظار', 'در حال بررسی']);
                                    } catch (Exception $e) {
                                        $is_assigned = false;
                                    }
                                }
                                ?>
                                <div class="request-item <?php echo $is_assigned ? 'assigned' : ''; ?>" 
                                     data-status="<?php echo $request['status']; ?>" 
                                     data-priority="<?php echo $request['priority']; ?>">
                                    <div class="request-header">
                                        <div>
                                            <div class="request-number"><?php echo htmlspecialchars($request['request_number']); ?></div>
                                            <div class="request-date"><?php echo date('Y/m/d H:i', strtotime($request['created_at'])); ?></div>
                                        </div>
                                        <div>
                                            <span class="status-badge status-<?php echo str_replace(' ', '-', strtolower($request['status'])); ?>">
                                                <?php echo $request['status']; ?>
                                            </span>
                                            <?php if ($is_assigned): ?>
                                                <span class="badge bg-warning ms-2">ارجاع شده به من</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="request-details">
                                        <div class="request-item-name"><?php echo htmlspecialchars($request['item_name']); ?></div>
                                        <div class="request-meta">
                                            <div class="meta-item">
                                                <i class="fas fa-user"></i>
                                                <span>درخواست‌دهنده: <?php echo htmlspecialchars($request['requester_name']); ?></span>
                                            </div>
                                            <div class="meta-item">
                                                <i class="fas fa-hashtag"></i>
                                                <span>تعداد: <?php echo $request['quantity']; ?></span>
                                            </div>
                                            <div class="meta-item">
                                                <i class="fas fa-dollar-sign"></i>
                                                <span>قیمت: <?php echo number_format($request['price']); ?> ریال</span>
                                            </div>
                                            <div class="meta-item">
                                                <i class="fas fa-exclamation-triangle"></i>
                                                <span class="priority-badge priority-<?php echo strtolower($request['priority']); ?>">
                                                    <?php echo $request['priority']; ?>
                                                </span>
                                            </div>
                                            <div class="meta-item">
                                                <i class="fas fa-paperclip"></i>
                                                <span><?php echo $request['file_count']; ?> فایل</span>
                                            </div>
                                            <?php if (isset($request['assigned_names']) && $request['assigned_names']): ?>
                                                <div class="meta-item">
                                                    <i class="fas fa-users"></i>
                                                    <span>ارجاع شده به: <?php echo htmlspecialchars($request['assigned_names']); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($request['description']): ?>
                                            <div class="mt-2">
                                                <small class="text-muted"><?php echo htmlspecialchars($request['description']); ?></small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="request-actions">
                                        <button class="btn btn-info" onclick="viewDetails(<?php echo $request['id']; ?>, <?php echo $is_assigned ? 'true' : 'false'; ?>)">
                                            <i class="fas fa-eye me-1"></i>
                                            جزئیات
                                        </button>
                                        <button class="btn btn-primary" onclick="viewWorkflow(<?php echo $request['id']; ?>)">
                                            <i class="fas fa-route me-1"></i>
                                            گردش کار
                                        </button>
                                        <?php if ($request['file_count'] > 0): ?>
                                            <button class="btn btn-success" onclick="viewFiles(<?php echo $request['id']; ?>)">
                                                <i class="fas fa-download me-1"></i>
                                                فایل‌ها
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($is_assigned): ?>
                                            <button class="btn btn-warning" onclick="updateWorkflow(<?php echo $request['id']; ?>, '<?php echo $request['status']; ?>')">
                                                <i class="fas fa-edit me-1"></i>
                                                اقدام
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for Workflow Update -->
    <div class="modal fade" id="workflowModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>
                        به‌روزرسانی وضعیت درخواست
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="workflowForm">
                        <input type="hidden" id="workflowRequestId" name="request_id">
                        <input type="hidden" name="action" value="update_workflow">
                        
                        <div class="mb-3">
                            <label class="form-label">وضعیت جدید</label>
                            <select class="form-control" id="workflowStatus" name="status" required>
                                <option value="در انتظار">در انتظار</option>
                                <option value="در حال بررسی">در حال بررسی</option>
                                <option value="تأیید شده">تأیید شده</option>
                                <option value="رد شده">رد شده</option>
                                <option value="تکمیل شده">تکمیل شده</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">اولویت</label>
                            <select class="form-control" id="workflowPriority" name="priority">
                                <option value="">تغییر اولویت (اختیاری)</option>
                                <option value="کم">کم</option>
                                <option value="متوسط">متوسط</option>
                                <option value="بالا">بالا</option>
                                <option value="فوری">فوری</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">توضیحات</label>
                            <textarea class="form-control" id="workflowComments" name="comments" rows="4" 
                                      placeholder="توضیحات خود را وارد کنید..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="button" class="btn btn-primary" onclick="submitWorkflowUpdate()">
                        <i class="fas fa-save me-1"></i>
                        ذخیره تغییرات
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for Request Details -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-info-circle me-2"></i>
                        جزئیات درخواست
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailsContent">
                    <!-- محتوا از طریق AJAX بارگذاری می‌شود -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function clearFilters() {
            document.getElementById('filterForm').reset();
            window.location.href = 'request_workflow_professional.php';
        }

        function viewDetails(requestId, isAssigned) {
            fetch(`get_request_details.php?id=${requestId}&assigned=${isAssigned}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('detailsContent').innerHTML = data;
                    new bootstrap.Modal(document.getElementById('detailsModal')).show();
                })
                .catch(error => {
                    alert('خطا در بارگذاری جزئیات: ' + error);
                });
        }

        function viewWorkflow(requestId) {
            fetch(`get_request_workflow.php?id=${requestId}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('detailsContent').innerHTML = data;
                    new bootstrap.Modal(document.getElementById('detailsModal')).show();
                })
                .catch(error => {
                    alert('خطا در بارگذاری گردش کار: ' + error);
                });
        }

        function updateWorkflow(requestId, currentStatus) {
            document.getElementById('workflowRequestId').value = requestId;
            document.getElementById('workflowStatus').value = currentStatus;
            new bootstrap.Modal(document.getElementById('workflowModal')).show();
        }

        function submitWorkflowUpdate() {
            const form = document.getElementById('workflowForm');
            const formData = new FormData(form);
            
            fetch('request_workflow_professional.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                if (data.includes('success_message')) {
                    location.reload();
                } else {
                    alert('خطا در به‌روزرسانی وضعیت');
                }
            })
            .catch(error => {
                alert('خطا در به‌روزرسانی: ' + error);
            });
        }

        function viewFiles(requestId) {
            alert('فایل‌های درخواست #' + requestId + ' - این قابلیت به زودی اضافه خواهد شد');
        }
    </script>
</body>
</html>