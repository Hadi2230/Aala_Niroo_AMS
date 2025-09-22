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
    <title><?= $assetId > 0 ? 'پروفایل دارایی ' . e($assetData['name'] ?? '') : 'لیست دارایی‌ها' ?></title>
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

<?php if ($assetId > 0): ?>
    <?php if ($assetData): ?>
        <!-- هدر پروفایل -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">پروفایل دستگاه</h2>
                    <?php
                    // نمایش نام مناسب بر اساس نوع دارایی
                    $display_name = $assetData['name'] ?? 'بدون نام';
                    $asset_type_name = $assetData['asset_type_name'] ?? '';
                    
                    if ($asset_type_name === 'generator' && !empty($assetData['device_identifier'])) {
                        $display_name = $assetData['device_identifier'];
                    } elseif ($asset_type_name === 'power_motor' && !empty($assetData['serial_number'])) {
                        $display_name = $assetData['serial_number'];
                    } elseif ($asset_type_name === 'consumable' && !empty($assetData['device_identifier'])) {
                        $display_name = $assetData['device_identifier'];
                    } elseif ($asset_type_name === 'parts' && !empty($assetData['device_identifier'])) {
                        $display_name = $assetData['device_identifier'];
                    }
                    ?>
                    <h4 class="text-primary mb-0"><?= e($display_name) ?></h4>
                    <?php if ($asset_type_name === 'generator' && !empty($assetData['device_identifier'])): ?>
                        <small class="text-muted">نام: <?= e($assetData['name'] ?? 'بدون نام') ?></small>
                    <?php endif; ?>
                </div>
            <div>
                <a href="profile.php" class="btn btn-outline-secondary">
                    <i class="fas fa-list"></i> لیست همه دارایی‌ها
                </a>
                <a href="assets.php" class="btn btn-outline-primary">
                    <i class="fas fa-cog"></i> مدیریت دارایی‌ها
                </a>
                <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#editAssetModal">
                    <i class="fas fa-edit"></i> ویرایش دستگاه
                </button>
            </div>
        </div>

        <!-- اطلاعات کلی دارایی -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-info-circle"></i> اطلاعات کامل دستگاه</h5>
            </div>
            <div class="card-body">
                <!-- اطلاعات اصلی -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h6 class="text-primary border-bottom pb-2 mb-3">اطلاعات اصلی</h6>
                    </div>
                    <div class="col-md-6">
                        <p><strong>نام دستگاه:</strong> <?= e($assetData['name'] ?? '-') ?></p>
                        <p><strong>برند:</strong> <?= e($assetData['brand'] ?? '-') ?></p>
                        <p><strong>مدل:</strong> <?= e($assetData['model'] ?? '-') ?></p>
                        <p><strong>سریال:</strong> <?= e($assetData['serial_number'] ?? '-') ?></p>
                        <?php if (!empty($assetData['device_identifier'])): ?>
                        <p><strong>شناسه دستگاه:</strong> <span class="text-primary fw-bold"><?= e($assetData['device_identifier']) ?></span></p>
                        <?php endif; ?>
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
                        <?php if (!empty($assetData['engine_type'])): ?>
                        <p><strong>نوع موتور:</strong> <?= e($assetData['engine_type']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($assetData['consumable_type'])): ?>
                        <p><strong>نوع کالای مصرفی:</strong> <?= e($assetData['consumable_type']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- اطلاعات موتور -->
                <?php if (!empty($assetData['engine_model']) || !empty($assetData['engine_serial']) || !empty($assetData['oil_capacity']) || !empty($assetData['radiator_capacity'])): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <h6 class="text-primary border-bottom pb-2 mb-3">اطلاعات موتور</h6>
                    </div>
                    <div class="col-md-6">
                        <?php if (!empty($assetData['engine_model'])): ?>
                        <p><strong>مدل موتور:</strong> <?= e($assetData['engine_model']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($assetData['engine_serial'])): ?>
                        <p><strong>سریال موتور:</strong> <?= e($assetData['engine_serial']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($assetData['oil_capacity'])): ?>
                        <p><strong>ظرفیت روغن:</strong> <?= e($assetData['oil_capacity']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($assetData['radiator_capacity'])): ?>
                        <p><strong>ظرفیت رادیاتور:</strong> <?= e($assetData['radiator_capacity']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <?php if (!empty($assetData['antifreeze'])): ?>
                        <p><strong>ضدیخ:</strong> <?= e($assetData['antifreeze']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($assetData['battery'])): ?>
                        <p><strong>باتری:</strong> <?= e($assetData['battery']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($assetData['battery_charger'])): ?>
                        <p><strong>شارژر باتری:</strong> <?= e($assetData['battery_charger']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($assetData['heater'])): ?>
                        <p><strong>گرمکن:</strong> <?= e($assetData['heater']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- اطلاعات آلترناتور -->
                <?php if (!empty($assetData['alternator_model']) || !empty($assetData['alternator_serial']) || !empty($assetData['device_model']) || !empty($assetData['device_serial'])): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <h6 class="text-primary border-bottom pb-2 mb-3">اطلاعات آلترناتور</h6>
                    </div>
                    <div class="col-md-6">
                        <?php if (!empty($assetData['alternator_model'])): ?>
                        <p><strong>مدل آلترناتور:</strong> <?= e($assetData['alternator_model']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($assetData['alternator_serial'])): ?>
                        <p><strong>سریال آلترناتور:</strong> <?= e($assetData['alternator_serial']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($assetData['device_model'])): ?>
                        <p><strong>مدل دستگاه:</strong> <?= e($assetData['device_model']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($assetData['device_serial'])): ?>
                        <p><strong>سریال دستگاه:</strong> <?= e($assetData['device_serial']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <?php if (!empty($assetData['control_panel_model'])): ?>
                        <p><strong>مدل کنترل پنل:</strong> <?= e($assetData['control_panel_model']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($assetData['breaker_model'])): ?>
                        <p><strong>مدل بریکر:</strong> <?= e($assetData['breaker_model']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- فیلترها -->
                <?php if (!empty($assetData['oil_filter_part']) || !empty($assetData['fuel_filter_part']) || !empty($assetData['air_filter_part']) || !empty($assetData['water_filter_part'])): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <h6 class="text-primary border-bottom pb-2 mb-3">فیلترها</h6>
                    </div>
                    <div class="col-md-6">
                        <?php if (!empty($assetData['oil_filter_part'])): ?>
                        <p><strong>پارت نامبر فیلتر روغن:</strong> <?= e($assetData['oil_filter_part']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($assetData['fuel_filter_part'])): ?>
                        <p><strong>پارت نامبر فیلتر سوخت:</strong> <?= e($assetData['fuel_filter_part']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <?php if (!empty($assetData['air_filter_part'])): ?>
                        <p><strong>پارت نامبر فیلتر هوا:</strong> <?= e($assetData['air_filter_part']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($assetData['water_filter_part'])): ?>
                        <p><strong>پارت نامبر فیلتر آب:</strong> <?= e($assetData['water_filter_part']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($assetData['water_fuel_filter_part'])): ?>
                        <p><strong>پارت نامبر فیلتر سوخت آبیگیر:</strong> <?= e($assetData['water_fuel_filter_part']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- اطلاعات کارگاه -->
                <?php if (!empty($assetData['workshop_entry_date']) || !empty($assetData['workshop_exit_date'])): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <h6 class="text-primary border-bottom pb-2 mb-3">اطلاعات کارگاه</h6>
                    </div>
                    <div class="col-md-6">
                        <?php if (!empty($assetData['workshop_entry_date'])): ?>
                        <p><strong>تاریخ ورود به کارگاه:</strong> <?= e(gregorianToJalaliFromDB($assetData['workshop_entry_date'])) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <?php if (!empty($assetData['workshop_exit_date'])): ?>
                        <p><strong>تاریخ خروج از کارگاه:</strong> <?= e(gregorianToJalaliFromDB($assetData['workshop_exit_date'])) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- لینک‌های manual -->
                <?php if (!empty($assetData['datasheet_link']) || !empty($assetData['engine_manual_link']) || !empty($assetData['alternator_manual_link']) || !empty($assetData['control_panel_manual_link'])): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <h6 class="text-primary border-bottom pb-2 mb-3">لینک‌های مفید</h6>
                    </div>
                    <div class="col-md-6">
                        <?php if (!empty($assetData['datasheet_link'])): ?>
                        <p><strong>دیتاشیت:</strong> <a href="<?= e($assetData['datasheet_link']) ?>" target="_blank" class="text-primary">مشاهده</a></p>
                        <?php endif; ?>
                        <?php if (!empty($assetData['engine_manual_link'])): ?>
                        <p><strong>Manual موتور:</strong> <a href="<?= e($assetData['engine_manual_link']) ?>" target="_blank" class="text-primary">مشاهده</a></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <?php if (!empty($assetData['alternator_manual_link'])): ?>
                        <p><strong>Manual آلترناتور:</strong> <a href="<?= e($assetData['alternator_manual_link']) ?>" target="_blank" class="text-primary">مشاهده</a></p>
                        <?php endif; ?>
                        <?php if (!empty($assetData['control_panel_manual_link'])): ?>
                        <p><strong>Manual کنترل پنل:</strong> <a href="<?= e($assetData['control_panel_manual_link']) ?>" target="_blank" class="text-primary">مشاهده</a></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- اطلاعات اضافی -->
                <?php if (!empty($assetData['fuel_tank_specs']) || !empty($assetData['other_items']) || !empty($assetData['supply_method']) || !empty($assetData['location']) || !empty($assetData['quantity']) || !empty($assetData['supplier_name'])): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <h6 class="text-primary border-bottom pb-2 mb-3">اطلاعات اضافی</h6>
                    </div>
                    <div class="col-md-6">
                        <?php if (!empty($assetData['fuel_tank_specs'])): ?>
                        <p><strong>مشخصات تانک سوخت:</strong> <?= nl2br(e($assetData['fuel_tank_specs'])) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($assetData['other_items'])): ?>
                        <p><strong>سایر اقلام:</strong> <?= nl2br(e($assetData['other_items'])) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($assetData['supply_method'])): ?>
                        <p><strong>نحوه تأمین:</strong> <?= e($assetData['supply_method']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <?php if (!empty($assetData['location'])): ?>
                        <p><strong>مکان:</strong> <?= e($assetData['location']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($assetData['quantity'])): ?>
                        <p><strong>تعداد:</strong> <?= e($assetData['quantity']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($assetData['supplier_name'])): ?>
                        <p><strong>تأمین‌کننده:</strong> <?= e($assetData['supplier_name']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($assetData['supplier_contact'])): ?>
                        <p><strong>تماس تأمین‌کننده:</strong> <?= e($assetData['supplier_contact']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- توضیحات -->
                <?php if (!empty($assetData['description'])): ?>
                <div class="row">
                    <div class="col-12">
                        <h6 class="text-primary border-bottom pb-2 mb-3">توضیحات</h6>
                        <p><?= nl2br(e($assetData['description'])) ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

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
                        <input type="hidden" id="edit_asset_type" value="">
                        <div class="modal-header">
                            <h5 class="modal-title">ویرایش اطلاعات دستگاه</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            
                            <!-- فیلدهای ژنراتور -->
                            <div id="edit_generator_fields" class="edit-dynamic-field" style="display: none;">
                                <h5 class="mb-3 text-secondary">مشخصات ژنراتور</h5>
                                
                                <!-- ردیف 1 -->
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">نام دستگاه *</label>
                                            <select class="form-select" id="edit_gen_name" name="name" required>
                                                <option value="">-- انتخاب کنید --</option>
                                                <option value="Cummins">Cummins</option>
                                                <option value="Volvo">Volvo</option>
                                                <option value="Perkins">Perkins</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">شماره سریال دستگاه</label>
                                            <input type="text" class="form-control" id="edit_gen_serial_number" name="serial_number">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">تاریخ خرید</label>
                                            <input type="text" class="form-control jalali-date" id="edit_gen_purchase_date" name="purchase_date" readonly>
                                        </div>
                                    </div>
                                </div>

                                <!-- ردیف 2 -->
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">وضعیت *</label>
                                            <select class="form-select" id="edit_gen_status" name="status" required>
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
                                            <input type="text" class="form-control" id="edit_gen_brand" name="brand">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">مدل دستگاه</label>
                                            <input type="text" class="form-control" id="edit_gen_device_model" name="device_model">
                                        </div>
                                    </div>
                                </div>

                                <!-- ردیف 3 -->
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">ظرفیت توان (کیلووات)</label>
                                            <input type="text" class="form-control" id="edit_gen_power_capacity" name="power_capacity">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">مدل موتور</label>
                                            <input type="text" class="form-control" id="edit_gen_engine_model" name="engine_model">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">سریال موتور</label>
                                            <input type="text" class="form-control" id="edit_gen_engine_serial" name="engine_serial">
                                        </div>
                                    </div>
                                </div>

                                <!-- ردیف 4 -->
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">مدل آلترناتور</label>
                                            <input type="text" class="form-control" id="edit_gen_alternator_model" name="alternator_model">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">سریال آلترناتور</label>
                                            <input type="text" class="form-control" id="edit_gen_alternator_serial" name="alternator_serial">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">سریال دستگاه</label>
                                            <input type="text" class="form-control" id="edit_gen_device_serial" name="device_serial">
                                        </div>
                                    </div>
                                </div>

                                <!-- ردیف 5 -->
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">مدل کنترل پنل</label>
                                            <input type="text" class="form-control" id="edit_gen_control_panel_model" name="control_panel_model">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">مدل بریکر</label>
                                            <input type="text" class="form-control" id="edit_gen_breaker_model" name="breaker_model">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">باتری</label>
                                            <input type="text" class="form-control" id="edit_gen_battery" name="battery">
                                        </div>
                                    </div>
                                </div>

                                <!-- ردیف 6 -->
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">حجم روغن</label>
                                            <input type="text" class="form-control" id="edit_gen_oil_capacity" name="oil_capacity">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">حجم آب رادیاتور</label>
                                            <input type="text" class="form-control" id="edit_gen_radiator_capacity" name="radiator_capacity">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">ضدیخ</label>
                                            <input type="text" class="form-control" id="edit_gen_antifreeze" name="antifreeze">
                                        </div>
                                    </div>
                                </div>

                                <!-- فیلترها -->
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">پارت نامبر فیلتر روغن</label>
                                            <input type="text" class="form-control" id="edit_gen_oil_filter_part" name="oil_filter_part">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">پارت نامبر فیلتر سوخت</label>
                                            <input type="text" class="form-control" id="edit_gen_fuel_filter_part" name="fuel_filter_part">
                                        </div>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">پارت نامبر فیلتر سوخت آبیگیر</label>
                                            <input type="text" class="form-control" id="edit_gen_water_fuel_filter_part" name="water_fuel_filter_part">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">پارت نامبر فیلتر هوا</label>
                                            <input type="text" class="form-control" id="edit_gen_air_filter_part" name="air_filter_part">
                                        </div>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">پارت نامبر فیلتر آب</label>
                                            <input type="text" class="form-control" id="edit_gen_water_filter_part" name="water_filter_part">
                                        </div>
                                    </div>
                                </div>

                                <!-- تاریخ‌های کارگاه -->
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">تاریخ ورود به کارگاه</label>
                                            <input type="text" class="form-control jalali-date" id="edit_gen_workshop_entry_date" name="workshop_entry_date" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">تاریخ خروج از کارگاه</label>
                                            <input type="text" class="form-control jalali-date" id="edit_gen_workshop_exit_date" name="workshop_exit_date" readonly>
                                        </div>
                                    </div>
                                </div>

                                <!-- لینک‌های manual -->
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">لینک دیتاشیت</label>
                                            <input type="text" class="form-control" id="edit_gen_datasheet_link" name="datasheet_link">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">لینک manual موتور</label>
                                            <input type="text" class="form-control" id="edit_gen_engine_manual_link" name="engine_manual_link">
                                        </div>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">لینک manual آلترناتور</label>
                                            <input type="text" class="form-control" id="edit_gen_alternator_manual_link" name="alternator_manual_link">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">لینک manual کنترل پنل</label>
                                            <input type="text" class="form-control" id="edit_gen_control_panel_manual_link" name="control_panel_manual_link">
                                        </div>
                                    </div>
                                </div>

                                <!-- توضیحات -->
                                <div class="mb-3">
                                    <label class="form-label">توضیحات</label>
                                    <textarea class="form-control" id="edit_gen_description" name="description" rows="4"></textarea>
                                </div>
                            </div>

                            <!-- فیلدهای موتور برق -->
                            <div id="edit_motor_fields" class="edit-dynamic-field" style="display: none;">
                                <h5 class="mb-3 text-secondary">مشخصات موتور برق</h5>
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">نام موتور برق *</label>
                                            <select class="form-select" id="edit_motor_name" name="name" required>
                                                <option value="">-- انتخاب کنید --</option>
                                                <option value="Cummins">Cummins</option>
                                                <option value="Volvo">Volvo</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">نوع موتور *</label>
                                            <select class="form-select" id="edit_motor_engine_type" name="engine_type" required>
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
                                            <label class="form-label">سریال موتور</label>
                                            <input type="text" class="form-control" id="edit_motor_serial_number" name="serial_number">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">شناسه دستگاه</label>
                                            <input type="text" class="form-control" id="edit_motor_device_identifier" name="device_identifier">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">تاریخ خرید</label>
                                            <input type="text" class="form-control jalali-date" id="edit_motor_purchase_date" name="purchase_date" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">وضعیت *</label>
                                            <select class="form-select" id="edit_motor_status" name="status" required>
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
                            <div id="edit_consumable_fields" class="edit-dynamic-field" style="display: none;">
                                <h5 class="mb-3 text-secondary">مشخصات اقلام مصرفی</h5>
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">نام کالا *</label>
                                            <input type="text" class="form-control" id="edit_consumable_name" name="name" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">تاریخ ثبت</label>
                                            <input type="text" class="form-control jalali-date" id="edit_consumable_purchase_date" name="purchase_date" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">شناسه دستگاه</label>
                                            <input type="text" class="form-control" id="edit_consumable_device_identifier" name="device_identifier">
                                        </div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">وضعیت *</label>
                                            <select class="form-select" id="edit_consumable_status" name="status" required>
                                                <option value="">-- انتخاب کنید --</option>
                                                <option value="فعال">فعال</option>
                                                <option value="غیرفعال">غیرفعال</option>
                                                <option value="در حال تعمیر">در حال تعمیر</option>
                                                <option value="آماده بهره‌برداری">آماده بهره‌برداری</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">نوع کالای مصرفی *</label>
                                            <input type="text" class="form-control" id="edit_consumable_type" name="consumable_type" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">پارت نامبر</label>
                                            <input type="text" class="form-control" id="edit_consumable_part" name="oil_filter_part">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- فیلدهای قطعات -->
                            <div id="edit_parts_fields" class="edit-dynamic-field" style="display: none;">
                                <h5 class="mb-3 text-secondary">مشخصات قطعات</h5>
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">نام قطعه *</label>
                                            <input type="text" class="form-control" id="edit_parts_name" name="name" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">شماره سریال</label>
                                            <input type="text" class="form-control" id="edit_parts_serial_number" name="serial_number">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">شناسه دستگاه</label>
                                            <input type="text" class="form-control" id="edit_parts_device_identifier" name="device_identifier">
                                        </div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">تاریخ ثبت</label>
                                            <input type="text" class="form-control jalali-date" id="edit_parts_purchase_date" name="purchase_date" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">وضعیت *</label>
                                            <select class="form-select" id="edit_parts_status" name="status" required>
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
                                            <textarea class="form-control" id="edit_parts_description" name="description" rows="3"></textarea>
                                        </div>
                                    </div>
                                </div>
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

<?php else: ?>
    <!-- لیست همه دارایی‌ها -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-server"></i> لیست دارایی‌ها</h2>
        <a href="assets.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> افزودن دارایی جدید
        </a>
    </div>

    <?php if (!empty($allAssets)): ?>
        <div class="row">
            <?php foreach ($allAssets as $asset): ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0"><?= e($asset['name'] ?? 'بدون نام') ?></h5>
                    </div>
                    <div class="card-body">
                        <p><strong>برند:</strong> <?= e($asset['brand'] ?? '-') ?></p>
                        <p><strong>مدل:</strong> <?= e($asset['model'] ?? '-') ?></p>
                        <p><strong>وضعیت:</strong> 
                            <span class="badge bg-<?= ($asset['status'] ?? '') === 'فعال' ? 'success' : 'warning' ?>">
                                <?= e($asset['status'] ?? 'نامشخص') ?>
                            </span>
                        </p>
                    </div>
                    <div class="card-footer">
                        <a href="profile.php?id=<?= e($asset['id']) ?>" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-eye"></i> مشاهده پروفایل
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> فعلاً هیچ دارایی‌ای ثبت نشده است.
        </div>
    <?php endif; ?>
<?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery for Persian DatePicker -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <!-- Persian DatePicker JS -->
    <script src="https://cdn.jsdelivr.net/npm/persian-date@1.1.0/dist/persian-date.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js"></script>
    
    <!-- JavaScript for Edit Asset Modal -->
    <script>
        // Function to show edit modal with appropriate fields based on asset type
        function showEditModal(assetData) {
            // Hide all field sections first
            document.querySelectorAll('.edit-dynamic-field').forEach(field => {
                field.style.display = 'none';
            });
            
            // Determine asset type from asset_types table
            const assetTypeId = assetData.type_id;
            let assetTypeName = '';
            
            // Map asset type ID to name (you might need to adjust these based on your database)
            switch(assetTypeId) {
                case '1': // Generator
                case 1:
                    assetTypeName = 'ژنراتور';
                    break;
                case '2': // Power Motor
                case 2:
                    assetTypeName = 'موتور برق';
                    break;
                case '3': // Consumables
                case 3:
                    assetTypeName = 'اقلام مصرفی';
                    break;
                case '4': // Parts
                case 4:
                    assetTypeName = 'قطعات';
                    break;
                default:
                    // Try to determine from asset name or other fields
                    if (assetData.power_capacity || assetData.engine_model || assetData.alternator_model) {
                        assetTypeName = 'ژنراتور';
                    } else if (assetData.engine_type) {
                        assetTypeName = 'موتور برق';
                    } else if (assetData.consumable_type) {
                        assetTypeName = 'اقلام مصرفی';
                    } else {
                        assetTypeName = 'قطعات';
                    }
            }
            
            // Store asset type for form submission
            document.getElementById('edit_asset_type').value = assetTypeName;
            
            // Show appropriate fields based on asset type
            if (assetTypeName.includes('ژنراتور')) {
                showGeneratorEditFields(assetData);
            } else if (assetTypeName.includes('موتور برق')) {
                showMotorEditFields(assetData);
            } else if (assetTypeName.includes('مصرفی')) {
                showConsumableEditFields(assetData);
            } else if (assetTypeName.includes('قطعات')) {
                showPartsEditFields(assetData);
            }
            
            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('editAssetModal'));
            modal.show();
        }
        
        // Function to populate generator fields
        function showGeneratorEditFields(assetData) {
            document.getElementById('edit_generator_fields').style.display = 'block';
            
            // Populate fields
            setValue('edit_gen_name', assetData.name);
            setValue('edit_gen_serial_number', assetData.serial_number);
            setValue('edit_gen_purchase_date', convertToJalali(assetData.purchase_date));
            setValue('edit_gen_status', assetData.status);
            setValue('edit_gen_brand', assetData.brand);
            setValue('edit_gen_device_model', assetData.device_model);
            setValue('edit_gen_power_capacity', assetData.power_capacity);
            setValue('edit_gen_engine_model', assetData.engine_model);
            setValue('edit_gen_engine_serial', assetData.engine_serial);
            setValue('edit_gen_alternator_model', assetData.alternator_model);
            setValue('edit_gen_alternator_serial', assetData.alternator_serial);
            setValue('edit_gen_device_serial', assetData.device_serial);
            setValue('edit_gen_control_panel_model', assetData.control_panel_model);
            setValue('edit_gen_breaker_model', assetData.breaker_model);
            setValue('edit_gen_battery', assetData.battery);
            setValue('edit_gen_oil_capacity', assetData.oil_capacity);
            setValue('edit_gen_radiator_capacity', assetData.radiator_capacity);
            setValue('edit_gen_antifreeze', assetData.antifreeze);
            setValue('edit_gen_oil_filter_part', assetData.oil_filter_part);
            setValue('edit_gen_fuel_filter_part', assetData.fuel_filter_part);
            setValue('edit_gen_water_fuel_filter_part', assetData.water_fuel_filter_part);
            setValue('edit_gen_air_filter_part', assetData.air_filter_part);
            setValue('edit_gen_water_filter_part', assetData.water_filter_part);
            setValue('edit_gen_workshop_entry_date', convertToJalali(assetData.workshop_entry_date));
            setValue('edit_gen_workshop_exit_date', convertToJalali(assetData.workshop_exit_date));
            setValue('edit_gen_datasheet_link', assetData.datasheet_link);
            setValue('edit_gen_engine_manual_link', assetData.engine_manual_link);
            setValue('edit_gen_alternator_manual_link', assetData.alternator_manual_link);
            setValue('edit_gen_control_panel_manual_link', assetData.control_panel_manual_link);
            setValue('edit_gen_description', assetData.description);
        }
        
        // Function to populate motor fields
        function showMotorEditFields(assetData) {
            document.getElementById('edit_motor_fields').style.display = 'block';
            
            // Populate fields
            setValue('edit_motor_name', assetData.name);
            setValue('edit_motor_engine_type', assetData.engine_type);
            setValue('edit_motor_serial_number', assetData.serial_number);
            setValue('edit_motor_device_identifier', assetData.device_identifier);
            setValue('edit_motor_purchase_date', convertToJalali(assetData.purchase_date));
            setValue('edit_motor_status', assetData.status);
        }
        
        // Function to populate consumable fields
        function showConsumableEditFields(assetData) {
            document.getElementById('edit_consumable_fields').style.display = 'block';
            
            // Populate fields
            setValue('edit_consumable_name', assetData.name);
            setValue('edit_consumable_purchase_date', convertToJalali(assetData.purchase_date));
            setValue('edit_consumable_device_identifier', assetData.device_identifier);
            setValue('edit_consumable_status', assetData.status);
            setValue('edit_consumable_type', assetData.consumable_type);
            setValue('edit_consumable_part', assetData.oil_filter_part);
        }
        
        // Function to populate parts fields
        function showPartsEditFields(assetData) {
            document.getElementById('edit_parts_fields').style.display = 'block';
            
            // Populate fields
            setValue('edit_parts_name', assetData.name);
            setValue('edit_parts_serial_number', assetData.serial_number);
            setValue('edit_parts_device_identifier', assetData.device_identifier);
            setValue('edit_parts_purchase_date', convertToJalali(assetData.purchase_date));
            setValue('edit_parts_status', assetData.status);
            setValue('edit_parts_description', assetData.description);
        }
        
        // Helper function to convert Gregorian date to Jalali
        function convertToJalali(gregorianDate) {
            if (!gregorianDate || gregorianDate === '') return '';
            
            // Simple conversion - you might want to use a more robust library
            try {
                const date = new Date(gregorianDate);
                if (isNaN(date.getTime())) return '';
                
                // Basic Jalali conversion (simplified)
                const year = date.getFullYear();
                const month = date.getMonth() + 1;
                const day = date.getDate();
                
                // Convert to Jalali (simplified)
                const jalaliYear = year - 621;
                const jalaliMonth = month > 3 ? month - 3 : month + 9;
                const jalaliDay = day;
                
                return jalaliYear + '/' + jalaliMonth.toString().padStart(2, '0') + '/' + jalaliDay.toString().padStart(2, '0');
            } catch (e) {
                return '';
            }
        }
        
        // Helper function to set value safely
        function setValue(elementId, value) {
            const element = document.getElementById(elementId);
            if (element && value !== null && value !== undefined) {
                element.value = value;
            }
        }
        
        // Initialize Persian DatePicker for all jalali-date inputs
        function initializePersianDatePickers() {
            $('.jalali-date').persianDatepicker({
                format: 'YYYY/MM/DD',
                altField: '.jalali-date',
                altFormat: 'YYYY/MM/DD',
                observer: true,
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
        }
        
        // Add event listener to edit button
        <?php if ($assetData): ?>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Persian DatePickers
            initializePersianDatePickers();
            
            const editButton = document.querySelector('[data-bs-target="#editAssetModal"]');
            if (editButton) {
                editButton.addEventListener('click', function() {
                    const assetData = <?= json_encode($assetData, JSON_UNESCAPED_UNICODE) ?>;
                    showEditModal(assetData);
                });
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>

<!-- Additional functionality and enhancements -->
<script>
// Enhanced JavaScript functionality for better user experience
document.addEventListener('DOMContentLoaded', function() {
    // Auto-refresh data every 30 seconds
    setInterval(function() {
        // Refresh page data without full reload
        location.reload();
    }, 30000);
    
    // Enhanced modal functionality
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.addEventListener('shown.bs.modal', function() {
            // Focus on first input when modal opens
            const firstInput = modal.querySelector('input, textarea, select');
            if (firstInput) {
                firstInput.focus();
            }
        });
    });
    
    // Enhanced table functionality
    const tables = document.querySelectorAll('table');
    tables.forEach(table => {
        // Add hover effects
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.backgroundColor = '#f8f9fa';
            });
            row.addEventListener('mouseleave', function() {
                this.style.backgroundColor = '';
            });
        });
    });
    
    // Enhanced form validation
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('لطفاً تمام فیلدهای الزامی را پر کنید.');
            }
        });
    });
    
    // Enhanced tab functionality
    const tabLinks = document.querySelectorAll('.nav-tabs .nav-link');
    tabLinks.forEach(link => {
        link.addEventListener('click', function() {
            // Add loading state
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + this.textContent.trim();
            
            setTimeout(() => {
                // Remove loading state
                const icon = this.querySelector('i');
                if (icon) {
                    icon.className = 'fas fa-wrench';
                }
            }, 500);
        });
    });
});

// Additional utility functions
function formatNumber(num) {
    return new Intl.NumberFormat('fa-IR').format(num);
}

function formatDate(date) {
    return new Intl.DateTimeFormat('fa-IR').format(new Date(date));
}

function showNotification(message, type = 'success') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i> 
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    const container = document.querySelector('.container-fluid');
    container.insertBefore(alertDiv, container.firstChild);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

// Enhanced file upload functionality
function handleFileUpload(input) {
    const file = input.files[0];
    if (file) {
        const maxSize = 10 * 1024 * 1024; // 10MB
        if (file.size > maxSize) {
            showNotification('حجم فایل نباید بیشتر از 10 مگابایت باشد.', 'danger');
            input.value = '';
            return;
        }
        
        const allowedTypes = ['image/jpeg', 'image/png', 'application/pdf', 'text/plain'];
        if (!allowedTypes.includes(file.type)) {
            showNotification('نوع فایل مجاز نیست. فقط تصاویر، PDF و فایل‌های متنی مجاز است.', 'danger');
            input.value = '';
            return;
        }
        
        showNotification('فایل با موفقیت انتخاب شد.', 'success');
    }
}

// Enhanced search functionality
function searchTable(tableId, searchInput) {
    const table = document.getElementById(tableId);
    const input = document.getElementById(searchInput);
    
    if (!table || !input) return;
    
    const rows = table.querySelectorAll('tbody tr');
    const searchTerm = input.value.toLowerCase();
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        if (text.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Enhanced export functionality
function exportTable(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    let csv = '';
    const rows = table.querySelectorAll('tr');
    
    rows.forEach(row => {
        const cells = row.querySelectorAll('td, th');
        const rowData = Array.from(cells).map(cell => cell.textContent.trim());
        csv += rowData.join(',') + '\n';
    });
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename + '.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}

// Enhanced print functionality
function printPage() {
    window.print();
}

// Enhanced responsive functionality
function handleResize() {
    const tables = document.querySelectorAll('.table-responsive');
    tables.forEach(table => {
        if (window.innerWidth < 768) {
            table.classList.add('table-sm');
        } else {
            table.classList.remove('table-sm');
        }
    });
}

window.addEventListener('resize', handleResize);
document.addEventListener('DOMContentLoaded', handleResize);
</script>

<!-- Additional CSS for enhanced functionality -->
<style>
/* Enhanced responsive design */
@media (max-width: 768px) {
    .modal-dialog {
        margin: 0.5rem;
    }
    
    .table-responsive {
        font-size: 0.875rem;
    }
    
    .btn {
        padding: 0.375rem 0.75rem;
        font-size: 0.875rem;
    }
}

/* Enhanced animations */
.fade-in {
    animation: fadeIn 0.5s ease-in;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Enhanced hover effects */
.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.btn:hover {
    transform: translateY(-1px);
    transition: all 0.3s ease;
}

/* Enhanced loading states */
.loading {
    opacity: 0.6;
    pointer-events: none;
}

.spinner {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid #f3f3f3;
    border-top: 3px solid #007bff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Enhanced form validation */
.is-invalid {
    border-color: #dc3545;
    box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
}

.is-valid {
    border-color: #28a745;
    box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
}

/* Enhanced table styling */
.table-hover tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.075);
}

.table-striped tbody tr:nth-of-type(odd) {
    background-color: rgba(0, 0, 0, 0.02);
}

/* Enhanced modal styling */
.modal-content {
    border-radius: 0.5rem;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
}

.modal-header {
    border-bottom: 1px solid #dee2e6;
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    color: white;
}

.modal-footer {
    border-top: 1px solid #dee2e6;
    background-color: #f8f9fa;
}

/* Enhanced alert styling */
.alert {
    border-radius: 0.5rem;
    border: none;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.alert-success {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
}

.alert-danger {
    background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);
    color: white;
}

.alert-info {
    background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);
    color: white;
}

.alert-warning {
    background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
    color: #212529;
}

/* Enhanced badge styling */
.badge {
    font-size: 0.75em;
    padding: 0.375rem 0.75rem;
    border-radius: 0.375rem;
}

/* Enhanced button styling */
.btn {
    border-radius: 0.375rem;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-primary {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    border: none;
}

.btn-success {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    border: none;
}

.btn-warning {
    background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
    border: none;
    color: #212529;
}

.btn-danger {
    background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);
    border: none;
}

.btn-info {
    background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);
    border: none;
}

/* Enhanced card styling */
.card {
    border-radius: 0.5rem;
    border: 1px solid #e9ecef;
    transition: all 0.3s ease;
}

.card-header {
    border-radius: 0.5rem 0.5rem 0 0;
    font-weight: 600;
}

/* Enhanced form styling */
.form-control {
    border-radius: 0.375rem;
    border: 1px solid #ced4da;
    transition: all 0.3s ease;
}

.form-control:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

.form-label {
    font-weight: 500;
    color: #495057;
}

/* Enhanced table styling */
.table {
    border-radius: 0.5rem;
    overflow: hidden;
}

.table thead th {
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
    color: #495057;
}

.table tbody td {
    vertical-align: middle;
    border-top: 1px solid #dee2e6;
}

/* Enhanced responsive utilities */
.d-print-none {
    display: none !important;
}

@media print {
    .d-print-none {
        display: none !important;
    }
    
    .d-print-block {
        display: block !important;
    }
    
    .table {
        font-size: 0.875rem;
    }
    
    .card {
        border: 1px solid #000;
        box-shadow: none;
    }
}

/* Enhanced accessibility */
.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

/* Enhanced focus indicators */
.btn:focus,
.form-control:focus,
.form-select:focus {
    outline: 2px solid #007bff;
    outline-offset: 2px;
}

/* Enhanced loading states */
.loading-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

/* Enhanced error states */
.error-message {
    color: #dc3545;
    font-size: 0.875rem;
    margin-top: 0.25rem;
}

/* Enhanced success states */
.success-message {
    color: #28a745;
    font-size: 0.875rem;
    margin-top: 0.25rem;
}

/* Enhanced info states */
.info-message {
    color: #17a2b8;
    font-size: 0.875rem;
    margin-top: 0.25rem;
}

/* Enhanced warning states */
.warning-message {
    color: #ffc107;
    font-size: 0.875rem;
    margin-top: 0.25rem;
}
</style>
