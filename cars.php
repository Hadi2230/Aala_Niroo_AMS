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
        
        $data = [
            'car_plate' => sanitizeInput($_POST['car_plate']),
            'car_name' => sanitizeInput($_POST['car_name']),
            'car_model' => sanitizeInput($_POST['car_model']),
            'car_year' => (int)$_POST['car_year'],
            'car_color' => sanitizeInput($_POST['car_color']),
            'car_type' => sanitizeInput($_POST['car_type']),
            'engine_number' => sanitizeInput($_POST['engine_number']),
            'chassis_number' => sanitizeInput($_POST['chassis_number']),
            'insurance_number' => sanitizeInput($_POST['insurance_number']),
            'insurance_expiry' => $_POST['insurance_expiry'],
            'technical_inspection_expiry' => $_POST['technical_inspection_expiry'],
            'fuel_type' => sanitizeInput($_POST['fuel_type']),
            'fuel_capacity' => (float)$_POST['fuel_capacity'],
            'current_mileage' => (int)$_POST['current_mileage'],
            'max_mileage' => (int)$_POST['max_mileage'],
            'purchase_date' => $_POST['purchase_date'],
            'purchase_price' => (float)$_POST['purchase_price'],
            'current_value' => (float)$_POST['current_value'],
            'status' => sanitizeInput($_POST['status']),
            'location' => sanitizeInput($_POST['location']),
            'notes' => sanitizeInput($_POST['notes'])
        ];
        
        $car_id = addCompanyCar($pdo, $data);
        
        $pdo->commit();
        
        $_SESSION['success'] = "خودرو با موفقیت افزوده شد!";
        logAction($pdo, 'ADD_CAR', "افزودن خودرو جدید: " . $data['car_name'] . " (پلاک: " . $data['car_plate'] . ")");
        
        header('Location: cars.php');
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "خطا در افزودن خودرو: " . $e->getMessage();
        logAction($pdo, 'ADD_CAR_ERROR', "خطا در افزودن خودرو: " . $e->getMessage());
    }
}

// حذف خودرو
if (isset($_GET['delete_id'])) {
    checkPermission('ادمین');
    
    $delete_id = (int)$_GET['delete_id'];
    
    try {
        // دریافت اطلاعات خودرو برای ثبت در لاگ
        $stmt = $pdo->prepare("SELECT car_name, car_plate FROM company_cars WHERE id = ?");
        $stmt->execute([$delete_id]);
        $car = $stmt->fetch();
        
        if ($car) {
            $stmt = $pdo->prepare("DELETE FROM company_cars WHERE id = ?");
            $stmt->execute([$delete_id]);
            
            $_SESSION['success'] = "خودرو با موفقیت حذف شد!";
            logAction($pdo, 'DELETE_CAR', "حذف خودرو: " . $car['car_name'] . " (پلاک: " . $car['car_plate'] . ")");
        } else {
            $_SESSION['error'] = "خودرو مورد نظر یافت نشد!";
        }
        
        header('Location: cars.php');
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = "خطا در حذف خودرو: " . $e->getMessage();
        logAction($pdo, 'DELETE_CAR_ERROR', "خطا در حذف خودرو ID: $delete_id - " . $e->getMessage());
    }
}

// جستجو و فیلتر
$search = $_GET['search'] ?? '';
$type_filter = $_GET['type_filter'] ?? '';
$status_filter = $_GET['status_filter'] ?? '';

// ساخت کوئری بر اساس فیلترها
$query = "SELECT * FROM company_cars WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (car_name LIKE ? OR car_plate LIKE ? OR car_model LIKE ? OR engine_number LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($type_filter)) {
    $query .= " AND car_type = ?";
    $params[] = $type_filter;
}

if (!empty($status_filter)) {
    $query .= " AND status = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$cars = $stmt->fetchAll();

// دریافت آمار
$total_cars = $pdo->query("SELECT COUNT(*) as total FROM company_cars")->fetch()['total'];
$active_cars = $pdo->query("SELECT COUNT(*) as total FROM company_cars WHERE status = 'فعال'")->fetch()['total'];
$assigned_cars = $pdo->query("SELECT COUNT(DISTINCT car_id) as total FROM car_assignments WHERE assignment_status = 'فعال'")->fetch()['total'];
$filtered_count = count($cars);
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت خودروهای شرکتی - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
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
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .stats-card h3 {
            font-size: 2.5rem;
            font-weight: bold;
            margin: 0;
        }
        .stats-card p {
            margin: 5px 0 0 0;
            opacity: 0.9;
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
        .car-type-icon {
            font-size: 1.2rem;
            margin-left: 5px;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-car"></i> مدیریت خودروهای شرکتی</h2>
                    <div>
                        <span class="filter-active">
                            <i class="fas fa-filter"></i> 
                            <?= $filtered_count ?> از <?= $total_cars ?> مورد
                        </span>
                    </div>
                </div>

                <!-- آمار کلی -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="stats-card">
                            <h3><?= $total_cars ?></h3>
                            <p><i class="fas fa-car"></i> کل خودروها</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <h3><?= $active_cars ?></h3>
                            <p><i class="fas fa-check-circle"></i> خودروهای فعال</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <h3><?= $assigned_cars ?></h3>
                            <p><i class="fas fa-user-check"></i> خودروهای منتسب</p>
                        </div>
                    </div>
                </div>

                <!-- جستجو و فیلتر -->
                <div class="card search-box">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <input type="text" name="search" class="form-control" placeholder="جستجو بر اساس نام، پلاک، مدل یا شماره موتور..." value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="col-md-2">
                                <select name="type_filter" class="form-select">
                                    <option value="">همه انواع</option>
                                    <option value="سواری" <?= $type_filter == 'سواری' ? 'selected' : '' ?>>سواری</option>
                                    <option value="وانت" <?= $type_filter == 'وانت' ? 'selected' : '' ?>>وانت</option>
                                    <option value="کامیون" <?= $type_filter == 'کامیون' ? 'selected' : '' ?>>کامیون</option>
                                    <option value="مینی‌بوس" <?= $type_filter == 'مینی‌بوس' ? 'selected' : '' ?>>مینی‌بوس</option>
                                    <option value="اتوبوس" <?= $type_filter == 'اتوبوس' ? 'selected' : '' ?>>اتوبوس</option>
                                    <option value="سایر" <?= $type_filter == 'سایر' ? 'selected' : '' ?>>سایر</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="status_filter" class="form-select">
                                    <option value="">همه وضعیت‌ها</option>
                                    <option value="فعال" <?= $status_filter == 'فعال' ? 'selected' : '' ?>>فعال</option>
                                    <option value="غیرفعال" <?= $status_filter == 'غیرفعال' ? 'selected' : '' ?>>غیرفعال</option>
                                    <option value="در تعمیر" <?= $status_filter == 'در تعمیر' ? 'selected' : '' ?>>در تعمیر</option>
                                    <option value="آماده تحویل" <?= $status_filter == 'آماده تحویل' ? 'selected' : '' ?>>آماده تحویل</option>
                                    <option value="تحویل داده شده" <?= $status_filter == 'تحویل داده شده' ? 'selected' : '' ?>>تحویل داده شده</option>
                                    <option value="برگشت داده شده" <?= $status_filter == 'برگشت داده شده' ? 'selected' : '' ?>>برگشت داده شده</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-filter"></i> اعمال فیلتر
                                </button>
                            </div>
                            <div class="col-md-2">
                                <a href="cars.php" class="btn btn-outline-secondary w-100">
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

                <!-- فرم افزودن خودرو -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-plus-circle"></i> افزودن خودرو جدید</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="carForm">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            
                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="car_plate" class="form-label">شماره پلاک *</label>
                                        <input type="text" class="form-control" id="car_plate" name="car_plate" required>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="car_name" class="form-label">نام خودرو *</label>
                                        <input type="text" class="form-control" id="car_name" name="car_name" required>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="car_model" class="form-label">مدل خودرو *</label>
                                        <input type="text" class="form-control" id="car_model" name="car_model" required>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="car_year" class="form-label">سال تولید</label>
                                        <input type="number" class="form-control" id="car_year" name="car_year" min="1900" max="2030">
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="car_color" class="form-label">رنگ</label>
                                        <input type="text" class="form-control" id="car_color" name="car_color">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="car_type" class="form-label">نوع خودرو *</label>
                                        <select class="form-select" id="car_type" name="car_type" required>
                                            <option value="">-- انتخاب کنید --</option>
                                            <option value="سواری">سواری</option>
                                            <option value="وانت">وانت</option>
                                            <option value="کامیون">کامیون</option>
                                            <option value="مینی‌بوس">مینی‌بوس</option>
                                            <option value="اتوبوس">اتوبوس</option>
                                            <option value="سایر">سایر</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="fuel_type" class="form-label">نوع سوخت *</label>
                                        <select class="form-select" id="fuel_type" name="fuel_type" required>
                                            <option value="">-- انتخاب کنید --</option>
                                            <option value="بنزین">بنزین</option>
                                            <option value="گازوئیل">گازوئیل</option>
                                            <option value="گاز">گاز</option>
                                            <option value="هیبرید">هیبرید</option>
                                            <option value="برقی">برقی</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="status" class="form-label">وضعیت *</label>
                                        <select class="form-select" id="status" name="status" required>
                                            <option value="فعال">فعال</option>
                                            <option value="غیرفعال">غیرفعال</option>
                                            <option value="در تعمیر">در تعمیر</option>
                                            <option value="آماده تحویل">آماده تحویل</option>
                                            <option value="تحویل داده شده">تحویل داده شده</option>
                                            <option value="برگشت داده شده">برگشت داده شده</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="engine_number" class="form-label">شماره موتور</label>
                                        <input type="text" class="form-control" id="engine_number" name="engine_number">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="chassis_number" class="form-label">شماره شاسی</label>
                                        <input type="text" class="form-control" id="chassis_number" name="chassis_number">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="insurance_number" class="form-label">شماره بیمه</label>
                                        <input type="text" class="form-control" id="insurance_number" name="insurance_number">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="insurance_expiry" class="form-label">تاریخ انقضای بیمه</label>
                                        <input type="date" class="form-control" id="insurance_expiry" name="insurance_expiry">
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="technical_inspection_expiry" class="form-label">تاریخ انقضای معاینه فنی</label>
                                        <input type="date" class="form-control" id="technical_inspection_expiry" name="technical_inspection_expiry">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="fuel_capacity" class="form-label">ظرفیت باک (لیتر)</label>
                                        <input type="number" class="form-control" id="fuel_capacity" name="fuel_capacity" step="0.01">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="current_mileage" class="form-label">کیلومتر فعلی</label>
                                        <input type="number" class="form-control" id="current_mileage" name="current_mileage" value="0">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="max_mileage" class="form-label">حداکثر کیلومتر</label>
                                        <input type="number" class="form-control" id="max_mileage" name="max_mileage">
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="purchase_date" class="form-label">تاریخ خرید</label>
                                        <input type="date" class="form-control" id="purchase_date" name="purchase_date">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="purchase_price" class="form-label">قیمت خرید (ریال)</label>
                                        <input type="number" class="form-control" id="purchase_price" name="purchase_price" step="0.01">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="current_value" class="form-label">ارزش فعلی (ریال)</label>
                                        <input type="number" class="form-control" id="current_value" name="current_value" step="0.01">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="location" class="form-label">مکان فعلی</label>
                                        <input type="text" class="form-control" id="location" name="location">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="notes" class="form-label">توضیحات</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                            </div>

                            <button type="submit" name="add_car" class="btn btn-success">
                                <i class="fas fa-save"></i> ثبت خودرو
                            </button>
                        </form>
                    </div>
                </div>

                <!-- لیست خودروها -->
                <div class="card mt-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-list"></i> لیست خودروهای شرکتی</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($cars) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>پلاک</th>
                                            <th>نام خودرو</th>
                                            <th>مدل</th>
                                            <th>نوع</th>
                                            <th>رنگ</th>
                                            <th>وضعیت</th>
                                            <th>کیلومتر</th>
                                            <th>مکان</th>
                                            <th>عملیات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cars as $car): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($car['car_plate']) ?></strong>
                                            </td>
                                            <td><?= htmlspecialchars($car['car_name']) ?></td>
                                            <td><?= htmlspecialchars($car['car_model']) ?></td>
                                            <td>
                                                <?php
                                                $type_icons = [
                                                    'سواری' => 'fas fa-car',
                                                    'وانت' => 'fas fa-truck',
                                                    'کامیون' => 'fas fa-truck',
                                                    'مینی‌بوس' => 'fas fa-bus',
                                                    'اتوبوس' => 'fas fa-bus',
                                                    'سایر' => 'fas fa-car'
                                                ];
                                                $icon = $type_icons[$car['car_type']] ?? 'fas fa-car';
                                                ?>
                                                <i class="<?= $icon ?> car-type-icon"></i>
                                                <?= htmlspecialchars($car['car_type']) ?>
                                            </td>
                                            <td><?= htmlspecialchars($car['car_color']) ?></td>
                                            <td>
                                                <?php
                                                $status_class = 'secondary';
                                                if ($car['status'] == 'فعال') $status_class = 'success';
                                                if ($car['status'] == 'غیرفعال') $status_class = 'danger';
                                                if ($car['status'] == 'در تعمیر') $status_class = 'warning';
                                                if ($car['status'] == 'آماده تحویل') $status_class = 'info';
                                                if ($car['status'] == 'تحویل داده شده') $status_class = 'primary';
                                                if ($car['status'] == 'برگشت داده شده') $status_class = 'dark';
                                                ?>
                                                <span class="badge bg-<?= $status_class ?> badge-status"><?= $car['status'] ?></span>
                                            </td>
                                            <td><?= number_format($car['current_mileage']) ?> کیلومتر</td>
                                            <td><?= htmlspecialchars($car['location']) ?></td>
                                            <td class="action-buttons">
                                                <a href="car_details.php?id=<?= $car['id'] ?>" class="btn btn-sm btn-info" title="مشاهده جزئیات">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="car_assignments.php?car_id=<?= $car['id'] ?>" class="btn btn-sm btn-primary" title="مدیریت انتساب‌ها">
                                                    <i class="fas fa-user-check"></i>
                                                </a>
                                                <a href="car_maintenance.php?car_id=<?= $car['id'] ?>" class="btn btn-sm btn-warning" title="تعمیرات">
                                                    <i class="fas fa-wrench"></i>
                                                </a>
                                                <a href="edit_car.php?id=<?= $car['id'] ?>" class="btn btn-sm btn-secondary" title="ویرایش">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if ($_SESSION['role'] == 'ادمین'): ?>
                                                <a href="cars.php?delete_id=<?= $car['id'] ?>" class="btn btn-sm btn-danger" title="حذف"
                                                   onclick="return confirm('آیا از حذف این خودرو مطمئن هستید؟')">
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
                                <i class="fas fa-info-circle"></i> هیچ خودرویی یافت نشد.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>