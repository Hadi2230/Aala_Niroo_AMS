<?php
/**
 * index.php - صفحه اصلی سیستم درخواست‌ها
 */

session_start();

// اگر کاربر لاگین نکرده، به صفحه راه‌اندازی هدایت می‌شود
if (!isset($_SESSION['user_id'])) {
    header('Location: setup_complete.php');
    exit();
}

require_once 'config_complete.php';

$page_title = 'سیستم مدیریت درخواست‌ها';
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
            --dark-bg: #1a1a1a;
            --light-bg: #f8f9fa;
            --card-bg: #ffffff;
            --text-primary: #2c3e50;
            --text-secondary: #6c757d;
            --border-color: #e9ecef;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-hover: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        body {
            font-family: 'Vazirmatn', 'Tahoma', sans-serif;
            background: #f8f9fa;
            margin: 0;
            padding: 0;
            color: var(--text-primary);
        }

        .main-container {
            padding-top: 80px;
            min-height: 100vh;
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #34495e 100%);
            color: white;
            padding: 40px 0;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .page-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .card {
            background: var(--card-bg);
            border: none;
            border-radius: 15px;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            overflow: hidden;
            margin-bottom: 25px;
        }

        .card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
        }

        .card-header {
            background: linear-gradient(135deg, var(--secondary-color) 0%, #5dade2 100%);
            color: white;
            border: none;
            padding: 20px 25px;
            font-weight: 600;
            font-size: 1.2rem;
        }

        .card-body {
            padding: 25px;
        }

        .feature-card {
            background: linear-gradient(135deg, var(--primary-color) 0%, #34495e 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s ease;
            height: 100%;
            cursor: pointer;
            text-decoration: none;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            color: white;
            text-decoration: none;
        }

        .feature-card.success {
            background: linear-gradient(135deg, var(--success-color) 0%, #2ecc71 100%);
        }

        .feature-card.warning {
            background: linear-gradient(135deg, var(--warning-color) 0%, #f39c12 100%);
        }

        .feature-card.info {
            background: linear-gradient(135deg, var(--info-color) 0%, #5dade2 100%);
        }

        .feature-icon {
            font-size: 3rem;
            margin-bottom: 20px;
            opacity: 0.8;
        }

        .feature-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .feature-description {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
        }

        .stats-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--secondary-color);
            margin-bottom: 10px;
        }

        .stats-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .stats-description {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        @media (max-width: 768px) {
            .page-title {
                font-size: 2rem;
            }
            
            .card-body {
                padding: 20px;
            }
            
            .feature-card {
                margin-bottom: 20px;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="main-container">
        <div class="container">
            <!-- Page Header -->
            <div class="page-header text-center">
                <h1 class="page-title">
                    <i class="fas fa-cogs me-3"></i>
                    سیستم مدیریت درخواست‌ها
                </h1>
                <p class="page-subtitle">
                    سیستم حرفه‌ای و کامل برای مدیریت درخواست‌های کالا/خدمات
                </p>
            </div>

            <!-- Quick Stats -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card">
                        <div class="stats-number">
                            <?php
                            try {
                                $stmt = $pdo->query("SELECT COUNT(*) FROM requests");
                                echo $stmt->fetchColumn();
                            } catch (Exception $e) {
                                echo "0";
                            }
                            ?>
                        </div>
                        <div class="stats-title">کل درخواست‌ها</div>
                        <div class="stats-description">تعداد کل</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card">
                        <div class="stats-number">
                            <?php
                            try {
                                $stmt = $pdo->query("SELECT COUNT(*) FROM requests WHERE status IN ('در انتظار تأیید', 'در حال بررسی')");
                                echo $stmt->fetchColumn();
                            } catch (Exception $e) {
                                echo "0";
                            }
                            ?>
                        </div>
                        <div class="stats-title">در انتظار</div>
                        <div class="stats-description">نیاز به اقدام</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card">
                        <div class="stats-number">
                            <?php
                            try {
                                $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1");
                                echo $stmt->fetchColumn();
                            } catch (Exception $e) {
                                echo "0";
                            }
                            ?>
                        </div>
                        <div class="stats-title">کاربران فعال</div>
                        <div class="stats-description">تعداد کل</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card">
                        <div class="stats-number">
                            <?php
                            try {
                                $stmt = $pdo->query("SELECT SUM(price) FROM requests WHERE price IS NOT NULL");
                                $total = $stmt->fetchColumn();
                                echo number_format($total ?: 0);
                            } catch (Exception $e) {
                                echo "0";
                            }
                            ?>
                        </div>
                        <div class="stats-title">مجموع قیمت</div>
                        <div class="stats-description">ریال</div>
                    </div>
                </div>
            </div>

            <!-- Main Features -->
            <div class="row mb-4">
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="request_management_final.php" class="feature-card">
                        <i class="fas fa-plus-circle feature-icon"></i>
                        <div class="feature-title">ایجاد درخواست</div>
                        <div class="feature-description">ایجاد درخواست جدید با قابلیت آپلود فایل</div>
                    </a>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="request_workflow_professional.php" class="feature-card success">
                        <i class="fas fa-cogs feature-icon"></i>
                        <div class="feature-title">سیستم حرفه‌ای</div>
                        <div class="feature-description">مدیریت و پیگیری پیشرفته درخواست‌ها</div>
                    </a>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="request_tracking_final.php" class="feature-card warning">
                        <i class="fas fa-search feature-icon"></i>
                        <div class="feature-title">پیگیری درخواست‌ها</div>
                        <div class="feature-description">مشاهده و پیگیری وضعیت درخواست‌ها</div>
                    </a>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="request_reports.php" class="feature-card info">
                        <i class="fas fa-chart-bar feature-icon"></i>
                        <div class="feature-title">گزارش‌ها</div>
                        <div class="feature-description">گزارش‌های آماری و تحلیلی کامل</div>
                    </a>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="test_final_system.php" class="feature-card">
                        <i class="fas fa-vial feature-icon"></i>
                        <div class="feature-title">تست سیستم</div>
                        <div class="feature-description">تست کامل عملکرد سیستم</div>
                    </a>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="setup_complete.php" class="feature-card">
                        <i class="fas fa-tools feature-icon"></i>
                        <div class="feature-title">راه‌اندازی</div>
                        <div class="feature-description">راه‌اندازی و تنظیمات سیستم</div>
                    </a>
                </div>
            </div>

            <!-- Recent Requests -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-clock me-2"></i>
                    آخرین درخواست‌ها
                </div>
                <div class="card-body">
                    <?php
                    try {
                        $stmt = $pdo->query("
                            SELECT r.*, u.full_name as requester_full_name
                            FROM requests r
                            LEFT JOIN users u ON r.requester_id = u.id
                            ORDER BY r.created_at DESC
                            LIMIT 5
                        ");
                        $recent_requests = $stmt->fetchAll();
                        
                        if (empty($recent_requests)) {
                            echo "<div class='text-center text-muted'>
                                <i class='fas fa-inbox fa-3x mb-3'></i>
                                <p>هیچ درخواستی یافت نشد</p>
                                <a href='request_management_final.php' class='btn btn-primary'>ایجاد اولین درخواست</a>
                            </div>";
                        } else {
                            echo "<div class='table-responsive'>
                                <table class='table table-hover'>
                                    <thead>
                                        <tr>
                                            <th>شماره درخواست</th>
                                            <th>درخواست‌دهنده</th>
                                            <th>نام آیتم</th>
                                            <th>وضعیت</th>
                                            <th>تاریخ</th>
                                        </tr>
                                    </thead>
                                    <tbody>";
                            
                            foreach ($recent_requests as $request) {
                                $status_class = '';
                                switch ($request['status']) {
                                    case 'در انتظار تأیید':
                                    case 'در حال بررسی':
                                        $status_class = 'warning';
                                        break;
                                    case 'تأیید شده':
                                    case 'تکمیل شده':
                                        $status_class = 'success';
                                        break;
                                    case 'رد شده':
                                        $status_class = 'danger';
                                        break;
                                }
                                
                                echo "<tr>
                                    <td>{$request['request_number']}</td>
                                    <td>" . htmlspecialchars($request['requester_full_name'] ?: $request['requester_name']) . "</td>
                                    <td>" . htmlspecialchars($request['item_name']) . "</td>
                                    <td><span class='badge bg-$status_class'>{$request['status']}</span></td>
                                    <td>" . date('Y/m/d H:i', strtotime($request['created_at'])) . "</td>
                                </tr>";
                            }
                            
                            echo "</tbody>
                                </table>
                            </div>";
                        }
                    } catch (Exception $e) {
                        echo "<div class='alert alert-danger'>خطا در بارگذاری درخواست‌ها: " . $e->getMessage() . "</div>";
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>