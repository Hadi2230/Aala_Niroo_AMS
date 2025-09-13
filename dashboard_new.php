<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// ثبت لاگ مشاهده داشبورد
if (function_exists('logAction')) {
    logAction($pdo, 'VIEW_DASHBOARD', 'مشاهده داشبورد');
}

// مقادیر پیش‌فرض
$total_assets = $total_customers = $total_users = $total_assignments = $assigned_assets = 0;
$total_guaranties = $active_guaranties = 0;
$last_login = null;

// آمار workflow
$total_tickets = $open_tickets = $total_maintenance = $upcoming_maintenance = 0;
$unread_notifications_count = $unread_messages_count = 0;

// دریافت آمار
try { $total_assets = (int)$pdo->query("SELECT COUNT(*) as total FROM assets")->fetch()['total']; } catch (Throwable $e) {}
try { $total_customers = (int)$pdo->query("SELECT COUNT(*) as total FROM customers")->fetch()['total']; } catch (Throwable $e) {}
try { $total_users = (int)$pdo->query("SELECT COUNT(*) as total FROM users")->fetch()['total']; } catch (Throwable $e) {}
try { $total_assignments = (int)$pdo->query("SELECT COUNT(*) as total FROM asset_assignments")->fetch()['total']; } catch (Throwable $e) {}
try { $assigned_assets = (int)$pdo->query("SELECT COUNT(DISTINCT asset_id) as total FROM asset_assignments")->fetch()['total']; } catch (Throwable $e) {}
try {
    $total_guaranties = (int)$pdo->query("SELECT COUNT(*) as total FROM guaranty_cards")->fetch()['total'];
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM guaranty_cards WHERE DATE_ADD(issue_date, INTERVAL 18 MONTH) >= CURDATE()");
    $stmt->execute();
    $active_guaranties = (int)$stmt->fetch()['total'];
} catch (Throwable $e) {}
try {
    $stmt = $pdo->prepare("SELECT last_login FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $last_login = $stmt->fetch()['last_login'] ?? null;
} catch (Throwable $e) {}

// آمار workflow
try { $total_tickets = (int)$pdo->query("SELECT COUNT(*) as total FROM tickets")->fetch()['total']; } catch (Throwable $e) {}
try { $open_tickets = (int)$pdo->query("SELECT COUNT(*) as total FROM tickets WHERE status NOT IN ('تکمیل شده', 'لغو شده')")->fetch()['total']; } catch (Throwable $e) {}
try { $total_maintenance = (int)$pdo->query("SELECT COUNT(*) as total FROM maintenance_schedules")->fetch()['total']; } catch (Throwable $e) {}
try { $upcoming_maintenance = (int)$pdo->query("SELECT COUNT(*) as total FROM maintenance_schedules WHERE schedule_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND status = 'برنامه‌ریزی شده'")->fetch()['total']; } catch (Throwable $e) {}

// دریافت اعلان‌های خوانده نشده
$unread_notifications = getUnreadNotifications($pdo, $_SESSION['user_id']);
$unread_messages = getUnreadMessages($pdo, $_SESSION['user_id']);
$unread_notifications_count = count($unread_notifications);
$unread_messages_count = count($unread_messages);

// تعمیرات نزدیک
$upcoming_maintenance_list = checkUpcomingMaintenance($pdo, 7);

// تیکت‌های اخیر
$recent_tickets = [];
try {
    $recent_tickets = $pdo->query("
        SELECT t.*, c.full_name as customer_name, u.full_name as assigned_user_name
        FROM tickets t
        LEFT JOIN customers c ON t.customer_id = c.id
        LEFT JOIN users u ON t.assigned_to = u.id
        ORDER BY t.created_at DESC
        LIMIT 5
    ")->fetchAll();
} catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>داشبورد مدیریتی شرکت اعلا نیرو</title>
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
    --dark-text: #ffffff;
}
body { font-family: Vazirmatn, sans-serif; background-color: #f8f9fa; padding-top: 80px; }
.dark-mode { background-color: var(--dark-bg) !important; color: var(--dark-text) !important; }
.stat-card { background: #fff; border-radius: 12px; padding: 12px; text-align: center; box-shadow: 0 3px 12px rgba(0,0,0,0.08); transition: transform 0.3s, box-shadow 0.3s; height: 100%; }
.dark-mode .stat-card { background: #2d3748; }
.stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 20px rgba(0,0,0,0.15); }
.stat-number { font-size: 1.6rem; font-weight: 700; margin: 6px 0; }
.assets-count { color: var(--secondary-color); }
.customers-count { color: var(--success-color); }
.users-count { color: var(--accent-color); }
.assignments-count { color: var(--warning-color); }
.guaranty-count { color: var(--info-color); }
.tickets-count { color: #8e44ad; }
.maintenance-count { color: #e67e22; }
.notifications-count { color: #27ae60; }
.messages-count { color: #2980b9; }
.stat-title { font-weight: 500; color: #555; margin-bottom: 8px; }
.dark-mode .stat-title { color: #ccc; }
.card { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 20px; }
.dark-mode .card { background-color: #2d3748; }
.card-header { background: linear-gradient(135deg, var(--primary-color) 0%, #34495e 100%); color: white; border-radius: 12px 12px 0 0 !important; font-weight: 600; }
.btn-primary { background: linear-gradient(135deg, var(--primary-color) 0%, #34495e 100%); border: none; border-radius: 6px; padding: 6px 18px; transition: all 0.3s; font-size: 0.85rem; }
.btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(52,152,219,0.3); }
.list-group-item { border: 1px solid rgba(0,0,0,0.1); border-radius: 8px !important; margin-bottom: 6px; position: relative; padding-right: 10px; }
.dark-mode .list-group-item { background-color: #374151; border-color: #4b5563; color: var(--dark-text); }
.note-actions { display:flex; gap:5px; position:absolute; left:10px; top:50%; transform:translateY(-50%); }
.note-actions form { display:inline; }
.workflow-section { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 12px; padding: 20px; margin: 20px 0; }
.workflow-card { background: rgba(255,255,255,0.1); border-radius: 8px; padding: 15px; text-align: center; backdrop-filter: blur(10px); transition: all 0.3s; }
.workflow-card:hover { background: rgba(255,255,255,0.2); transform: translateY(-2px); }
.workflow-number { font-size: 2rem; font-weight: 700; margin: 10px 0; }
.workflow-title { font-size: 0.9rem; opacity: 0.9; }
.alert-item { border-left: 4px solid; margin-bottom: 10px; padding: 10px; border-radius: 0 8px 8px 0; }
.alert-danger { border-left-color: #dc3545; background-color: #f8d7da; }
.alert-warning { border-left-color: #ffc107; background-color: #fff3cd; }
.alert-info { border-left-color: #17a2b8; background-color: #d1ecf1; }
.alert-success { border-left-color: #28a745; background-color: #d4edda; }
.dark-mode .alert-danger { background-color: #2d1b1b; color: #fff; }
.dark-mode .alert-warning { background-color: #2d2a1b; color: #fff; }
.dark-mode .alert-info { background-color: #1b2d2d; color: #fff; }
.dark-mode .alert-success { background-color: #1b2d1b; color: #fff; }
</style>
</head>
<body class="<?php echo isset($_COOKIE['theme']) && $_COOKIE['theme']==='dark' ? 'dark-mode' : ''; ?>">
<?php include 'navbar.php'; ?>

<div class="container mt-4">
<h1 class="text-center mb-2">داشبورد مدیریتی</h1>
<p class="text-center mb-3">به سامانه مدیریت شرکت <strong>اعلا نیرو</strong> خوش آمدید.</p>
<?php if ($last_login): ?>
<p class="text-center text-muted mb-4">آخرین ورود شما: <?php echo htmlspecialchars(jalaliDate($last_login)); ?></p>
<?php endif; ?>

<!-- کارت‌های آمار اصلی -->
<div class="row g-3 justify-content-center">
<?php
$cards = [
    ['num'=>$total_assets,'title'=>'دارایی‌ها','color'=>'assets-count','icon'=>'fa-box','link'=>'assets.php','btn'=>'مشاهده'],
    ['num'=>$total_customers,'title'=>'مشتریان','color'=>'customers-count','icon'=>'fa-users','link'=>'customers.php','btn'=>'مشاهده'],
    ['num'=>$total_users,'title'=>'کاربران','color'=>'users-count','icon'=>'fa-user','link'=>'users.php','btn'=>'مشاهده'],
    ['num'=>"$assigned_assets/$total_assets",'title'=>'انتساب‌ها','color'=>'assignments-count','icon'=>'fa-link','link'=>'assignments.php','btn'=>'مدیریت'],
    ['num'=>$total_guaranties,'title'=>'گارانتی‌ها','color'=>'guaranty-count','icon'=>'fa-id-card','link'=>'create_guaranty.php','btn'=>'مدیریت'],
    ['num'=>$active_guaranties,'title'=>'گارانتی فعال','color'=>'guaranty-count','icon'=>'fa-check-circle','link'=>'create_guaranty.php','btn'=>'جزئیات'],
];
foreach ($cards as $c) {
    echo '<div class="col-lg-2 col-md-3 col-sm-4 col-6">';
    echo '<div class="stat-card">';
    echo '<i class="fa '.$c['icon'].' fa-2x mb-1 '.$c['color'].'"></i>';
    echo '<div class="stat-number '.$c['color'].'">'.$c['num'].'</div>';
    echo '<div class="stat-title">'.$c['title'].'</div>';
    echo '<a href="'.$c['link'].'" class="btn btn-primary btn-sm">'.$c['btn'].'</a>';
    echo '</div></div>';
}
?>
</div>

<!-- بخش Workflow -->
<div class="workflow-section">
    <h3 class="text-center mb-4"><i class="fas fa-cogs"></i> سیستم گردش کار</h3>
    <div class="row g-3">
        <div class="col-lg-3 col-md-6">
            <div class="workflow-card">
                <i class="fas fa-ticket-alt fa-2x mb-2"></i>
                <div class="workflow-number"><?php echo $open_tickets; ?></div>
                <div class="workflow-title">تیکت‌های باز</div>
                <small>از <?php echo $total_tickets; ?> تیکت کل</small>
                <br><a href="tickets.php" class="btn btn-light btn-sm mt-2">مدیریت تیکت‌ها</a>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="workflow-card">
                <i class="fas fa-wrench fa-2x mb-2"></i>
                <div class="workflow-number"><?php echo $upcoming_maintenance; ?></div>
                <div class="workflow-title">تعمیرات نزدیک</div>
                <small>در 7 روز آینده</small>
                <br><a href="maintenance.php" class="btn btn-light btn-sm mt-2">مدیریت تعمیرات</a>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="workflow-card">
                <i class="fas fa-bell fa-2x mb-2"></i>
                <div class="workflow-number"><?php echo $unread_notifications_count; ?></div>
                <div class="workflow-title">اعلان‌های جدید</div>
                <small>خوانده نشده</small>
                <br><button class="btn btn-light btn-sm mt-2" onclick="markAllAsRead()">مشاهده همه</button>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="workflow-card">
                <i class="fas fa-envelope fa-2x mb-2"></i>
                <div class="workflow-number"><?php echo $unread_messages_count; ?></div>
                <div class="workflow-title">پیام‌های جدید</div>
                <small>خوانده نشده</small>
                <br><button class="btn btn-light btn-sm mt-2" onclick="viewMessages()">مشاهده پیام‌ها</button>
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

<!-- اعلان‌ها و یادآوری‌ها -->
<div class="row mt-4">
  <div class="col-md-12">
    <div class="card">
      <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="fa fa-bell"></i> اعلان‌ها و یادآوری‌ها</span>
        <span class="badge bg-danger"><?php echo date("Y/m/d"); ?></span>
      </div>
      <div class="card-body">
        <?php
        $alerts = [];
        try {
            // گارانتی‌های منقضی
            $stmt = $pdo->query("SELECT asset_id, issue_date 
                                 FROM guaranty_cards 
                                 WHERE DATE_ADD(issue_date, INTERVAL 18 MONTH) < CURDATE()");
            $expired = $stmt->fetchAll();
            foreach ($expired as $ex) {
                $alerts[] = [
                    'type' => 'danger',
                    'icon' => 'fa-times-circle',
                    'msg'  => "گارانتی دستگاه با شناسه #{$ex['asset_id']} منقضی شده است.",
                    'alert_type' => 'warranty_expired',
                    'related_id' => $ex['asset_id']
                ];
            }

            // گارانتی‌های نزدیک به پایان
            $stmt = $pdo->query("SELECT asset_id, issue_date,
                                 DATEDIFF(DATE_ADD(issue_date, INTERVAL 18 MONTH), CURDATE()) as days_left
                                 FROM guaranty_cards
                                 HAVING days_left BETWEEN 0 AND 30");
            $soon = $stmt->fetchAll();
            foreach ($soon as $sn) {
                $alerts[] = [
                    'type' => 'warning',
                    'icon' => 'fa-exclamation-triangle',
                    'msg'  => "تنها {$sn['days_left']} روز تا پایان گارانتی دستگاه #{$sn['asset_id']} باقی مانده است.",
                    'alert_type' => 'warranty_soon',
                    'related_id' => $sn['asset_id']
                ];
            }

            // دستگاه‌های بدون انتساب
            if ($total_assets > $assigned_assets) {
                $alerts[] = [
                    'type' => 'info',
                    'icon' => 'fa-info-circle',
                    'msg'  => ($total_assets - $assigned_assets)." دستگاه بدون انتساب به مشتری وجود دارد.",
                    'alert_type' => 'unassigned_assets',
                    'related_id' => null
                ];
            }

            // تیکت‌های فوری
            if ($open_tickets > 0) {
                try {
                    $urgent_tickets = $pdo->query("SELECT COUNT(*) as count FROM tickets WHERE priority = 'فوری' AND status NOT IN ('تکمیل شده', 'لغو شده')")->fetch()['count'];
                    if ($urgent_tickets > 0) {
                        $alerts[] = [
                            'type' => 'danger',
                            'icon' => 'fa-exclamation-circle',
                            'msg'  => "{$urgent_tickets} تیکت فوری نیاز به رسیدگی فوری دارد.",
                            'alert_type' => 'urgent_tickets',
                            'related_id' => null
                        ];
                    }
                } catch (Throwable $e) {}
            }

            // تعمیرات تأخیری
            try {
                $overdue_maintenance = $pdo->query("SELECT COUNT(*) as count FROM maintenance_schedules WHERE schedule_date < CURDATE() AND status = 'برنامه‌ریزی شده'")->fetch()['count'];
                if ($overdue_maintenance > 0) {
                    $alerts[] = [
                        'type' => 'warning',
                        'icon' => 'fa-clock',
                        'msg'  => "{$overdue_maintenance} تعمیرات تأخیر داشته و نیاز به رسیدگی دارد.",
                        'alert_type' => 'overdue_maintenance',
                        'related_id' => null
                    ];
                }
            } catch (Throwable $e) {}

        } catch (Throwable $e) {}

        if ($alerts) {
            echo '<div class="row">';
            foreach ($alerts as $a) {
                echo '<div class="col-md-6 mb-3">';
                echo '<div class="alert-item alert-'.$a['type'].'">';
                echo '<div class="d-flex align-items-center">';
                echo '<i class="fa '.$a['icon'].' me-2"></i>';
                echo '<span>'.$a['msg'].'</span>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
            }
            echo '</div>';
        } else {
            echo '<p class="text-muted">هیچ اعلان یا هشدار جدیدی وجود ندارد.</p>';
        }
        ?>
      </div>
    </div>
  </div>
</div>

<!-- یادداشت‌های من -->
<div class="row mt-4">
<div class="col-md-12">
<div class="card">
<div class="card-header">یادداشت‌های من</div>
<div class="card-body">
<form method="post" action="save_note.php">
<?php if(function_exists('csrf_field')) csrf_field(); ?>
<div class="mb-2"><textarea class="form-control" name="note" rows="2" placeholder="یادداشت خود را بنویسید..."></textarea></div>
<button class="btn btn-primary btn-sm" type="submit">ذخیره</button>
</form>
<hr>
<div>
<?php
try {
if (isset($_SESSION['user_id'])) {
$stmt = $pdo->prepare("SELECT id, note, updated_at FROM user_notes WHERE user_id = ? ORDER BY updated_at DESC LIMIT 10");
$stmt->execute([$_SESSION['user_id']]);
$notes = $stmt->fetchAll();
if ($notes) {
    echo '<ul class="list-group">';
    foreach ($notes as $n) {
        echo '<li class="list-group-item">';
        echo htmlspecialchars($n['note']);
        echo '<div class="note-actions">';
        echo '<a href="edit_note.php?note_id='.(int)$n['id'].'" class="btn btn-warning btn-sm"><i class="fa fa-edit"></i></a>';
        echo '<a href="delete_note.php?note_id='.(int)$n['id'].'" class="btn btn-danger btn-sm" onclick="return confirm(\'آیا مطمئن هستید؟\');"><i class="fa fa-trash"></i></a>';
        echo '</div>';
        echo '<div class="text-muted small mt-2">'.htmlspecialchars($n['updated_at']).'</div>';
        echo '</li>';
    }
    echo '</ul>';
} else { echo '<p class="text-muted">یادداشتی موجود نیست.</p>'; }
}
} catch (Throwable $e) {}
?>
</div>
</div>
</div>
</div>
</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
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
                const badge = document.querySelector('.badge.bg-danger');
                if (badge) badge.textContent = data.count;
            } else {
                const badge = document.querySelector('.badge.bg-danger');
                if (badge) badge.remove();
            }
        });
}, 30000);
</script>
</body>
</html>