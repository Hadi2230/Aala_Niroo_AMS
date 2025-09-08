<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// ایجاد داده‌های نمونه برای تست
if (isset($_GET['create_sample_data'])) {
    try {
        // ایجاد مشتری نمونه
        $stmt = $pdo->prepare("INSERT INTO customers (full_name, phone, company, address) VALUES (?, ?, ?, ?)");
        $stmt->execute(['شرکت نمونه', '09123456789', 'شرکت نمونه', 'تهران، خیابان نمونه']);
        $customer_id = $pdo->lastInsertId();
        
        // ایجاد تیکت نمونه
        $ticket_id = createTicket($pdo, $customer_id, null, 'مشکل در ژنراتور', 'ژنراتور روشن نمی‌شود و صدای عجیبی می‌دهد', 'بالا', $_SESSION['user_id']);
        
        // ایجاد برنامه تعمیرات نمونه
        $future_date = date('Y-m-d', strtotime('+7 days'));
        $maintenance_id = createMaintenanceSchedule($pdo, 1, null, $future_date, 90, 'تعمیر دوره‌ای', $_SESSION['user_id']);
        
        echo "داده‌های نمونه با موفقیت ایجاد شدند!";
    } catch (Exception $e) {
        echo "خطا در ایجاد داده‌های نمونه: " . $e->getMessage();
    }
    exit();
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تست سیستم Workflow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <style>
        body { font-family: Vazirmatn, sans-serif; background-color: #f8f9fa; padding-top: 80px; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .card-header { background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); color: white; border-radius: 12px 12px 0 0 !important; font-weight: 600; }
        .btn-primary { background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); border: none; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h2>تست سیستم Workflow</h2>
                <p>این صفحه برای تست قابلیت‌های workflow ایجاد شده است.</p>
                
                <div class="card">
                    <div class="card-body">
                        <h5>دستورات تست:</h5>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>ایجاد داده‌های نمونه</span>
                                <a href="?create_sample_data=1" class="btn btn-primary btn-sm">ایجاد</a>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>مشاهده تیکت‌ها</span>
                                <a href="tickets.php" class="btn btn-info btn-sm">مشاهده</a>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>مشاهده تعمیرات</span>
                                <a href="maintenance.php" class="btn btn-warning btn-sm">مشاهده</a>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>بازگشت به داشبورد</span>
                                <a href="dashboard.php" class="btn btn-success btn-sm">بازگشت</a>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-body">
                        <h5>وضعیت سیستم:</h5>
                        <?php
                        // بررسی جداول
                        $tables = ['tickets', 'maintenance_schedules', 'notifications', 'messages'];
                        foreach ($tables as $table) {
                            try {
                                $count = $pdo->query("SELECT COUNT(*) as count FROM {$table}")->fetch()['count'];
                                echo "<p class='text-success'><i class='fa fa-check-circle'></i> جدول {$table}: {$count} رکورد</p>";
                            } catch (Exception $e) {
                                echo "<p class='text-danger'><i class='fa fa-times-circle'></i> جدول {$table}: خطا</p>";
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>