<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// ثبت لاگ
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
    <link rel="stylesheet" href="assets/css/styles.css">
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
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-plus-circle"></i> افزودن دارایی جدید</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="assetForm" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            
                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">نام دستگاه *</label>
                                        <input type="text" class="form-control" id="name" name="name" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="type_id" class="form-label">نوع دارایی *</label>
                                        <select class="form-select" id="type_id" name="type_id" required onchange="showFields()">
                                            <option value="">-- انتخاب کنید --</option>
                                            <?php foreach ($asset_types as $type): ?>
                                                <option value="<?= $type['id'] ?>"><?= $type['display_name'] ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="serial_number" class="form-label">شماره سریال *</label>
                                        <input type="text" class="form-control" id="serial_number" name="serial_number" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="purchase_date" class="form-label">تاریخ خرید</label>
                                        <input type="date" class="form-control" id="purchase_date" name="purchase_date">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="status" class="form-label">وضعیت *</label>
                                        <select class="form-select" id="status" name="status" required>
                                            <option value="فعال">فعال</option>
                                            <option value="غیرفعال">غیرفعال</option>
                                            <option value="در حال تعمیر">در حال تعمیر</option>
                                            <option value="آماده بهره‌برداری">آماده بهره‌برداری</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="brand" class="form-label">برند</label>
                                        <input type="text" class="form-control" id="brand" name="brand">
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="model" class="form-label">مدل</label>
                                        <input type="text" class="form-control" id="model" name="model">
                                    </div>
                                </div>
                            </div>

                            <!-- فیلدهای پویا -->
                            <div id="generator_fields" class="dynamic-field">
                                <hr>
                                <h4 class="mb-4 mt-4 text-primary">مشخصات ژنراتور</h4>
                                
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

                            <div id="motor_fields" class="dynamic-field">
                                <hr>
                                <h4 class="mb-4 mt-4 text-primary">مشخصات موتور برق</h4>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="power_capacity" class="form-label">ظرفیت توان (کیلووات) *</label>
                                            <input type="text" class="form-control" id="power_capacity" name="power_capacity">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="engine_type" class="form-label">نوع موتور *</label>
                                            <input type="text" class="form-control" id="engine_type" name="engine_type">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div id="consumable_fields" class="dynamic-field">
                                <hr>
                                <h4 class="mb-4 mt-4 text-primary">مشخصات اقلام مصرفی</h4>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="consumable_type" class="form-label">نوع کالای مصرفی *</label>
                                            <input type="text" class="form-control" id="consumable_type" name="consumable_type">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" name="add_asset" class="btn btn-success">
                                <i class="fas fa-save"></i> ثبت دارایی
                            </button>
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
    function showFields() {
        // مخفی کردن همه فیلدها
        document.querySelectorAll('.dynamic-field').forEach(field => {
            field.style.display = 'none';
        });

        // نمایش فیلدهای مربوطه
        const typeSelect = document.getElementById('type_id');
        const typeId = typeSelect.value;
        const selectedOption = typeSelect.options[typeSelect.selectedIndex];
        const typeName = selectedOption.text.toLowerCase();
        
        if (typeName.includes('ژنراتور')) {
            document.getElementById('generator_fields').style.display = 'block';
        } else if (typeName.includes('موتور برق')) {
            document.getElementById('motor_fields').style.display = 'block';
        } else if (typeName.includes('مصرفی')) {
            document.getElementById('consumable_fields').style.display = 'block';
        }
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

    // اعتبارسنجی فرم
    document.getElementById('assetForm').addEventListener('submit', function(e) {
        const typeSelect = document.getElementById('type_id');
        const typeId = typeSelect.value;
        const selectedOption = typeSelect.options[typeSelect.selectedIndex];
        const typeName = selectedOption.text.toLowerCase();
        
        let isValid = true;
        let errorMessage = '';
        
        if (typeName.includes('ژنراتور')) {
            const requiredFields = [
                'power_capacity', 'engine_model', 'engine_serial', 'alternator_model',
                'alternator_serial', 'device_model', 'device_serial'
            ];
            
            for (const field of requiredFields) {
                const fieldElement = document.getElementById(field);
                if (fieldElement && !fieldElement.value.trim()) {
                    isValid = false;
                    errorMessage = `لطفاً فیلد "${fieldElement.previousElementSibling.textContent}" را پر کنید.`;
                    break;
                }
            }
        } else if (typeName.includes('موتور برق')) {
            const powerCapacity = document.getElementById('power_capacity');
            const engineType = document.getElementById('engine_type');
            
            if (!powerCapacity.value.trim()) {
                isValid = false;
                errorMessage = 'لطفاً ظرفیت توان را وارد کنید.';
            } else if (!engineType.value.trim()) {
                isValid = false;
                errorMessage = 'لطفاً نوع موتور را وارد کنید.';
            }
        } else if (typeName.includes('مصرفی')) {
            const consumableType = document.getElementById('consumable_type');
            if (!consumableType.value.trim()) {
                isValid = false;
                errorMessage = 'لطفاً نوع کالای مصرفی را وارد کنید.';
            }
        }
        
        if (!isValid) {
            e.preventDefault();
            alert(errorMessage);
        }
    });

    // اجرای اولیه برای نمایش فیلدهای مناسب
    document.addEventListener('DOMContentLoaded', function() {
        showFields();
    });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>