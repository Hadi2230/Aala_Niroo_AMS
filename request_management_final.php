<?php
/**
 * request_management_final.php - مدیریت درخواست‌های کالا/خدمات - نسخه نهایی
 */

// فعال کردن نمایش خطاها برای دیباگ
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

try {
    require_once 'config.php';
} catch (Exception $e) {
    die("<div style='text-align: center; padding: 50px; font-family: Tahoma;'>
        <h2>خطا در بارگذاری سیستم</h2>
        <p>خطا: " . $e->getMessage() . "</p>
        <p><a href='test_request_debug.php'>تست سیستم</a></p>
        </div>");
}

$page_title = 'مدیریت درخواست‌های کالا/خدمات';

// پردازش فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_request') {
        try {
            // پردازش آیتم‌های متعدد
            $items = [];
            if (isset($_POST['items']) && is_array($_POST['items'])) {
                foreach ($_POST['items'] as $item) {
                    if (!empty($item['item_name'])) {
                        // تبدیل قیمت فارسی به انگلیسی
                        $price = $item['price'];
                        $price = str_replace(['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'], 
                                           ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'], $price);
                        $price = str_replace(',', '', $price);
                        
                        $items[] = [
                            'item_name' => sanitizeInput($item['item_name']),
                            'quantity' => (int)$item['quantity'],
                            'price' => floatval($price),
                            'priority' => sanitizeInput($item['priority'])
                        ];
                    }
                }
            }
            
            if (empty($items)) {
                throw new Exception('حداقل یک آیتم باید وارد شود');
            }
            
            // ایجاد درخواست
            $request_id = createRequest($pdo, [
                'requester_id' => $_SESSION['user_id'],
                'requester_name' => $_SESSION['username'],
                'item_name' => implode('، ', array_column($items, 'item_name')),
                'quantity' => array_sum(array_column($items, 'quantity')),
                'price' => array_sum(array_column($items, 'price')),
                'description' => sanitizeInput($_POST['description'] ?? ''),
                'priority' => sanitizeInput($_POST['priority'] ?? 'متوسط')
            ]);
            
            if ($request_id) {
                // ایجاد workflow
                createRequestWorkflow($pdo, $request_id, $_SESSION['user_id'], 'در انتظار تأیید', 'درخواست ایجاد شد');
                
                // ایجاد notification
                createRequestNotification($pdo, $request_id, 'درخواست جدید ایجاد شد', 'info');
                
                $_SESSION['success_message'] = 'درخواست با موفقیت ایجاد شد! شماره درخواست: ' . generateRequestNumber($pdo);
            } else {
                throw new Exception('خطا در ایجاد درخواست');
            }
            
        } catch (Exception $e) {
            $error_message = 'خطا در ایجاد درخواست: ' . $e->getMessage();
        }
    }
}

// دریافت درخواست‌ها
$requests = [];
try {
    $stmt = $pdo->query("
        SELECT r.*, u.username as requester_name,
               (SELECT COUNT(*) FROM request_workflow WHERE request_id = r.id) as workflow_count,
               (SELECT status FROM request_workflow WHERE request_id = r.id ORDER BY created_at DESC LIMIT 1) as current_status
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

// توابع کمکی
function createRequest($pdo, $data) {
    try {
        $request_number = generateRequestNumber($pdo);
        $stmt = $pdo->prepare("
            INSERT INTO requests (request_number, requester_id, requester_name, item_name, quantity, price, description, priority, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'در انتظار تأیید')
        ");
        $stmt->execute([
            $request_number,
            $data['requester_id'],
            $data['requester_name'],
            $data['item_name'],
            $data['quantity'],
            $data['price'],
            $data['description'],
            $data['priority']
        ]);
        return $pdo->lastInsertId();
    } catch (Exception $e) {
        error_log("Error creating request: " . $e->getMessage());
        return false;
    }
}

function createRequestWorkflow($pdo, $request_id, $user_id, $status, $comments) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO request_workflow (request_id, assigned_to, status, comments, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$request_id, $user_id, $status, $comments]);
        return true;
    } catch (Exception $e) {
        error_log("Error creating workflow: " . $e->getMessage());
        return false;
    }
}

function createRequestNotification($pdo, $request_id, $message, $type = 'info') {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO request_notifications (request_id, message, type, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$request_id, $message, $type]);
        return true;
    } catch (Exception $e) {
        error_log("Error creating notification: " . $e->getMessage());
        return false;
    }
}

function generateRequestNumber($pdo) {
    $today = date('Ymd');
    $prefix = "REQ-{$today}-";
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM requests WHERE request_number LIKE ?");
        $stmt->execute([$prefix . '%']);
        $result = $stmt->fetch();
        $count = ($result['count'] ?? 0) + 1;
        return $prefix . str_pad($count, 3, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        error_log("Error generating request number: " . $e->getMessage());
        return $prefix . '001';
    }
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function is_admin() {
    return isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'ادمین');
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

        .form-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            border: none;
        }

        .table-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border: none;
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

        .item-row {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid #e9ecef;
        }

        .jalali-date {
            background: white;
            border: 1px solid #ced4da;
            border-radius: 8px;
            padding: 0.75rem;
            width: 100%;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .table th {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            font-weight: bold;
        }

        .table td {
            vertical-align: middle;
            border-color: #e9ecef;
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
                            <i class="fas fa-clipboard-list me-3"></i><?php echo $page_title; ?>
                        </h1>
                        <p class="mb-0 mt-2">مدیریت و پیگیری درخواست‌های کالا و خدمات</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newRequestModal">
                            <i class="fas fa-plus me-2"></i>درخواست جدید
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

        <!-- Requests Table -->
        <div class="table-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="mb-0">
                    <i class="fas fa-list me-2"></i>لیست درخواست‌ها
                </h3>
                <div>
                    <button class="btn btn-success" onclick="location.reload()">
                        <i class="fas fa-sync-alt me-2"></i>تازه‌سازی
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>شماره درخواست</th>
                            <th>درخواست‌کننده</th>
                            <th>آیتم</th>
                            <th>تعداد</th>
                            <th>قیمت</th>
                            <th>اولویت</th>
                            <th>وضعیت</th>
                            <th>تاریخ ایجاد</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $request): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($request['request_number']); ?></strong>
                            </td>
                            <td><?php echo htmlspecialchars($request['requester_name']); ?></td>
                            <td><?php echo htmlspecialchars($request['item_name']); ?></td>
                            <td><?php echo number_format($request['quantity']); ?></td>
                            <td><?php echo number_format($request['price']); ?> تومان</td>
                            <td>
                                <span class="priority-badge priority-<?php echo strtolower($request['priority']); ?>">
                                    <?php echo htmlspecialchars($request['priority']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo str_replace(['در انتظار تأیید', 'تأیید شده', 'رد شده'], ['pending', 'approved', 'rejected'], $request['status']); ?>">
                                    <?php echo htmlspecialchars($request['status']); ?>
                                </span>
                            </td>
                            <td><?php echo jalali_format($request['created_at']); ?></td>
                            <td>
                                <div class="btn-group" role="group">
                                    <button class="btn btn-info btn-sm" onclick="viewRequest(<?php echo $request['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if (is_admin()): ?>
                                    <button class="btn btn-warning btn-sm" onclick="editRequest(<?php echo $request['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="deleteRequest(<?php echo $request['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- New Request Modal -->
    <div class="modal fade" id="newRequestModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>درخواست جدید
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_request">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">تاریخ درخواست</label>
                                <input type="text" class="form-control jalali-date" name="request_date" 
                                       value="<?php echo jalali_format(date('Y-m-d')); ?>" required readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">اولویت</label>
                                <select class="form-select" name="priority" required>
                                    <option value="کم">کم</option>
                                    <option value="متوسط" selected>متوسط</option>
                                    <option value="بالا">بالا</option>
                                    <option value="فوری">فوری</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">توضیحات</label>
                            <textarea class="form-control" name="description" rows="3" 
                                      placeholder="توضیحات اضافی در مورد درخواست..."></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">آیتم‌های درخواست</label>
                            <div id="items-container">
                                <div class="item-row">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <label class="form-label">نام آیتم</label>
                                            <input type="text" class="form-control" name="items[0][item_name]" 
                                                   placeholder="نام کالا یا خدمت" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">تعداد</label>
                                            <input type="number" class="form-control" name="items[0][quantity]" 
                                                   value="1" min="1" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">قیمت (تومان)</label>
                                            <input type="text" class="form-control" name="items[0][price]" 
                                                   placeholder="0" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">اولویت</label>
                                            <select class="form-select" name="items[0][priority]">
                                                <option value="کم">کم</option>
                                                <option value="متوسط" selected>متوسط</option>
                                                <option value="بالا">بالا</option>
                                            </select>
                                        </div>
                                        <div class="col-md-1">
                                            <label class="form-label">&nbsp;</label>
                                            <button type="button" class="btn btn-danger btn-sm w-100" onclick="removeItem(this)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-success btn-sm" onclick="addItem()">
                                <i class="fas fa-plus me-2"></i>افزودن آیتم
                            </button>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>ایجاد درخواست
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
        let itemIndex = 1;

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

        function addItem() {
            const container = document.getElementById('items-container');
            const newItem = document.createElement('div');
            newItem.className = 'item-row';
            newItem.innerHTML = `
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label">نام آیتم</label>
                        <input type="text" class="form-control" name="items[${itemIndex}][item_name]" 
                               placeholder="نام کالا یا خدمت" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">تعداد</label>
                        <input type="number" class="form-control" name="items[${itemIndex}][quantity]" 
                               value="1" min="1" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">قیمت (تومان)</label>
                        <input type="text" class="form-control" name="items[${itemIndex}][price]" 
                               placeholder="0" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">اولویت</label>
                        <select class="form-select" name="items[${itemIndex}][priority]">
                            <option value="کم">کم</option>
                            <option value="متوسط" selected>متوسط</option>
                            <option value="بالا">بالا</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">&nbsp;</label>
                        <button type="button" class="btn btn-danger btn-sm w-100" onclick="removeItem(this)">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
            container.appendChild(newItem);
            itemIndex++;
        }

        function removeItem(button) {
            button.closest('.item-row').remove();
        }

        function viewRequest(id) {
            // Implementation for viewing request details
            alert('مشاهده جزئیات درخواست: ' + id);
        }

        function editRequest(id) {
            // Implementation for editing request
            alert('ویرایش درخواست: ' + id);
        }

        function deleteRequest(id) {
            if (confirm('آیا مطمئن هستید که می‌خواهید این درخواست را حذف کنید؟')) {
                // Implementation for deleting request
                alert('حذف درخواست: ' + id);
            }
        }
    </script>
</body>
</html>