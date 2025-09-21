<?php
/**
 * my_assigned_requests.php - درخواست‌های ارجاع شده به من
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'config.php';

$page_title = 'درخواست‌های ارجاع شده به من';

// پردازش عملیات
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_request_status') {
        try {
            $request_id = (int)$_POST['request_id'];
            $status = sanitizeInput($_POST['status']);
            $comments = sanitizeInput($_POST['comments']);
            $priority = sanitizeInput($_POST['priority'] ?? '');
            
            // بررسی دسترسی کاربر
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM request_workflow 
                WHERE request_id = ? AND assigned_to = ? AND status IN ('ارجاع شده', 'در انتظار')
            ");
            $stmt->execute([$request_id, $_SESSION['user_id']]);
            $has_access = $stmt->fetchColumn() > 0;
            
            if (!$has_access) {
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
            header('Location: my_assigned_requests.php');
            exit();
        } catch (Exception $e) {
            $error_message = 'خطا در به‌روزرسانی: ' . $e->getMessage();
        }
    }
}

// دریافت درخواست‌های ارجاع شده به کاربر
$assigned_requests = [];
try {
    $stmt = $pdo->prepare("
        SELECT r.*, u.username as requester_name, u.full_name as requester_full_name,
               rw.status as workflow_status, rw.comments as workflow_comments,
               rw.created_at as assigned_date, rw.action_date
        FROM requests r
        LEFT JOIN users u ON r.requester_id = u.id
        LEFT JOIN request_workflow rw ON r.id = rw.request_id AND rw.assigned_to = ?
        WHERE rw.assigned_to = ? AND rw.status IN ('ارجاع شده', 'در انتظار', 'در حال بررسی')
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $assigned_requests = $stmt->fetchAll();
} catch (Exception $e) {
    $error_message = 'خطا در دریافت درخواست‌ها: ' . $e->getMessage();
}

// محاسبه آمار
$stats = [
    'total' => count($assigned_requests),
    'pending' => 0,
    'in_progress' => 0,
    'completed' => 0,
    'urgent' => 0
];

foreach ($assigned_requests as $request) {
    switch ($request['workflow_status']) {
        case 'ارجاع شده':
        case 'در انتظار':
            $stats['pending']++;
            break;
        case 'در حال بررسی':
            $stats['in_progress']++;
            break;
        case 'تأیید شده':
        case 'رد شده':
        case 'تکمیل شده':
            $stats['completed']++;
            break;
    }
    
    if ($request['priority'] === 'فوری' || $request['priority'] === 'بالا') {
        $stats['urgent']++;
    }
}

// توابع کمکی
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
        case 'ارجاع شده':
        case 'در انتظار':
            return 'warning';
        case 'در حال بررسی':
            return 'info';
        case 'تأیید شده':
            return 'success';
        case 'رد شده':
            return 'danger';
        case 'تکمیل شده':
            return 'primary';
        default:
            return 'secondary';
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

        .request-card.pending {
            border-left: 5px solid var(--warning-color);
        }

        .request-card.in-progress {
            border-left: 5px solid var(--info-color);
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
                            <i class="fas fa-user-check me-3"></i><?php echo $page_title; ?>
                        </h1>
                        <p class="mb-0 mt-2">درخواست‌هایی که به شما ارجاع شده‌اند</p>
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
            <div class="col-md-2">
                <div class="stats-card">
                    <div class="stats-icon" style="background: linear-gradient(135deg, var(--info-color), #4facfe);">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <h3 class="mb-1"><?php echo $stats['total']; ?></h3>
                    <p class="text-muted mb-0">کل درخواست‌ها</p>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card">
                    <div class="stats-icon" style="background: linear-gradient(135deg, var(--warning-color), #f093fb);">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3 class="mb-1"><?php echo $stats['pending']; ?></h3>
                    <p class="text-muted mb-0">در انتظار</p>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card">
                    <div class="stats-icon" style="background: linear-gradient(135deg, var(--info-color), #4facfe);">
                        <i class="fas fa-cog"></i>
                    </div>
                    <h3 class="mb-1"><?php echo $stats['in_progress']; ?></h3>
                    <p class="text-muted mb-0">در حال بررسی</p>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card">
                    <div class="stats-icon" style="background: linear-gradient(135deg, var(--success-color), #56ab2f);">
                        <i class="fas fa-check"></i>
                    </div>
                    <h3 class="mb-1"><?php echo $stats['completed']; ?></h3>
                    <p class="text-muted mb-0">تکمیل شده</p>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card">
                    <div class="stats-icon" style="background: linear-gradient(135deg, var(--danger-color), #ff6b6b);">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h3 class="mb-1"><?php echo $stats['urgent']; ?></h3>
                    <p class="text-muted mb-0">اولویت بالا</p>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card">
                    <div class="stats-icon" style="background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3 class="mb-1"><?php echo $stats['total'] > 0 ? round(($stats['completed'] / $stats['total']) * 100, 1) : 0; ?>%</h3>
                    <p class="text-muted mb-0">نرخ تکمیل</p>
                </div>
            </div>
        </div>

        <!-- Assigned Requests List -->
        <div class="row">
            <?php if (empty($assigned_requests)): ?>
            <div class="col-12">
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                    <h4 class="text-muted">هیچ درخواستی به شما ارجاع نشده است</h4>
                    <p class="text-muted">درخواست‌های ارجاع شده به شما در اینجا نمایش داده می‌شوند</p>
                </div>
            </div>
            <?php else: ?>
                <?php foreach ($assigned_requests as $request): ?>
                <div class="col-12">
                    <div class="request-card <?php echo $request['priority'] === 'فوری' ? 'urgent' : ($request['workflow_status'] === 'ارجاع شده' ? 'pending' : 'in-progress'); ?>">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <div class="d-flex align-items-center mb-2">
                                    <h5 class="mb-0 me-3"><?php echo htmlspecialchars($request['request_number']); ?></h5>
                                    <span class="status-badge bg-<?php echo getStatusColor($request['workflow_status']); ?>">
                                        <?php echo htmlspecialchars($request['workflow_status']); ?>
                                    </span>
                                    <span class="priority-badge bg-<?php echo getPriorityColor($request['priority']); ?> ms-2">
                                        <?php echo htmlspecialchars($request['priority']); ?>
                                    </span>
                                </div>
                                <p class="text-muted mb-1">
                                    <i class="fas fa-user me-1"></i>
                                    درخواست‌کننده: <?php echo htmlspecialchars($request['requester_full_name'] ?: $request['requester_name']); ?>
                                    <span class="ms-3">
                                        <i class="fas fa-calendar me-1"></i>
                                        ارجاع شده: <?php echo jalali_format($request['assigned_date']); ?>
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
                                <?php if ($request['workflow_comments']): ?>
                                <p class="text-info mt-2 mb-0">
                                    <i class="fas fa-sticky-note me-1"></i><?php echo htmlspecialchars($request['workflow_comments']); ?>
                                </p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4 text-end">
                                <div class="btn-group" role="group">
                                    <button class="btn btn-info btn-sm" onclick="viewDetails(<?php echo $request['id']; ?>)">
                                        <i class="fas fa-eye me-1"></i>جزئیات
                                    </button>
                                    <button class="btn btn-warning btn-sm" onclick="updateStatus(<?php echo $request['id']; ?>, '<?php echo $request['workflow_status']; ?>')">
                                        <i class="fas fa-edit me-1"></i>اقدام
                                    </button>
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
                        <input type="hidden" name="action" value="update_request_status">
                        <input type="hidden" name="request_id" id="update_request_id">
                        
                        <div class="mb-3">
                            <label class="form-label">وضعیت جدید</label>
                            <select class="form-select" name="status" id="update_status" required>
                                <option value="در حال بررسی">در حال بررسی</option>
                                <option value="تأیید شده">تأیید شده</option>
                                <option value="رد شده">رد شده</option>
                                <option value="تکمیل شده">تکمیل شده</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">اولویت</label>
                            <select class="form-select" name="priority">
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

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function updateStatus(requestId, currentStatus) {
            document.getElementById('update_request_id').value = requestId;
            document.getElementById('update_status').value = currentStatus;
            new bootstrap.Modal(document.getElementById('updateStatusModal')).show();
        }

        function viewDetails(requestId) {
            // Implementation for viewing request details
            alert('مشاهده جزئیات درخواست: ' + requestId);
        }
    </script>
</body>
</html>