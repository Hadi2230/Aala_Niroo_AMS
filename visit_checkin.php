<?php
session_start();
require_once 'config.php';

// بررسی احراز هویت
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$visit_id = null;
$visit = null;
$qr_code = $_GET['qr'] ?? '';

// جستجو بر اساس QR Code یا ID
try {
    if ($qr_code) {
        $stmt = $pdo->prepare("SELECT * FROM visit_requests WHERE qr_code = ?");
        $stmt->execute([$qr_code]);
        $visit = $stmt->fetch();
        if ($visit) {
            $visit_id = $visit['id'];
        }
    } else {
        $visit_id = (int)($_GET['id'] ?? 0);
        if ($visit_id) {
            $stmt = $pdo->prepare("SELECT * FROM visit_requests WHERE id = ?");
            $stmt->execute([$visit_id]);
            $visit = $stmt->fetch();
        }
    }
} catch (Exception $e) {
    // جدول وجود ندارد
    $visit = null;
    $visit_id = null;
}

// پردازش Check-in
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkin'])) {
    try {
        if (!$visit) {
            throw new Exception('درخواست بازدید یافت نشد');
        }
        
        if ($visit['status'] !== 'ready_for_visit') {
            throw new Exception('وضعیت درخواست برای Check-in مناسب نیست');
        }
        
        // Check-in
        if (checkInVisit($pdo, $visit['id'], $qr_code)) {
            $_SESSION['success_message'] = "Check-in با موفقیت انجام شد";
            header("Location: visit_checkin.php?id=" . $visit['id']);
            exit();
        } else {
            throw new Exception('خطا در انجام Check-in');
        }
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "خطا: " . $e->getMessage();
    }
}

// پردازش Check-out
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    try {
        if (!$visit) {
            throw new Exception('درخواست بازدید یافت نشد');
        }
        
        if ($visit['status'] !== 'onsite') {
            throw new Exception('وضعیت درخواست برای Check-out مناسب نیست');
        }
        
        // Check-out
        if (checkOutVisit($pdo, $visit['id'])) {
            $_SESSION['success_message'] = "Check-out با موفقیت انجام شد";
            header("Location: visit_checkin.php?id=" . $visit['id']);
            exit();
        } else {
            throw new Exception('خطا در انجام Check-out');
        }
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "خطا: " . $e->getMessage();
    }
}

// پردازش آپلود عکس
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_photo'])) {
    try {
        if (!$visit) {
            throw new Exception('درخواست بازدید یافت نشد');
        }
        
        $file = $_FILES['photo_file'];
        $photo_type = sanitizeInput($_POST['photo_type']);
        $caption = sanitizeInput($_POST['photo_caption']);
        
        uploadVisitPhoto($pdo, $visit['id'], $file, $photo_type, $caption);
        $_SESSION['success_message'] = "عکس با موفقیت آپلود شد";
        header("Location: visit_checkin.php?id=" . $visit['id']);
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "خطا: " . $e->getMessage();
    }
}

// پردازش تکمیل چک‌لیست
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_checklist'])) {
    try {
        $checklist_id = (int)$_POST['checklist_id'];
        $notes = sanitizeInput($_POST['checklist_notes']);
        
        completeChecklistItem($pdo, $checklist_id, $notes);
        $_SESSION['success_message'] = "آیتم چک‌لیست تکمیل شد";
        header("Location: visit_checkin.php?id=" . $visit['id']);
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "خطا: " . $e->getMessage();
    }
}

// دریافت چک‌لیست‌ها
$checklists = [];
if ($visit) {
    $stmt = $pdo->prepare("SELECT * FROM visit_checklists WHERE visit_request_id = ? ORDER BY checklist_type, created_at");
    $stmt->execute([$visit['id']]);
    $checklists = $stmt->fetchAll();
}

// دریافت عکس‌ها
$photos = [];
if ($visit) {
    $stmt = $pdo->prepare("SELECT * FROM visit_photos WHERE visit_request_id = ? ORDER BY taken_at DESC");
    $stmt->execute([$visit['id']]);
    $photos = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check-in بازدید - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <style>
        body { 
            font-family: Vazirmatn, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding-top: 20px;
        }
        .checkin-container { 
            max-width: 500px; 
            margin: 0 auto; 
            background: white; 
            border-radius: 20px; 
            box-shadow: 0 10px 30px rgba(0,0,0,.2);
            overflow: hidden;
        }
        .checkin-header { 
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%); 
            color: white; 
            padding: 30px; 
            text-align: center; 
        }
        .checkin-body { padding: 30px; }
        .qr-scanner { 
            background: #f8f9fa; 
            border-radius: 15px; 
            padding: 30px; 
            text-align: center; 
            margin-bottom: 30px;
        }
        .visit-info { 
            background: #f8f9fa; 
            border-radius: 15px; 
            padding: 20px; 
            margin-bottom: 20px; 
        }
        .status-badge { 
            padding: 8px 16px; 
            border-radius: 25px; 
            font-size: 0.9rem; 
            font-weight: bold; 
        }
        .status-ready_for_visit { background: #e8f5e8; color: #2e7d32; }
        .status-checked_in { background: #e3f2fd; color: #1976d2; }
        .status-onsite { background: #fff3e0; color: #f57c00; }
        .status-completed { background: #e8f5e8; color: #2e7d32; }
        .btn-checkin { 
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%); 
            border: none; 
            color: white; 
            padding: 15px 30px; 
            border-radius: 25px; 
            font-size: 1.1rem; 
            font-weight: bold; 
            width: 100%; 
            margin-bottom: 15px;
            transition: all .3s ease;
        }
        .btn-checkin:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 5px 15px rgba(0,0,0,.2); 
            color: white;
        }
        .btn-checkout { 
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); 
            border: none; 
            color: white; 
            padding: 15px 30px; 
            border-radius: 25px; 
            font-size: 1.1rem; 
            font-weight: bold; 
            width: 100%; 
            margin-bottom: 15px;
            transition: all .3s ease;
        }
        .btn-checkout:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 5px 15px rgba(0,0,0,.2); 
            color: white;
        }
        .checklist-item { 
            background: #f8f9fa; 
            border-radius: 10px; 
            padding: 15px; 
            margin-bottom: 10px; 
            border-left: 4px solid #e0e0e0;
        }
        .checklist-completed { 
            background: #e8f5e8; 
            border-left-color: #27ae60; 
        }
        .photo-gallery { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); 
            gap: 10px; 
            margin-top: 15px; 
        }
        .photo-item { 
            position: relative; 
            border-radius: 8px; 
            overflow: hidden; 
            box-shadow: 0 2px 8px rgba(0,0,0,.1); 
        }
        .photo-item img { 
            width: 100%; 
            height: 100px; 
            object-fit: cover; 
        }
        .form-control, .form-select { 
            border-radius: 10px; 
            border: 1px solid #ddd; 
            padding: 12px 15px; 
        }
        .form-control:focus, .form-select:focus { 
            border-color: #3498db; 
            box-shadow: 0 0 0 .25rem rgba(52,152,219,.25); 
        }
        .search-box { 
            background: white; 
            border-radius: 15px; 
            padding: 20px; 
            margin-bottom: 20px; 
            box-shadow: 0 2px 10px rgba(0,0,0,.1); 
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="checkin-container">
            <div class="checkin-header">
                <h2><i class="bi bi-qr-code-scan"></i> Check-in بازدید</h2>
                <p class="mb-0">سیستم مدیریت بازدید کارخانه</p>
            </div>
            
            <div class="checkin-body">
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="bi bi-check-circle-fill"></i>
                        <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="bi bi-exclamation-circle-fill"></i>
                        <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (!$visit): ?>
                    <!-- جستجو -->
                    <div class="search-box">
                        <h5><i class="bi bi-search"></i> جستجوی بازدید</h5>
                        <form method="GET" class="row g-3">
                            <div class="col-md-8">
                                <input type="text" name="qr" class="form-control" placeholder="QR Code یا شماره درخواست" value="<?php echo htmlspecialchars($qr_code); ?>">
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i> جستجو
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <div class="text-center py-5">
                        <i class="bi bi-qr-code display-1 text-muted"></i>
                        <p class="text-muted mt-3">QR Code را اسکن کنید یا شماره درخواست را وارد کنید</p>
                    </div>
                    
                <?php else: ?>
                    <!-- اطلاعات بازدید -->
                    <div class="visit-info">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h5><?php echo htmlspecialchars($visit['request_number']); ?></h5>
                                <p class="mb-1"><strong><?php echo htmlspecialchars($visit['company_name']); ?></strong></p>
                                <p class="mb-1 text-muted"><?php echo htmlspecialchars($visit['contact_person']); ?></p>
                                <p class="mb-0 text-muted"><?php echo htmlspecialchars($visit['contact_phone']); ?></p>
                            </div>
                            <div class="col-md-4 text-end">
                                <span class="status-badge status-<?php echo $visit['status']; ?>">
                                    <?php 
                                    $status_labels = [
                                        'ready_for_visit' => 'آماده بازدید',
                                        'checked_in' => 'وارد شده',
                                        'onsite' => 'در حال بازدید',
                                        'completed' => 'تکمیل شده'
                                    ];
                                    echo $status_labels[$visit['status']] ?? $visit['status'];
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- دکمه‌های عملیات -->
                    <?php if ($visit['status'] === 'ready_for_visit'): ?>
                        <form method="POST">
                            <input type="hidden" name="checkin" value="1">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="btn-checkin">
                                <i class="bi bi-box-arrow-in-right"></i> Check-in
                            </button>
                        </form>
                    <?php elseif ($visit['status'] === 'checked_in' || $visit['status'] === 'onsite'): ?>
                        <form method="POST">
                            <input type="hidden" name="checkout" value="1">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="btn-checkout">
                                <i class="bi bi-box-arrow-right"></i> Check-out
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <!-- چک‌لیست -->
                    <?php if (!empty($checklists)): ?>
                        <div class="mt-4">
                            <h6><i class="bi bi-list-check"></i> چک‌لیست</h6>
                            <?php foreach ($checklists as $item): ?>
                                <div class="checklist-item <?php echo $item['is_completed'] ? 'checklist-completed' : ''; ?>">
                                    <div class="form-check">
                                        <?php if ($item['is_completed']): ?>
                                            <input class="form-check-input" type="checkbox" checked disabled>
                                            <label class="form-check-label text-success">
                                                <i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($item['item_name']); ?>
                                            </label>
                                        <?php else: ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="complete_checklist" value="1">
                                                <input type="hidden" name="checklist_id" value="<?php echo $item['id']; ?>">
                                                <?php echo csrf_field(); ?>
                                                <div class="row align-items-center">
                                                    <div class="col-md-8">
                                                        <label class="form-check-label">
                                                            <?php echo htmlspecialchars($item['item_name']); ?>
                                                        </label>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <input type="text" name="checklist_notes" class="form-control form-control-sm" placeholder="یادداشت">
                                                        <button type="submit" class="btn btn-sm btn-success mt-1">
                                                            <i class="bi bi-check"></i> تکمیل
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- آپلود عکس -->
                    <div class="mt-4">
                        <h6><i class="bi bi-camera"></i> آپلود عکس</h6>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="upload_photo" value="1">
                            <?php echo csrf_field(); ?>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <select name="photo_type" class="form-select form-select-sm" required>
                                        <option value="check_in">Check-in</option>
                                        <option value="onsite">حین بازدید</option>
                                        <option value="equipment">تجهیزات</option>
                                        <option value="visitor">بازدیدکننده</option>
                                        <option value="signature">امضا</option>
                                        <option value="other">سایر</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <input type="text" name="photo_caption" class="form-control form-control-sm" placeholder="توضیح">
                                </div>
                                <div class="col-md-4">
                                    <input type="file" name="photo_file" class="form-control form-control-sm" accept=".jpg,.jpeg,.png" required>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm mt-2">
                                <i class="bi bi-upload"></i> آپلود
                            </button>
                        </form>
                    </div>
                    
                    <!-- گالری عکس‌ها -->
                    <?php if (!empty($photos)): ?>
                        <div class="mt-4">
                            <h6><i class="bi bi-images"></i> عکس‌های آپلود شده</h6>
                            <div class="photo-gallery">
                                <?php foreach ($photos as $photo): ?>
                                    <div class="photo-item">
                                        <img src="<?php echo $photo['file_path']; ?>" alt="<?php echo htmlspecialchars($photo['caption']); ?>">
                                        <div class="p-2">
                                            <small class="text-muted"><?php echo htmlspecialchars($photo['caption'] ?: 'بدون توضیح'); ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- اطلاعات زمان -->
                    <div class="mt-4">
                        <div class="row text-center">
                            <div class="col-md-6">
                                <small class="text-muted">تاریخ ایجاد</small>
                                <br>
                                <strong><?php echo jalali_format($visit['created_at']); ?></strong>
                            </div>
                            <?php if ($visit['check_in_time']): ?>
                                <div class="col-md-6">
                                    <small class="text-muted">زمان ورود</small>
                                    <br>
                                    <strong><?php echo jalali_format($visit['check_in_time']); ?></strong>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh هر 30 ثانیه
        setInterval(function() {
            if (window.location.search.includes('id=')) {
                window.location.reload();
            }
        }, 30000);
        
        // نمایش پیام‌ها
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>