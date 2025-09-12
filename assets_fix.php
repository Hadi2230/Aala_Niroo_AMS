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
    verifyCsrfToken();
    try {
        $pdo->beginTransaction();

        $name = sanitizeInput($_POST['name']);
        $type_id = (int)$_POST['type_id'];
        $serial_number = sanitizeInput($_POST['serial_number']);
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

        $stmt = $pdo->prepare("SELECT name FROM asset_types WHERE id = ?");
        $stmt->execute([$type_id]);
        $asset_type = $stmt->fetch();
        $asset_type_name = $asset_type['name'] ?? '';

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

$query = "SELECT a.*, at.display_name as type_display_name 
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
