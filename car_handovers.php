<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// ثبت تحویل خودرو
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['handover_car'])) {
    verifyCsrfToken();
    
    try {
        $pdo->beginTransaction();
        
        // تولید کد تحویل
        $handover_code = 'HO' . date('Ymd') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        
        // دریافت اطلاعات
        $car_id = intval($_POST['car_id']);
        $driver_id = intval($_POST['driver_id']);
        $handover_date = sanitizeInput($_POST['handover_date']);
        $handover_time = sanitizeInput($_POST['handover_time']);
        $handover_odometer = floatval($_POST['handover_odometer']);
        $handover_fuel_level = intval($_POST['handover_fuel_level']);
        $handover_location = sanitizeInput($_POST['handover_location']);
        $purpose = sanitizeInput($_POST['purpose']);
        $destination = sanitizeInput($_POST['destination']);
        $expected_return_date = sanitizeInput($_POST['expected_return_date']);
        
        // وضعیت خودرو
        $exterior_condition = sanitizeInput($_POST['exterior_condition']);
        $interior_condition = sanitizeInput($_POST['interior_condition']);
        $tires_condition = sanitizeInput($_POST['tires_condition']);
        
        // چک‌لیست
        $spare_tire_check = isset($_POST['spare_tire_check']) ? 1 : 0;
        $jack_check = isset($_POST['jack_check']) ? 1 : 0;
        $tools_check = isset($_POST['tools_check']) ? 1 : 0;
        $documents_check = isset($_POST['documents_check']) ? 1 : 0;
        $first_aid_check = isset($_POST['first_aid_check']) ? 1 : 0;
        $fire_extinguisher_check = isset($_POST['fire_extinguisher_check']) ? 1 : 0;
        $warning_triangle_check = isset($_POST['warning_triangle_check']) ? 1 : 0;
        
        $notes = sanitizeInput($_POST['notes'] ?? '');
        
        // آپلود امضا
        $signature_path = '';
        if (isset($_POST['signature_data']) && !empty($_POST['signature_data'])) {
            $signature_data = $_POST['signature_data'];
            $signature_data = str_replace('data:image/png;base64,', '', $signature_data);
            $signature_data = str_replace(' ', '+', $signature_data);
            $data = base64_decode($signature_data);
            
            $signature_dir = 'uploads/signatures/';
            if (!is_dir($signature_dir)) {
                mkdir($signature_dir, 0755, true);
            }
            
            $filename = 'sig_' . $handover_code . '_' . time() . '.png';
            $signature_path = $signature_dir . $filename;
            file_put_contents($signature_path, $data);
        }
        
        // آپلود تصاویر
        $images = [];
        $upload_dir = 'uploads/handovers/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        if (isset($_FILES['handover_images'])) {
            foreach ($_FILES['handover_images']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['handover_images']['error'][$key] === 0) {
                    $file = [
                        'name' => $_FILES['handover_images']['name'][$key],
                        'type' => $_FILES['handover_images']['type'][$key],
                        'tmp_name' => $tmp_name,
                        'error' => $_FILES['handover_images']['error'][$key],
                        'size' => $_FILES['handover_images']['size'][$key]
                    ];
                    
                    try {
                        $uploaded_file = uploadFile($file, $upload_dir);
                        $images[] = $uploaded_file;
                    } catch (Exception $e) {
                        // ادامه با سایر تصاویر
                    }
                }
            }
        }
        
        // درج رکورد تحویل
        $stmt = $pdo->prepare("INSERT INTO car_handovers (
            handover_code, car_id, driver_id, handover_type, handover_date, handover_time,
            handover_by, receiver_signature, handover_odometer, handover_fuel_level,
            handover_location, exterior_condition, interior_condition, tires_condition,
            spare_tire_check, jack_check, tools_check, documents_check, first_aid_check,
            fire_extinguisher_check, warning_triangle_check, purpose, destination,
            expected_return_date, handover_images, notes, status
        ) VALUES (?, ?, ?, 'تحویل', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'در حال استفاده')");
        
        $stmt->execute([
            $handover_code, $car_id, $driver_id, $handover_date, $handover_time,
            $_SESSION['user_id'], $signature_path, $handover_odometer, $handover_fuel_level,
            $handover_location, $exterior_condition, $interior_condition, $tires_condition,
            $spare_tire_check, $jack_check, $tools_check, $documents_check, $first_aid_check,
            $fire_extinguisher_check, $warning_triangle_check, $purpose, $destination,
            $expected_return_date, json_encode($images), $notes
        ]);
        
        $handover_id = $pdo->lastInsertId();
        
        // به‌روزرسانی وضعیت خودرو
        $stmt = $pdo->prepare("UPDATE company_cars SET 
            current_status = 'در حال استفاده',
            current_driver_id = ?,
            current_odometer = ?,
            current_fuel_level = ?,
            current_location = ?
            WHERE id = ?");
        
        $stmt->execute([$driver_id, $handover_odometer, $handover_fuel_level, $handover_location, $car_id]);
        
        // ایجاد رکورد سفر
        $trip_code = 'TR' . date('Ymd') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare("INSERT INTO car_trips (
            trip_code, car_id, driver_id, handover_id, start_datetime, start_location,
            start_odometer, purpose, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'در حال انجام')");
        
        $stmt->execute([
            $trip_code, $car_id, $driver_id, $handover_id,
            $handover_date . ' ' . $handover_time, $handover_location,
            $handover_odometer, $purpose
        ]);
        
        $pdo->commit();
        $success_message = "خودرو با موفقیت تحویل داده شد. کد تحویل: $handover_code";
        logAction($pdo, 'CAR_HANDOVER', "تحویل خودرو $car_id به راننده $driver_id");
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "خطا در ثبت تحویل: " . $e->getMessage();
        error_log($e->getMessage());
    }
}

// ثبت عودت خودرو
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['return_car'])) {
    verifyCsrfToken();
    
    try {
        $pdo->beginTransaction();
        
        $handover_id = intval($_POST['handover_id']);
        $return_date = sanitizeInput($_POST['return_date']);
        $return_time = sanitizeInput($_POST['return_time']);
        $return_odometer = floatval($_POST['return_odometer']);
        $return_fuel_level = intval($_POST['return_fuel_level']);
        $return_location = sanitizeInput($_POST['return_location']);
        $reported_issues = sanitizeInput($_POST['reported_issues'] ?? '');
        $damages_description = sanitizeInput($_POST['damages_description'] ?? '');
        
        // دریافت اطلاعات تحویل
        $stmt = $pdo->prepare("SELECT * FROM car_handovers WHERE id = ?");
        $stmt->execute([$handover_id]);
        $handover = $stmt->fetch();
        
        if (!$handover) {
            throw new Exception("رکورد تحویل یافت نشد");
        }
        
        // محاسبه مسافت و سوخت مصرفی
        $total_distance = $return_odometer - $handover['handover_odometer'];
        $fuel_consumed = 0; // محاسبه بر اساس درصد
        
        // آپلود امضای عودت
        $return_signature = '';
        if (isset($_POST['return_signature_data']) && !empty($_POST['return_signature_data'])) {
            $signature_data = $_POST['return_signature_data'];
            $signature_data = str_replace('data:image/png;base64,', '', $signature_data);
            $signature_data = str_replace(' ', '+', $signature_data);
            $data = base64_decode($signature_data);
            
            $signature_dir = 'uploads/signatures/';
            $filename = 'sig_return_' . $handover['handover_code'] . '_' . time() . '.png';
            $return_signature = $signature_dir . $filename;
            file_put_contents($return_signature, $data);
        }
        
        // آپلود تصاویر عودت
        $return_images = [];
        $upload_dir = 'uploads/handovers/';
        
        if (isset($_FILES['return_images'])) {
            foreach ($_FILES['return_images']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['return_images']['error'][$key] === 0) {
                    $file = [
                        'name' => $_FILES['return_images']['name'][$key],
                        'type' => $_FILES['return_images']['type'][$key],
                        'tmp_name' => $tmp_name,
                        'error' => $_FILES['return_images']['error'][$key],
                        'size' => $_FILES['return_images']['size'][$key]
                    ];
                    
                    try {
                        $uploaded_file = uploadFile($file, $upload_dir);
                        $return_images[] = $uploaded_file;
                    } catch (Exception $e) {
                        // ادامه
                    }
                }
            }
        }
        
        // به‌روزرسانی رکورد تحویل
        $stmt = $pdo->prepare("UPDATE car_handovers SET 
            return_date = ?, return_time = ?, return_odometer = ?, return_fuel_level = ?,
            return_location = ?, return_received_by = ?, return_signature = ?,
            total_distance_km = ?, fuel_consumed_liters = ?, reported_issues = ?,
            damages_description = ?, return_images = ?, status = 'تکمیل شده'
            WHERE id = ?");
        
        $stmt->execute([
            $return_date, $return_time, $return_odometer, $return_fuel_level,
            $return_location, $_SESSION['user_id'], $return_signature,
            $total_distance, $fuel_consumed, $reported_issues,
            $damages_description, json_encode($return_images), $handover_id
        ]);
        
        // به‌روزرسانی وضعیت خودرو
        $stmt = $pdo->prepare("UPDATE company_cars SET 
            current_status = 'در دسترس',
            current_driver_id = NULL,
            current_odometer = ?,
            current_fuel_level = ?,
            current_location = ?
            WHERE id = ?");
        
        $stmt->execute([$return_odometer, $return_fuel_level, $return_location, $handover['car_id']]);
        
        // تکمیل رکورد سفر
        $stmt = $pdo->prepare("UPDATE car_trips SET 
            end_datetime = ?, end_location = ?, end_odometer = ?,
            total_distance_km = ?, status = 'تکمیل شده'
            WHERE handover_id = ?");
        
        $stmt->execute([
            $return_date . ' ' . $return_time, $return_location,
            $return_odometer, $total_distance, $handover_id
        ]);
        
        $pdo->commit();
        $success_message = "خودرو با موفقیت عودت داده شد. مسافت طی شده: " . number_format($total_distance) . " کیلومتر";
        logAction($pdo, 'CAR_RETURN', "عودت خودرو {$handover['car_id']}");
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "خطا در ثبت عودت: " . $e->getMessage();
        error_log($e->getMessage());
    }
}

// دریافت لیست خودروهای در دسترس
$available_cars = $pdo->query("
    SELECT c.*, 
           DATEDIFF(insurance_expiry_date, CURDATE()) as insurance_days,
           DATEDIFF(technical_inspection_expiry, CURDATE()) as inspection_days
    FROM company_cars c
    WHERE c.current_status = 'در دسترس' 
    AND c.is_active = 1
    ORDER BY c.brand, c.model
")->fetchAll();

// دریافت لیست رانندگان فعال
$active_drivers = $pdo->query("
    SELECT * FROM car_drivers 
    WHERE status = 'فعال' 
    ORDER BY full_name
")->fetchAll();

// دریافت لیست تحویل‌های فعال
$active_handovers = $pdo->query("
    SELECT h.*, c.brand, c.model, c.plate_number, d.full_name as driver_name,
           u.full_name as handover_by_name
    FROM car_handovers h
    JOIN company_cars c ON h.car_id = c.id
    JOIN car_drivers d ON h.driver_id = d.id
    JOIN users u ON h.handover_by = u.id
    WHERE h.status = 'در حال استفاده'
    ORDER BY h.handover_date DESC, h.handover_time DESC
")->fetchAll();

// دریافت تاریخچه تحویل‌ها
$history_filter_date = isset($_GET['filter_date']) ? sanitizeInput($_GET['filter_date']) : '';
$history_filter_car = isset($_GET['filter_car']) ? intval($_GET['filter_car']) : 0;

$history_query = "
    SELECT h.*, c.brand, c.model, c.plate_number, d.full_name as driver_name,
           u1.full_name as handover_by_name, u2.full_name as return_by_name
    FROM car_handovers h
    JOIN company_cars c ON h.car_id = c.id
    JOIN car_drivers d ON h.driver_id = d.id
    JOIN users u1 ON h.handover_by = u1.id
    LEFT JOIN users u2 ON h.return_received_by = u2.id
    WHERE h.status = 'تکمیل شده'";

$history_params = [];

if ($history_filter_date) {
    $history_query .= " AND h.handover_date = ?";
    $history_params[] = $history_filter_date;
}

if ($history_filter_car) {
    $history_query .= " AND h.car_id = ?";
    $history_params[] = $history_filter_car;
}

$history_query .= " ORDER BY h.return_date DESC, h.return_time DESC LIMIT 50";

$history_stmt = $pdo->prepare($history_query);
$history_stmt->execute($history_params);
$handover_history = $history_stmt->fetchAll();

// دریافت لیست همه خودروها برای فیلتر
$all_cars = $pdo->query("SELECT id, brand, model, plate_number FROM company_cars ORDER BY brand, model")->fetchAll();

?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تحویل و عودت خودرو - سامانه اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .handover-card {
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        .handover-card:hover {
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .car-select-card {
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.3s;
        }
        .car-select-card:hover {
            border-color: #007bff;
            background-color: #f8f9fa;
        }
        .car-select-card.selected {
            border-color: #28a745;
            background-color: #e8f5e9;
        }
        .signature-pad {
            border: 2px solid #dee2e6;
            border-radius: 5px;
            width: 100%;
            height: 200px;
        }
        .checklist-item {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 5px;
            background-color: #f8f9fa;
        }
        .status-active {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
        }
        .status-completed {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        .timeline-item {
            border-left: 3px solid #007bff;
            padding-left: 20px;
            margin-left: 10px;
            position: relative;
            padding-bottom: 20px;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -8px;
            top: 0;
            width: 13px;
            height: 13px;
            border-radius: 50%;
            background: #007bff;
        }
        .image-preview {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 5px;
            margin: 5px;
        }
        .nav-pills .nav-link {
            border-radius: 20px;
            margin: 0 5px;
        }
        .warning-alert {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 10px;
            margin-bottom: 10px;
        }
        .odometer-input {
            font-size: 1.2rem;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container-fluid mt-4">
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- تب‌ها -->
        <ul class="nav nav-pills mb-4" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="pill" href="#handover-tab">
                    <i class="bi bi-key-fill"></i> تحویل خودرو
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#return-tab">
                    <i class="bi bi-arrow-return-left"></i> عودت خودرو
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#active-tab">
                    <i class="bi bi-clock-history"></i> تحویل‌های فعال
                    <?php if (count($active_handovers) > 0): ?>
                        <span class="badge bg-danger"><?php echo count($active_handovers); ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#history-tab">
                    <i class="bi bi-journal-text"></i> تاریخچه
                </a>
            </li>
        </ul>

        <div class="tab-content">
            <!-- تب تحویل خودرو -->
            <div class="tab-pane fade show active" id="handover-tab">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-car-front me-2"></i>تحویل خودرو به راننده</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" id="handoverForm">
                            <?php echo csrf_field(); ?>
                            
                            <!-- انتخاب خودرو -->
                            <div class="mb-4">
                                <h6 class="border-bottom pb-2 mb-3">انتخاب خودرو</h6>
                                <div class="row" id="carSelection">
                                    <?php foreach ($available_cars as $car): 
                                        $has_warning = ($car['insurance_days'] <= 30 || $car['inspection_days'] <= 30);
                                    ?>
                                    <div class="col-md-4 col-lg-3 mb-3">
                                        <div class="card car-select-card" data-car-id="<?php echo $car['id']; ?>"
                                             data-odometer="<?php echo $car['current_odometer']; ?>"
                                             data-fuel="<?php echo $car['current_fuel_level']; ?>">
                                            <div class="card-body">
                                                <h6 class="card-title">
                                                    <?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?>
                                                    <?php if ($has_warning): ?>
                                                        <i class="bi bi-exclamation-triangle text-warning float-end"></i>
                                                    <?php endif; ?>
                                                </h6>
                                                <p class="mb-1">پلاک: <strong><?php echo htmlspecialchars($car['plate_number']); ?></strong></p>
                                                <p class="mb-1">کیلومتر: <?php echo number_format($car['current_odometer']); ?></p>
                                                <?php if ($has_warning): ?>
                                                    <div class="warning-alert mt-2">
                                                        <?php if ($car['insurance_days'] <= 30): ?>
                                                            <small>بیمه: <?php echo $car['insurance_days']; ?> روز</small><br>
                                                        <?php endif; ?>
                                                        <?php if ($car['inspection_days'] <= 30): ?>
                                                            <small>معاینه: <?php echo $car['inspection_days']; ?> روز</small>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <input type="hidden" name="car_id" id="selected_car_id" required>
                            </div>

                            <!-- انتخاب راننده -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">راننده *</label>
                                    <select name="driver_id" class="form-select" required>
                                        <option value="">انتخاب کنید</option>
                                        <?php foreach ($active_drivers as $driver): ?>
                                            <option value="<?php echo $driver['id']; ?>">
                                                <?php echo htmlspecialchars($driver['full_name']); ?> - 
                                                <?php echo htmlspecialchars($driver['national_code']); ?>
                                                (امتیاز: <?php echo $driver['driver_score']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">تاریخ تحویل *</label>
                                    <input type="date" name="handover_date" class="form-control" 
                                           value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">ساعت تحویل *</label>
                                    <input type="time" name="handover_time" class="form-control" 
                                           value="<?php echo date('H:i'); ?>" required>
                                </div>
                            </div>

                            <!-- اطلاعات کیلومتر و سوخت -->
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">کیلومتر فعلی *</label>
                                    <input type="number" name="handover_odometer" id="handover_odometer"
                                           class="form-control odometer-input" required min="0" step="0.1">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">سطح سوخت (%) *</label>
                                    <input type="range" name="handover_fuel_level" class="form-range" 
                                           min="0" max="100" value="50" id="fuel_range">
                                    <div class="text-center"><span id="fuel_value">50</span>%</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">محل تحویل</label>
                                    <input type="text" name="handover_location" class="form-control" 
                                           value="پارکینگ شرکت">
                                </div>
                            </div>

                            <!-- وضعیت خودرو -->
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">وضعیت ظاهری</label>
                                    <select name="exterior_condition" class="form-select">
                                        <option value="سالم">سالم</option>
                                        <option value="خراشیدگی جزئی">خراشیدگی جزئی</option>
                                        <option value="آسیب متوسط">آسیب متوسط</option>
                                        <option value="آسیب شدید">آسیب شدید</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">وضعیت داخلی</label>
                                    <select name="interior_condition" class="form-select">
                                        <option value="تمیز">تمیز</option>
                                        <option value="نیاز به نظافت">نیاز به نظافت</option>
                                        <option value="آسیب جزئی">آسیب جزئی</option>
                                        <option value="آسیب شدید">آسیب شدید</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">وضعیت لاستیک‌ها</label>
                                    <select name="tires_condition" class="form-select">
                                        <option value="مناسب">مناسب</option>
                                        <option value="نیاز به تعویض">نیاز به تعویض</option>
                                        <option value="پنچر">پنچر</option>
                                        <option value="نامناسب">نامناسب</option>
                                    </select>
                                </div>
                            </div>

                            <!-- چک‌لیست تحویل -->
                            <div class="mb-3">
                                <h6 class="border-bottom pb-2 mb-3">چک‌لیست تحویل</h6>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="checklist-item">
                                            <div class="form-check">
                                                <input type="checkbox" name="spare_tire_check" class="form-check-input" 
                                                       id="spare_tire" checked>
                                                <label class="form-check-label" for="spare_tire">
                                                    <i class="bi bi-circle"></i> لاستیک زاپاس
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="checklist-item">
                                            <div class="form-check">
                                                <input type="checkbox" name="jack_check" class="form-check-input" 
                                                       id="jack" checked>
                                                <label class="form-check-label" for="jack">
                                                    <i class="bi bi-tools"></i> جک
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="checklist-item">
                                            <div class="form-check">
                                                <input type="checkbox" name="tools_check" class="form-check-input" 
                                                       id="tools" checked>
                                                <label class="form-check-label" for="tools">
                                                    <i class="bi bi-wrench"></i> ابزار
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="checklist-item">
                                            <div class="form-check">
                                                <input type="checkbox" name="documents_check" class="form-check-input" 
                                                       id="documents" checked>
                                                <label class="form-check-label" for="documents">
                                                    <i class="bi bi-file-text"></i> مدارک
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="checklist-item">
                                            <div class="form-check">
                                                <input type="checkbox" name="first_aid_check" class="form-check-input" 
                                                       id="first_aid" checked>
                                                <label class="form-check-label" for="first_aid">
                                                    <i class="bi bi-bandaid"></i> جعبه کمک‌های اولیه
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="checklist-item">
                                            <div class="form-check">
                                                <input type="checkbox" name="fire_extinguisher_check" class="form-check-input" 
                                                       id="fire_extinguisher" checked>
                                                <label class="form-check-label" for="fire_extinguisher">
                                                    <i class="bi bi-fire"></i> کپسول آتش‌نشانی
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="checklist-item">
                                            <div class="form-check">
                                                <input type="checkbox" name="warning_triangle_check" class="form-check-input" 
                                                       id="warning_triangle" checked>
                                                <label class="form-check-label" for="warning_triangle">
                                                    <i class="bi bi-triangle"></i> مثلث خطر
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- اطلاعات سفر -->
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">هدف استفاده *</label>
                                    <select name="purpose" class="form-select" required>
                                        <option value="">انتخاب کنید</option>
                                        <option value="ماموریت کاری">ماموریت کاری</option>
                                        <option value="جلسه">جلسه</option>
                                        <option value="بازدید مشتری">بازدید مشتری</option>
                                        <option value="سرویس و تعمیر">سرویس و تعمیر</option>
                                        <option value="شخصی">شخصی</option>
                                        <option value="سایر">سایر</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">مقصد</label>
                                    <input type="text" name="destination" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">تاریخ بازگشت مورد انتظار</label>
                                    <input type="date" name="expected_return_date" class="form-control">
                                </div>
                            </div>

                            <!-- تصاویر و امضا -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">تصاویر خودرو</label>
                                    <input type="file" name="handover_images[]" class="form-control" 
                                           accept="image/*" multiple>
                                    <small class="text-muted">می‌توانید چند تصویر انتخاب کنید</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">امضای گیرنده</label>
                                    <canvas id="signature-pad" class="signature-pad"></canvas>
                                    <input type="hidden" name="signature_data" id="signature_data">
                                    <button type="button" class="btn btn-sm btn-secondary mt-2" onclick="clearSignature()">
                                        پاک کردن امضا
                                    </button>
                                </div>
                            </div>

                            <!-- یادداشت -->
                            <div class="mb-3">
                                <label class="form-label">یادداشت‌ها</label>
                                <textarea name="notes" class="form-control" rows="2"></textarea>
                            </div>

                            <div class="text-end">
                                <button type="button" class="btn btn-secondary" onclick="resetForm()">
                                    <i class="bi bi-x-circle"></i> انصراف
                                </button>
                                <button type="submit" name="handover_car" class="btn btn-primary">
                                    <i class="bi bi-check-circle"></i> ثبت تحویل
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- تب عودت خودرو -->
            <div class="tab-pane fade" id="return-tab">
                <?php if (count($active_handovers) > 0): ?>
                    <div class="row">
                        <?php foreach ($active_handovers as $handover): ?>
                        <div class="col-md-6 mb-3">
                            <div class="card handover-card">
                                <div class="card-header status-active">
                                    <h6 class="mb-0">
                                        <?php echo htmlspecialchars($handover['brand'] . ' ' . $handover['model']); ?> - 
                                        <?php echo htmlspecialchars($handover['plate_number']); ?>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p><strong>راننده:</strong> <?php echo htmlspecialchars($handover['driver_name']); ?></p>
                                            <p><strong>تاریخ تحویل:</strong> <?php echo jalaliDate($handover['handover_date']); ?></p>
                                            <p><strong>کیلومتر تحویل:</strong> <?php echo number_format($handover['handover_odometer']); ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong>کد تحویل:</strong> <?php echo htmlspecialchars($handover['handover_code']); ?></p>
                                            <p><strong>هدف:</strong> <?php echo htmlspecialchars($handover['purpose']); ?></p>
                                            <p><strong>مقصد:</strong> <?php echo htmlspecialchars($handover['destination'] ?? '-'); ?></p>
                                        </div>
                                    </div>
                                    <button class="btn btn-success btn-sm w-100" 
                                            onclick="showReturnForm(<?php echo $handover['id']; ?>)">
                                        <i class="bi bi-arrow-return-left"></i> ثبت عودت
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info text-center">
                        <i class="bi bi-info-circle fs-1 d-block mb-3"></i>
                        <h4>هیچ خودرویی در حال استفاده نیست</h4>
                        <p>همه خودروها در پارکینگ هستند.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- تب تحویل‌های فعال -->
            <div class="tab-pane fade" id="active-tab">
                <?php if (count($active_handovers) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>کد تحویل</th>
                                    <th>خودرو</th>
                                    <th>راننده</th>
                                    <th>تاریخ تحویل</th>
                                    <th>کیلومتر</th>
                                    <th>هدف</th>
                                    <th>مقصد</th>
                                    <th>بازگشت متوقع</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($active_handovers as $handover): ?>
                                <tr>
                                    <td><span class="badge bg-primary"><?php echo htmlspecialchars($handover['handover_code']); ?></span></td>
                                    <td>
                                        <?php echo htmlspecialchars($handover['brand'] . ' ' . $handover['model']); ?><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($handover['plate_number']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($handover['driver_name']); ?></td>
                                    <td>
                                        <?php echo jalaliDate($handover['handover_date']); ?><br>
                                        <small><?php echo $handover['handover_time']; ?></small>
                                    </td>
                                    <td><?php echo number_format($handover['handover_odometer']); ?> کیلومتر</td>
                                    <td><?php echo htmlspecialchars($handover['purpose']); ?></td>
                                    <td><?php echo htmlspecialchars($handover['destination'] ?? '-'); ?></td>
                                    <td>
                                        <?php if ($handover['expected_return_date']): ?>
                                            <?php echo jalaliDate($handover['expected_return_date']); ?>
                                            <?php 
                                            $days_diff = (strtotime($handover['expected_return_date']) - time()) / 86400;
                                            if ($days_diff < 0): ?>
                                                <span class="badge bg-danger">تاخیر</span>
                                            <?php elseif ($days_diff <= 1): ?>
                                                <span class="badge bg-warning">امروز</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-success" 
                                                onclick="showReturnForm(<?php echo $handover['id']; ?>)">
                                            عودت
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info text-center">
                        هیچ تحویل فعالی وجود ندارد.
                    </div>
                <?php endif; ?>
            </div>

            <!-- تب تاریخچه -->
            <div class="tab-pane fade" id="history-tab">
                <div class="card mb-3">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">تاریخ</label>
                                <input type="date" name="filter_date" class="form-control" 
                                       value="<?php echo htmlspecialchars($history_filter_date); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">خودرو</label>
                                <select name="filter_car" class="form-select">
                                    <option value="">همه خودروها</option>
                                    <?php foreach ($all_cars as $car): ?>
                                        <option value="<?php echo $car['id']; ?>" 
                                                <?php echo $history_filter_car == $car['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($car['brand'] . ' ' . $car['model'] . ' - ' . $car['plate_number']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary d-block">
                                    <i class="bi bi-search"></i> فیلتر
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if (count($handover_history) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>کد</th>
                                    <th>خودرو</th>
                                    <th>راننده</th>
                                    <th>تحویل</th>
                                    <th>عودت</th>
                                    <th>مسافت</th>
                                    <th>هدف</th>
                                    <th>وضعیت</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($handover_history as $history): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($history['handover_code']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($history['brand'] . ' ' . $history['model']); ?><br>
                                        <small><?php echo htmlspecialchars($history['plate_number']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($history['driver_name']); ?></td>
                                    <td>
                                        <?php echo jalaliDate($history['handover_date']); ?><br>
                                        <small><?php echo number_format($history['handover_odometer']); ?> کیلومتر</small>
                                    </td>
                                    <td>
                                        <?php echo jalaliDate($history['return_date']); ?><br>
                                        <small><?php echo number_format($history['return_odometer']); ?> کیلومتر</small>
                                    </td>
                                    <td>
                                        <?php echo number_format($history['total_distance_km']); ?> کیلومتر
                                    </td>
                                    <td><?php echo htmlspecialchars($history['purpose']); ?></td>
                                    <td><span class="badge bg-success">تکمیل شده</span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info text-center">
                        هیچ تاریخچه‌ای یافت نشد.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- مودال عودت خودرو -->
    <div class="modal fade" id="returnModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">عودت خودرو</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body" id="returnFormContent">
                        <!-- محتوا با AJAX بارگذاری می‌شود -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" name="return_car" class="btn btn-success">
                            <i class="bi bi-check-circle"></i> ثبت عودت
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
    
    <script>
        // مدیریت امضا
        var canvas = document.getElementById('signature-pad');
        var signaturePad = new SignaturePad(canvas);

        function clearSignature() {
            signaturePad.clear();
        }

        // ذخیره امضا قبل از ارسال فرم
        document.getElementById('handoverForm').addEventListener('submit', function(e) {
            if (!signaturePad.isEmpty()) {
                document.getElementById('signature_data').value = signaturePad.toDataURL();
            }
        });

        // انتخاب خودرو
        document.querySelectorAll('.car-select-card').forEach(card => {
            card.addEventListener('click', function() {
                // حذف کلاس selected از همه
                document.querySelectorAll('.car-select-card').forEach(c => c.classList.remove('selected'));
                
                // اضافه کردن به کارت انتخاب شده
                this.classList.add('selected');
                
                // مقداردهی فیلد مخفی
                document.getElementById('selected_car_id').value = this.dataset.carId;
                document.getElementById('handover_odometer').value = this.dataset.odometer;
                document.getElementById('handover_odometer').min = this.dataset.odometer;
            });
        });

        // نمایش مقدار سوخت
        document.getElementById('fuel_range').addEventListener('input', function() {
            document.getElementById('fuel_value').textContent = this.value;
        });

        // نمایش فرم عودت
        function showReturnForm(handoverId) {
            $.ajax({
                url: 'ajax/get_return_form.php',
                type: 'GET',
                data: { id: handoverId },
                success: function(response) {
                    $('#returnFormContent').html(response);
                    $('#returnModal').modal('show');
                    
                    // راه‌اندازی امضای عودت
                    var returnCanvas = document.getElementById('return-signature-pad');
                    if (returnCanvas) {
                        var returnSignaturePad = new SignaturePad(returnCanvas);
                        
                        // ذخیره امضا قبل از ارسال
                        $('#returnModal form').on('submit', function() {
                            if (!returnSignaturePad.isEmpty()) {
                                $('#return_signature_data').val(returnSignaturePad.toDataURL());
                            }
                        });
                    }
                },
                error: function() {
                    alert('خطا در دریافت فرم عودت');
                }
            });
        }

        // ریست فرم
        function resetForm() {
            document.getElementById('handoverForm').reset();
            document.querySelectorAll('.car-select-card').forEach(c => c.classList.remove('selected'));
            document.getElementById('selected_car_id').value = '';
            clearSignature();
        }
    </script>
</body>
</html>