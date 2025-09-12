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

// افزودن دارایی جدید
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_asset'])) {
    verifyCsrfToken();
    
    try {
        $pdo->beginTransaction();
        
        // دریافت داده‌های اصلی
        $name = sanitizeInput($_POST['name']);
        $type_id = (int)$_POST['type_id'];
        $serial_number = sanitizeInput($_POST['serial_number']);
        $purchase_date = sanitizeInput($_POST['purchase_date']);
        $status = sanitizeInput($_POST['status']);
        $brand = sanitizeInput($_POST['brand'] ?? '');
        $model = sanitizeInput($_POST['model'] ?? '');
        
        // دریافت نوع دارایی
        $stmt = $pdo->prepare("SELECT name FROM asset_types WHERE id = ?");
        $stmt->execute([$type_id]);
        $asset_type = $stmt->fetch();
        $asset_type_name = $asset_type['name'] ?? '';
        
        // فیلدهای خاص بر اساس نوع دارایی
        $power_capacity = sanitizeInput($_POST['power_capacity'] ?? '');
        $engine_type = sanitizeInput($_POST['engine_type'] ?? '');
        $consumable_type = sanitizeInput($_POST['consumable_type'] ?? '');
        
        // فیلدهای مخصوص ژنراتور
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
        $workshop_entry_date = sanitizeInput($_POST['workshop_entry_date'] ?? '');
        $workshop_exit_date = sanitizeInput($_POST['workshop_exit_date'] ?? '');
        $datasheet_link = sanitizeInput($_POST['datasheet_link'] ?? '');
        $engine_manual_link = sanitizeInput($_POST['engine_manual_link'] ?? '');
        $alternator_manual_link = sanitizeInput($_POST['alternator_manual_link'] ?? '');
        $control_panel_manual_link = sanitizeInput($_POST['control_panel_manual_link'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        
        // پارت نامبرها
        $oil_filter_part = sanitizeInput($_POST['oil_filter_part'] ?? '');
        $fuel_filter_part = sanitizeInput($_POST['fuel_filter_part'] ?? '');
        $water_fuel_filter_part = sanitizeInput($_POST['water_fuel_filter_part'] ?? '');
        $air_filter_part = sanitizeInput($_POST['air_filter_part'] ?? '');
        $water_filter_part = sanitizeInput($_POST['water_filter_part'] ?? '');
        
        // درج دارایی اصلی
        $stmt = $pdo->prepare("INSERT INTO assets (name, type_id, serial_number, purchase_date, status, brand, model, 
                              power_capacity, engine_type, consumable_type, engine_model, engine_serial, 
                              alternator_model, alternator_serial, device_model, device_serial, control_panel_model, 
                              breaker_model, fuel_tank_specs, battery, battery_charger, heater, oil_capacity, 
                              radiator_capacity, antifreeze, other_items, workshop_entry_date, workshop_exit_date, 
                              datasheet_link, engine_manual_link, alternator_manual_link, control_panel_manual_link, 
                              description, oil_filter_part, fuel_filter_part, water_fuel_filter_part, air_filter_part, 
                              water_filter_part) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $name, $type_id, $serial_number, $purchase_date, $status, $brand, $model,
            $power_capacity, $engine_type, $consumable_type, $engine_model, $engine_serial,
            $alternator_model, $alternator_serial, $device_model, $device_serial, $control_panel_model,
            $breaker_model, $fuel_tank_specs, $battery, $battery_charger, $heater, $oil_capacity,
            $radiator_capacity, $antifreeze, $other_items, $workshop_entry_date, $workshop_exit_date,
            $datasheet_link, $engine_manual_link, $alternator_manual_link, $control_panel_manual_link,
            $description, $oil_filter_part, $fuel_filter_part, $water_fuel_filter_part, $air_filter_part,
            $water_filter_part
        ]);
        
        $asset_id = $pdo->lastInsertId();
        
        // آپلود عکس‌ها
        $upload_dir = 'uploads/assets/';
        $image_fields = [
            'oil_filter', 'fuel_filter', 'water_fuel_filter', 
            'air_filter', 'water_filter', 'device_image'
        ];
        
        foreach ($image_fields as $field) {
            if (!empty($_FILES[$field]['name'])) {
                try {
                    $image_path = uploadFile($_FILES[$field], $upload_dir);
                    
                    $stmt = $pdo->prepare("INSERT INTO asset_images (asset_id, field_name, image_path) VALUES (?, ?, ?)");
                    $stmt->execute([$asset_id, $field, $image_path]);
                } catch (Exception $e) {
                    // خطا در آپلود عکس - ادامه می‌دهیم
                    error_log("خطا در آپلود عکس $field: " . $e->getMessage());
                }
            }
        }
        
        $pdo->commit();
        
        $_SESSION['success'] = "دارایی با موفقیت افزوده شد!";
        logAction($pdo, 'ADD_ASSET', "افزودن دارایی جدید: $name (ID: $asset_id)");
        
        header('Location: assets.php');
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "خطا در افزودن دارایی: " . $e->getMessage();
        logAction($pdo, 'ADD_ASSET_ERROR', "خطا در افزودن دارایی: " . $e->getMessage());
    }
}

// حذف دارایی
if (isset($_GET['delete_id'])) {
    checkPermission('ادمین');
    
    $delete_id = (int)$_GET['delete_id'];
    
    try {
        // دریافت اطلاعات دارایی برای ثبت در لاگ
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

// دریافت انواع دارایی‌ها
$asset_types = $pdo->query("SELECT * FROM asset_types ORDER BY display_name")->fetchAll();

// جستجو و فیلتر
$search = $_GET['search'] ?? '';
$type_filter = $_GET['type_filter'] ?? '';
$status_filter = $_GET['status_filter'] ?? '';

// ساخت کوئری بر اساس فیلترها
$query = "SELECT a.*, at.display_name as type_display_name 
          FROM assets a 
          JOIN asset_types at ON a.type_id = at.id 
          WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (a.name LIKE ? OR a.serial_number LIKE ? OR a.model LIKE ? OR a.brand LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
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

// دریافت تعداد کل دارایی‌ها برای نمایش
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
    <link rel="stylesheet" href="styles.css">
    <style>
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .card-header {
            border-radius: 10px 10px 0 0 !important;
            font-weight: 600;
        }
        .search-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .image-preview {
            max-width: 200px;
            max-height: 200px;
            margin-top: 10px;
            display: none;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        .dynamic-field {
            display: none;
        }
        .badge-status {
            font-size: 0.9rem;
            padding: 0.5em 0.8em;
        }
        .table th {
            font-weight: 600;
            background-color: #f8f9fa;
        }
        .action-buttons .btn {
            margin-left: 5px;
        }
        .filter-active {
            background-color: #e9ecef;
            border-radius: 5px;
            padding: 5px 10px;
            font-weight: 600;
        }
        
        /* Step navigation styles */
        .form-steps { 
            display:flex; 
            gap:.6rem; 
            align-items:center; 
            flex-wrap:wrap; 
            margin-bottom:.75rem; 
        }
        .form-step-item { 
            min-width:74px; 
            border-radius:12px; 
            padding:.45rem .6rem; 
            background:#f6f8fb; 
            cursor:pointer; 
            border:1px solid #eef2f6; 
            text-align:center; 
            transition:all .18s ease-in-out; 
            direction:rtl; 
        }
        .form-step-item i { 
            display:block; 
            font-size:1.05rem; 
            margin-bottom:.18rem; 
        }
        .form-step-item .small { 
            font-size:.74rem; 
            opacity:.9; 
        }
        .form-step-item.active { 
            background:linear-gradient(180deg,#0d6efd,#0b5ed7); 
            color:#fff; 
            box-shadow:0 6px 18px rgba(13,110,253,0.12); 
            transform:translateY(-1px); 
            border-color:rgba(11,93,215,0.9); 
        }
        .form-step-item.completed { 
            background:#e7f1ff; 
            border-color:#cfe3ff; 
            color:#0566c9; 
        }
        .form-step-item .fa-check { 
            font-weight:700; 
        }
        @media (max-width:576px){ 
            .form-step-item { 
                min-width:60px; 
                padding:.35rem .45rem; 
            } 
        }
        
        .step { 
            display: none; 
        }
        .step.active { 
            display: block; 
        }
        
        .preview-item { 
            margin-bottom: 15px; 
            padding: 10px; 
            border: 1px solid #eee; 
            border-radius: 5px; 
        }
        .preview-label { 
            font-weight: bold; 
            color: #555; 
        }
        .supply-method-fields { 
            display: none; 
        }
        
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
        .flash { 
            animation: flash 480ms ease-in-out; 
        }
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
                        <button class="btn btn-primary me-2" onclick="showAddAssetForm()">
                            <i class="fas fa-plus"></i> افزودن دارایی جدید
                        </button>
                        <span class="filter-active">
                            <i class="fas fa-filter"></i> 
                            <?= $filtered_count ?> از <?= $total_assets ?> مورد
                        </span>
                    </div>
                </div>

                <!-- جستجو و فیلتر -->
                <div class="card search-box">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <input type="text" name="search" class="form-control" placeholder="جستجو بر اساس نام، سریال، مدل یا برند..." value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="col-md-2">
                                <select name="type_filter" class="form-select">
                                    <option value="">همه انواع</option>
                                    <?php foreach ($asset_types as $type): ?>
                                        <option value="<?= $type['id'] ?>" <?= $type_filter == $type['id'] ? 'selected' : '' ?>>
                                            <?= $type['display_name'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="status_filter" class="form-select">
                                    <option value="">همه وضعیت‌ها</option>
                                    <option value="فعال" <?= $status_filter == 'فعال' ? 'selected' : '' ?>>فعال</option>
                                    <option value="غیرفعال" <?= $status_filter == 'غیرفعال' ? 'selected' : '' ?>>غیرفعال</option>
                                    <option value="در حال تعمیر" <?= $status_filter == 'در حال تعمیر' ? 'selected' : '' ?>>در حال تعمیر</option>
                                    <option value="آماده بهره‌برداری" <?= $status_filter == 'آماده بهره‌برداری' ? 'selected' : '' ?>>آماده بهره‌برداری</option>
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

                <!-- نمایش پیام‌ها -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= $_SESSION['success'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= $_SESSION['error'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <!-- فرم افزودن دارایی -->
                <div class="card" id="add-asset-form" style="display: none;">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-plus-circle"></i> افزودن دارایی جدید</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="assetForm" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

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
                                                    <option value="<?= $type['id'] ?>"><?= $type['display_name'] ?></option>
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
                                            <input type="text" class="form-control gen-dev-serial" id="gen_serial_number" name="serial_number" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">تاریخ خرید</label>
                                            <input type="date" class="form-control" id="gen_purchase_date" name="purchase_date">
                                        </div>
                                    </div>
                                </div>

                                <!-- ردیف 2 -->
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">وضعیت *</label>
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
                                
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="power_capacity" class="form-label">ظرفیت توان (کیلووات) *</label>
                                            <input type="text" class="form-control" id="power_capacity" name="power_capacity">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="engine_model" class="form-label">مدل موتور *</label>
                                            <input type="text" class="form-control" id="engine_model" name="engine_model">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="engine_serial" class="form-label">سریال موتور *</label>
                                            <input type="text" class="form-control" id="engine_serial" name="engine_serial">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="alternator_model" class="form-label">مدل آلترناتور *</label>
                                            <input type="text" class="form-control" id="alternator_model" name="alternator_model">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="alternator_serial" class="form-label">سریال آلترناتور *</label>
                                            <input type="text" class="form-control" id="alternator_serial" name="alternator_serial">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="device_model" class="form-label">مدل دستگاه *</label>
                                            <input type="text" class="form-control" id="device_model" name="device_model">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="device_serial" class="form-label">سریال دستگاه *</label>
                                            <input type="text" class="form-control" id="device_serial" name="device_serial">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="control_panel_model" class="form-label">مدل کنترل پنل</label>
                                            <input type="text" class="form-control" id="control_panel_model" name="control_panel_model">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="breaker_model" class="form-label">مدل بریکر</label>
                                            <input type="text" class="form-control" id="breaker_model" name="breaker_model">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="fuel_tank_specs" class="form-label">مشخصات تانک سوخت</label>
                                            <textarea class="form-control" id="fuel_tank_specs" name="fuel_tank_specs" rows="2"></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="battery" class="form-label">باتری</label>
                                            <input type="text" class="form-control" id="battery" name="battery">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="battery_charger" class="form-label">باتری شارژر</label>
                                            <input type="text" class="form-control" id="battery_charger" name="battery_charger">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="heater" class="form-label">هیتر</label>
                                            <input type="text" class="form-control" id="heater" name="heater">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="oil_capacity" class="form-label">حجم روغن</label>
                                            <input type="text" class="form-control" id="oil_capacity" name="oil_capacity">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="radiator_capacity" class="form-label">حجم آب رادیاتور</label>
                                            <input type="text" class="form-control" id="radiator_capacity" name="radiator_capacity">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="antifreeze" class="form-label">ضدیخ</label>
                                            <input type="text" class="form-control" id="antifreeze" name="antifreeze">
                                        </div>
                                    </div>
                                </div>
                                
                                <h5 class="mt-4 mb-3 text-secondary">پارت نامبر فیلترها</h5>
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="oil_filter_part" class="form-label">پارت نامبر فیلتر روغن</label>
                                            <input type="text" class="form-control" id="oil_filter_part" name="oil_filter_part">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="oil_filter" class="form-label">عکس فیلتر روغن</label>
                                            <input type="file" class="form-control" id="oil_filter" name="oil_filter" accept="image/*" onchange="previewImage(this, 'oil_filter_preview')">
                                            <img id="oil_filter_preview" class="image-preview" src="#" alt="پیش‌نمایش">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="fuel_filter_part" class="form-label">پارت نامبر فیلتر سوخت</label>
                                            <input type="text" class="form-control" id="fuel_filter_part" name="fuel_filter_part">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="fuel_filter" class="form-label">عکس فیلتر سوخت</label>
                                            <input type="file" class="form-control" id="fuel_filter" name="fuel_filter" accept="image/*" onchange="previewImage(this, 'fuel_filter_preview')">
                                            <img id="fuel_filter_preview" class="image-preview" src="#" alt="پیش‌نمایش">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="water_fuel_filter_part" class="form-label">پارت نامبر فیلتر سوخت آبی</label>
                                            <input type="text" class="form-control" id="water_fuel_filter_part" name="water_fuel_filter_part">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="water_fuel_filter" class="form-label">عکس فیلتر سوخت آبی</label>
                                            <input type="file" class="form-control" id="water_fuel_filter" name="water_fuel_filter" accept="image/*" onchange="previewImage(this, 'water_fuel_filter_preview')">
                                            <img id="water_fuel_filter_preview" class="image-preview" src="#" alt="پیش‌نمایش">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="air_filter_part" class="form-label">پارت نامبر فیلتر هوا</label>
                                            <input type="text" class="form-control" id="air_filter_part" name="air_filter_part">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="air_filter" class="form-label">عکس فیلتر هوا</label>
                                            <input type="file" class="form-control" id="air_filter" name="air_filter" accept="image/*" onchange="previewImage(this, 'air_filter_preview')">
                                            <img id="air_filter_preview" class="image-preview" src="#" alt="پیش‌نمایش">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="water_filter_part" class="form-label">پارت نامبر فیلتر آب</label>
                                            <input type="text" class="form-control" id="water_filter_part" name="water_filter_part">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="water_filter" class="form-label">عکس فیلتر آب</label>
                                            <input type="file" class="form-control" id="water_filter" name="water_filter" accept="image/*" onchange="previewImage(this, 'water_filter_preview')">
                                            <img id="water_filter_preview" class="image-preview" src="#" alt="پیش‌نمایش">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="other_items" class="form-label">سایر اقلام مولد</label>
                                            <textarea class="form-control" id="other_items" name="other_items" rows="3"></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="device_image" class="form-label">عکس دستگاه</label>
                                            <input type="file" class="form-control" id="device_image" name="device_image" accept="image/*" onchange="previewImage(this, 'device_image_preview')">
                                            <img id="device_image_preview" class="image-preview" src="#" alt="پیش‌نمایش">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="workshop_entry_date" class="form-label">تاریخ ورود به کارگاه</label>
                                            <input type="date" class="form-control" id="workshop_entry_date" name="workshop_entry_date">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="workshop_exit_date" class="form-label">تاریخ خروج از کارگاه</label>
                                            <input type="date" class="form-control" id="workshop_exit_date" name="workshop_exit_date">
                                        </div>
                                    </div>
                                </div>
                                
                                <h5 class="mt-4 mb-3 text-secondary">لینک‌های مرتبط</h5>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="datasheet_link" class="form-label">لینک دیتاشیت</label>
                                            <input type="url" class="form-control" id="datasheet_link" name="datasheet_link">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="engine_manual_link" class="form-label">لینک منوال موتور</label>
                                            <input type="url" class="form-control" id="engine_manual_link" name="engine_manual_link">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="alternator_manual_link" class="form-label">لینک منوال آلترناتور</label>
                                            <input type="url" class="form-control" id="alternator_manual_link" name="alternator_manual_link">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="control_panel_manual_link" class="form-label">لینک منوال کنترل پنل</label>
                                            <input type="url" class="form-control" id="control_panel_manual_link" name="control_panel_manual_link">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">توضیحات</label>
                                    <textarea class="form-control" id="description" name="description" rows="4"></textarea>
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
                                            <input type="text" class="form-control" id="motor_serial_number" name="serial_number" required>
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
                                <button type="button" class="btn btn-primary" onclick="nextStep(3)">مرحله بعد <i class="fas fa-arrow-left"></i></button>
                            </div>
                        </div>

                        <div class="step" id="step3">
                            <h4 class="mb-4 text-primary">نحوه تامین</h4>
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="supply_method" class="form-label">نحوه تامین *</label>
                                        <select class="form-select" id="supply_method" name="supply_method" required onchange="toggleSupplyFields()">
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
                                <button type="button" class="btn btn-secondary" onclick="prevStep(3)"><i class="fas fa-arrow-right"></i> مرحله قبل</button>
                                <div>
                                    <button type="button" class="btn btn-warning" onclick="editForm()"><i class="fas fa-edit"></i> ویرایش اطلاعات</button>
                                    <button type="submit" name="add_asset" class="btn btn-success"><i class="fas fa-save"></i> ثبت نهایی</button>
                                </div>
                            </div>
                        </div>
                        </form>
                    </div>
                </div>

                <!-- لیست دارایی‌ها -->
                <div class="card mt-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-list"></i> لیست دارایی‌ها</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($assets) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>نام دستگاه</th>
                                            <th>نوع</th>
                                            <th>سریال</th>
                                            <th>برند/مدل</th>
                                            <th>وضعیت</th>
                                            <th>تاریخ خرید</th>
                                            <th>عملیات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($assets as $asset): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($asset['name']) ?></td>
                                            <td>
                                                <span class="badge bg-secondary"><?= htmlspecialchars($asset['type_display_name']) ?></span>
                                            </td>
                                            <td><?= htmlspecialchars($asset['serial_number']) ?></td>
                                            <td>
                                                <?= htmlspecialchars($asset['brand']) ?>
                                                <?= $asset['model'] ? ' / ' . htmlspecialchars($asset['model']) : '' ?>
                                            </td>
                                            <td>
                                                <?php
                                                $status_class = 'secondary';
                                                if ($asset['status'] == 'فعال') $status_class = 'success';
                                                if ($asset['status'] == 'غیرفعال') $status_class = 'danger';
                                                if ($asset['status'] == 'در حال تعمیر') $status_class = 'warning';
                                                if ($asset['status'] == 'آماده بهره‌برداری') $status_class = 'info';
                                                ?>
                                                <span class="badge bg-<?= $status_class ?> badge-status"><?= $asset['status'] ?></span>
                                            </td>
                                            <td><?= $asset['purchase_date'] ? jalaliDate($asset['purchase_date']) : '--' ?></td>
                                            <td class="action-buttons">
                                                <a href="asset_details.php?id=<?= $asset['id'] ?>" class="btn btn-sm btn-info" title="مشاهده جزئیات">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="edit_asset.php?id=<?= $asset['id'] ?>" class="btn btn-sm btn-warning" title="ویرایش">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if ($_SESSION['role'] == 'ادمین'): ?>
                                                <a href="assets.php?delete_id=<?= $asset['id'] ?>" class="btn btn-sm btn-danger" title="حذف"
                                                   onclick="return confirm('آیا از حذف این دارایی مطمئن هستید؟')">
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
                                <i class="fas fa-info-circle"></i> هیچ دارایی یافت نشد.
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
        
        document.getElementById('step' + currentStep).classList.remove('active');
        document.getElementById('step' + step).classList.add('active');
        currentStep = step;
        
        // Smart navigation based on asset type
        if (currentStep === 2) {
            if (assetType.includes('ژنراتور') || assetType.includes('موتور برق')) {
                // Skip supply step for generators and power motors
                nextStep(4);
                return;
            } else {
                // Go to supply step for consumables and parts
                nextStep(3);
                return;
            }
        }
        
        if (currentStep === 4) {
            generatePreview();
        }
        
        updateStepNav();
    }

    function prevStep(step) {
        document.getElementById('step' + currentStep).classList.remove('active');
        document.getElementById('step' + step).classList.add('active');
        currentStep = step;
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
            if (assetType.includes('ژنراتور')) {
                const name = document.getElementById('gen_name');
                const serial = document.getElementById('gen_serial_number');
                const status = document.getElementById('gen_status');
                if (!name.value.trim()) { isValid=false; errorMessage='لطفاً نام دستگاه را وارد کنید.'; }
                else if (!serial.value.trim()) { isValid=false; errorMessage='لطفاً شماره سریال را وارد کنید.'; }
                else if (!status.value) { isValid=false; errorMessage='لطفاً وضعیت را انتخاب کنید.'; }
            } else if (assetType.includes('موتور برق')) {
                const name = document.getElementById('motor_name');
                const serial = document.getElementById('motor_serial_number');
                const status = document.getElementById('motor_status');
                if (!name.value.trim()) { isValid=false; errorMessage='لطفاً نام موتور برق را انتخاب کنید.'; }
                else if (!serial.value.trim()) { isValid=false; errorMessage='لطفاً شماره سریال موتور را وارد کنید.'; }
                else if (!status.value) { isValid=false; errorMessage='لطفاً وضعیت را انتخاب کنید.'; }
            } else if (assetType.includes('مصرفی')) {
                const name = document.getElementById('consumable_name');
                const status = document.getElementById('consumable_status');
                const type = document.getElementById('consumable_type');
                if (!name.value.trim()) { isValid=false; errorMessage='لطفاً نام کالا را وارد کنید.'; }
                else if (!status.value) { isValid=false; errorMessage='لطفاً وضعیت را انتخاب کنید.'; }
                else if (!type.value.trim()) { isValid=false; errorMessage='لطفاً نوع کالای مصرفی را وارد کنید.'; }
            } else if (assetType.includes('قطعات')) {
                const name = document.getElementById('parts_name');
                const status = document.getElementById('parts_status');
                if (!name.value.trim()) { isValid=false; errorMessage='لطفاً نام قطعه را وارد کنید.'; }
                else if (!status.value) { isValid=false; errorMessage='لطفاً وضعیت را انتخاب کنید.'; }
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
            // Initialize motor identifier
            initMotorIdentifier();
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
        
        // اطلاعات عمومی
        let name = '', serial = '', purchaseDate = '', status = '', deviceIdentifier = '';
        
        if (assetType.includes('ژنراتور')) {
            name = document.getElementById('gen_name').value;
            serial = document.getElementById('gen_serial_number').value;
            purchaseDate = document.getElementById('gen_purchase_date').value;
            status = document.getElementById('gen_status').value;
            deviceIdentifier = document.getElementById('device_identifier').value;
        } else if (assetType.includes('موتور برق')) {
            name = document.getElementById('motor_name').value;
            serial = document.getElementById('motor_serial_number').value;
            purchaseDate = document.getElementById('motor_purchase_date').value;
            status = document.getElementById('motor_status').value;
            deviceIdentifier = serial; // Use serial as identifier for power motors
        } else if (assetType.includes('مصرفی')) {
            name = document.getElementById('consumable_name').value;
            serial = '--';
            purchaseDate = document.getElementById('consumable_purchase_date').value;
            status = document.getElementById('consumable_status').value;
            deviceIdentifier = name; // Use name as identifier for consumables
        } else if (assetType.includes('قطعات')) {
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
                    <div class="col-md-6 preview-item"><span class="preview-label">تاریخ خرید/ثبت:</span><span>${purchaseDate || '--'}</span></div>
                    <div class="col-md-6 preview-item"><span class="preview-label">وضعیت:</span><span>${status}</span></div>
                    <div class="col-md-6 preview-item"><span class="preview-label">شماره شناسایی:</span><span class="text-primary fw-bold">${deviceIdentifier || '--'}</span></div>
                </div>
            </div>`;
        
        previewContainer.innerHTML = previewHTML;
    }

    function editForm() {
        if (assetType.includes('مصرفی') || assetType.includes('قطعات')) {
            document.getElementById('step4').classList.remove('active');
            document.getElementById('step3').classList.add('active');
            currentStep = 3;
        } else {
            document.getElementById('step4').classList.remove('active');
            document.getElementById('step2').classList.add('active');
            currentStep = 2;
        }
        updateStepNav();
    }

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

    // Motor identifier system (similar to generator)
    function initMotorIdentifier() {
        const nameEl = document.getElementById('motor_name');
        const serialEl = document.getElementById('motor_serial_number');
        const typeEl = document.getElementById('motor_engine_type');
        
        if (nameEl && serialEl && typeEl) {
            // For power motors, use serial as identifier
            const updateIdentifier = () => {
                const serial = serialEl.value.trim();
                if (serial) {
                    // You can add any formatting logic here if needed
                    console.log('Motor identifier set to:', serial);
                }
            };
            
            serialEl.addEventListener('input', updateIdentifier);
            updateIdentifier();
        }
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

    // Initialize step navigation
    document.addEventListener('DOMContentLoaded', function(){
        updateStepNav();
    });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>