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
