<?php
// index_visit.php - صفحه اصلی سیستم بازدید
session_start();

// تنظیم session برای تست
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'admin';
    $_SESSION['role'] = 'ادمین';
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سیستم مدیریت بازدید کارخانه - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <style>
        body { 
            font-family: Vazirmatn, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding-top: 50px;
        }
        .main-container { 
            max-width: 1200px; 
            margin: 0 auto; 
            background: white; 
            border-radius: 20px; 
            box-shadow: 0 10px 30px rgba(0,0,0,.2);
            overflow: hidden;
        }
        .header { 
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%); 
            color: white; 
            padding: 40px; 
            text-align: center; 
        }
        .content { padding: 40px; }
        .card { 
            background: white; 
            border-radius: 15px; 
            padding: 30px; 
            margin-bottom: 20px; 
            box-shadow: 0 5px 15px rgba(0,0,0,.1);
            transition: all .3s ease;
            border-left: 4px solid #3498db;
        }
        .card:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 10px 25px rgba(0,0,0,.15); 
        }
        .btn-main { 
            background: linear-gradient(135deg, #3498db 0%, #2c3e50 100%); 
            border: none; 
            color: white; 
            padding: 15px 30px; 
            border-radius: 25px; 
            font-size: 1.1rem; 
            font-weight: bold; 
            text-decoration: none;
            display: inline-block;
            margin: 10px;
            transition: all .3s ease;
        }
        .btn-main:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 5px 15px rgba(0,0,0,.2); 
            color: white;
        }
        .btn-success { background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%); }
        .btn-warning { background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); }
        .btn-info { background: linear-gradient(135deg, #17a2b8 0%, #20c997 100%); }
        .btn-danger { background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); }
        .feature-icon { 
            font-size: 3rem; 
            color: #3498db; 
            margin-bottom: 20px; 
        }
        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); 
            gap: 20px; 
            margin: 30px 0; 
        }
        .stat-card { 
            background: #f8f9fa; 
            border-radius: 10px; 
            padding: 20px; 
            text-align: center; 
            border-left: 4px solid #3498db;
        }
        .stat-number { 
            font-size: 2.5rem; 
            font-weight: bold; 
            color: #2c3e50; 
            margin-bottom: 10px; 
        }
        .stat-label { 
            color: #6c757d; 
            font-size: 0.9rem; 
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="main-container">
            <div class="header">
                <h1><i class="bi bi-building"></i> سیستم مدیریت بازدید کارخانه</h1>
                <p class="mb-0">مدیریت جامع بازدیدها از درخواست تا گزارش نهایی</p>
            </div>
            
            <div class="content">
                <div class="row">
                    <div class="col-md-8">
                        <h2>🏭 ویژگی‌های سیستم</h2>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <i class="bi bi-calendar-check feature-icon"></i>
                                    <h5>مدیریت بازدیدها</h5>
                                    <p>ثبت، ویرایش و پیگیری درخواست‌های بازدید</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <i class="bi bi-qr-code-scan feature-icon"></i>
                                    <h5>Check-in موبایل</h5>
                                    <p>ورود و خروج بازدیدکنندگان با QR Code</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <i class="bi bi-file-earmark feature-icon"></i>
                                    <h5>مدیریت مدارک</h5>
                                    <p>آپلود، تایید و مدیریت مدارک</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <i class="bi bi-gear feature-icon"></i>
                                    <h5>رزرو دستگاه‌ها</h5>
                                    <p>رزرو تجهیزات با جلوگیری از تداخل</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <h2>📊 آمار کلی</h2>
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-number">0</div>
                                <div class="stat-label">کل درخواست‌ها</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number">0</div>
                                <div class="stat-label">بازدیدهای امروز</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number">0</div>
                                <div class="stat-label">نیاز به مدارک</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number">0</div>
                                <div class="stat-label">تکمیل شده</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-12">
                        <h2>🚀 عملیات سریع</h2>
                        <div class="text-center">
                            <a href="visit_dashboard.php" class="btn-main">
                                <i class="bi bi-speedometer2"></i> داشبورد بازدیدها
                            </a>
                            <a href="visit_management.php" class="btn-main btn-success">
                                <i class="bi bi-plus-circle"></i> مدیریت بازدیدها
                            </a>
                            <a href="visit_checkin.php" class="btn-main btn-warning">
                                <i class="bi bi-qr-code-scan"></i> Check-in موبایل
                            </a>
                            <a href="visit_details.php?id=1" class="btn-main btn-info">
                                <i class="bi bi-eye"></i> جزئیات بازدید
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-12">
                        <h2>🔧 تست سیستم</h2>
                        <div class="text-center">
                            <a href="test_final_visit.php" class="btn-main btn-danger">
                                <i class="bi bi-bug"></i> تست کامل سیستم
                            </a>
                            <a href="debug_visit.php" class="btn-main btn-info">
                                <i class="bi bi-tools"></i> تشخیص مشکل
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card">
                            <h3>📋 راهنمای استفاده</h3>
                            <ol>
                                <li><strong>داشبورد بازدیدها:</strong> مشاهده آمار کلی و عملیات سریع</li>
                                <li><strong>مدیریت بازدیدها:</strong> ثبت درخواست جدید و مدیریت موجود</li>
                                <li><strong>Check-in موبایل:</strong> ورود و خروج بازدیدکنندگان</li>
                                <li><strong>جزئیات بازدید:</strong> مشاهده کامل اطلاعات یک بازدید</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>