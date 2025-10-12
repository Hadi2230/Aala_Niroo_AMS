<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// دریافت آمار
$total_assets = $pdo->query("SELECT COUNT(*) as total FROM assets")->fetch()['total'];
$total_customers = $pdo->query("SELECT COUNT(*) as total FROM customers")->fetch()['total'];
$total_users = $pdo->query("SELECT COUNT(*) as total FROM users")->fetch()['total'];
$total_assignments = $pdo->query("SELECT COUNT(*) as total FROM asset_assignments")->fetch()['total'];
$assigned_assets = $pdo->query("SELECT COUNT(DISTINCT asset_id) as total FROM asset_assignments")->fetch()['total'];

// دریافت آمار ناوگان خودرو
$car_fleet_stats = getCarFleetStatistics($pdo);
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد - سامانه مدیریت اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">اعلا نیرو</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">داشبورد</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="assets.php">مدیریت دارایی‌ها</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="customers.php">مدیریت مشتریان</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="assignments.php">انتساب دستگاه</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="car_fleet.php">ناوگان خودرو</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">گزارش‌ها</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">خروج</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <h1 class="text-center">داشبورد مدیریت</h1>
        <p class="text-center">به سامانه مدیریت شرکت <strong>اعلا نیرو</strong> خوش آمدید.</p>

        <div class="row mt-5">
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h6 class="card-title">تعداد دارایی‌ها</h6>
                        <p class="card-text display-6"><?php echo $total_assets; ?></p>
                        <a href="assets.php" class="btn btn-primary btn-sm">مشاهده</a>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h6 class="card-title">تعداد مشتریان</h6>
                        <p class="card-text display-6"><?php echo $total_customers; ?></p>
                        <a href="customers.php" class="btn btn-primary btn-sm">مشاهده</a>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h6 class="card-title">تعداد کاربران</h6>
                        <p class="card-text display-6"><?php echo $total_users; ?></p>
                        <a href="users.php" class="btn btn-primary btn-sm">مشاهده</a>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h6 class="card-title">انتساب‌های فعال</h6>
                        <p class="card-text display-6"><?php echo $assigned_assets; ?></p>
                        <a href="assignments.php" class="btn btn-primary btn-sm">مدیریت</a>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="card text-center bg-info text-white">
                    <div class="card-body">
                        <h6 class="card-title">ناوگان خودرو</h6>
                        <p class="card-text display-6"><?php echo $car_fleet_stats['total_cars']; ?></p>
                        <a href="car_fleet.php" class="btn btn-light btn-sm">مدیریت</a>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="card text-center bg-warning text-dark">
                    <div class="card-body">
                        <h6 class="card-title">در حال استفاده</h6>
                        <p class="card-text display-6"><?php echo $car_fleet_stats['active_handovers']; ?></p>
                        <a href="car_fleet.php#handovers-tab" class="btn btn-dark btn-sm">مشاهده</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- آمار سریع -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">آمار دارایی‌ها</div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between">
                                    <span>دارایی‌های منتسب شده:</span>
                                    <span class="badge bg-success"><?php echo $assigned_assets; ?></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between">
                                    <span>دارایی‌های منتسب نشده:</span>
                                    <span class="badge bg-warning"><?php echo $total_assets - $assigned_assets; ?></span>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <span>کل انتساب‌ها:</span>
                            <span class="badge bg-info"><?php echo $total_assignments; ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-car"></i> آمار ناوگان خودرو
                        <a href="car_fleet.php" class="btn btn-sm btn-outline-primary float-end">مشاهده کامل</a>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between">
                                    <span>خودروهای آزاد:</span>
                                    <span class="badge bg-success"><?php echo $car_fleet_stats['total_cars'] - $car_fleet_stats['active_handovers']; ?></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between">
                                    <span>نیازمند سرویس:</span>
                                    <span class="badge bg-danger"><?php echo $car_fleet_stats['cars_needing_service']; ?></span>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-md-6">
                                <small class="text-muted">هزینه سوخت ماهانه:</small><br>
                                <strong><?php echo number_format($car_fleet_stats['monthly_fuel_cost']); ?> ریال</strong>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted">هزینه تعمیرات ماهانه:</small><br>
                                <strong><?php echo number_format($car_fleet_stats['monthly_maintenance_cost']); ?> ریال</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>