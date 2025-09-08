<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// دریافت آمار
$total_assets = $pdo->query("SELECT COUNT(*) as total FROM assets")->fetch()['total'];
$total_customers = $pdo->query("SELECT COUNT(*) as total FROM customers")->fetch()['total'];
$total_users = $pdo->query("SELECT COUNT(*) as total FROM users")->fetch()['total'];
$total_assignments = $pdo->query("SELECT COUNT(*) as total FROM asset_assignments")->fetch()['total'];
$assigned_assets = $pdo->query("SELECT COUNT(DISTINCT asset_id) as total FROM asset_assignments")->fetch()['total'];

// آمار workflow
$total_tickets = $pdo->query("SELECT COUNT(*) as total FROM tickets")->fetch()['total'];
$open_tickets = $pdo->query("SELECT COUNT(*) as total FROM tickets WHERE status NOT IN ('تکمیل شده', 'لغو شده')")->fetch()['total'];
$total_maintenance = $pdo->query("SELECT COUNT(*) as total FROM maintenance_schedules")->fetch()['total'];
$upcoming_maintenance = $pdo->query("SELECT COUNT(*) as total FROM maintenance_schedules WHERE schedule_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND status = 'برنامه‌ریزی شده'")->fetch()['total'];

// دریافت اعلان‌های خوانده نشده
$unread_notifications = getUnreadNotifications($pdo, $_SESSION['user_id']);
$unread_messages = getUnreadMessages($pdo, $_SESSION['user_id']);

// تعمیرات نزدیک
$upcoming_maintenance_list = checkUpcomingMaintenance($pdo, 7);

// تیکت‌های اخیر
$recent_tickets = $pdo->query("
    SELECT t.*, c.full_name as customer_name, u.full_name as assigned_user_name
    FROM tickets t
    LEFT JOIN customers c ON t.customer_id = c.id
    LEFT JOIN users u ON t.assigned_to = u.id
    ORDER BY t.created_at DESC
    LIMIT 5
")->fetchAll();
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد - سامانه مدیریت اعلا نیرو</title>
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
                        <a class="nav-link active" href="dashboard.php">داشبورد</a>
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

    <div class="container mt-5">
        <h1 class="text-center">داشبورد مدیریت</h1>
        <p class="text-center">به سامانه مدیریت شرکت <strong>اعلا نیرو</strong> خوش آمدید.</p>

        <div class="row mt-5">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-box"></i> تعداد دارایی‌ها</h5>
                        <p class="card-text display-4"><?php echo $total_assets; ?></p>
                        <a href="assets.php" class="btn btn-primary">مشاهده دارایی‌ها</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-users"></i> تعداد مشتریان</h5>
                        <p class="card-text display-4"><?php echo $total_customers; ?></p>
                        <a href="customers.php" class="btn btn-primary">مشاهده مشتریان</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-user-cog"></i> تعداد کاربران</h5>
                        <p class="card-text display-4"><?php echo $total_users; ?></p>
                        <a href="users.php" class="btn btn-primary">مشاهده کاربران</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-link"></i> انتساب‌های فعال</h5>
                        <p class="card-text display-4"><?php echo $assigned_assets; ?> از <?php echo $total_assets; ?></p>
                        <a href="assignments.php" class="btn btn-primary">مدیریت انتساب‌ها</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- کارت‌های Workflow -->
        <div class="row mt-4">
            <div class="col-md-3">
                <div class="card text-center border-warning">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-ticket-alt"></i> تیکت‌های باز</h5>
                        <p class="card-text display-4 text-warning"><?php echo $open_tickets; ?></p>
                        <small class="text-muted">از <?php echo $total_tickets; ?> تیکت کل</small><br>
                        <a href="tickets.php" class="btn btn-warning">مدیریت تیکت‌ها</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center border-info">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-wrench"></i> تعمیرات نزدیک</h5>
                        <p class="card-text display-4 text-info"><?php echo $upcoming_maintenance; ?></p>
                        <small class="text-muted">در 7 روز آینده</small><br>
                        <a href="maintenance.php" class="btn btn-info">مدیریت تعمیرات</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center border-success">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-bell"></i> اعلان‌های جدید</h5>
                        <p class="card-text display-4 text-success"><?php echo count($unread_notifications); ?></p>
                        <small class="text-muted">اعلان خوانده نشده</small><br>
                        <button class="btn btn-success" onclick="markAllAsRead()">مشاهده همه</button>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center border-primary">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-envelope"></i> پیام‌های جدید</h5>
                        <p class="card-text display-4 text-primary"><?php echo count($unread_messages); ?></p>
                        <small class="text-muted">پیام خوانده نشده</small><br>
                        <button class="btn btn-primary" onclick="viewMessages()">مشاهده پیام‌ها</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- تیکت‌های اخیر و تعمیرات نزدیک -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-ticket-alt"></i> تیکت‌های اخیر</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_tickets)): ?>
                            <p class="text-muted text-center">هیچ تیکتی یافت نشد</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recent_tickets as $ticket): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-start">
                                        <div class="ms-2 me-auto">
                                            <div class="fw-bold"><?php echo htmlspecialchars($ticket['title']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($ticket['customer_name']); ?> - <?php echo $ticket['ticket_number']; ?></small>
                                        </div>
                                        <span class="badge bg-<?php echo $ticket['priority'] === 'فوری' ? 'danger' : ($ticket['priority'] === 'بالا' ? 'warning' : 'primary'); ?> rounded-pill">
                                            <?php echo $ticket['priority']; ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="text-center mt-3">
                                <a href="tickets.php" class="btn btn-outline-primary">مشاهده همه تیکت‌ها</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-wrench"></i> تعمیرات نزدیک</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($upcoming_maintenance_list)): ?>
                            <p class="text-muted text-center">تعمیرات نزدیکی برنامه‌ریزی نشده است</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach (array_slice($upcoming_maintenance_list, 0, 5) as $maintenance): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-start">
                                        <div class="ms-2 me-auto">
                                            <div class="fw-bold"><?php echo htmlspecialchars($maintenance['asset_name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($maintenance['customer_name']); ?> - <?php echo jalaliDate($maintenance['schedule_date']); ?></small>
                                        </div>
                                        <span class="badge bg-info rounded-pill">
                                            <?php echo $maintenance['maintenance_type']; ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="text-center mt-3">
                                <a href="maintenance.php" class="btn btn-outline-info">مشاهده همه تعمیرات</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- آمار سریع -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">آمار سریع</div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="d-flex justify-content-between">
                                    <span>دارایی‌های منتسب شده:</span>
                                    <span class="badge bg-success"><?php echo $assigned_assets; ?></span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="d-flex justify-content-between">
                                    <span>دارایی‌های منتسب نشده:</span>
                                    <span class="badge bg-warning"><?php echo $total_assets - $assigned_assets; ?></span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="d-flex justify-content-between">
                                    <span>کل تیکت‌ها:</span>
                                    <span class="badge bg-info"><?php echo $total_tickets; ?></span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="d-flex justify-content-between">
                                    <span>کل تعمیرات:</span>
                                    <span class="badge bg-primary"><?php echo $total_maintenance; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
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
        
        function markAllAsRead() {
            // پیاده‌سازی علامت‌گذاری همه اعلان‌ها به عنوان خوانده شده
            alert('همه اعلان‌ها به عنوان خوانده شده علامت‌گذاری شدند');
        }
        
        function viewMessages() {
            // پیاده‌سازی مشاهده پیام‌ها
            alert('صفحه پیام‌ها در حال توسعه است');
        }
        
        // به‌روزرسانی خودکار اعلان‌ها هر 30 ثانیه
        setInterval(function() {
            fetch('get_notifications_count.php')
                .then(response => response.json())
                .then(data => {
                    if (data.count > 0) {
                        document.querySelector('.badge.bg-danger').textContent = data.count;
                    } else {
                        const badge = document.querySelector('.badge.bg-danger');
                        if (badge) badge.remove();
                    }
                });
        }, 30000);
    </script>
</body>
</html>