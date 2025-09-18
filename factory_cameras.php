<?php
/**
 * factory_cameras.php - صفحه مشاهده دوربین‌های مداربسته کارخانه
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'config.php';

// بررسی دسترسی
if (!hasPermission('visit_management')) {
    die('دسترسی غیرمجاز - شما مجوز دسترسی به این بخش را ندارید');
}

$page_title = 'دوربین‌های مداربسته کارخانه';
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
    <!-- CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --dark-color: #1f2937;
            --light-color: #f8fafc;
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-dark: linear-gradient(135deg, #1f2937 0%, #374151 100%);
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--gradient-dark);
            min-height: 100vh;
            color: white;
        }

        .main-container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            margin: 20px;
            box-shadow: var(--shadow-xl);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .header {
            background: var(--gradient-primary);
            color: white;
            padding: 30px;
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
        }

        .header-content {
            position: relative;
            z-index: 2;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .page-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 0;
        }

        .back-btn {
            position: absolute;
            top: 30px;
            right: 30px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            font-size: 1.2rem;
            transition: all 0.3s ease;
            z-index: 3;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }

        .content-area {
            padding: 30px;
        }

        .camera-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .camera-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 20px;
            box-shadow: var(--shadow-lg);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .camera-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
            border-color: var(--primary-color);
        }

        .camera-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
        }

        .camera-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .camera-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .camera-status {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }

        .status-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        .status-online {
            background: var(--success-color);
        }

        .status-offline {
            background: var(--danger-color);
        }

        .camera-container {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            background: #000;
            margin-bottom: 15px;
        }

        .camera-video {
            width: 100%;
            height: 250px;
            object-fit: cover;
            border-radius: 12px;
        }

        .camera-placeholder {
            width: 100%;
            height: 250px;
            background: linear-gradient(45deg, #1f2937, #374151);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
            border-radius: 12px;
            position: relative;
            overflow: hidden;
        }

        .camera-placeholder::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="camera-pattern" width="20" height="20" patternUnits="userSpaceOnUse"><circle cx="10" cy="10" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23camera-pattern)"/></svg>');
            opacity: 0.3;
        }

        .camera-placeholder i {
            font-size: 3rem;
            margin-bottom: 10px;
            position: relative;
            z-index: 2;
        }

        .camera-placeholder span {
            position: relative;
            z-index: 2;
        }

        .camera-controls {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .control-btn {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            padding: 8px 16px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .control-btn:hover {
            background: var(--primary-color);
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }

        .control-btn.active {
            background: var(--success-color);
            border-color: var(--success-color);
        }

        .fullscreen-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.95);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .fullscreen-video {
            max-width: 100%;
            max-height: 100%;
            border-radius: 12px;
        }

        .fullscreen-close {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            font-size: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .fullscreen-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }

        .global-controls {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 30px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .controls-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: white;
        }

        .global-controls-grid {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .global-btn {
            background: var(--gradient-primary);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 12px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .global-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .global-btn.success {
            background: var(--gradient-primary);
        }

        .global-btn.warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }

        .global-btn.danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.5;
            }
        }

        @media (max-width: 768px) {
            .main-container {
                margin: 10px;
                border-radius: 15px;
            }
            
            .content-area {
                padding: 20px;
            }
            
            .camera-grid {
                grid-template-columns: 1fr;
            }
            
            .camera-video,
            .camera-placeholder {
                height: 200px;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php if (file_exists('navbar.php')): ?>
        <?php include 'navbar.php'; ?>
    <?php endif; ?>

    <div class="main-container">
        <!-- Header -->
        <div class="header">
            <button class="back-btn" onclick="window.history.back()" title="بازگشت">
                <i class="fas fa-arrow-right"></i>
            </button>
            <div class="header-content">
                <h1 class="page-title">
                    <i class="fas fa-video me-3"></i>
                    دوربین‌های مداربسته کارخانه
                </h1>
                <p class="page-subtitle">
                    نظارت زنده بر تمام بخش‌های کارخانه
                </p>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content-area">
            <!-- Global Controls -->
            <div class="global-controls" data-aos="fade-up">
                <h3 class="controls-title">
                    <i class="fas fa-sliders-h me-2"></i>
                    کنترل‌های کلی
                </h3>
                <div class="global-controls-grid">
                    <button class="global-btn success" onclick="startAllCameras()">
                        <i class="fas fa-play"></i>
                        شروع همه
                    </button>
                    <button class="global-btn warning" onclick="pauseAllCameras()">
                        <i class="fas fa-pause"></i>
                        توقف همه
                    </button>
                    <button class="global-btn" onclick="refreshAllCameras()">
                        <i class="fas fa-sync-alt"></i>
                        بروزرسانی
                    </button>
                    <button class="global-btn" onclick="takeAllSnapshots()">
                        <i class="fas fa-camera"></i>
                        عکس‌برداری همه
                    </button>
                </div>
            </div>

            <!-- Camera Grid -->
            <div class="camera-grid" data-aos="fade-up" data-aos-delay="200">
                <!-- Camera 1: Entrance -->
                <div class="camera-card">
                    <div class="camera-header">
                        <h3 class="camera-title">
                            <i class="fas fa-door-open"></i>
                            دوربین ورودی کارخانه
                        </h3>
                        <div class="camera-status">
                            <span class="status-indicator status-online"></span>
                            <span>آنلاین</span>
                        </div>
                    </div>
                    <div class="camera-container">
                        <iframe 
                            src="https://www.youtube.com/embed/dQw4w9WgXcQ?autoplay=1&mute=1&controls=0&showinfo=0&rel=0&modestbranding=1&loop=1&playlist=dQw4w9WgXcQ"
                            class="camera-video"
                            frameborder="0"
                            allow="autoplay; encrypted-media"
                            allowfullscreen>
                        </iframe>
                    </div>
                    <div class="camera-controls">
                        <button class="control-btn" onclick="toggleCamera('camera1')">
                            <i class="fas fa-play"></i>
                            پخش
                        </button>
                        <button class="control-btn" onclick="toggleFullscreen('camera1')">
                            <i class="fas fa-expand"></i>
                            تمام صفحه
                        </button>
                        <button class="control-btn" onclick="takeSnapshot('camera1')">
                            <i class="fas fa-camera"></i>
                            عکس
                        </button>
                    </div>
                </div>

                <!-- Camera 2: Production Hall -->
                <div class="camera-card">
                    <div class="camera-header">
                        <h3 class="camera-title">
                            <i class="fas fa-industry"></i>
                            دوربین سالن تولید
                        </h3>
                        <div class="camera-status">
                            <span class="status-indicator status-online"></span>
                            <span>آنلاین</span>
                        </div>
                    </div>
                    <div class="camera-container">
                        <iframe 
                            src="https://www.youtube.com/embed/jNQXAC9IVRw?autoplay=1&mute=1&controls=0&showinfo=0&rel=0&modestbranding=1&loop=1&playlist=jNQXAC9IVRw"
                            class="camera-video"
                            frameborder="0"
                            allow="autoplay; encrypted-media"
                            allowfullscreen>
                        </iframe>
                    </div>
                    <div class="camera-controls">
                        <button class="control-btn" onclick="toggleCamera('camera2')">
                            <i class="fas fa-play"></i>
                            پخش
                        </button>
                        <button class="control-btn" onclick="toggleFullscreen('camera2')">
                            <i class="fas fa-expand"></i>
                            تمام صفحه
                        </button>
                        <button class="control-btn" onclick="takeSnapshot('camera2')">
                            <i class="fas fa-camera"></i>
                            عکس
                        </button>
                    </div>
                </div>

                <!-- Camera 3: Warehouse -->
                <div class="camera-card">
                    <div class="camera-header">
                        <h3 class="camera-title">
                            <i class="fas fa-warehouse"></i>
                            دوربین انبار
                        </h3>
                        <div class="camera-status">
                            <span class="status-indicator status-online"></span>
                            <span>آنلاین</span>
                        </div>
                    </div>
                    <div class="camera-container">
                        <iframe 
                            src="https://www.youtube.com/embed/M7lc1UVf-VE?autoplay=1&mute=1&controls=0&showinfo=0&rel=0&modestbranding=1&loop=1&playlist=M7lc1UVf-VE"
                            class="camera-video"
                            frameborder="0"
                            allow="autoplay; encrypted-media"
                            allowfullscreen>
                        </iframe>
                    </div>
                    <div class="camera-controls">
                        <button class="control-btn" onclick="toggleCamera('camera3')">
                            <i class="fas fa-play"></i>
                            پخش
                        </button>
                        <button class="control-btn" onclick="toggleFullscreen('camera3')">
                            <i class="fas fa-expand"></i>
                            تمام صفحه
                        </button>
                        <button class="control-btn" onclick="takeSnapshot('camera3')">
                            <i class="fas fa-camera"></i>
                            عکس
                        </button>
                    </div>
                </div>

                <!-- Camera 4: Parking -->
                <div class="camera-card">
                    <div class="camera-header">
                        <h3 class="camera-title">
                            <i class="fas fa-car"></i>
                            دوربین پارکینگ
                        </h3>
                        <div class="camera-status">
                            <span class="status-indicator status-online"></span>
                            <span>آنلاین</span>
                        </div>
                    </div>
                    <div class="camera-container">
                        <iframe 
                            src="https://www.youtube.com/embed/9bZkp7q19f0?autoplay=1&mute=1&controls=0&showinfo=0&rel=0&modestbranding=1&loop=1&playlist=9bZkp7q19f0"
                            class="camera-video"
                            frameborder="0"
                            allow="autoplay; encrypted-media"
                            allowfullscreen>
                        </iframe>
                    </div>
                    <div class="camera-controls">
                        <button class="control-btn" onclick="toggleCamera('camera4')">
                            <i class="fas fa-play"></i>
                            پخش
                        </button>
                        <button class="control-btn" onclick="toggleFullscreen('camera4')">
                            <i class="fas fa-expand"></i>
                            تمام صفحه
                        </button>
                        <button class="control-btn" onclick="takeSnapshot('camera4')">
                            <i class="fas fa-camera"></i>
                            عکس
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Fullscreen Overlay -->
    <div class="fullscreen-overlay" id="fullscreenOverlay">
        <button class="fullscreen-close" onclick="closeFullscreen()">
            <i class="fas fa-times"></i>
        </button>
        <iframe id="fullscreenVideo" class="fullscreen-video" frameborder="0" allowfullscreen></iframe>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
    
    <script>
        // Initialize AOS
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true
        });

        // Camera states
        const cameraStates = {
            'camera1': true,
            'camera2': true,
            'camera3': true,
            'camera4': true
        };

        // Toggle camera play/pause
        function toggleCamera(cameraId) {
            const iframe = document.querySelector(`#${cameraId} iframe`);
            const btn = document.querySelector(`#${cameraId} .control-btn`);
            
            if (cameraStates[cameraId]) {
                iframe.style.display = 'none';
                btn.innerHTML = '<i class="fas fa-play"></i> پخش';
                cameraStates[cameraId] = false;
            } else {
                iframe.style.display = 'block';
                btn.innerHTML = '<i class="fas fa-pause"></i> توقف';
                cameraStates[cameraId] = true;
            }
        }

        // Toggle fullscreen
        function toggleFullscreen(cameraId) {
            const iframe = document.querySelector(`#${cameraId} iframe`);
            const fullscreenOverlay = document.getElementById('fullscreenOverlay');
            const fullscreenVideo = document.getElementById('fullscreenVideo');
            
            fullscreenVideo.src = iframe.src;
            fullscreenOverlay.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        // Close fullscreen
        function closeFullscreen() {
            const fullscreenOverlay = document.getElementById('fullscreenOverlay');
            const fullscreenVideo = document.getElementById('fullscreenVideo');
            
            fullscreenOverlay.style.display = 'none';
            fullscreenVideo.src = '';
            document.body.style.overflow = 'auto';
        }

        // Take snapshot
        function takeSnapshot(cameraId) {
            // Simulate snapshot
            showNotification(`عکس از ${cameraId} با موفقیت ذخیره شد`, 'success');
        }

        // Start all cameras
        function startAllCameras() {
            Object.keys(cameraStates).forEach(cameraId => {
                if (!cameraStates[cameraId]) {
                    toggleCamera(cameraId);
                }
            });
            showNotification('همه دوربین‌ها شروع شدند', 'success');
        }

        // Pause all cameras
        function pauseAllCameras() {
            Object.keys(cameraStates).forEach(cameraId => {
                if (cameraStates[cameraId]) {
                    toggleCamera(cameraId);
                }
            });
            showNotification('همه دوربین‌ها متوقف شدند', 'warning');
        }

        // Refresh all cameras
        function refreshAllCameras() {
            Object.keys(cameraStates).forEach(cameraId => {
                const iframe = document.querySelector(`#${cameraId} iframe`);
                const currentSrc = iframe.src;
                iframe.src = '';
                setTimeout(() => {
                    iframe.src = currentSrc;
                }, 100);
            });
            showNotification('همه دوربین‌ها بروزرسانی شدند', 'info');
        }

        // Take all snapshots
        function takeAllSnapshots() {
            Object.keys(cameraStates).forEach(cameraId => {
                takeSnapshot(cameraId);
            });
            showNotification('عکس‌برداری از همه دوربین‌ها انجام شد', 'success');
        }

        // Show notification
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            notification.style.cssText = 'top: 20px; right: 20px; z-index: 10000; min-width: 300px;';
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 3000);
        }

        // Close fullscreen on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeFullscreen();
            }
        });

        // Initialize cameras on load
        document.addEventListener('DOMContentLoaded', function() {
            // Add loading animation to camera cards
            const cameraCards = document.querySelectorAll('.camera-card');
            cameraCards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    card.style.transition = 'all 0.6s ease';
                    
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, 100);
                }, index * 150);
            });
        });
    </script>
</body>
</html>