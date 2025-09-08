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
</head>
<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-12">
                <h2>تست سیستم Workflow</h2>
                <p>این صفحه برای تست قابلیت‌های workflow ایجاد شده است.</p>
                
                <div class="card">
                    <div class="card-body">
                        <h5>دستورات تست:</h5>
                        <ul>
                            <li><a href="?create_sample_data=1" class="btn btn-primary">ایجاد داده‌های نمونه</a></li>
                            <li><a href="tickets.php" class="btn btn-info">مشاهده تیکت‌ها</a></li>
                            <li><a href="maintenance.php" class="btn btn-warning">مشاهده تعمیرات</a></li>
                            <li><a href="dashboard.php" class="btn btn-success">بازگشت به داشبورد</a></li>
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
                                echo "<p class='text-success'>✓ جدول {$table}: {$count} رکورد</p>";
                            } catch (Exception $e) {
                                echo "<p class='text-danger'>✗ جدول {$table}: خطا</p>";
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>