<?php
session_start();
require_once 'config.php';

// بررسی embed mode
$embed_mode = isset($_GET['embed']) && $_GET['embed'] == '1';

// بررسی دسترسی
if (!isset($_SESSION['user_id'])) {
    if ($embed_mode) {
        echo '<div class="alert alert-warning">لطفاً ابتدا وارد شوید</div>';
        exit;
    }
    header('Location: login.php');
    exit;
}

// ایجاد جداول ابزارها در صورت عدم وجود
try {
    // جدول ابزارها
    $pdo->exec("CREATE TABLE IF NOT EXISTS tools (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tool_code VARCHAR(50) UNIQUE NOT NULL,
        name VARCHAR(255) NOT NULL,
        category ENUM('ابزار_دستی', 'ابزار_برقی', 'تجهیزات_اندازه_گیری', 'تجهیزات_ایمنی', 'سایر') NOT NULL,
        brand VARCHAR(100),
        model VARCHAR(100),
        serial_number VARCHAR(100),
        purchase_date DATE,
        purchase_price DECIMAL(10,2),
        supplier VARCHAR(255),
        location VARCHAR(255),
        status ENUM('موجود', 'تحویل_داده_شده', 'تعمیر', 'از_دست_رفته', 'خراب') DEFAULT 'موجود',
        condition_notes TEXT,
        maintenance_date DATE,
        next_maintenance_date DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_tool_code (tool_code),
        INDEX idx_category (category),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // جدول تحویل ابزارها
    $pdo->exec("CREATE TABLE IF NOT EXISTS tool_issues (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tool_id INT NOT NULL,
        issued_to VARCHAR(255) NOT NULL,
        issued_by INT NOT NULL,
        issue_date DATE NOT NULL,
        expected_return_date DATE,
        actual_return_date DATE,
        purpose TEXT,
        condition_before TEXT,
        condition_after TEXT,
        notes TEXT,
        status ENUM('تحویل_داده_شده', 'برگشت_داده_شده', 'تاخیر_در_برگشت') DEFAULT 'تحویل_داده_شده',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_tool_id (tool_id),
        INDEX idx_issued_to (issued_to),
        INDEX idx_status (status),
        INDEX idx_issue_date (issue_date),
        FOREIGN KEY (tool_id) REFERENCES tools(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // جدول تاریخچه ابزارها
    $pdo->exec("CREATE TABLE IF NOT EXISTS tool_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tool_id INT NOT NULL,
        action_type ENUM('ایجاد', 'ویرایش', 'تحویل', 'استرداد', 'حذف', 'تعمیر', 'سایر') NOT NULL,
        action_description TEXT NOT NULL,
        performed_by INT NOT NULL,
        performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        old_values JSON,
        new_values JSON,
        notes TEXT,
        INDEX idx_tool_id (tool_id),
        INDEX idx_action_type (action_type),
        INDEX idx_performed_by (performed_by),
        INDEX idx_performed_at (performed_at),
        FOREIGN KEY (tool_id) REFERENCES tools(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {
    error_log("Error creating tools tables: " . $e->getMessage());
}

// تابع ثبت تاریخچه ابزار
function logToolHistory($pdo, $tool_id, $action_type, $action_description, $performed_by, $old_values = null, $new_values = null, $notes = null) {
    try {
        $stmt = $pdo->prepare("INSERT INTO tool_history (tool_id, action_type, action_description, performed_by, old_values, new_values, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $tool_id,
            $action_type,
            $action_description,
            $performed_by,
            $old_values ? json_encode($old_values) : null,
            $new_values ? json_encode($new_values) : null,
            $notes
        ]);
    } catch (Exception $e) {
        error_log("Error logging tool history: " . $e->getMessage());
    }
}

// پردازش فرم‌ها
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_tool':
                try {
                    $pdo->beginTransaction();
                    
                    // تولید کد ابزار
                    $tool_code = 'TL' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    
                    $stmt = $pdo->prepare("INSERT INTO tools (tool_code, name, category, brand, model, serial_number, purchase_date, purchase_price, supplier, location, status, condition_notes, maintenance_date, next_maintenance_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $tool_code,
                        $_POST['name'],
                        $_POST['category'],
                        $_POST['brand'] ?? null,
                        $_POST['model'] ?? null,
                        $_POST['serial_number'] ?? null,
                        $_POST['purchase_date'] ?: null,
                        $_POST['purchase_price'] ?: null,
                        $_POST['supplier'] ?? null,
                        $_POST['location'] ?? null,
                        'موجود',
                        $_POST['condition_notes'] ?? null,
                        $_POST['maintenance_date'] ?: null,
                        $_POST['next_maintenance_date'] ?: null
                    ]);
                    
                    $tool_id = $pdo->lastInsertId();
                    
                    // ثبت تاریخچه
                    logToolHistory($pdo, $tool_id, 'ایجاد', "ایجاد ابزار جدید: " . $_POST['name'] . " (کد: $tool_code)", $_SESSION['user_id'], null, [
                        'name' => $_POST['name'],
                        'category' => $_POST['category'],
                        'brand' => $_POST['brand'] ?? null,
                        'model' => $_POST['model'] ?? null,
                        'serial_number' => $_POST['serial_number'] ?? null,
                        'purchase_date' => $_POST['purchase_date'] ?? null,
                        'purchase_price' => $_POST['purchase_price'] ?? null,
                        'supplier' => $_POST['supplier'] ?? null,
                        'location' => $_POST['location'] ?? null,
                        'status' => 'موجود'
                    ], "ابزار جدید به سیستم اضافه شد");
                    
                    $pdo->commit();
                    $_SESSION['success'] = "ابزار با موفقیت اضافه شد";
                    logAction($pdo, 'ADD_TOOL', "افزودن ابزار جدید: " . $_POST['name'] . " (کد: $tool_code)");
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $_SESSION['error'] = "خطا در افزودن ابزار: " . $e->getMessage();
                    logAction($pdo, 'ADD_TOOL_ERROR', "خطا در افزودن ابزار: " . $e->getMessage());
                }
                break;
                
            case 'edit_tool':
                try {
                    $pdo->beginTransaction();
                    
                    $stmt = $pdo->prepare("UPDATE tools SET name = ?, category = ?, brand = ?, model = ?, serial_number = ?, purchase_date = ?, purchase_price = ?, supplier = ?, location = ?, condition_notes = ?, maintenance_date = ?, next_maintenance_date = ? WHERE id = ?");
                    $stmt->execute([
                        $_POST['name'],
                        $_POST['category'],
                        $_POST['brand'] ?? null,
                        $_POST['model'] ?? null,
                        $_POST['serial_number'] ?? null,
                        $_POST['purchase_date'] ?: null,
                        $_POST['purchase_price'] ?: null,
                        $_POST['supplier'] ?? null,
                        $_POST['location'] ?? null,
                        $_POST['condition_notes'] ?? null,
                        $_POST['maintenance_date'] ?: null,
                        $_POST['next_maintenance_date'] ?: null,
                        $_POST['tool_id']
                    ]);
                    
                    // ثبت تاریخچه ویرایش
                    logToolHistory($pdo, $_POST['tool_id'], 'ویرایش', "ویرایش ابزار: " . $_POST['name'], $_SESSION['user_id'], null, [
                        'name' => $_POST['name'],
                        'category' => $_POST['category'],
                        'brand' => $_POST['brand'] ?? null,
                        'model' => $_POST['model'] ?? null,
                        'serial_number' => $_POST['serial_number'] ?? null,
                        'purchase_date' => $_POST['purchase_date'] ?? null,
                        'purchase_price' => $_POST['purchase_price'] ?? null,
                        'supplier' => $_POST['supplier'] ?? null,
                        'location' => $_POST['location'] ?? null,
                        'condition_notes' => $_POST['condition_notes'] ?? null,
                        'maintenance_date' => $_POST['maintenance_date'] ?? null,
                        'next_maintenance_date' => $_POST['next_maintenance_date'] ?? null
                    ], "اطلاعات ابزار به‌روزرسانی شد");
                    
                    $pdo->commit();
                    $_SESSION['success'] = "ابزار با موفقیت ویرایش شد";
                    logAction($pdo, 'EDIT_TOOL', "ویرایش ابزار: " . $_POST['name'] . " (ID: " . $_POST['tool_id'] . ")");
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $_SESSION['error'] = "خطا در ویرایش ابزار: " . $e->getMessage();
                    logAction($pdo, 'EDIT_TOOL_ERROR', "خطا در ویرایش ابزار: " . $e->getMessage());
                }
                break;
                
            case 'issue_tool':
                try {
                    $pdo->beginTransaction();
                    
                    $stmt = $pdo->prepare("INSERT INTO tool_issues (tool_id, issued_to, issued_by, issue_date, expected_return_date, purpose, condition_before, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['tool_id'],
                        $_POST['issued_to'],
                        $_SESSION['user_id'],
                        $_POST['issue_date'],
                        $_POST['expected_return_date'] ?: null,
                        $_POST['purpose'] ?? null,
                        $_POST['condition_before'] ?? null,
                        $_POST['notes'] ?? null
                    ]);
                    
                    // به‌روزرسانی وضعیت ابزار
                    $stmt = $pdo->prepare("UPDATE tools SET status = 'تحویل_داده_شده' WHERE id = ?");
                    $stmt->execute([$_POST['tool_id']]);
                    
                    // ثبت تاریخچه تحویل
                    logToolHistory($pdo, $_POST['tool_id'], 'تحویل', "تحویل ابزار به " . $_POST['issued_to'], $_SESSION['user_id'], null, [
                        'issued_to' => $_POST['issued_to'],
                        'issue_date' => $_POST['issue_date'],
                        'expected_return_date' => $_POST['expected_return_date'] ?? null,
                        'purpose' => $_POST['purpose'] ?? null,
                        'condition_before' => $_POST['condition_before'] ?? null,
                        'notes' => $_POST['notes'] ?? null
                    ], "ابزار به " . $_POST['issued_to'] . " تحویل داده شد");
                    
                    $pdo->commit();
                    $_SESSION['success'] = "ابزار با موفقیت تحویل داده شد";
                    logAction($pdo, 'ISSUE_TOOL', "تحویل ابزار: " . $_POST['tool_id'] . " به " . $_POST['issued_to']);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $_SESSION['error'] = "خطا در تحویل ابزار: " . $e->getMessage();
                    logAction($pdo, 'ISSUE_TOOL_ERROR', "خطا در تحویل ابزار: " . $e->getMessage());
                }
                break;
                
            case 'return_tool':
                try {
                    $pdo->beginTransaction();
                    
                    // دریافت اطلاعات ابزار قبل از استرداد
                    $stmt = $pdo->prepare("SELECT tool_id FROM tool_issues WHERE id = ?");
                    $stmt->execute([$_POST['tool_issue_id']]);
                    $tool_issue = $stmt->fetch();
                    
                    if (!$tool_issue) {
                        throw new Exception('تحویل ابزار یافت نشد');
                    }
                    
                    // به‌روزرسانی وضعیت تحویل ابزار
                    $stmt = $pdo->prepare("UPDATE tool_issues SET status = 'برگشت_داده_شده', actual_return_date = CURDATE(), condition_after = ? WHERE id = ?");
                    $stmt->execute([
                        $_POST['condition_after'] ?? null,
                        $_POST['tool_issue_id']
                    ]);
                    
                    // به‌روزرسانی وضعیت ابزار به موجود
                    $stmt = $pdo->prepare("UPDATE tools SET status = 'موجود' WHERE id = ?");
                    $stmt->execute([$tool_issue['tool_id']]);
                    
                    // ثبت تاریخچه استرداد
                    logToolHistory($pdo, $tool_issue['tool_id'], 'استرداد', "استرداد ابزار", $_SESSION['user_id'], null, [
                        'condition_after' => $_POST['condition_after'] ?? null,
                        'actual_return_date' => date('Y-m-d'),
                        'status_change' => 'موجود'
                    ], "ابزار برگشت داده شد و وضعیت به موجود تغییر یافت");
                    
                    $pdo->commit();
                    $_SESSION['success'] = "ابزار با موفقیت برگشت داده شد و وضعیت آن به 'موجود' تغییر یافت";
                    logAction($pdo, 'RETURN_TOOL', "برگشت ابزار: " . $tool_issue['tool_id'] . " - وضعیت به موجود تغییر یافت");
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $_SESSION['error'] = "خطا در برگشت ابزار: " . $e->getMessage();
                    logAction($pdo, 'RETURN_TOOL_ERROR', "خطا در برگشت ابزار: " . $e->getMessage());
                }
                break;
                
            case 'delete_tool':
                try {
                    $pdo->beginTransaction();
                    
                    // دریافت اطلاعات ابزار قبل از حذف
                    $stmt = $pdo->prepare("SELECT * FROM tools WHERE id = ?");
                    $stmt->execute([$_POST['tool_id']]);
                    $tool = $stmt->fetch();
                    
                    if (!$tool) {
                        throw new Exception('ابزار یافت نشد');
                    }
                    
                    // بررسی اینکه ابزار تحویل داده نشده باشد
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tool_issues WHERE tool_id = ? AND status = 'تحویل_داده_شده'");
                    $stmt->execute([$_POST['tool_id']]);
                    $issued_count = $stmt->fetchColumn();
                    
                    if ($issued_count > 0) {
                        throw new Exception('نمی‌توان ابزاری که در حال استفاده است را حذف کرد');
                    }
                    
                    // ثبت تاریخچه قبل از حذف
                    logToolHistory($pdo, $_POST['tool_id'], 'حذف', "حذف ابزار: " . $tool['name'] . " (کد: " . $tool['tool_code'] . ")", $_SESSION['user_id'], $tool, null, "ابزار به طور کامل از سیستم حذف شد");
                    
                    // حذف ابزار
                    $stmt = $pdo->prepare("DELETE FROM tools WHERE id = ?");
                    $stmt->execute([$_POST['tool_id']]);
                    
                    $pdo->commit();
                    $_SESSION['success'] = "ابزار با موفقیت حذف شد";
                    logAction($pdo, 'DELETE_TOOL', "حذف ابزار: " . $tool['name'] . " (ID: " . $_POST['tool_id'] . ")");
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $_SESSION['error'] = "خطا در حذف ابزار: " . $e->getMessage();
                    logAction($pdo, 'DELETE_TOOL_ERROR', "خطا در حذف ابزار: " . $e->getMessage());
                }
                break;
        }
    }
}

// دریافت داده‌ها
try {
    // همه ابزارها
    $tools = $pdo->query("SELECT * FROM tools ORDER BY created_at DESC")->fetchAll();
    
    // ابزارهای موجود (فقط آنهایی که تحویل داده نشده‌اند)
    $available_tools = $pdo->query("SELECT * FROM tools WHERE status = 'موجود' ORDER BY name")->fetchAll();
    
    // ابزارهای تحویل داده شده (در حال استفاده)
    $tools_issued = $pdo->query("SELECT ti.*, t.name as tool_name, t.tool_code FROM tool_issues ti JOIN tools t ON ti.tool_id = t.id WHERE ti.status = 'تحویل_داده_شده' ORDER BY ti.issue_date DESC")->fetchAll();
    
    // ابزارهای برگشت داده شده (تاریخی)
    $tools_returned = $pdo->query("SELECT ti.*, t.name as tool_name, t.tool_code FROM tool_issues ti JOIN tools t ON ti.tool_id = t.id WHERE ti.status = 'برگشت_داده_شده' ORDER BY ti.actual_return_date DESC")->fetchAll();
    
    // ابزارهای با تاخیر در برگشت
    $tools_overdue = $pdo->query("SELECT ti.*, t.name as tool_name, t.tool_code FROM tool_issues ti JOIN tools t ON ti.tool_id = t.id WHERE ti.status = 'تحویل_داده_شده' AND ti.expected_return_date < CURDATE() ORDER BY ti.expected_return_date ASC")->fetchAll();
} catch (Exception $e) {
    $tools = [];
    $available_tools = [];
    $tools_issued = [];
    $tools_returned = [];
    $tools_overdue = [];
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $embed_mode ? 'مدیریت ابزارها' : 'مدیریت ابزارها و تجهیزات نصب'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <style>
        html, body { font-family: Vazirmatn, Tahoma, Arial, sans-serif; }
        .form-select, .form-control, .form-label, .btn, .card, option { font-family: inherit; }
        .form-select, .form-control { direction: rtl; text-align: right; } option { direction: rtl; text-align: right; }
        .card { 
            border: none; 
            border-radius: 12px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.08); 
            margin-bottom: 20px; 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
            background: linear-gradient(145deg, #ffffff 0%, #f8f9fa 100%);
            border: 1px solid rgba(0,0,0,0.05);
        }
        .card:hover { 
            transform: translateY(-4px) scale(1.02); 
            box-shadow: 0 12px 40px rgba(0,0,0,0.15); 
            border-color: rgba(13, 110, 253, 0.2);
        }
        .card-header { 
            border-radius: 12px 12px 0 0 !important; 
            font-weight: 600; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }
        
        /* بهبود ظاهر تب‌های ابزارها */
        .nav-tabs {
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 1.5rem;
        }
        
        .nav-tabs .nav-link {
            border: 2px solid transparent;
            border-radius: 8px 8px 0 0;
            font-weight: 600;
            transition: all 0.3s ease;
            color: #495057 !important;
            background-color: #f8f9fa;
            margin-right: 0.25rem;
            padding: 0.75rem 1.25rem;
            font-size: 0.9rem;
        }
        
        .nav-tabs .nav-link:hover {
            border-color: #dee2e6 #dee2e6 #e9ecef;
            background-color: #e9ecef;
            color: #212529 !important;
            transform: translateY(-2px);
        }
        
        .nav-tabs .nav-link.active {
            color: #fff !important;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-color: #667eea #667eea #fff;
            font-weight: 700;
            box-shadow: 0 4px 8px rgba(102, 126, 234, 0.3);
            transform: translateY(-2px);
        }
        
        .nav-tabs .nav-link i {
            font-size: 0.9em;
            margin-right: 0.5rem;
        }
        
        /* یکسان کردن رنگ تمام تب‌ها */
        .nav-tabs .nav-link {
            color: #495057 !important;
            background-color: #f8f9fa;
        }
        
        .nav-tabs .nav-link:hover {
            color: #212529 !important;
            background-color: #e9ecef;
        }
        
        .nav-tabs .nav-link.active {
            color: #fff !important;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        
        .btn-sm {
            margin: 2px;
        }
        
        .history-modal .modal-dialog {
            max-width: 90%;
        }
        
        .history-table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        
        .action-badge {
            font-size: 0.75em;
        }
        
        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
            border-top: none;
        }
        
        .embed-container {
            background: #f8f9fa;
            border-radius: 10px;
            border: 1px solid #dee2e6;
        }
        
        .embed-frame {
            width: 100%;
            height: 600px;
            border: none;
            border-radius: 8px;
        }
    </style>
</head>
<body class="bg-light">
    <?php if (!$embed_mode): ?>
        <?php include 'navbar.php'; ?>
    <?php endif; ?>

    <div class="container-fluid py-4">
        <?php if (!$embed_mode): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <h2 class="h3 mb-0">
                        <i class="fas fa-tools text-warning me-2"></i>
                        مدیریت ابزارها و تجهیزات نصب
                    </h2>
                    <p class="text-muted">ثبت، تحویل و پیگیری ابزارهای نصب و تعمیر</p>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- دکمه‌های عملیات -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#addToolModal">
                        <i class="fas fa-plus me-1"></i>افزودن ابزار جدید
                    </button>
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#issueToolModal" onclick="updateAvailableToolsList()">
                        <i class="fas fa-hand-holding me-1"></i>تحویل ابزار
                    </button>
                    <button type="button" class="btn btn-info" onclick="loadToolsData('all'); loadToolsData('available'); loadToolsData('issued'); loadToolsData('overdue');">
                        <i class="fas fa-sync me-1"></i>بروزرسانی
                    </button>
                </div>
            </div>
        </div>

        <!-- آمار سریع -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-primary">ابزارهای موجود</h5>
                        <h3 class="text-primary available-count"><?php echo count($available_tools); ?></h3>
                        <small class="text-muted">قابل تحویل</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-info">تحویل داده شده</h5>
                        <h3 class="text-info issued-count"><?php echo count($tools_issued); ?></h3>
                        <small class="text-muted">در حال استفاده</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-success">برگشت داده شده</h5>
                        <h3 class="text-success"><?php echo count($tools_returned); ?></h3>
                        <small class="text-muted">تاریخی</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-danger">تاخیر در برگشت</h5>
                        <h3 class="text-danger overdue-count"><?php echo count($tools_overdue); ?></h3>
                        <small class="text-muted">نیاز به پیگیری</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- تب‌های مدیریت -->
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">
                    <i class="fas fa-tools me-2"></i>مدیریت ابزارها
                </h5>
            </div>
            <div class="card-body">
                <ul class="nav nav-tabs nav-fill mb-3" id="toolsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="all-tools-tab" data-bs-toggle="tab" data-bs-target="#all-tools" type="button" role="tab">
                            <i class="fas fa-list"></i>همه ابزارها
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="available-tools-tab" data-bs-toggle="tab" data-bs-target="#available-tools" type="button" role="tab">
                            <i class="fas fa-wrench"></i>ابزارهای موجود
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="issued-tools-tab" data-bs-toggle="tab" data-bs-target="#issued-tools" type="button" role="tab">
                            <i class="fas fa-hand-holding"></i>تحویل داده شده
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="overdue-tools-tab" data-bs-toggle="tab" data-bs-target="#overdue-tools" type="button" role="tab">
                            <i class="fas fa-exclamation-triangle"></i>تاخیر در برگشت
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="toolsTabContent">
                    <div class="tab-pane fade show active" id="all-tools" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>کد ابزار</th>
                                        <th>نام</th>
                                        <th>دسته‌بندی</th>
                                        <th>برند</th>
                                        <th>وضعیت</th>
                                        <th>مکان</th>
                                        <th>عملیات</th>
                                    </tr>
                                </thead>
                                <tbody id="all-tools-table">
                                    <!-- داده‌ها با JavaScript بارگذاری می‌شوند -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="tab-pane fade" id="available-tools" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>کد ابزار</th>
                                        <th>نام</th>
                                        <th>دسته‌بندی</th>
                                        <th>برند</th>
                                        <th>مکان</th>
                                        <th>عملیات</th>
                                    </tr>
                                </thead>
                                <tbody id="available-tools-table">
                                    <!-- داده‌ها با JavaScript بارگذاری می‌شوند -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="tab-pane fade" id="issued-tools" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>کد ابزار</th>
                                        <th>نام</th>
                                        <th>تحویل به</th>
                                        <th>تاریخ تحویل</th>
                                        <th>تاریخ بازگشت مورد انتظار</th>
                                        <th>عملیات</th>
                                    </tr>
                                </thead>
                                <tbody id="issued-tools-table">
                                    <!-- داده‌ها با JavaScript بارگذاری می‌شوند -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="tab-pane fade" id="overdue-tools" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>کد ابزار</th>
                                        <th>نام</th>
                                        <th>تحویل به</th>
                                        <th>تاریخ تحویل</th>
                                        <th>تاریخ بازگشت مورد انتظار</th>
                                        <th>تاخیر (روز)</th>
                                        <th>عملیات</th>
                                    </tr>
                                </thead>
                                <tbody id="overdue-tools-table">
                                    <!-- داده‌ها با JavaScript بارگذاری می‌شوند -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- مودال افزودن ابزار -->
    <div class="modal fade" id="addToolModal" tabindex="-1" aria-labelledby="addToolModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="addToolModalLabel">
                        <i class="fas fa-plus me-2"></i>افزودن ابزار جدید
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" onsubmit="setTimeout(refreshAllData, 1000);">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_tool">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">نام ابزار *</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="category" class="form-label">دسته‌بندی *</label>
                                <select class="form-select" id="category" name="category" required>
                                    <option value="">انتخاب کنید</option>
                                    <option value="ابزار_دستی">ابزار دستی</option>
                                    <option value="ابزار_برقی">ابزار برقی</option>
                                    <option value="تجهیزات_اندازه_گیری">تجهیزات اندازه‌گیری</option>
                                    <option value="تجهیزات_ایمنی">تجهیزات ایمنی</option>
                                    <option value="سایر">سایر</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="brand" class="form-label">برند</label>
                                <input type="text" class="form-control" id="brand" name="brand">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="model" class="form-label">مدل</label>
                                <input type="text" class="form-control" id="model" name="model">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="serial_number" class="form-label">شماره سریال</label>
                                <input type="text" class="form-control" id="serial_number" name="serial_number">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="purchase_date" class="form-label">تاریخ خرید</label>
                                <input type="text" class="form-control jalali-date" id="purchase_date" name="purchase_date" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="purchase_price" class="form-label">قیمت خرید</label>
                                <input type="number" class="form-control" id="purchase_price" name="purchase_price" step="0.01">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="supplier" class="form-label">تامین‌کننده</label>
                                <input type="text" class="form-control" id="supplier" name="supplier">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="location" class="form-label">مکان</label>
                                <input type="text" class="form-control" id="location" name="location">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="maintenance_date" class="form-label">تاریخ تعمیر</label>
                                <input type="text" class="form-control jalali-date" id="maintenance_date" name="maintenance_date" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="next_maintenance_date" class="form-label">تاریخ تعمیر بعدی</label>
                                <input type="text" class="form-control jalali-date" id="next_maintenance_date" name="next_maintenance_date" readonly>
                            </div>
                            <div class="col-12 mb-3">
                                <label for="condition_notes" class="form-label">یادداشت‌های وضعیت</label>
                                <textarea class="form-control" id="condition_notes" name="condition_notes" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" class="btn btn-warning">افزودن ابزار</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- مودال ویرایش ابزار -->
    <div class="modal fade" id="editToolModal" tabindex="-1" aria-labelledby="editToolModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="editToolModalLabel">
                        <i class="fas fa-edit me-2"></i>ویرایش ابزار
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" onsubmit="setTimeout(refreshAllData, 1000);">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_tool">
                        <input type="hidden" name="tool_id" id="edit_tool_id">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_name" class="form-label">نام ابزار *</label>
                                <input type="text" class="form-control" id="edit_name" name="name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_category" class="form-label">دسته‌بندی *</label>
                                <select class="form-select" id="edit_category" name="category" required>
                                    <option value="">انتخاب کنید</option>
                                    <option value="ابزار_دستی">ابزار دستی</option>
                                    <option value="ابزار_برقی">ابزار برقی</option>
                                    <option value="تجهیزات_اندازه_گیری">تجهیزات اندازه‌گیری</option>
                                    <option value="تجهیزات_ایمنی">تجهیزات ایمنی</option>
                                    <option value="سایر">سایر</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_brand" class="form-label">برند</label>
                                <input type="text" class="form-control" id="edit_brand" name="brand">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_model" class="form-label">مدل</label>
                                <input type="text" class="form-control" id="edit_model" name="model">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_serial_number" class="form-label">شماره سریال</label>
                                <input type="text" class="form-control" id="edit_serial_number" name="serial_number">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_purchase_date" class="form-label">تاریخ خرید</label>
                                <input type="text" class="form-control jalali-date" id="edit_purchase_date" name="purchase_date" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_purchase_price" class="form-label">قیمت خرید</label>
                                <input type="number" class="form-control" id="edit_purchase_price" name="purchase_price" step="0.01">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_supplier" class="form-label">تامین‌کننده</label>
                                <input type="text" class="form-control" id="edit_supplier" name="supplier">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_location" class="form-label">مکان</label>
                                <input type="text" class="form-control" id="edit_location" name="location">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_maintenance_date" class="form-label">تاریخ تعمیر</label>
                                <input type="text" class="form-control jalali-date" id="edit_maintenance_date" name="maintenance_date" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_next_maintenance_date" class="form-label">تاریخ تعمیر بعدی</label>
                                <input type="text" class="form-control jalali-date" id="edit_next_maintenance_date" name="next_maintenance_date" readonly>
                            </div>
                            <div class="col-12 mb-3">
                                <label for="edit_condition_notes" class="form-label">یادداشت‌های وضعیت</label>
                                <textarea class="form-control" id="edit_condition_notes" name="condition_notes" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" class="btn btn-primary">ویرایش ابزار</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- مودال تحویل ابزار -->
    <div class="modal fade" id="issueToolModal" tabindex="-1" aria-labelledby="issueToolModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="issueToolModalLabel">
                        <i class="fas fa-hand-holding me-2"></i>تحویل ابزار
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" onsubmit="setTimeout(refreshAllData, 1000);">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="issue_tool">
                        <div class="mb-3">
                            <label for="tool_id" class="form-label">انتخاب ابزار *</label>
                            <select class="form-select" id="tool_id" name="tool_id" required>
                                <option value="">انتخاب کنید</option>
                                <?php 
                                // دریافت ابزارهای موجود از دیتابیس
                                $available_tools_for_issue = $pdo->query("SELECT * FROM tools WHERE status = 'موجود' ORDER BY name")->fetchAll();
                                foreach ($available_tools_for_issue as $tool): 
                                ?>
                                    <option value="<?php echo $tool['id']; ?>">
                                        <?php echo $tool['tool_code']; ?> - <?php echo $tool['name']; ?> (<?php echo $tool['category']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">فقط ابزارهای موجود (تحویل داده نشده) در این لیست نمایش داده می‌شوند</div>
                        </div>
                        <div class="mb-3">
                            <label for="issued_to" class="form-label">تحویل به *</label>
                            <input type="text" class="form-control" id="issued_to" name="issued_to" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="issue_date" class="form-label">تاریخ تحویل *</label>
                                <input type="text" class="form-control jalali-date" id="issue_date" name="issue_date" required readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="expected_return_date" class="form-label">تاریخ بازگشت مورد انتظار</label>
                                <input type="text" class="form-control jalali-date" id="expected_return_date" name="expected_return_date" readonly>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="purpose" class="form-label">هدف استفاده</label>
                            <textarea class="form-control" id="purpose" name="purpose" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="condition_before" class="form-label">وضعیت قبل از تحویل</label>
                            <textarea class="form-control" id="condition_before" name="condition_before" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="notes" class="form-label">یادداشت‌ها</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" class="btn btn-success">تحویل ابزار</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- مودال استرداد ابزار -->
    <div class="modal fade" id="returnToolModal" tabindex="-1" aria-labelledby="returnToolModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="returnToolModalLabel">
                        <i class="fas fa-undo me-2"></i>استرداد ابزار
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" onsubmit="setTimeout(refreshAllData, 1000);">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="return_tool">
                        <input type="hidden" name="tool_issue_id" id="return_tool_issue_id">
                        <input type="hidden" name="tool_id" id="return_tool_id">
                        <div class="mb-3">
                            <label class="form-label">ابزار</label>
                            <input type="text" class="form-control" id="return_tool_name" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">تحویل به</label>
                            <input type="text" class="form-control" id="return_issued_to" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">تاریخ تحویل</label>
                            <input type="text" class="form-control" id="return_issue_date" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="condition_after" class="form-label">وضعیت پس از استرداد *</label>
                            <textarea class="form-control" id="condition_after" name="condition_after" rows="3" required placeholder="وضعیت ابزار پس از استرداد را شرح دهید..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" class="btn btn-info">استرداد ابزار</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- مودال تاریخچه ابزار -->
    <div class="modal fade history-modal" id="toolHistoryModal" tabindex="-1" aria-labelledby="toolHistoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="toolHistoryModalLabel">
                        <i class="fas fa-history me-2"></i>تاریخچه ابزار
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6>نام ابزار: <span id="history_tool_name" class="text-primary"></span></h6>
                        </div>
                        <div class="col-md-6">
                            <h6>کد ابزار: <span id="history_tool_code" class="text-muted"></span></h6>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover history-table">
                            <thead>
                                <tr>
                                    <th>تاریخ و زمان</th>
                                    <th>عملیات</th>
                                    <th>شرح</th>
                                    <th>انجام دهنده</th>
                                    <th>جزئیات</th>
                                </tr>
                            </thead>
                            <tbody id="tool-history-table">
                                <!-- تاریخچه با JavaScript بارگذاری می‌شود -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/persian-date@1.1.0/dist/persian-date.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js"></script>
    <script>
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
    <script>
        // بارگذاری داده‌های ابزارها
        function loadToolsData(type) {
            fetch('get_tools_data.php?type=' + type)
                .then(response => response.json())
                .then(data => {
                    loadToolsTable(type, data);
                    // به‌روزرسانی آمار
                    updateStats();
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        }
        
        // به‌روزرسانی آمار
        function updateStats() {
            fetch('get_tools_data.php?type=available')
                .then(response => response.json())
                .then(data => {
                    document.querySelector('.available-count').textContent = data.length;
                });
            
            fetch('get_tools_data.php?type=issued')
                .then(response => response.json())
                .then(data => {
                    document.querySelector('.issued-count').textContent = data.length;
                });
            
            fetch('get_tools_data.php?type=overdue')
                .then(response => response.json())
                .then(data => {
                    document.querySelector('.overdue-count').textContent = data.length;
                });
        }

        // بارگذاری جدول ابزارها
        function loadToolsTable(type, data) {
            const tableBody = document.getElementById(type + '-tools-table');
            if (!tableBody) return;

            tableBody.innerHTML = '';
            
            if (data.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">هیچ داده‌ای یافت نشد</td></tr>';
                return;
            }

            data.forEach(item => {
                const row = document.createElement('tr');
                
                if (type === 'all') {
                    row.innerHTML = `
                        <td>${item.tool_code}</td>
                        <td>${item.name}</td>
                        <td>${item.category}</td>
                        <td>${item.brand || '-'}</td>
                        <td><span class="badge status-badge ${getStatusColor(item.status)}">${item.status}</span></td>
                        <td>${item.location || '-'}</td>
                        <td>
                            <button class="btn btn-sm btn-info" onclick="viewTool(${item.id})" title="مشاهده">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-warning" onclick="editTool(${item.id})" title="ویرایش">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-primary" onclick="showToolHistory(${item.id}, '${item.name}', '${item.tool_code}')" title="تاریخچه">
                                <i class="fas fa-history"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deleteTool(${item.id}, '${item.name}', '${item.tool_code}')" title="حذف">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    `;
                } else if (type === 'available') {
                    row.innerHTML = `
                        <td>${item.tool_code}</td>
                        <td>${item.name}</td>
                        <td>${item.category}</td>
                        <td>${item.brand || '-'}</td>
                        <td>${item.location || '-'}</td>
                        <td>
                            <button class="btn btn-sm btn-success" onclick="issueTool(${item.id})" title="تحویل">
                                <i class="fas fa-hand-holding"></i>
                            </button>
                        </td>
                    `;
                } else if (type === 'issued' || type === 'overdue') {
                    const delayDays = type === 'overdue' ? Math.ceil((new Date() - new Date(item.expected_return_date)) / (1000 * 60 * 60 * 24)) : 0;
                    row.innerHTML = `
                        <td>${item.tool_code}</td>
                        <td>${item.tool_name}</td>
                        <td>${item.issued_to}</td>
                        <td>${item.issue_date}</td>
                        <td>${item.expected_return_date || '-'}</td>
                        ${type === 'overdue' ? `<td><span class="badge bg-danger">${delayDays} روز</span></td>` : ''}
                        <td>
                            <button class="btn btn-sm btn-info" onclick="returnTool(${item.id}, ${item.tool_id}, '${item.tool_name}', '${item.issued_to}', '${item.issue_date}')" title="استرداد">
                                <i class="fas fa-undo"></i>
                            </button>
                            <button class="btn btn-sm btn-warning" onclick="remindReturn(${item.id})" title="یادآوری">
                                <i class="fas fa-bell"></i>
                            </button>
                        </td>
                    `;
                }
                
                tableBody.appendChild(row);
            });
        }

        // رنگ‌بندی وضعیت
        function getStatusColor(status) {
            switch(status) {
                case 'موجود': return 'bg-success';
                case 'تحویل_داده_شده': return 'bg-info';
                case 'تعمیر': return 'bg-warning';
                case 'از_دست_رفته': return 'bg-danger';
                case 'خراب': return 'bg-secondary';
                default: return 'bg-secondary';
            }
        }

        // مشاهده ابزار
        function viewTool(id) {
            // دریافت اطلاعات ابزار
            fetch('get_tool_details.php?id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToolDetailsModal(data.tool);
                    } else {
                        alert('خطا در دریافت اطلاعات ابزار: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('خطا در دریافت اطلاعات ابزار');
                });
        }

        // ویرایش ابزار
        function editTool(id) {
            // دریافت اطلاعات ابزار برای ویرایش
            fetch('get_tool_details.php?id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showEditToolModal(data.tool);
                    } else {
                        alert('خطا در دریافت اطلاعات ابزار: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('خطا در دریافت اطلاعات ابزار');
                });
        }

        // نمایش مودال جزئیات ابزار
        function showToolDetailsModal(tool) {
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.innerHTML = `
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-info text-white">
                            <h5 class="modal-title">
                                <i class="fas fa-eye me-2"></i>جزئیات ابزار
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>اطلاعات پایه</h6>
                                    <table class="table table-sm">
                                        <tr><td><strong>کد ابزار:</strong></td><td>${tool.tool_code}</td></tr>
                                        <tr><td><strong>نام:</strong></td><td>${tool.name}</td></tr>
                                        <tr><td><strong>دسته‌بندی:</strong></td><td>${tool.category}</td></tr>
                                        <tr><td><strong>برند:</strong></td><td>${tool.brand || '-'}</td></tr>
                                        <tr><td><strong>مدل:</strong></td><td>${tool.model || '-'}</td></tr>
                                        <tr><td><strong>شماره سریال:</strong></td><td>${tool.serial_number || '-'}</td></tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <h6>اطلاعات تکمیلی</h6>
                                    <table class="table table-sm">
                                        <tr><td><strong>وضعیت:</strong></td><td><span class="badge ${getStatusColor(tool.status)}">${tool.status}</span></td></tr>
                                        <tr><td><strong>مکان:</strong></td><td>${tool.location || '-'}</td></tr>
                                        <tr><td><strong>تاریخ خرید:</strong></td><td>${tool.purchase_date || '-'}</td></tr>
                                        <tr><td><strong>قیمت خرید:</strong></td><td>${tool.purchase_price ? tool.purchase_price + ' تومان' : '-'}</td></tr>
                                        <tr><td><strong>تامین‌کننده:</strong></td><td>${tool.supplier || '-'}</td></tr>
                                    </table>
                                </div>
                            </div>
                            ${tool.condition_notes ? `
                                <div class="mt-3">
                                    <h6>یادداشت‌های وضعیت</h6>
                                    <p class="text-muted">${tool.condition_notes}</p>
                                </div>
                            ` : ''}
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                            <button type="button" class="btn btn-warning" onclick="editTool(${tool.id})" data-bs-dismiss="modal">ویرایش</button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            new bootstrap.Modal(modal).show();
            modal.addEventListener('hidden.bs.modal', () => modal.remove());
        }

        // نمایش مودال ویرایش ابزار
        function showEditToolModal(tool) {
            // پر کردن فرم ویرایش
            document.getElementById('edit_tool_id').value = tool.id;
            document.getElementById('edit_name').value = tool.name;
            document.getElementById('edit_category').value = tool.category;
            document.getElementById('edit_brand').value = tool.brand || '';
            document.getElementById('edit_model').value = tool.model || '';
            document.getElementById('edit_serial_number').value = tool.serial_number || '';
            document.getElementById('edit_purchase_date').value = tool.purchase_date || '';
            document.getElementById('edit_purchase_price').value = tool.purchase_price || '';
            document.getElementById('edit_supplier').value = tool.supplier || '';
            document.getElementById('edit_location').value = tool.location || '';
            document.getElementById('edit_maintenance_date').value = tool.maintenance_date || '';
            document.getElementById('edit_next_maintenance_date').value = tool.next_maintenance_date || '';
            document.getElementById('edit_condition_notes').value = tool.condition_notes || '';
            
            // نمایش مودال ویرایش
            new bootstrap.Modal(document.getElementById('editToolModal')).show();
        }

        // تحویل ابزار
        function issueTool(id) {
            document.getElementById('tool_id').value = id;
            new bootstrap.Modal(document.getElementById('issueToolModal')).show();
        }
        
        // به‌روزرسانی لیست ابزارهای موجود برای تحویل
        function updateAvailableToolsList() {
            fetch('get_tools_data.php?type=available')
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById('tool_id');
                    select.innerHTML = '<option value="">انتخاب کنید</option>';
                    
                    data.forEach(tool => {
                        const option = document.createElement('option');
                        option.value = tool.id;
                        option.textContent = `${tool.tool_code} - ${tool.name} (${tool.category})`;
                        select.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Error updating available tools:', error);
                });
        }

        // استرداد ابزار
        function returnTool(toolIssueId, toolId, toolName, issuedTo, issueDate) {
            // پر کردن فرم استرداد
            document.getElementById('return_tool_issue_id').value = toolIssueId;
            document.getElementById('return_tool_id').value = toolId;
            document.getElementById('return_tool_name').value = toolName;
            document.getElementById('return_issued_to').value = issuedTo;
            document.getElementById('return_issue_date').value = issueDate;
            
            // نمایش مودال استرداد
            new bootstrap.Modal(document.getElementById('returnToolModal')).show();
        }

        // یادآوری برگشت ابزار
        function remindReturn(id) {
            alert('یادآوری برگشت ابزار ارسال شد');
        }

        // حذف ابزار
        function deleteTool(id, name, code) {
            if (confirm(`آیا از حذف ابزار "${name}" (${code}) مطمئن هستید؟\n\nتوجه: این عمل قابل بازگشت نیست!`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_tool">
                    <input type="hidden" name="tool_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // نمایش تاریخچه ابزار
        function showToolHistory(id, name, code) {
            document.getElementById('history_tool_name').textContent = name;
            document.getElementById('history_tool_code').textContent = code;
            
            // بارگذاری تاریخچه
            fetch(`get_tool_history.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    const tbody = document.getElementById('tool-history-table');
                    tbody.innerHTML = '';
                    
                    if (data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">هیچ تاریخچه‌ای یافت نشد</td></tr>';
                        return;
                    }
                    
                    data.forEach(history => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${formatDateTime(history.performed_at)}</td>
                            <td><span class="badge ${getActionBadgeColor(history.action_type)}">${history.action_type}</span></td>
                            <td>${history.action_description}</td>
                            <td>${history.performer_name || 'نامشخص'}</td>
                            <td>
                                ${history.notes ? `<small class="text-muted">${history.notes}</small>` : ''}
                                ${history.old_values || history.new_values ? 
                                    `<button class="btn btn-sm btn-outline-info ms-2" onclick="showHistoryDetails(${history.id})" title="جزئیات">
                                        <i class="fas fa-info-circle"></i>
                                    </button>` : ''
                                }
                            </td>
                        `;
                        tbody.appendChild(row);
                    });
                })
                .catch(error => {
                    console.error('Error loading tool history:', error);
                    document.getElementById('tool-history-table').innerHTML = '<tr><td colspan="5" class="text-center text-danger">خطا در بارگذاری تاریخچه</td></tr>';
                });
            
            new bootstrap.Modal(document.getElementById('toolHistoryModal')).show();
        }

        // فرمت تاریخ و زمان
        function formatDateTime(dateTime) {
            const date = new Date(dateTime);
            return date.toLocaleString('fa-IR');
        }

        // رنگ بج عملیات
        function getActionBadgeColor(actionType) {
            switch(actionType) {
                case 'ایجاد': return 'bg-success';
                case 'ویرایش': return 'bg-warning';
                case 'تحویل': return 'bg-info';
                case 'استرداد': return 'bg-primary';
                case 'حذف': return 'bg-danger';
                case 'تعمیر': return 'bg-secondary';
                default: return 'bg-light text-dark';
            }
        }

        // نمایش جزئیات تاریخچه
        function showHistoryDetails(historyId) {
            // پیاده‌سازی نمایش جزئیات تغییرات
            alert('جزئیات تغییرات برای تاریخچه ID: ' + historyId);
        }

        // بارگذاری اولیه
        document.addEventListener('DOMContentLoaded', function() {
            loadToolsData('all');
            loadToolsData('available');
            loadToolsData('issued');
            loadToolsData('overdue');
            
            // تنظیم تاریخ امروز در فرم تحویل ابزار
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('issue_date').value = today;
            
            // به‌روزرسانی خودکار آمار هر 30 ثانیه
            setInterval(updateStats, 30000);
        });
        
        // به‌روزرسانی آمار پس از عملیات
        function refreshAllData() {
            loadToolsData('all');
            loadToolsData('available');
            loadToolsData('issued');
            loadToolsData('overdue');
            updateAvailableToolsList(); // به‌روزرسانی لیست ابزارهای موجود برای تحویل
        }
    </script>
</body>
</html>