<?php
session_start();
require_once 'config.php';

// بررسی احراز هویت
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// دریافت آمار
try {
    $stats = getVisitStatistics($pdo);
} catch (Exception $e) {
    $stats = ['total_requests' => 0, 'by_status' => [], 'by_type' => []];
}

// دریافت بازدیدهای امروز
$today_visits = [];
try {
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT vr.*, u.full_name as host_name
        FROM visit_requests vr
        LEFT JOIN users u ON vr.host_id = u.id
        WHERE DATE(vr.confirmed_date) = ? AND vr.status IN ('scheduled', 'reserved', 'ready_for_visit', 'checked_in', 'onsite')
        ORDER BY vr.confirmed_date
    ");
    $stmt->execute([$today]);
    $today_visits = $stmt->fetchAll();
} catch (Exception $e) {
    // جدول وجود ندارد
}

// دریافت بازدیدهای نیازمند مدارک
$pending_documents = [];
try {
    $stmt = $pdo->prepare("
        SELECT vr.*, u.full_name as created_by_name
        FROM visit_requests vr
        LEFT JOIN users u ON vr.created_by = u.id
        WHERE vr.status = 'documents_required'
        ORDER BY vr.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $pending_documents = $stmt->fetchAll();
} catch (Exception $e) {
    // جدول وجود ندارد
}

// دریافت دستگاه‌های رزرو شده امروز
$reserved_devices = [];
try {
    $stmt = $pdo->prepare("
        SELECT dr.*, a.name as asset_name, at.display_name as type_name, vr.company_name
        FROM device_reservations dr
        LEFT JOIN assets a ON dr.asset_id = a.id
        LEFT JOIN asset_types at ON a.type_id = at.id
        LEFT JOIN visit_requests vr ON dr.visit_request_id = vr.id
        WHERE DATE(dr.reserved_from) = ? AND dr.status IN ('reserved', 'in_use')
        ORDER BY dr.reserved_from
    ");
    $stmt->execute([$today]);
    $reserved_devices = $stmt->fetchAll();
} catch (Exception $e) {
    // جدول وجود ندارد
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد بازدیدها - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --info-color: #17a2b8;
            --light-bg: #f8f9fa;
        }
        
        body {
            font-family: Vazirmatn, sans-serif;
            background: var(--light-bg);
            padding-top: 20px;
        }
        
        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .dashboard-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 25px rgba(0,0,0,.1);
        }
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,.08);
            transition: all .3s ease;
            border-left: 4px solid var(--secondary-color);
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,.15);
        }
        
        .stats-card.success { border-left-color: var(--success-color); }
        .stats-card.warning { border-left-color: var(--warning-color); }
        .stats-card.danger { border-left-color: var(--danger-color); }
        .stats-card.info { border-left-color: var(--info-color); }
        
        .stats-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        .stats-label {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        
        .stats-change {
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .stats-change.positive { color: var(--success-color); }
        .stats-change.negative { color: var(--danger-color); }
        
        .widget-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,.08);
        }
        
        .widget-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .widget-title {
            font-size: 1.1rem;
            font-weight: bold;
            color: var(--primary-color);
            margin: 0;
        }
        
        .visit-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid var(--secondary-color);
            transition: all .3s ease;
        }
        
        .visit-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }
        
        .visit-item.urgent { border-left-color: var(--danger-color); }
        .visit-item.high { border-left-color: var(--warning-color); }
        .visit-item.medium { border-left-color: var(--info-color); }
        .visit-item.low { border-left-color: var(--success-color); }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .status-scheduled { background: #e3f2fd; color: #1976d2; }
        .status-ready_for_visit { background: #f1f8e9; color: #689f38; }
        .status-checked_in { background: #e0f2f1; color: #00796b; }
        .status-onsite { background: #fff8e1; color: #f9a825; }
        .status-documents_required { background: #fff3e0; color: #f57c00; }
        
        .priority-badge {
            padding: 2px 8px;
            border-radius: 15px;
            font-size: 0.7rem;
            font-weight: bold;
        }
        
        .priority-فوری { background: #ffebee; color: #c62828; }
        .priority-بالا { background: #fff3e0; color: #f57c00; }
        .priority-متوسط { background: #e3f2fd; color: #1976d2; }
        .priority-کم { background: #e8f5e8; color: #2e7d32; }
        
        .device-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .device-reserved { border-left: 4px solid var(--warning-color); }
        .device-in-use { border-left: 4px solid var(--danger-color); }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin-top: 20px;
        }
        
        .quick-action {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 3px 10px rgba(0,0,0,.1);
            transition: all .3s ease;
            text-decoration: none;
            color: inherit;
        }
        
        .quick-action:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,.15);
            color: inherit;
        }
        
        .quick-action i {
            font-size: 2rem;
            color: var(--secondary-color);
            margin-bottom: 10px;
        }
        
        .quick-action h6 {
            color: var(--primary-color);
            margin: 0;
        }
        
        @media (max-width: 768px) {
            .dashboard-header { padding: 20px; }
            .stats-card { padding: 20px; }
            .stats-number { font-size: 2rem; }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="dashboard-container">
            <!-- هدر داشبورد -->
            <div class="dashboard-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1><i class="bi bi-speedometer2"></i> داشبورد بازدیدها</h1>
                        <p class="mb-0">مدیریت جامع بازدیدهای کارخانه</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="text-light">
                            <small>آخرین به‌روزرسانی: <?php echo date('Y-m-d H:i:s'); ?></small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- آمار کلی -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo $stats['total_requests']; ?></div>
                        <div class="stats-label">کل درخواست‌ها</div>
                        <div class="stats-change positive">
                            <i class="bi bi-arrow-up"></i> +12% نسبت به ماه گذشته
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card success">
                        <div class="stats-number">
                            <?php 
                            $completed = array_filter($stats['by_status'], function($s) { return $s['status'] === 'completed'; });
                            echo $completed ? $completed[0]['count'] : 0;
                            ?>
                        </div>
                        <div class="stats-label">تکمیل شده</div>
                        <div class="stats-change positive">
                            <i class="bi bi-arrow-up"></i> +8% نسبت به هفته گذشته
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card warning">
                        <div class="stats-number">
                            <?php 
                            $pending = array_filter($stats['by_status'], function($s) { return $s['status'] === 'documents_required'; });
                            echo $pending ? $pending[0]['count'] : 0;
                            ?>
                        </div>
                        <div class="stats-label">نیاز به مدارک</div>
                        <div class="stats-change negative">
                            <i class="bi bi-arrow-down"></i> -3% نسبت به دیروز
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card info">
                        <div class="stats-number"><?php echo count($today_visits); ?></div>
                        <div class="stats-label">بازدیدهای امروز</div>
                        <div class="stats-change positive">
                            <i class="bi bi-calendar-check"></i> برنامه‌ریزی شده
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- بازدیدهای امروز -->
                <div class="col-md-6">
                    <div class="widget-card">
                        <div class="widget-header">
                            <h5 class="widget-title">
                                <i class="bi bi-calendar-day"></i> بازدیدهای امروز
                            </h5>
                            <span class="badge bg-primary"><?php echo count($today_visits); ?></span>
                        </div>
                        
                        <?php if (empty($today_visits)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-calendar-x display-4 text-muted"></i>
                                <p class="text-muted mt-3">هیچ بازدیدی برای امروز برنامه‌ریزی نشده است</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($today_visits as $visit): ?>
                                <div class="visit-item priority-<?php echo $visit['priority']; ?>">
                                    <div class="row align-items-center">
                                        <div class="col-md-8">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($visit['request_number']); ?></h6>
                                            <p class="mb-1 text-muted"><?php echo htmlspecialchars($visit['company_name']); ?></p>
                                            <p class="mb-0 text-muted small">
                                                <?php echo htmlspecialchars($visit['contact_person']); ?> | 
                                                <?php echo $visit['visitor_count']; ?> نفر
                                            </p>
                                        </div>
                                        <div class="col-md-4 text-end">
                                            <span class="status-badge status-<?php echo $visit['status']; ?>">
                                                <?php 
                                                $status_labels = [
                                                    'scheduled' => 'برنامه‌ریزی شده',
                                                    'ready_for_visit' => 'آماده بازدید',
                                                    'checked_in' => 'وارد شده',
                                                    'onsite' => 'در حال بازدید'
                                                ];
                                                echo $status_labels[$visit['status']] ?? $visit['status'];
                                                ?>
                                            </span>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo date('H:i', strtotime($visit['confirmed_date'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- نیازمند مدارک -->
                <div class="col-md-6">
                    <div class="widget-card">
                        <div class="widget-header">
                            <h5 class="widget-title">
                                <i class="bi bi-file-earmark-excel"></i> نیازمند مدارک
                            </h5>
                            <span class="badge bg-warning"><?php echo count($pending_documents); ?></span>
                        </div>
                        
                        <?php if (empty($pending_documents)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-check-circle display-4 text-success"></i>
                                <p class="text-muted mt-3">همه درخواست‌ها مدارک کامل دارند</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($pending_documents as $visit): ?>
                                <div class="visit-item">
                                    <div class="row align-items-center">
                                        <div class="col-md-8">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($visit['request_number']); ?></h6>
                                            <p class="mb-1 text-muted"><?php echo htmlspecialchars($visit['company_name']); ?></p>
                                            <p class="mb-0 text-muted small">
                                                ایجاد شده: <?php echo date('Y-m-d', strtotime($visit['created_at'])); ?>
                                            </p>
                                        </div>
                                        <div class="col-md-4 text-end">
                                            <span class="priority-badge priority-<?php echo $visit['priority']; ?>">
                                                <?php echo $visit['priority']; ?>
                                            </span>
                                            <br>
                                            <a href="visit_details.php?id=<?php echo $visit['id']; ?>" class="btn btn-sm btn-outline-primary mt-1">
                                                <i class="bi bi-eye"></i> مشاهده
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- دستگاه‌های رزرو شده -->
                <div class="col-md-6">
                    <div class="widget-card">
                        <div class="widget-header">
                            <h5 class="widget-title">
                                <i class="bi bi-gear"></i> دستگاه‌های رزرو شده امروز
                            </h5>
                            <span class="badge bg-info"><?php echo count($reserved_devices); ?></span>
                        </div>
                        
                        <?php if (empty($reserved_devices)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-gear display-4 text-muted"></i>
                                <p class="text-muted mt-3">هیچ دستگاهی برای امروز رزرو نشده است</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($reserved_devices as $device): ?>
                                <div class="device-item device-<?php echo $device['status']; ?>">
                                    <div>
                                        <strong><?php echo htmlspecialchars($device['asset_name']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($device['type_name']); ?></small>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted">
                                            <?php echo date('H:i', strtotime($device['reserved_from'])); ?> - 
                                            <?php echo date('H:i', strtotime($device['reserved_to'])); ?>
                                        </small>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($device['company_name']); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- عملیات سریع -->
                <div class="col-md-6">
                    <div class="widget-card">
                        <div class="widget-header">
                            <h5 class="widget-title">
                                <i class="bi bi-lightning"></i> عملیات سریع
                            </h5>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <a href="visit_management.php" class="quick-action">
                                    <i class="bi bi-plus-circle"></i>
                                    <h6>درخواست جدید</h6>
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="visit_checkin.php" class="quick-action">
                                    <i class="bi bi-qr-code-scan"></i>
                                    <h6>Check-in</h6>
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="visit_management.php?status=scheduled" class="quick-action">
                                    <i class="bi bi-calendar3"></i>
                                    <h6>تقویم بازدیدها</h6>
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="visit_management.php?status=documents_required" class="quick-action">
                                    <i class="bi bi-file-earmark-check"></i>
                                    <h6>بررسی مدارک</h6>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- نمودار آمار -->
            <div class="row">
                <div class="col-md-12">
                    <div class="widget-card">
                        <div class="widget-header">
                            <h5 class="widget-title">
                                <i class="bi bi-bar-chart"></i> آمار بازدیدها
                            </h5>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h6>وضعیت بازدیدها</h6>
                                <div class="chart-container">
                                    <canvas id="statusChart"></canvas>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6>نوع بازدیدها</h6>
                                <div class="chart-container">
                                    <canvas id="typeChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // نمودار وضعیت بازدیدها
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusData = <?php echo json_encode($stats['by_status']); ?>;
        
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: statusData.map(item => item.status),
                datasets: [{
                    data: statusData.map(item => item.count),
                    backgroundColor: [
                        '#3498db', '#e74c3c', '#f39c12', '#27ae60', '#9b59b6',
                        '#1abc9c', '#34495e', '#e67e22', '#2ecc71', '#95a5a6'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        // نمودار نوع بازدیدها
        const typeCtx = document.getElementById('typeChart').getContext('2d');
        const typeData = <?php echo json_encode($stats['by_type']); ?>;
        
        new Chart(typeCtx, {
            type: 'bar',
            data: {
                labels: typeData.map(item => item.visit_type),
                datasets: [{
                    label: 'تعداد',
                    data: typeData.map(item => item.count),
                    backgroundColor: '#3498db',
                    borderColor: '#2980b9',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        
        // Auto-refresh هر 5 دقیقه
        setInterval(function() {
            window.location.reload();
        }, 300000);
    </script>
</body>
</html>