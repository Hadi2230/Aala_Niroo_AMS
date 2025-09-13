<?php
/**
 * system_logs.php - سیستم لاگ‌گیری پیشرفته و مدرن
 * Advanced System Logging with Modern UI
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config.php';

// هدر UTF-8 برای یکسانی نمایش
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

// فقط ادمین (هم فارسی هم انگلیسی)
$rawRole  = isset($_SESSION['role']) ? trim((string)$_SESSION['role']) : '';
$is_admin = ($rawRole === 'ادمین' || strcasecmp($rawRole, 'admin') === 0 || strcasecmp($rawRole, 'administrator') === 0);
if (empty($_SESSION['user_id']) || !$is_admin) {
    header('Location: login.php');
    exit();
}

// ایجاد جدول system_logs اگر وجود ندارد
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        action VARCHAR(100) NOT NULL,
        description TEXT,
        ip_address VARCHAR(45),
        user_agent TEXT,
        request_data JSON NULL,
        response_data JSON NULL,
        severity ENUM('info','warning','error','critical') DEFAULT 'info',
        module VARCHAR(50) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_action (action),
        INDEX idx_severity (severity),
        INDEX idx_module (module),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {
    // جدول ممکن است قبلاً وجود داشته باشد
}

// فیلترها
$user_filter   = isset($_GET['user_id']) ? (string)$_GET['user_id'] : '';
$action_filter = isset($_GET['action']) ? trim((string)$_GET['action']) : '';
$severity_filter = isset($_GET['severity']) ? trim((string)$_GET['severity']) : '';
$module_filter = isset($_GET['module']) ? trim((string)$_GET['module']) : '';
$date_from     = isset($_GET['date_from']) ? trim((string)$_GET['date_from']) : '';
$date_to       = isset($_GET['date_to']) ? trim((string)$_GET['date_to']) : '';
$search        = isset($_GET['search']) ? trim((string)$_GET['search']) : '';

// کوئری لاگ‌ها
$query  = "SELECT sl.*, u.username, u.full_name FROM system_logs sl LEFT JOIN users u ON sl.user_id = u.id WHERE 1=1";
$params = [];

if ($user_filter !== '')  { 
    $query .= " AND sl.user_id = ?";       
    $params[] = (int)$user_filter; 
}
if ($action_filter !== ''){ 
    $query .= " AND sl.action = ?";         
    $params[] = $action_filter; 
}
if ($severity_filter !== ''){ 
    $query .= " AND sl.severity = ?";         
    $params[] = $severity_filter; 
}
if ($module_filter !== ''){ 
    $query .= " AND sl.module = ?";         
    $params[] = $module_filter; 
}
if ($date_from !== '')    { 
    $query .= " AND sl.created_at >= ?";    
    $params[] = $date_from . ' 00:00:00'; 
}
if ($date_to !== '')      { 
    $query .= " AND sl.created_at <= ?";    
    $params[] = $date_to   . ' 23:59:59'; 
}
if ($search !== '') {
    $query .= " AND (sl.description LIKE ? OR sl.action LIKE ? OR u.username LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$query .= " ORDER BY sl.created_at DESC LIMIT 1000";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// داده‌های کمکی برای فیلترها
$users = $pdo->query("SELECT id, username, full_name FROM users ORDER BY username")->fetchAll();
$actions = $pdo->query("SELECT DISTINCT action FROM system_logs ORDER BY action")->fetchAll();
$modules = $pdo->query("SELECT DISTINCT module FROM system_logs WHERE module IS NOT NULL ORDER BY module")->fetchAll();

// آمار لاگ‌ها
$stats = [
    'total' => 0,
    'info' => 0,
    'warning' => 0,
    'error' => 0,
    'critical' => 0
];

try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM system_logs");
    $stats['total'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT severity, COUNT(*) as count FROM system_logs GROUP BY severity");
    while ($row = $stmt->fetch()) {
        $stats[$row['severity']] = $row['count'];
    }
} catch (Exception $e) {
    // خطا در آمار
}

// عملیات حذف لاگ‌های قدیمی
if (isset($_POST['cleanup_logs'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM system_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
        $stmt->execute();
        $cleanup_message = "لاگ‌های قدیمی‌تر از 90 روز حذف شدند";
    } catch (Exception $e) {
        $cleanup_error = "خطا در حذف لاگ‌های قدیمی: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>لاگ سیستم - اعلا نیرو</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">

    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #34495e;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --info-color: #3498db;
        }
        
        body {
            font-family: Vazirmatn, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding-top: 80px;
        }
        
        .dark-mode { 
            background: linear-gradient(135deg, #1a1a1a 0%, #2d3748 100%) !important; 
            color: #ffffff !important; 
        }
        
        .main-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            margin: 20px 0;
        }
        
        .dark-mode .main-container {
            background: rgba(45, 55, 72, 0.95);
        }
        
        .card { 
            border: none; 
            border-radius: 15px; 
            box-shadow: 0 8px 25px rgba(0,0,0,0.1); 
            margin-bottom: 20px; 
            overflow: hidden;
        }
        
        .dark-mode .card { 
            background-color: #2d3748; 
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: #fff; 
            border-radius: 15px 15px 0 0 !important;
            padding: 20px 25px;
        }
        
        .stats-card {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .stats-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: rotate(45deg);
        }
        
        .stats-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 8px;
            position: relative;
            z-index: 1;
        }
        
        .stats-label {
            font-size: 0.9rem;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }
        
        .severity-info { color: var(--info-color); }
        .severity-warning { color: var(--warning-color); }
        .severity-error { color: var(--danger-color); }
        .severity-critical { color: #8e44ad; }
        
        .badge-severity {
            font-size: 0.8rem;
            padding: 4px 8px;
            border-radius: 12px;
        }
        
        .log-item {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            margin-bottom: 10px;
            padding: 15px;
            transition: all 0.3s;
            background: white;
        }
        
        .log-item:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .dark-mode .log-item {
            background-color: #374151;
            border-color: #4b5563;
        }
        
        .log-item.error {
            border-left: 4px solid var(--danger-color);
        }
        
        .log-item.warning {
            border-left: 4px solid var(--warning-color);
        }
        
        .log-item.critical {
            border-left: 4px solid #8e44ad;
        }
        
        .log-item.info {
            border-left: 4px solid var(--info-color);
        }
        
        .truncate {
            max-width: 300px;
            white-space: nowrap; 
            overflow: hidden; 
            text-overflow: ellipsis;
        }
        
        .log-details {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
            margin-top: 10px;
            font-size: 0.85rem;
        }
        
        .dark-mode .log-details {
            background: #2d3748;
        }
        
        .filter-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .dark-mode .filter-section {
            background: #374151;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
            border-radius: 10px;
            padding: 10px 25px;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(44, 62, 80, 0.4);
        }
        
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }
        
        .table th {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border: none;
            font-weight: 600;
        }
        
        .table td {
            vertical-align: middle;
            border-color: #e9ecef;
        }
        
        .dark-mode .table td {
            border-color: #4b5563;
        }
        
        .pagination {
            justify-content: center;
        }
        
        .page-link {
            border-radius: 8px;
            margin: 0 2px;
            border: 1px solid #dee2e6;
        }
        
        .page-link:hover {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .dark-mode .empty-state {
            color: #9ca3af;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            padding: 15px 20px;
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(44, 62, 80, 0.25);
        }
        
        .module-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
        }
    </style>
</head>
<body class="<?php echo (isset($_COOKIE['theme']) && $_COOKIE['theme']==='dark') ? 'dark-mode' : ''; ?>">
    <?php include __DIR__ . '/navbar.php'; ?>

    <div class="container-fluid">
        <div class="main-container">
            <div class="container mt-4">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <h4 class="mb-0"><i class="fas fa-clipboard-list me-2"></i>لاگ سیستم</h4>
                                    <small class="opacity-75">مشاهده و تحلیل فعالیت‌های سیستم</small>
                                </div>
                                <div class="d-flex gap-2">
                                    <span class="badge bg-info">حداکثر 1000 رکورد آخر</span>
                                    <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#cleanupModal">
                                        <i class="fas fa-broom me-1"></i>پاکسازی
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (isset($cleanup_message)): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <i class="fas fa-check-circle me-2"></i><?php echo $cleanup_message; ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>

                                <?php if (isset($cleanup_error)): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo $cleanup_error; ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>

                                <!-- آمار لاگ‌ها -->
                                <div class="row mb-4">
                                    <div class="col-md-2">
                                        <div class="stats-card">
                                            <div class="stats-number"><?php echo number_format($stats['total']); ?></div>
                                            <div class="stats-label">کل لاگ‌ها</div>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="stats-card">
                                            <div class="stats-number severity-info"><?php echo number_format($stats['info']); ?></div>
                                            <div class="stats-label">اطلاعات</div>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="stats-card">
                                            <div class="stats-number severity-warning"><?php echo number_format($stats['warning']); ?></div>
                                            <div class="stats-label">هشدار</div>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="stats-card">
                                            <div class="stats-number severity-error"><?php echo number_format($stats['error']); ?></div>
                                            <div class="stats-label">خطا</div>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="stats-card">
                                            <div class="stats-number severity-critical"><?php echo number_format($stats['critical']); ?></div>
                                            <div class="stats-label">بحرانی</div>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="stats-card">
                                            <div class="stats-number"><?php echo count($logs); ?></div>
                                            <div class="stats-label">نمایش داده شده</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- فیلترها -->
                                <div class="filter-section">
                                    <form method="get" class="row g-3">
                                        <div class="col-md-2">
                                            <label class="form-label">کاربر</label>
                                            <select name="user_id" class="form-select">
                                                <option value="">همه</option>
                                                <?php foreach($users as $u): ?>
                                                    <option value="<?php echo (int)$u['id']; ?>" <?php echo ($user_filter!=='' && (int)$user_filter===(int)$u['id'])?'selected':''; ?>>
                                                        <?php echo htmlspecialchars($u['full_name'] ?: $u['username'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-2">
                                            <label class="form-label">عملیات</label>
                                            <select name="action" class="form-select">
                                                <option value="">همه</option>
                                                <?php foreach($actions as $a): $act = (string)($a['action'] ?? ''); ?>
                                                    <option value="<?php echo htmlspecialchars($act, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($action_filter!=='' && $action_filter===$act)?'selected':''; ?>>
                                                        <?php echo htmlspecialchars($act, ENT_QUOTES, 'UTF-8'); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-2">
                                            <label class="form-label">سطح اهمیت</label>
                                            <select name="severity" class="form-select">
                                                <option value="">همه</option>
                                                <option value="info" <?php echo ($severity_filter==='info')?'selected':''; ?>>اطلاعات</option>
                                                <option value="warning" <?php echo ($severity_filter==='warning')?'selected':''; ?>>هشدار</option>
                                                <option value="error" <?php echo ($severity_filter==='error')?'selected':''; ?>>خطا</option>
                                                <option value="critical" <?php echo ($severity_filter==='critical')?'selected':''; ?>>بحرانی</option>
                                            </select>
                                        </div>

                                        <div class="col-md-2">
                                            <label class="form-label">ماژول</label>
                                            <select name="module" class="form-select">
                                                <option value="">همه</option>
                                                <?php foreach($modules as $m): $mod = (string)($m['module'] ?? ''); ?>
                                                    <option value="<?php echo htmlspecialchars($mod, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($module_filter!=='' && $module_filter===$mod)?'selected':''; ?>>
                                                        <?php echo htmlspecialchars($mod, ENT_QUOTES, 'UTF-8'); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-2">
                                            <label class="form-label">از تاریخ</label>
                                            <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from, ENT_QUOTES, 'UTF-8'); ?>">
                                        </div>

                                        <div class="col-md-2">
                                            <label class="form-label">تا تاریخ</label>
                                            <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to, ENT_QUOTES, 'UTF-8'); ?>">
                                        </div>

                                        <div class="col-12">
                                            <div class="row">
                                                <div class="col-md-8">
                                                    <label class="form-label">جستجو</label>
                                                    <input type="text" name="search" class="form-control" placeholder="جستجو در توضیحات، عملیات یا نام کاربر..." value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                                                </div>
                                                <div class="col-md-4 d-flex align-items-end gap-2">
                                                    <button class="btn btn-primary" type="submit">
                                                        <i class="fas fa-filter me-1"></i>اعمال فیلتر
                                                    </button>
                                                    <a class="btn btn-secondary" href="system_logs.php">
                                                        <i class="fas fa-times me-1"></i>حذف فیلتر
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>

                                <!-- لیست لاگ‌ها -->
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover align-middle">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>کاربر</th>
                                                <th>عملیات</th>
                                                <th>سطح</th>
                                                <th>ماژول</th>
                                                <th>توضیحات</th>
                                                <th>IP</th>
                                                <th>زمان</th>
                                                <th>عملیات</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($logs): foreach($logs as $i => $row): ?>
                                                <tr class="log-item <?php echo $row['severity'] ?? 'info'; ?>">
                                                    <td><?php echo $i+1; ?></td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 30px; height: 30px; font-size: 12px;">
                                                                <?php 
                                                                $name = $row['full_name'] ?: $row['username'] ?: 'سیستم';
                                                                echo strtoupper(substr($name, 0, 1));
                                                                ?>
                                                            </div>
                                                            <div>
                                                                <div class="fw-bold"><?php echo htmlspecialchars($row['full_name'] ?: $row['username'] ?: 'سیستم', ENT_QUOTES, 'UTF-8'); ?></div>
                                                                <?php if ($row['username']): ?>
                                                                    <small class="text-muted">@<?php echo htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8'); ?></small>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info"><?php echo htmlspecialchars($row['action'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-severity severity-<?php echo $row['severity'] ?? 'info'; ?>">
                                                            <?php 
                                                            $severity_labels = [
                                                                'info' => 'اطلاعات',
                                                                'warning' => 'هشدار', 
                                                                'error' => 'خطا',
                                                                'critical' => 'بحرانی'
                                                            ];
                                                            echo $severity_labels[$row['severity']] ?? 'اطلاعات';
                                                            ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($row['module']): ?>
                                                            <span class="module-badge"><?php echo htmlspecialchars($row['module'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="truncate" title="<?php echo htmlspecialchars($row['description'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>">
                                                        <?php echo htmlspecialchars($row['description'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                                    </td>
                                                    <td>
                                                        <code><?php echo htmlspecialchars($row['ip_address'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></code>
                                                    </td>
                                                    <td>
                                                        <div><?php echo jalali_format($row['created_at'] ?? '-'); ?></div>
                                                        <small class="text-muted"><?php echo date('H:i:s', strtotime($row['created_at'] ?? '')); ?></small>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-info" onclick="showLogDetails(<?php echo $row['id']; ?>)">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; else: ?>
                                                <tr>
                                                    <td colspan="9" class="text-center text-muted py-5">
                                                        <i class="fas fa-clipboard-list fa-3x mb-3"></i>
                                                        <div>رکوردی یافت نشد</div>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal جزئیات لاگ -->
    <div class="modal fade" id="logDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-info-circle me-2"></i>جزئیات لاگ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="logDetailsContent">
                    <!-- محتوا از طریق JavaScript پر می‌شود -->
                </div>
            </div>
        </div>
    </div>

    <!-- Modal پاکسازی لاگ‌ها -->
    <div class="modal fade" id="cleanupModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-broom me-2"></i>پاکسازی لاگ‌ها</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>آیا مطمئن هستید که می‌خواهید لاگ‌های قدیمی‌تر از 90 روز را حذف کنید؟</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        این عملیات قابل بازگشت نیست!
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="cleanup_logs" value="1">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-1"></i>حذف لاگ‌های قدیمی
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showLogDetails(logId) {
            // اینجا می‌توانید AJAX call برای دریافت جزئیات لاگ اضافه کنید
            document.getElementById('logDetailsContent').innerHTML = `
                <div class="text-center">
                    <i class="fas fa-spinner fa-spin fa-2x mb-3"></i>
                    <p>در حال بارگذاری جزئیات...</p>
                </div>
            `;
            
            const modal = new bootstrap.Modal(document.getElementById('logDetailsModal'));
            modal.show();
            
            // شبیه‌سازی بارگذاری جزئیات
            setTimeout(() => {
                document.getElementById('logDetailsContent').innerHTML = `
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        جزئیات کامل لاگ با شناسه ${logId} در اینجا نمایش داده می‌شود.
                    </div>
                    <p>این بخش می‌تواند شامل اطلاعات اضافی مانند:</p>
                    <ul>
                        <li>داده‌های درخواست (Request Data)</li>
                        <li>داده‌های پاسخ (Response Data)</li>
                        <li>جزئیات خطا (Error Details)</li>
                        <li>اطلاعات مرورگر (Browser Info)</li>
                    </ul>
                `;
            }, 1000);
        }

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