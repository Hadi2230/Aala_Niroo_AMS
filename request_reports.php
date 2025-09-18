<?php
/**
 * request_reports.php - سیستم گزارش‌گیری کامل و حرفه‌ای درخواست‌ها
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'config_simple_fixed.php';

$page_title = 'گزارش‌های درخواست‌ها';

// دریافت فیلترها
$filters = [
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'status' => $_GET['status'] ?? '',
    'priority' => $_GET['priority'] ?? '',
    'user_id' => $_GET['user_id'] ?? '',
    'department' => $_GET['department'] ?? ''
];

// تنظیم تاریخ پیش‌فرض (آخرین 30 روز)
if (empty($filters['date_from'])) {
    $filters['date_from'] = date('Y-m-01'); // اول ماه
}
if (empty($filters['date_to'])) {
    $filters['date_to'] = date('Y-m-d'); // امروز
}

// ساخت شرط‌های WHERE
$where_conditions = [];
$params = [];

if (!empty($filters['date_from'])) {
    $where_conditions[] = "r.created_at >= ?";
    $params[] = $filters['date_from'] . ' 00:00:00';
}

if (!empty($filters['date_to'])) {
    $where_conditions[] = "r.created_at <= ?";
    $params[] = $filters['date_to'] . ' 23:59:59';
}

if (!empty($filters['status'])) {
    $where_conditions[] = "r.status = ?";
    $params[] = $filters['status'];
}

if (!empty($filters['priority'])) {
    $where_conditions[] = "r.priority = ?";
    $params[] = $filters['priority'];
}

if (!empty($filters['user_id'])) {
    $where_conditions[] = "r.requester_id = ?";
    $params[] = $filters['user_id'];
}

if (!empty($filters['department'])) {
    $where_conditions[] = "rw.department = ?";
    $params[] = $filters['department'];
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// آمار کلی
$stats = [];
try {
    // تعداد کل درخواست‌ها
    $sql = "SELECT COUNT(*) as total FROM requests r LEFT JOIN request_workflow rw ON r.id = rw.request_id $where_clause";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $stats['total'] = $stmt->fetchColumn();

    // آمار بر اساس وضعیت
    $sql = "SELECT status, COUNT(*) as count FROM requests r LEFT JOIN request_workflow rw ON r.id = rw.request_id $where_clause GROUP BY status";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $status_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $stats['pending'] = $status_stats['در انتظار تأیید'] ?? 0;
    $stats['in_progress'] = $status_stats['در حال بررسی'] ?? 0;
    $stats['approved'] = $status_stats['تأیید شده'] ?? 0;
    $stats['rejected'] = $status_stats['رد شده'] ?? 0;
    $stats['completed'] = $status_stats['تکمیل شده'] ?? 0;

    // آمار بر اساس اولویت
    $sql = "SELECT priority, COUNT(*) as count FROM requests r LEFT JOIN request_workflow rw ON r.id = rw.request_id $where_clause GROUP BY priority";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $priority_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $stats['priority_low'] = $priority_stats['کم'] ?? 0;
    $stats['priority_medium'] = $priority_stats['متوسط'] ?? 0;
    $stats['priority_high'] = $priority_stats['بالا'] ?? 0;
    $stats['priority_urgent'] = $priority_stats['فوری'] ?? 0;

    // میانگین زمان پردازش
    $sql = "SELECT AVG(TIMESTAMPDIFF(HOUR, r.created_at, COALESCE(rw.action_date, NOW()))) as avg_hours 
            FROM requests r 
            LEFT JOIN request_workflow rw ON r.id = rw.request_id 
            $where_clause AND rw.action_date IS NOT NULL";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $stats['avg_processing_hours'] = round($stmt->fetchColumn() ?? 0, 1);

    // مجموع قیمت درخواست‌ها
    $sql = "SELECT SUM(price) as total_price FROM requests r LEFT JOIN request_workflow rw ON r.id = rw.request_id $where_clause";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $stats['total_price'] = $stmt->fetchColumn() ?? 0;

} catch (Exception $e) {
    error_log("Error fetching stats: " . $e->getMessage());
    $stats = array_fill_keys(['total', 'pending', 'in_progress', 'approved', 'rejected', 'completed', 'priority_low', 'priority_medium', 'priority_high', 'priority_urgent', 'avg_processing_hours', 'total_price'], 0);
}

// گزارش تفصیلی
$detailed_reports = [];
try {
    $sql = "
        SELECT 
            r.*,
            u.full_name as requester_full_name,
            rw.department,
            rw.status as workflow_status,
            rw.action_date,
            COUNT(rf.id) as file_count
        FROM requests r
        LEFT JOIN users u ON r.requester_id = u.id
        LEFT JOIN request_workflow rw ON r.id = rw.request_id
        LEFT JOIN request_files rf ON r.id = rf.request_id
        $where_clause
        GROUP BY r.id
        ORDER BY r.created_at DESC
        LIMIT 100
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $detailed_reports = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching detailed reports: " . $e->getMessage());
    $detailed_reports = [];
}

// آمار کاربران
$user_stats = [];
try {
    $sql = "
        SELECT 
            u.id,
            u.full_name,
            u.username,
            COUNT(r.id) as request_count,
            SUM(r.price) as total_price
        FROM users u
        LEFT JOIN requests r ON u.id = r.requester_id
        $where_clause
        GROUP BY u.id, u.full_name, u.username
        ORDER BY request_count DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $user_stats = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching user stats: " . $e->getMessage());
    $user_stats = [];
}

// آمار واحدها
$department_stats = [];
try {
    $sql = "
        SELECT 
            rw.department,
            COUNT(DISTINCT r.id) as request_count,
            AVG(TIMESTAMPDIFF(HOUR, r.created_at, COALESCE(rw.action_date, NOW()))) as avg_processing_hours
        FROM request_workflow rw
        LEFT JOIN requests r ON rw.request_id = r.id
        $where_clause
        GROUP BY rw.department
        ORDER BY request_count DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $department_stats = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching department stats: " . $e->getMessage());
    $department_stats = [];
}

// دریافت لیست کاربران برای فیلتر
$users = [];
try {
    $stmt = $pdo->query("SELECT id, full_name, username FROM users WHERE is_active = 1 ORDER BY full_name");
    $users = $stmt->fetchAll();
} catch (Exception $e) {
    $users = [];
}

// دریافت لیست واحدها برای فیلتر
$departments = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT department FROM request_workflow WHERE department IS NOT NULL ORDER BY department");
    $departments = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $departments = [];
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        .stats-card.success {
            background: linear-gradient(135deg, var(--success-color) 0%, #2ecc71 100%);
        }

        .stats-card.warning {
            background: linear-gradient(135deg, var(--warning-color) 0%, #f39c12 100%);
        }

        .stats-card.danger {
            background: linear-gradient(135deg, var(--accent-color) 0%, #e67e22 100%);
        }

        .stats-card.info {
            background: linear-gradient(135deg, var(--info-color) 0%, #5dade2 100%);
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

        .chart-container {
            position: relative;
            height: 400px;
            margin: 20px 0;
        }

        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background: var(--primary-color);
            color: white;
            border: none;
            font-weight: 600;
        }

        .table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .filter-section {
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

        .btn {
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 600;
            font-size: 0.95rem;
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

        .export-buttons {
            margin-bottom: 20px;
        }

        .export-buttons .btn {
            margin-left: 10px;
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
            
            .chart-container {
                height: 300px;
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
                    <i class="fas fa-chart-bar me-3"></i>
                    گزارش‌های درخواست‌ها
                </h1>
                <p class="page-subtitle">
                    آمار و تحلیل کامل درخواست‌های کالا/خدمات
                </p>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <form method="GET" id="filterForm">
                    <div class="row">
                        <div class="col-md-3">
                            <label class="form-label">از تاریخ</label>
                            <input type="date" class="form-control" name="date_from" value="<?php echo $filters['date_from']; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">تا تاریخ</label>
                            <input type="date" class="form-control" name="date_to" value="<?php echo $filters['date_to']; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">وضعیت</label>
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
                            <label class="form-label">اولویت</label>
                            <select class="form-control" name="priority">
                                <option value="">همه اولویت‌ها</option>
                                <option value="کم" <?php echo $filters['priority'] === 'کم' ? 'selected' : ''; ?>>کم</option>
                                <option value="متوسط" <?php echo $filters['priority'] === 'متوسط' ? 'selected' : ''; ?>>متوسط</option>
                                <option value="بالا" <?php echo $filters['priority'] === 'بالا' ? 'selected' : ''; ?>>بالا</option>
                                <option value="فوری" <?php echo $filters['priority'] === 'فوری' ? 'selected' : ''; ?>>فوری</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-1"></i>
                                    اعمال فیلتر
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Export Buttons -->
            <div class="export-buttons text-end">
                <button class="btn btn-success" onclick="exportToExcel()">
                    <i class="fas fa-file-excel me-1"></i>
                    خروجی Excel
                </button>
                <button class="btn btn-info" onclick="exportToPDF()">
                    <i class="fas fa-file-pdf me-1"></i>
                    خروجی PDF
                </button>
                <button class="btn btn-warning" onclick="printReport()">
                    <i class="fas fa-print me-1"></i>
                    چاپ گزارش
                </button>
            </div>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo number_format($stats['total']); ?></div>
                        <div class="stats-title">کل درخواست‌ها</div>
                        <div class="stats-description">تعداد کل</div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="stats-card warning">
                        <div class="stats-number"><?php echo number_format($stats['pending'] + $stats['in_progress']); ?></div>
                        <div class="stats-title">در انتظار</div>
                        <div class="stats-description">نیاز به اقدام</div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="stats-card success">
                        <div class="stats-number"><?php echo number_format($stats['approved'] + $stats['completed']); ?></div>
                        <div class="stats-title">تأیید شده</div>
                        <div class="stats-description">موفق</div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="stats-card danger">
                        <div class="stats-number"><?php echo number_format($stats['rejected']); ?></div>
                        <div class="stats-title">رد شده</div>
                        <div class="stats-description">ناموفق</div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="stats-card info">
                        <div class="stats-number"><?php echo $stats['avg_processing_hours']; ?></div>
                        <div class="stats-title">میانگین زمان</div>
                        <div class="stats-description">ساعت</div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo number_format($stats['total_price']); ?></div>
                        <div class="stats-title">مجموع قیمت</div>
                        <div class="stats-description">ریال</div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row mb-4">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-chart-pie me-2"></i>
                            توزیع بر اساس وضعیت
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="statusChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-chart-bar me-2"></i>
                            توزیع بر اساس اولویت
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="priorityChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Reports -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-table me-2"></i>
                    گزارش تفصیلی درخواست‌ها
                    <span class="badge bg-light text-dark ms-2"><?php echo count($detailed_reports); ?> درخواست</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>شماره درخواست</th>
                                    <th>درخواست‌دهنده</th>
                                    <th>نام آیتم</th>
                                    <th>تعداد</th>
                                    <th>قیمت</th>
                                    <th>اولویت</th>
                                    <th>وضعیت</th>
                                    <th>واحد</th>
                                    <th>تاریخ ایجاد</th>
                                    <th>فایل‌ها</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($detailed_reports as $report): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($report['request_number']); ?></td>
                                        <td><?php echo htmlspecialchars($report['requester_full_name'] ?: $report['requester_name']); ?></td>
                                        <td><?php echo htmlspecialchars($report['item_name']); ?></td>
                                        <td><?php echo $report['quantity']; ?></td>
                                        <td><?php echo number_format($report['price']); ?> ریال</td>
                                        <td>
                                            <span class="badge priority-<?php echo strtolower($report['priority']); ?>">
                                                <?php echo $report['priority']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge status-<?php echo str_replace(' ', '-', strtolower($report['status'])); ?>">
                                                <?php echo $report['status']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($report['department'] ?? '-'); ?></td>
                                        <td><?php echo date('Y/m/d H:i', strtotime($report['created_at'])); ?></td>
                                        <td>
                                            <?php if ($report['file_count'] > 0): ?>
                                                <span class="badge bg-info"><?php echo $report['file_count']; ?> فایل</span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- User Statistics -->
            <div class="row">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-users me-2"></i>
                            آمار کاربران
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>کاربر</th>
                                            <th>تعداد درخواست</th>
                                            <th>مجموع قیمت</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($user_stats as $user): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></td>
                                                <td><?php echo $user['request_count']; ?></td>
                                                <td><?php echo number_format($user['total_price']); ?> ریال</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-building me-2"></i>
                            آمار واحدها
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>واحد</th>
                                            <th>تعداد درخواست</th>
                                            <th>میانگین زمان</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($department_stats as $dept): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($dept['department']); ?></td>
                                                <td><?php echo $dept['request_count']; ?></td>
                                                <td><?php echo round($dept['avg_processing_hours'], 1); ?> ساعت</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // نمودار وضعیت
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['در انتظار تأیید', 'در حال بررسی', 'تأیید شده', 'رد شده', 'تکمیل شده'],
                datasets: [{
                    data: [
                        <?php echo $stats['pending']; ?>,
                        <?php echo $stats['in_progress']; ?>,
                        <?php echo $stats['approved']; ?>,
                        <?php echo $stats['rejected']; ?>,
                        <?php echo $stats['completed']; ?>
                    ],
                    backgroundColor: [
                        '#ffc107',
                        '#17a2b8',
                        '#28a745',
                        '#dc3545',
                        '#6f42c1'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // نمودار اولویت
        const priorityCtx = document.getElementById('priorityChart').getContext('2d');
        new Chart(priorityCtx, {
            type: 'bar',
            data: {
                labels: ['کم', 'متوسط', 'بالا', 'فوری'],
                datasets: [{
                    label: 'تعداد درخواست‌ها',
                    data: [
                        <?php echo $stats['priority_low']; ?>,
                        <?php echo $stats['priority_medium']; ?>,
                        <?php echo $stats['priority_high']; ?>,
                        <?php echo $stats['priority_urgent']; ?>
                    ],
                    backgroundColor: [
                        '#28a745',
                        '#ffc107',
                        '#fd7e14',
                        '#dc3545'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // توابع خروجی
        function exportToExcel() {
            // ایجاد جدول HTML برای Excel
            const table = document.querySelector('.table');
            const wb = XLSX.utils.table_to_book(table);
            XLSX.writeFile(wb, 'گزارش_درخواست‌ها.xlsx');
        }

        function exportToPDF() {
            window.print();
        }

        function printReport() {
            window.print();
        }

        // استایل‌های اضافی برای چاپ
        const printStyles = `
            @media print {
                .main-container { padding-top: 0; }
                .page-header { margin-bottom: 20px; }
                .btn, .export-buttons { display: none; }
                .card { box-shadow: none; border: 1px solid #ddd; }
            }
        `;
        
        const styleSheet = document.createElement("style");
        styleSheet.textContent = printStyles;
        document.head.appendChild(styleSheet);
    </script>
    
    <style>
        .priority-low { background: #d4edda; color: #155724; }
        .priority-medium { background: #fff3cd; color: #856404; }
        .priority-high { background: #f8d7da; color: #721c24; }
        .priority-urgent { background: #f5c6cb; color: #721c24; }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-completed { background: #d1ecf1; color: #0c5460; }
        .status-in-progress { background: #cce5ff; color: #004085; }
    </style>
</body>
</html>