<?php
/**
 * test_camera_colors.php - تست رنگ‌های دوربین‌ها
 */

session_start();
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['role'] = 'ادمین';

echo "<!DOCTYPE html>
<html lang='fa' dir='rtl'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>تست رنگ‌های دوربین‌ها</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1f2937 0%, #374151 100%);
            min-height: 100vh;
            color: white;
            padding: 20px;
        }
        
        .test-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            margin: 20px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
        
        .camera-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .camera-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #ffffff !important;
            display: flex;
            align-items: center;
            gap: 12px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            margin-bottom: 15px;
        }
        
        .camera-title i {
            color: #10b981 !important;
            font-size: 1.4rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        .camera-status {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            color: #ffffff !important;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
            margin-bottom: 15px;
        }
        
        .status-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10b981;
            animation: pulse 2s infinite;
        }
        
        .control-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white !important;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 10px;
            padding: 10px 18px;
            font-size: 0.95rem;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            margin: 5px;
        }
        
        .control-btn:hover {
            background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-3px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.2);
            color: white !important;
        }
        
        .control-btn i {
            color: white !important;
            font-size: 1rem;
        }
        
        .global-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white !important;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            padding: 14px 24px;
            font-weight: 700;
            font-size: 1rem;
            transition: all 0.3s ease;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            margin: 8px;
        }
        
        .global-btn:hover {
            background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-3px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.2);
            color: white !important;
        }
        
        .global-btn i {
            color: white !important;
            font-size: 1.1rem;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
</head>
<body>
    <div class='test-container'>
        <h1><i class='fas fa-palette me-3'></i>تست رنگ‌های دوربین‌ها</h1>
        
        <div class='camera-card'>
            <h3 class='camera-title'>
                <i class='fas fa-water'></i>
                دوربین ساحل آتلانتیک هایلندز
            </h3>
            <div class='camera-status'>
                <span class='status-indicator'></span>
                <span>آنلاین</span>
            </div>
            <div>
                <button class='control-btn'>
                    <i class='fas fa-play'></i>
                    پخش
                </button>
                <button class='control-btn'>
                    <i class='fas fa-expand'></i>
                    تمام صفحه
                </button>
                <button class='control-btn'>
                    <i class='fas fa-camera'></i>
                    عکس
                </button>
            </div>
        </div>
        
        <div class='camera-card'>
            <h3 class='camera-title'>
                <i class='fas fa-city'></i>
                دوربین میدان تایمز
            </h3>
            <div class='camera-status'>
                <span class='status-indicator'></span>
                <span>آنلاین</span>
            </div>
            <div>
                <button class='control-btn'>
                    <i class='fas fa-play'></i>
                    پخش
                </button>
                <button class='control-btn'>
                    <i class='fas fa-expand'></i>
                    تمام صفحه
                </button>
                <button class='control-btn'>
                    <i class='fas fa-camera'></i>
                    عکس
                </button>
            </div>
        </div>
        
        <div class='camera-card'>
            <h3 class='camera-title'>
                <i class='fas fa-tree'></i>
                دوربین پارک مرکزی
            </h3>
            <div class='camera-status'>
                <span class='status-indicator'></span>
                <span>آنلاین</span>
            </div>
            <div>
                <button class='control-btn'>
                    <i class='fas fa-play'></i>
                    پخش
                </button>
                <button class='control-btn'>
                    <i class='fas fa-expand'></i>
                    تمام صفحه
                </button>
                <button class='control-btn'>
                    <i class='fas fa-camera'></i>
                    عکس
                </button>
            </div>
        </div>
        
        <div class='camera-card'>
            <h3 class='camera-title'>
                <i class='fas fa-bridge'></i>
                دوربین پل بروکلین
            </h3>
            <div class='camera-status'>
                <span class='status-indicator'></span>
                <span>آنلاین</span>
            </div>
            <div>
                <button class='control-btn'>
                    <i class='fas fa-play'></i>
                    پخش
                </button>
                <button class='control-btn'>
                    <i class='fas fa-expand'></i>
                    تمام صفحه
                </button>
                <button class='control-btn'>
                    <i class='fas fa-camera'></i>
                    عکس
                </button>
            </div>
        </div>
        
        <div style='text-align: center; margin-top: 30px;'>
            <h3>دکمه‌های کنترل کلی</h3>
            <a href='factory_cameras.php' class='global-btn'>
                <i class='fas fa-play'></i>
                شروع همه
            </a>
            <a href='factory_cameras.php' class='global-btn'>
                <i class='fas fa-pause'></i>
                توقف همه
            </a>
            <a href='factory_cameras.php' class='global-btn'>
                <i class='fas fa-sync-alt'></i>
                بروزرسانی
            </a>
            <a href='factory_cameras.php' class='global-btn'>
                <i class='fas fa-camera'></i>
                عکس‌برداری همه
            </a>
        </div>
        
        <div style='text-align: center; margin-top: 30px;'>
            <a href='factory_cameras.php' class='global-btn' style='background: linear-gradient(135deg, #10b981 0%, #059669 100%);'>
                <i class='fas fa-arrow-right'></i>
                بازگشت به صفحه اصلی
            </a>
        </div>
    </div>
</body>
</html>";
?>