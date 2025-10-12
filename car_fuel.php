<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// ثبت لاگ
logAction($pdo, 'VIEW_CAR_FUEL', 'مشاهده صفحه مدیریت سوخت خودروها');

// افزودن ثبت سوخت جدید
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_fuel_record'])) {
    verifyCsrfToken();
    
    try {
        $pdo->beginTransaction();
        
        $data = [
            'car_id' => (int)$_POST['car_id'],
            'fuel_date' => $_POST['fuel_date'],
            'fuel_type' => sanitizeInput($_POST['fuel_type']),
            'fuel_amount' => (float)$_POST['fuel_amount'],
            'fuel_cost' => (float)$_POST['fuel_cost'],
            'fuel_station' => sanitizeInput($_POST['fuel_station']),
            'mileage_before' => (int)$_POST['mileage_before'],
            'mileage_after' => (int)$_POST['mileage_after'],
            'fuel_efficiency' => 0, // محاسبه خواهد شد
            'driver_id' => $_POST['driver_id'] ? (int)$_POST['driver_id'] : null,
            'purpose' => sanitizeInput($_POST['purpose']),
            'receipt_path' => '',
            'notes' => sanitizeInput($_POST['notes'])
        ];
        
        // محاسبه بازدهی سوخت
        if ($data['mileage_after'] > $data['mileage_before'] && $data['fuel_amount'] > 0) {
            $distance = $data['mileage_after'] - $data['mileage_before'];
            $data['fuel_efficiency'] = round($distance / $data['fuel_amount'], 2);
        }
        
        // آپلود فیش
        if (!empty($_FILES['receipt']['name'])) {
            try {
                $upload_dir = 'uploads/car_fuel/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $data['receipt_path'] = uploadFile($_FILES['receipt'], $upload_dir);
            } catch (Exception $e) {
                error_log("خطا در آپلود فیش: " . $e->getMessage());
            }
        }
        
        $fuel_id = addFuelRecord($pdo, $data);
        
        // به‌روزرسانی کیلومتر خودرو
        if ($data['mileage_after'] > 0) {
            $stmt = $pdo->prepare("UPDATE company_cars SET current_mileage = ? WHERE id = ?");
            $stmt->execute([$data['mileage_after'], $data['car_id']]);
        }
        
        $pdo->commit();
        
        $_SESSION['success'] = "ثبت سوخت با موفقیت انجام شد!";
        logAction($pdo, 'ADD_FUEL_RECORD', "افزودن ثبت سوخت جدید برای خودرو ID: " . $data['car_id']);
        
        header('Location: car_fuel.php');
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "خطا در ثبت سوخت: " . $e->getMessage();
        logAction($pdo, 'ADD_FUEL_RECORD_ERROR', "خطا در ثبت سوخت: " . $e->getMessage());
    }
}

// دریافت فیلترها
$car_filter = $_GET['car_id'] ?? '';
$fuel_type_filter = $_GET['fuel_type'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// ساخت کوئری بر اساس فیلترها
$query = "SELECT cfr.*, cc.car_plate, cc.car_name, cc.car_model, u.full_name as driver_name 
          FROM car_fuel_records cfr 
          LEFT JOIN company_cars cc ON cfr.car_id = cc.id 
          LEFT JOIN users u ON cfr.driver_id = u.id 
          WHERE 1=1";
$params = [];

if ($car_filter) {
    $query .= " AND cfr.car_id = ?";
    $params[] = $car_filter;
}
if ($fuel_type_filter) {
    $query .= " AND cfr.fuel_type = ?";
    $params[] = $fuel_type_filter;
}
if ($date_from) {
    $query .= " AND cfr.fuel_date >= ?";
    $params[] = $date_from;
}
if ($date_to) {
    $query .= " AND cfr.fuel_date <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY cfr.fuel_date DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$fuel_records = $stmt->fetchAll();

// دریافت لیست خودروها و کاربران
$cars = $pdo->query("SELECT id, car_plate, car_name, car_model, current_mileage FROM company_cars ORDER BY car_name")->fetchAll();
$drivers = $pdo->query("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name")->fetchAll();

// آمار
$total_records = $pdo->query("SELECT COUNT(*) as total FROM car_fuel_records")->fetch()['total'];
$total_cost = $pdo->query("SELECT SUM(fuel_cost) as total FROM car_fuel_records")->fetch()['total'] ?? 0;
$total_amount = $pdo->query("SELECT SUM(fuel_amount) as total FROM car_fuel_records")->fetch()['total'] ?? 0;
$avg_efficiency = $pdo->query("SELECT AVG(fuel_efficiency) as avg FROM car_fuel_records WHERE fuel_efficiency > 0")->fetch()['avg'] ?? 0;

// آمار ماهانه
$monthly_stats = $pdo->query("
    SELECT 
        DATE_FORMAT(fuel_date, '%Y-%m') as month,
        COUNT(*) as records_count,
        SUM(fuel_cost) as total_cost,
        SUM(fuel_amount) as total_amount,
        AVG(fuel_efficiency) as avg_efficiency
    FROM car_fuel_records 
    WHERE fuel_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(fuel_date, '%Y-%m')
    ORDER BY month DESC
")->fetchAll();
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت سوخت خودروها - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .cost-highlight {
            font-weight: bold;
            color: #dc3545;
        }
        .efficiency-good {
            color: #28a745;
            font-weight: bold;
        }
        .efficiency-average {
            color: #ffc107;
            font-weight: bold;
        }
        .efficiency-poor {
            color: #dc3545;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-gas-pump"></i> مدیریت سوخت خودروها</h2>
                    <div>
                        <span class="filter-active">
                            <i class="fas fa-filter"></i> 
                            <?= count($fuel_records) ?> مورد
                        </span>
                    </div>
                </div>

                <!-- آمار کلی -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h3><?= $total_records ?></h3>
                            <p><i class="fas fa-list"></i> کل ثبت‌ها</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <h3><?= number_format($total_cost) ?></h3>
                            <p><i class="fas fa-dollar-sign"></i> کل هزینه (ریال)</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <h3><?= number_format($total_amount) ?></h3>
                            <p><i class="fas fa-tint"></i> کل سوخت (لیتر)</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                            <h3><?= number_format($avg_efficiency, 1) ?></h3>
                            <p><i class="fas fa-chart-line"></i> میانگین بازدهی</p>
                        </div>
                    </div>
                </div>

                <!-- نمودار آمار ماهانه -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-chart-bar"></i> آمار ماهانه مصرف سوخت</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="monthlyChart" height="100"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- جستجو و فیلتر -->
                <div class="card search-box">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <select name="car_id" class="form-select">
                                    <option value="">همه خودروها</option>
                                    <?php foreach ($cars as $car): ?>
                                        <option value="<?= $car['id'] ?>" <?= $car_filter == $car['id'] ? 'selected' : '' ?>>
                                            <?= $car['car_plate'] ?> - <?= $car['car_name'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="fuel_type" class="form-select">
                                    <option value="">همه انواع</option>
                                    <option value="بنزین" <?= $fuel_type_filter == 'بنزین' ? 'selected' : '' ?>>بنزین</option>
                                    <option value="گازوئیل" <?= $fuel_type_filter == 'گازوئیل' ? 'selected' : '' ?>>گازوئیل</option>
                                    <option value="گاز" <?= $fuel_type_filter == 'گاز' ? 'selected' : '' ?>>گاز</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <input type="date" name="date_from" class="form-control" placeholder="از تاریخ" value="<?= $date_from ?>">
                            </div>
                            <div class="col-md-2">
                                <input type="date" name="date_to" class="form-control" placeholder="تا تاریخ" value="<?= $date_to ?>">
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-filter"></i> اعمال فیلتر
                                </button>
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

                <!-- فرم افزودن ثبت سوخت -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-plus-circle"></i> ثبت سوخت جدید</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="fuelForm" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            
                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="car_id" class="form-label">انتخاب خودرو *</label>
                                        <select class="form-select" id="car_id" name="car_id" required onchange="loadCarDetails()">
                                            <option value="">-- انتخاب خودرو --</option>
                                            <?php foreach ($cars as $car): ?>
                                                <option value="<?= $car['id'] ?>" data-mileage="<?= $car['current_mileage'] ?>">
                                                    <?= $car['car_plate'] ?> - <?= $car['car_name'] ?> (<?= $car['car_model'] ?>)
                                                </option>
                                            <?php endforeach; ?>
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
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="fuel_date" class="form-label">تاریخ سوخت‌گیری *</label>
                                        <input type="datetime-local" class="form-control" id="fuel_date" name="fuel_date" required value="<?= date('Y-m-d\TH:i') ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="driver_id" class="form-label">راننده</label>
                                        <select class="form-select" id="driver_id" name="driver_id">
                                            <option value="">-- انتخاب کنید --</option>
                                            <?php foreach ($drivers as $driver): ?>
                                                <option value="<?= $driver['id'] ?>"><?= $driver['full_name'] ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="fuel_amount" class="form-label">مقدار سوخت (لیتر) *</label>
                                        <input type="number" class="form-control" id="fuel_amount" name="fuel_amount" step="0.01" required onchange="calculateEfficiency()">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="fuel_cost" class="form-label">هزینه (ریال) *</label>
                                        <input type="number" class="form-control" id="fuel_cost" name="fuel_cost" step="0.01" required>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="fuel_station" class="form-label">پمپ بنزین</label>
                                        <input type="text" class="form-control" id="fuel_station" name="fuel_station">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="purpose" class="form-label">هدف استفاده</label>
                                        <input type="text" class="form-control" id="purpose" name="purpose">
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="mileage_before" class="form-label">کیلومتر قبل از سوخت‌گیری *</label>
                                        <input type="number" class="form-control" id="mileage_before" name="mileage_before" required onchange="calculateEfficiency()">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="mileage_after" class="form-label">کیلومتر بعد از سوخت‌گیری *</label>
                                        <input type="number" class="form-control" id="mileage_after" name="mileage_after" required onchange="calculateEfficiency()">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">بازدهی سوخت (کیلومتر/لیتر)</label>
                                        <input type="text" class="form-control" id="efficiency_display" readonly style="background-color: #f8f9fa;">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="receipt" class="form-label">فیش پرداخت</label>
                                        <input type="file" class="form-control" id="receipt" name="receipt" accept=".pdf,.jpg,.jpeg,.png">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="notes" class="form-label">توضیحات</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                            </div>

                            <button type="submit" name="add_fuel_record" class="btn btn-success">
                                <i class="fas fa-save"></i> ثبت سوخت
                            </button>
                        </form>
                    </div>
                </div>

                <!-- لیست ثبت‌های سوخت -->
                <div class="card mt-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-list"></i> لیست ثبت‌های سوخت</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($fuel_records) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>خودرو</th>
                                            <th>نوع سوخت</th>
                                            <th>تاریخ</th>
                                            <th>مقدار (لیتر)</th>
                                            <th>هزینه (ریال)</th>
                                            <th>راننده</th>
                                            <th>بازدهی</th>
                                            <th>عملیات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($fuel_records as $record): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($record['car_plate']) ?></strong><br>
                                                <small><?= htmlspecialchars($record['car_name']) ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?= $record['fuel_type'] ?></span>
                                            </td>
                                            <td><?= jalali_format($record['fuel_date'], 'Y/m/d H:i') ?></td>
                                            <td><?= number_format($record['fuel_amount'], 2) ?></td>
                                            <td>
                                                <span class="cost-highlight"><?= number_format($record['fuel_cost']) ?></span>
                                            </td>
                                            <td><?= $record['driver_name'] ?: '--' ?></td>
                                            <td>
                                                <?php if ($record['fuel_efficiency'] > 0): ?>
                                                    <?php
                                                    $efficiency_class = 'efficiency-poor';
                                                    if ($record['fuel_efficiency'] >= 15) $efficiency_class = 'efficiency-good';
                                                    elseif ($record['fuel_efficiency'] >= 10) $efficiency_class = 'efficiency-average';
                                                    ?>
                                                    <span class="<?= $efficiency_class ?>"><?= number_format($record['fuel_efficiency'], 1) ?></span>
                                                <?php else: ?>
                                                    --
                                                <?php endif; ?>
                                            </td>
                                            <td class="action-buttons">
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#detailsModal<?= $record['id'] ?>" title="جزئیات">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($record['receipt_path']): ?>
                                                <a href="<?= $record['receipt_path'] ?>" class="btn btn-sm btn-secondary" target="_blank" title="مشاهده فیش">
                                                    <i class="fas fa-file-pdf"></i>
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
                                <i class="fas fa-info-circle"></i> هیچ ثبت سوختی یافت نشد.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal های جزئیات -->
    <?php foreach ($fuel_records as $record): ?>
    <div class="modal fade" id="detailsModal<?= $record['id'] ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">جزئیات ثبت سوخت #<?= $record['id'] ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>خودرو:</strong> <?= $record['car_plate'] ?> - <?= $record['car_name'] ?></p>
                            <p><strong>نوع سوخت:</strong> <?= $record['fuel_type'] ?></p>
                            <p><strong>تاریخ:</strong> <?= jalali_format($record['fuel_date'], 'Y/m/d H:i') ?></p>
                            <p><strong>مقدار:</strong> <?= number_format($record['fuel_amount'], 2) ?> لیتر</p>
                            <p><strong>هزینه:</strong> <?= number_format($record['fuel_cost']) ?> ریال</p>
                            <p><strong>پمپ بنزین:</strong> <?= $record['fuel_station'] ?: '--' ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>راننده:</strong> <?= $record['driver_name'] ?: '--' ?></p>
                            <p><strong>هدف:</strong> <?= $record['purpose'] ?: '--' ?></p>
                            <p><strong>کیلومتر قبل:</strong> <?= number_format($record['mileage_before']) ?></p>
                            <p><strong>کیلومتر بعد:</strong> <?= number_format($record['mileage_after']) ?></p>
                            <p><strong>بازدهی:</strong> 
                                <?php if ($record['fuel_efficiency'] > 0): ?>
                                    <?= number_format($record['fuel_efficiency'], 1) ?> کیلومتر/لیتر
                                <?php else: ?>
                                    --
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <?php if ($record['notes']): ?>
                    <div class="row mt-3">
                        <div class="col-md-12">
                            <p><strong>توضیحات:</strong></p>
                            <p><?= nl2br(htmlspecialchars($record['notes'])) ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <script>
    function loadCarDetails() {
        const carSelect = document.getElementById('car_id');
        const selectedOption = carSelect.options[carSelect.selectedIndex];
        
        if (carSelect.value) {
            const mileage = selectedOption.getAttribute('data-mileage');
            document.getElementById('mileage_before').value = mileage;
        }
    }
    
    function calculateEfficiency() {
        const fuelAmount = parseFloat(document.getElementById('fuel_amount').value) || 0;
        const mileageBefore = parseInt(document.getElementById('mileage_before').value) || 0;
        const mileageAfter = parseInt(document.getElementById('mileage_after').value) || 0;
        
        if (fuelAmount > 0 && mileageAfter > mileageBefore) {
            const distance = mileageAfter - mileageBefore;
            const efficiency = distance / fuelAmount;
            document.getElementById('efficiency_display').value = efficiency.toFixed(1) + ' کیلومتر/لیتر';
        } else {
            document.getElementById('efficiency_display').value = '';
        }
    }
    
    // نمودار آمار ماهانه
    const monthlyData = <?= json_encode($monthly_stats) ?>;
    const ctx = document.getElementById('monthlyChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: monthlyData.map(item => item.month),
            datasets: [{
                label: 'هزینه (هزار ریال)',
                data: monthlyData.map(item => Math.round(item.total_cost / 1000)),
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                yAxisID: 'y'
            }, {
                label: 'مقدار سوخت (لیتر)',
                data: monthlyData.map(item => Math.round(item.total_amount)),
                borderColor: 'rgb(255, 99, 132)',
                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            scales: {
                x: {
                    display: true,
                    title: {
                        display: true,
                        text: 'ماه'
                    }
                },
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'هزینه (هزار ریال)'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'مقدار سوخت (لیتر)'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                }
            }
        }
    });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>