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

        if (preg_match('/^\d{4}\/\d{1,2}\/\d{1,2}$/', $purchase_date_input)) {
            // اگر کاربر تاریخ جلالی فرستاده بود، آن را به میلادی تبدیل کن
            list($jy, $jm, $jd) = explode('/', $purchase_date_input);
            $g = jalali_to_gregorian((int)$jy, (int)$jm, (int)$jd);
            $purchase_date = sprintf('%04d-%02d-%02d', $g[0], $g[1], $g[2]);
        } else {
            // فرض کن یا میلادی است یا رشته خالی
            $purchase_date = $purchase_date_input;
        }

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
        if (preg_match('/^\d{4}\/\d{1,2}\/\d{1,2}$/', $workshop_entry_date_input)) {
            list($jy,$jm,$jd) = explode('/', $workshop_entry_date_input);
            $g = jalali_to_gregorian((int)$jy,(int)$jm,(int)$jd);
            $workshop_entry_date = sprintf('%04d-%02d-%02d', $g[0], $g[1], $g[2]);
        } else {
            $workshop_entry_date = $workshop_entry_date_input;
        }

        $workshop_exit_date_input = sanitizeInput($_POST['workshop_exit_date'] ?? '');
        if (preg_match('/^\d{4}\/\d{1,2}\/\d{1,2}$/', $workshop_exit_date_input)) {
            list($jy,$jm,$jd) = explode('/', $workshop_exit_date_input);
            $g = jalali_to_gregorian((int)$jy,(int)$jm,(int)$jd);
            $workshop_exit_date = sprintf('%04d-%02d-%02d', $g[0], $g[1], $g[2]);
        } else {
            $workshop_exit_date = $workshop_exit_date_input;
        }

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

$search = $_GET['search'] ?? '';
$type_filter = $_GET['type_filter'] ?? '';
$status_filter = $_GET['status_filter'] ?? '';

$query = "SELECT a.*, at.display_name as type_display_name, at.name as type_name
          FROM assets a 
          JOIN asset_types at ON a.type_id = at.id 
          WHERE 1=1";
$params = [];
if (!empty($search)) {
    $query .= " AND (a.name LIKE ? OR a.serial_number LIKE ? OR a.model LIKE ? OR a.brand LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term; $params[] = $search_term; $params[] = $search_term; $params[] = $search_term;
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

$total_assets = $pdo->query("SELECT COUNT(*) as total FROM assets")->fetch()['total'];
$filtered_count = count($assets);
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت دارایی‌ها - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <style>
        html, body { font-family: Vazirmatn, Tahoma, Arial, sans-serif; }
        .form-select, .form-control, .form-label, .btn, .card, option { font-family: inherit; }
        .form-select, .form-control { direction: rtl; text-align: right; } option { direction: rtl; text-align: right; }
        .card { border: none; border-radius: 10px; box-shadow: 0 0 15px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .card-header { border-radius: 10px 10px 0 0 !important; font-weight: 600; }
        .search-box { background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .image-preview { max-width: 200px; max-height: 200px; margin-top: 10px; display: none; border-radius: 5px; border: 1px solid #ddd; }
        .dynamic-field { display: none; }
        .badge-status { font-size: 0.9rem; padding: 0.5em 0.8em; }
        .table th { font-weight: 600; background-color: #f8f9fa; }
        .action-buttons .btn { margin-left: 5px; }
        .filter-active { background-color: #e9ecef; border-radius: 5px; padding: 5px 10px; font-weight: 600; }
        #add-asset-form { display: none; }
        .step { display: none; }
        .step.active { display: block; }
        .preview-item { margin-bottom: 15px; padding: 10px; border: 1px solid #eee; border-radius: 5px; }
        .preview-label { font-weight: bold; color: #555; }
        .supply-method-fields { display: none; }

        /* Embed containers */
        .embed-frame{width:100%;height:1200px;border:0;border-radius:10px;background:#fff}
        .embed-container{background:#f8f9fa;border-radius:12px;border:1px solid #e5e7eb}
    
        /* ===== Step navigation ===== */
        .form-steps { display:flex; gap:.6rem; align-items:center; flex-wrap:wrap; margin-bottom:.75rem; }
        .form-step-item { min-width:74px; border-radius:12px; padding:.45rem .6rem; background:#f6f8fb; cursor:pointer; border:1px solid #eef2f6; text-align:center; transition:all .18s ease-in-out; direction:rtl; }
        .form-step-item i { display:block; font-size:1.05rem; margin-bottom:.18rem; }
        .form-step-item .small { font-size:.74rem; opacity:.9; }
        .form-step-item.active { background:linear-gradient(180deg,#0d6efd,#0b5ed7); color:#fff; box-shadow:0 6px 18px rgba(13,110,253,0.12); transform:translateY(-1px); border-color:rgba(11,93,215,0.9); }
        .form-step-item.completed { background:#e7f1ff; border-color:#cfe3ff; color:#0566c9; }
        .form-step-item .fa-check { font-weight:700; }
        @media (max-width:576px){ .form-step-item { min-width:60px; padding:.35rem .45rem; } }

        /* Generator identifier styles */
        .identifier-wrapper .form-control {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono","Courier New", monospace;
            letter-spacing: .08em;
        }
        .identifier-generated {
            background: linear-gradient(90deg, rgba(13,110,253,.08), rgba(13,110,253,.2));
            border-color: #0d6efd !important;
            color: #0d6efd;
            font-weight: 700;
        }
        .identifier-missing {
            background: #f8f9fa;
            color: #6c757d;
            border-style: dashed;
        }
        .flash { animation: flash 480ms ease-in-out; }
        @keyframes flash {
            0% { box-shadow: 0 0 0 0 rgba(13,110,253,.6); }
            100% { box-shadow: 0 0 0 12px rgba(13,110,253,0); }
        }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-server"></i> مدیریت دارایی‌ها</h2>
                <div>
                    <span class="filter-active"><i class="fas fa-filter"></i> <?php echo $filtered_count ?> از <?php echo $total_assets ?> مورد</span>
                </div>
            </div>

            <!-- کارت‌های عملیاتی اصلی -->
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-body d-flex align-items-center justify-content-between">
                            <div>
                                <h5 class="card-title mb-1">افزودن دارایی جدید</h5>
                                <p class="text-muted mb-2">ثبت دستگاه با تمام مشخصات و تصاویر</p>
                                <a href="javascript:void(0);" onclick="showAddAssetForm()" class="btn btn-primary">شروع ثبت</a>
                            </div>
                            <div class="display-4 text-primary"><i class="fas fa-plus-circle"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-body d-flex align-items-center justify-content-between">
                            <div>
                                <h5 class="card-title mb-1">پروفایل دستگاه‌ها</h5>
                                <p class="text-muted mb-2">مدیریت سرویس و نگهداشت هر دستگاه</p>
                                <a href="profiles_list.php" class="btn btn-outline-primary">مشاهده پروفایل‌ها</a>
                            </div>
                            <div class="display-4 text-info"><i class="fas fa-id-card"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- کارت‌های جدید: مشتریان و انتساب‌ها -->
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-body d-flex align-items-center justify-content-between">
                            <div>
                                <h5 class="card-title mb-1">مدیریت مشتریان</h5>
                                <p class="text-muted mb-2">افزودن/حذف/ویرایش مشتریان با تمام امکانات فعلی</p>
                                <div class="d-flex gap-2">
                                    <a class="btn btn-outline-primary" data-bs-toggle="collapse" href="#customersEmbed" role="button" aria-expanded="false" aria-controls="customersEmbed">
                                        باز کردن داخل همین صفحه
                                    </a>
                                    <a class="btn btn-primary" href="customers.php" target="_blank">صفحه کامل</a>
                                </div>
                            </div>
                            <div class="display-4 text-primary"><i class="fas fa-users"></i></div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-body d-flex align-items-center justify-content-between">
                            <div>
                                <h5 class="card-title mb-1">مدیریت انتساب‌ها</h5>
                                <p class="text-muted mb-2">انتساب دستگاه به مشتری + جزئیات نصب و گارانتی</p>
                                <div class="d-flex gap-2">
                                    <a class="btn btn-outline-primary" data-bs-toggle="collapse" href="#assignmentsEmbed" role="button" aria-expanded="false" aria-controls="assignmentsEmbed">
                                        باز کردن داخل همین صفحه
                                    </a>
                                    <a class="btn btn-primary" href="assignments.php" target="_blank">صفحه کامل</a>
                                </div>
                            </div>
                            <div class="display-4 text-info"><i class="fas fa-link"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- محفظه‌های Embed -->
            <div class="collapse mb-4" id="customersEmbed">
                <div class="embed-container p-2">
                    <iframe class="embed-frame" data-src="customers.php?embed=1" title="مدیریت مشتریان (Embed)" loading="lazy" referrerpolicy="no-referrer"></iframe>
                </div>
            </div>

            <div class="collapse mb-4" id="assignmentsEmbed">
                <div class="embed-container p-2">
                    <iframe class="embed-frame" data-src="assignments.php?embed=1" title="مدیریت انتساب‌ها (Embed)" loading="lazy" referrerpolicy="no-referrer"></iframe>
                </div>
            </div>

            <!-- جستجو و فیلتر -->
            <div class="card search-box">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <input type="text" name="search" class="form-control" placeholder="جستجو بر اساس نام، سریال، مدل یا برند..." value="<?php echo htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-md-2">
                            <select name="type_filter" class="form-select">
                                <option value="">همه انواع</option>
                                <?php foreach ($asset_types as $type): ?>
                                    <option value="<?php echo $type['id'] ?>" <?php echo $type_filter == $type['id'] ? 'selected' : '' ?>>
                                        <?php echo $type['display_name'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="status_filter" class="form-select">
                                <option value="">همه وضعیت‌ها</option>
                                <option value="فعال" <?php echo $status_filter == 'فعال' ? 'selected' : '' ?>>فعال</option>
                                <option value="غیرفعال" <?php echo $status_filter == 'غیرفعال' ? 'selected' : '' ?>>غیرفعال</option>
                                <option value="در حال تعمیر" <?php echo $status_filter == 'در حال تعمیر' ? 'selected' : '' ?>>در حال تعمیر</option>
                                <option value="آماده بهره‌برداری" <?php echo $status_filter == 'آماده بهره‌برداری' ? 'selected' : '' ?>>آماده بهره‌برداری</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter"></i> اعمال فیلتر
                            </button>
                        </div>
                        <div class="col-md-2">
                            <a href="assets.php" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-times"></i> حذف فیلتر
                            </a>
                        </div>
                    </form>
                </div>
            </div>

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
            <!-- فرم افزودن دارایی -->
            <div class="card" id="add-asset-form">
                <div class="card-header bg-primary text-white"><h5 class="mb-0"><i class="fas fa-plus-circle"></i> افزودن دارایی جدید</h5></div>
                <div class="card-body">
                    <form method="POST" id="assetForm" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

                        <!-- Step navigation -->
                        <div class="form-steps" id="formSteps">
                            <div class="form-step-item" data-step="1" title="مرحله ۱ — انتخاب نوع دارایی">
                                <i class="fas fa-list"></i>
                                <div class="small">نوع دارایی</div>
                            </div>
                            <div class="form-step-item" data-step="2" title="مرحله ۲ — اطلاعات">
                                <i class="fas fa-info-circle"></i>
                                <div class="small">اطلاعات</div>
                            </div>
                            <div class="form-step-item" data-step="3" title="مرحله ۳ — نحوه تأمین">
                                <i class="fas fa-truck"></i>
                                <div class="small">تأمین</div>
                            </div>
                            <div class="form-step-item" data-step="4" title="مرحله ۴ — پیش‌نمایش">
                                <i class="fas fa-eye"></i>
                                <div class="small">پیش‌نمایش</div>
                            </div>
                        </div>

                        <div class="step active" id="step1">
                            <h4 class="mb-4 text-primary">انتخاب نوع دارایی</h4>
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="type_id" class="form-label">نوع دارایی *</label>
                                        <select class="form-select" id="type_id" name="type_id" required onchange="showStep2()">
                                            <option value="">-- انتخاب کنید --</option>
                                            <?php foreach ($asset_types as $type): ?>
                                                <option value="<?php echo $type['id'] ?>"><?php echo $type['display_name'] ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-primary" onclick="nextStep(2)">مرحله بعد <i class="fas fa-arrow-left"></i></button>
                            </div>
                        </div>

                        <div class="step" id="step2">
                            <h4 class="mb-4 text-primary" id="step2-title">اطلاعات دارایی</h4>
                            
                            <!-- فیلدهای ژنراتور -->
                            <div id="generator_fields" class="dynamic-field">
                                <h5 class="mb-3 text-secondary">مشخصات ژنراتور</h5>
                                
                                <!-- ردیف 1 -->
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">نام دستگاه *</label>
                                            <select class="form-select gen-name" id="gen_name" name="name" required>
                                                <option value="">-- انتخاب کنید --</option>
                                                <option value="Cummins">Cummins</option>
                                                <option value="Volvo">Volvo</option>
                                                <option value="Perkins">Perkins</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">شماره سریال دستگاه *</label>
                                            <input type="text" class="form-control gen-dev-serial" id="gen_serial_number" name="serial_number">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">تاریخ خرید</label>
                                            <input type="text" class="form-control persian-date" id="gen_purchase_date" name="purchase_date" autocomplete="off">
                                        </div>
                                    </div>
                                </div>

                                <!-- ردیف 2 -->
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label d-flex align-items-center justify-content-between" style="gap:.5rem;">
                                                <span>وضعیت *</span>
                                                <span class="d-flex align-items-center" style="gap:.35rem;">
                                                    <i class="fas fa-info-circle text-muted" title="انتخاب وضعیت دستگاه — برای مثال: فعال، در حال تعمیر، غیرفعال" style="cursor:help"></i>
                                                </span>
                                            </label>
                                            <select class="form-select" id="gen_status" name="status" required>
                                                <option value="">-- انتخاب کنید --</option>
                                                <option value="ترخیص شده از گمرک">ترخیص شده از گمرک</option>
                                                <option value="انبار نظر آباد">انبار نظر آباد</option>
                                                <option value="در حال تعمیر">در حال تعمیر</option>
                                                <option value="آماده بهره‌برداری">آماده بهره‌برداری</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">برند</label>
                                            <input type="text" class="form-control" id="gen_brand" name="brand">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">مدل دستگاه *</label>
                                            <input type="text" class="form-control gen-device-model" id="gen_device_model" name="device_model" required>
                                        </div>
                                    </div>
                                </div>

                                <!-- ردیف 3 -->
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">ظرفیت توان (کیلووات)</label>
                                            <input type="text" class="form-control" id="gen_power_capacity" name="power_capacity">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">مدل موتور</label>
                                            <input type="text" class="form-control" id="gen_engine_model" name="engine_model">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">سریال موتور</label>
                                            <input type="text" class="form-control" id="gen_engine_serial" name="engine_serial">
                                        </div>
                                    </div>
                                </div>

                                <!-- ردیف 4 -->
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">مدل آلترناتور</label>
                                            <input type="text" class="form-control" id="gen_alternator_model" name="alternator_model">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">سریال آلترناتور *</label>
                                            <input type="text" class="form-control gen-alt-serial" id="gen_alternator_serial" name="alternator_serial" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">سریال دستگاه</label>
                                            <input type="text" class="form-control" id="gen_device_serial" name="device_serial">
                                        </div>
                                    </div>
                                </div>

                                <!-- شماره شناسه دستگاه -->
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="mb-3 identifier-wrapper">
                                            <label class="form-label d-flex align-items-center gap-2">
                                                شماره شناسه دستگاه
                                                <span class="badge bg-secondary gen-identifier-status" id="identifier_status">در انتظار</span>
                                            </label>

                                            <div class="input-group">
                                                <input type="text" class="form-control identifier-missing gen-device-identifier"
                                                       id="device_identifier" name="device_identifier"
                                                       readonly
                                                       placeholder="— پس از تکمیل فیلدها ساخته می‌شود —" aria-describedby="identifier_hint">
                                                <button class="btn btn-outline-secondary gen-copy-btn" type="button" id="copy_identifier" disabled title="کپی">
                                                    کپی
                                                </button>
                                            </div>

                                            <div class="form-text gen-identifier-hint" id="identifier_hint">
                                                الگو: حرف اول نام دستگاه + ۴ کاراکتر اول سریال آلترناتور + ۴ کاراکتر آخر سریال دستگاه
                                            </div>
                                            <div class="invalid-feedback gen-identifier-error" id="identifier_error" style="display:none">
                                                برای ساخت شناسه، فیلدهای لازم کامل نشده‌اند.
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- ردیف 5 -->
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">مدل کنترل پنل</label>
                                            <input type="text" class="form-control" id="gen_control_panel_model" name="control_panel_model">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">مدل بریکر</label>
                                            <input type="text" class="form-control" id="gen_breaker_model" name="breaker_model">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">باتری</label>
                                            <input type="text" class="form-control" id="gen_battery" name="battery">
                                        </div>
                                    </div>
                                </div>

                                <!-- ردیف 6 -->
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">مشخصات تانک سوخت</label>
                                            <textarea class="form-control" id="gen_fuel_tank_specs" name="fuel_tank_specs" rows="2"></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">سایر اقلام مولد</label>
                                            <textarea class="form-control" id="gen_other_items" name="other_items" rows="2"></textarea>
                                        </div>
                                    </div>
                                </div>

                                <!-- ردیف 7 -->
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">حجم روغن</label>
                                            <input type="text" class="form-control" id="gen_oil_capacity" name="oil_capacity">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">حجم آب رادیاتور</label>
                                            <input type="text" class="form-control" id="gen_radiator_capacity" name="radiator_capacity">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">ضدیخ</label>
                                            <input type="text" class="form-control" id="gen_antifreeze" name="antifreeze">
                                        </div>
                                    </div>
                                </div>

                                <!-- فیلتر روغن -->
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">پارت نامبر فیلتر روغن</label>
                                            <input type="text" class="form-control" id="gen_oil_filter_part" name="oil_filter_part">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">تعداد</label>
                                            <select class="form-select quantity-select" id="gen_oil_filter_quantity" name="oil_filter_quantity">
                                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                                    <option value="<?php echo $i ?>" <?php echo $i == 1 ? 'selected' : '' ?>><?php echo $i ?></option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">عکس فیلتر روغن</label>
                                            <input type="file" class="form-control" id="gen_oil_filter" name="oil_filter" accept="image/*" onchange="previewImage(this, 'gen_oil_filter_preview')">
                                            <img id="gen_oil_filter_preview" class="image-preview" src="#" alt="پیش‌نمایش">
                                        </div>
                                    </div>
                                </div>

                                <!-- فیلتر سوخت -->
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">پارت نامبر فیلتر سوخت</label>
                                            <input type="text" class="form-control" id="gen_fuel_filter_part" name="fuel_filter_part">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">تعداد</label>
                                            <select class="form-select quantity-select" id="gen_fuel_filter_quantity" name="fuel_filter_quantity">
                                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                                    <option value="<?php echo $i ?>" <?php echo $i == 1 ? 'selected' : '' ?>><?php echo $i ?></option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">عکس فیلتر سوخت</label>
                                            <input type="file" class="form-control" id="gen_fuel_filter" name="fuel_filter" accept="image/*" onchange="previewImage(this, 'gen_fuel_filter_preview')">
                                            <img id="gen_fuel_filter_preview" class="image-preview" src="#" alt="پیش‌نمایش">
                                        </div>
                                    </div>
                                </div>

                                <!-- فیلتر سوخت آبیگیر -->
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">پارت نامبر فیلتر سوخت آبیگیر</label>
                                            <input type="text" class="form-control" id="gen_water_fuel_filter_part" name="water_fuel_filter_part">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">تعداد</label>
                                            <select class="form-select quantity-select" id="gen_water_fuel_filter_quantity" name="water_fuel_filter_quantity">
                                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                                    <option value="<?php echo $i ?>" <?php echo $i == 1 ? 'selected' : '' ?>><?php echo $i ?></option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">عکس فیلتر سوخت آبیگیر</label>
                                            <input type="file" class="form-control" id="gen_water_fuel_filter" name="water_fuel_filter" accept="image/*" onchange="previewImage(this, 'gen_water_fuel_filter_preview')">
                                            <img id="gen_water_fuel_filter_preview" class="image-preview" src="#" alt="پیش‌نمایش">
                                        </div>
                                    </div>
                                </div>

                                <!-- فیلتر هوا -->
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">پارت نامبر فیلتر هوا</label>
                                            <input type="text" class="form-control" id="gen_air_filter_part" name="air_filter_part">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">تعداد</label>
                                            <select class="form-select quantity-select" id="gen_air_filter_quantity" name="air_filter_quantity">
                                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                                    <option value="<?php echo $i ?>" <?php echo $i == 1 ? 'selected' : '' ?>><?php echo $i ?></option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">عکس فیلتر هوا</label>
                                            <input type="file" class="form-control" id="gen_air_filter" name="air_filter" accept="image/*" onchange="previewImage(this, 'gen_air_filter_preview')">
                                            <img id="gen_air_filter_preview" class="image-preview" src="#" alt="پیش‌نمایش">
                                        </div>
                                    </div>
                                </div>

                                <!-- فیلتر آب -->
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">پارت نامبر فیلتر آب</label>
                                            <input type="text" class="form-control" id="gen_water_filter_part" name="water_filter_part">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">تعداد</label>
                                            <select class="form-select quantity-select" id="gen_water_filter_quantity" name="water_filter_quantity">
                                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                                    <option value="<?php echo $i ?>" <?php echo $i == 1 ? 'selected' : '' ?>><?php echo $i ?></option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">عکس فیلتر آب</label>
                                            <input type="file" class="form-control" id="gen_water_filter" name="water_filter" accept="image/*" onchange="previewImage(this, 'gen_water_filter_preview')">
                                            <img id="gen_water_filter_preview" class="image-preview" src="#" alt="پیش‌نمایش">
                                        </div>
                                    </div>
                                </div>

                                <!-- عکس دستگاه -->
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">عکس دستگاه</label>
                                            <input type="file" class="form-control" id="gen_device_image" name="device_image" accept="image/*" onchange="previewImage(this, 'gen_device_image_preview')">
                                            <img id="gen_device_image_preview" class="image-preview" src="#" alt="پیش‌نمایش">
                                        </div>
                                    </div>
                                </div>

                                <!-- تاریخ‌های کارگاه -->
                                <div class="row mb-3">
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">تاریخ ورود به کارگاه</label>
                                            <input type="date" class="form-control" id="gen_workshop_entry_date" name="workshop_entry_date">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">تاریخ خروج از کارگاه</label>
                                            <input type="date" class="form-control" id="gen_workshop_exit_date" name="workshop_exit_date">
                                        </div>
                                    </div>
                                </div>

                                <!-- توضیحات -->
                                <div class="mb-3">
                                    <label class="form-label">توضیحات</label>
                                    <textarea class="form-control" id="gen_description" name="description" rows="4"></textarea>
                                </div>
                            </div>
                            <!-- فیلدهای موتور برق -->
                            <div id="motor_fields" class="dynamic-field">
                                <h5 class="mb-3 text-secondary">مشخصات موتور برق</h5>
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">نام موتور برق *</label>
                                            <select class="form-select" id="motor_name" name="name" required>
                                                <option value="">-- انتخاب کنید --</option>
                                                <option value="Cummins">Cummins</option>
                                                <option value="Volvo">Volvo</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">نوع موتور *</label>
                                            <select class="form-select" id="motor_engine_type" name="engine_type" required>
                                                <option value="">-- انتخاب کنید --</option>
                                                <option value="P4500">P4500</option>
                                                <option value="P5000e">P5000e</option>
                                                <option value="P2200">P2200</option>
                                                <option value="P2600">P2600</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">سریال موتور *</label>
                                            <input type="text" class="form-control" id="motor_serial_number" name="serial_number">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- ردیف 2 -->
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">شناسه دستگاه *</label>
                                            <input type="text" class="form-control motor-device-identifier" id="motor_device_identifier" name="device_identifier" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">تاریخ خرید</label>
                                            <input type="date" class="form-control" id="motor_purchase_date" name="purchase_date">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">وضعیت *</label>
                                            <select class="form-select" id="motor_status" name="status" required>
                                                <option value="">-- انتخاب کنید --</option>
                                                <option value="فعال">فعال</option>
                                                <option value="غیرفعال">غیرفعال</option>
                                                <option value="در حال تعمیر">در حال تعمیر</option>
                                                <option value="آماده بهره‌برداری">آماده بهره‌برداری</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- فیلدهای اقلام مصرفی -->
                            <div id="consumable_fields" class="dynamic-field">
                                <h5 class="mb-3 text-secondary">مشخصات اقلام مصرفی</h5>
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">نام کالا *</label>
                                            <input type="text" class="form-control" id="consumable_name" name="name" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">تاریخ ثبت</label>
                                            <input type="date" class="form-control" id="consumable_purchase_date" name="purchase_date">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">شناسه دستگاه *</label>
                                            <input type="text" class="form-control consumable-device-identifier" id="consumable_device_identifier" name="device_identifier" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">وضعیت *</label>
                                            <select class="form-select" id="consumable_status" name="status" required>
                                                <option value="">-- انتخاب کنید --</option>
                                                <option value="فعال">فعال</option>
                                                <option value="غیرفعال">غیرفعال</option>
                                                <option value="در حال تعمیر">در حال تعمیر</option>
                                                <option value="آماده بهره‌برداری">آماده بهره‌برداری</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">نوع کالای مصرفی *</label>
                                            <input type="text" class="form-control" id="consumable_type" name="consumable_type" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">پارت نامبر</label>
                                            <input type="text" class="form-control" id="consumable_part" name="oil_filter_part">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- فیلدهای قطعات -->
                            <div id="parts_fields" class="dynamic-field">
                                <h5 class="mb-3 text-secondary">مشخصات قطعات</h5>
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">نام قطعه *</label>
                                            <input type="text" class="form-control" id="parts_name" name="name" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">شماره سریال</label>
                                            <input type="text" class="form-control" id="parts_serial_number" name="serial_number">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">شناسه دستگاه *</label>
                                            <input type="text" class="form-control parts-device-identifier" id="parts_device_identifier" name="device_identifier" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">تاریخ ثبت</label>
                                            <input type="date" class="form-control" id="parts_purchase_date" name="purchase_date">
                                        </div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">وضعیت *</label>
                                            <select class="form-select" id="parts_status" name="status" required>
                                                <option value="">-- انتخاب کنید --</option>
                                                <option value="فعال">فعال</option>
                                                <option value="غیرفعال">غیرفعال</option>
                                                <option value="در حال تعمیر">در حال تعمیر</option>
                                                <option value="آماده بهره‌برداری">آماده بهره‌برداری</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <div class="mb-3">
                                            <label class="form-label">توضیحات</label>
                                            <textarea class="form-control" id="parts_description" name="description" rows="3"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between">
                                <button type="button" class="btn btn-secondary" onclick="prevStep(1)"><i class="fas fa-arrow-right"></i> مرحله قبل</button>
                                <button type="button" class="btn btn-primary" onclick="nextStepFrom2()">مرحله بعد <i class="fas fa-arrow-left"></i></button>
                            </div>
                        </div>

                        <div class="step" id="step3">
                            <h4 class="mb-4 text-primary">نحوه تامین</h4>
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="supply_method" class="form-label">نحوه تامین *</label>
                                        <select class="form-select" id="supply_method" name="supply_method" onchange="toggleSupplyFields()">
                                            <option value="">-- انتخاب کنید --</option>
                                            <option value="انبار">انبار</option>
                                            <option value="third_party">Third Party</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div id="warehouse_fields" class="supply-method-fields">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">لوکیشن *</label>
                                            <input type="text" class="form-control" id="location" name="location">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">تعداد *</label>
                                            <input type="number" class="form-control" id="quantity" name="quantity" min="1">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div id="third_party_fields" class="supply-method-fields">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">نام تامین کننده *</label>
                                            <input type="text" class="form-control" id="supplier_name" name="supplier_name">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">شماره تماس تامین کننده *</label>
                                            <input type="text" class="form-control" id="supplier_contact" name="supplier_contact">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <button type="button" class="btn btn-secondary" onclick="prevStep(2)"><i class="fas fa-arrow-right"></i> مرحله قبل</button>
                                <button type="button" class="btn btn-primary" onclick="nextStep(4)">مرحله بعد <i class="fas fa-arrow-left"></i></button>
                            </div>
                        </div>

                        <div class="step" id="step4">
                            <h4 class="mb-4 text-primary">پیش‌نمایش و تأیید اطلاعات</h4>
                            <div class="alert alert-info"><i class="fas fa-info-circle"></i> لطفاً اطلاعات زیر را بررسی کرده و در صورت صحیح بودن، ثبت نهایی را انجام دهید.</div>
                            <div class="preview-container" id="previewContainer"></div>
                            <div class="d-flex justify-content-between mt-4">
                                <button type="button" class="btn btn-secondary" onclick="prevStepFrom4()"><i class="fas fa-arrow-right"></i> مرحله قبل</button>
                                <div>
                                    <button type="button" class="btn btn-warning" onclick="editForm()"><i class="fas fa-edit"></i> ویرایش اطلاعات</button>
                                    <button type="submit" name="add_asset" class="btn btn-success"><i class="fas fa-save"></i> ثبت نهایی</button>
                                </div>
                            </div>
                        </div>

                    </form>
                </div>
            </div>

            <!-- لیست دارایی‌های ثبت شده -->
            <div class="card mt-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-list"></i> لیست دارایی‌های ثبت شده</h5>
                </div>
                <div class="card-body">
                    <?php if (count($assets) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                <tr>
                                    <th>نام دستگاه</th>
                                    <th>نوع</th>
                                    <th>سریال/شناسه</th>
                                    <th>برند/مدل</th>
                                    <th>مشخصات</th>
                                    <th>وضعیت</th>
                                    <th>تاریخ خرید</th>
                                    <th>عملیات</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($assets as $asset): ?>
                                <tr>
                                    <td>
                                        <?php
                                        // نمایش نام مناسب بر اساس نوع دارایی
                                        $display_name = $asset['name'];
                                        if ($asset['type_name'] === 'generator' && $asset['device_identifier']) {
                                            $display_name = $asset['device_identifier'];
                                        } elseif ($asset['type_name'] === 'power_motor' && $asset['serial_number']) {
                                            $display_name = $asset['serial_number'];
                                        } elseif ($asset['type_name'] === 'consumable' && $asset['device_identifier']) {
                                            $display_name = $asset['device_identifier'];
                                        } elseif ($asset['type_name'] === 'parts' && $asset['device_identifier']) {
                                            $display_name = $asset['device_identifier'];
                                        }
                                        ?>
                                        <strong><?php echo htmlspecialchars($display_name) ?></strong>
                                        <?php if ($asset['device_identifier'] && $asset['type_name'] === 'generator'): ?>
                                            <br><small class="text-muted">نام: <?php echo htmlspecialchars($asset['name']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($asset['type_display_name']) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($asset['device_identifier']): ?>
                                            <span class="text-primary fw-bold"><?php echo htmlspecialchars($asset['device_identifier']) ?></span>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($asset['serial_number']) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($asset['brand']) ?>
                                        <?php echo $asset['model'] ? ' / ' . htmlspecialchars($asset['model']) : '' ?>
                                    </td>
                                    <td>
                                        <?php
                                        // نمایش مشخصات بر اساس نوع دارایی
                                        $specs = [];
                                        if ($asset['power_capacity']) $specs[] = 'قدرت: ' . $asset['power_capacity'];
                                        if ($asset['engine_type']) $specs[] = 'نوع موتور: ' . $asset['engine_type'];
                                        if ($asset['consumable_type']) $specs[] = 'نوع: ' . $asset['consumable_type'];
                                        if ($asset['quantity'] > 0) $specs[] = 'تعداد: ' . $asset['quantity'];
                                        if ($asset['location']) $specs[] = 'مکان: ' . $asset['location'];
                                        if ($asset['supply_method']) $specs[] = 'تأمین: ' . $asset['supply_method'];
                                        
                                        if (!empty($specs)) {
                                            echo '<small>' . implode('<br>', $specs) . '</small>';
                                        } else {
                                            echo '--';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = 'secondary';
                                        if ($asset['status'] == 'فعال') $status_class = 'success';
                                        if ($asset['status'] == 'غیرفعال') $status_class = 'danger';
                                        if ($asset['status'] == 'در حال تعمیر') $status_class = 'warning';
                                        if ($asset['status'] == 'آماده بهره‌برداری') $status_class = 'info';
                                        ?>
                                        <span class="badge bg-<?php echo $status_class ?> badge-status"><?php echo $asset['status'] ?></span>
                                    </td>
                                    <td><?php echo $asset['purchase_date'] ? jalaliDate($asset['purchase_date']) : '--' ?></td>
                                    <td class="action-buttons">
                                        <a href="profile.php?id=<?php echo $asset['id'] ?>" class="btn btn-sm btn-info" title="پروفایل دستگاه">
                                            <i class="fas fa-id-card"></i>
                                        </a>
                                        <a href="edit_asset.php?id=<?php echo $asset['id'] ?>" class="btn btn-sm btn-warning" title="ویرایش">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($_SESSION['role'] == 'ادمین'): ?>
                                        <a href="assets.php?delete_id=<?php echo $asset['id'] ?>" class="btn btn-sm btn-danger" title="حذف" onclick="return confirm('آیا از حذف این دارایی مطمئن هستید؟')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info text-center">
                            <i class="fas fa-info-circle"></i> هیچ دارایی ثبت نشده است.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>
<script>
let currentStep = 1;
let assetType = '';

// تبدیل گرگوری به جلالی (همان الگوریتم PHP اما JS)
function gregorianToJalali(gy, gm, gd) {
    var g_d_m = [0,31,59,90,120,151,181,212,243,273,304,334];
    var gy2 = (gm > 2) ? (gy + 1) : gy;
    var days = 355666 + (365 * gy) + Math.floor((gy2 + 3) / 4) - Math.floor((gy2 + 99) / 100) + Math.floor((gy2 + 399) / 400) + gd + g_d_m[gm - 1];
    var jy = -1595 + 33 * Math.floor(days / 12053);
    days = days % 12053;
    jy += 4 * Math.floor(days / 1461);
    days = days % 1461;
    if (days > 365) {
        jy += Math.floor((days - 1) / 365);
        days = (days - 1) % 365;
    }
    var jm, jd;
    if (days < 186) {
        jm = 1 + Math.floor(days / 31);
        jd = 1 + (days % 31);
    } else {
        jm = 7 + Math.floor((days - 186) / 30);
        jd = 1 + ((days - 186) % 30);
    }
    return [jy, jm, jd];
}

function formatJalaliFromIso(iso) {
    if (!iso) return iso;
    var parts = iso.split('-');
    if (parts.length < 3) return iso;
    var g = gregorianToJalali(parseInt(parts[0],10), parseInt(parts[1],10), parseInt(parts[2],10));
    return g[0] + '/' + String(g[1]).padStart(2,'0') + '/' + String(g[2]).padStart(2,'0');
}

function showAddAssetForm() {
    document.getElementById('add-asset-form').style.display = 'block';
    document.getElementById('add-asset-form').scrollIntoView({ behavior: 'smooth' });
    resetForm();
}

function hideAddAssetForm() {
    document.getElementById('add-asset-form').style.display = 'none';
    resetForm();
}

function resetForm() {
    currentStep = 1;
    document.querySelectorAll('.step').forEach(step => { step.classList.remove('active'); });
    document.getElementById('step1').classList.add('active');
    document.getElementById('assetForm').reset();
    hideAllDynamicFields();
    hideAllSupplyFields();
    updateStepNav();
}

function hideAllDynamicFields() { 
    document.querySelectorAll('.dynamic-field').forEach(field => { field.style.display = 'none'; }); 
}

function hideAllSupplyFields() { 
    document.querySelectorAll('.supply-method-fields').forEach(field => { field.style.display = 'none'; }); 
}

function nextStep(step) {
    if (!validateStep(currentStep)) return;
    
    // Get asset type from select if not set
    if (!assetType) {
        const typeSelect = document.getElementById('type_id');
        if (typeSelect && typeSelect.value) {
            assetType = typeSelect.options[typeSelect.selectedIndex].text.toLowerCase();
        }
    }
    
    // Normal step navigation - no smart skipping
    if (currentStep === 1 && step === 2) {
        // Go to step 2 for all asset types
        document.getElementById('step' + currentStep).classList.remove('active');
        document.getElementById('step2').classList.add('active');
        currentStep = 2;
        updateStepNav();
        return;
    }
    
    // Normal step navigation
    document.getElementById('step' + currentStep).classList.remove('active');
    document.getElementById('step' + step).classList.add('active');
    currentStep = step;
    
    if (currentStep === 4) {
        generatePreview();
    }
    
    updateStepNav();
}

function nextStepFrom2() {
    if (!validateStep(currentStep)) return;
    
    // Get asset type from select if not set
    if (!assetType) {
        const typeSelect = document.getElementById('type_id');
        if (typeSelect && typeSelect.value) {
            assetType = typeSelect.options[typeSelect.selectedIndex].text.toLowerCase();
        }
    }
    
    // Smart navigation from step 2
    if (assetType && (assetType.includes('ژنراتور') || assetType.includes('موتور برق'))) {
        // Skip supply step for generators and power motors - go directly to preview
        document.getElementById('step2').classList.remove('active');
        document.getElementById('step4').classList.add('active');
        currentStep = 4;
        generatePreview();
        updateStepNav();
    } else {
        // Go to supply step for consumables and parts
        document.getElementById('step2').classList.remove('active');
        document.getElementById('step3').classList.add('active');
        currentStep = 3;
        updateStepNav();
    }
}

function prevStep(step) {
    document.getElementById('step' + currentStep).classList.remove('active');
    document.getElementById('step' + step).classList.add('active');
    currentStep = step;
    updateStepNav();
}

function prevStepFrom4() {
    // Ensure assetType is set
    if (!assetType) {
        const typeSelect = document.getElementById('type_id');
        if (typeSelect && typeSelect.value) {
            assetType = typeSelect.options[typeSelect.selectedIndex].text.toLowerCase();
        }
    }
    
    // Smart navigation from step 4
    if (assetType && (assetType.includes('ژنراتور') || assetType.includes('موتور برق'))) {
        // Go back to step 2 for generators and power motors
        document.getElementById('step4').classList.remove('active');
        document.getElementById('step2').classList.add('active');
        currentStep = 2;
    } else {
        // Go back to step 3 for consumables and parts
        document.getElementById('step4').classList.remove('active');
        document.getElementById('step3').classList.add('active');
        currentStep = 3;
    }
    updateStepNav();
}

function validateStep(step) {
    let isValid = true;
    let errorMessage = '';
    
    if (step === 1) {
        const typeSelect = document.getElementById('type_id');
        if (!typeSelect.value) { 
            isValid = false; 
            errorMessage = 'لطفاً نوع دارایی را انتخاب کنید.'; 
        } else { 
            assetType = typeSelect.options[typeSelect.selectedIndex].text.toLowerCase(); 
        }
    } else if (step === 2) {
        // Ensure assetType is set
        if (!assetType) {
            const typeSelect = document.getElementById('type_id');
            if (typeSelect && typeSelect.value) {
                assetType = typeSelect.options[typeSelect.selectedIndex].text.toLowerCase();
            }
        }
        
        if (assetType && assetType.includes('ژنراتور')) {
            const name = document.getElementById('gen_name');
            const serial = document.getElementById('gen_serial_number');
            const status = document.getElementById('gen_status');
            const deviceModel = document.getElementById('gen_device_model');
            const alternatorSerial = document.getElementById('gen_alternator_serial');
            if (!name || !name.value.trim()) { isValid=false; errorMessage='لطفاً نام دستگاه را انتخاب کنید.'; }
            else if (!serial || !serial.value.trim()) { isValid=false; errorMessage='لطفاً شماره سریال دستگاه را وارد کنید.'; }
            else if (!status || !status.value) { isValid=false; errorMessage='لطفاً وضعیت را انتخاب کنید.'; }
            else if (!deviceModel || !deviceModel.value.trim()) { isValid=false; errorMessage='لطفاً مدل دستگاه را وارد کنید.'; }
            else if (!alternatorSerial || !alternatorSerial.value.trim()) { isValid=false; errorMessage='لطفاً سریال آلترناتور را وارد کنید.'; }
        } else if (assetType && assetType.includes('موتور برق')) {
            const name = document.getElementById('motor_name');
            const serial = document.getElementById('motor_serial_number');
            const status = document.getElementById('motor_status');
            const engineType = document.getElementById('motor_engine_type');
            if (!name || !name.value.trim()) { isValid=false; errorMessage='لطفاً نام موتور برق را انتخاب کنید.'; }
            else if (!serial || !serial.value.trim()) { isValid=false; errorMessage='لطفاً شماره سریال موتور را وارد کنید.'; }
            else if (!status || !status.value) { isValid=false; errorMessage='لطفاً وضعیت را انتخاب کنید.'; }
            else if (!engineType || !engineType.value) { isValid=false; errorMessage='لطفاً نوع موتور را انتخاب کنید.'; }
        } else if (assetType && assetType.includes('مصرفی')) {
            const name = document.getElementById('consumable_name');
            const status = document.getElementById('consumable_status');
            const type = document.getElementById('consumable_type');
            if (!name || !name.value.trim()) { isValid=false; errorMessage='لطفاً نام کالا را وارد کنید.'; }
            else if (!status || !status.value) { isValid=false; errorMessage='لطفاً وضعیت را انتخاب کنید.'; }
            else if (!type || !type.value.trim()) { isValid=false; errorMessage='لطفاً نوع کالای مصرفی را وارد کنید.'; }
        } else if (assetType && assetType.includes('قطعات')) {
            const name = document.getElementById('parts_name');
            const status = document.getElementById('parts_status');
            if (!name || !name.value.trim()) { isValid=false; errorMessage='لطفاً نام قطعه را وارد کنید.'; }
            else if (!status || !status.value) { isValid=false; errorMessage='لطفاً وضعیت را انتخاب کنید.'; }
        }
    } else if (step === 3) {
        const supplyMethod = document.getElementById('supply_method').value;
        if (!supplyMethod) { isValid=false; errorMessage='لطفاً نحوه تامین را انتخاب کنید.'; }
        else if (supplyMethod === 'انبار') {
            const location = document.getElementById('location');
            const quantity = document.getElementById('quantity');
            if (!location.value.trim()) { isValid=false; errorMessage='لطفاً لوکیشن را وارد کنید.'; }
            else if (!quantity.value || quantity.value <= 0) { isValid=false; errorMessage='لطفاً تعداد را وارد کنید.'; }
        } else if (supplyMethod === 'third_party') {
            const supplierName = document.getElementById('supplier_name');
            const supplierContact = document.getElementById('supplier_contact');
            if (!supplierName.value.trim()) { isValid=false; errorMessage='لطفاً نام تامین کننده را وارد کنید.'; }
            else if (!supplierContact.value.trim()) { isValid=false; errorMessage='لطفاً شماره تماس تامین کننده را وارد کنید.'; }
        }
    } else if (step === 4) {
        // برای مرحله 4 (پیش‌نمایش) فقط بررسی کن که همه چیز آماده است
        console.log('Validating step 4');
        console.log('Asset type:', assetType);
        isValid = true; // همیشه true برگردان
        console.log('Step 4 validation result:', isValid);
    }
    
    if (!isValid) alert(errorMessage);
    return isValid;
}

function showStep2() {
    const typeSelect = document.getElementById('type_id');
    const selectedOption = typeSelect.options[typeSelect.selectedIndex];
    const typeName = selectedOption.text.toLowerCase();
    assetType = typeName;
    document.getElementById('step2-title').textContent = `اطلاعات ${selectedOption.text}`;
    hideAllDynamicFields();
    
    if (typeName.includes('ژنراتور')) { 
        document.getElementById('generator_fields').style.display = 'block'; 
        // Initialize generator identifier
        initGeneratorIdentifier();
    } else if (typeName.includes('موتور برق')) { 
        document.getElementById('motor_fields').style.display = 'block'; 
    } else if (typeName.includes('مصرفی')) { 
        document.getElementById('consumable_fields').style.display = 'block'; 
    } else if (typeName.includes('قطعات')) { 
        document.getElementById('parts_fields').style.display = 'block'; 
    }
}

function toggleSupplyFields() {
    const supplyMethod = document.getElementById('supply_method').value;
    hideAllSupplyFields();
    if (supplyMethod === 'انبار') document.getElementById('warehouse_fields').style.display = 'block';
    else if (supplyMethod === 'third_party') document.getElementById('third_party_fields').style.display = 'block';
}

function generatePreview() {
    const previewContainer = document.getElementById('previewContainer');
    let previewHTML = '';
    
    // Ensure assetType is set
    if (!assetType) {
        const typeSelect = document.getElementById('type_id');
        if (typeSelect && typeSelect.value) {
            assetType = typeSelect.options[typeSelect.selectedIndex].text.toLowerCase();
        }
    }
    
    // اطلاعات عمومی
    let name = '', serial = '', purchaseDate = '', status = '', deviceIdentifier = '';
    
    if (assetType && assetType.includes('ژنراتور')) {
        name = document.getElementById('gen_name').value;
        serial = document.getElementById('gen_serial_number').value;
        purchaseDate = document.getElementById('gen_purchase_date').value;
        status = document.getElementById('gen_status').value;
        deviceIdentifier = document.getElementById('device_identifier').value;
    } else if (assetType && assetType.includes('موتور برق')) {
        name = document.getElementById('motor_name').value;
        serial = document.getElementById('motor_serial_number').value;
        purchaseDate = document.getElementById('motor_purchase_date').value;
        status = document.getElementById('motor_status').value;
        deviceIdentifier = serial; // Use serial as identifier for power motors
    } else if (assetType && assetType.includes('مصرفی')) {
        name = document.getElementById('consumable_name').value;
        serial = '--';
        purchaseDate = document.getElementById('consumable_purchase_date').value;
        status = document.getElementById('consumable_status').value;
        deviceIdentifier = name; // Use name as identifier for consumables
    } else if (assetType && assetType.includes('قطعات')) {
        name = document.getElementById('parts_name').value;
        serial = document.getElementById('parts_serial_number').value;
        purchaseDate = document.getElementById('parts_purchase_date').value;
        status = document.getElementById('parts_status').value;
        deviceIdentifier = serial || name; // Use serial or name as identifier for parts
    }
    
    previewHTML += `
        <div class="preview-section mb-4">
            <h5 class="text-primary">اطلاعات عمومی</h5>
            <div class="row">
                <div class="col-md-6 preview-item"><span class="preview-label">نام:</span><span>${name}</span></div>
                <div class="col-md-6 preview-item"><span class="preview-label">شماره سریال:</span><span>${serial || '--'}</span></div>
                <div class="col-md-6 preview-item"><span class="preview-label">تاریخ خرید/ثبت:</span><span>${purchaseDate ? formatJalaliFromIso(purchaseDate) : '--'}</span></div>
                <div class="col-md-6 preview-item"><span class="preview-label">وضعیت:</span><span>${status}</span></div>
                <div class="col-md-6 preview-item"><span class="preview-label">شماره شناسایی:</span><span class="text-primary fw-bold">${deviceIdentifier || '--'}</span></div>
            </div>
        </div>`;
    
    // اطلاعات خاص بر اساس نوع
    if (assetType && assetType.includes('ژنراتور')) {
        previewHTML += `
        <div class="preview-section mb-4">
            <h5 class="text-primary">مشخصات ژنراتور</h5>
            <div class="row">
                <div class="col-md-6 preview-item"><span class="preview-label">برند:</span><span>${document.getElementById('gen_brand').value || '--'}</span></div>
                <div class="col-md-6 preview-item"><span class="preview-label">مدل دستگاه:</span><span>${document.getElementById('gen_device_model').value || '--'}</span></div>
                <div class="col-md-6 preview-item"><span class="preview-label">ظرفیت توان:</span><span>${document.getElementById('gen_power_capacity').value || '--'} کیلووات</span></div>
                <div class="col-md-6 preview-item"><span class="preview-label">مدل موتور:</span><span>${document.getElementById('gen_engine_model').value || '--'}</span></div>
                <div class="col-md-6 preview-item"><span class="preview-label">سریال موتور:</span><span>${document.getElementById('gen_engine_serial').value || '--'}</span></div>
                <div class="col-md-6 preview-item"><span class="preview-label">سریال آلترناتور:</span><span>${document.getElementById('gen_alternator_serial').value || '--'}</span></div>
                <div class="col-md-6 preview-item"><span class="preview-label">شناسه دستگاه:</span><span>${document.getElementById('device_identifier').value || '--'}</span></div>
            </div>
        </div>`;
    } else if (assetType && assetType.includes('موتور برق')) {
        previewHTML += `
        <div class="preview-section mb-4">
            <h5 class="text-primary">مشخصات موتور برق</h5>
            <div class="row">
                <div class="col-md-6 preview-item"><span class="preview-label">نام موتور برق:</span><span>${name}</span></div>
                <div class="col-md-6 preview-item"><span class="preview-label">نوع موتور:</span><span>${document.getElementById('motor_engine_type').value || '--'}</span></div>
            </div>
        </div>`;
    } else if (assetType && assetType.includes('مصرفی')) {
        previewHTML += `
        <div class="preview-section mb-4">
            <h5 class="text-primary">مشخصات اقلام مصرفی</h5>
            <div class="row">
                <div class="col-md-6 preview-item"><span class="preview-label">نوع کالای مصرفی:</span><span>${document.getElementById('consumable_type').value || '--'}</span></div>
                <div class="col-md-6 preview-item"><span class="preview-label">پارت نامبر:</span><span>${document.getElementById('consumable_part').value || '--'}</span></div>
            </div>
        </div>`;
    } else if (assetType && assetType.includes('قطعات')) {
        previewHTML += `
        <div class="preview-section mb-4">
            <h5 class="text-primary">مشخصات قطعات</h5>
            <div class="row">
                <div class="col-md-12 preview-item"><span class="preview-label">توضیحات:</span><span>${document.getElementById('parts_description').value || '--'}</span></div>
            </div>
        </div>`;
    }
    
    // اطلاعات تامین
    const supplyMethod = document.getElementById('supply_method').value;
    if (supplyMethod) {
        previewHTML += `
        <div class="preview-section mb-4">
            <h5 class="text-primary">نحوه تامین</h5>
            <div class="row">
                <div class="col-md-6 preview-item"><span class="preview-label">نحوه تامین:</span><span>${supplyMethod === 'انبار' ? 'انبار' : 'Third Party'}</span></div>`;
        if (supplyMethod === 'انبار') {
            previewHTML += `
                <div class="col-md-6 preview-item"><span class="preview-label">لوکیشن:</span><span>${document.getElementById('location').value || '--'}</span></div>
                <div class="col-md-6 preview-item"><span class="preview-label">تعداد:</span><span>${document.getElementById('quantity').value || '--'}</span></div>`;
        } else if (supplyMethod === 'third_party') {
            previewHTML += `
                <div class="col-md-6 preview-item"><span class="preview-label">نام تامین کننده:</span><span>${document.getElementById('supplier_name').value || '--'}</span></div>
                <div class="col-md-6 preview-item"><span class="preview-label">شماره تماس تامین کننده:</span><span>${document.getElementById('supplier_contact').value || '--'}</span></div>`;
        }
        previewHTML += `</div></div>`;
    }
    
    previewContainer.innerHTML = previewHTML;
}

function editForm() {
    // Ensure assetType is set
    if (!assetType) {
        const typeSelect = document.getElementById('type_id');
        if (typeSelect && typeSelect.value) {
            assetType = typeSelect.options[typeSelect.selectedIndex].text.toLowerCase();
        }
    }
    
    // Always go back to step 2 for editing
    document.getElementById('step4').classList.remove('active');
    document.getElementById('step2').classList.add('active');
    currentStep = 2;
    updateStepNav();
}

function submitForm() {
    // Validate all required fields before submission
    if (!validateStep(4)) {
        return false;
    }
    
    // Show confirmation dialog
    if (confirm('آیا از ثبت نهایی اطلاعات مطمئن هستید؟')) {
        // Submit the form
        document.getElementById('assetForm').submit();
        return true;
    }
    return false;
}

// اضافه کردن event listener برای دکمه submit
document.addEventListener('DOMContentLoaded', function() {
    const submitBtn = document.querySelector('button[name="add_asset"]');
    if (submitBtn) {
        submitBtn.addEventListener('click', function(e) {
            console.log('Submit button clicked');
            
            // حذف required از فیلدهای مخفی
            const hiddenFields = document.querySelectorAll('.dynamic-field input[required], .dynamic-field select[required]');
            hiddenFields.forEach(field => {
                field.removeAttribute('required');
                console.log('Removed required from:', field.name);
            });
            
            // حذف required از فیلدهای supply که مخفی هستند
            const supplyFields = document.querySelectorAll('#supply_fields input[required], #supply_fields select[required]');
            supplyFields.forEach(field => {
                field.removeAttribute('required');
                console.log('Removed required from supply field:', field.name);
            });
            
            // تایید کاربر
            if (!confirm('آیا از ثبت نهایی اطلاعات مطمئن هستید؟')) {
                console.log('User cancelled');
                e.preventDefault();
                return false;
            }
            
            console.log('User confirmed, form will be submitted');
            
            // اجازه بده فرم submit شود
            return true;
        });
    }
    
    // اضافه کردن event listener برای پر کردن خودکار برند
    const genNameSelect = document.getElementById('gen_name');
    if (genNameSelect) {
        genNameSelect.addEventListener('change', function() {
            const brandField = document.getElementById('gen_brand');
            if (brandField && this.value) {
                brandField.value = this.value;
            }
        });
    }
});

function previewImage(input, previewId) {
    const preview = document.getElementById(previewId);
    const file = input.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) { 
            preview.src = e.target.result; 
            preview.style.display = 'block'; 
        }
        reader.readAsDataURL(file);
    } else {
        preview.style.display = 'none';
    }
}

// Generator identifier system
function initGeneratorIdentifier() {
    const nameEl = document.querySelector('.gen-name');
    const altSn = document.querySelector('.gen-alt-serial');
    const devSn = document.querySelector('.gen-dev-serial');
    const model = document.querySelector('.gen-device-model');
    const out = document.querySelector('.gen-device-identifier');
    const copyBtn = document.querySelector('.gen-copy-btn');
    const badge = document.querySelector('.gen-identifier-status');
    const hint = document.querySelector('.gen-identifier-hint');
    const error = document.querySelector('.gen-identifier-error');

    const brandPrefixMap = {
        "Cummins": "C",
        "Volvo": "V",
        "Perkins": "P"
    };

    function normalize(str) {
        if (!str) return "";
        const persianDigits = "۰۱۲۳۴۵۶۷۸۹";
        const arabicDigits = "٠١٢٣٤٥٦٧٨٩";
        return Array.from(str)
            .map(ch => {
                const pd = persianDigits.indexOf(ch);
                if (pd > -1) return String(pd);
                const ad = arabicDigits.indexOf(ch);
                if (ad > -1) return String(ad);
                return ch;
            })
            .join("")
            .replace(/[^0-9A-Za-z]/g, "");
    }

    function debounce(fn, delay = 180) {
        let t;
        return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), delay); };
    }

    function partsReady() {
        const nameOk = (nameEl?.value || "").trim().length > 0;
        const modelOk = (model?.value || "").trim().length > 0;
        const altOk = normalize(altSn?.value || "").length >= 4;
        const devOk = normalize(devSn?.value || "").length >= 4;
        return { nameOk, modelOk, altOk, devOk };
    }

    function buildIdentifier() {
        const nameVal = (nameEl?.value || "").trim();
        const mapped = brandPrefixMap[nameVal];
        const prefix = mapped || (nameVal ? nameVal.charAt(0).toUpperCase() : "");
        const altClean = normalize(altSn?.value || "");
        const devClean = normalize(devSn?.value || "");
        const part1 = altClean.substring(0, 4);
        const part2 = devClean.slice(-4);
        if (prefix && part1.length === 4 && part2.length === 4) {
            return `${prefix}-${part1}-${part2}`;
        }
        return "";
    }

    function updateUI() {
        const ready = partsReady();
        const idVal = (ready.nameOk && ready.modelOk && ready.altOk && ready.devOk) ? buildIdentifier() : "";

        if (idVal) {
            out.value = idVal;
            out.classList.remove("identifier-missing", "is-invalid");
            out.classList.add("identifier-generated", "is-valid", "flash");
            copyBtn.disabled = false;
            badge.textContent = "ساخته شد";
            badge.className = "badge bg-primary";
            hint.textContent = "شناسه‌ی منحصربه‌فرد ساخته شد و همراه فرم ذخیره می‌شود.";
            if (error) error.style.display = "none";
            setTimeout(() => out.classList.remove("flash"), 500);
        } else {
            out.value = "";
            out.classList.remove("identifier-generated", "is-valid");
            out.classList.add("identifier-missing");
            copyBtn.disabled = true;
            badge.textContent = "در انتظار";
            badge.className = "badge bg-secondary";
            const missing = [];
            const r = ready;
            if (!r.nameOk) missing.push("نام دستگاه");
            if (!r.modelOk) missing.push("مدل دستگاه");
            if (!r.altOk) missing.push("سریال آلترناتور (حداقل ۴ کاراکتر)");
            if (!r.devOk) missing.push("شماره سریال دستگاه (حداقل ۴ کاراکتر)");
            hint.textContent = missing.length ? "نیاز به تکمیل: " + missing.join("، ") : hint.textContent;
            if (error) error.style.display = missing.length ? "" : "none";
        }
    }

    const debouncedUpdate = debounce(updateUI, 180);

    // Event listeners
    nameEl?.addEventListener("change", debouncedUpdate);
    altSn?.addEventListener("input", debouncedUpdate);
    devSn?.addEventListener("input", debouncedUpdate);
    model?.addEventListener("input", debouncedUpdate);

    // Copy to clipboard
    copyBtn?.addEventListener("click", async () => {
        if (!out.value) return;
        try {
            await navigator.clipboard.writeText(out.value);
            const old = copyBtn.textContent;
            copyBtn.textContent = "کپی شد!";
            copyBtn.classList.remove("btn-outline-secondary");
            copyBtn.classList.add("btn-success");
            setTimeout(() => {
                copyBtn.textContent = old;
                copyBtn.classList.add("btn-outline-secondary");
                copyBtn.classList.remove("btn-success");
            }, 900);
        } catch (e) {
            out.select();
            document.execCommand("copy");
        }
    });

    updateUI();
}

// Step navigation functions
function updateStepNav() {
    const items = document.querySelectorAll('.form-step-item');
    if (!items || items.length === 0) return;
    
    items.forEach((el) => {
        const step = parseInt(el.getAttribute('data-step') || 0, 10);
        el.classList.remove('active', 'completed');
        
        if (step < currentStep) {
            el.classList.add('completed');
        }
        if (step === currentStep) {
            el.classList.add('active');
        }
        
        // Update icons
        const ico = el.querySelector('i');
        if (ico) {
            ico.classList.remove('fa-check');
            if (el.classList.contains('completed')) {
                ico.classList.add('fa-check');
            } else {
                // Restore default icons
                if (step === 1) { ico.classList.add('fa-list'); }
                if (step === 2) { ico.classList.add('fa-info-circle'); }
                if (step === 3) { ico.classList.add('fa-truck'); }
                if (step === 4) { ico.classList.add('fa-eye'); }
            }
        }
    });
}

// Allow clicking on step items
document.addEventListener('click', function(e) {
    const target = e.target.closest('.form-step-item');
    if (!target) return;
    
    const step = parseInt(target.getAttribute('data-step') || 0, 10);
    if (!step) return;
    
    nextStep(step);
}, true);

// Lazy-load iframes when collapse opens
document.addEventListener('DOMContentLoaded', function(){
    ['customersEmbed','assignmentsEmbed'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        
        el.addEventListener('shown.bs.collapse', function(){
            const iframe = el.querySelector('iframe[data-src]');
            if (iframe && !iframe.src) { 
                iframe.src = iframe.getAttribute('data-src'); 
            }
        });
    });
    
    // Initialize step navigation
    updateStepNav();
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
