<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// ثبت لاگ
logAction($pdo, 'VIEW_CAR_REPORTS', 'مشاهده صفحه گزارشات خودروها');

// دریافت فیلترها
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$car_id = $_GET['car_id'] ?? '';

// آمار کلی
$stats = getCarStatistics($pdo, $date_from, $date_to);

// آمار خودروها
$car_stats = $pdo->query("
    SELECT 
        cc.id,
        cc.car_plate,
        cc.car_name,
        cc.car_model,
        cc.current_mileage,
        cc.status,
        COUNT(ca.id) as total_assignments,
        COUNT(CASE WHEN ca.assignment_status = 'فعال' THEN 1 END) as active_assignments,
        COUNT(cm.id) as total_maintenance,
        COALESCE(SUM(cm.maintenance_cost), 0) as total_maintenance_cost,
        COUNT(cfr.id) as total_fuel_records,
        COALESCE(SUM(cfr.fuel_cost), 0) as total_fuel_cost,
        COALESCE(SUM(cfr.fuel_amount), 0) as total_fuel_amount,
        COALESCE(AVG(cfr.fuel_efficiency), 0) as avg_fuel_efficiency
    FROM company_cars cc
    LEFT JOIN car_assignments ca ON cc.id = ca.car_id
    LEFT JOIN car_maintenance cm ON cc.id = cm.car_id AND cm.maintenance_date BETWEEN ? AND ?
    LEFT JOIN car_fuel_records cfr ON cc.id = cfr.car_id AND cfr.fuel_date BETWEEN ? AND ?
    GROUP BY cc.id
    ORDER BY cc.car_name
", [$date_from, $date_to, $date_from, $date_to])->fetchAll();

// آمار ماهانه
$monthly_stats = $pdo->query("
    SELECT 
        DATE_FORMAT(fuel_date, '%Y-%m') as month,
        COUNT(*) as fuel_records,
        SUM(fuel_cost) as total_fuel_cost,
        SUM(fuel_amount) as total_fuel_amount,
        AVG(fuel_efficiency) as avg_efficiency
    FROM car_fuel_records 
    WHERE fuel_date BETWEEN ? AND ?
    GROUP BY DATE_FORMAT(fuel_date, '%Y-%m')
    ORDER BY month
", [$date_from, $date_to])->fetchAll();

// آمار تعمیرات
$maintenance_stats = $pdo->query("
    SELECT 
        maintenance_type,
        COUNT(*) as count,
        SUM(maintenance_cost) as total_cost
    FROM car_maintenance 
    WHERE maintenance_date BETWEEN ? AND ?
    GROUP BY maintenance_type
    ORDER BY count DESC
", [$date_from, $date_to])->fetchAll();

// آمار حوادث
$incident_stats = $pdo->query("
    SELECT 
        incident_type,
        COUNT(*) as count,
        SUM(actual_damage_cost) as total_damage
    FROM car_incidents 
    WHERE incident_date BETWEEN ? AND ?
    GROUP BY incident_type
    ORDER BY count DESC
", [$date_from, $date_to])->fetchAll();

// دریافت لیست خودروها
$cars = $pdo->query("SELECT id, car_plate, car_name FROM company_cars ORDER BY car_name")->fetchAll();
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>گزارشات خودروها - اعلا نیرو</title>
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
        .chart-container {
            position: relative;
            height: 400px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-chart-bar"></i> گزارشات خودروها</h2>
                </div>

                <!-- فیلتر تاریخ -->
                <div class="card search-box">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="date_from" class="form-label">از تاریخ</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" value="<?= $date_from ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="date_to" class="form-label">تا تاریخ</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" value="<?= $date_to ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="car_id" class="form-label">خودرو</label>
                                <select name="car_id" class="form-select">
                                    <option value="">همه خودروها</option>
                                    <?php foreach ($cars as $car): ?>
                                        <option value="<?= $car['id'] ?>" <?= $car_id == $car['id'] ? 'selected' : '' ?>>
                                            <?= $car['car_plate'] ?> - <?= $car['car_name'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-filter"></i> اعمال فیلتر
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- آمار کلی -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h3><?= $stats['total_cars'] ?></h3>
                            <p><i class="fas fa-car"></i> کل خودروها</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <h3><?= $stats['total_assignments'] ?></h3>
                            <p><i class="fas fa-user-check"></i> کل انتساب‌ها</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <h3><?= number_format($stats['maintenance_cost']) ?></h3>
                            <p><i class="fas fa-wrench"></i> هزینه تعمیرات (ریال)</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                            <h3><?= number_format($stats['fuel_cost']) ?></h3>
                            <p><i class="fas fa-gas-pump"></i> هزینه سوخت (ریال)</p>
                        </div>
                    </div>
                </div>

                <!-- نمودار آمار ماهانه -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-chart-line"></i> آمار ماهانه مصرف سوخت</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="monthlyChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- نمودار انواع تعمیرات -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-chart-pie"></i> انواع تعمیرات</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="maintenanceChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-chart-pie"></i> انواع حوادث</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="incidentChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- جدول آمار خودروها -->
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-table"></i> آمار تفصیلی خودروها</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>خودرو</th>
                                        <th>کیلومتر</th>
                                        <th>وضعیت</th>
                                        <th>انتساب‌ها</th>
                                        <th>تعمیرات</th>
                                        <th>هزینه تعمیر</th>
                                        <th>سوخت (لیتر)</th>
                                        <th>هزینه سوخت</th>
                                        <th>بازدهی</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($car_stats as $car): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($car['car_plate']) ?></strong><br>
                                            <small><?= htmlspecialchars($car['car_name']) ?></small>
                                        </td>
                                        <td><?= number_format($car['current_mileage']) ?></td>
                                        <td>
                                            <?php
                                            $status_class = 'secondary';
                                            if ($car['status'] == 'فعال') $status_class = 'success';
                                            if ($car['status'] == 'غیرفعال') $status_class = 'danger';
                                            if ($car['status'] == 'در تعمیر') $status_class = 'warning';
                                            ?>
                                            <span class="badge bg-<?= $status_class ?>"><?= $car['status'] ?></span>
                                        </td>
                                        <td>
                                            <?= $car['total_assignments'] ?>
                                            <?php if ($car['active_assignments'] > 0): ?>
                                                <small class="text-success">(<?= $car['active_assignments'] ?> فعال)</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $car['total_maintenance'] ?></td>
                                        <td>
                                            <?php if ($car['total_maintenance_cost'] > 0): ?>
                                                <span class="cost-highlight"><?= number_format($car['total_maintenance_cost']) ?></span>
                                            <?php else: ?>
                                                --
                                            <?php endif; ?>
                                        </td>
                                        <td><?= number_format($car['total_fuel_amount'], 1) ?></td>
                                        <td>
                                            <?php if ($car['total_fuel_cost'] > 0): ?>
                                                <span class="cost-highlight"><?= number_format($car['total_fuel_cost']) ?></span>
                                            <?php else: ?>
                                                --
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($car['avg_fuel_efficiency'] > 0): ?>
                                                <?= number_format($car['avg_fuel_efficiency'], 1) ?> کیلومتر/لیتر
                                            <?php else: ?>
                                                --
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- خلاصه تعمیرات -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-wrench"></i> خلاصه تعمیرات</h5>
                            </div>
                            <div class="card-body">
                                <?php if (count($maintenance_stats) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>نوع تعمیر</th>
                                                    <th>تعداد</th>
                                                    <th>هزینه</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($maintenance_stats as $maintenance): ?>
                                                <tr>
                                                    <td><?= $maintenance['maintenance_type'] ?></td>
                                                    <td><?= $maintenance['count'] ?></td>
                                                    <td><?= number_format($maintenance['total_cost']) ?> ریال</td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">هیچ تعمیری در این بازه زمانی ثبت نشده است.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-exclamation-triangle"></i> خلاصه حوادث</h5>
                            </div>
                            <div class="card-body">
                                <?php if (count($incident_stats) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>نوع حادثه</th>
                                                    <th>تعداد</th>
                                                    <th>خسارت</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($incident_stats as $incident): ?>
                                                <tr>
                                                    <td><?= $incident['incident_type'] ?></td>
                                                    <td><?= $incident['count'] ?></td>
                                                    <td><?= number_format($incident['total_damage']) ?> ریال</td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">هیچ حادثه‌ای در این بازه زمانی گزارش نشده است.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // نمودار آمار ماهانه
    const monthlyData = <?= json_encode($monthly_stats) ?>;
    const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
    new Chart(monthlyCtx, {
        type: 'line',
        data: {
            labels: monthlyData.map(item => item.month),
            datasets: [{
                label: 'هزینه سوخت (هزار ریال)',
                data: monthlyData.map(item => Math.round(item.total_fuel_cost / 1000)),
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                yAxisID: 'y'
            }, {
                label: 'مقدار سوخت (لیتر)',
                data: monthlyData.map(item => Math.round(item.total_fuel_amount)),
                borderColor: 'rgb(255, 99, 132)',
                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                yAxisID: 'y1'
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

    // نمودار انواع تعمیرات
    const maintenanceData = <?= json_encode($maintenance_stats) ?>;
    const maintenanceCtx = document.getElementById('maintenanceChart').getContext('2d');
    new Chart(maintenanceCtx, {
        type: 'doughnut',
        data: {
            labels: maintenanceData.map(item => item.maintenance_type),
            datasets: [{
                data: maintenanceData.map(item => item.count),
                backgroundColor: [
                    '#FF6384',
                    '#36A2EB',
                    '#FFCE56',
                    '#4BC0C0',
                    '#9966FF',
                    '#FF9F40'
                ]
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

    // نمودار انواع حوادث
    const incidentData = <?= json_encode($incident_stats) ?>;
    const incidentCtx = document.getElementById('incidentChart').getContext('2d');
    new Chart(incidentCtx, {
        type: 'doughnut',
        data: {
            labels: incidentData.map(item => item.incident_type),
            datasets: [{
                data: incidentData.map(item => item.count),
                backgroundColor: [
                    '#FF6384',
                    '#36A2EB',
                    '#FFCE56',
                    '#4BC0C0',
                    '#9966FF',
                    '#FF9F40'
                ]
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
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>