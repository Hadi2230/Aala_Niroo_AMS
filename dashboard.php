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
$total_tickets = $open_tickets = $total_maintenance = $upcoming_maintenance = 0;
$unread_notifications_count = $unread_messages_count = 0;

try {
    $total_tickets = (int)$pdo->query("SELECT COUNT(*) as total FROM tickets")->fetch()['total'];
    $open_tickets = (int)$pdo->query("SELECT COUNT(*) as total FROM tickets WHERE status IN ('جدید', 'در انتظار', 'در حال بررسی')")->fetch()['total'];
    $total_maintenance = (int)$pdo->query("SELECT COUNT(*) as total FROM maintenance_schedules")->fetch()['total'];
    $upcoming_maintenance = (int)$pdo->query("SELECT COUNT(*) as total FROM maintenance_schedules WHERE schedule_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND status = 'برنامه‌ریزی شده'")->fetch()['total'];
    $unread_notifications_count = (int)$pdo->query("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = false")->fetch(['user_id' => $_SESSION['user_id']])['total'];
    $unread_messages_count = (int)$pdo->query("SELECT COUNT(*) as total FROM messages WHERE receiver_id = ? AND is_read = false")->fetch(['receiver_id' => $_SESSION['user_id']])['total'];
} catch (Throwable $e) {}

// دریافت تیکت‌های اخیر
$recent_tickets = [];
try {
    $stmt = $pdo->prepare("
        SELECT t.*, c.full_name as customer_name, u.full_name as assigned_user
        FROM tickets t
        LEFT JOIN customers c ON t.customer_id = c.id
        LEFT JOIN users u ON t.assigned_to = u.id
        ORDER BY t.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recent_tickets = $stmt->fetchAll();
} catch (Throwable $e) {}

// دریافت تعمیرات نزدیک
$upcoming_maintenance_list = [];
try {
    $stmt = $pdo->prepare("
        SELECT ms.*, a.name as asset_name, c.full_name as customer_name, u.full_name as assigned_user
        FROM maintenance_schedules ms
        LEFT JOIN assets a ON ms.asset_id = a.id
        LEFT JOIN asset_assignments aa ON ms.assignment_id = aa.id
        LEFT JOIN customers c ON aa.customer_id = c.id
        LEFT JOIN users u ON ms.assigned_to = u.id
        WHERE ms.schedule_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND ms.status = 'برنامه‌ریزی شده'
        ORDER BY ms.schedule_date ASC
        LIMIT 5
    ");
    $stmt->execute();
    $upcoming_maintenance_list = $stmt->fetchAll();
} catch (Throwable $e) {}

// دریافت اعلان‌های خوانده نشده
$unread_notifications = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? AND is_read = false 
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $unread_notifications = $stmt->fetchAll();
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
    --workflow-primary: #8b5cf6;
    --workflow-secondary: #a78bfa;
    --workflow-accent: #c084fc;
    --workflow-success: #10b981;
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
.tickets-count { color: var(--workflow-primary); }
.maintenance-count { color: var(--workflow-secondary); }
.notifications-count { color: var(--workflow-accent); }
.messages-count { color: var(--workflow-success); }
.stat-title { font-weight: 500; color: #555; margin-bottom: 8px; }
.dark-mode .stat-title { color: #ccc; }
.card { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 20px; }
.dark-mode .card { background-color: #2d3748; }
.card-header { background: linear-gradient(135deg, var(--primary-color) 0%, #34495e 100%); color: white; border-radius: 12px 12px 0 0 !important; font-weight: 600; }
.btn-primary { background: linear-gradient(135deg, var(--primary-color) 0%, #34495e 100%); border: none; border-radius: 6px; padding: 6px 18px; transition: all 0.3s; font-size: 0.85rem; }
.btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(52,152,219,0.3); }
.list-group-item { border: 1px solid rgba(0,0,0,0.1); border-radius: 8px !important; margin-bottom: 6px; position: relative; padding-right: 10px; }
.dark-mode .list-group-item { background-color: #374151; border-color: #4b5563; color: var(--dark-text); }
.note-actions { display:flex; gap:5px; position:absolute; left:10px; top:50%; Transform:translateY(-50%); }
.note-actions form { display:inline; }
.workflow-card { background: linear-gradient(135deg, var(--workflow-primary) 0%, var(--workflow-secondary) 100%); color: white; }
.workflow-card:hover { transform: translateY(-4px); box-shadow: 0 8px 20px rgba(139, 92, 246, 0.3); }
.notification-item { border-left: 4px solid var(--workflow-primary); }
.notification-item.unread { background-color: #f8f9ff; }
.dark-mode .notification-item.unread { background-color: #2d1b69; }
.priority-badge { font-size: 0.75rem; padding: 2px 6px; }
.status-badge { font-size: 0.75rem; padding: 2px 6px; }
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
<div class="row mt-4">
    <div class="col-12">
        <h3 class="mb-3"><i class="fa fa-workflow"></i> سیستم گردش کار</h3>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-3 col-md-6">
        <div class="stat-card workflow-card">
            <i class="fa fa-ticket-alt fa-2x mb-2"></i>
            <div class="stat-number"><?php echo $open_tickets; ?></div>
            <div class="stat-title">تیکت‌های باز</div>
            <a href="tickets.php" class="btn btn-light btn-sm">مشاهده</a>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="stat-card workflow-card">
            <i class="fa fa-tools fa-2x mb-2"></i>
            <div class="stat-number"><?php echo $upcoming_maintenance; ?></div>
            <div class="stat-title">تعمیرات نزدیک</div>
            <a href="maintenance.php" class="btn btn-light btn-sm">مشاهده</a>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="stat-card workflow-card">
            <i class="fa fa-bell fa-2x mb-2"></i>
            <div class="stat-number"><?php echo $unread_notifications_count; ?></div>
            <div class="stat-title">اعلان‌های جدید</div>
            <a href="#" class="btn btn-light btn-sm" onclick="viewNotifications()">مشاهده</a>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="stat-card workflow-card">
            <i class="fa fa-envelope fa-2x mb-2"></i>
            <div class="stat-number"><?php echo $unread_messages_count; ?></div>
            <div class="stat-title">پیام‌های جدید</div>
            <a href="#" class="btn btn-light btn-sm" onclick="viewMessages()">مشاهده</a>
        </div>
    </div>
</div>

<!-- تیکت‌های اخیر -->
<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <i class="fa fa-ticket-alt"></i> تیکت‌های اخیر
            </div>
            <div class="card-body">
                <?php if ($recent_tickets): ?>
                    <div class="list-group">
                        <?php foreach ($recent_tickets as $ticket): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($ticket['title']); ?></h6>
                                        <p class="mb-1 text-muted"><?php echo htmlspecialchars($ticket['customer_name']); ?></p>
                                        <small class="text-muted"><?php echo jalaliDate($ticket['created_at']); ?></small>
                                    </div>
                                    <div>
                                        <span class="badge priority-badge bg-<?php echo $ticket['priority'] === 'فوری' ? 'danger' : ($ticket['priority'] === 'بالا' ? 'warning' : 'info'); ?>">
                                            <?php echo $ticket['priority']; ?>
                                        </span>
                                        <span class="badge status-badge bg-<?php echo $ticket['status'] === 'جدید' ? 'primary' : ($ticket['status'] === 'تکمیل شده' ? 'success' : 'secondary'); ?>">
                                            <?php echo $ticket['status']; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted">هیچ تیکتی موجود نیست.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- تعمیرات نزدیک -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <i class="fa fa-tools"></i> تعمیرات نزدیک
            </div>
            <div class="card-body">
                <?php if ($upcoming_maintenance_list): ?>
                    <div class="list-group">
                        <?php foreach ($upcoming_maintenance_list as $maintenance): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($maintenance['asset_name']); ?></h6>
                                        <p class="mb-1 text-muted"><?php echo htmlspecialchars($maintenance['customer_name']); ?></p>
                                        <small class="text-muted"><?php echo jalaliDate($maintenance['schedule_date']); ?></small>
                                    </div>
                                    <div>
                                        <span class="badge status-badge bg-info">
                                            <?php echo $maintenance['maintenance_type']; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted">هیچ تعمیرات برنامه‌ریزی شده‌ای موجود نیست.</p>
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
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM tickets WHERE priority = 'فوری' AND status IN ('جدید', 'در انتظار', 'در حال بررسی')");
                $urgent_tickets = $stmt->fetch()['count'];
                if ($urgent_tickets > 0) {
                    $alerts[] = [
                        'type' => 'danger',
                        'icon' => 'fa-exclamation-triangle',
                        'msg'  => "{$urgent_tickets} تیکت فوری نیاز به رسیدگی دارد.",
                        'alert_type' => 'urgent_tickets',
                        'related_id' => null
                    ];
                }
            }

            // تعمیرات عقب افتاده
            if ($upcoming_maintenance > 0) {
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM maintenance_schedules WHERE schedule_date < CURDATE() AND status = 'برنامه‌ریزی شده'");
                $overdue_maintenance = $stmt->fetch()['count'];
                if ($overdue_maintenance > 0) {
                    $alerts[] = [
                        'type' => 'warning',
                        'icon' => 'fa-clock',
                        'msg'  => "{$overdue_maintenance} تعمیرات عقب افتاده نیاز به رسیدگی دارد.",
                        'alert_type' => 'overdue_maintenance',
                        'related_id' => null
                    ];
                }
            }

        } catch (Throwable $e) {}

        if ($alerts) {
            echo '<ul class="list-group">';
            foreach ($alerts as $a) {
                // بررسی اقدامات ثبت‌شده
                $stmt = $pdo->prepare("SELECT * FROM alerts_actions 
                                       WHERE alert_type=? AND (related_id=? OR ? IS NULL)
                                       ORDER BY updated_at DESC LIMIT 1");
                $stmt->execute([$a['alert_type'], $a['related_id'], $a['related_id']]);
                $action = $stmt->fetch();

                $status_badge = '<span class="badge bg-secondary">در انتظار</span>';
                if ($action) {
                    if ($action['status'] === 'in_progress') $status_badge = '<span class="badge bg-warning">در حال پیگیری</span>';
                    if ($action['status'] === 'done') $status_badge = '<span class="badge bg-success">تکمیل شد</span>';
                }

                echo '<li class="list-group-item list-group-item-'.$a['type'].'">';
                echo '<div class="d-flex justify-content-between align-items-center">';
                echo '<div><i class="fa '.$a['icon'].' me-2"></i> '.$a['msg'].'</div>';
                echo $status_badge;
                echo '</div>';

                if ($action && $action['action_note']) {
                    echo '<div class="text-muted small mt-2">یادداشت: '.htmlspecialchars($action['action_note']).'</div>';
                }

                echo '<div class="mt-2">';
                echo '<button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#actionModal" 
                       data-type="'.$a['alert_type'].'" data-id="'.$a['related_id'].'">
                       ثبت/ویرایش اقدام</button>';
                echo '</div>';

                echo '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p class="text-muted">هیچ اعلان یا هشدار جدیدی وجود ندارد.</p>';
        }
        ?>
      </div>
    </div>
  </div>
</div>

<!-- Modal برای ثبت اقدام -->
<div class="modal fade" id="actionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="save_alert_action.php">
      <div class="modal-header">
        <h5 class="modal-title">ثبت اقدام برای اعلان</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="alert_type" id="alertType">
        <input type="hidden" name="related_id" id="relatedId">
        <div class="mb-2">
          <label class="form-label">وضعیت</label>
          <select class="form-select" name="status">
            <option value="pending">در انتظار اقدام</option>
            <option value="in_progress">در حال پیگیری</option>
            <option value="done">تکمیل شده</option>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label">یادداشت</label>
          <textarea class="form-control" name="action_note" rows="3" placeholder="یادداشت یا توضیح خود را وارد کنید..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">ذخیره</button>
      </div>
    </form>
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

<!-- گزارش اقدامات -->
<div class="row mt-4">
  <div class="col-md-12">
    <div class="card">
      <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="fa fa-clipboard-list"></i> گزارش اقدامات</span>
        <span class="badge bg-primary">آخرین بروزرسانی: <?php echo date("H:i"); ?></span>
      </div>
      <div class="card-body">
        <?php
        try {
            $stmt = $pdo->prepare("
                SELECT a.id, a.alert_type, a.related_id, a.status, a.action_note, a.updated_at, u.username
                FROM alerts_actions a
                LEFT JOIN users u ON a.user_id = u.id
                ORDER BY a.updated_at DESC
                LIMIT 20
            ");
            $stmt->execute();
            $actions = $stmt->fetchAll();

            if ($actions) {
                echo '<div class="table-responsive">';
                echo '<table class="table table-hover align-middle">';
                echo '<thead class="table-light"><tr>
                        <th>#</th>
                        <th>نوع اعلان</th>
                        <th>شناسه مرتبط</th>
                        <th>وضعیت</th>
                        <th>یادداشت</th>
                        <th>کاربر</th>
                        <th>تاریخ بروزرسانی</th>
                      </tr></thead><tbody>';
                foreach ($actions as $a) {
                    $status_badge = '<span class="badge bg-secondary">در انتظار</span>';
                    if ($a['status'] === 'in_progress') $status_badge = '<span class="badge bg-warning">در حال پیگیری</span>';
                    if ($a['status'] === 'done') $status_badge = '<span class="badge bg-success">تکمیل شد</span>';

                    $alert_title = match($a['alert_type']) {
                        'warranty_expired' => 'گارانتی منقضی',
                        'warranty_soon'    => 'پایان قریب گارانتی',
                        'unassigned_assets'=> 'دارایی بدون انتساب',
                        'urgent_tickets'   => 'تیکت‌های فوری',
                        'overdue_maintenance' => 'تعمیرات عقب افتاده',
                        default            => $a['alert_type']
                    };

                    echo '<tr>';
                    echo '<td>'.(int)$a['id'].'</td>';
                    echo '<td>'.$alert_title.'</td>';
                    echo '<td>'.($a['related_id'] ?? '-').'</td>';
                    echo '<td>'.$status_badge.'</td>';
                    echo '<td>'.htmlspecialchars($a['action_note'] ?? '-').'</td>';
                    echo '<td>'.htmlspecialchars($a['username'] ?? '-').'</td>';
                    echo '<td>'.$a['updated_at'].'</td>';
                    echo '</tr>';
                }
                echo '</tbody></table></div>';
            } else {
                echo '<p class="text-muted">هیچ اقدامی ثبت نشده است.</p>';
            }
        } catch (Throwable $e) {
            echo '<p class="text-danger">خطا در بارگذاری گزارش اقدامات</p>';
        }
        ?>
      </div>
    </div>
  </div>
</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
  const actionModal = document.getElementById("actionModal");
  if (actionModal) {
    actionModal.addEventListener("show.bs.modal", function(event) {
      const button = event.relatedTarget;
      document.getElementById("alertType").value = button.getAttribute("data-type");
      document.getElementById("relatedId").value = button.getAttribute("data-id");
    });
  }
});

// تابع‌های workflow
function markAsRead(notificationId) {
    fetch('mark_notification_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'notification_id=' + notificationId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}

function markAllAsRead() {
    fetch('mark_all_notifications_read.php', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}

function viewNotifications() {
    // نمایش اعلان‌ها در modal یا redirect
    window.location.href = 'notifications.php';
}

function viewMessages() {
    // نمایش پیام‌ها در modal یا redirect
    window.location.href = 'messages.php';
}

// به‌روزرسانی تعداد اعلان‌ها هر 30 ثانیه
setInterval(function() {
    fetch('get_notifications_count.php')
    .then(response => response.json())
    .then(data => {
        if (data.count !== undefined) {
            document.querySelector('.notifications-count').textContent = data.count;
        }
    });
}, 30000);
</script>
</body>
</html>