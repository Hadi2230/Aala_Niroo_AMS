<?php
require_once 'config.php';

// بررسی دسترسی
if (!hasPermission('visit_management')) {
    die('دسترسی غیرمجاز');
}

$visit_id = (int)($_GET['id'] ?? 0);
if (!$visit_id) {
    redirect('visit_management.php');
}

// دریافت جزئیات بازدید
try {
    $stmt = $pdo->prepare("
        SELECT vr.*, 
               u1.full_name as created_by_name,
               u2.full_name as assigned_to_name,
               u3.full_name as host_name
        FROM visit_requests vr
        LEFT JOIN users u1 ON vr.created_by = u1.id
        LEFT JOIN users u2 ON vr.assigned_to = u2.id
        LEFT JOIN users u3 ON vr.host_id = u3.id
        WHERE vr.id = ?
    ");
    $stmt->execute([$visit_id]);
    $visit = $stmt->fetch();
    
    if (!$visit) {
        redirect('visit_management.php');
    }
    
    // دریافت مدارک
    $stmt = $pdo->prepare("SELECT * FROM visit_documents WHERE visit_request_id = ? ORDER BY created_at DESC");
    $stmt->execute([$visit_id]);
    $documents = $stmt->fetchAll();
    
    // دریافت دستگاه‌های رزرو شده
    $stmt = $pdo->prepare("
        SELECT dr.*, a.name as asset_name, at.display_name as asset_type
        FROM device_reservations dr
        JOIN assets a ON dr.asset_id = a.id
        LEFT JOIN asset_types at ON a.type_id = at.id
        WHERE dr.visit_request_id = ?
    ");
    $stmt->execute([$visit_id]);
    $reservations = $stmt->fetchAll();
    
    // دریافت تاریخچه
    $stmt = $pdo->prepare("
        SELECT vh.*, u.full_name as performed_by_name
        FROM visit_history vh
        LEFT JOIN users u ON vh.performed_by = u.id
        WHERE vh.visit_request_id = ?
        ORDER BY vh.created_at DESC
    ");
    $stmt->execute([$visit_id]);
    $history = $stmt->fetchAll();
    
} catch (Exception $e) {
    $visit = null;
    $documents = [];
    $reservations = [];
    $history = [];
}

if (!$visit) {
    redirect('visit_management.php');
}

$page_title = 'جزئیات بازدید - ' . $visit['company_name'];
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
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
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
        .info-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .history-item {
            border-left: 3px solid #007bff;
            padding-left: 15px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0">
                        <i class="fas fa-building me-2"></i>
                        جزئیات بازدید
                    </h1>
                    <div>
                        <a href="visit_management.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-right me-1"></i>
                            بازگشت
                        </a>
                    </div>
                </div>

                <!-- اطلاعات کلی -->
                <div class="info-card">
                    <div class="row">
                        <div class="col-md-8">
                            <h4 class="mb-3"><?php echo htmlspecialchars($visit['company_name']); ?></h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>تماس‌گیرنده:</strong> <?php echo htmlspecialchars($visit['contact_person']); ?></p>
                                    <p><strong>شماره تماس:</strong> <?php echo htmlspecialchars($visit['contact_phone']); ?></p>
                                    <p><strong>ایمیل:</strong> <?php echo htmlspecialchars($visit['contact_email'] ?: 'ندارد'); ?></p>
                                    <p><strong>تعداد بازدیدکنندگان:</strong> <?php echo $visit['visitor_count']; ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>هدف بازدید:</strong> <?php echo $visit['visit_purpose']; ?></p>
                                    <p><strong>نوع بازدید:</strong> <?php echo $visit['visit_type']; ?></p>
                                    <p><strong>روش درخواست:</strong> <?php echo $visit['request_method']; ?></p>
                                    <p><strong>مدت بازدید:</strong> <?php echo $visit['visit_duration']; ?> دقیقه</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 text-end">
                            <span class="status-badge status-<?php echo $visit['status']; ?>">
                                <?php echo $visit['status']; ?>
                            </span>
                            <div class="mt-3">
                                <p class="text-muted mb-1">
                                    <strong>شماره درخواست:</strong><br>
                                    <?php echo $visit['request_number']; ?>
                                </p>
                                <p class="text-muted mb-1">
                                    <strong>تاریخ ایجاد:</strong><br>
                                    <?php echo jalali_format($visit['created_at'], 'Y/m/d H:i'); ?>
                                </p>
                                <?php if ($visit['confirmed_date']): ?>
                                    <p class="text-muted mb-1">
                                        <strong>تاریخ تایید شده:</strong><br>
                                        <?php echo jalali_format($visit['confirmed_date'], 'Y/m/d H:i'); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- اطلاعات تکمیلی -->
                    <div class="col-md-6">
                        <div class="info-card">
                            <h5 class="mb-3">
                                <i class="fas fa-info-circle me-2"></i>
                                اطلاعات تکمیلی
                            </h5>
                            <p><strong>اولویت:</strong> <?php echo $visit['priority']; ?></p>
                            <p><strong>نیاز به NDA:</strong> 
                                <?php echo $visit['requires_nda'] ? 'بله' : 'خیر'; ?>
                            </p>
                            <?php if ($visit['special_requirements']): ?>
                                <p><strong>نیازهای خاص:</strong><br>
                                <?php echo nl2br(htmlspecialchars($visit['special_requirements'])); ?></p>
                            <?php endif; ?>
                            <?php if ($visit['notes']): ?>
                                <p><strong>یادداشت‌ها:</strong><br>
                                <?php echo nl2br(htmlspecialchars($visit['notes'])); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- تاریخ‌های پیشنهادی -->
                    <div class="col-md-6">
                        <div class="info-card">
                            <h5 class="mb-3">
                                <i class="fas fa-calendar me-2"></i>
                                تاریخ‌های پیشنهادی
                            </h5>
                            <?php 
                            $preferred_dates = json_decode($visit['preferred_dates'], true);
                            if ($preferred_dates && is_array($preferred_dates)):
                            ?>
                                <?php foreach ($preferred_dates as $date): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">
                                        <span><?php echo jalali_format($date['date'] . ' ' . $date['time'], 'Y/m/d H:i'); ?></span>
                                        <?php if ($date['date'] . ' ' . $date['time'] === $visit['confirmed_date']): ?>
                                            <span class="badge bg-success">تایید شده</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted">تاریخ پیشنهادی ثبت نشده</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- مدارک -->
                <div class="info-card">
                    <h5 class="mb-3">
                        <i class="fas fa-file-alt me-2"></i>
                        مدارک (<?php echo count($documents); ?> مورد)
                    </h5>
                    <?php if (empty($documents)): ?>
                        <p class="text-muted">مدرکی آپلود نشده</p>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($documents as $doc): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <h6 class="card-title"><?php echo htmlspecialchars($doc['document_name']); ?></h6>
                                            <p class="card-text">
                                                <small class="text-muted">
                                                    نوع: <?php echo $doc['document_type']; ?><br>
                                                    حجم: <?php echo formatFileSize($doc['file_size']); ?><br>
                                                    تاریخ: <?php echo jalali_format($doc['created_at'], 'Y/m/d H:i'); ?>
                                                </small>
                                            </p>
                                            <div class="d-flex gap-2">
                                                <a href="<?php echo $doc['file_path']; ?>" target="_blank" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-download me-1"></i>
                                                    دانلود
                                                </a>
                                                <?php if ($doc['is_verified']): ?>
                                                    <span class="badge bg-success">تایید شده</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">در انتظار تایید</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- دستگاه‌های رزرو شده -->
                <div class="info-card">
                    <h5 class="mb-3">
                        <i class="fas fa-cogs me-2"></i>
                        دستگاه‌های رزرو شده (<?php echo count($reservations); ?> مورد)
                    </h5>
                    <?php if (empty($reservations)): ?>
                        <p class="text-muted">دستگاهی رزرو نشده</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>دستگاه</th>
                                        <th>نوع</th>
                                        <th>از تاریخ</th>
                                        <th>تا تاریخ</th>
                                        <th>وضعیت</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reservations as $reservation): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($reservation['asset_name']); ?></td>
                                            <td><?php echo htmlspecialchars($reservation['asset_type']); ?></td>
                                            <td><?php echo jalali_format($reservation['reserved_from'], 'Y/m/d H:i'); ?></td>
                                            <td><?php echo jalali_format($reservation['reserved_to'], 'Y/m/d H:i'); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $reservation['status'] === 'reserved' ? 'warning' : 'success'; ?>">
                                                    <?php echo $reservation['status']; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- تاریخچه -->
                <div class="info-card">
                    <h5 class="mb-3">
                        <i class="fas fa-history me-2"></i>
                        تاریخچه تغییرات
                    </h5>
                    <?php if (empty($history)): ?>
                        <p class="text-muted">تاریخچه‌ای ثبت نشده</p>
                    <?php else: ?>
                        <?php foreach ($history as $item): ?>
                            <div class="history-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($item['action']); ?></h6>
                                        <?php if ($item['description']): ?>
                                            <p class="mb-1"><?php echo htmlspecialchars($item['description']); ?></p>
                                        <?php endif; ?>
                                        <?php if ($item['old_status'] && $item['new_status']): ?>
                                            <p class="mb-1">
                                                <span class="badge bg-secondary"><?php echo $item['old_status']; ?></span>
                                                <i class="fas fa-arrow-left mx-2"></i>
                                                <span class="badge bg-primary"><?php echo $item['new_status']; ?></span>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted">
                                            <?php echo $item['performed_by_name'] ?: 'سیستم'; ?><br>
                                            <?php echo jalali_format($item['created_at'], 'Y/m/d H:i'); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>