<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';
require_auth('کاربر عادی');

// دریافت آمار ناوگان
$fleet_stats = getCarFleetStatistics($pdo);

// پردازش درخواست‌ها
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    verifyCsrfToken();
    
    try {
        if (isset($_POST['add_car'])) {
            // اضافه کردن خودرو جدید
            $car_data = [
                'brand' => sanitizeInput($_POST['brand']),
                'model' => sanitizeInput($_POST['model']),
                'year' => (int)$_POST['year'],
                'color' => sanitizeInput($_POST['color']),
                'license_plate' => sanitizeInput($_POST['license_plate']),
                'chassis_number' => sanitizeInput($_POST['chassis_number']),
                'engine_number' => sanitizeInput($_POST['engine_number']),
                'fuel_type' => sanitizeInput($_POST['fuel_type']),
                'transmission_type' => sanitizeInput($_POST['transmission_type']),
                'engine_capacity' => sanitizeInput($_POST['engine_capacity']),
                'purchase_date' => sanitizeInput($_POST['purchase_date']),
                'purchase_price' => (float)($_POST['purchase_price'] ?? 0),
                'current_mileage' => (int)($_POST['current_mileage'] ?? 0),
                'insurance_company' => sanitizeInput($_POST['insurance_company']),
                'insurance_policy_number' => sanitizeInput($_POST['insurance_policy_number']),
                'insurance_expiry_date' => sanitizeInput($_POST['insurance_expiry_date']),
                'registration_expiry_date' => sanitizeInput($_POST['registration_expiry_date']),
                'technical_inspection_expiry' => sanitizeInput($_POST['technical_inspection_expiry']),
                'department' => sanitizeInput($_POST['department']),
                'responsible_person' => sanitizeInput($_POST['responsible_person']),
                'location' => sanitizeInput($_POST['location']),
                'notes' => sanitizeInput($_POST['notes'])
            ];
            
            $car_id = addCompanyCar($pdo, $car_data);
            $message = 'خودرو با موفقیت اضافه شد.';
            
        } elseif (isset($_POST['handover_car'])) {
            // تحویل خودرو
            $handover_data = [
                'car_id' => (int)$_POST['car_id'],
                'employee_name' => sanitizeInput($_POST['employee_name']),
                'employee_id' => sanitizeInput($_POST['employee_id']),
                'employee_phone' => sanitizeInput($_POST['employee_phone']),
                'employee_department' => sanitizeInput($_POST['employee_department']),
                'employee_position' => sanitizeInput($_POST['employee_position']),
                'handover_date' => sanitizeInput($_POST['handover_date']),
                'expected_return_date' => sanitizeInput($_POST['expected_return_date']),
                'purpose' => sanitizeInput($_POST['purpose']),
                'destination' => sanitizeInput($_POST['destination']),
                'mileage_at_handover' => (int)$_POST['mileage_at_handover'],
                'fuel_level_handover' => sanitizeInput($_POST['fuel_level_handover']),
                'car_condition_handover' => sanitizeInput($_POST['car_condition_handover']),
                'additional_items' => sanitizeInput($_POST['additional_items']),
                'handover_location' => sanitizeInput($_POST['handover_location']),
                'notes' => sanitizeInput($_POST['notes'])
            ];
            
            $handover_id = handoverCar($pdo, $handover_data);
            $message = 'خودرو با موفقیت تحویل داده شد.';
            
        } elseif (isset($_POST['return_car'])) {
            // بازگشت خودرو
            $return_data = [
                'actual_return_date' => sanitizeInput($_POST['actual_return_date']),
                'mileage_at_return' => (int)$_POST['mileage_at_return'],
                'fuel_level_return' => sanitizeInput($_POST['fuel_level_return']),
                'car_condition_return' => sanitizeInput($_POST['car_condition_return']),
                'damages_noted' => sanitizeInput($_POST['damages_noted']),
                'return_location' => sanitizeInput($_POST['return_location']),
                'late_return_reason' => sanitizeInput($_POST['late_return_reason']),
                'penalty_amount' => (float)($_POST['penalty_amount'] ?? 0)
            ];
            
            $handover_id = (int)$_POST['handover_id'];
            returnCar($pdo, $handover_id, $return_data);
            $message = 'خودرو با موفقیت بازگشت داده شد.';
            
        } elseif (isset($_POST['add_maintenance'])) {
            // اضافه کردن سرویس
            $maintenance_data = [
                'car_id' => (int)$_POST['car_id'],
                'maintenance_type' => sanitizeInput($_POST['maintenance_type']),
                'maintenance_date' => sanitizeInput($_POST['maintenance_date']),
                'mileage_at_service' => (int)$_POST['mileage_at_service'],
                'service_provider' => sanitizeInput($_POST['service_provider']),
                'service_location' => sanitizeInput($_POST['service_location']),
                'description' => sanitizeInput($_POST['description']),
                'parts_replaced' => sanitizeInput($_POST['parts_replaced']),
                'cost' => (float)($_POST['cost'] ?? 0),
                'invoice_number' => sanitizeInput($_POST['invoice_number']),
                'warranty_period' => (int)($_POST['warranty_period'] ?? 0),
                'warranty_mileage' => (int)($_POST['warranty_mileage'] ?? 0),
                'next_service_date' => sanitizeInput($_POST['next_service_date']),
                'next_service_mileage' => (int)($_POST['next_service_mileage'] ?? 0),
                'technician_name' => sanitizeInput($_POST['technician_name']),
                'quality_rating' => sanitizeInput($_POST['quality_rating']),
                'notes' => sanitizeInput($_POST['notes'])
            ];
            
            $maintenance_id = addCarMaintenance($pdo, $maintenance_data);
            $message = 'سرویس خودرو با موفقیت ثبت شد.';
            
        } elseif (isset($_POST['add_fuel'])) {
            // اضافه کردن سوخت‌گیری
            $fuel_data = [
                'car_id' => (int)$_POST['car_id'],
                'handover_id' => !empty($_POST['handover_id']) ? (int)$_POST['handover_id'] : null,
                'fuel_date' => sanitizeInput($_POST['fuel_date']),
                'fuel_station' => sanitizeInput($_POST['fuel_station']),
                'fuel_type' => sanitizeInput($_POST['fuel_type']),
                'liters' => (float)$_POST['liters'],
                'price_per_liter' => (float)($_POST['price_per_liter'] ?? 0),
                'total_cost' => (float)($_POST['total_cost'] ?? 0),
                'mileage_at_fuel' => (int)$_POST['mileage_at_fuel'],
                'fuel_card_number' => sanitizeInput($_POST['fuel_card_number']),
                'receipt_number' => sanitizeInput($_POST['receipt_number']),
                'filled_by' => sanitizeInput($_POST['filled_by']),
                'notes' => sanitizeInput($_POST['notes'])
            ];
            
            $fuel_id = addFuelRecord($pdo, $fuel_data);
            $message = 'سوخت‌گیری با موفقیت ثبت شد.';
        }
        
        // Refresh stats after any operation
        $fleet_stats = getCarFleetStatistics($pdo);
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// دریافت لیست خودروها
$cars_stmt = $pdo->query("SELECT cc.*, 
    (SELECT COUNT(*) FROM car_handovers ch WHERE ch.car_id = cc.id AND ch.status = 'در حال استفاده') as is_in_use,
    (SELECT ch.employee_name FROM car_handovers ch WHERE ch.car_id = cc.id AND ch.status = 'در حال استفاده' ORDER BY ch.handover_date DESC LIMIT 1) as current_user,
    (SELECT ch.expected_return_date FROM car_handovers ch WHERE ch.car_id = cc.id AND ch.status = 'در حال استفاده' ORDER BY ch.handover_date DESC LIMIT 1) as expected_return
    FROM company_cars cc 
    ORDER BY cc.brand, cc.model");
$cars = $cars_stmt->fetchAll();

// دریافت تحویل‌های فعال
$active_handovers_stmt = $pdo->query("SELECT ch.*, cc.brand, cc.model, cc.license_plate, cc.car_code
    FROM car_handovers ch 
    JOIN company_cars cc ON ch.car_id = cc.id 
    WHERE ch.status = 'در حال استفاده' 
    ORDER BY ch.handover_date DESC");
$active_handovers = $active_handovers_stmt->fetchAll();

// دریافت خودروهای نیازمند سرویس
$service_needed_stmt = $pdo->query("SELECT cc.*, 
    (cc.current_mileage - cc.next_service_mileage) as overdue_mileage
    FROM company_cars cc 
    WHERE cc.current_mileage >= cc.next_service_mileage AND cc.next_service_mileage > 0 
    ORDER BY overdue_mileage DESC");
$cars_needing_service = $service_needed_stmt->fetchAll();

// دریافت اسناد منقضی شده
$expired_docs_stmt = $pdo->query("SELECT cd.*, cc.brand, cc.model, cc.license_plate, cc.car_code
    FROM car_documents cd 
    JOIN company_cars cc ON cd.car_id = cc.id 
    WHERE cd.expiry_date <= CURDATE() AND cd.is_active = 1 
    ORDER BY cd.expiry_date ASC");
$expired_documents = $expired_docs_stmt->fetchAll();
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت ناوگان خودروهای شرکتی - سامانه اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <style>
        .car-card {
            transition: transform 0.2s;
            border-left: 4px solid #007bff;
        }
        .car-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .car-in-use {
            border-left-color: #dc3545;
            background-color: #fff5f5;
        }
        .car-available {
            border-left-color: #28a745;
            background-color: #f8fff8;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
        }
        .stat-card-warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .stat-card-success {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        .stat-card-info {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }
        .quick-action-btn {
            border-radius: 25px;
            padding: 10px 20px;
            font-weight: bold;
            transition: all 0.3s;
        }
        .quick-action-btn:hover {
            transform: scale(1.05);
        }
        .status-badge {
            font-size: 0.8em;
            padding: 4px 8px;
            border-radius: 12px;
        }
        .fuel-level {
            display: inline-block;
            width: 60px;
            height: 8px;
            background-color: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
        }
        .fuel-level-fill {
            height: 100%;
            transition: width 0.3s;
        }
        .fuel-empty { background-color: #dc3545; width: 0%; }
        .fuel-quarter { background-color: #fd7e14; width: 25%; }
        .fuel-half { background-color: #ffc107; width: 50%; }
        .fuel-three-quarter { background-color: #20c997; width: 75%; }
        .fuel-full { background-color: #28a745; width: 100%; }
        .mileage-progress {
            font-size: 0.9em;
        }
        .overdue {
            color: #dc3545;
            font-weight: bold;
        }
        .due-soon {
            color: #fd7e14;
            font-weight: bold;
        }
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .nav-pills .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .table-hover tbody tr:hover {
            background-color: rgba(102, 126, 234, 0.1);
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container-fluid mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2><i class="fas fa-car text-primary"></i> مدیریت ناوگان خودروهای شرکتی</h2>
                        <p class="text-muted">مدیریت جامع خودروهای شرکتی، تحویل، بازگشت، سرویس و نگهداری</p>
                    </div>
                    <div>
                        <button class="btn btn-primary quick-action-btn me-2" data-bs-toggle="modal" data-bs-target="#addCarModal">
                            <i class="fas fa-plus"></i> افزودن خودرو
                        </button>
                        <button class="btn btn-success quick-action-btn me-2" data-bs-toggle="modal" data-bs-target="#handoverModal">
                            <i class="fas fa-hand-holding"></i> تحویل خودرو
                        </button>
                        <button class="btn btn-info quick-action-btn" data-bs-toggle="modal" data-bs-target="#reportsModal">
                            <i class="fas fa-chart-line"></i> گزارشات
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alerts -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stat-card h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-car fa-3x mb-3"></i>
                        <h3><?php echo number_format($fleet_stats['total_cars']); ?></h3>
                        <p class="mb-0">کل خودروها</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stat-card-warning h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-hand-holding fa-3x mb-3"></i>
                        <h3><?php echo number_format($fleet_stats['active_handovers']); ?></h3>
                        <p class="mb-0">در حال استفاده</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stat-card-info h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-tools fa-3x mb-3"></i>
                        <h3><?php echo number_format($fleet_stats['cars_needing_service']); ?></h3>
                        <p class="mb-0">نیازمند سرویس</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stat-card-success h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-gas-pump fa-3x mb-3"></i>
                        <h3><?php echo number_format($fleet_stats['monthly_fuel_cost']); ?></h3>
                        <p class="mb-0">هزینه سوخت ماهانه (ریال)</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Tabs -->
        <div class="row">
            <div class="col-12">
                <ul class="nav nav-pills mb-3" id="fleetTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="cars-tab" data-bs-toggle="pill" data-bs-target="#cars" type="button" role="tab">
                            <i class="fas fa-car"></i> خودروها
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="handovers-tab" data-bs-toggle="pill" data-bs-target="#handovers" type="button" role="tab">
                            <i class="fas fa-exchange-alt"></i> تحویل‌های فعال
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="maintenance-tab" data-bs-toggle="pill" data-bs-target="#maintenance" type="button" role="tab">
                            <i class="fas fa-wrench"></i> سرویس و نگهداری
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="alerts-tab" data-bs-toggle="pill" data-bs-target="#alerts" type="button" role="tab">
                            <i class="fas fa-bell"></i> هشدارها
                            <?php if ($fleet_stats['cars_needing_service'] > 0 || $fleet_stats['expired_documents'] > 0): ?>
                                <span class="badge bg-danger ms-1"><?php echo $fleet_stats['cars_needing_service'] + $fleet_stats['expired_documents']; ?></span>
                            <?php endif; ?>
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="fleetTabsContent">
                    <!-- Cars Tab -->
                    <div class="tab-pane fade show active" id="cars" role="tabpanel">
                        <div class="row">
                            <?php foreach ($cars as $car): ?>
                                <div class="col-lg-4 col-md-6 mb-4">
                                    <div class="card car-card <?php echo $car['is_in_use'] ? 'car-in-use' : 'car-available'; ?>">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <h6 class="mb-0">
                                                <i class="fas fa-car"></i> 
                                                <?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?>
                                            </h6>
                                            <span class="status-badge badge <?php echo $car['is_in_use'] ? 'bg-danger' : 'bg-success'; ?>">
                                                <?php echo $car['is_in_use'] ? 'در حال استفاده' : 'آزاد'; ?>
                                            </span>
                                        </div>
                                        <div class="card-body">
                                            <div class="row mb-2">
                                                <div class="col-6">
                                                    <small class="text-muted">کد خودرو:</small><br>
                                                    <strong><?php echo htmlspecialchars($car['car_code']); ?></strong>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted">پلاک:</small><br>
                                                    <strong><?php echo htmlspecialchars($car['license_plate']); ?></strong>
                                                </div>
                                            </div>
                                            <div class="row mb-2">
                                                <div class="col-6">
                                                    <small class="text-muted">سال:</small><br>
                                                    <?php echo $car['year']; ?>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted">رنگ:</small><br>
                                                    <?php echo htmlspecialchars($car['color'] ?? '-'); ?>
                                                </div>
                                            </div>
                                            <div class="row mb-2">
                                                <div class="col-12">
                                                    <small class="text-muted">کیلومتر:</small><br>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span><?php echo number_format($car['current_mileage']); ?> کیلومتر</span>
                                                        <?php if ($car['next_service_mileage'] > 0): ?>
                                                            <small class="mileage-progress <?php echo $car['current_mileage'] >= $car['next_service_mileage'] ? 'overdue' : ($car['current_mileage'] >= ($car['next_service_mileage'] - 1000) ? 'due-soon' : ''); ?>">
                                                                سرویس بعدی: <?php echo number_format($car['next_service_mileage']); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php if ($car['is_in_use']): ?>
                                                <div class="alert alert-warning py-2 mb-2">
                                                    <small>
                                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($car['current_user']); ?><br>
                                                        <?php if ($car['expected_return']): ?>
                                                            <i class="fas fa-calendar"></i> بازگشت: <?php echo jalali_format($car['expected_return'], 'Y/m/d'); ?>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                            <div class="d-flex gap-1">
                                                <button class="btn btn-sm btn-outline-primary flex-fill" onclick="viewCarDetails(<?php echo $car['id']; ?>)">
                                                    <i class="fas fa-eye"></i> جزئیات
                                                </button>
                                                <?php if (!$car['is_in_use']): ?>
                                                    <button class="btn btn-sm btn-success" onclick="handoverCar(<?php echo $car['id']; ?>)">
                                                        <i class="fas fa-hand-holding"></i> تحویل
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn btn-sm btn-warning" onclick="returnCar(<?php echo $car['id']; ?>)">
                                                        <i class="fas fa-undo"></i> بازگشت
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Active Handovers Tab -->
                    <div class="tab-pane fade" id="handovers" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-exchange-alt"></i> تحویل‌های فعال</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>کد تحویل</th>
                                                <th>خودرو</th>
                                                <th>تحویل گیرنده</th>
                                                <th>تاریخ تحویل</th>
                                                <th>بازگشت مورد انتظار</th>
                                                <th>مقصد</th>
                                                <th>وضعیت</th>
                                                <th>عملیات</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($active_handovers as $handover): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($handover['handover_code']); ?></td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($handover['brand'] . ' ' . $handover['model']); ?></strong><br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($handover['license_plate']); ?></small>
                                                    </td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($handover['employee_name']); ?></strong><br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($handover['employee_department'] ?? ''); ?></small>
                                                    </td>
                                                    <td><?php echo jalali_format($handover['handover_date'], 'Y/m/d H:i'); ?></td>
                                                    <td>
                                                        <?php if ($handover['expected_return_date']): ?>
                                                            <?php 
                                                            $expected = new DateTime($handover['expected_return_date']);
                                                            $now = new DateTime();
                                                            $is_overdue = $expected < $now;
                                                            ?>
                                                            <span class="<?php echo $is_overdue ? 'text-danger' : ''; ?>">
                                                                <?php echo jalali_format($handover['expected_return_date'], 'Y/m/d H:i'); ?>
                                                                <?php if ($is_overdue): ?>
                                                                    <br><small><i class="fas fa-exclamation-triangle"></i> دیرکرد</small>
                                                                <?php endif; ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-muted">نامشخص</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($handover['destination'] ?? '-'); ?></td>
                                                    <td>
                                                        <span class="badge bg-primary"><?php echo htmlspecialchars($handover['status']); ?></span>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-warning" onclick="returnCarModal(<?php echo $handover['id']; ?>)">
                                                            <i class="fas fa-undo"></i> بازگشت
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Maintenance Tab -->
                    <div class="tab-pane fade" id="maintenance" role="tabpanel">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between">
                                        <h5><i class="fas fa-wrench"></i> سرویس و نگهداری</h5>
                                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#maintenanceModal">
                                            <i class="fas fa-plus"></i> ثبت سرویس
                                        </button>
                                    </div>
                                    <div class="card-body">
                                        <!-- Recent maintenance records will be loaded here -->
                                        <p class="text-muted">آخرین سرویس‌های انجام شده نمایش داده خواهد شد.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h6><i class="fas fa-gas-pump"></i> ثبت سوخت‌گیری</h6>
                                    </div>
                                    <div class="card-body">
                                        <button class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#fuelModal">
                                            <i class="fas fa-plus"></i> ثبت سوخت‌گیری
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Alerts Tab -->
                    <div class="tab-pane fade" id="alerts" role="tabpanel">
                        <div class="row">
                            <!-- Cars Needing Service -->
                            <?php if (!empty($cars_needing_service)): ?>
                                <div class="col-md-6 mb-4">
                                    <div class="card border-warning">
                                        <div class="card-header bg-warning text-dark">
                                            <h6><i class="fas fa-tools"></i> خودروهای نیازمند سرویس</h6>
                                        </div>
                                        <div class="card-body">
                                            <?php foreach ($cars_needing_service as $car): ?>
                                                <div class="alert alert-warning d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?></strong><br>
                                                        <small>پلاک: <?php echo htmlspecialchars($car['license_plate']); ?></small><br>
                                                        <small>کیلومتر فعلی: <?php echo number_format($car['current_mileage']); ?></small><br>
                                                        <small class="text-danger">
                                                            <?php echo $car['overdue_mileage'] > 0 ? 'دیرکرد: ' . number_format($car['overdue_mileage']) . ' کیلومتر' : 'نیازمند سرویس'; ?>
                                                        </small>
                                                    </div>
                                                    <button class="btn btn-sm btn-warning" onclick="addMaintenanceForCar(<?php echo $car['id']; ?>)">
                                                        <i class="fas fa-wrench"></i> سرویس
                                                    </button>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Expired Documents -->
                            <?php if (!empty($expired_documents)): ?>
                                <div class="col-md-6 mb-4">
                                    <div class="card border-danger">
                                        <div class="card-header bg-danger text-white">
                                            <h6><i class="fas fa-file-times"></i> اسناد منقضی شده</h6>
                                        </div>
                                        <div class="card-body">
                                            <?php foreach ($expired_documents as $doc): ?>
                                                <div class="alert alert-danger d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($doc['brand'] . ' ' . $doc['model']); ?></strong><br>
                                                        <small>پلاک: <?php echo htmlspecialchars($doc['license_plate']); ?></small><br>
                                                        <small>سند: <?php echo htmlspecialchars($doc['document_name']); ?></small><br>
                                                        <small class="text-danger">انقضا: <?php echo jalali_format($doc['expiry_date'], 'Y/m/d'); ?></small>
                                                    </div>
                                                    <button class="btn btn-sm btn-danger" onclick="renewDocument(<?php echo $doc['id']; ?>)">
                                                        <i class="fas fa-sync"></i> تمدید
                                                    </button>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (empty($cars_needing_service) && empty($expired_documents)): ?>
                                <div class="col-12">
                                    <div class="alert alert-success text-center">
                                        <i class="fas fa-check-circle fa-3x mb-3"></i>
                                        <h5>همه چیز در وضعیت مطلوب است!</h5>
                                        <p>هیچ هشدار یا مسئله‌ای وجود ندارد.</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Car Modal -->
    <div class="modal fade" id="addCarModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> افزودن خودرو جدید</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">برند *</label>
                                <input type="text" class="form-control" name="brand" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">مدل *</label>
                                <input type="text" class="form-control" name="model" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">سال تولید *</label>
                                <input type="number" class="form-control" name="year" min="1300" max="1450" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">رنگ</label>
                                <input type="text" class="form-control" name="color">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">پلاک *</label>
                                <input type="text" class="form-control" name="license_plate" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">شماره شاسی</label>
                                <input type="text" class="form-control" name="chassis_number">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">شماره موتور</label>
                                <input type="text" class="form-control" name="engine_number">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">نوع سوخت</label>
                                <select class="form-select" name="fuel_type">
                                    <option value="بنزین">بنزین</option>
                                    <option value="دیزل">دیزل</option>
                                    <option value="گاز طبیعی">گاز طبیعی</option>
                                    <option value="هیبرید">هیبرید</option>
                                    <option value="برقی">برقی</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">نوع گیربکس</label>
                                <select class="form-select" name="transmission_type">
                                    <option value="دستی">دستی</option>
                                    <option value="اتوماتیک">اتوماتیک</option>
                                    <option value="نیمه اتوماتیک">نیمه اتوماتیک</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">حجم موتور</label>
                                <input type="text" class="form-control" name="engine_capacity" placeholder="مثال: 1600cc">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">تاریخ خرید</label>
                                <input type="date" class="form-control" name="purchase_date">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">قیمت خرید (ریال)</label>
                                <input type="number" class="form-control" name="purchase_price" min="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">کیلومتر فعلی</label>
                                <input type="number" class="form-control" name="current_mileage" min="0" value="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">شرکت بیمه</label>
                                <input type="text" class="form-control" name="insurance_company">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">شماره بیمه‌نامه</label>
                                <input type="text" class="form-control" name="insurance_policy_number">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">انقضای بیمه</label>
                                <input type="date" class="form-control" name="insurance_expiry_date">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">انقضای کارت خودرو</label>
                                <input type="date" class="form-control" name="registration_expiry_date">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">انقضای معاینه فنی</label>
                                <input type="date" class="form-control" name="technical_inspection_expiry">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">بخش/واحد</label>
                                <input type="text" class="form-control" name="department">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">مسئول خودرو</label>
                                <input type="text" class="form-control" name="responsible_person">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">محل نگهداری</label>
                                <input type="text" class="form-control" name="location">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">یادداشت‌ها</label>
                                <textarea class="form-control" name="notes" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" name="add_car" class="btn btn-primary">
                            <i class="fas fa-save"></i> ذخیره خودرو
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Handover Modal -->
    <div class="modal fade" id="handoverModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-hand-holding"></i> تحویل خودرو</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">انتخاب خودرو *</label>
                                <select class="form-select" name="car_id" required>
                                    <option value="">انتخاب کنید...</option>
                                    <?php foreach ($cars as $car): ?>
                                        <?php if (!$car['is_in_use']): ?>
                                            <option value="<?php echo $car['id']; ?>">
                                                <?php echo htmlspecialchars($car['brand'] . ' ' . $car['model'] . ' - ' . $car['license_plate']); ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">نام تحویل‌گیرنده *</label>
                                <input type="text" class="form-control" name="employee_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">کد پرسنلی</label>
                                <input type="text" class="form-control" name="employee_id">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">شماره تماس</label>
                                <input type="text" class="form-control" name="employee_phone">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">بخش/واحد</label>
                                <input type="text" class="form-control" name="employee_department">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">سمت</label>
                                <input type="text" class="form-control" name="employee_position">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">تاریخ و ساعت تحویل *</label>
                                <input type="datetime-local" class="form-control" name="handover_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">تاریخ و ساعت بازگشت مورد انتظار</label>
                                <input type="datetime-local" class="form-control" name="expected_return_date">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">کیلومتر هنگام تحویل *</label>
                                <input type="number" class="form-control" name="mileage_at_handover" min="0" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">سطح سوخت هنگام تحویل</label>
                                <select class="form-select" name="fuel_level_handover">
                                    <option value="پر">پر</option>
                                    <option value="3/4">3/4</option>
                                    <option value="1/2">1/2</option>
                                    <option value="1/4">1/4</option>
                                    <option value="خالی">خالی</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">محل تحویل</label>
                                <input type="text" class="form-control" name="handover_location">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">هدف سفر</label>
                                <input type="text" class="form-control" name="purpose">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">مقصد</label>
                                <input type="text" class="form-control" name="destination">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">وضعیت خودرو هنگام تحویل</label>
                                <textarea class="form-control" name="car_condition_handover" rows="3" placeholder="توضیح وضعیت ظاهری، خرابی‌ها، نواقص و..."></textarea>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">اقلام همراه</label>
                                <textarea class="form-control" name="additional_items" rows="2" placeholder="کیف کمک‌های اولیه، جک، چراغ قوه، کابل جامپر و..."></textarea>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">یادداشت‌ها</label>
                                <textarea class="form-control" name="notes" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" name="handover_car" class="btn btn-success">
                            <i class="fas fa-hand-holding"></i> تحویل خودرو
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Return Car Modal -->
    <div class="modal fade" id="returnModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-undo"></i> بازگشت خودرو</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="handover_id" id="return_handover_id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">تاریخ و ساعت بازگشت *</label>
                                <input type="datetime-local" class="form-control" name="actual_return_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">کیلومتر هنگام بازگشت *</label>
                                <input type="number" class="form-control" name="mileage_at_return" min="0" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">سطح سوخت هنگام بازگشت</label>
                                <select class="form-select" name="fuel_level_return">
                                    <option value="">انتخاب کنید...</option>
                                    <option value="پر">پر</option>
                                    <option value="3/4">3/4</option>
                                    <option value="1/2">1/2</option>
                                    <option value="1/4">1/4</option>
                                    <option value="خالی">خالی</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">محل بازگشت</label>
                                <input type="text" class="form-control" name="return_location">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">وضعیت خودرو هنگام بازگشت</label>
                                <textarea class="form-control" name="car_condition_return" rows="3" placeholder="توضیح وضعیت ظاهری، خرابی‌ها، تغییرات و..."></textarea>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">خسارات و آسیب‌های وارده</label>
                                <textarea class="form-control" name="damages_noted" rows="3" placeholder="در صورت وجود خسارت یا آسیب، توضیح دهید..."></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">دلیل دیرکرد (در صورت وجود)</label>
                                <input type="text" class="form-control" name="late_return_reason">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">مبلغ جریمه (ریال)</label>
                                <input type="number" class="form-control" name="penalty_amount" min="0" value="0">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" name="return_car" class="btn btn-warning">
                            <i class="fas fa-undo"></i> ثبت بازگشت
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Maintenance Modal -->
    <div class="modal fade" id="maintenanceModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-wrench"></i> ثبت سرویس و نگهداری</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">انتخاب خودرو *</label>
                                <select class="form-select" name="car_id" required>
                                    <option value="">انتخاب کنید...</option>
                                    <?php foreach ($cars as $car): ?>
                                        <option value="<?php echo $car['id']; ?>">
                                            <?php echo htmlspecialchars($car['brand'] . ' ' . $car['model'] . ' - ' . $car['license_plate']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">نوع سرویس *</label>
                                <select class="form-select" name="maintenance_type" required>
                                    <option value="">انتخاب کنید...</option>
                                    <option value="سرویس دوره‌ای">سرویس دوره‌ای</option>
                                    <option value="تعمیر">تعمیر</option>
                                    <option value="تعویض روغن">تعویض روغن</option>
                                    <option value="تعویض لاستیک">تعویض لاستیک</option>
                                    <option value="بازرسی فنی">بازرسی فنی</option>
                                    <option value="تصادف">تصادف</option>
                                    <option value="سایر">سایر</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">تاریخ سرویس *</label>
                                <input type="date" class="form-control" name="maintenance_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">کیلومتر هنگام سرویس *</label>
                                <input type="number" class="form-control" name="mileage_at_service" min="0" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">ارائه‌دهنده خدمات</label>
                                <input type="text" class="form-control" name="service_provider">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">محل سرویس</label>
                                <input type="text" class="form-control" name="service_location">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">شرح کار انجام شده *</label>
                                <textarea class="form-control" name="description" rows="3" required></textarea>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">قطعات تعویض شده</label>
                                <textarea class="form-control" name="parts_replaced" rows="2"></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">هزینه (ریال)</label>
                                <input type="number" class="form-control" name="cost" min="0" value="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">شماره فاکتور</label>
                                <input type="text" class="form-control" name="invoice_number">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">مدت گارانتی (روز)</label>
                                <input type="number" class="form-control" name="warranty_period" min="0" value="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">گارانتی کیلومتری</label>
                                <input type="number" class="form-control" name="warranty_mileage" min="0" value="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">تاریخ سرویس بعدی</label>
                                <input type="date" class="form-control" name="next_service_date">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">کیلومتر سرویس بعدی</label>
                                <input type="number" class="form-control" name="next_service_mileage" min="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">نام تکنسین</label>
                                <input type="text" class="form-control" name="technician_name">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">کیفیت کار</label>
                                <select class="form-select" name="quality_rating">
                                    <option value="خوب">خوب</option>
                                    <option value="عالی">عالی</option>
                                    <option value="متوسط">متوسط</option>
                                    <option value="ضعیف">ضعیف</option>
                                </select>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">یادداشت‌ها</label>
                                <textarea class="form-control" name="notes" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" name="add_maintenance" class="btn btn-primary">
                            <i class="fas fa-save"></i> ثبت سرویس
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Fuel Modal -->
    <div class="modal fade" id="fuelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-gas-pump"></i> ثبت سوخت‌گیری</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label class="form-label">انتخاب خودرو *</label>
                                <select class="form-select" name="car_id" required>
                                    <option value="">انتخاب کنید...</option>
                                    <?php foreach ($cars as $car): ?>
                                        <option value="<?php echo $car['id']; ?>">
                                            <?php echo htmlspecialchars($car['brand'] . ' ' . $car['model'] . ' - ' . $car['license_plate']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">تاریخ و ساعت *</label>
                                <input type="datetime-local" class="form-control" name="fuel_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">پمپ بنزین</label>
                                <input type="text" class="form-control" name="fuel_station">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">نوع سوخت *</label>
                                <select class="form-select" name="fuel_type" required>
                                    <option value="">انتخاب کنید...</option>
                                    <option value="بنزین معمولی">بنزین معمولی</option>
                                    <option value="بنزین سوپر">بنزین سوپر</option>
                                    <option value="دیزل">دیزل</option>
                                    <option value="گاز طبیعی">گاز طبیعی</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">مقدار (لیتر) *</label>
                                <input type="number" class="form-control" name="liters" step="0.01" min="0" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">قیمت هر لیتر (ریال)</label>
                                <input type="number" class="form-control" name="price_per_liter" min="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">مبلغ کل (ریال)</label>
                                <input type="number" class="form-control" name="total_cost" min="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">کیلومتر هنگام سوخت‌گیری *</label>
                                <input type="number" class="form-control" name="mileage_at_fuel" min="0" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">شماره کارت سوخت</label>
                                <input type="text" class="form-control" name="fuel_card_number">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">شماره رسید</label>
                                <input type="text" class="form-control" name="receipt_number">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">سوخت‌گیری توسط</label>
                                <input type="text" class="form-control" name="filled_by">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">یادداشت‌ها</label>
                                <textarea class="form-control" name="notes" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" name="add_fuel" class="btn btn-success">
                            <i class="fas fa-save"></i> ثبت سوخت‌گیری
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Set current datetime for handover
        document.addEventListener('DOMContentLoaded', function() {
            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            const currentDateTime = now.toISOString().slice(0, 16);
            
            const handoverDateInput = document.querySelector('input[name="handover_date"]');
            if (handoverDateInput) {
                handoverDateInput.value = currentDateTime;
            }
            
            const fuelDateInput = document.querySelector('input[name="fuel_date"]');
            if (fuelDateInput) {
                fuelDateInput.value = currentDateTime;
            }
            
            const returnDateInput = document.querySelector('input[name="actual_return_date"]');
            if (returnDateInput) {
                returnDateInput.value = currentDateTime;
            }
        });

        function viewCarDetails(carId) {
            // Implementation for viewing car details
            alert('نمایش جزئیات خودرو - در حال توسعه');
        }

        function handoverCar(carId) {
            const modal = new bootstrap.Modal(document.getElementById('handoverModal'));
            document.querySelector('#handoverModal select[name="car_id"]').value = carId;
            modal.show();
        }

        function returnCar(carId) {
            // Find the active handover for this car
            alert('بازگشت خودرو - در حال توسعه');
        }

        function returnCarModal(handoverId) {
            const modal = new bootstrap.Modal(document.getElementById('returnModal'));
            document.getElementById('return_handover_id').value = handoverId;
            modal.show();
        }

        function addMaintenanceForCar(carId) {
            const modal = new bootstrap.Modal(document.getElementById('maintenanceModal'));
            document.querySelector('#maintenanceModal select[name="car_id"]').value = carId;
            modal.show();
        }

        function renewDocument(documentId) {
            alert('تمدید سند - در حال توسعه');
        }

        // Auto-calculate total cost in fuel modal
        document.addEventListener('input', function(e) {
            if (e.target.name === 'liters' || e.target.name === 'price_per_liter') {
                const liters = parseFloat(document.querySelector('input[name="liters"]').value) || 0;
                const pricePerLiter = parseFloat(document.querySelector('input[name="price_per_liter"]').value) || 0;
                const totalCost = liters * pricePerLiter;
                document.querySelector('input[name="total_cost"]').value = totalCost.toFixed(0);
            }
        });
    </script>
</body>
</html>