<?php
session_start();
require_once 'config.php';

// بررسی ورود کاربر
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// دریافت لیست دارایی‌ها
try {
    $stmt = $pdo->prepare("
        SELECT a.*, at.name as asset_type_name, at.display_name as asset_type_display 
        FROM assets a 
        LEFT JOIN asset_types at ON a.type_id = at.id 
        ORDER BY a.id DESC
    ");
    $stmt->execute();
    $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching assets: " . $e->getMessage());
    $assets = [];
}

// تابع کمکی برای خروجی امن HTML
function e($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لیست پروفایل دارایی‌ها - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    
    <style>
        html, body { 
            font-family: Vazirmatn, Tahoma, Arial, sans-serif; 
            background-color: #f8f9fa;
        }
        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid rgba(0, 0, 0, 0.125);
            transition: all 0.3s ease;
        }
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .card-header {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.125);
        }
        .badge {
            font-size: 0.8em;
            padding: 0.375rem 0.75rem;
        }
        .text-primary {
            color: #007bff !important;
        }
        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
        }
        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }
    </style>
</head>
<body style="padding-top: 80px;">
    <?php include 'navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-server"></i> لیست پروفایل دارایی‌ها</h2>
            <a href="assets.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> افزودن دارایی جدید
            </a>
        </div>

        <?php if (!empty($assets)): ?>
            <div class="row">
                <?php foreach ($assets as $asset): ?>
                    <?php
                    // تعیین نام نمایشی بر اساس نوع دارایی
                    $display_name = $asset['name'];
                    $asset_type_name = $asset['asset_type_name'] ?? '';
                    
                    if ($asset_type_name === 'generator' && !empty($asset['device_identifier'])) {
                        $display_name = $asset['device_identifier'];
                    } elseif ($asset_type_name === 'power_motor' && !empty($asset['serial_number'])) {
                        $display_name = $asset['serial_number'];
                    } elseif ($asset_type_name === 'consumable' && !empty($asset['device_identifier'])) {
                        $display_name = $asset['device_identifier'];
                    } elseif ($asset_type_name === 'parts' && !empty($asset['device_identifier'])) {
                        $display_name = $asset['device_identifier'];
                    }
                    
                    // تعیین رنگ badge بر اساس وضعیت
                    $status_class = 'secondary';
                    if ($asset['status'] === 'فعال' || $asset['status'] === 'آماده بهره‌برداری') {
                        $status_class = 'success';
                    } elseif ($asset['status'] === 'در حال تعمیر') {
                        $status_class = 'warning';
                    } elseif ($asset['status'] === 'غیرفعال') {
                        $status_class = 'danger';
                    }
                    ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="mb-0"><?= e($display_name) ?></h5>
                                <?php if ($asset_type_name === 'generator' && !empty($asset['device_identifier'])): ?>
                                    <small class="opacity-75">نام: <?= e($asset['name']) ?></small>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <div class="mb-2">
                                    <span class="badge bg-secondary"><?= e($asset['asset_type_display'] ?? $asset['asset_type_name'] ?? 'نامشخص') ?></span>
                                    <span class="badge bg-<?= $status_class ?>"><?= e($asset['status'] ?? 'نامشخص') ?></span>
                                </div>
                                
                                <div class="mb-2">
                                    <strong>برند:</strong> <?= e($asset['brand'] ?? '-') ?>
                                    <?php if ($asset['model']): ?>
                                        / <?= e($asset['model']) ?>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($asset['serial_number']): ?>
                                <div class="mb-2">
                                    <strong>سریال:</strong> <?= e($asset['serial_number']) ?>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($asset['device_identifier']): ?>
                                <div class="mb-2">
                                    <strong>شناسه دستگاه:</strong> <span class="text-primary fw-bold"><?= e($asset['device_identifier']) ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($asset['purchase_date']): ?>
                                <div class="mb-2">
                                    <strong>تاریخ خرید:</strong> <?= e($asset['purchase_date']) ?>
                                </div>
                                <?php endif; ?>
                                
                                <?php
                                // نمایش مشخصات بر اساس نوع دارایی
                                $specs = [];
                                if ($asset['power_capacity']) $specs[] = 'قدرت: ' . $asset['power_capacity'] . ' کیلووات';
                                if ($asset['engine_type']) $specs[] = 'نوع موتور: ' . $asset['engine_type'];
                                if ($asset['consumable_type']) $specs[] = 'نوع: ' . $asset['consumable_type'];
                                if ($asset['quantity'] > 0) $specs[] = 'تعداد: ' . $asset['quantity'];
                                if ($asset['location']) $specs[] = 'مکان: ' . $asset['location'];
                                
                                if (!empty($specs)): ?>
                                <div class="mb-2">
                                    <strong>مشخصات:</strong><br>
                                    <small><?= implode('<br>', $specs) ?></small>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer">
                                <a href="profile.php?id=<?= e($asset['id']) ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-eye"></i> مشاهده پروفایل
                                </a>
                                <a href="assets.php" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-edit"></i> ویرایش
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> فعلاً هیچ دارایی‌ای ثبت نشده است.
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>