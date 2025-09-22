<?php
// profile.php - نسخهٔ نهایی و مقاوم
session_start();
require_once 'config.php';

// تابع کمکی برای خروجی امن HTML
function e($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

// مقداردهی اولیه متغیرها
$assetId = (int)($_GET['id'] ?? $_POST['asset_id'] ?? 0);
$assetData = null;
$allAssets = [];
$services = [];
$tasks = [];
$correspondence = [];
$assignments = [];

// بررسی وجود assetId
if ($assetId <= 0) {
    $_SESSION['error'] = 'شناسه دارایی معتبر نیست.';
    header('Location: assets.php');
    exit;
}

// پردازش فرم‌ها (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $postAssetId = (int)($_POST['asset_id'] ?? 0);
        if ($postAssetId <= 0) {
            throw new Exception('شناسهٔ دارایی (asset_id) ارسال نشده است.');
        }

        // ---------- ثبت سرویس ----------
        if (isset($_POST['add_service'])) {
            $service_date = trim($_POST['service_date'] ?? '') ?: null;
            // تبدیل تاریخ شمسی به میلادی برای ذخیره در دیتابیس
            if ($service_date) {
                $service_date = jalaliToGregorianForDB($service_date);
            }
            $service_type = trim($_POST['service_type'] ?? '') ?: null;
            $service_provider = trim($_POST['service_provider'] ?? '') ?: null;
            $description = trim($_POST['description'] ?? '') ?: null;
            $cost = $_POST['cost'] !== '' ? $_POST['cost'] : null;

            $ins = $pdo->prepare(
                "INSERT INTO asset_services
                (asset_id, service_date, service_type, performed_by, summary, cost)
                VALUES (?, ?, ?, ?, ?, ?)"
            );
            $ins->execute([
                $postAssetId,
                $service_date,
                $service_type,
                $service_provider,
                $description,
                $cost,
            ]);

            $_SESSION['success'] = 'سرویس با موفقیت ثبت شد.';
            header('Location: profile.php?id=' . $postAssetId);
            exit;
        }

        // ---------- ثبت تسک ----------
        if (isset($_POST['add_task'])) {
            $task_name = trim($_POST['task_name'] ?? '');
            if ($task_name === '') {
                throw new Exception('عنوان تسک الزامی است.');
            }
            $assigned_to = trim($_POST['assigned_to'] ?? '') ?: null;
            $due_date = trim($_POST['due_date'] ?? '') ?: null;
            // تبدیل تاریخ شمسی به میلادی برای ذخیره در دیتابیس
            if ($due_date) {
                $due_date = jalaliToGregorianForDB($due_date);
            }
            $status = trim($_POST['status'] ?? 'pending');
            $allowed = ['pending', 'in_progress', 'completed', 'cancelled'];
            if (!in_array($status, $allowed, true)) $status = 'pending';
            $task_description = trim($_POST['description'] ?? '') ?: null;

            $ins = $pdo->prepare(
                "INSERT INTO maintenance_tasks
                (asset_id, title, assigned_to, planned_date, status, description)
                VALUES (?, ?, ?, ?, ?, ?)"
            );
            $ins->execute([
                $postAssetId,
                $task_name,
                $assigned_to,
                $due_date,
                $status,
                $task_description,
            ]);

            $_SESSION['success'] = 'تسک با موفقیت اضافه شد.';
            header('Location: profile.php?id=' . $postAssetId);
            exit;
        }

        // ---------- ویرایش دارایی ----------
        if (isset($_POST['edit_asset'])) {
            // Sanitize all input fields
            $name = trim($_POST['name'] ?? '');
            $brand = trim($_POST['brand'] ?? '');
            $model = trim($_POST['model'] ?? '');
            $serial_number = trim($_POST['serial_number'] ?? '');
            if (empty($serial_number)) {
                $serial_number = null; // Set to NULL for empty values to avoid UNIQUE constraint violation
            }
            
            $purchase_date = trim($_POST['purchase_date'] ?? '');
            // تبدیل تاریخ شمسی به میلادی برای ذخیره در دیتابیس
            if ($purchase_date) {
                $purchase_date = jalaliToGregorianForDB($purchase_date);
            }
            $status = trim($_POST['status'] ?? '');
            
            // Generator specific fields
            $power_capacity = trim($_POST['power_capacity'] ?? '');
            $engine_type = trim($_POST['engine_type'] ?? '');
            $engine_model = trim($_POST['engine_model'] ?? '');
            $engine_serial = trim($_POST['engine_serial'] ?? '');
            $alternator_model = trim($_POST['alternator_model'] ?? '');
            $alternator_serial = trim($_POST['alternator_serial'] ?? '');
            $device_model = trim($_POST['device_model'] ?? '');
            $device_serial = trim($_POST['device_serial'] ?? '');
            $control_panel_model = trim($_POST['control_panel_model'] ?? '');
            $breaker_model = trim($_POST['breaker_model'] ?? '');
            $battery = trim($_POST['battery'] ?? '');
            $battery_charger = trim($_POST['battery_charger'] ?? '');
            $oil_capacity = trim($_POST['oil_capacity'] ?? '');
            $radiator_capacity = trim($_POST['radiator_capacity'] ?? '');
            $antifreeze = trim($_POST['antifreeze'] ?? '');
            
            // Filter parts
            $oil_filter_part = trim($_POST['oil_filter_part'] ?? '');
            $fuel_filter_part = trim($_POST['fuel_filter_part'] ?? '');
            $water_fuel_filter_part = trim($_POST['water_fuel_filter_part'] ?? '');
            $air_filter_part = trim($_POST['air_filter_part'] ?? '');
            $water_filter_part = trim($_POST['water_filter_part'] ?? '');
            
            // Workshop dates
            $workshop_entry_date = trim($_POST['workshop_entry_date'] ?? '');
            $workshop_exit_date = trim($_POST['workshop_exit_date'] ?? '');
            // تبدیل تاریخ‌های شمسی به میلادی برای ذخیره در دیتابیس
            if ($workshop_entry_date) {
                $workshop_entry_date = jalaliToGregorianForDB($workshop_entry_date);
            }
            if ($workshop_exit_date) {
                $workshop_exit_date = jalaliToGregorianForDB($workshop_exit_date);
            }
            
            // Manual links
            $datasheet_link = trim($_POST['datasheet_link'] ?? '');
            $engine_manual_link = trim($_POST['engine_manual_link'] ?? '');
            $alternator_manual_link = trim($_POST['alternator_manual_link'] ?? '');
            $control_panel_manual_link = trim($_POST['control_panel_manual_link'] ?? '');
            
            // Consumable specific fields
            $consumable_type = trim($_POST['consumable_type'] ?? '');
            
            // Device identifier
            $device_identifier = trim($_POST['device_identifier'] ?? '');
            if (empty($device_identifier)) {
                $device_identifier = null; // Set to NULL for empty values
            }
            
            // Additional fields that might be present
            $fuel_tank_specs = trim($_POST['fuel_tank_specs'] ?? '');
            $other_items = trim($_POST['other_items'] ?? '');
            $heater = trim($_POST['heater'] ?? '');
            $supply_method = trim($_POST['supply_method'] ?? '');
            if (empty($supply_method)) {
                $supply_method = null;
            }
            $location = trim($_POST['location'] ?? '');
            if (empty($location)) {
                $location = null;
            }
            $quantity = trim($_POST['quantity'] ?? '');
            if (empty($quantity)) {
                $quantity = null;
            }
            $supplier_name = trim($_POST['supplier_name'] ?? '');
            if (empty($supplier_name)) {
                $supplier_name = null;
            }
            $supplier_contact = trim($_POST['supplier_contact'] ?? '');
            if (empty($supplier_contact)) {
                $supplier_contact = null;
            }
            
            $description = trim($_POST['description'] ?? '');

            // Update query with all possible fields
            $stmt = $pdo->prepare("
                UPDATE assets SET 
                name = ?, brand = ?, model = ?, serial_number = ?, purchase_date = ?, 
                status = ?, power_capacity = ?, engine_type = ?, engine_model = ?, 
                engine_serial = ?, alternator_model = ?, alternator_serial = ?, 
                device_model = ?, device_serial = ?, control_panel_model = ?, 
                breaker_model = ?, battery = ?, battery_charger = ?, oil_capacity = ?, 
                radiator_capacity = ?, antifreeze = ?, oil_filter_part = ?, fuel_filter_part = ?, 
                water_fuel_filter_part = ?, air_filter_part = ?, water_filter_part = ?, 
                workshop_entry_date = ?, workshop_exit_date = ?, datasheet_link = ?, 
                engine_manual_link = ?, alternator_manual_link = ?, 
                control_panel_manual_link = ?, consumable_type = ?, device_identifier = ?,
                fuel_tank_specs = ?, other_items = ?, heater = ?, supply_method = ?,
                location = ?, quantity = ?, supplier_name = ?, supplier_contact = ?, description = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $name, $brand, $model, $serial_number, $purchase_date, $status, 
                $power_capacity, $engine_type, $engine_model, $engine_serial, 
                $alternator_model, $alternator_serial, $device_model, $device_serial, 
                $control_panel_model, $breaker_model, $battery, $battery_charger, 
                $oil_capacity, $radiator_capacity, $antifreeze, $oil_filter_part, $fuel_filter_part, 
                $water_fuel_filter_part, $air_filter_part, $water_filter_part, 
                $workshop_entry_date, $workshop_exit_date, $datasheet_link, 
                $engine_manual_link, $alternator_manual_link, $control_panel_manual_link,
                $consumable_type, $device_identifier, $fuel_tank_specs, $other_items, $heater,
                $supply_method, $location, $quantity, $supplier_name, $supplier_contact,
                $description, $postAssetId
            ]);

            $_SESSION['success'] = 'اطلاعات دستگاه با موفقیت به‌روزرسانی شد.';
            header('Location: profile.php?id=' . $postAssetId);
            exit;
        }

        // ---------- ثبت مکاتبه ----------
        if (isset($_POST['add_correspondence'])) {
            $corr_date = trim($_POST['corr_date'] ?? '') ?: jalaliDate();
            // تبدیل تاریخ شمسی به میلادی برای ذخیره در دیتابیس
            if ($corr_date) {
                $corr_date = jalaliToGregorianForDB($corr_date);
            }
            $summary = trim($_POST['summary'] ?? '') ?: null;
            $notes = trim($_POST['notes'] ?? '') ?: null;
            $filePath = null;

            if (!empty($_FILES['corr_file']['name'])) {
                $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'correspondence' . DIRECTORY_SEPARATOR . $postAssetId;
                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
                    throw new Exception('خطا در ایجاد پوشه آپلود.');
                }
                $orig = basename($_FILES['corr_file']['name']);
                $ext = pathinfo($orig, PATHINFO_EXTENSION);
                $filename = time() . '_' . bin2hex(random_bytes(6)) . ($ext ? '.' . $ext : '');
                $targetFile = $uploadDir . DIRECTORY_SEPARATOR . $filename;
                if (!move_uploaded_file($_FILES['corr_file']['tmp_name'], $targetFile)) {
                    throw new Exception('آپلود فایل ناموفق بود.');
                }
                $filePath = 'uploads/correspondence/' . $postAssetId . '/' . $filename;
            }

            $ins = $pdo->prepare(
                "INSERT INTO asset_correspondence
                (asset_id, letter_date, subject, notes, file_path)
                VALUES (?, ?, ?, ?, ?)"
            );
            $ins->execute([
                $postAssetId,
                $corr_date,
                $summary,
                $notes,
                $filePath,
            ]);

            $_SESSION['success'] = 'مکاتبه با موفقیت ثبت شد.';
            header('Location: profile.php?id=' . $postAssetId);
            exit;
        }

    } catch (Throwable $ex) {
        error_log('profile.php POST error: ' . $ex->getMessage());
        $_SESSION['error'] = 'خطا در پردازش فرم: ' . $ex->getMessage();
        header('Location: profile.php?id=' . ($postAssetId ?: $assetId));
        exit;
    }
}

// ---------- بارگذاری اطلاعات برای نمایش ----------
try {
    if ($assetId > 0) {
        // اطلاعات دارایی با نوع دارایی
        $stmt = $pdo->prepare("
            SELECT a.*, at.name as asset_type_name, at.display_name as asset_type_display 
            FROM assets a 
            LEFT JOIN asset_types at ON a.type_id = at.id 
            WHERE a.id = ?
        ");
        $stmt->execute([$assetId]);
        $assetData = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        
        if ($assetData) {
            // سرویس‌های دارایی
            $stmt = $pdo->prepare("SELECT * FROM asset_services WHERE asset_id = ? ORDER BY service_date DESC, created_at DESC");
            $stmt->execute([$assetId]);
            $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // تسک‌های نگهداری
            $stmt = $pdo->prepare("SELECT * FROM maintenance_tasks WHERE asset_id = ? ORDER BY planned_date DESC, created_at DESC");
            $stmt->execute([$assetId]);
            $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // مکاتبات
            $stmt = $pdo->prepare("SELECT * FROM asset_correspondence WHERE asset_id = ? ORDER BY letter_date DESC, created_at DESC");
            $stmt->execute([$assetId]);
            $correspondence = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // انتساب‌های دارایی
            $stmt = $pdo->prepare("
                SELECT aa.*, c.full_name AS customer_name, c.phone AS customer_phone,
                       ad.installation_date, ad.warranty_start_date, ad.warranty_end_date
                FROM asset_assignments aa
                JOIN customers c ON aa.customer_id = c.id
                LEFT JOIN assignment_details ad ON aa.id = ad.assignment_id
                WHERE aa.asset_id = ?
                ORDER BY aa.assignment_date DESC
            ");
            $stmt->execute([$assetId]);
            $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        // لیست همه دارایی‌ها
        $stmt = $pdo->query("SELECT * FROM assets ORDER BY id DESC");
        $allAssets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $ex) {
    error_log('profile.php SELECT error: ' . $ex->getMessage());
    $_SESSION['error'] = 'خطا در بارگذاری اطلاعات: ' . $ex->getMessage();
    $assetData = null;
    $allAssets = [];
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پروفایل دارایی - <?= e($assetData['name'] ?? 'نامشخص') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <!-- Persian DatePicker CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css">
    
    <style>
        html, body { 
            font-family: Vazirmatn, Tahoma, Arial, sans-serif; 
            background-color: #f8f9fa;
        }
        .status-badge {
            font-size: 0.8em;
            padding: 0.25em 0.5em;
        }
        .file-link {
            color: #0d6efd;
            text-decoration: none;
        }
        .file-link:hover {
            text-decoration: underline;
        }
        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid rgba(0, 0, 0, 0.125);
        }
        .card-header {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.125);
        }
        .nav-tabs .nav-link.active {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }
        .nav-tabs .nav-link {
            color: #007bff;
            border: 1px solid transparent;
        }
        .nav-tabs .nav-link:hover {
            border-color: #e9ecef #e9ecef #007bff;
            color: #007bff;
        }
        .nav-tabs {
            border-bottom: 1px solid #dee2e6;
        }
        .tab-content {
            background-color: white;
            border: 1px solid #dee2e6;
            border-top: none;
            padding: 20px;
        }
        .text-primary {
            color: #007bff !important;
        }
        .border-bottom {
            border-bottom: 2px solid #007bff !important;
        }
        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
        }
        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }
        /* تنظیم فونت و فاصله */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* ظاهر کلی تب‌ها */
        .nav-tabs {
            border-bottom: none;
            gap: 8px;
            justify-content: flex-start;
        }

        /* تب‌ها */
        .nav-tabs .nav-link {
            font-weight: 600;
            padding: 12px 24px;
            border-radius: 16px 16px 0 0;
            transition: all 0.3s ease;
            border: none;
            color: #fff;
            background: linear-gradient(135deg, #6c5ce7, #a29bfe);
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
        }

        /* هاور روی تب‌ها */
        .nav-tabs .nav-link:hover {
            background: linear-gradient(135deg, #a29bfe, #ffeaa7);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 6px 14px rgba(0,0,0,0.15);
        }

        /* تب فعال */
        .nav-tabs .nav-link.active {
            background: linear-gradient(135deg, #00b894, #00cec9);
            color: #fff;
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
            transform: translateY(-2px);
        }

        /* محتوای تب‌ها */
        .tab-content {
            border-radius: 0 0 16px 16px;
            background: #f7f9fc;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            padding: 24px;
            min-height: 400px;
            transition: all 0.3s ease;
        }

        /* دکمه‌ها */
        .tab-content .btn-primary {
            border-radius: 12px;
            background: linear-gradient(135deg, #00b894, #00cec9);
            border: none;
            color: #fff;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        .tab-content .btn-primary:hover {
            background: linear-gradient(135deg, #019875, #00b3b0);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.25);
        }

        /* آیکن‌ها */
        .nav-tabs .nav-link i {
            margin-right: 8px;
        }
    </style>
</head>
<body style="padding-top: 80px;">
    <?php include 'navbar.php'; ?>

    <div class="container-fluid mt-4">

<?php if (!empty($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle"></i> <?= e($_SESSION['success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if (!empty($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-triangle"></i> <?= e($_SESSION['error']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<?php if ($assetData): ?>
    <!-- هدر پروفایل -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">پروفایل دستگاه</h2>
            <h4 class="text-primary mb-0"><?= e($assetData['name'] ?? 'بدون نام') ?></h4>
            <small class="text-muted">شناسه: <?= e($assetData['id']) ?></small>
        </div>
        <div>
            <a href="assets.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-right"></i> بازگشت به لیست
            </a>
            <button class="btn btn-warning" onclick="openEditModal()" id="editButton">
                <i class="fas fa-edit"></i> ویرایش دستگاه
            </button>
        </div>
    </div>

    <!-- اطلاعات کلی دارایی -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-info-circle"></i> اطلاعات دستگاه</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>نام دستگاه:</strong> <?= e($assetData['name'] ?? '-') ?></p>
                    <p><strong>برند:</strong> <?= e($assetData['brand'] ?? '-') ?></p>
                    <p><strong>مدل:</strong> <?= e($assetData['model'] ?? '-') ?></p>
                    <p><strong>سریال:</strong> <?= e($assetData['serial_number'] ?? '-') ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>تاریخ خرید:</strong> <?= e(gregorianToJalaliFromDB($assetData['purchase_date'] ?? '')) ?></p>
                    <p><strong>وضعیت:</strong> 
                        <span class="badge bg-<?= ($assetData['status'] ?? '') === 'فعال' ? 'success' : 'warning' ?>">
                            <?= e($assetData['status'] ?? 'نامشخص') ?>
                        </span>
                    </p>
                    <p><strong>نوع دارایی:</strong> <?= e($assetData['asset_type_display'] ?? $assetData['asset_type_name'] ?? '-') ?></p>
                    <?php if (!empty($assetData['power_capacity'])): ?>
                    <p><strong>ظرفیت توان:</strong> <?= e($assetData['power_capacity']) ?> کیلووات</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- اطلاعات اضافی -->
    <?php if (!empty($assetData['engine_model']) || !empty($assetData['engine_serial']) || !empty($assetData['power_capacity'])): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-cog"></i> اطلاعات فنی</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <?php if (!empty($assetData['engine_model'])): ?>
                    <p><strong>مدل موتور:</strong> <?= e($assetData['engine_model']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($assetData['engine_serial'])): ?>
                    <p><strong>سریال موتور:</strong> <?= e($assetData['engine_serial']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($assetData['power_capacity'])): ?>
                    <p><strong>ظرفیت توان:</strong> <?= e($assetData['power_capacity']) ?> کیلووات</p>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <?php if (!empty($assetData['oil_capacity'])): ?>
                    <p><strong>ظرفیت روغن:</strong> <?= e($assetData['oil_capacity']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($assetData['radiator_capacity'])): ?>
                    <p><strong>ظرفیت رادیاتور:</strong> <?= e($assetData['radiator_capacity']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($assetData['battery'])): ?>
                    <p><strong>باتری:</strong> <?= e($assetData['battery']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- توضیحات -->
    <?php if (!empty($assetData['description'])): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-file-text"></i> توضیحات</h5>
        </div>
        <div class="card-body">
            <p><?= nl2br(e($assetData['description'])) ?></p>
        </div>
    </div>
    <?php endif; ?>

    <!-- تب‌ها برای نمایش اطلاعات -->
    <ul class="nav nav-tabs" id="assetTabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#services" role="tab">
                <i class="fas fa-wrench"></i> سرویس‌ها (<?= count($services) ?>)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tasks" role="tab">
                <i class="fas fa-tasks"></i> تسک‌ها (<?= count($tasks) ?>)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#correspondence" role="tab">
                <i class="fas fa-envelope"></i> مکاتبات (<?= count($correspondence) ?>)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#assignments" role="tab">
                <i class="fas fa-handshake"></i> انتساب‌ها (<?= count($assignments) ?>)
            </a>
        </li>
    </ul>

    <div class="tab-content">
        <!-- تب سرویس‌ها -->
        <div class="tab-pane fade show active" id="services" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5>سرویس‌های انجام شده</h5>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addServiceModal">
                    <i class="fas fa-plus"></i> ثبت سرویس جدید
                </button>
            </div>
            
            <?php if (!empty($services)): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>تاریخ سرویس</th>
                                <th>نوع سرویس</th>
                                <th>مجری</th>
                                <th>خلاصه</th>
                                <th>هزینه</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($services as $service): ?>
                            <tr>
                                <td><?= e(gregorianToJalaliFromDB($service['service_date'] ?? '')) ?></td>
                                <td><?= e($service['service_type'] ?? '-') ?></td>
                                <td><?= e($service['performed_by'] ?? '-') ?></td>
                                <td><?= e($service['summary'] ?? '-') ?></td>
                                <td>
                                    <?php if ($service['cost']): ?>
                                        <?= number_format($service['cost'], 0) ?> تومان
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> هیچ سرویسی برای این دستگاه ثبت نشده است.
                </div>
            <?php endif; ?>
        </div>

        <!-- تب تسک‌ها -->
        <div class="tab-pane fade" id="tasks" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5>تسک‌های نگهداری</h5>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addTaskModal">
                    <i class="fas fa-plus"></i> ثبت تسک جدید
                </button>
            </div>
            
            <?php if (!empty($tasks)): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>عنوان</th>
                                <th>مسئول</th>
                                <th>تاریخ برنامه</th>
                                <th>وضعیت</th>
                                <th>توضیحات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tasks as $task): ?>
                            <tr>
                                <td><?= e($task['title'] ?? '-') ?></td>
                                <td><?= e($task['assigned_to'] ?? '-') ?></td>
                                <td><?= e(gregorianToJalaliFromDB($task['planned_date'] ?? '')) ?></td>
                                <td>
                                    <?php
                                    $statusClass = match($task['status']) {
                                        'completed' => 'success',
                                        'in_progress' => 'warning',
                                        'cancelled' => 'danger',
                                        default => 'secondary'
                                    };
                                    $statusText = match($task['status']) {
                                        'pending' => 'در انتظار',
                                        'in_progress' => 'در حال انجام',
                                        'completed' => 'انجام شده',
                                        'cancelled' => 'لغو شده',
                                        default => 'نامشخص'
                                    };
                                    ?>
                                    <span class="badge bg-<?= $statusClass ?> status-badge">
                                        <?= $statusText ?>
                                    </span>
                                </td>
                                <td><?= e($task['description'] ?? '-') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> هیچ تسکی برای این دستگاه ثبت نشده است.
                </div>
            <?php endif; ?>
        </div>

        <!-- تب مکاتبات -->
        <div class="tab-pane fade" id="correspondence" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5>مکاتبات</h5>
                <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#addCorrespondenceModal">
                    <i class="fas fa-plus"></i> ثبت مکاتبه جدید
                </button>
            </div>
            
            <?php if (!empty($correspondence)): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>تاریخ</th>
                                <th>موضوع</th>
                                <th>یادداشت</th>
                                <th>فایل</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($correspondence as $corr): ?>
                            <tr>
                                <td><?= e(gregorianToJalaliFromDB($corr['letter_date'] ?? '')) ?></td>
                                <td><?= e($corr['subject'] ?? '-') ?></td>
                                <td><?= e($corr['notes'] ?? '-') ?></td>
                                <td>
                                    <?php if ($corr['file_path']): ?>
                                        <a href="<?= e($corr['file_path']) ?>" class="file-link" target="_blank">
                                            <i class="fas fa-download"></i> دانلود
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> هیچ مکاتبه‌ای برای این دستگاه ثبت نشده است.
                </div>
            <?php endif; ?>
        </div>

        <!-- تب انتساب‌ها -->
        <div class="tab-pane fade" id="assignments" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5>انتساب‌های دستگاه</h5>
                <a href="assignments.php" class="btn btn-warning">
                    <i class="fas fa-plus"></i> انتساب جدید
                </a>
            </div>
            
            <?php if (!empty($assignments)): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>مشتری</th>
                                <th>تلفن</th>
                                <th>تاریخ انتساب</th>
                                <th>تاریخ نصب</th>
                                <th>گارانتی از</th>
                                <th>گارانتی تا</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assignments as $assignment): ?>
                            <tr>
                                <td><?= e($assignment['customer_name'] ?? '-') ?></td>
                                <td><?= e($assignment['customer_phone'] ?? '-') ?></td>
                                <td><?= e(gregorianToJalaliFromDB($assignment['assignment_date'] ?? '')) ?></td>
                                <td><?= e(gregorianToJalaliFromDB($assignment['installation_date'] ?? '')) ?></td>
                                <td><?= e(gregorianToJalaliFromDB($assignment['warranty_start_date'] ?? '')) ?></td>
                                <td><?= e(gregorianToJalaliFromDB($assignment['warranty_end_date'] ?? '')) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> این دستگاه به هیچ مشتری انتساب داده نشده است.
                </div>
            <?php endif; ?>
        </div>
    </div>
    <!-- Modal: editAsset -->
    <div class="modal fade" id="editAssetModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <form method="post" id="editAssetForm">
                    <input type="hidden" name="asset_id" value="<?= e($assetId) ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">ویرایش اطلاعات دستگاه</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">نام دستگاه *</label>
                                    <input type="text" class="form-control" name="name" value="<?= e($assetData['name'] ?? '') ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">برند</label>
                                    <input type="text" class="form-control" name="brand" value="<?= e($assetData['brand'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">مدل</label>
                                    <input type="text" class="form-control" name="model" value="<?= e($assetData['model'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">شماره سریال</label>
                                    <input type="text" class="form-control" name="serial_number" value="<?= e($assetData['serial_number'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">تاریخ خرید</label>
                                    <input type="text" class="form-control jalali-date" name="purchase_date" value="<?= e(gregorianToJalaliFromDB($assetData['purchase_date'] ?? '')) ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">وضعیت *</label>
                                    <select class="form-select" name="status" required>
                                        <option value="">-- انتخاب کنید --</option>
                                        <option value="فعال" <?= ($assetData['status'] ?? '') === 'فعال' ? 'selected' : '' ?>>فعال</option>
                                        <option value="غیرفعال" <?= ($assetData['status'] ?? '') === 'غیرفعال' ? 'selected' : '' ?>>غیرفعال</option>
                                        <option value="در حال تعمیر" <?= ($assetData['status'] ?? '') === 'در حال تعمیر' ? 'selected' : '' ?>>در حال تعمیر</option>
                                        <option value="آماده بهره‌برداری" <?= ($assetData['status'] ?? '') === 'آماده بهره‌برداری' ? 'selected' : '' ?>>آماده بهره‌برداری</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">توضیحات</label>
                            <textarea class="form-control" name="description" rows="3"><?= e($assetData['description'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" name="edit_asset" class="btn btn-warning">ذخیره تغییرات</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Modal: addService -->
    <div class="modal fade" id="addServiceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="asset_id" value="<?= e($assetId) ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">ثبت سرویس جدید</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">تاریخ سرویس</label>
                            <input type="text" name="service_date" class="form-control jalali-date" value="<?= jalaliDate() ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">نوع سرویس</label>
                            <input type="text" name="service_type" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">مجری سرویس</label>
                            <input type="text" name="service_provider" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">خلاصه سرویس</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">هزینه (تومان)</label>
                            <input type="number" name="cost" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" name="add_service" class="btn btn-primary">ثبت سرویس</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: addTask -->
    <div class="modal fade" id="addTaskModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="asset_id" value="<?= e($assetId) ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">ثبت تسک جدید</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">عنوان تسک *</label>
                            <input type="text" name="task_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">مسئول</label>
                            <input type="text" name="assigned_to" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">تاریخ برنامه</label>
                            <input type="text" name="due_date" class="form-control jalali-date" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">وضعیت</label>
                            <select name="status" class="form-control">
                                <option value="pending">در انتظار</option>
                                <option value="in_progress">در حال انجام</option>
                                <option value="completed">انجام شده</option>
                                <option value="cancelled">لغو شده</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">توضیحات</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" name="add_task" class="btn btn-success">ثبت تسک</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: addCorrespondence -->
    <div class="modal fade" id="addCorrespondenceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="asset_id" value="<?= e($assetId) ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">ثبت مکاتبه جدید</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">تاریخ نامه</label>
                            <input type="text" name="corr_date" class="form-control jalali-date" value="<?= jalaliDate() ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">موضوع</label>
                            <input type="text" name="summary" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">یادداشت</label>
                            <textarea name="notes" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">فایل ضمیمه</label>
                            <input type="file" name="corr_file" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" name="add_correspondence" class="btn btn-info">ثبت مکاتبه</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<?php else: ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle"></i> دارایی مورد نظر یافت نشد.
    </div>
<?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery for Persian DatePicker -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <!-- Persian DatePicker JS -->
    <script src="https://cdn.jsdelivr.net/npm/persian-date@1.1.0/dist/persian-date.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js"></script>
    
    <script>
        // Function to open edit modal
        function openEditModal() {
            console.log('openEditModal called');
            try {
                const modalElement = document.getElementById('editAssetModal');
                if (modalElement) {
                    const modal = new bootstrap.Modal(modalElement);
                    modal.show();
                    console.log('Modal shown successfully');
                } else {
                    console.error('Modal element not found');
                }
            } catch (e) {
                console.error('Error in openEditModal:', e);
                alert('خطا در باز کردن فرم ویرایش: ' + e.message);
            }
        }
        
        // Initialize Persian DatePickers on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Persian DatePickers
            try {
                $('.jalali-date').persianDatepicker({
                    format: 'YYYY/MM/DD',
                    timePicker: false,
                    autoClose: true,
                    initialValue: false,
                    position: 'auto',
                    viewMode: 'day',
                    calendar: {
                        persian: {
                            locale: 'fa',
                            showHint: true,
                            leapYearMode: 'algorithmic'
                        }
                    }
                });
                console.log('Persian DatePickers initialized successfully');
            } catch (error) {
                console.error('Error initializing Persian DatePickers:', error);
            }
        });
    </script>
</body>
</html>
