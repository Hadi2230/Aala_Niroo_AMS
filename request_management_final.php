<?php
/**
 * request_management_final.php - مدیریت درخواست‌های کالا/خدمات - نسخه نهایی و کامل
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
            
            // ایجاد درخواست برای هر آیتم
            $request_ids = [];
            foreach ($items as $item) {
                $data = [
                    'requester_id' => $_SESSION['user_id'],
                    'requester_name' => $_SESSION['username'],
                    'item_name' => $item['item_name'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'description' => sanitizeInput($_POST['description'] ?? ''),
                    'priority' => $item['priority']
                ];
                
                $request_id = createRequest($pdo, $data);
                if ($request_id) {
                    $request_ids[] = $request_id;
                }
            }
            
            if (!empty($request_ids)) {
                // آپلود فایل‌ها برای اولین درخواست
                if (!empty($_FILES['files']['name'][0])) {
                    foreach ($_FILES['files']['name'] as $key => $name) {
                        if (!empty($name)) {
                            $file = [
                                'name' => $_FILES['files']['name'][$key],
                                'type' => $_FILES['files']['type'][$key],
                                'tmp_name' => $_FILES['files']['tmp_name'][$key],
                                'size' => $_FILES['files']['size'][$key]
                            ];
                            uploadRequestFile($pdo, $request_ids[0], $file);
                        }
                    }
                }
                
                // ایجاد گردش کار برای همه درخواست‌ها
                if (!empty($_POST['assignments'])) {
                    // اگر assignments به صورت آرایه ارسال شده باشد
                    if (is_array($_POST['assignments'])) {
                        $assignments = $_POST['assignments'];
                    } else {
                        // اگر به صورت JSON string ارسال شده باشد
                        $assignments = json_decode($_POST['assignments'], true);
                    }
                    
                    if (is_array($assignments)) {
                        foreach ($request_ids as $request_id) {
                            foreach ($assignments as $assignment) {
                                createRequestWorkflow($pdo, $request_id, $assignment['user_id'], 'ارجاع شده', $assignment['comments']);
                            }
                        }
                    }
                } else {
                    // ایجاد workflow پیش‌فرض
                    foreach ($request_ids as $request_id) {
                        createRequestWorkflow($pdo, $request_id, $_SESSION['user_id'], 'در انتظار تأیید', 'درخواست ایجاد شد');
                    }
                }
                
                // ایجاد notification
                foreach ($request_ids as $request_id) {
                    createRequestNotification($pdo, $request_id, 'درخواست جدید ایجاد شد', 'info');
                }
                
                $_SESSION['success_message'] = 'درخواست‌ها با موفقیت ایجاد شدند! تعداد: ' . count($request_ids);
            } else {
                throw new Exception('خطا در ایجاد درخواست‌ها');
            }
            
        } catch (Exception $e) {
            $error_message = 'خطا در ایجاد درخواست: ' . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'update_request') {
        try {
            $request_id = (int)$_POST['request_id'];
            $status = sanitizeInput($_POST['status']);
            $comments = sanitizeInput($_POST['comments'] ?? '');
            
            // به‌روزرسانی درخواست
            $stmt = $pdo->prepare("UPDATE requests SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$status, $request_id]);
            
            // ایجاد workflow
            createRequestWorkflow($pdo, $request_id, $_SESSION['user_id'], $status, $comments);
            
            $_SESSION['success_message'] = 'درخواست با موفقیت به‌روزرسانی شد!';
            
        } catch (Exception $e) {
            $error_message = 'خطا در به‌روزرسانی درخواست: ' . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'delete_request') {
        try {
            $request_id = (int)$_POST['request_id'];
            
            // بررسی دسترسی ادمین
            if (!is_admin()) {
                throw new Exception('فقط ادمین می‌تواند درخواست‌ها را حذف کند');
            }
            
            // حذف درخواست
            $stmt = $pdo->prepare("DELETE FROM requests WHERE id = ?");
            $stmt->execute([$request_id]);
            
            $_SESSION['success_message'] = 'درخواست با موفقیت حذف شد!';
            
        } catch (Exception $e) {
            $error_message = 'خطا در حذف درخواست: ' . $e->getMessage();
        }
    }
}

// دریافت درخواست‌ها با فیلتر و جستجو
$requests = [];
$filter_status = $_GET['status'] ?? '';
$search_term = $_GET['search'] ?? '';
$filter_priority = $_GET['priority'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';

try {
    $where_conditions = [];
    $params = [];
    
    if (!empty($filter_status)) {
        $where_conditions[] = "r.status = ?";
        $params[] = $filter_status;
    }
    
    if (!empty($search_term)) {
        $where_conditions[] = "(r.item_name LIKE ? OR r.description LIKE ? OR r.request_number LIKE ?)";
        $search_param = "%{$search_term}%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if (!empty($filter_priority)) {
        $where_conditions[] = "r.priority = ?";
        $params[] = $filter_priority;
    }
    
    if (!empty($filter_date_from)) {
        $where_conditions[] = "DATE(r.created_at) >= ?";
        $params[] = jalaliToGregorianForDB($filter_date_from);
    }
    
    if (!empty($filter_date_to)) {
        $where_conditions[] = "DATE(r.created_at) <= ?";
        $params[] = jalaliToGregorianForDB($filter_date_to);
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    $stmt = $pdo->prepare("
        SELECT r.*, u.username as requester_name,
               (SELECT COUNT(*) FROM request_workflow WHERE request_id = r.id) as workflow_count,
               (SELECT status FROM request_workflow WHERE request_id = r.id ORDER BY created_at DESC LIMIT 1) as current_status,
               (SELECT comments FROM request_workflow WHERE request_id = r.id ORDER BY created_at DESC LIMIT 1) as last_comments,
               (SELECT created_at FROM request_workflow WHERE request_id = r.id ORDER BY created_at DESC LIMIT 1) as last_workflow_date
        FROM requests r
        LEFT JOIN users u ON r.requester_id = u.id
        {$where_clause}
        ORDER BY r.created_at DESC
    ");
    $stmt->execute($params);
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

// دریافت آمار کلی
$stats = [];
try {
    $stats['total'] = $pdo->query("SELECT COUNT(*) FROM requests")->fetchColumn();
    $stats['pending'] = $pdo->query("SELECT COUNT(*) FROM requests WHERE status = 'در انتظار تأیید'")->fetchColumn();
    $stats['approved'] = $pdo->query("SELECT COUNT(*) FROM requests WHERE status = 'تأیید شده'")->fetchColumn();
    $stats['rejected'] = $pdo->query("SELECT COUNT(*) FROM requests WHERE status = 'رد شده'")->fetchColumn();
    $stats['high_priority'] = $pdo->query("SELECT COUNT(*) FROM requests WHERE priority = 'بالا' OR priority = 'فوری'")->fetchColumn();
} catch (Exception $e) {
    $stats = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0, 'high_priority' => 0];
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

// دریافت فایل‌های درخواست‌ها
$request_files = [];
try {
    $stmt = $pdo->query("SELECT * FROM request_files ORDER BY created_at DESC");
    $request_files = $stmt->fetchAll();
} catch (Exception $e) {
    // خطا در دریافت فایل‌ها
}

// توابع کمکی
function createRequest($pdo, $data) {
    try {
        $request_number = generateRequestNumber($pdo);
        $stmt = $pdo->prepare("
            INSERT INTO requests (request_number, requester_id, requester_name, item_name, quantity, price, description, priority, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'در انتظار تأیید', NOW())
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

function uploadRequestFile($pdo, $request_id, $file, $upload_dir = 'uploads/requests/') {
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $allowed_types = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_extension, $allowed_types)) {
        throw new Exception('نوع فایل مجاز نیست');
    }
    
    $file_name = uniqid() . '_' . $file['name'];
    $file_path = $upload_dir . $file_name;
    
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO request_files (request_id, original_name, file_name, file_path, file_size, file_type, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $request_id,
                $file['name'],
                $file_name,
                $file_path,
                $file['size'],
                $file['type']
            ]);
            return true;
        } catch (Exception $e) {
            unlink($file_path);
            throw new Exception('خطا در ذخیره اطلاعات فایل');
        }
    } else {
        throw new Exception('خطا در آپلود فایل');
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

function getWorkflowForRequest($workflows, $request_id) {
    return array_filter($workflows, function($w) use ($request_id) {
        return $w['request_id'] == $request_id;
    });
}

function getFilesForRequest($request_files, $request_id) {
    return array_filter($request_files, function($f) use ($request_id) {
        return $f['request_id'] == $request_id;
    });
}

function formatFileSizeLocal($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

function getStatusColor($status) {
    switch ($status) {
        case 'در انتظار تأیید':
            return 'warning';
        case 'تأیید شده':
            return 'success';
        case 'رد شده':
            return 'danger';
        case 'ارجاع شده':
            return 'info';
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
                    <div class="stats-icon" style="background: linear-gradient(135deg, var(--success-color), #56ab2f);">
                        <i class="fas fa-check"></i>
                    </div>
                    <h3 class="mb-1"><?php echo $stats['approved']; ?></h3>
                    <p class="text-muted mb-0">تأیید شده</p>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card">
                    <div class="stats-icon" style="background: linear-gradient(135deg, var(--danger-color), #ff6b6b);">
                        <i class="fas fa-times"></i>
                    </div>
                    <h3 class="mb-1"><?php echo $stats['rejected']; ?></h3>
                    <p class="text-muted mb-0">رد شده</p>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card">
                    <div class="stats-icon" style="background: linear-gradient(135deg, #ff6b6b, #ff8e8e);">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h3 class="mb-1"><?php echo $stats['high_priority']; ?></h3>
                    <p class="text-muted mb-0">اولویت بالا</p>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card">
                    <div class="stats-icon" style="background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3 class="mb-1"><?php echo $stats['total'] > 0 ? round(($stats['approved'] / $stats['total']) * 100, 1) : 0; ?>%</h3>
                    <p class="text-muted mb-0">نرخ تأیید</p>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-filter me-2"></i>فیلتر و جستجو
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">وضعیت</label>
                                <select class="form-select" name="status">
                                    <option value="">همه وضعیت‌ها</option>
                                    <option value="در انتظار تأیید" <?php echo $filter_status === 'در انتظار تأیید' ? 'selected' : ''; ?>>در انتظار تأیید</option>
                                    <option value="تأیید شده" <?php echo $filter_status === 'تأیید شده' ? 'selected' : ''; ?>>تأیید شده</option>
                                    <option value="رد شده" <?php echo $filter_status === 'رد شده' ? 'selected' : ''; ?>>رد شده</option>
                                    <option value="ارجاع شده" <?php echo $filter_status === 'ارجاع شده' ? 'selected' : ''; ?>>ارجاع شده</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">اولویت</label>
                                <select class="form-select" name="priority">
                                    <option value="">همه اولویت‌ها</option>
                                    <option value="فوری" <?php echo $filter_priority === 'فوری' ? 'selected' : ''; ?>>فوری</option>
                                    <option value="بالا" <?php echo $filter_priority === 'بالا' ? 'selected' : ''; ?>>بالا</option>
                                    <option value="متوسط" <?php echo $filter_priority === 'متوسط' ? 'selected' : ''; ?>>متوسط</option>
                                    <option value="کم" <?php echo $filter_priority === 'کم' ? 'selected' : ''; ?>>کم</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">از تاریخ</label>
                                <input type="text" class="form-control jalali-date" name="date_from" 
                                       value="<?php echo $filter_date_from; ?>" placeholder="1403/01/01">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">تا تاریخ</label>
                                <input type="text" class="form-control jalali-date" name="date_to" 
                                       value="<?php echo $filter_date_to; ?>" placeholder="1403/12/29">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">جستجو</label>
                                <input type="text" class="form-control" name="search" 
                                       value="<?php echo htmlspecialchars($search_term); ?>" placeholder="جستجو...">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-2"></i>اعمال فیلتر
                                </button>
                                <a href="request_management_final.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>پاک کردن
                                </a>
                                <button type="button" class="btn btn-success" onclick="exportToExcel()">
                                    <i class="fas fa-file-excel me-2"></i>خروجی Excel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Requests Table -->
        <div class="table-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="mb-0">
                    <i class="fas fa-list me-2"></i>لیست درخواست‌ها
                    <span class="badge bg-primary ms-2"><?php echo count($requests); ?> مورد</span>
                </h3>
                <div>
                    <button class="btn btn-success" onclick="location.reload()">
                        <i class="fas fa-sync-alt me-2"></i>تازه‌سازی
                    </button>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newRequestModal">
                        <i class="fas fa-plus me-2"></i>درخواست جدید
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
                            <th>گردش کار</th>
                            <th>فایل‌ها</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $request): 
                            $request_workflows = getWorkflowForRequest($workflows, $request['id']);
                            $request_files = getFilesForRequest($request_files, $request['id']);
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($request['request_number']); ?></strong>
                                <?php if ($request['workflow_count'] > 0): ?>
                                    <br><small class="text-muted"><?php echo $request['workflow_count']; ?> فعالیت</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($request['requester_name']); ?>
                                <?php if ($request['description']): ?>
                                    <br><small class="text-muted" title="<?php echo htmlspecialchars($request['description']); ?>">
                                        <?php echo mb_substr($request['description'], 0, 30) . '...'; ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($request['item_name']); ?></td>
                            <td><?php echo number_format($request['quantity']); ?></td>
                            <td><?php echo number_format($request['price']); ?> تومان</td>
                            <td>
                                <span class="badge bg-<?php echo getPriorityColor($request['priority']); ?>">
                                    <?php echo htmlspecialchars($request['priority']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo getStatusColor($request['status']); ?>">
                                    <?php echo htmlspecialchars($request['status']); ?>
                                </span>
                                <?php if ($request['last_comments']): ?>
                                    <br><small class="text-muted" title="<?php echo htmlspecialchars($request['last_comments']); ?>">
                                        <?php echo mb_substr($request['last_comments'], 0, 20) . '...'; ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo jalali_format($request['created_at']); ?></td>
                            <td>
                                <?php if (!empty($request_workflows)): ?>
                                    <button class="btn btn-info btn-sm" onclick="viewWorkflow(<?php echo $request['id']; ?>)">
                                        <i class="fas fa-history me-1"></i><?php echo count($request_workflows); ?>
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($request_files)): ?>
                                    <button class="btn btn-secondary btn-sm" onclick="viewFiles(<?php echo $request['id']; ?>)">
                                        <i class="fas fa-paperclip me-1"></i><?php echo count($request_files); ?>
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <button class="btn btn-info btn-sm" onclick="viewRequest(<?php echo $request['id']; ?>)" title="مشاهده">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-warning btn-sm" onclick="updateStatus(<?php echo $request['id']; ?>, '<?php echo $request['status']; ?>')" title="تغییر وضعیت">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if (is_admin()): ?>
                                    <button class="btn btn-success btn-sm" onclick="assignRequest(<?php echo $request['id']; ?>)" title="ارجاع">
                                        <i class="fas fa-user-plus"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="deleteRequest(<?php echo $request['id']; ?>)" title="حذف">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($requests)): ?>
                        <tr>
                            <td colspan="11" class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <br>هیچ درخواستی یافت نشد
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- New Request Modal -->
    <div class="modal fade" id="newRequestModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>درخواست جدید
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_request">
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">تاریخ درخواست</label>
                                <input type="text" class="form-control jalali-date" name="request_date" 
                                       value="<?php echo jalali_format(date('Y-m-d')); ?>" required readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">اولویت کلی</label>
                                <select class="form-select" name="priority" required>
                                    <option value="کم">کم</option>
                                    <option value="متوسط" selected>متوسط</option>
                                    <option value="بالا">بالا</option>
                                    <option value="فوری">فوری</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">ارجاع به</label>
                                <select class="form-select" name="assignments[]" multiple>
                                    <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>">
                                        <?php echo htmlspecialchars($user['username']); ?> (<?php echo htmlspecialchars($user['role']); ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">می‌توانید چندین کاربر انتخاب کنید</small>
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

                        <div class="mb-3">
                            <label class="form-label">فایل‌های ضمیمه</label>
                            <input type="file" class="form-control" name="files[]" multiple 
                                   accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx">
                            <small class="text-muted">حداکثر 5 فایل، انواع مجاز: JPG, PNG, PDF, DOC, XLS</small>
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
                        <input type="hidden" name="action" value="update_request">
                        <input type="hidden" name="request_id" id="update_request_id">
                        
                        <div class="mb-3">
                            <label class="form-label">وضعیت جدید</label>
                            <select class="form-select" name="status" id="update_status" required>
                                <option value="در انتظار تأیید">در انتظار تأیید</option>
                                <option value="در حال بررسی">در حال بررسی</option>
                                <option value="تأیید شده">تأیید شده</option>
                                <option value="رد شده">رد شده</option>
                                <option value="تکمیل شده">تکمیل شده</option>
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

    <!-- View Workflow Modal -->
    <div class="modal fade" id="workflowModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-history me-2"></i>گردش کار درخواست
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="workflow-content">
                    <!-- محتوای گردش کار اینجا نمایش داده می‌شود -->
                </div>
            </div>
        </div>
    </div>

    <!-- View Files Modal -->
    <div class="modal fade" id="filesModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-paperclip me-2"></i>فایل‌های ضمیمه
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="files-content">
                    <!-- محتوای فایل‌ها اینجا نمایش داده می‌شود -->
                </div>
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
            // نمایش گردش کار درخواست
            const workflows = <?php echo json_encode($workflows); ?>;
            const requestWorkflows = workflows.filter(w => w.request_id == requestId);
            
            let html = '<div class="timeline">';
            if (requestWorkflows.length > 0) {
                requestWorkflows.forEach(workflow => {
                    html += `
                        <div class="timeline-item">
                            <div class="timeline-marker"></div>
                            <div class="timeline-content">
                                <h6>${workflow.assigned_to_name}</h6>
                                <p class="text-muted">${workflow.status}</p>
                                <p>${workflow.comments || 'بدون توضیحات'}</p>
                                <small class="text-muted">${workflow.created_at}</small>
                            </div>
                        </div>
                    `;
                });
            } else {
                html += '<p class="text-muted">گردش کاری یافت نشد</p>';
            }
            html += '</div>';
            
            document.getElementById('workflow-content').innerHTML = html;
            new bootstrap.Modal(document.getElementById('workflowModal')).show();
        }

        function viewFiles(requestId) {
            // نمایش فایل‌های درخواست
            const files = <?php echo json_encode($request_files); ?>;
            const requestFiles = files.filter(f => f.request_id == requestId);
            
            let html = '<div class="row">';
            if (requestFiles.length > 0) {
                requestFiles.forEach(file => {
                    html += `
                        <div class="col-md-6 mb-3">
                            <div class="card">
                                <div class="card-body">
                                    <h6>${file.original_name}</h6>
                                    <p class="text-muted">اندازه: ${formatFileSizeJS(file.file_size)}</p>
                                    <p class="text-muted">نوع: ${file.file_type}</p>
                                    <a href="${file.file_path}" class="btn btn-primary btn-sm" target="_blank">
                                        <i class="fas fa-download me-1"></i>دانلود
                                    </a>
                                </div>
                            </div>
                        </div>
                    `;
                });
            } else {
                html += '<div class="col-12"><p class="text-muted">فایلی یافت نشد</p></div>';
            }
            html += '</div>';
            
            document.getElementById('files-content').innerHTML = html;
            new bootstrap.Modal(document.getElementById('filesModal')).show();
        }

        function deleteRequest(id) {
            if (confirm('آیا مطمئن هستید که می‌خواهید این درخواست را حذف کنید؟')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_request">
                    <input type="hidden" name="request_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function formatFileSizeJS(bytes) {
            const units = ['B', 'KB', 'MB', 'GB'];
            bytes = Math.max(bytes, 0);
            const pow = Math.floor((bytes ? Math.log(bytes) : 0) / Math.log(1024));
            const powMin = Math.min(pow, units.length - 1);
            bytes /= Math.pow(1024, powMin);
            return Math.round(bytes * 100) / 100 + ' ' + units[powMin];
        }

        function exportToExcel() {
            // Implementation for Excel export
            alert('خروجی Excel در حال آماده‌سازی...');
        }

        // Auto-refresh every 30 seconds
        setInterval(function() {
            if (document.hidden) return;
            location.reload();
        }, 30000);
    </script>
</body>
</html>