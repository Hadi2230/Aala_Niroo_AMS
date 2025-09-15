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
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد - سامانه مدیریت اعلا نیرو</title>
    <?php include __DIR__ . '/partials/head.php'; ?>
</head>
<body class="<?php echo isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark' ? 'dark-mode' : ''; ?>">
    <?php include __DIR__ . '/navbar.php'; ?>

    <div class="container mt-5">
        <h1 class="text-center">داشبورد مدیریت</h1>
        <p class="text-center">به سامانه مدیریت شرکت <strong>اعلا نیرو</strong> خوش آمدید.</p>

        <div class="row mt-5">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">تعداد دارایی‌ها</h5>
                        <p class="card-text display-4"><?php echo $total_assets; ?></p>
                        <a href="assets.php" class="btn btn-primary">مشاهده دارایی‌ها</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">تعداد مشتریان</h5>
                        <p class="card-text display-4"><?php echo $total_customers; ?></p>
                        <a href="customers.php" class="btn btn-primary">مشاهده مشتریان</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">تعداد کاربران</h5>
                        <p class="card-text display-4"><?php echo $total_users; ?></p>
                        <a href="users.php" class="btn btn-primary">مشاهده کاربران</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">انتساب‌های فعال</h5>
                        <p class="card-text display-4"><?php echo $assigned_assets; ?> از <?php echo $total_assets; ?></p>
                        <a href="assignments.php" class="btn btn-primary">مدیریت انتساب‌ها</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- آمار سریع -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">آمار سریع</div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="d-flex justify-content-between">
                                    <span>دارایی‌های منتسب شده:</span>
                                    <span class="badge bg-success"><?php echo $assigned_assets; ?></span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex justify-content-between">
                                    <span>دارایی‌های منتسب نشده:</span>
                                    <span class="badge bg-warning"><?php echo $total_assets - $assigned_assets; ?></span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex justify-content-between">
                                    <span>کل انتساب‌ها:</span>
                                    <span class="badge bg-info"><?php echo $total_assignments; ?></span>
                                </div>
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