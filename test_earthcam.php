<?php
/**
 * test_earthcam.php - تست دوربین‌های EarthCam
 */

session_start();
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['role'] = 'ادمین';
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تست دوربین‌های EarthCam</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: white;
            padding: 20px;
        }

        .test-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .camera-test {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .camera-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .camera-frame {
            width: 100%;
            height: 400px;
            border-radius: 12px;
            border: none;
            background: #000;
        }

        .status-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10b981;
            animation: pulse 2s infinite;
            margin-left: 8px;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .test-info {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .btn-test {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 12px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
        }

        .btn-test:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            color: white;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <h1><i class="fas fa-video me-3"></i>تست دوربین‌های EarthCam</h1>
        
        <div class="test-info">
            <h4><i class="fas fa-info-circle me-2"></i>اطلاعات تست</h4>
            <p>این صفحه برای تست دوربین‌های واقعی EarthCam طراحی شده است. اگر دوربین‌ها درست کار کنند، در صفحه اصلی نیز به درستی نمایش داده خواهند شد.</p>
        </div>

        <!-- Test Camera 1: Atlantic Highlands -->
        <div class="camera-test">
            <h3 class="camera-title">
                <i class="fas fa-water"></i>
                دوربین ساحل آتلانتیک هایلندز
                <span class="status-indicator"></span>
            </h3>
            <iframe 
                src="https://www.earthcam.com/usa/newjersey/atlantichighlands/?cam=atlantichighlands"
                class="camera-frame"
                frameborder="0"
                allow="autoplay; encrypted-media"
                allowfullscreen>
            </iframe>
        </div>

        <!-- Test Camera 2: Times Square -->
        <div class="camera-test">
            <h3 class="camera-title">
                <i class="fas fa-city"></i>
                دوربین میدان تایمز
                <span class="status-indicator"></span>
            </h3>
            <iframe 
                src="https://www.earthcam.com/usa/newyork/timessquare/"
                class="camera-frame"
                frameborder="0"
                allow="autoplay; encrypted-media"
                allowfullscreen>
            </iframe>
        </div>

        <!-- Test Camera 3: Central Park -->
        <div class="camera-test">
            <h3 class="camera-title">
                <i class="fas fa-tree"></i>
                دوربین پارک مرکزی
                <span class="status-indicator"></span>
            </h3>
            <iframe 
                src="https://www.earthcam.com/usa/newyork/centralpark/"
                class="camera-frame"
                frameborder="0"
                allow="autoplay; encrypted-media"
                allowfullscreen>
            </iframe>
        </div>

        <!-- Test Camera 4: Brooklyn Bridge -->
        <div class="camera-test">
            <h3 class="camera-title">
                <i class="fas fa-bridge"></i>
                دوربین پل بروکلین
                <span class="status-indicator"></span>
            </h3>
            <iframe 
                src="https://www.earthcam.com/usa/newyork/brooklynbridge/"
                class="camera-frame"
                frameborder="0"
                allow="autoplay; encrypted-media"
                allowfullscreen>
            </iframe>
        </div>

        <div class="text-center mt-4">
            <a href="factory_cameras.php" class="btn-test">
                <i class="fas fa-arrow-right me-2"></i>
                بازگشت به صفحه اصلی دوربین‌ها
            </a>
            <a href="visit_dashboard_modern.php" class="btn-test">
                <i class="fas fa-home me-2"></i>
                داشبورد اصلی
            </a>
        </div>
    </div>

    <script>
        // Add loading animation
        document.addEventListener('DOMContentLoaded', function() {
            const cameraTests = document.querySelectorAll('.camera-test');
            cameraTests.forEach((test, index) => {
                setTimeout(() => {
                    test.style.opacity = '0';
                    test.style.transform = 'translateY(20px)';
                    test.style.transition = 'all 0.6s ease';
                    
                    setTimeout(() => {
                        test.style.opacity = '1';
                        test.style.transform = 'translateY(0)';
                    }, 100);
                }, index * 200);
            });
        });

        // Check if cameras are loading
        function checkCameraStatus() {
            const iframes = document.querySelectorAll('iframe');
            iframes.forEach((iframe, index) => {
                iframe.onload = function() {
                    console.log(`دوربین ${index + 1} با موفقیت بارگذاری شد`);
                };
                
                iframe.onerror = function() {
                    console.error(`خطا در بارگذاری دوربین ${index + 1}`);
                };
            });
        }

        // Run status check
        checkCameraStatus();
    </script>
</body>
</html>