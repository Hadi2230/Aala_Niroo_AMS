<?php
/**
 * visit_dashboard_modern.php - داشبورد مدرن مدیریت بازدید کارخانه
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
    
    <!-- CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/animate.css@4.1.1/animate.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
            --dark-color: #1f2937;
            --light-color: #f8fafc;
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-success: linear-gradient(135deg, #10b981 0%, #059669 100%);
            --gradient-warning: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            --gradient-danger: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: var(--dark-color);
        }

        .main-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            margin: 20px;
            box-shadow: var(--shadow-xl);
            overflow: hidden;
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
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="50" cy="10" r="0.5" fill="white" opacity="0.1"/><circle cx="10" cy="60" r="0.5" fill="white" opacity="0.1"/><circle cx="90" cy="40" r="0.5" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
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

        .content-area {
            padding: 30px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: var(--shadow-md);
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card.success::before {
            background: var(--gradient-success);
        }

        .stat-card.warning::before {
            background: var(--gradient-warning);
        }

        .stat-card.danger::before {
            background: var(--gradient-danger);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            margin-bottom: 15px;
        }

        .stat-icon.primary {
            background: var(--gradient-primary);
        }

        .stat-icon.success {
            background: var(--gradient-success);
        }

        .stat-icon.warning {
            background: var(--gradient-warning);
        }

        .stat-icon.danger {
            background: var(--gradient-danger);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 5px;
        }

        .stat-label {
            color: #6b7280;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .quick-actions {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: var(--shadow-md);
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .action-btn {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            text-decoration: none;
            color: var(--dark-color);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            position: relative;
            overflow: hidden;
        }

        .action-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: left 0.5s;
        }

        .action-btn:hover::before {
            left: 100%;
        }

        .action-btn:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .action-icon {
            font-size: 2rem;
            margin-bottom: 5px;
        }

        .action-text {
            font-weight: 600;
            font-size: 0.9rem;
        }

        .camera-section {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: var(--shadow-md);
            margin-bottom: 30px;
        }

        .camera-preview {
            background: linear-gradient(135deg, #1f2937 0%, #374151 100%);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            color: white;
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
        }

        .camera-preview::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="camera-pattern" width="20" height="20" patternUnits="userSpaceOnUse"><circle cx="10" cy="10" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23camera-pattern)"/></svg>');
            opacity: 0.3;
        }

        .camera-icon {
            font-size: 4rem;
            margin-bottom: 15px;
            position: relative;
            z-index: 2;
        }

        .camera-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 10px;
            position: relative;
            z-index: 2;
        }

        .camera-subtitle {
            opacity: 0.8;
            position: relative;
            z-index: 2;
        }

        .camera-btn {
            background: var(--gradient-primary);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 12px 24px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            position: relative;
            z-index: 2;
        }

        .camera-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            color: white;
        }

        .recent-requests {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: var(--shadow-md);
        }

        .request-item {
            background: #f8fafc;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
        }

        .request-item:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .request-header {
            display: flex;
            justify-content: between;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .request-company {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 5px;
        }

        .request-contact {
            color: #6b7280;
            font-size: 0.9rem;
        }

        .request-status {
            background: var(--gradient-success);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .request-status.pending {
            background: var(--gradient-warning);
        }

        .request-status.cancelled {
            background: var(--gradient-danger);
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6b7280;
        }

        .empty-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .floating-btn {
            position: fixed;
            bottom: 30px;
            left: 30px;
            width: 60px;
            height: 60px;
            background: var(--gradient-primary);
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 1.5rem;
            box-shadow: var(--shadow-lg);
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .floating-btn:hover {
            transform: scale(1.1);
            box-shadow: var(--shadow-xl);
        }

        @media (max-width: 768px) {
            .main-container {
                margin: 10px;
                border-radius: 15px;
            }
            
            .content-area {
                padding: 20px;
            }
            
            .page-title {
                font-size: 2rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .action-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .animate-fade-in {
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.5;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php if (file_exists('navbar.php')): ?>
        <?php include 'navbar.php'; ?>
    <?php endif; ?>

    <div class="main-container animate-fade-in">
        <!-- Header -->
        <div class="header">
            <div class="header-content">
                <h1 class="page-title">
                    <i class="fas fa-industry me-3"></i>
                    داشبورد مدیریت بازدید کارخانه
                </h1>
                <p class="page-subtitle">
                    مدیریت حرفه‌ای بازدیدها و نظارت بر کارخانه
                </p>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content-area">
            <!-- Stats Grid -->
            <div class="stats-grid" data-aos="fade-up" data-aos-delay="100">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="stat-value"><?php echo count($today_visits); ?></div>
                    <div class="stat-label">بازدیدهای امروز</div>
                </div>

                <div class="stat-card success">
                    <div class="stat-icon success">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="stat-value"><?php echo count($pending_documents); ?></div>
                    <div class="stat-label">مدارک در انتظار</div>
                </div>

                <div class="stat-card warning">
                    <div class="stat-icon warning">
                        <i class="fas fa-cogs"></i>
                    </div>
                    <div class="stat-value"><?php echo count($reserved_devices); ?></div>
                    <div class="stat-label">دستگاه‌های رزرو شده</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['total_requests'] ?? 0; ?></div>
                    <div class="stat-label">کل درخواست‌ها</div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions" data-aos="fade-up" data-aos-delay="200">
                <h2 class="section-title">
                    <i class="fas fa-bolt"></i>
                    اقدامات سریع
                </h2>
                <div class="action-grid">
                    <a href="visit_management.php" class="action-btn">
                        <i class="fas fa-plus-circle action-icon"></i>
                        <span class="action-text">درخواست جدید</span>
                    </a>
                    
                    <a href="visit_management.php" class="action-btn">
                        <i class="fas fa-list action-icon"></i>
                        <span class="action-text">مدیریت بازدیدها</span>
                    </a>
                    
                    <a href="visit_checkin.php" class="action-btn">
                        <i class="fas fa-qrcode action-icon"></i>
                        <span class="action-text">ورود بازدیدکنندگان</span>
                    </a>
                    
                    <a href="factory_cameras.php" class="action-btn">
                        <i class="fas fa-video action-icon"></i>
                        <span class="action-text">دوربین‌های کارخانه</span>
                    </a>
                    
                    <a href="tickets.php" class="action-btn">
                        <i class="fas fa-ticket-alt action-icon"></i>
                        <span class="action-text">تیکت‌ها</span>
                    </a>
                    
                    <a href="reports.php" class="action-btn">
                        <i class="fas fa-chart-bar action-icon"></i>
                        <span class="action-text">گزارش‌ها</span>
                    </a>
                </div>
            </div>

            <!-- Camera Section -->
            <div class="camera-section" data-aos="fade-up" data-aos-delay="300">
                <h2 class="section-title">
                    <i class="fas fa-video"></i>
                    نظارت بر کارخانه
                </h2>
                <div class="camera-preview">
                    <i class="fas fa-video camera-icon pulse"></i>
                    <h3 class="camera-title">دوربین‌های مداربسته کارخانه</h3>
                    <p class="camera-subtitle">مشاهده زنده تمام بخش‌های کارخانه</p>
                    <a href="factory_cameras.php" class="camera-btn">
                        <i class="fas fa-play"></i>
                        مشاهده دوربین‌ها
                    </a>
                </div>
            </div>

            <!-- Recent Requests -->
            <div class="recent-requests" data-aos="fade-up" data-aos-delay="400">
                <h2 class="section-title">
                    <i class="fas fa-history"></i>
                    درخواست‌های اخیر
                </h2>
                
                <?php if (empty($recent_requests)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox empty-icon"></i>
                        <h4>هیچ درخواست اخیری یافت نشد</h4>
                        <p>برای شروع، درخواست بازدید جدیدی ایجاد کنید</p>
                    </div>
                <?php else: ?>
                    <?php foreach (array_slice($recent_requests, 0, 5) as $request): ?>
                        <div class="request-item">
                            <div class="request-header">
                                <div>
                                    <div class="request-company"><?php echo htmlspecialchars($request['company_name']); ?></div>
                                    <div class="request-contact">
                                        <i class="fas fa-user me-1"></i>
                                        <?php echo htmlspecialchars($request['contact_person']); ?>
                                        <span class="ms-3">
                                            <i class="fas fa-phone me-1"></i>
                                            <?php echo htmlspecialchars($request['contact_phone']); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <span class="request-status status-<?php echo $request['status']; ?>">
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

    <!-- Floating Action Button -->
    <button class="floating-btn" onclick="window.location.href='visit_management.php'" title="درخواست جدید">
        <i class="fas fa-plus"></i>
    </button>

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

        // Add loading animation
        document.addEventListener('DOMContentLoaded', function() {
            // Animate stat cards
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    card.style.transition = 'all 0.6s ease';
                    
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, 100);
                }, index * 100);
            });

            // Add hover effects
            const actionBtns = document.querySelectorAll('.action-btn');
            actionBtns.forEach(btn => {
                btn.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px) scale(1.02)';
                });
                
                btn.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });
        });

        // Add smooth scrolling
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>
</html>