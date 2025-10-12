<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';
require_auth('کاربر عادی');

// دریافت آمار کامل
$fleet_stats = getCarFleetStatistics($pdo);

// دریافت فیلترها
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-t');
$car_id = $_GET['car_id'] ?? '';
$report_type = $_GET['report_type'] ?? 'overview';

// دریافت لیست خودروها برای فیلتر
$cars_stmt = $pdo->query("SELECT id, brand, model, license_plate, car_code FROM company_cars ORDER BY brand, model");
$cars = $cars_stmt->fetchAll();

// آمار بر اساس فیلترها
$where_conditions = ["1=1"];
$params = [];

if ($date_from) {
    $where_conditions[] = "DATE(created_at) >= ?";
    $params[] = $date_from;
}
if ($date_to) {
    $where_conditions[] = "DATE(created_at) <= ?";
    $params[] = $date_to;
}
if ($car_id) {
    $where_conditions[] = "car_id = ?";
    $params[] = $car_id;
}

$where_clause = implode(' AND ', $where_conditions);

// گزارش‌های مختلف
$reports = [];

if ($report_type === 'overview' || $report_type === 'all') {
    // آمار کلی
    $reports['overview'] = [
        'title' => 'آمار کلی ناوگان',
        'data' => $fleet_stats
    ];
}

if ($report_type === 'handovers' || $report_type === 'all') {
    // گزارش تحویل‌ها
    $handovers_stmt = $pdo->prepare("
        SELECT ch.*, cc.brand, cc.model, cc.license_plate, cc.car_code,
        DATEDIFF(COALESCE(ch.actual_return_date, NOW()), ch.handover_date) as usage_days,
        COALESCE(ch.mileage_at_return, cc.current_mileage) - ch.mileage_at_handover as mileage_used
        FROM car_handovers ch 
        JOIN company_cars cc ON ch.car_id = cc.id 
        WHERE $where_clause
        ORDER BY ch.handover_date DESC
    ");
    $handovers_stmt->execute($params);
    $reports['handovers'] = [
        'title' => 'گزارش تحویل‌ها',
        'data' => $handovers_stmt->fetchAll()
    ];
}

if ($report_type === 'maintenance' || $report_type === 'all') {
    // گزارش تعمیرات
    $maintenance_stmt = $pdo->prepare("
        SELECT cm.*, cc.brand, cc.model, cc.license_plate, cc.car_code,
        u.full_name as performed_by_name
        FROM car_maintenance cm 
        JOIN company_cars cc ON cm.car_id = cc.id 
        LEFT JOIN users u ON cm.performed_by = u.id
        WHERE $where_clause
        ORDER BY cm.maintenance_date DESC
    ");
    $maintenance_stmt->execute($params);
    $reports['maintenance'] = [
        'title' => 'گزارش تعمیرات و سرویس',
        'data' => $maintenance_stmt->fetchAll()
    ];
}

if ($report_type === 'fuel' || $report_type === 'all') {
    // گزارش سوخت
    $fuel_stmt = $pdo->prepare("
        SELECT cfr.*, cc.brand, cc.model, cc.license_plate, cc.car_code,
        ch.employee_name
        FROM car_fuel_records cfr 
        JOIN company_cars cc ON cfr.car_id = cc.id 
        LEFT JOIN car_handovers ch ON cfr.handover_id = ch.id
        WHERE $where_clause
        ORDER BY cfr.fuel_date DESC
    ");
    $fuel_stmt->execute($params);
    $reports['fuel'] = [
        'title' => 'گزارش سوخت‌گیری',
        'data' => $fuel_stmt->fetchAll()
    ];
}

if ($report_type === 'violations' || $report_type === 'all') {
    // گزارش تخلفات
    $violations_stmt = $pdo->prepare("
        SELECT cv.*, cc.brand, cc.model, cc.license_plate, cc.car_code,
        ch.employee_name
        FROM car_violations cv 
        JOIN company_cars cc ON cv.car_id = cc.id 
        LEFT JOIN car_handovers ch ON cv.handover_id = ch.id
        WHERE $where_clause
        ORDER BY cv.violation_date DESC
    ");
    $violations_stmt->execute($params);
    $reports['violations'] = [
        'title' => 'گزارش تخلفات',
        'data' => $violations_stmt->fetchAll()
    ];
}

// محاسبه آمار تجمیعی
$summary_stats = [];
if (isset($reports['fuel']['data'])) {
    $total_fuel_cost = array_sum(array_column($reports['fuel']['data'], 'total_cost'));
    $total_liters = array_sum(array_column($reports['fuel']['data'], 'liters'));
    $summary_stats['fuel'] = [
        'total_cost' => $total_fuel_cost,
        'total_liters' => $total_liters,
        'avg_price_per_liter' => $total_liters > 0 ? $total_fuel_cost / $total_liters : 0
    ];
}

if (isset($reports['maintenance']['data'])) {
    $total_maintenance_cost = array_sum(array_column($reports['maintenance']['data'], 'cost'));
    $summary_stats['maintenance'] = [
        'total_cost' => $total_maintenance_cost,
        'count' => count($reports['maintenance']['data'])
    ];
}

if (isset($reports['violations']['data'])) {
    $total_fine_amount = array_sum(array_column($reports['violations']['data'], 'fine_amount'));
    $summary_stats['violations'] = [
        'total_fines' => $total_fine_amount,
        'count' => count($reports['violations']['data'])
    ];
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>گزارشات ناوگان خودروهای شرکتی - سامانه اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <style>
        .report-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            transition: transform 0.3s;
        }
        .report-card:hover {
            transform: translateY(-5px);
        }
        .stat-item {
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
        }
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .filter-card {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-radius: 15px;
        }
        .table-responsive {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .export-btn {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            border: none;
            border-radius: 25px;
            color: white;
            padding: 10px 20px;
            transition: all 0.3s;
        }
        .export-btn:hover {
            transform: scale(1.05);
            color: white;
        }
        .summary-card {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
            border-radius: 15px;
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
                        <h2><i class="fas fa-chart-line text-primary"></i> گزارشات ناوگان خودروهای شرکتی</h2>
                        <p class="text-muted">تحلیل جامع عملکرد، هزینه‌ها و آمار ناوگان</p>
                    </div>
                    <div>
                        <button class="btn export-btn me-2" onclick="exportToExcel()">
                            <i class="fas fa-file-excel"></i> خروجی Excel
                        </button>
                        <button class="btn export-btn" onclick="printReport()">
                            <i class="fas fa-print"></i> چاپ گزارش
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card filter-card">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">از تاریخ</label>
                                <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">تا تاریخ</label>
                                <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">خودرو</label>
                                <select class="form-select" name="car_id">
                                    <option value="">همه خودروها</option>
                                    <?php foreach ($cars as $car): ?>
                                        <option value="<?php echo $car['id']; ?>" <?php echo $car_id == $car['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($car['brand'] . ' ' . $car['model'] . ' - ' . $car['license_plate']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">نوع گزارش</label>
                                <select class="form-select" name="report_type">
                                    <option value="overview" <?php echo $report_type === 'overview' ? 'selected' : ''; ?>>آمار کلی</option>
                                    <option value="handovers" <?php echo $report_type === 'handovers' ? 'selected' : ''; ?>>تحویل‌ها</option>
                                    <option value="maintenance" <?php echo $report_type === 'maintenance' ? 'selected' : ''; ?>>تعمیرات</option>
                                    <option value="fuel" <?php echo $report_type === 'fuel' ? 'selected' : ''; ?>>سوخت</option>
                                    <option value="violations" <?php echo $report_type === 'violations' ? 'selected' : ''; ?>>تخلفات</option>
                                    <option value="all" <?php echo $report_type === 'all' ? 'selected' : ''; ?>>همه گزارش‌ها</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-light">
                                    <i class="fas fa-search"></i> اعمال فیلتر
                                </button>
                                <a href="car_fleet_reports.php" class="btn btn-outline-light">
                                    <i class="fas fa-refresh"></i> پاک کردن فیلتر
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Statistics -->
        <?php if (!empty($summary_stats)): ?>
            <div class="row mb-4">
                <?php if (isset($summary_stats['fuel'])): ?>
                    <div class="col-md-4 mb-3">
                        <div class="card summary-card">
                            <div class="card-body text-center">
                                <i class="fas fa-gas-pump fa-3x mb-3"></i>
                                <h5>آمار سوخت</h5>
                                <div class="stat-item">
                                    <strong><?php echo number_format($summary_stats['fuel']['total_cost']); ?> ریال</strong><br>
                                    <small>کل هزینه سوخت</small>
                                </div>
                                <div class="stat-item">
                                    <strong><?php echo number_format($summary_stats['fuel']['total_liters'], 2); ?> لیتر</strong><br>
                                    <small>کل مصرف سوخت</small>
                                </div>
                                <div class="stat-item">
                                    <strong><?php echo number_format($summary_stats['fuel']['avg_price_per_liter']); ?> ریال</strong><br>
                                    <small>متوسط قیمت هر لیتر</small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (isset($summary_stats['maintenance'])): ?>
                    <div class="col-md-4 mb-3">
                        <div class="card summary-card">
                            <div class="card-body text-center">
                                <i class="fas fa-wrench fa-3x mb-3"></i>
                                <h5>آمار تعمیرات</h5>
                                <div class="stat-item">
                                    <strong><?php echo number_format($summary_stats['maintenance']['total_cost']); ?> ریال</strong><br>
                                    <small>کل هزینه تعمیرات</small>
                                </div>
                                <div class="stat-item">
                                    <strong><?php echo $summary_stats['maintenance']['count']; ?></strong><br>
                                    <small>تعداد سرویس‌ها</small>
                                </div>
                                <div class="stat-item">
                                    <strong><?php echo $summary_stats['maintenance']['count'] > 0 ? number_format($summary_stats['maintenance']['total_cost'] / $summary_stats['maintenance']['count']) : 0; ?> ریال</strong><br>
                                    <small>متوسط هزینه هر سرویس</small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (isset($summary_stats['violations'])): ?>
                    <div class="col-md-4 mb-3">
                        <div class="card summary-card">
                            <div class="card-body text-center">
                                <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                                <h5>آمار تخلفات</h5>
                                <div class="stat-item">
                                    <strong><?php echo number_format($summary_stats['violations']['total_fines']); ?> ریال</strong><br>
                                    <small>کل جریمه‌ها</small>
                                </div>
                                <div class="stat-item">
                                    <strong><?php echo $summary_stats['violations']['count']; ?></strong><br>
                                    <small>تعداد تخلفات</small>
                                </div>
                                <div class="stat-item">
                                    <strong><?php echo $summary_stats['violations']['count'] > 0 ? number_format($summary_stats['violations']['total_fines'] / $summary_stats['violations']['count']) : 0; ?> ریال</strong><br>
                                    <small>متوسط جریمه</small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Reports -->
        <?php foreach ($reports as $key => $report): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-table"></i> <?php echo $report['title']; ?></h5>
                        </div>
                        <div class="card-body">
                            <?php if ($key === 'overview'): ?>
                                <!-- Overview Report -->
                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <div class="card report-card text-center">
                                            <div class="card-body">
                                                <i class="fas fa-car fa-2x mb-2"></i>
                                                <h4><?php echo $report['data']['total_cars']; ?></h4>
                                                <p>کل خودروها</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <div class="card report-card text-center">
                                            <div class="card-body">
                                                <i class="fas fa-hand-holding fa-2x mb-2"></i>
                                                <h4><?php echo $report['data']['active_handovers']; ?></h4>
                                                <p>در حال استفاده</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <div class="card report-card text-center">
                                            <div class="card-body">
                                                <i class="fas fa-tools fa-2x mb-2"></i>
                                                <h4><?php echo $report['data']['cars_needing_service']; ?></h4>
                                                <p>نیازمند سرویس</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <div class="card report-card text-center">
                                            <div class="card-body">
                                                <i class="fas fa-file-times fa-2x mb-2"></i>
                                                <h4><?php echo $report['data']['expired_documents']; ?></h4>
                                                <p>اسناد منقضی</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Cars by Status Chart -->
                                <div class="chart-container mt-4">
                                    <h6>توزیع خودروها بر اساس وضعیت</h6>
                                    <canvas id="statusChart" width="400" height="200"></canvas>
                                </div>

                            <?php elseif ($key === 'handovers'): ?>
                                <!-- Handovers Report -->
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>کد تحویل</th>
                                                <th>خودرو</th>
                                                <th>تحویل‌گیرنده</th>
                                                <th>تاریخ تحویل</th>
                                                <th>تاریخ بازگشت</th>
                                                <th>مدت استفاده</th>
                                                <th>کیلومتر طی شده</th>
                                                <th>وضعیت</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($report['data'] as $handover): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($handover['handover_code']); ?></td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($handover['brand'] . ' ' . $handover['model']); ?></strong><br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($handover['license_plate']); ?></small>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($handover['employee_name']); ?></td>
                                                    <td><?php echo jalali_format($handover['handover_date'], 'Y/m/d H:i'); ?></td>
                                                    <td>
                                                        <?php if ($handover['actual_return_date']): ?>
                                                            <?php echo jalali_format($handover['actual_return_date'], 'Y/m/d H:i'); ?>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning">در حال استفاده</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo $handover['usage_days']; ?> روز</td>
                                                    <td><?php echo number_format($handover['mileage_used']); ?> کیلومتر</td>
                                                    <td>
                                                        <span class="badge <?php echo $handover['status'] === 'بازگشت داده شده' ? 'bg-success' : ($handover['status'] === 'دیرکرد' ? 'bg-danger' : 'bg-primary'); ?>">
                                                            <?php echo htmlspecialchars($handover['status']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                            <?php elseif ($key === 'maintenance'): ?>
                                <!-- Maintenance Report -->
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>خودرو</th>
                                                <th>نوع سرویس</th>
                                                <th>تاریخ</th>
                                                <th>کیلومتر</th>
                                                <th>ارائه‌دهنده</th>
                                                <th>هزینه</th>
                                                <th>کیفیت</th>
                                                <th>انجام‌دهنده</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($report['data'] as $maintenance): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($maintenance['brand'] . ' ' . $maintenance['model']); ?></strong><br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($maintenance['license_plate']); ?></small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info"><?php echo htmlspecialchars($maintenance['maintenance_type']); ?></span>
                                                    </td>
                                                    <td><?php echo jalali_format($maintenance['maintenance_date'], 'Y/m/d'); ?></td>
                                                    <td><?php echo number_format($maintenance['mileage_at_service']); ?></td>
                                                    <td><?php echo htmlspecialchars($maintenance['service_provider'] ?? '-'); ?></td>
                                                    <td><?php echo number_format($maintenance['cost']); ?> ریال</td>
                                                    <td>
                                                        <span class="badge <?php echo $maintenance['quality_rating'] === 'عالی' ? 'bg-success' : ($maintenance['quality_rating'] === 'خوب' ? 'bg-primary' : 'bg-warning'); ?>">
                                                            <?php echo htmlspecialchars($maintenance['quality_rating']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($maintenance['performed_by_name'] ?? '-'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                            <?php elseif ($key === 'fuel'): ?>
                                <!-- Fuel Report -->
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>خودرو</th>
                                                <th>تاریخ</th>
                                                <th>پمپ بنزین</th>
                                                <th>نوع سوخت</th>
                                                <th>مقدار</th>
                                                <th>قیمت</th>
                                                <th>کل هزینه</th>
                                                <th>کیلومتر</th>
                                                <th>راننده</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($report['data'] as $fuel): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($fuel['brand'] . ' ' . $fuel['model']); ?></strong><br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($fuel['license_plate']); ?></small>
                                                    </td>
                                                    <td><?php echo jalali_format($fuel['fuel_date'], 'Y/m/d H:i'); ?></td>
                                                    <td><?php echo htmlspecialchars($fuel['fuel_station'] ?? '-'); ?></td>
                                                    <td>
                                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($fuel['fuel_type']); ?></span>
                                                    </td>
                                                    <td><?php echo number_format($fuel['liters'], 2); ?> لیتر</td>
                                                    <td><?php echo number_format($fuel['price_per_liter']); ?> ریال</td>
                                                    <td><?php echo number_format($fuel['total_cost']); ?> ریال</td>
                                                    <td><?php echo number_format($fuel['mileage_at_fuel']); ?></td>
                                                    <td><?php echo htmlspecialchars($fuel['employee_name'] ?? $fuel['filled_by'] ?? '-'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                            <?php elseif ($key === 'violations'): ?>
                                <!-- Violations Report -->
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>خودرو</th>
                                                <th>تاریخ تخلف</th>
                                                <th>نوع تخلف</th>
                                                <th>محل</th>
                                                <th>مبلغ جریمه</th>
                                                <th>راننده مسئول</th>
                                                <th>وضعیت پرداخت</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($report['data'] as $violation): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($violation['brand'] . ' ' . $violation['model']); ?></strong><br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($violation['license_plate']); ?></small>
                                                    </td>
                                                    <td><?php echo jalali_format($violation['violation_date'], 'Y/m/d'); ?></td>
                                                    <td><?php echo htmlspecialchars($violation['violation_type']); ?></td>
                                                    <td><?php echo htmlspecialchars($violation['violation_location'] ?? '-'); ?></td>
                                                    <td><?php echo number_format($violation['fine_amount']); ?> ریال</td>
                                                    <td><?php echo htmlspecialchars($violation['employee_name'] ?? $violation['responsible_driver'] ?? '-'); ?></td>
                                                    <td>
                                                        <span class="badge <?php echo $violation['payment_status'] === 'پرداخت شده' ? 'bg-success' : 'bg-danger'; ?>">
                                                            <?php echo htmlspecialchars($violation['payment_status']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Status Chart
        <?php if (isset($reports['overview'])): ?>
            const statusData = <?php echo json_encode($fleet_stats['cars_by_status']); ?>;
            const statusLabels = statusData.map(item => item.status);
            const statusCounts = statusData.map(item => item.count);
            
            const ctx = document.getElementById('statusChart').getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: statusLabels,
                    datasets: [{
                        data: statusCounts,
                        backgroundColor: [
                            '#28a745',
                            '#dc3545',
                            '#ffc107',
                            '#17a2b8',
                            '#6f42c1'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        <?php endif; ?>

        function exportToExcel() {
            // Implementation for Excel export
            alert('خروجی Excel - در حال توسعه');
        }

        function printReport() {
            window.print();
        }
    </script>
</body>
</html>