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
} catch (Exception $e) {
    error_log("Error creating tools tables: " . $e->getMessage());
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
                    
                    // به‌روزرسانی وضعیت تحویل ابزار
                    $stmt = $pdo->prepare("UPDATE tool_issues SET status = 'برگشت_داده_شده', actual_return_date = CURDATE(), condition_after = ? WHERE id = ?");
                    $stmt->execute([
                        $_POST['condition_after'] ?? null,
                        $_POST['tool_issue_id']
                    ]);
                    
                    // به‌روزرسانی وضعیت ابزار
                    $stmt = $pdo->prepare("UPDATE tools SET status = 'موجود' WHERE id = ?");
                    $stmt->execute([$_POST['tool_id']]);
                    
                    $pdo->commit();
                    $_SESSION['success'] = "ابزار با موفقیت برگشت داده شد";
                    logAction($pdo, 'RETURN_TOOL', "برگشت ابزار: " . $_POST['tool_id']);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $_SESSION['error'] = "خطا در برگشت ابزار: " . $e->getMessage();
                    logAction($pdo, 'RETURN_TOOL_ERROR', "خطا در برگشت ابزار: " . $e->getMessage());
                }
                break;
        }
    }
}

// دریافت داده‌ها
try {
    $tools = $pdo->query("SELECT * FROM tools ORDER BY created_at DESC")->fetchAll();
    $tools_issued = $pdo->query("SELECT ti.*, t.name as tool_name, t.tool_code FROM tool_issues ti JOIN tools t ON ti.tool_id = t.id WHERE ti.status = 'تحویل_داده_شده' ORDER BY ti.issue_date DESC")->fetchAll();
    $tools_returned = $pdo->query("SELECT ti.*, t.name as tool_name, t.tool_code FROM tool_issues ti JOIN tools t ON ti.tool_id = t.id WHERE ti.status = 'برگشت_داده_شده' ORDER BY ti.actual_return_date DESC")->fetchAll();
    $tools_overdue = $pdo->query("SELECT ti.*, t.name as tool_name, t.tool_code FROM tool_issues ti JOIN tools t ON ti.tool_id = t.id WHERE ti.status = 'تحویل_داده_شده' AND ti.expected_return_date < CURDATE() ORDER BY ti.expected_return_date ASC")->fetchAll();
} catch (Exception $e) {
    $tools = [];
    $tools_issued = [];
    $tools_returned = [];
    $tools_overdue = [];
}

// دریافت ابزارهای موجود برای تحویل
$available_tools = [];
try {
    $available_tools = $pdo->query("SELECT * FROM tools WHERE status = 'موجود' ORDER BY name")->fetchAll();
} catch (Exception $e) {
    $available_tools = [];
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $embed_mode ? 'مدیریت ابزارها' : 'مدیریت ابزارها و تجهیزات نصب'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#issueToolModal">
                        <i class="fas fa-hand-holding me-1"></i>تحویل ابزار
                    </button>
                    <button type="button" class="btn btn-info" onclick="loadToolsData('all')">
                        <i class="fas fa-sync me-1"></i>بروزرسانی
                    </button>
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
                <form method="POST">
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
                                <input type="date" class="form-control" id="purchase_date" name="purchase_date">
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
                                <input type="date" class="form-control" id="maintenance_date" name="maintenance_date">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="next_maintenance_date" class="form-label">تاریخ تعمیر بعدی</label>
                                <input type="date" class="form-control" id="next_maintenance_date" name="next_maintenance_date">
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
                <form method="POST">
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
                                <input type="date" class="form-control" id="edit_purchase_date" name="purchase_date">
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
                                <input type="date" class="form-control" id="edit_maintenance_date" name="maintenance_date">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_next_maintenance_date" class="form-label">تاریخ تعمیر بعدی</label>
                                <input type="date" class="form-control" id="edit_next_maintenance_date" name="next_maintenance_date">
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
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="issue_tool">
                        <div class="mb-3">
                            <label for="tool_id" class="form-label">انتخاب ابزار *</label>
                            <select class="form-select" id="tool_id" name="tool_id" required>
                                <option value="">انتخاب کنید</option>
                                <?php foreach ($available_tools as $tool): ?>
                                    <option value="<?php echo $tool['id']; ?>">
                                        <?php echo $tool['tool_code']; ?> - <?php echo $tool['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="issued_to" class="form-label">تحویل به *</label>
                            <input type="text" class="form-control" id="issued_to" name="issued_to" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="issue_date" class="form-label">تاریخ تحویل *</label>
                                <input type="date" class="form-control" id="issue_date" name="issue_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="expected_return_date" class="form-label">تاریخ بازگشت مورد انتظار</label>
                                <input type="date" class="form-control" id="expected_return_date" name="expected_return_date">
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
                <form method="POST">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // بارگذاری داده‌های ابزارها
        function loadToolsData(type) {
            fetch('get_tools_data.php?type=' + type)
                .then(response => response.json())
                .then(data => {
                    loadToolsTable(type, data);
                })
                .catch(error => {
                    console.error('Error:', error);
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
                            <button class="btn btn-sm btn-info" onclick="returnTool(${item.id})" title="برگشت">
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

        // بارگذاری اولیه
        document.addEventListener('DOMContentLoaded', function() {
            loadToolsData('all');
            loadToolsData('available');
            loadToolsData('issued');
            loadToolsData('overdue');
        });
    </script>
</body>
</html>