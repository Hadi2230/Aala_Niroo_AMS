<?php
/**
 * request_tracking_final.php - پیگیری درخواست‌ها - نسخه نهایی
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'config_simple.php';

$page_title = 'پیگیری درخواست‌ها';

// دریافت درخواست‌های کاربر
$requests = [];
try {
    $stmt = $pdo->prepare("
        SELECT r.*, 
               COUNT(rf.id) as file_count,
               COUNT(rw.id) as workflow_count
        FROM requests r
        LEFT JOIN request_files rf ON r.id = rf.request_id
        LEFT JOIN request_workflow rw ON r.id = rw.request_id
        WHERE r.requester_id = ?
        GROUP BY r.id
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $requests = $stmt->fetchAll();
} catch (Exception $e) {
    $requests = [];
}

// دریافت آمار
$stats = [
    'total' => count($requests),
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'completed' => 0
];

foreach ($requests as $request) {
    switch ($request['status']) {
        case 'در انتظار تأیید':
        case 'در حال بررسی':
            $stats['pending']++;
            break;
        case 'تأیید شده':
            $stats['approved']++;
            break;
        case 'رد شده':
            $stats['rejected']++;
            break;
        case 'تکمیل شده':
            $stats['completed']++;
            break;
    }
}
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

        .stats-card {
            background: linear-gradient(135deg, var(--primary-color) 0%, #34495e 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            transition: all 0.3s ease;
            height: 100%;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
        }

        .stats-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .stats-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .stats-description {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .request-item {
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }

        .request-item:hover {
            border-color: var(--secondary-color);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.1);
        }

        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .request-number {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .request-date {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .request-details {
            margin-bottom: 15px;
        }

        .request-item-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .request-meta {
            display: flex;
            gap: 15px;
            margin-bottom: 10px;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-approved {
            background: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .status-completed {
            background: #d1ecf1;
            color: #0c5460;
        }

        .priority-badge {
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .priority-low {
            background: #d4edda;
            color: #155724;
        }

        .priority-medium {
            background: #fff3cd;
            color: #856404;
        }

        .priority-high {
            background: #f8d7da;
            color: #721c24;
        }

        .priority-urgent {
            background: #f5c6cb;
            color: #721c24;
        }

        .request-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn {
            border-radius: 8px;
            padding: 8px 16px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--secondary-color) 0%, #5dade2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success-color) 0%, #2ecc71 100%);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        }

        .btn-info {
            background: linear-gradient(135deg, var(--info-color) 0%, #5dade2 100%);
            color: white;
        }

        .btn-info:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
        }

        .text-primary {
            color: var(--text-primary) !important;
        }

        .text-secondary {
            color: var(--text-secondary) !important;
        }

        .text-muted {
            color: var(--text-secondary) !important;
        }

        .no-requests {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }

        .no-requests i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .search-filter {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: var(--shadow);
        }

        .form-control {
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 10px 15px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: white;
            color: var(--text-primary) !important;
        }

        .form-control:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
            background: white;
            color: var(--text-primary) !important;
        }

        .form-label {
            font-weight: 600;
            color: var(--text-primary) !important;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }

        @media (max-width: 768px) {
            .page-title {
                font-size: 2rem;
            }
            
            .card-body {
                padding: 20px;
            }
            
            .stats-card {
                margin-bottom: 20px;
            }
            
            .request-meta {
                flex-direction: column;
                gap: 8px;
            }
            
            .request-actions {
                flex-direction: column;
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
                    <i class="fas fa-search me-3"></i>
                    پیگیری درخواست‌ها
                </h1>
                <p class="page-subtitle">
                    وضعیت و جزئیات درخواست‌های خود را بررسی کنید
                </p>
            </div>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo $stats['total']; ?></div>
                        <div class="stats-title">کل درخواست‌ها</div>
                        <div class="stats-description">همه درخواست‌های شما</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo $stats['pending']; ?></div>
                        <div class="stats-title">در انتظار</div>
                        <div class="stats-description">در حال بررسی</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo $stats['approved']; ?></div>
                        <div class="stats-title">تأیید شده</div>
                        <div class="stats-description">تأیید شده توسط مدیر</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo $stats['completed']; ?></div>
                        <div class="stats-title">تکمیل شده</div>
                        <div class="stats-description">تحویل داده شده</div>
                    </div>
                </div>
            </div>

            <!-- Search and Filter -->
            <div class="search-filter">
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label">جستجو در درخواست‌ها</label>
                        <input type="text" class="form-control" id="searchInput" placeholder="جستجو بر اساس نام آیتم یا شماره درخواست...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">فیلتر بر اساس وضعیت</label>
                        <select class="form-control" id="statusFilter">
                            <option value="">همه وضعیت‌ها</option>
                            <option value="در انتظار تأیید">در انتظار تأیید</option>
                            <option value="در حال بررسی">در حال بررسی</option>
                            <option value="تأیید شده">تأیید شده</option>
                            <option value="رد شده">رد شده</option>
                            <option value="تکمیل شده">تکمیل شده</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">فیلتر بر اساس اولویت</label>
                        <select class="form-control" id="priorityFilter">
                            <option value="">همه اولویت‌ها</option>
                            <option value="کم">کم</option>
                            <option value="متوسط">متوسط</option>
                            <option value="بالا">بالا</option>
                            <option value="فوری">فوری</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button class="btn btn-primary w-100" onclick="clearFilters()">
                            <i class="fas fa-times me-1"></i>
                            پاک کردن
                        </button>
                    </div>
                </div>
            </div>

            <!-- Requests List -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-list me-2"></i>
                    لیست درخواست‌ها
                </div>
                <div class="card-body">
                    <?php if (empty($requests)): ?>
                        <div class="no-requests">
                            <i class="fas fa-inbox"></i>
                            <h4>هیچ درخواستی یافت نشد</h4>
                            <p>شما هنوز درخواستی ایجاد نکرده‌اید.</p>
                            <a href="request_management_final.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>
                                ایجاد درخواست جدید
                            </a>
                        </div>
                    <?php else: ?>
                        <div id="requestsList">
                            <?php foreach ($requests as $request): ?>
                                <div class="request-item" data-status="<?php echo $request['status']; ?>" data-priority="<?php echo $request['priority']; ?>">
                                    <div class="request-header">
                                        <div>
                                            <div class="request-number"><?php echo htmlspecialchars($request['request_number']); ?></div>
                                            <div class="request-date"><?php echo date('Y/m/d H:i', strtotime($request['created_at'])); ?></div>
                                        </div>
                                        <div>
                                            <span class="status-badge status-<?php echo str_replace(' ', '-', strtolower($request['status'])); ?>">
                                                <?php echo $request['status']; ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="request-details">
                                        <div class="request-item-name"><?php echo htmlspecialchars($request['item_name']); ?></div>
                                        <div class="request-meta">
                                            <div class="meta-item">
                                                <i class="fas fa-hashtag"></i>
                                                <span>تعداد: <?php echo $request['quantity']; ?></span>
                                            </div>
                                            <div class="meta-item">
                                                <i class="fas fa-dollar-sign"></i>
                                                <span>قیمت: <?php echo number_format($request['price']); ?> ریال</span>
                                            </div>
                                            <div class="meta-item">
                                                <i class="fas fa-exclamation-triangle"></i>
                                                <span class="priority-badge priority-<?php echo strtolower($request['priority']); ?>">
                                                    <?php echo $request['priority']; ?>
                                                </span>
                                            </div>
                                            <div class="meta-item">
                                                <i class="fas fa-paperclip"></i>
                                                <span><?php echo $request['file_count']; ?> فایل</span>
                                            </div>
                                        </div>
                                        
                                        <?php if ($request['description']): ?>
                                            <div class="mt-2">
                                                <small class="text-muted"><?php echo htmlspecialchars($request['description']); ?></small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="request-actions">
                                        <button class="btn btn-info" onclick="viewDetails(<?php echo $request['id']; ?>)">
                                            <i class="fas fa-eye me-1"></i>
                                            جزئیات
                                        </button>
                                        <?php if ($request['file_count'] > 0): ?>
                                            <button class="btn btn-success" onclick="viewFiles(<?php echo $request['id']; ?>)">
                                                <i class="fas fa-download me-1"></i>
                                                فایل‌ها
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($request['workflow_count'] > 0): ?>
                                            <button class="btn btn-primary" onclick="viewWorkflow(<?php echo $request['id']; ?>)">
                                                <i class="fas fa-route me-1"></i>
                                                گردش کار
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // جستجو و فیلتر
        document.getElementById('searchInput').addEventListener('input', filterRequests);
        document.getElementById('statusFilter').addEventListener('change', filterRequests);
        document.getElementById('priorityFilter').addEventListener('change', filterRequests);

        function filterRequests() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const priorityFilter = document.getElementById('priorityFilter').value;
            
            const requestItems = document.querySelectorAll('.request-item');
            
            requestItems.forEach(item => {
                const requestNumber = item.querySelector('.request-number').textContent.toLowerCase();
                const itemName = item.querySelector('.request-item-name').textContent.toLowerCase();
                const status = item.getAttribute('data-status');
                const priority = item.getAttribute('data-priority');
                
                const matchesSearch = requestNumber.includes(searchTerm) || itemName.includes(searchTerm);
                const matchesStatus = !statusFilter || status === statusFilter;
                const matchesPriority = !priorityFilter || priority === priorityFilter;
                
                if (matchesSearch && matchesStatus && matchesPriority) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        function clearFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('statusFilter').value = '';
            document.getElementById('priorityFilter').value = '';
            filterRequests();
        }

        function viewDetails(requestId) {
            alert('جزئیات درخواست #' + requestId + ' - این قابلیت به زودی اضافه خواهد شد');
        }

        function viewFiles(requestId) {
            alert('فایل‌های درخواست #' + requestId + ' - این قابلیت به زودی اضافه خواهد شد');
        }

        function viewWorkflow(requestId) {
            alert('گردش کار درخواست #' + requestId + ' - این قابلیت به زودی اضافه خواهد شد');
        }
    </script>
</body>
</html>