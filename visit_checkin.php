<?php
require_once 'config.php';

// بررسی دسترسی
if (!hasPermission('visit_management')) {
    die('دسترسی غیرمجاز');
}

$page_title = 'چک‌این بازدیدکنندگان';

// پردازش چک‌این
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'checkin':
                    $visit_id = (int)$_POST['visit_id'];
                    $qr_code = sanitizeInput($_POST['qr_code']);
                    
                    if (checkInVisit($pdo, $visit_id, $qr_code)) {
                        $_SESSION['success_message'] = 'چک‌این با موفقیت انجام شد';
                    } else {
                        $_SESSION['error_message'] = 'خطا در چک‌این - لطفاً اطلاعات را بررسی کنید';
                    }
                    break;
                    
                case 'checkout':
                    $visit_id = (int)$_POST['visit_id'];
                    
                    if (checkOutVisit($pdo, $visit_id)) {
                        $_SESSION['success_message'] = 'چک‌اوت با موفقیت انجام شد';
                    } else {
                        $_SESSION['error_message'] = 'خطا در چک‌اوت';
                    }
                    break;
            }
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'خطا: ' . $e->getMessage();
    }
}

// جستجوی بازدید
$search_results = [];
$search_query = $_GET['search'] ?? '';

if ($search_query) {
    try {
        // جستجو بر اساس QR Code یا شماره درخواست
        $stmt = $pdo->prepare("
            SELECT vr.*, 
                   u1.full_name as created_by_name,
                   u2.full_name as assigned_to_name,
                   u3.full_name as host_name
            FROM visit_requests vr
            LEFT JOIN users u1 ON vr.created_by = u1.id
            LEFT JOIN users u2 ON vr.assigned_to = u2.id
            LEFT JOIN users u3 ON vr.host_id = u3.id
            WHERE vr.qr_code = ? OR vr.request_number = ? OR vr.id = ?
            ORDER BY vr.created_at DESC
        ");
        $stmt->execute([$search_query, $search_query, $search_query]);
        $search_results = $stmt->fetchAll();
    } catch (Exception $e) {
        $search_results = [];
    }
}

// دریافت بازدیدهای امروز
try {
    $today_visits = getVisitRequests($pdo, [
        'date_from' => date('Y-m-d 00:00:00'),
        'date_to' => date('Y-m-d 23:59:59')
    ]);
} catch (Exception $e) {
    $today_visits = [];
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
        .visit-card {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .search-container {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
        }
        .qr-scanner {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0">
                        <i class="fas fa-qrcode me-2"></i>
                        <?php echo $page_title; ?>
                    </h1>
                    <div>
                        <a href="visit_management.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-right me-1"></i>
                            مدیریت بازدیدها
                        </a>
                    </div>
                </div>

                <!-- پیام‌های سیستم -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- جستجو -->
                <div class="search-container">
                    <h4 class="mb-3">
                        <i class="fas fa-search me-2"></i>
                        جستجوی بازدید
                    </h4>
                    <form method="GET" class="row g-3">
                        <div class="col-md-8">
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-qrcode"></i>
                                </span>
                                <input type="text" class="form-control form-control-lg" 
                                       name="search" value="<?php echo htmlspecialchars($search_query); ?>" 
                                       placeholder="شماره درخواست، QR Code یا ID را وارد کنید">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-light btn-lg w-100">
                                <i class="fas fa-search me-2"></i>
                                جستجو
                            </button>
                        </div>
                    </form>
                </div>

                <!-- نتایج جستجو -->
                <?php if (!empty($search_results)): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-list me-2"></i>
                                نتایج جستجو (<?php echo count($search_results); ?> مورد)
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($search_results as $visit): ?>
                                <div class="visit-card">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <h5 class="mb-2"><?php echo htmlspecialchars($visit['company_name']); ?></h5>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <p class="mb-1">
                                                        <i class="fas fa-user me-2"></i>
                                                        <strong>تماس‌گیرنده:</strong> <?php echo htmlspecialchars($visit['contact_person']); ?>
                                                    </p>
                                                    <p class="mb-1">
                                                        <i class="fas fa-phone me-2"></i>
                                                        <strong>تلفن:</strong> <?php echo htmlspecialchars($visit['contact_phone']); ?>
                                                    </p>
                                                    <p class="mb-1">
                                                        <i class="fas fa-users me-2"></i>
                                                        <strong>تعداد:</strong> <?php echo $visit['visitor_count']; ?> نفر
                                                    </p>
                                                </div>
                                                <div class="col-md-6">
                                                    <p class="mb-1">
                                                        <i class="fas fa-calendar me-2"></i>
                                                        <strong>تاریخ ایجاد:</strong> <?php echo jalali_format($visit['created_at'], 'Y/m/d H:i'); ?>
                                                    </p>
                                                    <p class="mb-1">
                                                        <i class="fas fa-clock me-2"></i>
                                                        <strong>مدت:</strong> <?php echo $visit['visit_duration']; ?> دقیقه
                                                    </p>
                                                    <p class="mb-1">
                                                        <i class="fas fa-bullseye me-2"></i>
                                                        <strong>هدف:</strong> <?php echo $visit['visit_purpose']; ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4 text-end">
                                            <div class="mb-3">
                                                <span class="status-badge status-<?php echo $visit['status']; ?>">
                                                    <?php echo $visit['status']; ?>
                                                </span>
                                            </div>
                                            <div class="d-grid gap-2">
                                                <?php if ($visit['status'] === 'ready_for_visit' || $visit['status'] === 'reserved'): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="checkin">
                                                        <input type="hidden" name="visit_id" value="<?php echo $visit['id']; ?>">
                                                        <input type="hidden" name="qr_code" value="<?php echo $visit['qr_code']; ?>">
                                                        <?php echo csrf_field(); ?>
                                                        <button type="submit" class="btn btn-success w-100">
                                                            <i class="fas fa-sign-in-alt me-2"></i>
                                                            چک‌این
                                                        </button>
                                                    </form>
                                                <?php elseif ($visit['status'] === 'checked_in' || $visit['status'] === 'onsite'): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="checkout">
                                                        <input type="hidden" name="visit_id" value="<?php echo $visit['id']; ?>">
                                                        <?php echo csrf_field(); ?>
                                                        <button type="submit" class="btn btn-warning w-100">
                                                            <i class="fas fa-sign-out-alt me-2"></i>
                                                            چک‌اوت
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <a href="visit_details.php?id=<?php echo $visit['id']; ?>" 
                                                   class="btn btn-outline-primary">
                                                    <i class="fas fa-eye me-2"></i>
                                                    جزئیات
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php elseif ($search_query): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-search fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">هیچ بازدیدی یافت نشد</h5>
                            <p class="text-muted">لطفاً شماره درخواست، QR Code یا ID را بررسی کنید</p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- بازدیدهای امروز -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-calendar-day me-2"></i>
                            بازدیدهای امروز (<?php echo count($today_visits); ?> مورد)
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($today_visits)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-calendar-times fa-3x mb-3"></i>
                                <p>هیچ بازدیدی برای امروز برنامه‌ریزی نشده</p>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($today_visits as $visit): ?>
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="visit-card">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="mb-0"><?php echo htmlspecialchars($visit['company_name']); ?></h6>
                                                <span class="status-badge status-<?php echo $visit['status']; ?>">
                                                    <?php echo $visit['status']; ?>
                                                </span>
                                            </div>
                                            <p class="text-muted mb-1">
                                                <i class="fas fa-user me-1"></i>
                                                <?php echo htmlspecialchars($visit['contact_person']); ?>
                                            </p>
                                            <p class="text-muted mb-1">
                                                <i class="fas fa-phone me-1"></i>
                                                <?php echo htmlspecialchars($visit['contact_phone']); ?>
                                            </p>
                                            <p class="text-muted mb-2">
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo $visit['visit_duration']; ?> دقیقه
                                            </p>
                                            <div class="d-flex gap-2">
                                                <?php if ($visit['status'] === 'ready_for_visit' || $visit['status'] === 'reserved'): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="checkin">
                                                        <input type="hidden" name="visit_id" value="<?php echo $visit['id']; ?>">
                                                        <input type="hidden" name="qr_code" value="<?php echo $visit['qr_code']; ?>">
                                                        <?php echo csrf_field(); ?>
                                                        <button type="submit" class="btn btn-sm btn-success">
                                                            <i class="fas fa-sign-in-alt me-1"></i>
                                                            چک‌این
                                                        </button>
                                                    </form>
                                                <?php elseif ($visit['status'] === 'checked_in' || $visit['status'] === 'onsite'): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="checkout">
                                                        <input type="hidden" name="visit_id" value="<?php echo $visit['id']; ?>">
                                                        <?php echo csrf_field(); ?>
                                                        <button type="submit" class="btn btn-sm btn-warning">
                                                            <i class="fas fa-sign-out-alt me-1"></i>
                                                            چک‌اوت
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <a href="visit_details.php?id=<?php echo $visit['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye me-1"></i>
                                                    جزئیات
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // QR Code Scanner (placeholder for future implementation)
        function startQRScanner() {
            // This would integrate with a QR code scanner library
            alert('QR Code Scanner در نسخه‌های آینده اضافه خواهد شد');
        }
        
        // Auto-focus on search input
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput) {
                searchInput.focus();
            }
        });
    </script>
</body>
</html>