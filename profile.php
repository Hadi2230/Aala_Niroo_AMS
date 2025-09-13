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
            $name = trim($_POST['name'] ?? '');
            $brand = trim($_POST['brand'] ?? '');
            $model = trim($_POST['model'] ?? '');
            $serial_number = trim($_POST['serial_number'] ?? '');
            $purchase_date = trim($_POST['purchase_date'] ?? '');
            $status = trim($_POST['status'] ?? '');
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
            $oil_filter_part = trim($_POST['oil_filter_part'] ?? '');
            $fuel_filter_part = trim($_POST['fuel_filter_part'] ?? '');
            $water_fuel_filter_part = trim($_POST['water_fuel_filter_part'] ?? '');
            $air_filter_part = trim($_POST['air_filter_part'] ?? '');
            $water_filter_part = trim($_POST['water_filter_part'] ?? '');
            $workshop_entry_date = trim($_POST['workshop_entry_date'] ?? '');
            $workshop_exit_date = trim($_POST['workshop_exit_date'] ?? '');
            $datasheet_link = trim($_POST['datasheet_link'] ?? '');
            $engine_manual_link = trim($_POST['engine_manual_link'] ?? '');
            $alternator_manual_link = trim($_POST['alternator_manual_link'] ?? '');
            $control_panel_manual_link = trim($_POST['control_panel_manual_link'] ?? '');
            $description = trim($_POST['description'] ?? '');

            $stmt = $pdo->prepare("
                UPDATE assets SET 
                name = ?, brand = ?, model = ?, serial_number = ?, purchase_date = ?, 
                status = ?, power_capacity = ?, engine_type = ?, engine_model = ?, 
                engine_serial = ?, alternator_model = ?, alternator_serial = ?, 
                device_model = ?, device_serial = ?, control_panel_model = ?, 
                breaker_model = ?, battery = ?, battery_charger = ?, oil_capacity = ?, 
                radiator_capacity = ?, oil_filter_part = ?, fuel_filter_part = ?, 
                water_fuel_filter_part = ?, air_filter_part = ?, water_filter_part = ?, 
                workshop_entry_date = ?, workshop_exit_date = ?, datasheet_link = ?, 
                engine_manual_link = ?, alternator_manual_link = ?, 
                control_panel_manual_link = ?, description = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $name, $brand, $model, $serial_number, $purchase_date, $status, 
                $power_capacity, $engine_type, $engine_model, $engine_serial, 
                $alternator_model, $alternator_serial, $device_model, $device_serial, 
                $control_panel_model, $breaker_model, $battery, $battery_charger, 
                $oil_capacity, $radiator_capacity, $oil_filter_part, $fuel_filter_part, 
                $water_fuel_filter_part, $air_filter_part, $water_filter_part, 
                $workshop_entry_date, $workshop_exit_date, $datasheet_link, 
                $engine_manual_link, $alternator_manual_link, $control_panel_manual_link, 
                $description, $postAssetId
            ]);

            $_SESSION['success'] = 'اطلاعات دستگاه با موفقیت به‌روزرسانی شد.';
            header('Location: profile.php?id=' . $postAssetId);
            exit;
        }

        // ---------- ثبت مکاتبه ----------
        if (isset($_POST['add_correspondence'])) {
            $corr_date = trim($_POST['corr_date'] ?? '') ?: date('Y-m-d');
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
        // اطلاعات دارایی
        $stmt = $pdo->prepare("SELECT * FROM assets WHERE id = ?");
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
                <h4 class="text-primary mb-0"><?= e($assetData['name'] ?? 'بدون نام') ?></h4>
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
                    </div>
                    <div class="col-md-6">
                        <p><strong>تاریخ خرید:</strong> <?= e($assetData['purchase_date'] ?? '-') ?></p>
                        <p><strong>وضعیت:</strong> 
                            <span class="badge bg-<?= ($assetData['status'] ?? '') === 'فعال' ? 'success' : 'warning' ?>">
                                <?= e($assetData['status'] ?? 'نامشخص') ?>
                            </span>
                        </p>
                        <p><strong>ظرفیت توان:</strong> <?= e($assetData['power_capacity'] ?? '-') ?></p>
                        <p><strong>نوع موتور:</strong> <?= e($assetData['engine_type'] ?? '-') ?></p>
                    </div>
                </div>

                <!-- اطلاعات موتور -->
                <?php if (!empty($assetData['engine_model']) || !empty($assetData['engine_serial'])): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <h6 class="text-primary border-bottom pb-2 mb-3">اطلاعات موتور</h6>
                    </div>
                    <div class="col-md-6">
                        <p><strong>مدل موتور:</strong> <?= e($assetData['engine_model'] ?? '-') ?></p>
                        <p><strong>سریال موتور:</strong> <?= e($assetData['engine_serial'] ?? '-') ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>ظرفیت روغن:</strong> <?= e($assetData['oil_capacity'] ?? '-') ?></p>
                        <p><strong>ظرفیت رادیاتور:</strong> <?= e($assetData['radiator_capacity'] ?? '-') ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- اطلاعات آلترناتور -->
                <?php if (!empty($assetData['alternator_model']) || !empty($assetData['alternator_serial'])): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <h6 class="text-primary border-bottom pb-2 mb-3">اطلاعات آلترناتور</h6>
                    </div>
                    <div class="col-md-6">
                        <p><strong>مدل آلترناتور:</strong> <?= e($assetData['alternator_model'] ?? '-') ?></p>
                        <p><strong>سریال آلترناتور:</strong> <?= e($assetData['alternator_serial'] ?? '-') ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>مدل دستگاه:</strong> <?= e($assetData['device_model'] ?? '-') ?></p>
                        <p><strong>سریال دستگاه:</strong> <?= e($assetData['device_serial'] ?? '-') ?></p>
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
                                    <td><?= e($service['service_date'] ?? '-') ?></td>
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
                                    <td><?= e($task['planned_date'] ?? '-') ?></td>
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
                                    <td><?= e($corr['letter_date'] ?? '-') ?></td>
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
                                    <td><?= e($assignment['assignment_date'] ?? '-') ?></td>
                                    <td><?= e($assignment['installation_date'] ?? '-') ?></td>
                                    <td><?= e($assignment['warranty_start_date'] ?? '-') ?></td>
                                    <td><?= e($assignment['warranty_end_date'] ?? '-') ?></td>
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