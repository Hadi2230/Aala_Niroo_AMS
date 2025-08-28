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
    header('Location: reports.php');
    exit();
}

$stmt = $pdo->prepare("
    SELECT a.*, at.display_name as type_name, at.name as type_code 
    FROM assets a 
    JOIN asset_types at ON a.type_id = at.id 
    WHERE a.id = ?
");
$stmt->execute([$asset_id]);
$asset = $stmt->fetch();

if (!$asset) {
    header('Location: reports.php');
    exit();
}

// دریافت عکس‌های دستگاه
$stmt = $pdo->prepare("SELECT * FROM asset_images WHERE asset_id = ?");
$stmt->execute([$asset_id]);
$images = $stmt->fetchAll();

// پردازش داده‌های JSON
$asset['custom_data'] = json_decode($asset['custom_data'], true);

// دریافت انواع دستگاه‌ها
$types = $pdo->query("SELECT * FROM asset_types")->fetchAll();

// دریافت فیلدهای مربوط به نوع این دستگاه
$current_fields = [];
if ($asset['type_id']) {
    $stmt = $pdo->prepare("SELECT * FROM asset_fields WHERE type_id = ?");
    $stmt->execute([$asset['type_id']]);
    $current_fields = $stmt->fetchAll();
}

// ویرایش دستگاه
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_asset'])) {
    $type_id = $_POST['type_id'];
    $serial_number = $_POST['serial_number'];
    $purchase_date = $_POST['purchase_date'];
    $status = $_POST['status'];
    
    // جمع‌آوری داده‌های داینامیک
    $custom_data = [];
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'field_') === 0) {
            $field_id = str_replace('field_', '', $key);
            $custom_data[$field_id] = $value;
        }
    }
    
    try {
        $pdo->beginTransaction();
        
        // آپدیت در دیتابیس
        $stmt = $pdo->prepare("UPDATE assets SET type_id = ?, serial_number = ?, purchase_date = ?, status = ?, custom_data = ? WHERE id = ?");
        $stmt->execute([$type_id, $serial_number, $purchase_date, $status, json_encode($custom_data, JSON_UNESCAPED_UNICODE), $asset_id]);
        
        // اگر نوع دستگاه ژنراتور است، فیلدهای خاص آن را دریافت و ذخیره کنید
        if ($asset['type_name'] === 'ژنراتور') {
            $brand = $_POST['brand'];
            $engine_model = $_POST['engine_model'];
            $engine_serial = $_POST['engine_serial'];
            $alternator_model = $_POST['alternator_model'];
            $alternator_serial = $_POST['alternator_serial'];
            $device_model = $_POST['device_model'];
            $device_serial = $_POST['device_serial'];
            $control_panel_model = $_POST['control_panel_model'];
            $breaker_model = $_POST['breaker_model'];
            $fuel_tank_specs = $_POST['fuel_tank_specs'];
            $battery = $_POST['battery'];
            $battery_charger = $_POST['battery_charger'];
            $heater = $_POST['heater'];
            $oil_capacity = $_POST['oil_capacity'];
            $radiator_capacity = $_POST['radiator_capacity'];
            $antifreeze = $_POST['antifreeze'];
            $other_items = $_POST['other_items'];
            $workshop_entry_date = $_POST['workshop_entry_date'];
            $workshop_exit_date = $_POST['workshop_exit_date'];
            $datasheet_link = $_POST['datasheet_link'];
            $engine_manual_link = $_POST['engine_manual_link'];
            $alternator_manual_link = $_POST['alternator_manual_link'];
            $control_panel_manual_link = $_POST['control_panel_manual_link'];
            $description = $_POST['description'];
            
            // آپلود عکس‌های جدید
            $upload_dir = 'uploads/assets/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // لیست فیلدهایی که نیاز به آپلود عکس دارند
            $image_fields = [
                'oil_filter', 'fuel_filter', 'water_fuel_filter', 
                'air_filter', 'water_filter', 'device_image'
            ];
            
            foreach ($image_fields as $field) {
                if (!empty($_FILES[$field]['name'])) {
                    // حذف عکس قبلی اگر وجود دارد
                    $stmt = $pdo->prepare("DELETE FROM asset_images WHERE asset_id = ? AND field_name = ?");
                    $stmt->execute([$asset_id, $field]);
                    
                    $file_ext = pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION);
                    $file_name = time() . '_' . uniqid() . '_' . $field . '.' . $file_ext;
                    $target_file = $upload_dir . $file_name;
                    
                    // بررسی نوع فایل
                    $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
                    if (in_array(strtolower($file_ext), $allowed_types)) {
                        if (move_uploaded_file($_FILES[$field]['tmp_name'], $target_file)) {
                            // ذخیره اطلاعات عکس در دیتابیس
                            $stmt = $pdo->prepare("INSERT INTO asset_images (asset_id, field_name, image_path) VALUES (?, ?, ?)");
                            $stmt->execute([$asset_id, $field, $target_file]);
                        }
                    }
                }
            }
            
            // آپدیت اطلاعات متنی در دیتابیس
            $stmt = $pdo->prepare("UPDATE assets SET 
                brand = ?, engine_model = ?, engine_serial = ?, alternator_model = ?, 
                alternator_serial = ?, device_model = ?, device_serial = ?, 
                control_panel_model = ?, breaker_model = ?, fuel_tank_specs = ?, 
                battery = ?, battery_charger = ?, heater = ?, oil_capacity = ?, 
                radiator_capacity = ?, antifreeze = ?, other_items = ?, 
                workshop_entry_date = ?, workshop_exit_date = ?, datasheet_link = ?, 
                engine_manual_link = ?, alternator_manual_link = ?, 
                control_panel_manual_link = ?, description = ? 
                WHERE id = ?");
            
            $stmt->execute([
                $brand, $engine_model, $engine_serial, $alternator_model, 
                $alternator_serial, $device_model, $device_serial, 
                $control_panel_model, $breaker_model, $fuel_tank_specs, 
                $battery, $battery_charger, $heater, $oil_capacity, 
                $radiator_capacity, $antifreeze, $other_items, 
                $workshop_entry_date, $workshop_exit_date, $datasheet_link, 
                $engine_manual_link, $alternator_manual_link, 
                $control_panel_manual_link, $description, $asset_id
            ]);
        }
        
        $pdo->commit();
        $success = "دستگاه با موفقیت ویرایش شد!";
        header('Location: reports.php?success=' . urlencode($success));
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "خطا در ویرایش دستگاه: " . $e->getMessage();
    }
}

// دریافت فیلدهای برای AJAX
$all_fields = [];
foreach ($types as $type) {
    $stmt = $pdo->prepare("SELECT * FROM asset_fields WHERE type_id = ?");
    $stmt->execute([$type['id']]);
    $all_fields[$type['id']] = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ویرایش دستگاه - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .image-preview {
            max-width: 200px;
            max-height: 200px;
            margin-top: 10px;
        }
    </style>
</head>
<body onload="loadFields(<?php echo $asset['type_id']; ?>)">
    <?php include 'navbar.php'; ?>

    <div class="container mt-5">
        <h2 class="text-center">ویرایش دستگاه #<?php echo $asset['id']; ?></h2>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card mt-4">
            <div class="card-header">ویرایش اطلاعات دستگاه</div>
            <div class="card-body">
                <form method="POST" id="assetForm" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="type_id" class="form-label">نوع دستگاه *</label>
                                <select class="form-select" id="type_id" name="type_id" required onchange="loadFields(this.value)">
                                    <option value="">-- انتخاب کنید --</option>
                                    <?php foreach ($types as $type): ?>
                                        <option value="<?php echo $type['id']; ?>" 
                                            <?php echo $type['id'] == $asset['type_id'] ? 'selected' : ''; ?>>
                                            <?php echo $type['display_name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="serial_number" class="form-label">شماره سریال *</label>
                                <input type="text" class="form-control" id="serial_number" name="serial_number" 
                                       value="<?php echo $asset['serial_number']; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="purchase_date" class="form-label">تاریخ خرید *</label>
                                <input type="date" class="form-control" id="purchase_date" name="purchase_date" 
                                       value="<?php echo $asset['purchase_date']; ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="status" class="form-label">وضعیت *</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="فعال" <?php echo $asset['status'] == 'فعال' ? 'selected' : ''; ?>>فعال</option>
                            <option value="غیرفعال" <?php echo $asset['status'] == 'غیرفعال' ? 'selected' : ''; ?>>غیرفعال</option>
                            <option value="در حال تعمیر" <?php echo $asset['status'] == 'در حال تعمیر' ? 'selected' : ''; ?>>در حال تعمیر</option>
                        </select>
                    </div>

                    <!-- فیلدهای پویا -->
                    <div id="dynamicFields">
                        <?php if ($asset['asset_type'] === 'ژنراتور'): ?>
                            <!-- فیلدهای مخصوص ژنراتور -->
                            <h4 class="mb-4 mt-4">مشخصات کامل ژنراتور</h4>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="brand" class="form-label">نام برند *</label>
                                        <input type="text" class="form-control" id="brand" name="brand" value="<?php echo $asset['brand']; ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="engine_model" class="form-label">مدل موتور *</label>
                                        <input type="text" class="form-control" id="engine_model" name="engine_model" value="<?php echo $asset['engine_model']; ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="engine_serial" class="form-label">سریال موتور *</label>
                                        <input type="text" class="form-control" id="engine_serial" name="engine_serial" value="<?php echo $asset['engine_serial']; ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- ادامه فیلدهای ژنراتور به همین ترتیب -->
                            
                            <!-- نمایش عکس‌های موجود -->
                            <h5 class="mt-4 mb-3">عکس‌های موجود</h5>
                            <div class="row">
                                <?php foreach ($images as $image): ?>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label"><?php echo $image['field_name']; ?></label>
                                        <div>
                                            <img src="<?php echo $image['image_path']; ?>" class="img-thumbnail" style="max-width: 200px;">
                                            <br>
                                            <a href="<?php echo $image['image_path']; ?>" target="_blank" class="btn btn-sm btn-info mt-2">مشاهده کامل</a>
                                            <a href="delete_image.php?id=<?php echo $image['id']; ?>&asset_id=<?php echo $asset_id; ?>" class="btn btn-sm btn-danger mt-2" onclick="return confirm('آیا مطمئن هستید؟')">حذف</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- فیلدهای آپلود جدید -->
                            <h5 class="mt-4 mb-3">آپلود عکس‌های جدید</h5>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="oil_filter" class="form-label">پارت نامبر فیلتر روغن (عکس)</label>
                                        <input type="file" class="form-control" id="oil_filter" name="oil_filter" accept="image/*">
                                    </div>
                                </div>
                                <!-- سایر فیلدهای آپلود به همین ترتیب -->
                            </div>
                        <?php endif; ?>
                    </div>

                    <button type="submit" name="edit_asset" class="btn btn-primary">ذخیره تغییرات</button>
                    <a href="reports.php" class="btn btn-secondary">انصراف</a>
                </form>
            </div>
        </div>
    </div>

    <script>
    const allFields = <?php echo json_encode($all_fields, JSON_UNESCAPED_UNICODE); ?>;

    function loadFields(typeId) {
        const dynamicFields = document.getElementById('dynamicFields');
        
        if (!typeId) {
            dynamicFields.innerHTML = '';
            return;
        }

        let html = '';
        if (allFields[typeId]) {
            allFields[typeId].forEach(field => {
                const fieldValue = <?php echo json_encode($asset['custom_data'] ?? [], JSON_UNESCAPED_UNICODE); ?>[field.id] || '';
                
                html += `<div class="mb-3">
                    <label for="field_${field.id}" class="form-label">${field.field_name}${field.is_required ? ' *' : ''}</label>`;
                
                switch (field.field_type) {
                    case 'text':
                        html += `<input type="text" class="form-control" id="field_${field.id}" name="field_${field.id}" value="${fieldValue}"${field.is_required ? ' required' : ''}>`;
                        break;
                    case 'number':
                        html += `<input type="number" class="form-control" id="field_${field.id}" name="field_${field.id}" value="${fieldValue}"${field.is_required ? ' required' : ''}>`;
                        break;
                    case 'date':
                        html += `<input type="date" class="form-control" id="field_${field.id}" name="field_${field.id}" value="${fieldValue}"${field.is_required ? ' required' : ''}>`;
                        break;
                    case 'select':
                        html += `<select class="form-select" id="field_${field.id}" name="field_${field.id}"${field.is_required ? ' required' : ''}>
                            <option value="">-- انتخاب کنید --</option>`;
                        if (field.options) {
                            field.options.split(',').forEach(option => {
                                option = option.trim();
                                const selected = fieldValue == option ? 'selected' : '';
                                html += `<option value="${option}" ${selected}>${option}</option>`;
                            });
                        }
                        html += `</select>`;
                        break;
                }
                
                html += `</div>`;
            });
        }
        
        dynamicFields.innerHTML = html;
    }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>