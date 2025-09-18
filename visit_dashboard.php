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
    <!-- Video.js برای پخش ویدیو -->
    <link href="https://vjs.zencdn.net/8.5.2/video-js.css" rel="stylesheet">
    <script src="https://vjs.zencdn.net/8.5.2/video.min.js"></script>
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

        /* استایل‌های دوربین‌های مداربسته */
        .camera-card {
            background: #fff;
            border-radius: 12px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .camera-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }

        .camera-title {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .camera-container {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            background: #000;
        }

        .camera-video {
            border-radius: 8px;
        }

        .camera-status {
            display: flex;
            align-items: center;
            margin-top: 8px;
            font-size: 12px;
        }

        .status-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-left: 5px;
            animation: pulse 2s infinite;
        }

        .status-indicator.online {
            background-color: #28a745;
        }

        .status-indicator.offline {
            background-color: #dc3545;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        .status-text {
            color: #6c757d;
            font-weight: 500;
        }

        .camera-controls {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            border: 1px solid #e9ecef;
        }

        .camera-controls .btn {
            margin-left: 5px;
            margin-bottom: 5px;
        }

        /* Video.js customization */
        .video-js {
            font-family: 'Tahoma', sans-serif;
        }

        .video-js .vjs-big-play-button {
            background-color: rgba(43, 51, 63, 0.7);
            border-radius: 50%;
        }

        .video-js .vjs-control-bar {
            background-color: rgba(43, 51, 63, 0.8);
        }
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

                <!-- دوربین‌های مداربسته کارخانه -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-video me-2"></i>
                                    دوربین‌های مداربسته کارخانه
                                    <span class="badge bg-success ms-2">لایو</span>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <!-- دوربین ورودی -->
                                    <div class="col-md-6 mb-3">
                                        <div class="camera-card">
                                            <h6 class="camera-title">
                                                <i class="fas fa-door-open me-2"></i>
                                                دوربین ورودی کارخانه
                                            </h6>
                                            <div class="camera-container">
                                                <video 
                                                    id="camera1" 
                                                    class="video-js vjs-default-skin camera-video" 
                                                    controls 
                                                    preload="auto" 
                                                    width="100%" 
                                                    height="250"
                                                    data-setup='{"fluid": true, "responsive": true}'>
                                                    <source src="rtsp://192.168.1.100:554/stream1" type="application/x-rtsp">
                                                    <p class="vjs-no-js">
                                                        برای مشاهده این ویدیو، لطفاً 
                                                        <a href="https://videojs.com/html5-video-support/" target="_blank">
                                                            JavaScript را فعال کنید
                                                        </a>
                                                        و از مرورگر مدرن استفاده کنید.
                                                    </p>
                                                </video>
                                            </div>
                                            <div class="camera-status">
                                                <span class="status-indicator online"></span>
                                                <span class="status-text">آنلاین</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- دوربین سالن تولید -->
                                    <div class="col-md-6 mb-3">
                                        <div class="camera-card">
                                            <h6 class="camera-title">
                                                <i class="fas fa-industry me-2"></i>
                                                دوربین سالن تولید
                                            </h6>
                                            <div class="camera-container">
                                                <video 
                                                    id="camera2" 
                                                    class="video-js vjs-default-skin camera-video" 
                                                    controls 
                                                    preload="auto" 
                                                    width="100%" 
                                                    height="250"
                                                    data-setup='{"fluid": true, "responsive": true}'>
                                                    <source src="rtsp://192.168.1.101:554/stream1" type="application/x-rtsp">
                                                    <p class="vjs-no-js">
                                                        برای مشاهده این ویدیو، لطفاً 
                                                        <a href="https://videojs.com/html5-video-support/" target="_blank">
                                                            JavaScript را فعال کنید
                                                        </a>
                                                        و از مرورگر مدرن استفاده کنید.
                                                    </p>
                                                </video>
                                            </div>
                                            <div class="camera-status">
                                                <span class="status-indicator online"></span>
                                                <span class="status-text">آنلاین</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- دوربین انبار -->
                                    <div class="col-md-6 mb-3">
                                        <div class="camera-card">
                                            <h6 class="camera-title">
                                                <i class="fas fa-warehouse me-2"></i>
                                                دوربین انبار
                                            </h6>
                                            <div class="camera-container">
                                                <video 
                                                    id="camera3" 
                                                    class="video-js vjs-default-skin camera-video" 
                                                    controls 
                                                    preload="auto" 
                                                    width="100%" 
                                                    height="250"
                                                    data-setup='{"fluid": true, "responsive": true}'>
                                                    <source src="rtsp://192.168.1.102:554/stream1" type="application/x-rtsp">
                                                    <p class="vjs-no-js">
                                                        برای مشاهده این ویدیو، لطفاً 
                                                        <a href="https://videojs.com/html5-video-support/" target="_blank">
                                                            JavaScript را فعال کنید
                                                        </a>
                                                        و از مرورگر مدرن استفاده کنید.
                                                    </p>
                                                </video>
                                            </div>
                                            <div class="camera-status">
                                                <span class="status-indicator online"></span>
                                                <span class="status-text">آنلاین</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- دوربین پارکینگ -->
                                    <div class="col-md-6 mb-3">
                                        <div class="camera-card">
                                            <h6 class="camera-title">
                                                <i class="fas fa-car me-2"></i>
                                                دوربین پارکینگ
                                            </h6>
                                            <div class="camera-container">
                                                <video 
                                                    id="camera4" 
                                                    class="video-js vjs-default-skin camera-video" 
                                                    controls 
                                                    preload="auto" 
                                                    width="100%" 
                                                    height="250"
                                                    data-setup='{"fluid": true, "responsive": true}'>
                                                    <source src="rtsp://192.168.1.103:554/stream1" type="application/x-rtsp">
                                                    <p class="vjs-no-js">
                                                        برای مشاهده این ویدیو، لطفاً 
                                                        <a href="https://videojs.com/html5-video-support/" target="_blank">
                                                            JavaScript را فعال کنید
                                                        </a>
                                                        و از مرورگر مدرن استفاده کنید.
                                                    </p>
                                                </video>
                                            </div>
                                            <div class="camera-status">
                                                <span class="status-indicator online"></span>
                                                <span class="status-text">آنلاین</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- کنترل‌های دوربین -->
                                <div class="camera-controls mt-3">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <button class="btn btn-outline-primary btn-sm" onclick="refreshAllCameras()">
                                                <i class="fas fa-sync-alt me-1"></i>
                                                بروزرسانی همه دوربین‌ها
                                            </button>
                                            <button class="btn btn-outline-success btn-sm" onclick="startAllCameras()">
                                                <i class="fas fa-play me-1"></i>
                                                شروع همه
                                            </button>
                                            <button class="btn btn-outline-warning btn-sm" onclick="pauseAllCameras()">
                                                <i class="fas fa-pause me-1"></i>
                                                توقف همه
                                            </button>
                                        </div>
                                        <div class="col-md-6 text-end">
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-outline-secondary btn-sm" onclick="toggleFullscreen('camera1')">
                                                    <i class="fas fa-expand me-1"></i>
                                                    تمام صفحه
                                                </button>
                                                <button class="btn btn-outline-info btn-sm" onclick="takeSnapshot()">
                                                    <i class="fas fa-camera me-1"></i>
                                                    عکس‌برداری
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
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
    
    <script>
        // متغیرهای سراسری برای دوربین‌ها
        let cameraPlayers = {};
        let cameraStatus = {
            'camera1': 'online',
            'camera2': 'online', 
            'camera3': 'online',
            'camera4': 'online'
        };

        // مقداردهی اولیه Video.js players
        document.addEventListener('DOMContentLoaded', function() {
            initializeCameras();
            startCameraStatusCheck();
        });

        // مقداردهی دوربین‌ها
        function initializeCameras() {
            const cameraIds = ['camera1', 'camera2', 'camera3', 'camera4'];
            
            cameraIds.forEach(cameraId => {
                try {
                    cameraPlayers[cameraId] = videojs(cameraId, {
                        fluid: true,
                        responsive: true,
                        controls: true,
                        preload: 'auto',
                        autoplay: false,
                        muted: true,
                        playbackRates: [0.5, 1, 1.25, 1.5, 2],
                        plugins: {
                            // اضافه کردن پلاگین‌های مورد نیاز
                        }
                    });

                    // رویدادهای دوربین
                    cameraPlayers[cameraId].on('loadstart', function() {
                        updateCameraStatus(cameraId, 'loading');
                    });

                    cameraPlayers[cameraId].on('canplay', function() {
                        updateCameraStatus(cameraId, 'online');
                    });

                    cameraPlayers[cameraId].on('error', function() {
                        updateCameraStatus(cameraId, 'offline');
                        console.error('خطا در بارگذاری دوربین ' + cameraId);
                    });

                } catch (error) {
                    console.error('خطا در مقداردهی دوربین ' + cameraId + ':', error);
                    updateCameraStatus(cameraId, 'offline');
                }
            });
        }

        // به‌روزرسانی وضعیت دوربین
        function updateCameraStatus(cameraId, status) {
            cameraStatus[cameraId] = status;
            const statusElement = document.querySelector(`#${cameraId}`).closest('.camera-card').querySelector('.status-indicator');
            const statusText = document.querySelector(`#${cameraId}`).closest('.camera-card').querySelector('.status-text');
            
            statusElement.className = 'status-indicator ' + status;
            
            switch(status) {
                case 'online':
                    statusText.textContent = 'آنلاین';
                    break;
                case 'loading':
                    statusText.textContent = 'در حال بارگذاری...';
                    break;
                case 'offline':
                    statusText.textContent = 'آفلاین';
                    break;
            }
        }

        // بررسی وضعیت دوربین‌ها
        function startCameraStatusCheck() {
            setInterval(() => {
                Object.keys(cameraPlayers).forEach(cameraId => {
                    if (cameraPlayers[cameraId]) {
                        const player = cameraPlayers[cameraId];
                        if (player.readyState() >= 2) { // HAVE_CURRENT_DATA
                            updateCameraStatus(cameraId, 'online');
                        } else {
                            updateCameraStatus(cameraId, 'offline');
                        }
                    }
                });
            }, 10000); // هر 10 ثانیه
        }

        // شروع همه دوربین‌ها
        function startAllCameras() {
            Object.keys(cameraPlayers).forEach(cameraId => {
                if (cameraPlayers[cameraId]) {
                    cameraPlayers[cameraId].play().catch(error => {
                        console.error('خطا در شروع دوربین ' + cameraId + ':', error);
                    });
                }
            });
        }

        // توقف همه دوربین‌ها
        function pauseAllCameras() {
            Object.keys(cameraPlayers).forEach(cameraId => {
                if (cameraPlayers[cameraId]) {
                    cameraPlayers[cameraId].pause();
                }
            });
        }

        // بروزرسانی همه دوربین‌ها
        function refreshAllCameras() {
            Object.keys(cameraPlayers).forEach(cameraId => {
                if (cameraPlayers[cameraId]) {
                    cameraPlayers[cameraId].src({
                        src: cameraPlayers[cameraId].currentSrc(),
                        type: 'application/x-rtsp'
                    });
                    cameraPlayers[cameraId].load();
                }
            });
        }

        // تمام صفحه کردن دوربین
        function toggleFullscreen(cameraId) {
            if (cameraPlayers[cameraId]) {
                if (cameraPlayers[cameraId].isFullscreen()) {
                    cameraPlayers[cameraId].exitFullscreen();
                } else {
                    cameraPlayers[cameraId].requestFullscreen();
                }
            }
        }

        // عکس‌برداری از دوربین
        function takeSnapshot() {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            const video = document.querySelector('#camera1 video');
            
            if (video) {
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                ctx.drawImage(video, 0, 0);
                
                // ایجاد لینک دانلود
                const link = document.createElement('a');
                link.download = 'camera-snapshot-' + new Date().getTime() + '.png';
                link.href = canvas.toDataURL();
                link.click();
                
                // نمایش پیام موفقیت
                showNotification('عکس با موفقیت ذخیره شد', 'success');
            } else {
                showNotification('خطا در عکس‌برداری', 'error');
            }
        }

        // نمایش اعلان
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(notification);
            
            // حذف خودکار بعد از 3 ثانیه
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 3000);
        }

        // تنظیمات پیشرفته دوربین‌ها
        function configureCameraSettings(cameraId, settings) {
            if (cameraPlayers[cameraId]) {
                const player = cameraPlayers[cameraId];
                
                // تنظیم کیفیت
                if (settings.quality) {
                    player.qualityLevels().forEach(level => {
                        if (level.height === settings.quality) {
                            level.enabled = true;
                        } else {
                            level.enabled = false;
                        }
                    });
                }
                
                // تنظیم سرعت پخش
                if (settings.playbackRate) {
                    player.playbackRate(settings.playbackRate);
                }
                
                // تنظیم حجم صدا
                if (settings.volume !== undefined) {
                    player.volume(settings.volume);
                }
            }
        }

        // دریافت آمار دوربین‌ها
        function getCameraStats() {
            const stats = {};
            Object.keys(cameraPlayers).forEach(cameraId => {
                if (cameraPlayers[cameraId]) {
                    const player = cameraPlayers[cameraId];
                    stats[cameraId] = {
                        status: cameraStatus[cameraId],
                        duration: player.duration(),
                        currentTime: player.currentTime(),
                        volume: player.volume(),
                        playbackRate: player.playbackRate(),
                        readyState: player.readyState(),
                        networkState: player.networkState()
                    };
                }
            });
            return stats;
        }

        // لاگ کردن آمار دوربین‌ها (برای دیباگ)
        function logCameraStats() {
            console.log('آمار دوربین‌ها:', getCameraStats());
        }

        // تمیز کردن منابع هنگام بسته شدن صفحه
        window.addEventListener('beforeunload', function() {
            Object.keys(cameraPlayers).forEach(cameraId => {
                if (cameraPlayers[cameraId]) {
                    cameraPlayers[cameraId].dispose();
                }
            });
        });
    </script>
</body>
</html>