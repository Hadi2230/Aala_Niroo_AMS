<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
include 'config.php';

if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

logAction($pdo, 'VIEW_ASSETS', 'مشاهده صفحه مدیریت دارایی‌ها');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_asset'])) {
    error_log("POST request received with add_asset");
    verifyCsrfToken();
    try {
        $pdo->beginTransaction();
        error_log("Transaction started");

        $name = sanitizeInput($_POST['name']);
        $type_id = (int)$_POST['type_id'];
        $serial_number = sanitizeInput($_POST['serial_number']);
        if (empty($serial_number)) {
            $serial_number = null; // Set to NULL for empty values to avoid UNIQUE constraint violation
        }
        
        error_log("Name: $name, Type ID: $type_id, Serial: $serial_number");
        $purchase_date_input = sanitizeInput($_POST['purchase_date'] ?? '');
        $purchase_date = jalaliToGregorianForDB($purchase_date_input);

        $status = sanitizeInput($_POST['status']);
        $brand = sanitizeInput($_POST['brand'] ?? '');
        $model = sanitizeInput($_POST['model'] ?? '');

        $stmt = $pdo->prepare("SELECT name, display_name FROM asset_types WHERE id = ?");
        $stmt->execute([$type_id]);
        $asset_type = $stmt->fetch();
        $asset_type_name = $asset_type['display_name'] ?? '';
        
        // Debug: بررسی نوع دارایی
        error_log("Asset type ID: $type_id, Name: " . ($asset_type['name'] ?? 'NULL') . ", Display Name: " . ($asset_type['display_name'] ?? 'NULL'));
        
        // اگر display_name خالی است، از name استفاده کن
        if (empty($asset_type_name) && !empty($asset_type['name'])) {
            $asset_type_name = $asset_type['name'];
        }
        
        // Debug: بررسی مقادیر
        error_log("Name: $name, Type ID: $type_id, Asset Type Name: $asset_type_name");

        $power_capacity = sanitizeInput($_POST['power_capacity'] ?? '');
        $engine_type = sanitizeInput($_POST['engine_type'] ?? '');
        $consumable_type = sanitizeInput($_POST['consumable_type'] ?? '');

        $engine_model = sanitizeInput($_POST['engine_model'] ?? '');
        $engine_serial = sanitizeInput($_POST['engine_serial'] ?? '');
        $alternator_model = sanitizeInput($_POST['alternator_model'] ?? '');
        $alternator_serial = sanitizeInput($_POST['alternator_serial'] ?? '');
        $device_model = sanitizeInput($_POST['device_model'] ?? '');
        $device_serial = sanitizeInput($_POST['device_serial'] ?? '');
        $control_panel_model = sanitizeInput($_POST['control_panel_model'] ?? '');
        $breaker_model = sanitizeInput($_POST['breaker_model'] ?? '');
        $fuel_tank_specs = sanitizeInput($_POST['fuel_tank_specs'] ?? '');
        $battery = sanitizeInput($_POST['battery'] ?? '');
        $battery_charger = sanitizeInput($_POST['battery_charger'] ?? '');
        $heater = sanitizeInput($_POST['heater'] ?? '');
        $oil_capacity = sanitizeInput($_POST['oil_capacity'] ?? '');
        $radiator_capacity = sanitizeInput($_POST['radiator_capacity'] ?? '');
        $antifreeze = sanitizeInput($_POST['antifreeze'] ?? '');
        $other_items = sanitizeInput($_POST['other_items'] ?? '');
        $workshop_entry_date_input = sanitizeInput($_POST['workshop_entry_date'] ?? '');
        $workshop_entry_date = jalaliToGregorianForDB($workshop_entry_date_input);

        $workshop_exit_date_input = sanitizeInput($_POST['workshop_exit_date'] ?? '');
        $workshop_exit_date = jalaliToGregorianForDB($workshop_exit_date_input);

        $datasheet_link = sanitizeInput($_POST['datasheet_link'] ?? '');
        $engine_manual_link = sanitizeInput($_POST['engine_manual_link'] ?? '');
        $alternator_manual_link = sanitizeInput($_POST['alternator_manual_link'] ?? '');
        $control_panel_manual_link = sanitizeInput($_POST['control_panel_manual_link'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');

        $oil_filter_part = sanitizeInput($_POST['oil_filter_part'] ?? '');
        $fuel_filter_part = sanitizeInput($_POST['fuel_filter_part'] ?? '');
        $water_fuel_filter_part = sanitizeInput($_POST['water_fuel_filter_part'] ?? '');
        $air_filter_part = sanitizeInput($_POST['air_filter_part'] ?? '');
        $water_filter_part = sanitizeInput($_POST['water_filter_part'] ?? '');

        // دریافت فیلدهای جدید
        $device_identifier = sanitizeInput($_POST['device_identifier'] ?? '');
        if (empty($device_identifier)) {
            $device_identifier = null; // Set to NULL for empty values
        }
        $supply_method = sanitizeInput($_POST['supply_method'] ?? '');
        if (empty($supply_method)) {
            $supply_method = null;
        }
        $location = sanitizeInput($_POST['location'] ?? '');
        if (empty($location)) {
            $location = null;
        }
        $quantity = (int)($_POST['quantity'] ?? 0);
        $supplier_name = sanitizeInput($_POST['supplier_name'] ?? '');
        if (empty($supplier_name)) {
            $supplier_name = null;
        }
        $supplier_contact = sanitizeInput($_POST['supplier_contact'] ?? '');
        if (empty($supplier_contact)) {
            $supplier_contact = null;
        }
        
        // تنظیم brand و model بر اساس نوع دارایی
        if ($asset_type_name && (strpos($asset_type_name, 'ژنراتور') !== false || strpos($asset_type_name, 'generator') !== false)) {
            $brand = $name; // نام دستگاه به عنوان برند
            $model = sanitizeInput($_POST['device_model'] ?? '');
        } else if ($asset_type_name && (strpos($asset_type_name, 'موتور برق') !== false || strpos($asset_type_name, 'power_motor') !== false)) {
            $brand = $name; // نام موتور به عنوان برند
            $model = sanitizeInput($_POST['engine_type'] ?? '');
        } else {
            // اگر نوع دارایی مشخص نیست، از مقادیر پیش‌فرض استفاده کن
            $brand = $brand ?: $name;
            $model = $model ?: '';
        }

        $stmt = $pdo->prepare("INSERT INTO assets (name, type_id, serial_number, purchase_date, status, brand, model, 
                              power_capacity, engine_type, consumable_type, engine_model, engine_serial, 
                              alternator_model, alternator_serial, device_model, device_serial, control_panel_model, 
                              breaker_model, fuel_tank_specs, battery, battery_charger, heater, oil_capacity, 
                              radiator_capacity, antifreeze, other_items, workshop_entry_date, workshop_exit_date, 
                              datasheet_link, engine_manual_link, alternator_manual_link, control_panel_manual_link, 
                              description, oil_filter_part, fuel_filter_part, water_fuel_filter_part, air_filter_part, 
                              water_filter_part, device_identifier, supply_method, location, quantity, supplier_name, supplier_contact) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $name, $type_id, $serial_number, $purchase_date, $status, $brand, $model,
            $power_capacity, $engine_type, $consumable_type, $engine_model, $engine_serial,
            $alternator_model, $alternator_serial, $device_model, $device_serial, $control_panel_model,
            $breaker_model, $fuel_tank_specs, $battery, $battery_charger, $heater, $oil_capacity,
            $radiator_capacity, $antifreeze, $other_items, $workshop_entry_date, $workshop_exit_date,
            $datasheet_link, $engine_manual_link, $alternator_manual_link, $control_panel_manual_link,
            $description, $oil_filter_part, $fuel_filter_part, $water_fuel_filter_part, $air_filter_part,
            $water_filter_part, $device_identifier, $supply_method, $location, $quantity, $supplier_name, $supplier_contact
        ]);

        $asset_id = $pdo->lastInsertId();

        $upload_dir = 'uploads/assets/';
        $image_fields = ['oil_filter', 'fuel_filter', 'water_fuel_filter', 'air_filter', 'water_filter', 'device_image'];
        foreach ($image_fields as $field) {
            if (!empty($_FILES[$field]['name'])) {
                try {
                    $image_path = uploadFile($_FILES[$field], $upload_dir);
                    $stmt = $pdo->prepare("INSERT INTO asset_images (asset_id, field_name, image_path) VALUES (?, ?, ?)");
                    $stmt->execute([$asset_id, $field, $image_path]);
                } catch (Exception $e) {
                    error_log("خطا در آپلود عکس $field: " . $e->getMessage());
                }
            }
        }

        $pdo->commit();
        
        // Debug: بررسی commit
        error_log("Transaction committed successfully");
        error_log("Asset ID: " . $asset_id);
        
        // پیام موفقیت سفارشی بر اساس نوع دارایی
        $success_message = "";
        if ($asset_type_name && (strpos($asset_type_name, 'ژنراتور') !== false || strpos($asset_type_name, 'generator') !== false)) {
            $identifier = $device_identifier ?: $serial_number;
            $success_message = "ژنراتور به شماره شناسه دستگاه $identifier با موفقیت ثبت شد!";
        } else if ($asset_type_name && (strpos($asset_type_name, 'موتور برق') !== false || strpos($asset_type_name, 'power_motor') !== false)) {
            $success_message = "موتور برق با شماره سریال $serial_number با موفقیت ثبت شد!";
        } else if ($asset_type_name && (strpos($asset_type_name, 'مصرفی') !== false || strpos($asset_type_name, 'consumable') !== false)) {
            $success_message = "کالای مصرفی $name با موفقیت ثبت شد!";
        } else if ($asset_type_name && (strpos($asset_type_name, 'قطعات') !== false || strpos($asset_type_name, 'parts') !== false)) {
            $success_message = "قطعه $name با موفقیت ثبت شد!";
        } else {
            $success_message = "دارایی $name با موفقیت ثبت شد!";
        }
        
        // Debug: بررسی پیام موفقیت
        error_log("Success message: $success_message");
        error_log("Asset type name: $asset_type_name");
        error_log("Device identifier: $device_identifier");
        error_log("Serial number: $serial_number");
        error_log("Asset ID: $asset_id");
        
        $_SESSION['success'] = $success_message;
        logAction($pdo, 'ADD_ASSET', "افزودن دارایی جدید: $name (ID: $asset_id)");
        error_log("Redirecting to assets.php");
        header('Location: assets.php');
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error in asset creation: " . $e->getMessage());
        $_SESSION['error'] = "خطا در افزودن دارایی: " . $e->getMessage();
        logAction($pdo, 'ADD_ASSET_ERROR', "خطا در افزودن دارایی: " . $e->getMessage());
    }
}

// افزودن ابزار جدید
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_tool'])) {
    verifyCsrfToken();
    try {
        $pdo->beginTransaction();
        
        // تولید کد ابزار
        $tool_code = 'T' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // بررسی یکتایی کد
        $check_stmt = $pdo->prepare("SELECT id FROM tools WHERE tool_code = ?");
        $check_stmt->execute([$tool_code]);
        while ($check_stmt->fetch()) {
            $tool_code = 'T' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $check_stmt->execute([$tool_code]);
        }
        
        $stmt = $pdo->prepare("INSERT INTO tools (
            tool_code, name, category, brand, model, serial_number, 
            purchase_date, purchase_price, supplier, location, 
            condition_notes, next_maintenance_date
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $tool_code,
            sanitizeInput($_POST['tool_name']),
            sanitizeInput($_POST['tool_category']),
            sanitizeInput($_POST['tool_brand'] ?? ''),
            sanitizeInput($_POST['tool_model'] ?? ''),
            sanitizeInput($_POST['tool_serial'] ?? ''),
            $_POST['tool_purchase_date'] ?: null,
            $_POST['tool_price'] ?: null,
            sanitizeInput($_POST['tool_supplier'] ?? ''),
            sanitizeInput($_POST['tool_location'] ?? ''),
            sanitizeInput($_POST['tool_notes'] ?? ''),
            $_POST['tool_next_maintenance'] ?: null
        ]);
        
        $pdo->commit();
        $_SESSION['success'] = "ابزار با موفقیت اضافه شد!";
        logAction($pdo, 'ADD_TOOL', "افزودن ابزار جدید: " . sanitizeInput($_POST['tool_name']) . " (کد: $tool_code)");
        header('Location: assets.php');
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "خطا در افزودن ابزار: " . $e->getMessage();
        logAction($pdo, 'ADD_TOOL_ERROR', "خطا در افزودن ابزار: " . $e->getMessage());
    }
}

// تحویل ابزار
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['issue_tool'])) {
    verifyCsrfToken();
    try {
        $pdo->beginTransaction();
        
        $tool_id = (int)$_POST['tool_id'];
        $issued_to = sanitizeInput($_POST['issued_to']);
        $issue_date = jalaliToGregorianForDB($_POST['issue_date']);
        $expected_return_date = jalaliToGregorianForDB($_POST['expected_return_date'] ?: '');
        $purpose = sanitizeInput($_POST['purpose'] ?? '');
        $condition_before = sanitizeInput($_POST['condition_before'] ?? '');
        $notes = sanitizeInput($_POST['notes'] ?? '');
        
        // بررسی موجود بودن ابزار
        $check_stmt = $pdo->prepare("SELECT tool_code, name FROM tools WHERE id = ? AND status = 'موجود'");
        $check_stmt->execute([$tool_id]);
        $tool = $check_stmt->fetch();
        
        if (!$tool) {
            throw new Exception("ابزار انتخاب شده موجود نیست یا قبلاً تحویل داده شده است.");
        }
        
        // ثبت تحویل
        $stmt = $pdo->prepare("INSERT INTO tool_issues (
            tool_id, issued_to, issued_by, issue_date, expected_return_date,
            purpose, condition_before, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $tool_id,
            $issued_to,
            $_SESSION['user_id'],
            $issue_date,
            $expected_return_date,
            $purpose,
            $condition_before,
            $notes
        ]);
        
        // تغییر وضعیت ابزار
        $update_stmt = $pdo->prepare("UPDATE tools SET status = 'تحویل_داده_شده' WHERE id = ?");
        $update_stmt->execute([$tool_id]);
        
        $pdo->commit();
        $_SESSION['success'] = "ابزار با موفقیت تحویل داده شد!";
        logAction($pdo, 'ISSUE_TOOL', "تحویل ابزار: " . $tool['name'] . " به " . $issued_to);
        header('Location: assets.php');
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "خطا در تحویل ابزار: " . $e->getMessage();
        logAction($pdo, 'ISSUE_TOOL_ERROR', "خطا در تحویل ابزار: " . $e->getMessage());
    }
}

if (isset($_GET['delete_id'])) {
    checkPermission('ادمین');
    $delete_id = (int)$_GET['delete_id'];
    try {
        $stmt = $pdo->prepare("SELECT name FROM assets WHERE id = ?");
        $stmt->execute([$delete_id]);
        $asset = $stmt->fetch();
        if ($asset) {
            $stmt = $pdo->prepare("DELETE FROM assets WHERE id = ?");
            $stmt->execute([$delete_id]);
            $_SESSION['success'] = "دارایی با موفقیت حذف شد!";
            logAction($pdo, 'DELETE_ASSET', "حذف دارایی: " . $asset['name'] . " (ID: $delete_id)");
        } else {
            $_SESSION['error'] = "دارایی مورد نظر یافت نشد!";
        }
        header('Location: assets.php');
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = "خطا در حذف دارایی: " . $e->getMessage();
        logAction($pdo, 'DELETE_ASSET_ERROR', "خطا در حذف دارایی ID: $delete_id - " . $e->getMessage());
    }
}

try {
    $defaults = [
        ['generator',   'ژنراتور'],
        ['power_motor', 'موتور برق'],
        ['consumable',  'اقلام مصرفی'],
        ['parts',       'قطعات']
    ];
    foreach ($defaults as [$name, $display]) {
        $chk = $pdo->prepare('SELECT id FROM asset_types WHERE name = ? LIMIT 1');
        $chk->execute([$name]);
        if (!$chk->fetch()) {
            $ins = $pdo->prepare('INSERT INTO asset_types (name, display_name) VALUES (?, ?)');
            $ins->execute([$name, $display]);
        }
    }
} catch (Throwable $e) {}

$asset_types = $pdo->query("SELECT * FROM asset_types ORDER BY display_name")->fetchAll();

// سیستم جستجوی جامع
$search = $_GET['search'] ?? '';
$type_filter = $_GET['type_filter'] ?? '';
$status_filter = $_GET['status_filter'] ?? '';
$search_type = $_GET['search_type'] ?? 'all'; // all, assets, customers, assignments, suppliers

// متغیرهای جستجو
$assets = [];
$customers = [];
$assignments = [];
$suppliers = [];
$tools = [];
$tools_issued = [];
$tools_returned = [];
$tools_overdue = [];
$search_results = [];

// ایجاد جداول ابزارها
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
    
    // دریافت آمار ابزارها
    $tools_stmt = $pdo->query("SELECT * FROM tools WHERE status = 'موجود'");
    $tools = $tools_stmt->fetchAll();
    
    $tools_issued_stmt = $pdo->query("SELECT * FROM tool_issues WHERE status = 'تحویل_داده_شده'");
    $tools_issued = $tools_issued_stmt->fetchAll();
    
    $tools_returned_stmt = $pdo->query("SELECT * FROM tool_issues WHERE status = 'برگشت_داده_شده'");
    $tools_returned = $tools_returned_stmt->fetchAll();
    
    $tools_overdue_stmt = $pdo->query("SELECT * FROM tool_issues WHERE status = 'تاخیر_در_برگشت' OR (status = 'تحویل_داده_شده' AND expected_return_date < CURDATE())");
    $tools_overdue = $tools_overdue_stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Error creating tools tables: " . $e->getMessage());
    $tools = [];
    $tools_issued = [];
    $tools_returned = [];
    $tools_overdue = [];
}

// جستجو در دارایی‌ها
if ($search_type === 'all' || $search_type === 'assets') {
    $query = "SELECT a.*, at.display_name as type_display_name, at.name as type_name
              FROM assets a 
              JOIN asset_types at ON a.type_id = at.id 
              WHERE 1=1";
    $params = [];
    if (!empty($search)) {
        $query .= " AND (a.name LIKE ? OR a.serial_number LIKE ? OR a.model LIKE ? OR a.brand LIKE ? OR a.device_identifier LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term; $params[] = $search_term; $params[] = $search_term; $params[] = $search_term; $params[] = $search_term;
    }
    if (!empty($type_filter)) {
        $query .= " AND a.type_id = ?";
        $params[] = $type_filter;
    }
    if (!empty($status_filter)) {
        $query .= " AND a.status = ?";
        $params[] = $status_filter;
    }
    $query .= " ORDER BY a.created_at DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $assets = $stmt->fetchAll();
} else {
    // اگر نوع جستجو assets نیست، دارایی‌ها را خالی نگه دار
    $assets = [];
}

// جستجو در مشتریان
if ($search_type === 'all' || $search_type === 'customers') {
    $customer_query = "SELECT * FROM customers WHERE 1=1";
    $customer_params = [];
    if (!empty($search)) {
        $customer_query .= " AND (full_name LIKE ? OR company LIKE ? OR phone LIKE ? OR email LIKE ? OR company_email LIKE ?)";
        $search_term = "%$search%";
        $customer_params[] = $search_term; $customer_params[] = $search_term; $customer_params[] = $search_term; $customer_params[] = $search_term; $customer_params[] = $search_term;
    }
    $customer_query .= " ORDER BY created_at DESC LIMIT 10";
    $stmt = $pdo->prepare($customer_query);
    $stmt->execute($customer_params);
    $customers = $stmt->fetchAll();
}

// جستجو در انتساب‌ها
if ($search_type === 'all' || $search_type === 'assignments') {
    $assignments = [];
    try {
        // بررسی وجود جدول assignments
        $table_exists = $pdo->query("SHOW TABLES LIKE 'assignments'")->fetch();
        if ($table_exists) {
            $assignment_query = "SELECT a.*, c.full_name as customer_name, c.company as customer_company, 
                                ast.name as asset_name, ast.serial_number as asset_serial
                                FROM assignments a 
                                LEFT JOIN customers c ON a.customer_id = c.id 
                                LEFT JOIN assets ast ON a.asset_id = ast.id 
                                WHERE 1=1";
            $assignment_params = [];
            if (!empty($search)) {
                $assignment_query .= " AND (a.notes LIKE ? OR c.full_name LIKE ? OR c.company LIKE ? OR ast.name LIKE ? OR ast.serial_number LIKE ?)";
                $search_term = "%$search%";
                $assignment_params[] = $search_term; $assignment_params[] = $search_term; $assignment_params[] = $search_term; $assignment_params[] = $search_term; $assignment_params[] = $search_term;
            }
            $assignment_query .= " ORDER BY a.created_at DESC LIMIT 10";
            $stmt = $pdo->prepare($assignment_query);
            $stmt->execute($assignment_params);
            $assignments = $stmt->fetchAll();
        }
    } catch (Exception $e) {
        $assignments = [];
        // فقط در صورت وجود جدول خطا را لاگ کن
        if (isset($table_exists) && $table_exists) {
            error_log("Error in assignments search: " . $e->getMessage());
        }
    }
}

// جستجو در تامین‌کنندگان
if ($search_type === 'all' || $search_type === 'suppliers') {
    try {
        // بررسی وجود جدول suppliers
        $table_exists = $pdo->query("SHOW TABLES LIKE 'suppliers'")->fetch();
        if ($table_exists) {
            $supplier_query = "SELECT * FROM suppliers WHERE 1=1";
            $supplier_params = [];
            if (!empty($search)) {
                $supplier_query .= " AND (company_name LIKE ? OR contact_person LIKE ? OR supplier_code LIKE ? OR business_category LIKE ? OR email LIKE ?)";
                $search_term = "%$search%";
                $supplier_params[] = $search_term; $supplier_params[] = $search_term; $supplier_params[] = $search_term; $supplier_params[] = $search_term; $supplier_params[] = $search_term;
            }
            $supplier_query .= " ORDER BY created_at DESC LIMIT 10";
            $stmt = $pdo->prepare($supplier_query);
            $stmt->execute($supplier_params);
            $suppliers = $stmt->fetchAll();
        } else {
            $suppliers = [];
        }
    } catch (Exception $e) {
        $suppliers = [];
        error_log("Error in suppliers search: " . $e->getMessage());
    }
}

// ترکیب نتایج جستجو
if ($search_type === 'all' && !empty($search)) {
    $search_results = [
        'assets' => $assets,
        'customers' => $customers,
        'assignments' => $assignments,
        'suppliers' => $suppliers
    ];
}

$total_assets = $pdo->query("SELECT COUNT(*) as total FROM assets")->fetch()['total'];
$filtered_count = count($assets);
?>