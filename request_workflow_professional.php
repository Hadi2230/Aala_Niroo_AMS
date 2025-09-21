<?php
/**
 * request_workflow_professional.php - سیستم حرفه‌ای مدیریت درخواست‌ها
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'config.php';

$page_title = 'سیستم حرفه‌ای مدیریت درخواست‌ها';

// پردازش عملیات
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'delete_request') {
        try {
            $request_id = (int)$_POST['request_id'];
            
            // بررسی دسترسی ادمین
            if (!is_admin()) {
                throw new Exception('فقط ادمین می‌تواند درخواست‌ها را حذف کند');
            }
            
            // حذف درخواست (cascade delete همه جداول مرتبط)
            $stmt = $pdo->prepare("DELETE FROM requests WHERE id = ?");
            $stmt->execute([$request_id]);
            
            $_SESSION['success_message'] = 'درخواست با موفقیت حذف شد!';
            header('Location: request_workflow_professional.php');
            exit();
        } catch (Exception $e) {
            $error_message = 'خطا در حذف درخواست: ' . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'update_workflow') {
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
            
            if (!$has_access && !is_admin()) {
                throw new Exception('شما دسترسی لازم برای این عملیات را ندارید');
            }
            
            // به‌روزرسانی workflow
            $stmt = $pdo->prepare("
                INSERT INTO request_workflow (request_id, assigned_to, status, comments, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$request_id, $_SESSION['user_id'], $status, $comments]);
            
            // به‌روزرسانی وضعیت درخواست
            $stmt = $pdo->prepare("
                UPDATE requests 
                SET status = ?, priority = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$status, $priority, $request_id]);
            
            // ایجاد notification
            $stmt = $pdo->prepare("
                INSERT INTO request_notifications (request_id, message, type, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $message = "وضعیت درخواست به '{$status}' تغییر یافت";
            $type = $status === 'تأیید شده' ? 'success' : ($status === 'رد شده' ? 'danger' : 'info');
            $stmt->execute([$request_id, $message, $type]);
            
            $_SESSION['success_message'] = 'وضعیت درخواست با موفقیت به‌روزرسانی شد!';
            header('Location: request_workflow_professional.php');
            exit();
        } catch (Exception $e) {
            $error_message = 'خطا در به‌روزرسانی وضعیت: ' . $e->getMessage();
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
            
            $_SESSION['success_message'] = 'درخواست با موفقیت ارجاع شد!';
            header('Location: request_workflow_professional.php');
            exit();
        } catch (Exception $e) {
            $error_message = 'خطا در ارجاع درخواست: ' . $e->getMessage();
        }
    }
}

// دریافت درخواست‌ها با جزئیات workflow
$requests = [];
try {
    $stmt = $pdo->query("
        SELECT r.*, u.username as requester_name,
               (SELECT COUNT(*) FROM request_workflow WHERE request_id = r.id) as workflow_count,
               (SELECT status FROM request_workflow WHERE request_id = r.id ORDER BY created_at DESC LIMIT 1) as current_status,
               (SELECT comments FROM request_workflow WHERE request_id = r.id ORDER BY created_at DESC LIMIT 1) as last_comments,
               (SELECT created_at FROM request_workflow WHERE request_id = r.id ORDER BY created_at DESC LIMIT 1) as last_workflow_date
        FROM requests r
        LEFT JOIN users u ON r.requester_id = u.id
        ORDER BY r.created_at DESC
    ");
    $requests = $stmt->fetchAll();
} catch (Exception $e) {
    $error_message = 'خطا در دریافت درخواست‌ها: ' . $e->getMessage();
}

// دریافت کاربران برای ارجاع
$users = [];
try {
    $stmt = $pdo->query("SELECT id, username, role FROM users WHERE status = 'active' ORDER BY username");
    $users = $stmt->fetchAll();
} catch (Exception $e) {
    // خطا در دریافت کاربران
}

// دریافت workflow برای هر درخواست
$workflows = [];
try {
    $stmt = $pdo->query("
        SELECT w.*, u.username as assigned_to_name
        FROM request_workflow w
        LEFT JOIN users u ON w.assigned_to = u.id
        ORDER BY w.created_at DESC
    ");
    $workflows = $stmt->fetchAll();
} catch (Exception $e) {
    // خطا در دریافت workflow
}

// توابع کمکی
function is_admin() {
    return isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'ادمین');
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function getWorkflowForRequest($workflows, $request_id) {
    return array_filter($workflows, function($w) use ($request_id) {
        return $w['request_id'] == $request_id;
    });
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

        .workflow-timeline {
            position: relative;
            padding-left: 2rem;
        }

        .workflow-timeline::before {
            content: '';
            position: absolute;
            left: 1rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }

        .workflow-item {
            position: relative;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
            border-right: 4px solid var(--primary-color);
        }

        .workflow-item::before {
            content: '';
            position: absolute;
            left: -2.5rem;
            top: 1rem;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--primary-color);
            border: 3px solid white;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.9rem;
        }

        .status-pending {
            background: linear-gradient(135deg, var(--warning-color), #f093fb);
            color: white;
        }

        .status-approved {
            background: linear-gradient(135deg, var(--success-color), #56ab2f);
            color: white;
        }

        .status-rejected {
            background: linear-gradient(135deg, var(--danger-color), #ff6b6b);
            color: white;
        }

        .status-assigned {
            background: linear-gradient(135deg, var(--info-color), #4facfe);
            color: white;
        }

        .priority-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .priority-high {
            background: #ff6b6b;
            color: white;
        }

        .priority-medium {
            background: #ffa726;
            color: white;
        }

        .priority-low {
            background: #66bb6a;
            color: white;
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

        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
        }

        .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .jalali-date {
            background: white;
            border: 1px solid #ced4da;
            border-radius: 8px;
            padding: 0.75rem;
            width: 100%;
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
                        <p class="mb-0 mt-2">مدیریت حرفه‌ای گردش کار درخواست‌ها</p>
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
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon" style="background: linear-gradient(135deg, var(--info-color), #4facfe);">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <h3 class="mb-1"><?php echo count($requests); ?></h3>
                    <p class="text-muted mb-0">کل درخواست‌ها</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon" style="background: linear-gradient(135deg, var(--warning-color), #f093fb);">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3 class="mb-1"><?php echo count(array_filter($requests, function($r) { return $r['status'] === 'در انتظار تأیید'; })); ?></h3>
                    <p class="text-muted mb-0">در انتظار</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon" style="background: linear-gradient(135deg, var(--success-color), #56ab2f);">
                        <i class="fas fa-check"></i>
                    </div>
                    <h3 class="mb-1"><?php echo count(array_filter($requests, function($r) { return $r['status'] === 'تأیید شده'; })); ?></h3>
                    <p class="text-muted mb-0">تأیید شده</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon" style="background: linear-gradient(135deg, var(--danger-color), #ff6b6b);">
                        <i class="fas fa-times"></i>
                    </div>
                    <h3 class="mb-1"><?php echo count(array_filter($requests, function($r) { return $r['status'] === 'رد شده'; })); ?></h3>
                    <p class="text-muted mb-0">رد شده</p>
                </div>
            </div>
        </div>

        <!-- Requests List -->
        <div class="row">
            <?php foreach ($requests as $request): ?>
            <div class="col-12">
                <div class="request-card">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <div class="d-flex align-items-center mb-2">
                                <h5 class="mb-0 me-3"><?php echo htmlspecialchars($request['request_number']); ?></h5>
                                <span class="status-badge status-<?php echo str_replace(['در انتظار تأیید', 'تأیید شده', 'رد شده', 'ارجاع شده'], ['pending', 'approved', 'rejected', 'assigned'], $request['status']); ?>">
                                    <?php echo htmlspecialchars($request['status']); ?>
                                </span>
                                <span class="priority-badge priority-<?php echo strtolower($request['priority']); ?> ms-2">
                                    <?php echo htmlspecialchars($request['priority']); ?>
                                </span>
                            </div>
                            <p class="text-muted mb-1">
                                <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($request['requester_name']); ?>
                                <span class="ms-3">
                                    <i class="fas fa-calendar me-1"></i><?php echo jalali_format($request['created_at']); ?>
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
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="btn-group" role="group">
                                <button class="btn btn-info btn-sm" onclick="viewWorkflow(<?php echo $request['id']; ?>)">
                                    <i class="fas fa-eye me-1"></i>گردش کار
                                </button>
                                <button class="btn btn-warning btn-sm" onclick="updateStatus(<?php echo $request['id']; ?>, '<?php echo $request['status']; ?>')">
                                    <i class="fas fa-edit me-1"></i>تغییر وضعیت
                                </button>
                                <?php if (is_admin()): ?>
                                <button class="btn btn-success btn-sm" onclick="assignRequest(<?php echo $request['id']; ?>)">
                                    <i class="fas fa-user-plus me-1"></i>ارجاع
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="deleteRequest(<?php echo $request['id']; ?>)">
                                    <i class="fas fa-trash me-1"></i>حذف
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Workflow Timeline -->
                    <div class="mt-3">
                        <h6 class="mb-2">
                            <i class="fas fa-history me-1"></i>گردش کار
                        </h6>
                        <div class="workflow-timeline">
                            <?php 
                            $request_workflows = getWorkflowForRequest($workflows, $request['id']);
                            foreach ($request_workflows as $workflow): 
                            ?>
                            <div class="workflow-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong><?php echo htmlspecialchars($workflow['assigned_to_name']); ?></strong>
                                        <span class="status-badge status-<?php echo str_replace(['در انتظار', 'تأیید شده', 'رد شده', 'ارجاع شده'], ['pending', 'approved', 'rejected', 'assigned'], $workflow['status']); ?> ms-2">
                                            <?php echo htmlspecialchars($workflow['status']); ?>
                                        </span>
                                        <?php if ($workflow['comments']): ?>
                                        <p class="mb-1 mt-2 text-muted"><?php echo htmlspecialchars($workflow['comments']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted"><?php echo jalali_format($workflow['created_at']); ?></small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>تغییر وضعیت درخواست
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_workflow">
                        <input type="hidden" name="request_id" id="update_request_id">
                        
                        <div class="mb-3">
                            <label class="form-label">وضعیت جدید</label>
                            <select class="form-select" name="status" id="update_status" required>
                                <option value="در انتظار">در انتظار</option>
                                <option value="در حال بررسی">در حال بررسی</option>
                                <option value="تأیید شده">تأیید شده</option>
                                <option value="رد شده">رد شده</option>
                                <option value="تکمیل شده">تکمیل شده</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">اولویت</label>
                            <select class="form-select" name="priority">
                                <option value="کم">کم</option>
                                <option value="متوسط">متوسط</option>
                                <option value="بالا">بالا</option>
                                <option value="فوری">فوری</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">توضیحات</label>
                            <textarea class="form-control" name="comments" rows="3" 
                                      placeholder="توضیحات مربوط به تغییر وضعیت..."></textarea>
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
                            <select class="form-select" name="assigned_to" required>
                                <option value="">انتخاب کاربر...</option>
                                <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['username']); ?> 
                                    (<?php echo htmlspecialchars($user['role']); ?>)
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

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js"></script>
    
    <script>
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
            // Implementation for viewing detailed workflow
            alert('مشاهده جزئیات گردش کار درخواست: ' + requestId);
        }

        function deleteRequest(requestId) {
            if (confirm('آیا مطمئن هستید که می‌خواهید این درخواست را حذف کنید؟')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_request">
                    <input type="hidden" name="request_id" value="${requestId}">
                `;
                document.body.appendChild(form);
                form.submit();
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