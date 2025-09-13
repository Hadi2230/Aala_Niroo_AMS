<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// دریافت اطلاعات دستگاه
$asset_id = $_GET['id'] ?? null;
if (!$asset_id) {
    header('Location: assets.php');
    exit();
}

$stmt = $pdo->prepare("
    SELECT a.*, at.display_name as type_display_name, at.name as type_name 
    FROM assets a 
    JOIN asset_types at ON a.type_id = at.id 
    WHERE a.id = ?
");
$stmt->execute([$asset_id]);
$asset = $stmt->fetch();

if (!$asset) {
    header('Location: assets.php');
    exit();
}

// دریافت انواع دستگاه‌ها
$types = $pdo->query("SELECT * FROM asset_types")->fetchAll();

// ویرایش دستگاه
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_asset'])) {
    try {
        $pdo->beginTransaction();
        
        $name = sanitizeInput($_POST['name']);
        $type_id = (int)$_POST['type_id'];
        $serial_number = sanitizeInput($_POST['serial_number']);
        if (empty($serial_number)) {
            $serial_number = null;
        }
        
        $purchase_date_input = sanitizeInput($_POST['purchase_date'] ?? '');
        $purchase_date = null;
        if (preg_match('/^\d{4}\/\d{1,2}\/\d{1,2}$/', $purchase_date_input)) {
            $purchase_date = jalaliToGregorian($purchase_date_input);
        }
        
        $status = sanitizeInput($_POST['status']);
        
        // دریافت فیلدهای جدید
        $device_identifier = sanitizeInput($_POST['device_identifier'] ?? '');
        if (empty($device_identifier)) {
            $device_identifier = null;
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
        
        // دریافت فیلدهای عمومی
        $brand = sanitizeInput($_POST['brand'] ?? '');
        $model = sanitizeInput($_POST['model'] ?? '');
        $power_capacity = sanitizeInput($_POST['power_capacity'] ?? '');
        $engine_type = sanitizeInput($_POST['engine_type'] ?? '');
        $consumable_type = sanitizeInput($_POST['consumable_type'] ?? '');
        
        // دریافت فیلدهای خاص ژنراتور
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
        $workshop_entry_date = null;
        if (preg_match('/^\d{4}\/\d{1,2}\/\d{1,2}$/', $workshop_entry_date_input)) {
            $workshop_entry_date = jalaliToGregorian($workshop_entry_date_input);
        }
        
        $workshop_exit_date_input = sanitizeInput($_POST['workshop_exit_date'] ?? '');
        $workshop_exit_date = null;
        if (preg_match('/^\d{4}\/\d{1,2}\/\d{1,2}$/', $workshop_exit_date_input)) {
            $workshop_exit_date = jalaliToGregorian($workshop_exit_date_input);
        }
        
        $datasheet_link = sanitizeInput($_POST['datasheet_link'] ?? '');
        $engine_manual_link = sanitizeInput($_POST['engine_manual_link'] ?? '');
        $alternator_manual_link = sanitizeInput($_POST['alternator_manual_link'] ?? '');
        $control_panel_manual_link = sanitizeInput($_POST['control_panel_manual_link'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        
        // دریافت فیلدهای پارت نامبر
        $oil_filter_part = sanitizeInput($_POST['oil_filter_part'] ?? '');
        $fuel_filter_part = sanitizeInput($_POST['fuel_filter_part'] ?? '');
        $water_fuel_filter_part = sanitizeInput($_POST['water_fuel_filter_part'] ?? '');
        $air_filter_part = sanitizeInput($_POST['air_filter_part'] ?? '');
        $water_filter_part = sanitizeInput($_POST['water_filter_part'] ?? '');
        
        // آپدیت در دیتابیس
        $stmt = $pdo->prepare("UPDATE assets SET 
            name = ?, type_id = ?, serial_number = ?, purchase_date = ?, status = ?, 
            brand = ?, model = ?, power_capacity = ?, engine_type = ?, consumable_type = ?,
            engine_model = ?, engine_serial = ?, alternator_model = ?, alternator_serial = ?, 
            device_model = ?, device_serial = ?, control_panel_model = ?, breaker_model = ?, 
            fuel_tank_specs = ?, battery = ?, battery_charger = ?, heater = ?, oil_capacity = ?, 
            radiator_capacity = ?, antifreeze = ?, other_items = ?, workshop_entry_date = ?, 
            workshop_exit_date = ?, datasheet_link = ?, engine_manual_link = ?, 
            alternator_manual_link = ?, control_panel_manual_link = ?, description = ?,
            oil_filter_part = ?, fuel_filter_part = ?, water_fuel_filter_part = ?, 
            air_filter_part = ?, water_filter_part = ?, device_identifier = ?, 
            supply_method = ?, location = ?, quantity = ?, supplier_name = ?, supplier_contact = ?
            WHERE id = ?");
        
        $stmt->execute([
            $name, $type_id, $serial_number, $purchase_date, $status,
            $brand, $model, $power_capacity, $engine_type, $consumable_type,
            $engine_model, $engine_serial, $alternator_model, $alternator_serial,
            $device_model, $device_serial, $control_panel_model, $breaker_model,
            $fuel_tank_specs, $battery, $battery_charger, $heater, $oil_capacity,
            $radiator_capacity, $antifreeze, $other_items, $workshop_entry_date,
            $workshop_exit_date, $datasheet_link, $engine_manual_link,
            $alternator_manual_link, $control_panel_manual_link, $description,
            $oil_filter_part, $fuel_filter_part, $water_fuel_filter_part,
            $air_filter_part, $water_filter_part, $device_identifier,
            $supply_method, $location, $quantity, $supplier_name, $supplier_contact,
            $asset_id
        ]);
        
        $pdo->commit();
        $_SESSION['success'] = "اطلاعات دستگاه با موفقیت به‌روزرسانی شد!";
        header('Location: assets.php');
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "خطا در به‌روزرسانی دستگاه: " . $e->getMessage();
    }
}

// تابع تبدیل تاریخ شمسی به میلادی
function jalaliToGregorian($jalali_date) {
    $parts = explode('/', $jalali_date);
    if (count($parts) != 3) return null;
    
    $year = (int)$parts[0];
    $month = (int)$parts[1];
    $day = (int)$parts[2];
    
    // تبدیل ساده شمسی به میلادی
    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $jy = $year;
    $jm = $month;
    $jd = $day;
    
    $jy2 = ($jm > 2) ? ($jy + 1) : $jy;
    $days = 355666 + (365 * $jy) + ((int)(($jy2 + 3) / 4)) - ((int)(($jy2 + 99) / 100)) + ((int)(($jy2 + 399) / 400)) + $jd + $g_d_m[$jm - 1];
    $gy = -1595 + (33 * ((int)($days / 12053)));
    $days %= 12053;
    $gy += 4 * ((int)($days / 1461));
    $days %= 1461;
    if ($days > 365) {
        $gy += (int)(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    $gm = ($days < 186) ? 1 + (int)($days / 31) : 7 + (int)(($days - 186) / 30);
    $gd = 1 + (($days < 186) ? ($days % 31) : (($days - 186) % 30));
    
    return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
}

// تابع تبدیل تاریخ میلادی به شمسی
function gregorianToJalali($gregorian_date) {
    if (!$gregorian_date) return '';
    
    $parts = explode('-', $gregorian_date);
    if (count($parts) != 3) return '';
    
    $gy = (int)$parts[0];
    $gm = (int)$parts[1];
    $gd = (int)$parts[2];
    
    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + ((int)(($gy2 + 3) / 4)) - ((int)(($gy2 + 99) / 100)) + ((int)(($gy2 + 399) / 400)) + $gd + $g_d_m[$gm - 1];
    $jy = -1595 + (33 * ((int)($days / 12053)));
    $days %= 12053;
    $jy += 4 * ((int)($days / 1461));
    $days %= 1461;
    if ($days > 365) {
        $jy += (int)(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    $jm = ($days < 186) ? 1 + (int)($days / 31) : 7 + (int)(($days - 186) / 30);
    $jd = 1 + (($days < 186) ? ($days % 31) : (($days - 186) % 30));
    
    return sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ویرایش دستگاه - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Vazirmatn', sans-serif; }
        .form-step { display: none; }
        .form-step.active { display: block; }
        .step-indicator { display: flex; justify-content: center; margin-bottom: 2rem; }
        .step-item { 
            padding: 10px 20px; margin: 0 5px; 
            background: #e9ecef; border-radius: 25px; 
            cursor: pointer; transition: all 0.3s;
        }
        .step-item.active { background: #007bff; color: white; }
        .step-item.completed { background: #28a745; color: white; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block bg-light sidebar">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> داشبورد
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="assets.php">
                                <i class="fas fa-cogs"></i> مدیریت دارایی‌ها
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="customers.php">
                                <i class="fas fa-users"></i> مشتریان
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="assignments.php">
                                <i class="fas fa-handshake"></i> انتساب‌ها
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main content -->
            <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">ویرایش دستگاه</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="assets.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-right"></i> بازگشت
                        </a>
                    </div>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" id="editAssetForm" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <!-- Step 1: نوع دارایی -->
                    <div class="form-step active" id="step1">
                        <div class="card">
                            <div class="card-header">
                                <h5>انتخاب نوع دارایی</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">نوع دارایی *</label>
                                    <select class="form-select" id="type_id" name="type_id" required onchange="showFields()">
                                        <option value="">-- انتخاب کنید --</option>
                                        <?php foreach ($types as $type): ?>
                                            <option value="<?php echo $type['id']; ?>" 
                                                    <?php echo $asset['type_id'] == $type['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($type['display_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: اطلاعات -->
                    <div class="form-step" id="step2">
                        <div class="card">
                            <div class="card-header">
                                <h5>اطلاعات دستگاه</h5>
                            </div>
                            <div class="card-body">
                                <!-- فیلدهای ژنراتور -->
                                <div id="generator_fields" class="dynamic-field" style="display: none;">
                                    <h5 class="mb-3 text-secondary">مشخصات ژنراتور</h5>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">نام دستگاه *</label>
                                                <select class="form-select gen-name" id="gen_name" name="name" required>
                                                    <option value="">-- انتخاب کنید --</option>
                                                    <option value="Cummins" <?php echo $asset['name'] == 'Cummins' ? 'selected' : ''; ?>>Cummins</option>
                                                    <option value="Volvo" <?php echo $asset['name'] == 'Volvo' ? 'selected' : ''; ?>>Volvo</option>
                                                    <option value="Perkins" <?php echo $asset['name'] == 'Perkins' ? 'selected' : ''; ?>>Perkins</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">شماره سریال دستگاه</label>
                                                <input type="text" class="form-control gen-dev-serial" id="gen_serial_number" name="serial_number" value="<?php echo htmlspecialchars($asset['serial_number']); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">تاریخ خرید</label>
                                                <input type="text" class="form-control persian-date" id="gen_purchase_date" name="purchase_date" value="<?php echo gregorianToJalali($asset['purchase_date']); ?>" autocomplete="off">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">وضعیت *</label>
                                                <select class="form-select" id="gen_status" name="status" required>
                                                    <option value="">-- انتخاب کنید --</option>
                                                    <option value="فعال" <?php echo $asset['status'] == 'فعال' ? 'selected' : ''; ?>>فعال</option>
                                                    <option value="غیرفعال" <?php echo $asset['status'] == 'غیرفعال' ? 'selected' : ''; ?>>غیرفعال</option>
                                                    <option value="در حال تعمیر" <?php echo $asset['status'] == 'در حال تعمیر' ? 'selected' : ''; ?>>در حال تعمیر</option>
                                                    <option value="آماده بهره‌برداری" <?php echo $asset['status'] == 'آماده بهره‌برداری' ? 'selected' : ''; ?>>آماده بهره‌برداری</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">برند</label>
                                                <input type="text" class="form-control" id="gen_brand" name="brand" value="<?php echo htmlspecialchars($asset['brand']); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">مدل دستگاه *</label>
                                                <input type="text" class="form-control gen-device-model" id="gen_device_model" name="device_model" value="<?php echo htmlspecialchars($asset['device_model']); ?>" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">ظرفیت توان (کیلووات)</label>
                                                <input type="text" class="form-control" id="gen_power_capacity" name="power_capacity" value="<?php echo htmlspecialchars($asset['power_capacity']); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">مدل موتور</label>
                                                <input type="text" class="form-control" id="gen_engine_model" name="engine_model" value="<?php echo htmlspecialchars($asset['engine_model']); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">سریال موتور</label>
                                                <input type="text" class="form-control" id="gen_engine_serial" name="engine_serial" value="<?php echo htmlspecialchars($asset['engine_serial']); ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">مدل آلترناتور</label>
                                                <input type="text" class="form-control" id="gen_alternator_model" name="alternator_model" value="<?php echo htmlspecialchars($asset['alternator_model']); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">سریال آلترناتور *</label>
                                                <input type="text" class="form-control gen-alt-serial" id="gen_alternator_serial" name="alternator_serial" value="<?php echo htmlspecialchars($asset['alternator_serial']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">سریال دستگاه</label>
                                                <input type="text" class="form-control" id="gen_device_serial" name="device_serial" value="<?php echo htmlspecialchars($asset['device_serial']); ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- شماره شناسه دستگاه -->
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <div class="mb-3 identifier-wrapper">
                                                <label class="form-label d-flex align-items-center gap-2">
                                                    شماره شناسه دستگاه
                                                    <span class="badge bg-success gen-identifier-status" id="identifier_status">تولید شده</span>
                                                </label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control identifier-complete gen-device-identifier"
                                                           id="device_identifier" name="device_identifier"
                                                           value="<?php echo htmlspecialchars($asset['device_identifier']); ?>"
                                                           readonly>
                                                    <button class="btn btn-outline-secondary gen-copy-btn" type="button" id="copy_identifier" title="کپی">
                                                        کپی
                                                    </button>
                                                </div>
                                                <div class="form-text gen-identifier-hint" id="identifier_hint">
                                                    شناسه تولید شده: <?php echo htmlspecialchars($asset['device_identifier']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- فیلدهای موتور برق -->
                                <div id="motor_fields" class="dynamic-field" style="display: none;">
                                    <h5 class="mb-3 text-secondary">مشخصات موتور برق</h5>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">نام دستگاه *</label>
                                                <input type="text" class="form-control" id="motor_name" name="name" value="<?php echo htmlspecialchars($asset['name']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">سریال موتور *</label>
                                                <input type="text" class="form-control" id="motor_serial_number" name="serial_number" value="<?php echo htmlspecialchars($asset['serial_number']); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">تاریخ خرید</label>
                                                <input type="text" class="form-control persian-date" id="motor_purchase_date" name="purchase_date" value="<?php echo gregorianToJalali($asset['purchase_date']); ?>" autocomplete="off">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">وضعیت *</label>
                                                <select class="form-select" id="motor_status" name="status" required>
                                                    <option value="">-- انتخاب کنید --</option>
                                                    <option value="فعال" <?php echo $asset['status'] == 'فعال' ? 'selected' : ''; ?>>فعال</option>
                                                    <option value="غیرفعال" <?php echo $asset['status'] == 'غیرفعال' ? 'selected' : ''; ?>>غیرفعال</option>
                                                    <option value="در حال تعمیر" <?php echo $asset['status'] == 'در حال تعمیر' ? 'selected' : ''; ?>>در حال تعمیر</option>
                                                    <option value="آماده بهره‌برداری" <?php echo $asset['status'] == 'آماده بهره‌برداری' ? 'selected' : ''; ?>>آماده بهره‌برداری</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">برند</label>
                                                <input type="text" class="form-control" id="motor_brand" name="brand" value="<?php echo htmlspecialchars($asset['brand']); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">مدل</label>
                                                <input type="text" class="form-control" id="motor_model" name="model" value="<?php echo htmlspecialchars($asset['model']); ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- فیلدهای اقلام مصرفی -->
                                <div id="consumable_fields" class="dynamic-field" style="display: none;">
                                    <h5 class="mb-3 text-secondary">مشخصات اقلام مصرفی</h5>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">نام کالا *</label>
                                                <input type="text" class="form-control" id="consumable_name" name="name" value="<?php echo htmlspecialchars($asset['name']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">شماره شناسه *</label>
                                                <input type="text" class="form-control" id="consumable_device_identifier" name="device_identifier" value="<?php echo htmlspecialchars($asset['device_identifier']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">تاریخ خرید</label>
                                                <input type="text" class="form-control persian-date" id="consumable_purchase_date" name="purchase_date" value="<?php echo gregorianToJalali($asset['purchase_date']); ?>" autocomplete="off">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">وضعیت *</label>
                                                <select class="form-select" id="consumable_status" name="status" required>
                                                    <option value="">-- انتخاب کنید --</option>
                                                    <option value="فعال" <?php echo $asset['status'] == 'فعال' ? 'selected' : ''; ?>>فعال</option>
                                                    <option value="غیرفعال" <?php echo $asset['status'] == 'غیرفعال' ? 'selected' : ''; ?>>غیرفعال</option>
                                                    <option value="در حال تعمیر" <?php echo $asset['status'] == 'در حال تعمیر' ? 'selected' : ''; ?>>در حال تعمیر</option>
                                                    <option value="آماده بهره‌برداری" <?php echo $asset['status'] == 'آماده بهره‌برداری' ? 'selected' : ''; ?>>آماده بهره‌برداری</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">نوع کالا</label>
                                                <input type="text" class="form-control" id="consumable_type" name="consumable_type" value="<?php echo htmlspecialchars($asset['consumable_type']); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">تعداد</label>
                                                <input type="number" class="form-control" id="consumable_quantity" name="quantity" value="<?php echo $asset['quantity']; ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- فیلدهای قطعات -->
                                <div id="parts_fields" class="dynamic-field" style="display: none;">
                                    <h5 class="mb-3 text-secondary">مشخصات قطعات</h5>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">نام قطعه *</label>
                                                <input type="text" class="form-control" id="parts_name" name="name" value="<?php echo htmlspecialchars($asset['name']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">شماره شناسه *</label>
                                                <input type="text" class="form-control" id="parts_device_identifier" name="device_identifier" value="<?php echo htmlspecialchars($asset['device_identifier']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">تاریخ خرید</label>
                                                <input type="text" class="form-control persian-date" id="parts_purchase_date" name="purchase_date" value="<?php echo gregorianToJalali($asset['purchase_date']); ?>" autocomplete="off">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">وضعیت *</label>
                                                <select class="form-select" id="parts_status" name="status" required>
                                                    <option value="">-- انتخاب کنید --</option>
                                                    <option value="فعال" <?php echo $asset['status'] == 'فعال' ? 'selected' : ''; ?>>فعال</option>
                                                    <option value="غیرفعال" <?php echo $asset['status'] == 'غیرفعال' ? 'selected' : ''; ?>>غیرفعال</option>
                                                    <option value="در حال تعمیر" <?php echo $asset['status'] == 'در حال تعمیر' ? 'selected' : ''; ?>>در حال تعمیر</option>
                                                    <option value="آماده بهره‌برداری" <?php echo $asset['status'] == 'آماده بهره‌برداری' ? 'selected' : ''; ?>>آماده بهره‌برداری</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">نوع قطعه</label>
                                                <input type="text" class="form-control" id="parts_type" name="consumable_type" value="<?php echo htmlspecialchars($asset['consumable_type']); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">تعداد</label>
                                                <input type="number" class="form-control" id="parts_quantity" name="quantity" value="<?php echo $asset['quantity']; ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- دکمه‌های کنترل -->
                    <div class="d-flex justify-content-between mt-4">
                        <button type="button" class="btn btn-secondary" onclick="prevStep()" id="prevBtn" style="display: none;">قبلی</button>
                        <div>
                            <button type="button" class="btn btn-primary" onclick="nextStep()" id="nextBtn">بعدی</button>
                            <button type="submit" class="btn btn-success" name="edit_asset" id="submitBtn" style="display: none;">ذخیره تغییرات</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentStep = 1;
        const totalSteps = 2;

        function showStep(step) {
            // مخفی کردن تمام مراحل
            for (let i = 1; i <= totalSteps; i++) {
                document.getElementById(`step${i}`).classList.remove('active');
            }
            
            // نمایش مرحله فعلی
            document.getElementById(`step${step}`).classList.add('active');
            
            // کنترل دکمه‌ها
            document.getElementById('prevBtn').style.display = step > 1 ? 'block' : 'none';
            document.getElementById('nextBtn').style.display = step < totalSteps ? 'block' : 'none';
            document.getElementById('submitBtn').style.display = step === totalSteps ? 'block' : 'none';
        }

        function nextStep() {
            if (currentStep < totalSteps) {
                currentStep++;
                showStep(currentStep);
            }
        }

        function prevStep() {
            if (currentStep > 1) {
                currentStep--;
                showStep(currentStep);
            }
        }

        function showFields() {
            const typeId = document.getElementById('type_id').value;
            const typeName = document.getElementById('type_id').selectedOptions[0].text;
            
            // مخفی کردن تمام فیلدها
            document.querySelectorAll('.dynamic-field').forEach(field => {
                field.style.display = 'none';
            });
            
            // نمایش فیلدهای مربوط به نوع انتخاب شده
            if (typeName.includes('ژنراتور')) {
                document.getElementById('generator_fields').style.display = 'block';
                initGeneratorIdentifier();
            } else if (typeName.includes('موتور برق')) {
                document.getElementById('motor_fields').style.display = 'block';
            } else if (typeName.includes('مصرفی')) {
                document.getElementById('consumable_fields').style.display = 'block';
            } else if (typeName.includes('قطعات')) {
                document.getElementById('parts_fields').style.display = 'block';
            }
        }

        function initGeneratorIdentifier() {
            const nameField = document.getElementById('gen_name');
            const altSerialField = document.getElementById('gen_alternator_serial');
            const devSerialField = document.getElementById('gen_device_serial');
            const identifierField = document.getElementById('device_identifier');
            const statusBadge = document.getElementById('identifier_status');
            const copyBtn = document.getElementById('copy_identifier');
            
            function updateIdentifier() {
                const name = nameField.value;
                const altSerial = altSerialField.value;
                const devSerial = devSerialField.value;
                
                if (name && altSerial && devSerial && altSerial.length >= 4 && devSerial.length >= 4) {
                    const identifier = name.charAt(0) + altSerial.substring(0, 4) + devSerial.substring(devSerial.length - 4);
                    identifierField.value = identifier;
                    statusBadge.textContent = 'تولید شده';
                    statusBadge.className = 'badge bg-success gen-identifier-status';
                    copyBtn.disabled = false;
                } else {
                    identifierField.value = '';
                    statusBadge.textContent = 'در انتظار';
                    statusBadge.className = 'badge bg-secondary gen-identifier-status';
                    copyBtn.disabled = true;
                }
            }
            
            // اضافه کردن event listener ها
            nameField.addEventListener('input', updateIdentifier);
            altSerialField.addEventListener('input', updateIdentifier);
            devSerialField.addEventListener('input', updateIdentifier);
            
            // کپی کردن شناسه
            copyBtn.addEventListener('click', function() {
                if (identifierField.value) {
                    navigator.clipboard.writeText(identifierField.value).then(function() {
                        alert('شناسه کپی شد!');
                    });
                }
            });
            
            // مقداردهی اولیه
            updateIdentifier();
        }

        // اضافه کردن event listener برای پر کردن خودکار برند
        document.addEventListener('DOMContentLoaded', function() {
            const genNameSelect = document.getElementById('gen_name');
            if (genNameSelect) {
                genNameSelect.addEventListener('change', function() {
                    const brandField = document.getElementById('gen_brand');
                    if (brandField && this.value) {
                        brandField.value = this.value;
                    }
                });
            }
            
            // نمایش فیلدهای مربوط به نوع فعلی
            showFields();
        });
    </script>
</body>
</html>