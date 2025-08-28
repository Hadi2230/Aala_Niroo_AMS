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
$last_login = null;
try {
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT last_login FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $last_login = $stmt->fetch()['last_login'] ?? null;
    }
} catch (Throwable $e) {}
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد - سامانه مدیریت اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-5">
        <h1 class="text-center">داشبورد مدیریت</h1>
        <p class="text-center">به سامانه مدیریت شرکت <strong>اعلا نیرو</strong> خوش آمدید.</p>
        <?php if ($last_login): ?>
            <p class="text-center text-muted">آخرین ورود شما: <?php echo htmlspecialchars($last_login); ?></p>
        <?php endif; ?>

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

        <!-- یادداشت‌های من -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">یادداشت‌های من</div>
                    <div class="card-body">
                        <form method="post" action="save_note.php">
                            <div class="mb-3">
                                <textarea class="form-control" name="note" rows="3" placeholder="یادداشت خود را بنویسید..."></textarea>
                            </div>
                            <button class="btn btn-primary" type="submit">ذخیره یادداشت</button>
                        </form>
                        <hr>
                        <div>
                            <?php
                            try {
                                if (isset($_SESSION['user_id'])) {
                                    $stmt = $pdo->prepare("SELECT note, updated_at FROM user_notes WHERE user_id = ? ORDER BY updated_at DESC LIMIT 10");
                                    $stmt->execute([$_SESSION['user_id']]);
                                    $notes = $stmt->fetchAll();
                                    if ($notes) {
                                        echo '<ul class="list-group">';
                                        foreach ($notes as $n) {
                                            echo '<li class="list-group-item d-flex justify-content-between align-items-center">'.htmlspecialchars($n['note']).'<span class="badge bg-secondary">'.htmlspecialchars($n['updated_at']).'</span></li>';
                                        }
                                        echo '</ul>';
                                    } else {
                                        echo '<p class="text-muted">یادداشتی موجود نیست.</p>';
                                    }
                                }
                            } catch (Throwable $e) {}
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>