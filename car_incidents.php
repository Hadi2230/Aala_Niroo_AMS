<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// ثبت لاگ
logAction($pdo, 'VIEW_CAR_INCIDENTS', 'مشاهده صفحه مدیریت حوادث خودروها');

// گزارش حادثه جدید
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['report_incident'])) {
    verifyCsrfToken();
    
    try {
        $pdo->beginTransaction();
        
        $data = [
            'car_id' => (int)$_POST['car_id'],
            'incident_date' => $_POST['incident_date'],
            'incident_type' => sanitizeInput($_POST['incident_type']),
            'incident_location' => sanitizeInput($_POST['incident_location']),
            'incident_description' => sanitizeInput($_POST['incident_description']),
            'damage_description' => sanitizeInput($_POST['damage_description']),
            'estimated_damage_cost' => (float)$_POST['estimated_damage_cost'],
            'actual_damage_cost' => (float)$_POST['actual_damage_cost'],
            'insurance_claim_number' => sanitizeInput($_POST['insurance_claim_number']),
            'insurance_claim_status' => sanitizeInput($_POST['insurance_claim_status']),
            'police_report_number' => sanitizeInput($_POST['police_report_number']),
            'involved_parties' => sanitizeInput($_POST['involved_parties']),
            'witness_names' => sanitizeInput($_POST['witness_names']),
            'witness_contacts' => sanitizeInput($_POST['witness_contacts']),
            'photos' => [],
            'documents' => [],
            'incident_status' => 'گزارش شده',
            'reported_by' => $_SESSION['user_id'],
            'assigned_to' => $_POST['assigned_to'] ? (int)$_POST['assigned_to'] : null,
            'resolution_date' => null,
            'resolution_notes' => sanitizeInput($_POST['resolution_notes'])
        ];
        
        // آپلود عکس‌ها
        if (!empty($_FILES['photos']['name'][0])) {
            $upload_dir = 'uploads/car_incidents/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $photos = [];
            foreach ($_FILES['photos']['name'] as $key => $name) {
                if (!empty($name)) {
                    $file = [
                        'name' => $name,
                        'type' => $_FILES['photos']['type'][$key],
                        'tmp_name' => $_FILES['photos']['tmp_name'][$key],
                        'error' => $_FILES['photos']['error'][$key],
                        'size' => $_FILES['photos']['size'][$key]
                    ];
                    
                    try {
                        $photo_path = uploadFile($file, $upload_dir);
                        $photos[] = $photo_path;
                    } catch (Exception $e) {
                        error_log("خطا در آپلود عکس: " . $e->getMessage());
                    }
                }
            }
            $data['photos'] = $photos;
        }
        
        // آپلود مدارک
        if (!empty($_FILES['documents']['name'][0])) {
            $upload_dir = 'uploads/car_incidents/documents/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $documents = [];
            foreach ($_FILES['documents']['name'] as $key => $name) {
                if (!empty($name)) {
                    $file = [
                        'name' => $name,
                        'type' => $_FILES['documents']['type'][$key],
                        'tmp_name' => $_FILES['documents']['tmp_name'][$key],
                        'error' => $_FILES['documents']['error'][$key],
                        'size' => $_FILES['documents']['size'][$key]
                    ];
                    
                    try {
                        $doc_path = uploadFile($file, $upload_dir);
                        $documents[] = $doc_path;
                    } catch (Exception $e) {
                        error_log("خطا در آپلود مدرک: " . $e->getMessage());
                    }
                }
            }
            $data['documents'] = $documents;
        }
        
        $incident_id = reportCarIncident($pdo, $data);
        
        $pdo->commit();
        
        $_SESSION['success'] = "گزارش حادثه با موفقیت ثبت شد!";
        logAction($pdo, 'REPORT_INCIDENT', "گزارش حادثه جدید برای خودرو ID: " . $data['car_id']);
        
        header('Location: car_incidents.php');
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "خطا در ثبت حادثه: " . $e->getMessage();
        logAction($pdo, 'REPORT_INCIDENT_ERROR', "خطا در ثبت حادثه: " . $e->getMessage());
    }
}

// دریافت فیلترها
$car_filter = $_GET['car_id'] ?? '';
$type_filter = $_GET['incident_type'] ?? '';
$status_filter = $_GET['incident_status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// ساخت کوئری بر اساس فیلترها
$query = "SELECT ci.*, cc.car_plate, cc.car_name, cc.car_model, u1.full_name as reported_by_name, u2.full_name as assigned_to_name 
          FROM car_incidents ci 
          LEFT JOIN company_cars cc ON ci.car_id = cc.id 
          LEFT JOIN users u1 ON ci.reported_by = u1.id 
          LEFT JOIN users u2 ON ci.assigned_to = u2.id 
          WHERE 1=1";
$params = [];

if ($car_filter) {
    $query .= " AND ci.car_id = ?";
    $params[] = $car_filter;
}
if ($type_filter) {
    $query .= " AND ci.incident_type = ?";
    $params[] = $type_filter;
}
if ($status_filter) {
    $query .= " AND ci.incident_status = ?";
    $params[] = $status_filter;
}
if ($date_from) {
    $query .= " AND ci.incident_date >= ?";
    $params[] = $date_from;
}
if ($date_to) {
    $query .= " AND ci.incident_date <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY ci.incident_date DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$incidents = $stmt->fetchAll();

// دریافت لیست خودروها و کاربران
$cars = $pdo->query("SELECT id, car_plate, car_name, car_model FROM company_cars ORDER BY car_name")->fetchAll();
$users = $pdo->query("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name")->fetchAll();

// آمار
$total_incidents = $pdo->query("SELECT COUNT(*) as total FROM car_incidents")->fetch()['total'];
$total_damage_cost = $pdo->query("SELECT SUM(actual_damage_cost) as total FROM car_incidents WHERE actual_damage_cost > 0")->fetch()['total'] ?? 0;
$pending_incidents = $pdo->query("SELECT COUNT(*) as total FROM car_incidents WHERE incident_status = 'گزارش شده'")->fetch()['total'];
$resolved_incidents = $pdo->query("SELECT COUNT(*) as total FROM car_incidents WHERE incident_status = 'تکمیل شده'")->fetch()['total'];
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت حوادث خودروها - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .card-header {
            border-radius: 10px 10px 0 0 !important;
            font-weight: 600;
        }
        .search-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .stats-card h3 {
            font-size: 2.5rem;
            font-weight: bold;
            margin: 0;
        }
        .stats-card p {
            margin: 5px 0 0 0;
            opacity: 0.9;
        }
        .badge-status {
            font-size: 0.9rem;
            padding: 0.5em 0.8em;
        }
        .table th {
            font-weight: 600;
            background-color: #f8f9fa;
        }
        .action-buttons .btn {
            margin-left: 5px;
        }
        .filter-active {
            background-color: #e9ecef;
            border-radius: 5px;
            padding: 5px 10px;
            font-weight: 600;
        }
        .cost-highlight {
            font-weight: bold;
            color: #dc3545;
        }
        .file-upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
        }
        .file-upload-area:hover {
            border-color: #007bff;
            background-color: #f8f9fa;
        }
        .file-upload-area.dragover {
            border-color: #007bff;
            background-color: #e3f2fd;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-exclamation-triangle"></i> مدیریت حوادث خودروها</h2>
                    <div>
                        <span class="filter-active">
                            <i class="fas fa-filter"></i> 
                            <?= count($incidents) ?> مورد
                        </span>
                    </div>
                </div>

                <!-- آمار کلی -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h3><?= $total_incidents ?></h3>
                            <p><i class="fas fa-exclamation-triangle"></i> کل حوادث</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <h3><?= number_format($total_damage_cost) ?></h3>
                            <p><i class="fas fa-dollar-sign"></i> کل خسارت (ریال)</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <h3><?= $pending_incidents ?></h3>
                            <p><i class="fas fa-clock"></i> حوادث در انتظار</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                            <h3><?= $resolved_incidents ?></h3>
                            <p><i class="fas fa-check-circle"></i> حوادث حل شده</p>
                        </div>
                    </div>
                </div>

                <!-- جستجو و فیلتر -->
                <div class="card search-box">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-2">
                                <select name="car_id" class="form-select">
                                    <option value="">همه خودروها</option>
                                    <?php foreach ($cars as $car): ?>
                                        <option value="<?= $car['id'] ?>" <?= $car_filter == $car['id'] ? 'selected' : '' ?>>
                                            <?= $car['car_plate'] ?> - <?= $car['car_name'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="incident_type" class="form-select">
                                    <option value="">همه انواع</option>
                                    <option value="تصادف" <?= $type_filter == 'تصادف' ? 'selected' : '' ?>>تصادف</option>
                                    <option value="خرابی" <?= $type_filter == 'خرابی' ? 'selected' : '' ?>>خرابی</option>
                                    <option value="دزدی" <?= $type_filter == 'دزدی' ? 'selected' : '' ?>>دزدی</option>
                                    <option value="آتش‌سوزی" <?= $type_filter == 'آتش‌سوزی' ? 'selected' : '' ?>>آتش‌سوزی</option>
                                    <option value="طبیعی" <?= $type_filter == 'طبیعی' ? 'selected' : '' ?>>طبیعی</option>
                                    <option value="سایر" <?= $type_filter == 'سایر' ? 'selected' : '' ?>>سایر</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="incident_status" class="form-select">
                                    <option value="">همه وضعیت‌ها</option>
                                    <option value="گزارش شده" <?= $status_filter == 'گزارش شده' ? 'selected' : '' ?>>گزارش شده</option>
                                    <option value="در حال بررسی" <?= $status_filter == 'در حال بررسی' ? 'selected' : '' ?>>در حال بررسی</option>
                                    <option value="تکمیل شده" <?= $status_filter == 'تکمیل شده' ? 'selected' : '' ?>>تکمیل شده</option>
                                    <option value="بسته شده" <?= $status_filter == 'بسته شده' ? 'selected' : '' ?>>بسته شده</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <input type="date" name="date_from" class="form-control" placeholder="از تاریخ" value="<?= $date_from ?>">
                            </div>
                            <div class="col-md-2">
                                <input type="date" name="date_to" class="form-control" placeholder="تا تاریخ" value="<?= $date_to ?>">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-filter"></i> اعمال فیلتر
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- نمایش پیام‌ها -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= $_SESSION['success'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= $_SESSION['error'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <!-- فرم گزارش حادثه -->
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="fas fa-plus-circle"></i> گزارش حادثه جدید</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="incidentForm" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            
                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="car_id" class="form-label">انتخاب خودرو *</label>
                                        <select class="form-select" id="car_id" name="car_id" required>
                                            <option value="">-- انتخاب خودرو --</option>
                                            <?php foreach ($cars as $car): ?>
                                                <option value="<?= $car['id'] ?>">
                                                    <?= $car['car_plate'] ?> - <?= $car['car_name'] ?> (<?= $car['car_model'] ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="incident_type" class="form-label">نوع حادثه *</label>
                                        <select class="form-select" id="incident_type" name="incident_type" required>
                                            <option value="">-- انتخاب کنید --</option>
                                            <option value="تصادف">تصادف</option>
                                            <option value="خرابی">خرابی</option>
                                            <option value="دزدی">دزدی</option>
                                            <option value="آتش‌سوزی">آتش‌سوزی</option>
                                            <option value="طبیعی">طبیعی</option>
                                            <option value="سایر">سایر</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="incident_date" class="form-label">تاریخ حادثه *</label>
                                        <input type="datetime-local" class="form-control" id="incident_date" name="incident_date" required value="<?= date('Y-m-d\TH:i') ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="assigned_to" class="form-label">واگذار شده به</label>
                                        <select class="form-select" id="assigned_to" name="assigned_to">
                                            <option value="">-- انتخاب کنید --</option>
                                            <?php foreach ($users as $user): ?>
                                                <option value="<?= $user['id'] ?>"><?= $user['full_name'] ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="incident_location" class="form-label">مکان حادثه *</label>
                                        <input type="text" class="form-control" id="incident_location" name="incident_location" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="estimated_damage_cost" class="form-label">هزینه خسارت تخمینی (ریال)</label>
                                        <input type="number" class="form-control" id="estimated_damage_cost" name="estimated_damage_cost" step="0.01">
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="incident_description" class="form-label">شرح حادثه *</label>
                                        <textarea class="form-control" id="incident_description" name="incident_description" rows="3" required></textarea>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="damage_description" class="form-label">شرح خسارات</label>
                                        <textarea class="form-control" id="damage_description" name="damage_description" rows="3"></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="insurance_claim_number" class="form-label">شماره بیمه</label>
                                        <input type="text" class="form-control" id="insurance_claim_number" name="insurance_claim_number">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="insurance_claim_status" class="form-label">وضعیت بیمه</label>
                                        <select class="form-select" id="insurance_claim_status" name="insurance_claim_status">
                                            <option value="در انتظار">در انتظار</option>
                                            <option value="تایید شده">تایید شده</option>
                                            <option value="رد شده">رد شده</option>
                                            <option value="تسویه شده">تسویه شده</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="police_report_number" class="form-label">شماره گزارش پلیس</label>
                                        <input type="text" class="form-control" id="police_report_number" name="police_report_number">
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="involved_parties" class="form-label">طرف‌های درگیر</label>
                                        <textarea class="form-control" id="involved_parties" name="involved_parties" rows="2"></textarea>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="witness_names" class="form-label">نام شاهدان</label>
                                        <textarea class="form-control" id="witness_names" name="witness_names" rows="2"></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="witness_contacts" class="form-label">اطلاعات تماس شاهدان</label>
                                <textarea class="form-control" id="witness_contacts" name="witness_contacts" rows="2"></textarea>
                            </div>

                            <!-- آپلود عکس‌ها -->
                            <div class="mb-4">
                                <label class="form-label">عکس‌های حادثه</label>
                                <div class="file-upload-area" id="photoUploadArea">
                                    <i class="fas fa-camera fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">عکس‌ها را اینجا بکشید یا کلیک کنید</p>
                                    <input type="file" class="form-control" id="photos" name="photos[]" multiple accept="image/*" style="display: none;">
                                </div>
                                <div id="photoPreview" class="mt-3"></div>
                            </div>

                            <!-- آپلود مدارک -->
                            <div class="mb-4">
                                <label class="form-label">مدارک حادثه</label>
                                <div class="file-upload-area" id="documentUploadArea">
                                    <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">مدارک را اینجا بکشید یا کلیک کنید</p>
                                    <input type="file" class="form-control" id="documents" name="documents[]" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" style="display: none;">
                                </div>
                                <div id="documentPreview" class="mt-3"></div>
                            </div>

                            <div class="mb-3">
                                <label for="resolution_notes" class="form-label">توضیحات تکمیلی</label>
                                <textarea class="form-control" id="resolution_notes" name="resolution_notes" rows="3"></textarea>
                            </div>

                            <button type="submit" name="report_incident" class="btn btn-danger">
                                <i class="fas fa-exclamation-triangle"></i> گزارش حادثه
                            </button>
                        </form>
                    </div>
                </div>

                <!-- لیست حوادث -->
                <div class="card mt-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-list"></i> لیست حوادث</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($incidents) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>خودرو</th>
                                            <th>نوع حادثه</th>
                                            <th>تاریخ</th>
                                            <th>مکان</th>
                                            <th>خسارت</th>
                                            <th>وضعیت</th>
                                            <th>گزارش‌دهنده</th>
                                            <th>عملیات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($incidents as $incident): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($incident['car_plate']) ?></strong><br>
                                                <small><?= htmlspecialchars($incident['car_name']) ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?= $incident['incident_type'] ?></span>
                                            </td>
                                            <td><?= jalali_format($incident['incident_date'], 'Y/m/d H:i') ?></td>
                                            <td><?= htmlspecialchars($incident['incident_location']) ?></td>
                                            <td>
                                                <?php if ($incident['actual_damage_cost'] > 0): ?>
                                                    <span class="cost-highlight"><?= number_format($incident['actual_damage_cost']) ?> ریال</span>
                                                <?php elseif ($incident['estimated_damage_cost'] > 0): ?>
                                                    <span class="text-warning"><?= number_format($incident['estimated_damage_cost']) ?> ریال (تخمینی)</span>
                                                <?php else: ?>
                                                    --
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $status_class = 'secondary';
                                                if ($incident['incident_status'] == 'تکمیل شده') $status_class = 'success';
                                                if ($incident['incident_status'] == 'در حال بررسی') $status_class = 'warning';
                                                if ($incident['incident_status'] == 'بسته شده') $status_class = 'dark';
                                                ?>
                                                <span class="badge bg-<?= $status_class ?> badge-status"><?= $incident['incident_status'] ?></span>
                                            </td>
                                            <td><?= $incident['reported_by_name'] ?: '--' ?></td>
                                            <td class="action-buttons">
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#detailsModal<?= $incident['id'] ?>" title="جزئیات">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($incident['photos']): ?>
                                                <button type="button" class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#photosModal<?= $incident['id'] ?>" title="عکس‌ها">
                                                    <i class="fas fa-images"></i>
                                                </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info text-center">
                                <i class="fas fa-info-circle"></i> هیچ حادثه‌ای یافت نشد.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal های جزئیات -->
    <?php foreach ($incidents as $incident): ?>
    <div class="modal fade" id="detailsModal<?= $incident['id'] ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">جزئیات حادثه #<?= $incident['id'] ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>خودرو:</strong> <?= $incident['car_plate'] ?> - <?= $incident['car_name'] ?></p>
                            <p><strong>نوع حادثه:</strong> <?= $incident['incident_type'] ?></p>
                            <p><strong>تاریخ:</strong> <?= jalali_format($incident['incident_date'], 'Y/m/d H:i') ?></p>
                            <p><strong>مکان:</strong> <?= $incident['incident_location'] ?></p>
                            <p><strong>خسارت تخمینی:</strong> <?= $incident['estimated_damage_cost'] > 0 ? number_format($incident['estimated_damage_cost']) . ' ریال' : '--' ?></p>
                            <p><strong>خسارت واقعی:</strong> <?= $incident['actual_damage_cost'] > 0 ? number_format($incident['actual_damage_cost']) . ' ریال' : '--' ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>وضعیت:</strong> <?= $incident['incident_status'] ?></p>
                            <p><strong>شماره بیمه:</strong> <?= $incident['insurance_claim_number'] ?: '--' ?></p>
                            <p><strong>وضعیت بیمه:</strong> <?= $incident['insurance_claim_status'] ?: '--' ?></p>
                            <p><strong>شماره پلیس:</strong> <?= $incident['police_report_number'] ?: '--' ?></p>
                            <p><strong>گزارش‌دهنده:</strong> <?= $incident['reported_by_name'] ?: '--' ?></p>
                            <p><strong>واگذار شده به:</strong> <?= $incident['assigned_to_name'] ?: '--' ?></p>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-12">
                            <p><strong>شرح حادثه:</strong></p>
                            <p><?= nl2br(htmlspecialchars($incident['incident_description'])) ?></p>
                            
                            <?php if ($incident['damage_description']): ?>
                            <p><strong>شرح خسارات:</strong></p>
                            <p><?= nl2br(htmlspecialchars($incident['damage_description'])) ?></p>
                            <?php endif; ?>
                            
                            <?php if ($incident['involved_parties']): ?>
                            <p><strong>طرف‌های درگیر:</strong></p>
                            <p><?= nl2br(htmlspecialchars($incident['involved_parties'])) ?></p>
                            <?php endif; ?>
                            
                            <?php if ($incident['witness_names']): ?>
                            <p><strong>شاهدان:</strong></p>
                            <p><?= nl2br(htmlspecialchars($incident['witness_names'])) ?></p>
                            <?php endif; ?>
                            
                            <?php if ($incident['witness_contacts']): ?>
                            <p><strong>اطلاعات تماس شاهدان:</strong></p>
                            <p><?= nl2br(htmlspecialchars($incident['witness_contacts'])) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <script>
    // مدیریت آپلود فایل‌ها
    function setupFileUpload(areaId, inputId, previewId) {
        const area = document.getElementById(areaId);
        const input = document.getElementById(inputId);
        const preview = document.getElementById(previewId);
        
        area.addEventListener('click', () => input.click());
        area.addEventListener('dragover', (e) => {
            e.preventDefault();
            area.classList.add('dragover');
        });
        area.addEventListener('dragleave', () => {
            area.classList.remove('dragover');
        });
        area.addEventListener('drop', (e) => {
            e.preventDefault();
            area.classList.remove('dragover');
            input.files = e.dataTransfer.files;
            showPreview(input, preview);
        });
        
        input.addEventListener('change', () => showPreview(input, preview));
    }
    
    function showPreview(input, preview) {
        preview.innerHTML = '';
        Array.from(input.files).forEach((file, index) => {
            const div = document.createElement('div');
            div.className = 'd-inline-block me-2 mb-2';
            
            if (file.type.startsWith('image/')) {
                const img = document.createElement('img');
                img.src = URL.createObjectURL(file);
                img.style.width = '100px';
                img.style.height = '100px';
                img.style.objectFit = 'cover';
                img.style.borderRadius = '5px';
                div.appendChild(img);
            } else {
                const icon = document.createElement('i');
                icon.className = 'fas fa-file fa-2x text-muted';
                div.appendChild(icon);
                div.innerHTML += '<br><small>' + file.name + '</small>';
            }
            
            preview.appendChild(div);
        });
    }
    
    // راه‌اندازی آپلود فایل‌ها
    setupFileUpload('photoUploadArea', 'photos', 'photoPreview');
    setupFileUpload('documentUploadArea', 'documents', 'documentPreview');
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>