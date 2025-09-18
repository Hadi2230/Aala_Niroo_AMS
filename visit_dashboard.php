<?php
require_once 'config.php';

// بررسی دسترسی
if (!hasPermission('visit_management')) {
    die('دسترسی غیرمجاز - شما مجوز دسترسی به این بخش را ندارید');
}

$page_title = 'داشبورد مدیریت بازدید کارخانه';

// دریافت آمار
try {
    $stats = getVisitStatistics($pdo);
    $today_visits = getVisitRequests($pdo, [
        'date_from' => date('Y-m-d 00:00:00'),
        'date_to' => date('Y-m-d 23:59:59')
    ]);
    $pending_documents = getVisitRequests($pdo, ['status' => 'documents_required']);
    $reserved_devices = getVisitRequests($pdo, ['status' => 'reserved']);
} catch (Exception $e) {
    $stats = ['total_requests' => 0, 'by_status' => [], 'by_type' => [], 'by_purpose' => []];
    $today_visits = [];
    $pending_documents = [];
    $reserved_devices = [];
}

// دریافت درخواست‌های اخیر
try {
    $recent_requests = getVisitRequests($pdo, ['date_from' => date('Y-m-d', strtotime('-7 days'))]);
} catch (Exception $e) {
    $recent_requests = [];
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .stats-card.success {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        .stats-card.warning {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }
        .stats-card.danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
        }
        .stats-card.info {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            color: #333;
        }
        .stats-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .stats-label {
            font-size: 1rem;
            opacity: 0.9;
        }
        .quick-action-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 15px 25px;
            border-radius: 10px;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .quick-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
            color: white;
        }
        .visit-card {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        .status-new { background: #e3f2fd; color: #1976d2; }
        .status-documents_required { background: #fff3e0; color: #f57c00; }
        .status-reviewed { background: #f3e5f5; color: #7b1fa2; }
        .status-scheduled { background: #e8f5e8; color: #388e3c; }
        .status-reserved { background: #fff8e1; color: #f9a825; }
        .status-ready_for_visit { background: #e0f2f1; color: #00695c; }
        .status-checked_in { background: #e1f5fe; color: #0277bd; }
        .status-onsite { background: #fce4ec; color: #c2185b; }
        .status-completed { background: #e8f5e8; color: #2e7d32; }
        .status-cancelled { background: #ffebee; color: #d32f2f; }
        .status-archived { background: #f5f5f5; color: #616161; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0">
                        <i class="fas fa-building me-2"></i>
                        <?php echo $page_title; ?>
                    </h1>
                    <div>
                        <a href="visit_management.php" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i>
                            درخواست جدید
                        </a>
                    </div>
                </div>

                <!-- آمار کلی -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo $stats['total_requests']; ?></div>
                            <div class="stats-label">کل درخواست‌ها</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card success">
                            <div class="stats-number"><?php echo count($today_visits); ?></div>
                            <div class="stats-label">بازدیدهای امروز</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card warning">
                            <div class="stats-number"><?php echo count($pending_documents); ?></div>
                            <div class="stats-label">نیاز به مدارک</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card info">
                            <div class="stats-number"><?php echo count($reserved_devices); ?></div>
                            <div class="stats-label">دستگاه‌های رزرو شده</div>
                        </div>
                    </div>
                </div>

                <!-- عملیات سریع -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-bolt me-2"></i>
                                    عملیات سریع
                                </h5>
                            </div>
                            <div class="card-body">
                                <a href="visit_management.php" class="quick-action-btn">
                                    <i class="fas fa-plus me-2"></i>
                                    ثبت درخواست جدید
                                </a>
                                <a href="visit_management.php?tab=calendar" class="quick-action-btn">
                                    <i class="fas fa-calendar me-2"></i>
                                    تقویم بازدیدها
                                </a>
                                <a href="visit_checkin.php" class="quick-action-btn">
                                    <i class="fas fa-qrcode me-2"></i>
                                    چک‌این بازدیدکنندگان
                                </a>
                                <a href="visit_management.php?status=documents_required" class="quick-action-btn">
                                    <i class="fas fa-file-upload me-2"></i>
                                    بررسی مدارک
                                </a>
                                <a href="visit_management.php?status=scheduled" class="quick-action-btn">
                                    <i class="fas fa-clock me-2"></i>
                                    بازدیدهای برنامه‌ریزی شده
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- درخواست‌های اخیر -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-history me-2"></i>
                                    درخواست‌های اخیر
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_requests)): ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-3x mb-3"></i>
                                        <p>هیچ درخواست اخیری یافت نشد</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach (array_slice($recent_requests, 0, 5) as $request): ?>
                                        <div class="visit-card">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($request['company_name']); ?></h6>
                                                    <p class="text-muted mb-1">
                                                        <i class="fas fa-user me-1"></i>
                                                        <?php echo htmlspecialchars($request['contact_person']); ?>
                                                    </p>
                                                    <p class="text-muted mb-0">
                                                        <i class="fas fa-phone me-1"></i>
                                                        <?php echo htmlspecialchars($request['contact_phone']); ?>
                                                    </p>
                                                </div>
                                                <div class="text-end">
                                                    <span class="status-badge status-<?php echo $request['status']; ?>">
                                                        <?php echo $request['status']; ?>
                                                    </span>
                                                    <div class="mt-2">
                                                        <small class="text-muted">
                                                            <?php echo jalali_format($request['created_at'], 'Y/m/d H:i'); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- آمار بر اساس وضعیت -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-chart-pie me-2"></i>
                                    آمار بر اساس وضعیت
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($stats['by_status'])): ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-chart-pie fa-3x mb-3"></i>
                                        <p>داده‌ای برای نمایش وجود ندارد</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($stats['by_status'] as $status): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="status-badge status-<?php echo $status['status']; ?>">
                                                <?php echo $status['status']; ?>
                                            </span>
                                            <span class="fw-bold"><?php echo $status['count']; ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- آمار بر اساس نوع -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-tags me-2"></i>
                                    آمار بر اساس نوع
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($stats['by_type'])): ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-tags fa-3x mb-3"></i>
                                        <p>داده‌ای برای نمایش وجود ندارد</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($stats['by_type'] as $type): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span><?php echo $type['visit_type']; ?></span>
                                            <span class="fw-bold"><?php echo $type['count']; ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
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