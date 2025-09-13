<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// بررسی دسترسی
if (!hasPermission('notifications.view')) {
    header('Location: dashboard.php');
    exit();
}

// پردازش درخواست‌ها
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    verifyCsrfToken();
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'mark_as_read':
                try {
                    $result = markNotificationAsRead($pdo, (int)$_POST['notification_id']);
                    if ($result) {
                        $success_message = 'اعلان به عنوان خوانده شده علامت‌گذاری شد';
                    } else {
                        $error_message = 'خطا در به‌روزرسانی وضعیت اعلان';
                    }
                } catch (Exception $e) {
                    $error_message = 'خطا در به‌روزرسانی وضعیت اعلان: ' . $e->getMessage();
                }
                break;
                
            case 'mark_all_read':
                try {
                    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
                    $result = $stmt->execute([$_SESSION['user_id']]);
                    if ($result) {
                        $success_message = 'همه اعلان‌ها به عنوان خوانده شده علامت‌گذاری شدند';
                    } else {
                        $error_message = 'خطا در به‌روزرسانی وضعیت اعلان‌ها';
                    }
                } catch (Exception $e) {
                    $error_message = 'خطا در به‌روزرسانی وضعیت اعلان‌ها: ' . $e->getMessage();
                }
                break;
        }
    }
}

// دریافت فیلترها
$filter = $_GET['filter'] ?? 'all';
$type_filter = $_GET['type'] ?? '';

// ساخت کوئری
$where_conditions = ["n.user_id = ?"];
$params = [$_SESSION['user_id']];

if ($filter === 'unread') {
    $where_conditions[] = "n.is_read = 0";
} elseif ($filter === 'read') {
    $where_conditions[] = "n.is_read = 1";
}

if (!empty($type_filter)) {
    $where_conditions[] = "n.type = ?";
    $params[] = $type_filter;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// دریافت اعلان‌ها
try {
    $notifications_query = "
        SELECT n.*
        FROM notifications n
        $where_clause
        ORDER BY n.created_at DESC
    ";
    
    $stmt = $pdo->prepare($notifications_query);
    $stmt->execute($params);
    $notifications = $stmt->fetchAll();
} catch (Exception $e) {
    $error_message = 'خطا در دریافت اعلان‌ها: ' . $e->getMessage();
    $notifications = [];
}

// دریافت آمار
try {
    $stats = [
        'total' => $pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = " . $_SESSION['user_id'])->fetchColumn(),
        'unread' => $pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = " . $_SESSION['user_id'] . " AND is_read = 0")->fetchColumn(),
        'read' => $pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = " . $_SESSION['user_id'] . " AND is_read = 1")->fetchColumn(),
        'ticket' => $pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = " . $_SESSION['user_id'] . " AND type = 'ticket'")->fetchColumn(),
        'maintenance' => $pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = " . $_SESSION['user_id'] . " AND type = 'maintenance'")->fetchColumn(),
        'message' => $pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = " . $_SESSION['user_id'] . " AND type = 'message'")->fetchColumn()
    ];
} catch (Exception $e) {
    $stats = ['total' => 0, 'unread' => 0, 'read' => 0, 'ticket' => 0, 'maintenance' => 0, 'message' => 0];
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اعلان‌ها - شرکت اعلا نیرو</title>
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
        .notification-item { border-left: 4px solid #3498db; }
        .notification-item.unread { border-left-color: #e74c3c; background-color: #fff5f5; }
        .notification-item.ticket { border-left-color: #3498db; }
        .notification-item.maintenance { border-left-color: #f39c12; }
        .notification-item.message { border-left-color: #9b59b6; }
        .notification-item.system { border-left-color: #95a5a6; }
        .stats-card { text-align: center; padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 12px; }
        .stats-number { font-size: 2rem; font-weight: bold; margin-bottom: 5px; }
        .stats-label { font-size: 0.9rem; opacity: 0.9; }
        .filter-buttons .btn { margin-left: 5px; margin-bottom: 5px; }
        .search-box { max-width: 300px; }
        .notification-actions { margin-top: 10px; }
        .notification-actions .btn { margin-left: 5px; }
        .type-badge { font-size: 0.75rem; padding: 2px 6px; }
        .unread-badge { font-size: 0.75rem; padding: 2px 6px; }
    </style>
</head>
<body class="<?php echo isset($_COOKIE['theme']) && $_COOKIE['theme']==='dark' ? 'dark-mode' : ''; ?>">
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-bell"></i> اعلان‌ها</span>
                        <div>
                            <button class="btn btn-light btn-sm" onclick="markAllAsRead()">
                                <i class="fas fa-check-double"></i> همه را خوانده کن
                            </button>
                        </div>
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

                        <!-- آمار اعلان‌ها -->
                        <div class="row mb-4">
                            <div class="col-md-2">
                                <div class="stats-card">
                                    <div class="stats-number"><?php echo $stats['total']; ?></div>
                                    <div class="stats-label">کل اعلان‌ها</div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stats-card">
                                    <div class="stats-number"><?php echo $stats['unread']; ?></div>
                                    <div class="stats-label">خوانده نشده</div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stats-card">
                                    <div class="stats-number"><?php echo $stats['read']; ?></div>
                                    <div class="stats-label">خوانده شده</div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stats-card">
                                    <div class="stats-number"><?php echo $stats['ticket']; ?></div>
                                    <div class="stats-label">تیکت</div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stats-card">
                                    <div class="stats-number"><?php echo $stats['maintenance']; ?></div>
                                    <div class="stats-label">تعمیرات</div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stats-card">
                                    <div class="stats-number"><?php echo $stats['message']; ?></div>
                                    <div class="stats-label">پیام</div>
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
                                    <a href="?filter=unread" class="btn btn-outline-danger <?php echo $filter == 'unread' ? 'active' : ''; ?>">
                                        <i class="fas fa-envelope"></i> خوانده نشده
                                    </a>
                                    <a href="?filter=read" class="btn btn-outline-success <?php echo $filter == 'read' ? 'active' : ''; ?>">
                                        <i class="fas fa-envelope-open"></i> خوانده شده
                                    </a>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <form method="GET" class="d-flex">
                                    <input type="hidden" name="filter" value="<?php echo $filter; ?>">
                                    <select class="form-select" name="type" onchange="this.form.submit()">
                                        <option value="">همه انواع</option>
                                        <option value="ticket" <?php echo $type_filter === 'ticket' ? 'selected' : ''; ?>>تیکت</option>
                                        <option value="maintenance" <?php echo $type_filter === 'maintenance' ? 'selected' : ''; ?>>تعمیرات</option>
                                        <option value="message" <?php echo $type_filter === 'message' ? 'selected' : ''; ?>>پیام</option>
                                        <option value="system" <?php echo $type_filter === 'system' ? 'selected' : ''; ?>>سیستم</option>
                                    </select>
                                </form>
                            </div>
                        </div>

                        <!-- لیست اعلان‌ها -->
                        <div class="notification-list">
                            <?php if ($notifications): ?>
                                <div class="list-group">
                                    <?php foreach ($notifications as $notification): ?>
                                        <div class="list-group-item notification-item <?php echo $notification['type']; ?> <?php echo $notification['is_read'] ? '' : 'unread'; ?>">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <h6 class="mb-0"><?php echo htmlspecialchars($notification['title']); ?></h6>
                                                        <div>
                                                            <span class="badge type-badge bg-<?php echo $notification['type'] === 'ticket' ? 'primary' : ($notification['type'] === 'maintenance' ? 'warning' : ($notification['type'] === 'message' ? 'info' : 'secondary')); ?>">
                                                                <?php 
                                                                $type_labels = [
                                                                    'ticket' => 'تیکت',
                                                                    'maintenance' => 'تعمیرات',
                                                                    'message' => 'پیام',
                                                                    'system' => 'سیستم'
                                                                ];
                                                                echo $type_labels[$notification['type']] ?? $notification['type'];
                                                                ?>
                                                            </span>
                                                            <?php if (!$notification['is_read']): ?>
                                                                <span class="badge unread-badge bg-danger">جدید</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <p class="text-muted mb-2"><?php echo htmlspecialchars($notification['message']); ?></p>
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <small class="text-muted">
                                                                <i class="fa fa-user"></i> فرستنده: سیستم<br>
                                                                <i class="fa fa-calendar"></i> تاریخ: <?php echo jalali_format($notification['created_at']); ?>
                                                            </small>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <small class="text-muted">
                                                                <i class="fa fa-clock"></i> زمان: <?php echo date('H:i', strtotime($notification['created_at'])); ?><br>
                                                                <?php if ($notification['is_read']): ?>
                                                                    <i class="fa fa-check-circle text-success"></i> خوانده شده
                                                                <?php else: ?>
                                                                    <i class="fa fa-circle text-danger"></i> خوانده نشده
                                                                <?php endif; ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="notification-actions">
                                                    <?php if (!$notification['is_read']): ?>
                                                        <form method="post" style="display: inline;">
                                                            <input type="hidden" name="action" value="mark_as_read">
                                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                            <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-success">
                                                                <i class="fa fa-check"></i> خوانده شد
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fa fa-bell-slash fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">هیچ اعلانی یافت نشد.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- فرم مخفی برای علامت‌گذاری همه اعلان‌ها -->
    <form id="markAllForm" method="post" style="display: none;">
        <input type="hidden" name="action" value="mark_all_read">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function markAllAsRead() {
            if (confirm('آیا مطمئن هستید که می‌خواهید همه اعلان‌ها را به عنوان خوانده شده علامت‌گذاری کنید؟')) {
                document.getElementById('markAllForm').submit();
            }
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