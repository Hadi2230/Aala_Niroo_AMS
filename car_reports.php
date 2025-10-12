<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// دریافت پارامترهای فیلتر
$date_from = isset($_GET['date_from']) ? sanitizeInput($_GET['date_from']) : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? sanitizeInput($_GET['date_to']) : date('Y-m-d');
$car_id = isset($_GET['car_id']) ? intval($_GET['car_id']) : 0;
$driver_id = isset($_GET['driver_id']) ? intval($_GET['driver_id']) : 0;
$report_type = isset($_GET['report_type']) ? sanitizeInput($_GET['report_type']) : 'summary';

// دریافت لیست خودروها و رانندگان
$cars = $pdo->query("SELECT id, brand, model, plate_number FROM company_cars ORDER BY brand, model")->fetchAll();
$drivers = $pdo->query("SELECT id, full_name FROM car_drivers ORDER BY full_name")->fetchAll();

// آمار کلی
$stats = [];

// تعداد کل خودروها
$stats['total_cars'] = $pdo->query("SELECT COUNT(*) FROM company_cars WHERE is_active = 1")->fetchColumn();

// تعداد رانندگان فعال
$stats['active_drivers'] = $pdo->query("SELECT COUNT(*) FROM car_drivers WHERE status = 'فعال'")->fetchColumn();

// کل مسافت طی شده در بازه
$query = "SELECT SUM(total_distance_km) as total FROM car_handovers 
          WHERE handover_date BETWEEN ? AND ? AND status = 'تکمیل شده'";
$params = [$date_from, $date_to];
if ($car_id) {
    $query .= " AND car_id = ?";
    $params[] = $car_id;
}
if ($driver_id) {
    $query .= " AND driver_id = ?";
    $params[] = $driver_id;
}
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$stats['total_distance'] = $stmt->fetchColumn() ?: 0;

// تعداد سفرها
$query = "SELECT COUNT(*) FROM car_handovers 
          WHERE handover_date BETWEEN ? AND ? AND status = 'تکمیل شده'";
$stmt = $pdo->prepare($query);
$stmt->execute([$date_from, $date_to]);
$stats['total_trips'] = $stmt->fetchColumn();

// میانگین مسافت هر سفر
$stats['avg_distance'] = $stats['total_trips'] > 0 ? $stats['total_distance'] / $stats['total_trips'] : 0;

// هزینه‌ها
$query = "SELECT 
    SUM(f.total_cost) as fuel_cost,
    SUM(m.total_cost) as maintenance_cost,
    SUM(v.fine_amount) as violation_cost
    FROM company_cars c
    LEFT JOIN car_fuel_logs f ON c.id = f.car_id AND f.fuel_date BETWEEN ? AND ?
    LEFT JOIN car_maintenance m ON c.id = m.car_id AND m.service_date BETWEEN ? AND ?
    LEFT JOIN car_violations v ON c.id = v.car_id AND v.violation_date BETWEEN ? AND ?
    WHERE 1=1";

$params = [$date_from, $date_to, $date_from, $date_to, $date_from, $date_to];

if ($car_id) {
    $query .= " AND c.id = ?";
    $params[] = $car_id;
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$costs = $stmt->fetch();

$stats['fuel_cost'] = $costs['fuel_cost'] ?: 0;
$stats['maintenance_cost'] = $costs['maintenance_cost'] ?: 0;
$stats['violation_cost'] = $costs['violation_cost'] ?: 0;
$stats['total_cost'] = $stats['fuel_cost'] + $stats['maintenance_cost'] + $stats['violation_cost'];

// گزارشات بر اساس نوع
$report_data = [];

if ($report_type == 'summary') {
    // گزارش خلاصه
    $query = "SELECT 
        c.id, c.brand, c.model, c.plate_number, c.current_odometer,
        c.current_status, c.current_fuel_level,
        COUNT(DISTINCT h.id) as trip_count,
        SUM(h.total_distance_km) as total_distance,
        COUNT(DISTINCT f.id) as fuel_count,
        SUM(f.total_cost) as fuel_cost,
        COUNT(DISTINCT m.id) as maintenance_count,
        COUNT(DISTINCT v.id) as violation_count
        FROM company_cars c
        LEFT JOIN car_handovers h ON c.id = h.car_id 
            AND h.handover_date BETWEEN ? AND ? 
            AND h.status = 'تکمیل شده'
        LEFT JOIN car_fuel_logs f ON c.id = f.car_id 
            AND f.fuel_date BETWEEN ? AND ?
        LEFT JOIN car_maintenance m ON c.id = m.car_id 
            AND m.service_date BETWEEN ? AND ?
        LEFT JOIN car_violations v ON c.id = v.car_id 
            AND v.violation_date BETWEEN ? AND ?
        WHERE c.is_active = 1";
    
    $params = [$date_from, $date_to, $date_from, $date_to, $date_from, $date_to, $date_from, $date_to];
    
    if ($car_id) {
        $query .= " AND c.id = ?";
        $params[] = $car_id;
    }
    
    $query .= " GROUP BY c.id ORDER BY c.brand, c.model";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $report_data = $stmt->fetchAll();
    
} elseif ($report_type == 'fuel') {
    // گزارش مصرف سوخت
    $query = "SELECT 
        f.*, c.brand, c.model, c.plate_number, d.full_name as driver_name
        FROM car_fuel_logs f
        JOIN company_cars c ON f.car_id = c.id
        JOIN car_drivers d ON f.driver_id = d.id
        WHERE f.fuel_date BETWEEN ? AND ?";
    
    $params = [$date_from, $date_to];
    
    if ($car_id) {
        $query .= " AND f.car_id = ?";
        $params[] = $car_id;
    }
    if ($driver_id) {
        $query .= " AND f.driver_id = ?";
        $params[] = $driver_id;
    }
    
    $query .= " ORDER BY f.fuel_date DESC, f.fuel_time DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $report_data = $stmt->fetchAll();
    
} elseif ($report_type == 'maintenance') {
    // گزارش تعمیرات
    $query = "SELECT 
        m.*, c.brand, c.model, c.plate_number
        FROM car_maintenance m
        JOIN company_cars c ON m.car_id = c.id
        WHERE m.service_date BETWEEN ? AND ?";
    
    $params = [$date_from, $date_to];
    
    if ($car_id) {
        $query .= " AND m.car_id = ?";
        $params[] = $car_id;
    }
    
    $query .= " ORDER BY m.service_date DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $report_data = $stmt->fetchAll();
    
} elseif ($report_type == 'violations') {
    // گزارش تخلفات
    $query = "SELECT 
        v.*, c.brand, c.model, c.plate_number, d.full_name as driver_name
        FROM car_violations v
        JOIN company_cars c ON v.car_id = c.id
        JOIN car_drivers d ON v.driver_id = d.id
        WHERE v.violation_date BETWEEN ? AND ?";
    
    $params = [$date_from, $date_to];
    
    if ($car_id) {
        $query .= " AND v.car_id = ?";
        $params[] = $car_id;
    }
    if ($driver_id) {
        $query .= " AND v.driver_id = ?";
        $params[] = $driver_id;
    }
    
    $query .= " ORDER BY v.violation_date DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $report_data = $stmt->fetchAll();
    
} elseif ($report_type == 'drivers') {
    // گزارش عملکرد رانندگان
    $query = "SELECT 
        d.*, ds.overall_score, ds.total_trips, ds.total_distance,
        COUNT(DISTINCT h.id) as period_trips,
        SUM(h.total_distance_km) as period_distance,
        COUNT(DISTINCT v.id) as violation_count,
        SUM(v.fine_amount) as violation_amount
        FROM car_drivers d
        LEFT JOIN driver_scores ds ON d.id = ds.driver_id
        LEFT JOIN car_handovers h ON d.id = h.driver_id 
            AND h.handover_date BETWEEN ? AND ? 
            AND h.status = 'تکمیل شده'
        LEFT JOIN car_violations v ON d.id = v.driver_id 
            AND v.violation_date BETWEEN ? AND ?
        WHERE d.status = 'فعال'";
    
    $params = [$date_from, $date_to, $date_from, $date_to];
    
    if ($driver_id) {
        $query .= " AND d.id = ?";
        $params[] = $driver_id;
    }
    
    $query .= " GROUP BY d.id ORDER BY ds.overall_score DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $report_data = $stmt->fetchAll();
    
} elseif ($report_type == 'trips') {
    // گزارش سفرها
    $query = "SELECT 
        h.*, c.brand, c.model, c.plate_number, d.full_name as driver_name,
        u1.full_name as handover_by_name, u2.full_name as return_by_name
        FROM car_handovers h
        JOIN company_cars c ON h.car_id = c.id
        JOIN car_drivers d ON h.driver_id = d.id
        JOIN users u1 ON h.handover_by = u1.id
        LEFT JOIN users u2 ON h.return_received_by = u2.id
        WHERE h.handover_date BETWEEN ? AND ? AND h.status = 'تکمیل شده'";
    
    $params = [$date_from, $date_to];
    
    if ($car_id) {
        $query .= " AND h.car_id = ?";
        $params[] = $car_id;
    }
    if ($driver_id) {
        $query .= " AND h.driver_id = ?";
        $params[] = $driver_id;
    }
    
    $query .= " ORDER BY h.handover_date DESC, h.handover_time DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $report_data = $stmt->fetchAll();
}

// نمودارهای آماری
$chart_data = [];

// نمودار استفاده روزانه
$query = "SELECT DATE(handover_date) as date, COUNT(*) as count, SUM(total_distance_km) as distance
          FROM car_handovers 
          WHERE handover_date BETWEEN ? AND ? AND status = 'تکمیل شده'
          GROUP BY DATE(handover_date)
          ORDER BY date";
$stmt = $pdo->prepare($query);
$stmt->execute([$date_from, $date_to]);
$chart_data['daily_usage'] = $stmt->fetchAll();

// نمودار توزیع هدف سفرها
$query = "SELECT purpose, COUNT(*) as count, SUM(total_distance_km) as distance
          FROM car_handovers 
          WHERE handover_date BETWEEN ? AND ? AND status = 'تکمیل شده'
          GROUP BY purpose
          ORDER BY count DESC";
$stmt = $pdo->prepare($query);
$stmt->execute([$date_from, $date_to]);
$chart_data['purpose_distribution'] = $stmt->fetchAll();

// خودروهای پرکاربرد
$query = "SELECT c.brand, c.model, c.plate_number, COUNT(h.id) as usage_count
          FROM company_cars c
          JOIN car_handovers h ON c.id = h.car_id
          WHERE h.handover_date BETWEEN ? AND ? AND h.status = 'تکمیل شده'
          GROUP BY c.id
          ORDER BY usage_count DESC
          LIMIT 5";
$stmt = $pdo->prepare($query);
$stmt->execute([$date_from, $date_to]);
$chart_data['top_cars'] = $stmt->fetchAll();

// رانندگان برتر
$query = "SELECT d.full_name, COUNT(h.id) as trip_count, SUM(h.total_distance_km) as total_distance
          FROM car_drivers d
          JOIN car_handovers h ON d.id = h.driver_id
          WHERE h.handover_date BETWEEN ? AND ? AND h.status = 'تکمیل شده'
          GROUP BY d.id
          ORDER BY total_distance DESC
          LIMIT 5";
$stmt = $pdo->prepare($query);
$stmt->execute([$date_from, $date_to]);
$chart_data['top_drivers'] = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>گزارشات خودرو - سامانه اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stat-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .stat-box h3 {
            font-size: 2rem;
            margin: 0;
        }
        .stat-box p {
            margin: 0;
            opacity: 0.9;
        }
        .report-card {
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 20px;
        }
        .table-container {
            max-height: 600px;
            overflow-y: auto;
        }
        .export-btn {
            border-radius: 20px;
            padding: 8px 20px;
        }
        .filter-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .nav-pills .nav-link {
            border-radius: 20px;
            margin: 0 5px;
        }
        .nav-pills .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .badge-status {
            padding: 5px 10px;
            border-radius: 10px;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            .report-card {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container-fluid mt-4">
        <!-- فیلترها -->
        <div class="filter-card no-print">
            <form method="GET" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">از تاریخ</label>
                    <input type="date" name="date_from" class="form-control" 
                           value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">تا تاریخ</label>
                    <input type="date" name="date_to" class="form-control" 
                           value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">خودرو</label>
                    <select name="car_id" class="form-select">
                        <option value="">همه خودروها</option>
                        <?php foreach ($cars as $car): ?>
                            <option value="<?php echo $car['id']; ?>" 
                                    <?php echo $car_id == $car['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($car['brand'] . ' ' . $car['model'] . ' - ' . $car['plate_number']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">راننده</label>
                    <select name="driver_id" class="form-select">
                        <option value="">همه رانندگان</option>
                        <?php foreach ($drivers as $driver): ?>
                            <option value="<?php echo $driver['id']; ?>" 
                                    <?php echo $driver_id == $driver['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($driver['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary d-block w-100">
                        <i class="bi bi-search"></i> اعمال فیلتر
                    </button>
                </div>
            </form>
        </div>

        <!-- تب‌های گزارش -->
        <ul class="nav nav-pills mb-4 no-print" role="tablist">
            <li class="nav-item">
                <a class="nav-link <?php echo $report_type == 'summary' ? 'active' : ''; ?>" 
                   href="?report_type=summary&<?php echo http_build_query(array_diff_key($_GET, ['report_type' => ''])); ?>">
                    <i class="bi bi-grid"></i> خلاصه
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $report_type == 'trips' ? 'active' : ''; ?>" 
                   href="?report_type=trips&<?php echo http_build_query(array_diff_key($_GET, ['report_type' => ''])); ?>">
                    <i class="bi bi-geo"></i> سفرها
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $report_type == 'fuel' ? 'active' : ''; ?>" 
                   href="?report_type=fuel&<?php echo http_build_query(array_diff_key($_GET, ['report_type' => ''])); ?>">
                    <i class="bi bi-fuel-pump"></i> سوخت
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $report_type == 'maintenance' ? 'active' : ''; ?>" 
                   href="?report_type=maintenance&<?php echo http_build_query(array_diff_key($_GET, ['report_type' => ''])); ?>">
                    <i class="bi bi-tools"></i> تعمیرات
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $report_type == 'violations' ? 'active' : ''; ?>" 
                   href="?report_type=violations&<?php echo http_build_query(array_diff_key($_GET, ['report_type' => ''])); ?>">
                    <i class="bi bi-exclamation-triangle"></i> تخلفات
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $report_type == 'drivers' ? 'active' : ''; ?>" 
                   href="?report_type=drivers&<?php echo http_build_query(array_diff_key($_GET, ['report_type' => ''])); ?>">
                    <i class="bi bi-people"></i> رانندگان
                </a>
            </li>
        </ul>

        <!-- آمار کلی -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="stat-box">
                    <h3><?php echo number_format($stats['total_distance']); ?></h3>
                    <p>کیلومتر طی شده</p>
                    <i class="bi bi-speedometer2 float-end fs-1 opacity-50"></i>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-box" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <h3><?php echo number_format($stats['total_trips']); ?></h3>
                    <p>تعداد سفرها</p>
                    <i class="bi bi-geo-alt float-end fs-1 opacity-50"></i>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-box" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <h3><?php echo number_format($stats['total_cost']); ?></h3>
                    <p>هزینه کل (ریال)</p>
                    <i class="bi bi-cash-stack float-end fs-1 opacity-50"></i>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-box" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <h3><?php echo number_format($stats['avg_distance'], 1); ?></h3>
                    <p>میانگین مسافت هر سفر</p>
                    <i class="bi bi-graph-up float-end fs-1 opacity-50"></i>
                </div>
            </div>
        </div>

        <!-- نمودارها -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card report-card">
                    <div class="card-header">
                        <h5 class="mb-0">استفاده روزانه</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="dailyUsageChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card report-card">
                    <div class="card-header">
                        <h5 class="mb-0">توزیع اهداف سفر</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="purposeChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- جدول گزارش -->
        <div class="card report-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <?php
                    switch($report_type) {
                        case 'summary': echo 'گزارش خلاصه خودروها'; break;
                        case 'trips': echo 'گزارش سفرها'; break;
                        case 'fuel': echo 'گزارش مصرف سوخت'; break;
                        case 'maintenance': echo 'گزارش تعمیرات و نگهداری'; break;
                        case 'violations': echo 'گزارش تخلفات'; break;
                        case 'drivers': echo 'گزارش عملکرد رانندگان'; break;
                    }
                    ?>
                </h5>
                <div class="no-print">
                    <button onclick="exportToExcel()" class="btn btn-success export-btn">
                        <i class="bi bi-file-earmark-excel"></i> Excel
                    </button>
                    <button onclick="window.print()" class="btn btn-primary export-btn">
                        <i class="bi bi-printer"></i> چاپ
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-container">
                    <table class="table table-hover" id="reportTable">
                        <?php if ($report_type == 'summary'): ?>
                            <thead>
                                <tr>
                                    <th>خودرو</th>
                                    <th>پلاک</th>
                                    <th>وضعیت</th>
                                    <th>کیلومتر فعلی</th>
                                    <th>تعداد سفر</th>
                                    <th>مسافت کل</th>
                                    <th>سوخت‌گیری</th>
                                    <th>هزینه سوخت</th>
                                    <th>تعمیرات</th>
                                    <th>تخلفات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['brand'] . ' ' . $row['model']); ?></td>
                                    <td><?php echo htmlspecialchars($row['plate_number']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            switch($row['current_status']) {
                                                case 'در دسترس': echo 'success'; break;
                                                case 'در حال استفاده': echo 'info'; break;
                                                case 'در تعمیرگاه': echo 'warning'; break;
                                                default: echo 'secondary';
                                            }
                                        ?>">
                                            <?php echo htmlspecialchars($row['current_status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo number_format($row['current_odometer']); ?> کیلومتر</td>
                                    <td><?php echo $row['trip_count']; ?></td>
                                    <td><?php echo number_format($row['total_distance']); ?> کیلومتر</td>
                                    <td><?php echo $row['fuel_count']; ?></td>
                                    <td><?php echo number_format($row['fuel_cost']); ?> ریال</td>
                                    <td><?php echo $row['maintenance_count']; ?></td>
                                    <td><?php echo $row['violation_count']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            
                        <?php elseif ($report_type == 'trips'): ?>
                            <thead>
                                <tr>
                                    <th>کد تحویل</th>
                                    <th>خودرو</th>
                                    <th>راننده</th>
                                    <th>تاریخ تحویل</th>
                                    <th>تاریخ عودت</th>
                                    <th>مسافت</th>
                                    <th>هدف</th>
                                    <th>مقصد</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['handover_code']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($row['brand'] . ' ' . $row['model']); ?><br>
                                        <small><?php echo htmlspecialchars($row['plate_number']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['driver_name']); ?></td>
                                    <td>
                                        <?php echo jalaliDate($row['handover_date']); ?><br>
                                        <small><?php echo $row['handover_time']; ?></small>
                                    </td>
                                    <td>
                                        <?php echo jalaliDate($row['return_date']); ?><br>
                                        <small><?php echo $row['return_time']; ?></small>
                                    </td>
                                    <td><?php echo number_format($row['total_distance_km']); ?> کیلومتر</td>
                                    <td><?php echo htmlspecialchars($row['purpose']); ?></td>
                                    <td><?php echo htmlspecialchars($row['destination'] ?? '-'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            
                        <?php elseif ($report_type == 'fuel'): ?>
                            <thead>
                                <tr>
                                    <th>تاریخ</th>
                                    <th>خودرو</th>
                                    <th>راننده</th>
                                    <th>جایگاه</th>
                                    <th>کیلومتر</th>
                                    <th>نوع سوخت</th>
                                    <th>لیتر</th>
                                    <th>قیمت واحد</th>
                                    <th>هزینه کل</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data as $row): ?>
                                <tr>
                                    <td>
                                        <?php echo jalaliDate($row['fuel_date']); ?><br>
                                        <small><?php echo $row['fuel_time']; ?></small>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($row['brand'] . ' ' . $row['model']); ?><br>
                                        <small><?php echo htmlspecialchars($row['plate_number']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['driver_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['station_name'] ?? '-'); ?></td>
                                    <td><?php echo number_format($row['odometer_reading']); ?></td>
                                    <td><?php echo htmlspecialchars($row['fuel_type']); ?></td>
                                    <td><?php echo number_format($row['fuel_liters'], 2); ?></td>
                                    <td><?php echo number_format($row['fuel_price_per_liter']); ?> ریال</td>
                                    <td><?php echo number_format($row['total_cost']); ?> ریال</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            
                        <?php elseif ($report_type == 'maintenance'): ?>
                            <thead>
                                <tr>
                                    <th>تاریخ</th>
                                    <th>خودرو</th>
                                    <th>نوع سرویس</th>
                                    <th>کیلومتر</th>
                                    <th>تعمیرگاه</th>
                                    <th>شرح کار</th>
                                    <th>هزینه قطعات</th>
                                    <th>دستمزد</th>
                                    <th>هزینه کل</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data as $row): ?>
                                <tr>
                                    <td><?php echo jalaliDate($row['service_date']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($row['brand'] . ' ' . $row['model']); ?><br>
                                        <small><?php echo htmlspecialchars($row['plate_number']); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo htmlspecialchars($row['maintenance_type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo number_format($row['odometer_reading']); ?></td>
                                    <td><?php echo htmlspecialchars($row['workshop_name'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars(mb_substr($row['work_description'] ?? '', 0, 50)); ?>...</td>
                                    <td><?php echo number_format($row['parts_cost']); ?> ریال</td>
                                    <td><?php echo number_format($row['labor_cost']); ?> ریال</td>
                                    <td><?php echo number_format($row['total_cost']); ?> ریال</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            
                        <?php elseif ($report_type == 'violations'): ?>
                            <thead>
                                <tr>
                                    <th>تاریخ</th>
                                    <th>خودرو</th>
                                    <th>راننده</th>
                                    <th>نوع تخلف</th>
                                    <th>محل</th>
                                    <th>جریمه</th>
                                    <th>نمره منفی</th>
                                    <th>وضعیت پرداخت</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data as $row): ?>
                                <tr>
                                    <td>
                                        <?php echo jalaliDate($row['violation_date']); ?><br>
                                        <small><?php echo $row['violation_time'] ?? '-'; ?></small>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($row['brand'] . ' ' . $row['model']); ?><br>
                                        <small><?php echo htmlspecialchars($row['plate_number']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['driver_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['violation_type']); ?></td>
                                    <td><?php echo htmlspecialchars($row['location'] ?? '-'); ?></td>
                                    <td><?php echo number_format($row['fine_amount']); ?> ریال</td>
                                    <td><?php echo $row['points_deducted']; ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            switch($row['payment_status']) {
                                                case 'پرداخت شده': echo 'success'; break;
                                                case 'پرداخت نشده': echo 'danger'; break;
                                                default: echo 'warning';
                                            }
                                        ?>">
                                            <?php echo htmlspecialchars($row['payment_status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            
                        <?php elseif ($report_type == 'drivers'): ?>
                            <thead>
                                <tr>
                                    <th>راننده</th>
                                    <th>کد ملی</th>
                                    <th>امتیاز کلی</th>
                                    <th>سفرهای دوره</th>
                                    <th>مسافت دوره</th>
                                    <th>کل سفرها</th>
                                    <th>کل مسافت</th>
                                    <th>تخلفات</th>
                                    <th>جریمه‌ها</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['national_code']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            if ($row['overall_score'] >= 90) echo 'success';
                                            elseif ($row['overall_score'] >= 70) echo 'warning';
                                            else echo 'danger';
                                        ?>">
                                            <?php echo $row['overall_score'] ?? 100; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $row['period_trips']; ?></td>
                                    <td><?php echo number_format($row['period_distance']); ?> کیلومتر</td>
                                    <td><?php echo $row['total_trips'] ?? 0; ?></td>
                                    <td><?php echo number_format($row['total_distance'] ?? 0); ?> کیلومتر</td>
                                    <td><?php echo $row['violation_count']; ?></td>
                                    <td><?php echo number_format($row['violation_amount']); ?> ریال</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>

        <!-- خودروها و رانندگان برتر -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card report-card">
                    <div class="card-header">
                        <h5 class="mb-0">خودروهای پرکاربرد</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group">
                            <?php foreach ($chart_data['top_cars'] as $car): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?> - 
                                <?php echo htmlspecialchars($car['plate_number']); ?>
                                <span class="badge bg-primary rounded-pill">
                                    <?php echo $car['usage_count']; ?> سفر
                                </span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card report-card">
                    <div class="card-header">
                        <h5 class="mb-0">رانندگان برتر</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group">
                            <?php foreach ($chart_data['top_drivers'] as $driver): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?php echo htmlspecialchars($driver['full_name']); ?>
                                <div>
                                    <span class="badge bg-info rounded-pill me-2">
                                        <?php echo $driver['trip_count']; ?> سفر
                                    </span>
                                    <span class="badge bg-success rounded-pill">
                                        <?php echo number_format($driver['total_distance']); ?> کیلومتر
                                    </span>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // نمودار استفاده روزانه
        const dailyUsageCtx = document.getElementById('dailyUsageChart').getContext('2d');
        const dailyUsageData = <?php echo json_encode($chart_data['daily_usage']); ?>;
        
        new Chart(dailyUsageCtx, {
            type: 'line',
            data: {
                labels: dailyUsageData.map(d => d.date),
                datasets: [{
                    label: 'تعداد سفر',
                    data: dailyUsageData.map(d => d.count),
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    yAxisID: 'y',
                }, {
                    label: 'مسافت (کیلومتر)',
                    data: dailyUsageData.map(d => d.distance),
                    borderColor: 'rgb(255, 99, 132)',
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    yAxisID: 'y1',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: {
                            drawOnChartArea: false,
                        },
                    },
                }
            }
        });

        // نمودار توزیع اهداف
        const purposeCtx = document.getElementById('purposeChart').getContext('2d');
        const purposeData = <?php echo json_encode($chart_data['purpose_distribution']); ?>;
        
        new Chart(purposeCtx, {
            type: 'doughnut',
            data: {
                labels: purposeData.map(d => d.purpose),
                datasets: [{
                    data: purposeData.map(d => d.count),
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.8)',
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(255, 206, 86, 0.8)',
                        'rgba(75, 192, 192, 0.8)',
                        'rgba(153, 102, 255, 0.8)',
                        'rgba(255, 159, 64, 0.8)'
                    ],
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });

        // صدور به Excel
        function exportToExcel() {
            const table = document.getElementById('reportTable');
            const html = table.outerHTML;
            const url = 'data:application/vnd.ms-excel,' + encodeURIComponent(html);
            const downloadLink = document.createElement('a');
            downloadLink.href = url;
            downloadLink.download = 'car_report_<?php echo date('Y-m-d'); ?>.xls';
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
        }
    </script>
</body>
</html>