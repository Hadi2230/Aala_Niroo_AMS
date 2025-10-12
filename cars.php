<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// ثبت لاگ
logAction($pdo, 'VIEW_CARS', 'مشاهده صفحه مدیریت خودروهای شرکتی');

// افزودن خودرو جدید
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_car'])) {
    verifyCsrfToken();
    
    try {
        $pdo->beginTransaction();
        
        // تولید کد خودرو
        $car_code = 'CAR' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // دریافت داده‌های فرم
        $plate_number = sanitizeInput($_POST['plate_number']);
        $vin_number = sanitizeInput($_POST['vin_number']);
        $engine_number = sanitizeInput($_POST['engine_number']);
        $brand = sanitizeInput($_POST['brand']);
        $model = sanitizeInput($_POST['model']);
        $year = intval($_POST['year']);
        $color = sanitizeInput($_POST['color']);
        $fuel_type = sanitizeInput($_POST['fuel_type']);
        $transmission_type = sanitizeInput($_POST['transmission_type']);
        $body_type = sanitizeInput($_POST['body_type']);
        $seats_count = intval($_POST['seats_count']);
        $current_odometer = floatval($_POST['current_odometer']);
        $purchase_date = sanitizeInput($_POST['purchase_date']);
        $purchase_price = floatval($_POST['purchase_price']);
        $insurance_company = sanitizeInput($_POST['insurance_company']);
        $insurance_policy_number = sanitizeInput($_POST['insurance_policy_number']);
        $insurance_expiry_date = sanitizeInput($_POST['insurance_expiry_date']);
        $third_party_insurance_expiry = sanitizeInput($_POST['third_party_insurance_expiry']);
        $body_insurance_expiry = sanitizeInput($_POST['body_insurance_expiry']);
        $technical_inspection_expiry = sanitizeInput($_POST['technical_inspection_expiry']);
        $has_gps = isset($_POST['has_gps']) ? 1 : 0;
        $gps_device_id = sanitizeInput($_POST['gps_device_id'] ?? '');
        $notes = sanitizeInput($_POST['notes'] ?? '');
        
        // تولید QR Code
        $qr_code = 'QR_' . $car_code . '_' . time();
        
        // آپلود تصاویر
        $upload_dir = 'uploads/cars/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $front_image = '';
        $back_image = '';
        $side_image = '';
        $interior_image = '';
        
        if (isset($_FILES['front_image']) && $_FILES['front_image']['error'] === 0) {
            $front_image = uploadFile($_FILES['front_image'], $upload_dir);
        }
        if (isset($_FILES['back_image']) && $_FILES['back_image']['error'] === 0) {
            $back_image = uploadFile($_FILES['back_image'], $upload_dir);
        }
        if (isset($_FILES['side_image']) && $_FILES['side_image']['error'] === 0) {
            $side_image = uploadFile($_FILES['side_image'], $upload_dir);
        }
        if (isset($_FILES['interior_image']) && $_FILES['interior_image']['error'] === 0) {
            $interior_image = uploadFile($_FILES['interior_image'], $upload_dir);
        }
        
        // درج در دیتابیس
        $stmt = $pdo->prepare("INSERT INTO company_cars (
            car_code, plate_number, vin_number, engine_number, brand, model, year, color,
            fuel_type, transmission_type, body_type, seats_count, current_odometer,
            purchase_date, purchase_price, current_value, insurance_company, insurance_policy_number,
            insurance_expiry_date, third_party_insurance_expiry, body_insurance_expiry, 
            technical_inspection_expiry, has_gps, gps_device_id, front_image, back_image,
            side_image, interior_image, notes, qr_code, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $car_code, $plate_number, $vin_number, $engine_number, $brand, $model, $year, $color,
            $fuel_type, $transmission_type, $body_type, $seats_count, $current_odometer,
            $purchase_date, $purchase_price, $purchase_price, $insurance_company, $insurance_policy_number,
            $insurance_expiry_date, $third_party_insurance_expiry, $body_insurance_expiry,
            $technical_inspection_expiry, $has_gps, $gps_device_id, $front_image, $back_image,
            $side_image, $interior_image, $notes, $qr_code, $_SESSION['user_id']
        ]);
        
        $pdo->commit();
        $success_message = "خودرو با موفقیت ثبت شد.";
        logAction($pdo, 'ADD_CAR', "افزودن خودرو جدید: $brand $model - $plate_number");
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "خطا در ثبت خودرو: " . $e->getMessage();
        error_log($e->getMessage());
    }
}

// ویرایش خودرو
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_car'])) {
    verifyCsrfToken();
    
    try {
        $car_id = intval($_POST['car_id']);
        $plate_number = sanitizeInput($_POST['plate_number']);
        $current_odometer = floatval($_POST['current_odometer']);
        $current_fuel_level = intval($_POST['current_fuel_level']);
        $current_location = sanitizeInput($_POST['current_location']);
        $current_status = sanitizeInput($_POST['current_status']);
        $insurance_expiry_date = sanitizeInput($_POST['insurance_expiry_date']);
        $third_party_insurance_expiry = sanitizeInput($_POST['third_party_insurance_expiry']);
        $body_insurance_expiry = sanitizeInput($_POST['body_insurance_expiry']);
        $technical_inspection_expiry = sanitizeInput($_POST['technical_inspection_expiry']);
        
        $stmt = $pdo->prepare("UPDATE company_cars SET 
            current_odometer = ?, current_fuel_level = ?, current_location = ?, 
            current_status = ?, insurance_expiry_date = ?, third_party_insurance_expiry = ?,
            body_insurance_expiry = ?, technical_inspection_expiry = ?
            WHERE id = ?");
        
        $stmt->execute([
            $current_odometer, $current_fuel_level, $current_location, $current_status,
            $insurance_expiry_date, $third_party_insurance_expiry, $body_insurance_expiry,
            $technical_inspection_expiry, $car_id
        ]);
        
        $success_message = "اطلاعات خودرو به‌روزرسانی شد.";
        logAction($pdo, 'EDIT_CAR', "ویرایش خودرو: $plate_number");
        
    } catch (Exception $e) {
        $error_message = "خطا در به‌روزرسانی: " . $e->getMessage();
    }
}

// دریافت لیست خودروها
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';

$query = "SELECT c.*, u.full_name as created_by_name, 
          DATEDIFF(insurance_expiry_date, CURDATE()) as insurance_days,
          DATEDIFF(technical_inspection_expiry, CURDATE()) as inspection_days
          FROM company_cars c
          LEFT JOIN users u ON c.created_by = u.id
          WHERE c.is_active = 1";

$params = [];

if ($search) {
    $query .= " AND (c.plate_number LIKE ? OR c.brand LIKE ? OR c.model LIKE ? OR c.car_code LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if ($status_filter) {
    $query .= " AND c.current_status = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY c.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$cars = $stmt->fetchAll();

// آمار خودروها
$stats = [];
$stats['total'] = $pdo->query("SELECT COUNT(*) FROM company_cars WHERE is_active = 1")->fetchColumn();
$stats['available'] = $pdo->query("SELECT COUNT(*) FROM company_cars WHERE current_status = 'در دسترس' AND is_active = 1")->fetchColumn();
$stats['in_use'] = $pdo->query("SELECT COUNT(*) FROM company_cars WHERE current_status = 'در حال استفاده' AND is_active = 1")->fetchColumn();
$stats['maintenance'] = $pdo->query("SELECT COUNT(*) FROM company_cars WHERE current_status = 'در تعمیرگاه' AND is_active = 1")->fetchColumn();
$stats['reserved'] = $pdo->query("SELECT COUNT(*) FROM company_cars WHERE current_status = 'رزرو شده' AND is_active = 1")->fetchColumn();

?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت خودروهای شرکتی - سامانه اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .car-card {
            border-radius: 10px;
            transition: transform 0.3s;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .car-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .status-badge {
            font-size: 0.85rem;
            padding: 0.35em 0.65em;
        }
        .status-available { background-color: #28a745; color: white; }
        .status-in-use { background-color: #17a2b8; color: white; }
        .status-maintenance { background-color: #ffc107; color: black; }
        .status-reserved { background-color: #6c757d; color: white; }
        .car-image {
            height: 200px;
            object-fit: cover;
            border-radius: 10px 10px 0 0;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .alert-icon {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        .tab-content {
            padding: 20px;
            background: white;
            border-radius: 0 0 10px 10px;
        }
        .nav-tabs .nav-link {
            color: #495057;
            border-radius: 10px 10px 0 0;
        }
        .nav-tabs .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }
        .qr-code {
            width: 100px;
            height: 100px;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container-fluid mt-4">
        <!-- آمار کلی -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6">
                <div class="stat-card">
                    <h3><?php echo $stats['total']; ?></h3>
                    <p class="mb-0">کل خودروها</p>
                    <i class="bi bi-car-front float-end fs-1 opacity-50"></i>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                    <h3><?php echo $stats['available']; ?></h3>
                    <p class="mb-0">در دسترس</p>
                    <i class="bi bi-check-circle float-end fs-1 opacity-50"></i>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);">
                    <h3><?php echo $stats['in_use']; ?></h3>
                    <p class="mb-0">در حال استفاده</p>
                    <i class="bi bi-person-fill float-end fs-1 opacity-50"></i>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card" style="background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);">
                    <h3><?php echo $stats['maintenance']; ?></h3>
                    <p class="mb-0">در تعمیرگاه</p>
                    <i class="bi bi-tools float-end fs-1 opacity-50"></i>
                </div>
            </div>
        </div>

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

        <!-- نوار ابزار -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <form method="GET" class="input-group">
                            <input type="text" name="search" class="form-control" 
                                   placeholder="جستجو (پلاک، برند، مدل، کد خودرو)" 
                                   value="<?php echo htmlspecialchars($search); ?>">
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-search"></i> جستجو
                            </button>
                        </form>
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select" onchange="window.location='?status='+this.value">
                            <option value="">همه وضعیت‌ها</option>
                            <option value="در دسترس" <?php echo $status_filter == 'در دسترس' ? 'selected' : ''; ?>>در دسترس</option>
                            <option value="در حال استفاده" <?php echo $status_filter == 'در حال استفاده' ? 'selected' : ''; ?>>در حال استفاده</option>
                            <option value="در تعمیرگاه" <?php echo $status_filter == 'در تعمیرگاه' ? 'selected' : ''; ?>>در تعمیرگاه</option>
                            <option value="رزرو شده" <?php echo $status_filter == 'رزرو شده' ? 'selected' : ''; ?>>رزرو شده</option>
                        </select>
                    </div>
                    <div class="col-md-5 text-end">
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addCarModal">
                            <i class="bi bi-plus-circle"></i> افزودن خودرو جدید
                        </button>
                        <a href="car_handovers.php" class="btn btn-info">
                            <i class="bi bi-arrow-left-right"></i> تحویل و عودت
                        </a>
                        <a href="car_reports.php" class="btn btn-warning">
                            <i class="bi bi-file-earmark-text"></i> گزارشات
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- لیست خودروها -->
        <div class="row">
            <?php foreach ($cars as $car): 
                $status_class = '';
                switch($car['current_status']) {
                    case 'در دسترس': $status_class = 'status-available'; break;
                    case 'در حال استفاده': $status_class = 'status-in-use'; break;
                    case 'در تعمیرگاه': $status_class = 'status-maintenance'; break;
                    case 'رزرو شده': $status_class = 'status-reserved'; break;
                }
                
                // هشدارها
                $has_alerts = false;
                $alert_messages = [];
                
                if ($car['insurance_days'] !== null && $car['insurance_days'] <= 30) {
                    $has_alerts = true;
                    $alert_messages[] = "بیمه: {$car['insurance_days']} روز مانده";
                }
                
                if ($car['inspection_days'] !== null && $car['inspection_days'] <= 30) {
                    $has_alerts = true;
                    $alert_messages[] = "معاینه فنی: {$car['inspection_days']} روز مانده";
                }
            ?>
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card car-card h-100">
                    <?php if ($car['front_image']): ?>
                        <img src="<?php echo htmlspecialchars($car['front_image']); ?>" 
                             class="car-image" alt="<?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?>">
                    <?php else: ?>
                        <div class="car-image bg-secondary d-flex align-items-center justify-content-center">
                            <i class="bi bi-car-front fs-1 text-white"></i>
                        </div>
                    <?php endif; ?>
                    
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="card-title mb-0">
                                <?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?>
                                <?php if ($car['year']): ?>
                                    <small class="text-muted">(<?php echo $car['year']; ?>)</small>
                                <?php endif; ?>
                            </h5>
                            <?php if ($has_alerts): ?>
                                <i class="bi bi-exclamation-triangle-fill text-warning alert-icon" 
                                   title="<?php echo implode(' | ', $alert_messages); ?>"></i>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <span class="badge <?php echo $status_class; ?> status-badge">
                                <?php echo htmlspecialchars($car['current_status']); ?>
                            </span>
                            <?php if ($car['fuel_type']): ?>
                                <span class="badge bg-secondary ms-2">
                                    <?php echo htmlspecialchars($car['fuel_type']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <ul class="list-unstyled small">
                            <li><i class="bi bi-card-text me-2"></i>پلاک: 
                                <strong><?php echo htmlspecialchars($car['plate_number']); ?></strong>
                            </li>
                            <li><i class="bi bi-qr-code me-2"></i>کد: 
                                <strong><?php echo htmlspecialchars($car['car_code']); ?></strong>
                            </li>
                            <li><i class="bi bi-speedometer2 me-2"></i>کیلومتر: 
                                <strong><?php echo number_format($car['current_odometer']); ?></strong> کیلومتر
                            </li>
                            <?php if ($car['current_fuel_level']): ?>
                                <li><i class="bi bi-fuel-pump me-2"></i>سوخت: 
                                    <strong><?php echo $car['current_fuel_level']; ?>%</strong>
                                </li>
                            <?php endif; ?>
                            <?php if ($car['current_location']): ?>
                                <li><i class="bi bi-geo-alt me-2"></i>موقعیت: 
                                    <?php echo htmlspecialchars($car['current_location']); ?>
                                </li>
                            <?php endif; ?>
                        </ul>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button class="btn btn-sm btn-primary" 
                                    onclick="viewCarDetails(<?php echo $car['id']; ?>)">
                                <i class="bi bi-eye"></i> جزئیات
                            </button>
                            <button class="btn btn-sm btn-warning" 
                                    onclick="editCar(<?php echo $car['id']; ?>)">
                                <i class="bi bi-pencil"></i> ویرایش
                            </button>
                            <?php if ($car['current_status'] == 'در دسترس'): ?>
                                <a href="car_handovers.php?car_id=<?php echo $car['id']; ?>" 
                                   class="btn btn-sm btn-success">
                                    <i class="bi bi-key"></i> تحویل
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($cars)): ?>
            <div class="alert alert-info text-center">
                <i class="bi bi-info-circle fs-1 d-block mb-3"></i>
                <h4>هیچ خودرویی یافت نشد</h4>
                <p>برای شروع، یک خودرو جدید اضافه کنید.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- مودال افزودن خودرو -->
    <div class="modal fade" id="addCarModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-car-front me-2"></i>افزودن خودرو جدید
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <?php echo csrf_field(); ?>
                        
                        <!-- تب‌ها -->
                        <ul class="nav nav-tabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#basic-info">
                                    <i class="bi bi-info-circle"></i> اطلاعات پایه
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#technical-info">
                                    <i class="bi bi-gear"></i> مشخصات فنی
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#insurance-info">
                                    <i class="bi bi-shield-check"></i> بیمه و اسناد
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#images-info">
                                    <i class="bi bi-images"></i> تصاویر
                                </a>
                            </li>
                        </ul>
                        
                        <div class="tab-content">
                            <!-- اطلاعات پایه -->
                            <div class="tab-pane fade show active" id="basic-info">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">شماره پلاک *</label>
                                        <input type="text" name="plate_number" class="form-control" required 
                                               placeholder="مثال: ۱۲ایران۳۴۵ع۶۷">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">شماره شاسی (VIN)</label>
                                        <input type="text" name="vin_number" class="form-control" 
                                               placeholder="17 کاراکتر">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">شماره موتور</label>
                                        <input type="text" name="engine_number" class="form-control">
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <label class="form-label">برند *</label>
                                        <select name="brand" class="form-select" required>
                                            <option value="">انتخاب کنید</option>
                                            <option value="پژو">پژو</option>
                                            <option value="پراید">پراید</option>
                                            <option value="سمند">سمند</option>
                                            <option value="پارس">پارس</option>
                                            <option value="تیبا">تیبا</option>
                                            <option value="ساینا">ساینا</option>
                                            <option value="کوییک">کوییک</option>
                                            <option value="دنا">دنا</option>
                                            <option value="رانا">رانا</option>
                                            <option value="تارا">تارا</option>
                                            <option value="شاهین">شاهین</option>
                                            <option value="هایما">هایما</option>
                                            <option value="جک">جک</option>
                                            <option value="چری">چری</option>
                                            <option value="ام‌وی‌ام">ام‌وی‌ام</option>
                                            <option value="تویوتا">تویوتا</option>
                                            <option value="نیسان">نیسان</option>
                                            <option value="مزدا">مزدا</option>
                                            <option value="هیوندای">هیوندای</option>
                                            <option value="کیا">کیا</option>
                                            <option value="سایر">سایر</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">مدل *</label>
                                        <input type="text" name="model" class="form-control" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">سال ساخت</label>
                                        <input type="number" name="year" class="form-control" 
                                               min="1390" max="<?php echo date('Y') + 1; ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">رنگ</label>
                                        <input type="text" name="color" class="form-control">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">کیلومتر فعلی *</label>
                                        <input type="number" name="current_odometer" class="form-control" 
                                               required min="0" step="0.01">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- مشخصات فنی -->
                            <div class="tab-pane fade" id="technical-info">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label">نوع سوخت</label>
                                        <select name="fuel_type" class="form-select">
                                            <option value="بنزینی">بنزینی</option>
                                            <option value="دیزلی">دیزلی</option>
                                            <option value="دوگانه سوز">دوگانه سوز</option>
                                            <option value="هیبریدی">هیبریدی</option>
                                            <option value="برقی">برقی</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">نوع گیربکس</label>
                                        <select name="transmission_type" class="form-select">
                                            <option value="دستی">دستی</option>
                                            <option value="اتوماتیک">اتوماتیک</option>
                                            <option value="CVT">CVT</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">نوع بدنه</label>
                                        <select name="body_type" class="form-select">
                                            <option value="سدان">سدان</option>
                                            <option value="هاچ‌بک">هاچ‌بک</option>
                                            <option value="SUV">SUV</option>
                                            <option value="وانت">وانت</option>
                                            <option value="ون">ون</option>
                                            <option value="کامیون">کامیون</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">تعداد صندلی</label>
                                        <input type="number" name="seats_count" class="form-control" 
                                               value="5" min="2" max="50">
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label class="form-label">تاریخ خرید</label>
                                        <input type="date" name="purchase_date" class="form-control">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">قیمت خرید (ریال)</label>
                                        <input type="number" name="purchase_price" class="form-control" min="0">
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check mt-4">
                                            <input type="checkbox" name="has_gps" class="form-check-input" 
                                                   id="has_gps" onchange="toggleGPS()">
                                            <label class="form-check-label" for="has_gps">
                                                دارای سیستم ردیاب GPS
                                            </label>
                                        </div>
                                        <input type="text" name="gps_device_id" class="form-control mt-2" 
                                               placeholder="شناسه دستگاه GPS" style="display:none" id="gps_device_id">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- بیمه و اسناد -->
                            <div class="tab-pane fade" id="insurance-info">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">شرکت بیمه</label>
                                        <select name="insurance_company" class="form-select">
                                            <option value="">انتخاب کنید</option>
                                            <option value="ایران">ایران</option>
                                            <option value="آسیا">آسیا</option>
                                            <option value="دانا">دانا</option>
                                            <option value="پارسیان">پارسیان</option>
                                            <option value="پاسارگاد">پاسارگاد</option>
                                            <option value="رازی">رازی</option>
                                            <option value="کارآفرین">کارآفرین</option>
                                            <option value="سینا">سینا</option>
                                            <option value="ما">ما</option>
                                            <option value="ملت">ملت</option>
                                            <option value="نوین">نوین</option>
                                            <option value="سایر">سایر</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">شماره بیمه‌نامه</label>
                                        <input type="text" name="insurance_policy_number" class="form-control">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">تاریخ انقضای بیمه</label>
                                        <input type="date" name="insurance_expiry_date" class="form-control">
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label class="form-label">انقضای بیمه شخص ثالث</label>
                                        <input type="date" name="third_party_insurance_expiry" class="form-control">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">انقضای بیمه بدنه</label>
                                        <input type="date" name="body_insurance_expiry" class="form-control">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">انقضای معاینه فنی</label>
                                        <input type="date" name="technical_inspection_expiry" class="form-control">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- تصاویر -->
                            <div class="tab-pane fade" id="images-info">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">تصویر جلو</label>
                                        <input type="file" name="front_image" class="form-control" accept="image/*">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">تصویر عقب</label>
                                        <input type="file" name="back_image" class="form-control" accept="image/*">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">تصویر کنار</label>
                                        <input type="file" name="side_image" class="form-control" accept="image/*">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">تصویر داخل</label>
                                        <input type="file" name="interior_image" class="form-control" accept="image/*">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">یادداشت‌ها</label>
                                        <textarea name="notes" class="form-control" rows="3"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" name="add_car" class="btn btn-primary">
                            <i class="bi bi-save"></i> ذخیره خودرو
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- مودال جزئیات خودرو -->
    <div class="modal fade" id="carDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">جزئیات خودرو</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="carDetailsContent">
                    <!-- محتوا با AJAX بارگذاری می‌شود -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('.form-select').select2({
                theme: 'bootstrap-5',
                width: '100%'
            });
        });

        function toggleGPS() {
            const checkbox = document.getElementById('has_gps');
            const gpsInput = document.getElementById('gps_device_id');
            gpsInput.style.display = checkbox.checked ? 'block' : 'none';
        }

        function viewCarDetails(carId) {
            $.ajax({
                url: 'ajax/get_car_details.php',
                type: 'GET',
                data: { id: carId },
                success: function(response) {
                    $('#carDetailsContent').html(response);
                    $('#carDetailsModal').modal('show');
                },
                error: function() {
                    alert('خطا در دریافت اطلاعات خودرو');
                }
            });
        }

        function editCar(carId) {
            window.location.href = 'edit_car.php?id=' + carId;
        }

        // نمایش هشدارها
        document.querySelectorAll('.alert-icon').forEach(icon => {
            icon.addEventListener('click', function() {
                alert(this.getAttribute('title'));
            });
        });
    </script>
</body>
</html>