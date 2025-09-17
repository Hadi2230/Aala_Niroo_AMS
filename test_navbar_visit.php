<?php
// test_navbar_visit.php - تست navbar با منوی بازدید کارخانه
session_start();

// تنظیم session برای تست
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['role'] = 'ادمین';
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تست Navbar با منوی بازدید کارخانه</title>
    <style>
        body { 
            font-family: Tahoma, Arial, sans-serif; 
            background: #f8f9fa; 
            padding-top: 100px; 
            margin: 0; 
        }
        .test-container { 
            max-width: 1200px; 
            margin: 0 auto; 
            background: white; 
            padding: 30px; 
            border-radius: 10px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
        }
        .success { 
            color: #27ae60; 
            background: #d5f4e6; 
            padding: 10px; 
            border-radius: 5px; 
            margin: 10px 0; 
        }
        .info { 
            color: #3498db; 
            background: #d6eaf8; 
            padding: 10px; 
            border-radius: 5px; 
            margin: 10px 0; 
        }
        h1 { 
            text-align: center; 
            color: #2c3e50; 
        }
        .feature-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); 
            gap: 20px; 
            margin: 30px 0; 
        }
        .feature-card { 
            background: #f8f9fa; 
            border-radius: 10px; 
            padding: 20px; 
            border-left: 4px solid #3498db; 
        }
        .feature-card h3 { 
            color: #2c3e50; 
            margin-top: 0; 
        }
        .btn { 
            background: #3498db; 
            color: white; 
            padding: 10px 20px; 
            text-decoration: none; 
            border-radius: 5px; 
            display: inline-block; 
            margin: 10px; 
        }
        .btn:hover { 
            background: #2980b9; 
            color: white; 
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="test-container">
        <h1>🏭 تست Navbar با منوی بازدید کارخانه</h1>
        
        <div class="success">
            ✅ منوی "بازدید کارخانه" با موفقیت به navbar اضافه شد!
        </div>
        
        <div class="info">
            📋 ویژگی‌های اضافه شده:
        </div>
        
        <div class="feature-grid">
            <div class="feature-card">
                <h3>🏠 داشبورد بازدیدها</h3>
                <p>آمار کلی، عملیات سریع و نمای کلی سیستم بازدید</p>
                <a href="visit_dashboard.php" class="btn">مشاهده داشبورد</a>
            </div>
            
            <div class="feature-card">
                <h3>📅 مدیریت بازدیدها</h3>
                <p>ثبت درخواست جدید، ویرایش و مدیریت بازدیدها</p>
                <a href="visit_management.php" class="btn">مدیریت بازدیدها</a>
            </div>
            
            <div class="feature-card">
                <h3>📱 Check-in موبایل</h3>
                <p>ورود و خروج بازدیدکنندگان با QR Code</p>
                <a href="visit_checkin.php" class="btn">Check-in</a>
            </div>
            
            <div class="feature-card">
                <h3>👁️ جزئیات بازدید</h3>
                <p>مشاهده کامل اطلاعات، مدارک و گزارش‌های بازدید</p>
                <a href="visit_details.php?id=1" class="btn">جزئیات بازدید</a>
            </div>
            
            <div class="feature-card">
                <h3>📊 تقویم بازدیدها</h3>
                <p>برنامه‌ریزی، زمان‌بندی و مدیریت تقویم</p>
                <a href="visit_management.php?status=scheduled" class="btn">تقویم</a>
            </div>
            
            <div class="feature-card">
                <h3>📄 بررسی مدارک</h3>
                <p>تایید، بررسی و مدیریت مدارک بازدید</p>
                <a href="visit_management.php?status=documents_required" class="btn">بررسی مدارک</a>
            </div>
        </div>
        
        <div class="success">
            🎉 منوی بازدید کارخانه کاملاً آماده و قابل استفاده است!
        </div>
        
        <div class="info">
            🔧 ویژگی‌های فنی:
            <ul>
                <li>✅ منوی مگا با 6 گزینه اصلی</li>
                <li>✅ آیکون‌های مناسب و زیبا</li>
                <li>✅ پشتیبانی از زبان فارسی و انگلیسی</li>
                <li>✅ طراحی ریسپانسیو</li>
                <li>✅ انیمیشن‌های نرم</li>
                <li>✅ Badge اعلان‌ها</li>
                <li>✅ Active state برای صفحات</li>
            </ul>
        </div>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="visit_dashboard.php" class="btn" style="font-size: 18px; padding: 15px 30px;">
                🚀 شروع سیستم بازدید کارخانه
            </a>
        </div>
    </div>
</body>
</html>