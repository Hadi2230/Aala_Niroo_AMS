<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// ثبت لاگ
logAction($pdo, 'VIEW_CAR_MAINTENANCE', 'مشاهده صفحه مدیریت تعمیرات خودروها');

// افزودن تعمیر جدید
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_maintenance'])) {
    verifyCsrfToken();
    
    try {
        $pdo->beginTransaction();
        
        $data = [
            'car_id' => (int)$_POST['car_id'],
            'maintenance_type' => sanitizeInput($_POST['maintenance_type']),
            'maintenance_date' => $_POST['maintenance_date'],
            'maintenance_location' => sanitizeInput($_POST['maintenance_location']),
            'maintenance_provider' => sanitizeInput($_POST['maintenance_provider']),
            'maintenance_cost' => (float)$_POST['maintenance_cost'],
            'mileage_at_maintenance' => (int)$_POST['mileage_at_maintenance'],
            'description' => sanitizeInput($_POST['description']),
            'parts_replaced' => $_POST['parts_replaced'] ?? [],
            'labor_hours' => (float)$_POST['labor_hours'],
            'next_maintenance_date' => $_POST['next_maintenance_date'],
            'next_maintenance_mileage' => (int)$_POST['next_maintenance_mileage'],
            'maintenance_status' => sanitizeInput($_POST['maintenance_status']),
            'performed_by' => $_SESSION['user_id'],
            'approved_by' => $_POST['approved_by'] ? (int)$_POST['approved_by'] : null,
            'approval_date' => $_POST['approval_date'],
            'receipt_path' => '',
            'warranty_expiry' => $_POST['warranty_expiry'],
            'notes' => sanitizeInput($_POST['notes'])
        ];
        
        // آپلود فیش
        if (!empty($_FILES['receipt']['name'])) {
            try {
                $upload_dir = 'uploads/car_maintenance/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $data['receipt_path'] = uploadFile($_FILES['receipt'], $upload_dir);
            } catch (Exception $e) {
                error_log("خطا در آپلود فیش: " . $e->getMessage());
            }
        }
        
        $maintenance_id = addCarMaintenance($pdo, $data);
        
        $pdo->commit();
        
        $_SESSION['success'] = "تعمیر با موفقیت ثبت شد!";
        logAction($pdo, 'ADD_MAINTENANCE', "افزودن تعمیر جدید برای خودرو ID: " . $data['car_id']);
        
        header('Location: car_maintenance.php');
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "خطا در ثبت تعمیر: " . $e->getMessage();
        logAction($pdo, 'ADD_MAINTENANCE_ERROR', "خطا در ثبت تعمیر: " . $e->getMessage());
    }
}

// دریافت فیلترها
$car_filter = $_GET['car_id'] ?? '';
$type_filter = $_GET['maintenance_type'] ?? '';
$status_filter = $_GET['maintenance_status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// ساخت کوئری بر اساس فیلترها
$query = "SELECT cm.*, cc.car_plate, cc.car_name, cc.car_model, u1.full_name as performed_by_name, u2.full_name as approved_by_name 
          FROM car_maintenance cm 
          LEFT JOIN company_cars cc ON cm.car_id = cc.id 
          LEFT JOIN users u1 ON cm.performed_by = u1.id 
          LEFT JOIN users u2 ON cm.approved_by = u2.id 
          WHERE 1=1";
$params = [];

if ($car_filter) {
    $query .= " AND cm.car_id = ?";
    $params[] = $car_filter;
}
if ($type_filter) {
    $query .= " AND cm.maintenance_type = ?";
    $params[] = $type_filter;
}
if ($status_filter) {
    $query .= " AND cm.maintenance_status = ?";
    $params[] = $status_filter;
}
if ($date_from) {
    $query .= " AND cm.maintenance_date >= ?";
    $params[] = $date_from;
}
if ($date_to) {
    $query .= " AND cm.maintenance_date <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY cm.maintenance_date DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$maintenances = $stmt->fetchAll();

// دریافت لیست خودروها و کاربران
$cars = $pdo->query("SELECT id, car_plate, car_name, car_model, current_mileage FROM company_cars ORDER BY car_name")->fetchAll();
$users = $pdo->query("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name")->fetchAll();

// آمار
$total_maintenance = $pdo->query("SELECT COUNT(*) as total FROM car_maintenance")->fetch()['total'];
$total_cost = $pdo->query("SELECT SUM(maintenance_cost) as total FROM car_maintenance")->fetch()['total'] ?? 0;
$pending_maintenance = $pdo->query("SELECT COUNT(*) as total FROM car_maintenance WHERE maintenance_status = 'برنامه‌ریزی شده'")->fetch()['total'];
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت تعمیرات خودروها - اعلا نیرو</title>
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
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-wrench"></i> مدیریت تعمیرات خودروها</h2>
                    <div>
                        <span class="filter-active">
                            <i class="fas fa-filter"></i> 
                            <?= count($maintenances) ?> مورد
                        </span>
                    </div>
                </div>

                <!-- آمار کلی -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="stats-card">
                            <h3><?= $total_maintenance ?></h3>
                            <p><i class="fas fa-wrench"></i> کل تعمیرات</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <h3><?= number_format($total_cost) ?></h3>
                            <p><i class="fas fa-dollar-sign"></i> کل هزینه (ریال)</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <h3><?= $pending_maintenance ?></h3>
                            <p><i class="fas fa-clock"></i> تعمیرات در انتظار</p>
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
                                <select name="maintenance_type" class="form-select">
                                    <option value="">همه انواع</option>
                                    <option value="تعمیر دوره‌ای" <?= $type_filter == 'تعمیر دوره‌ای' ? 'selected' : '' ?>>تعمیر دوره‌ای</option>
                                    <option value="تعمیر اضطراری" <?= $type_filter == 'تعمیر اضطراری' ? 'selected' : '' ?>>تعمیر اضطراری</option>
                                    <option value="سرویس" <?= $type_filter == 'سرویس' ? 'selected' : '' ?>>سرویس</option>
                                    <option value="تعویض روغن" <?= $type_filter == 'تعویض روغن' ? 'selected' : '' ?>>تعویض روغن</option>
                                    <option value="تعویض لاستیک" <?= $type_filter == 'تعویض لاستیک' ? 'selected' : '' ?>>تعویض لاستیک</option>
                                    <option value="کالیبراسیون" <?= $type_filter == 'کالیبراسیون' ? 'selected' : '' ?>>کالیبراسیون</option>
                                    <option value="سایر" <?= $type_filter == 'سایر' ? 'selected' : '' ?>>سایر</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="maintenance_status" class="form-select">
                                    <option value="">همه وضعیت‌ها</option>
                                    <option value="برنامه‌ریزی شده" <?= $status_filter == 'برنامه‌ریزی شده' ? 'selected' : '' ?>>برنامه‌ریزی شده</option>
                                    <option value="در حال انجام" <?= $status_filter == 'در حال انجام' ? 'selected' : '' ?>>در حال انجام</option>
                                    <option value="تکمیل شده" <?= $status_filter == 'تکمیل شده' ? 'selected' : '' ?>>تکمیل شده</option>
                                    <option value="لغو شده" <?= $status_filter == 'لغو شده' ? 'selected' : '' ?>>لغو شده</option>
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

                <!-- فرم افزودن تعمیر -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-plus-circle"></i> ثبت تعمیر جدید</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="maintenanceForm" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            
                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="car_id" class="form-label">انتخاب خودرو *</label>
                                        <select class="form-select" id="car_id" name="car_id" required onchange="loadCarDetails()">
                                            <option value="">-- انتخاب خودرو --</option>
                                            <?php foreach ($cars as $car): ?>
                                                <option value="<?= $car['id'] ?>" data-mileage="<?= $car['current_mileage'] ?>">
                                                    <?= $car['car_plate'] ?> - <?= $car['car_name'] ?> (<?= $car['car_model'] ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="maintenance_type" class="form-label">نوع تعمیر *</label>
                                        <select class="form-select" id="maintenance_type" name="maintenance_type" required>
                                            <option value="">-- انتخاب کنید --</option>
                                            <option value="تعمیر دوره‌ای">تعمیر دوره‌ای</option>
                                            <option value="تعمیر اضطراری">تعمیر اضطراری</option>
                                            <option value="سرویس">سرویس</option>
                                            <option value="تعویض روغن">تعویض روغن</option>
                                            <option value="تعویض لاستیک">تعویض لاستیک</option>
                                            <option value="کالیبراسیون">کالیبراسیون</option>
                                            <option value="سایر">سایر</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="maintenance_date" class="form-label">تاریخ تعمیر *</label>
                                        <input type="datetime-local" class="form-control" id="maintenance_date" name="maintenance_date" required value="<?= date('Y-m-d\TH:i') ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="maintenance_status" class="form-label">وضعیت *</label>
                                        <select class="form-select" id="maintenance_status" name="maintenance_status" required>
                                            <option value="برنامه‌ریزی شده">برنامه‌ریزی شده</option>
                                            <option value="در حال انجام">در حال انجام</option>
                                            <option value="تکمیل شده">تکمیل شده</option>
                                            <option value="لغو شده">لغو شده</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="maintenance_location" class="form-label">مکان تعمیر</label>
                                        <input type="text" class="form-control" id="maintenance_location" name="maintenance_location">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="maintenance_provider" class="form-label">ارائه‌دهنده خدمات</label>
                                        <input type="text" class="form-control" id="maintenance_provider" name="maintenance_provider">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="maintenance_cost" class="form-label">هزینه تعمیر (ریال)</label>
                                        <input type="number" class="form-control" id="maintenance_cost" name="maintenance_cost" step="0.01">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="mileage_at_maintenance" class="form-label">کیلومتر در زمان تعمیر *</label>
                                        <input type="number" class="form-control" id="mileage_at_maintenance" name="mileage_at_maintenance" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="description" class="form-label">شرح تعمیر *</label>
                                        <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="parts_replaced" class="form-label">قطعات تعویض شده</label>
                                        <textarea class="form-control" id="parts_replaced" name="parts_replaced" rows="3" placeholder="هر قطعه در یک خط"></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="labor_hours" class="form-label">ساعات کار (ساعت)</label>
                                        <input type="number" class="form-control" id="labor_hours" name="labor_hours" step="0.01">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="next_maintenance_date" class="form-label">تاریخ تعمیر بعدی</label>
                                        <input type="date" class="form-control" id="next_maintenance_date" name="next_maintenance_date">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="next_maintenance_mileage" class="form-label">کیلومتر تعمیر بعدی</label>
                                        <input type="number" class="form-control" id="next_maintenance_mileage" name="next_maintenance_mileage">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="warranty_expiry" class="form-label">تاریخ انقضای گارانتی</label>
                                        <input type="date" class="form-control" id="warranty_expiry" name="warranty_expiry">
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="approved_by" class="form-label">تایید کننده</label>
                                        <select class="form-select" id="approved_by" name="approved_by">
                                            <option value="">-- انتخاب کنید --</option>
                                            <?php foreach ($users as $user): ?>
                                                <option value="<?= $user['id'] ?>"><?= $user['full_name'] ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="approval_date" class="form-label">تاریخ تایید</label>
                                        <input type="datetime-local" class="form-control" id="approval_date" name="approval_date">
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="receipt" class="form-label">فیش پرداخت</label>
                                        <input type="file" class="form-control" id="receipt" name="receipt" accept=".pdf,.jpg,.jpeg,.png">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="notes" class="form-label">توضیحات</label>
                                        <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" name="add_maintenance" class="btn btn-success">
                                <i class="fas fa-save"></i> ثبت تعمیر
                            </button>
                        </form>
                    </div>
                </div>

                <!-- لیست تعمیرات -->
                <div class="card mt-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-list"></i> لیست تعمیرات</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($maintenances) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>خودرو</th>
                                            <th>نوع تعمیر</th>
                                            <th>تاریخ</th>
                                            <th>ارائه‌دهنده</th>
                                            <th>هزینه</th>
                                            <th>وضعیت</th>
                                            <th>کیلومتر</th>
                                            <th>عملیات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($maintenances as $maintenance): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($maintenance['car_plate']) ?></strong><br>
                                                <small><?= htmlspecialchars($maintenance['car_name']) ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?= $maintenance['maintenance_type'] ?></span>
                                            </td>
                                            <td><?= jalali_format($maintenance['maintenance_date'], 'Y/m/d H:i') ?></td>
                                            <td><?= htmlspecialchars($maintenance['maintenance_provider']) ?: '--' ?></td>
                                            <td>
                                                <?php if ($maintenance['maintenance_cost'] > 0): ?>
                                                    <span class="cost-highlight"><?= number_format($maintenance['maintenance_cost']) ?> ریال</span>
                                                <?php else: ?>
                                                    --
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $status_class = 'secondary';
                                                if ($maintenance['maintenance_status'] == 'تکمیل شده') $status_class = 'success';
                                                if ($maintenance['maintenance_status'] == 'در حال انجام') $status_class = 'warning';
                                                if ($maintenance['maintenance_status'] == 'لغو شده') $status_class = 'danger';
                                                ?>
                                                <span class="badge bg-<?= $status_class ?> badge-status"><?= $maintenance['maintenance_status'] ?></span>
                                            </td>
                                            <td><?= number_format($maintenance['mileage_at_maintenance']) ?></td>
                                            <td class="action-buttons">
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#detailsModal<?= $maintenance['id'] ?>" title="جزئیات">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($maintenance['receipt_path']): ?>
                                                <a href="<?= $maintenance['receipt_path'] ?>" class="btn btn-sm btn-secondary" target="_blank" title="مشاهده فیش">
                                                    <i class="fas fa-file-pdf"></i>
                                                </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info text-center">
                                <i class="fas fa-info-circle"></i> هیچ تعمیری یافت نشد.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal های جزئیات -->
    <?php foreach ($maintenances as $maintenance): ?>
    <div class="modal fade" id="detailsModal<?= $maintenance['id'] ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">جزئیات تعمیر #<?= $maintenance['id'] ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>خودرو:</strong> <?= $maintenance['car_plate'] ?> - <?= $maintenance['car_name'] ?></p>
                            <p><strong>نوع تعمیر:</strong> <?= $maintenance['maintenance_type'] ?></p>
                            <p><strong>تاریخ:</strong> <?= jalali_format($maintenance['maintenance_date'], 'Y/m/d H:i') ?></p>
                            <p><strong>مکان:</strong> <?= $maintenance['maintenance_location'] ?: '--' ?></p>
                            <p><strong>ارائه‌دهنده:</strong> <?= $maintenance['maintenance_provider'] ?: '--' ?></p>
                            <p><strong>هزینه:</strong> <?= $maintenance['maintenance_cost'] > 0 ? number_format($maintenance['maintenance_cost']) . ' ریال' : '--' ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>وضعیت:</strong> <?= $maintenance['maintenance_status'] ?></p>
                            <p><strong>کیلومتر:</strong> <?= number_format($maintenance['mileage_at_maintenance']) ?></p>
                            <p><strong>ساعات کار:</strong> <?= $maintenance['labor_hours'] ?: '--' ?></p>
                            <p><strong>تعمیر بعدی:</strong> <?= $maintenance['next_maintenance_date'] ? jalali_format($maintenance['next_maintenance_date'], 'Y/m/d') : '--' ?></p>
                            <p><strong>کیلومتر تعمیر بعدی:</strong> <?= $maintenance['next_maintenance_mileage'] ? number_format($maintenance['next_maintenance_mileage']) : '--' ?></p>
                            <p><strong>انقضای گارانتی:</strong> <?= $maintenance['warranty_expiry'] ? jalali_format($maintenance['warranty_expiry'], 'Y/m/d') : '--' ?></p>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-12">
                            <p><strong>شرح تعمیر:</strong></p>
                            <p><?= nl2br(htmlspecialchars($maintenance['description'])) ?></p>
                            
                            <?php if ($maintenance['parts_replaced']): ?>
                            <p><strong>قطعات تعویض شده:</strong></p>
                            <p><?= nl2br(htmlspecialchars($maintenance['parts_replaced'])) ?></p>
                            <?php endif; ?>
                            
                            <?php if ($maintenance['notes']): ?>
                            <p><strong>توضیحات:</strong></p>
                            <p><?= nl2br(htmlspecialchars($maintenance['notes'])) ?></p>
                            <?php endif; ?>
                            
                            <?php if ($maintenance['performed_by_name']): ?>
                            <p><strong>انجام دهنده:</strong> <?= $maintenance['performed_by_name'] ?></p>
                            <?php endif; ?>
                            
                            <?php if ($maintenance['approved_by_name']): ?>
                            <p><strong>تایید کننده:</strong> <?= $maintenance['approved_by_name'] ?></p>
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
    function loadCarDetails() {
        const carSelect = document.getElementById('car_id');
        const selectedOption = carSelect.options[carSelect.selectedIndex];
        
        if (carSelect.value) {
            const mileage = selectedOption.getAttribute('data-mileage');
            document.getElementById('mileage_at_maintenance').value = mileage;
        }
    }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>