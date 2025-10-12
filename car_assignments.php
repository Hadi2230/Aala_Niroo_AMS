<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// ثبت لاگ
logAction($pdo, 'VIEW_CAR_ASSIGNMENTS', 'مشاهده صفحه مدیریت انتساب خودروها');

// انتساب خودرو
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_car'])) {
    verifyCsrfToken();
    
    try {
        $pdo->beginTransaction();
        
        $data = [
            'car_id' => (int)$_POST['car_id'],
            'assigned_to' => (int)$_POST['assigned_to'],
            'assignment_type' => sanitizeInput($_POST['assignment_type']),
            'assignment_date' => $_POST['assignment_date'],
            'assignment_reason' => sanitizeInput($_POST['assignment_reason']),
            'assignment_location' => sanitizeInput($_POST['assignment_location']),
            'destination' => sanitizeInput($_POST['destination']),
            'expected_return_date' => $_POST['expected_return_date'],
            'mileage_at_assignment' => (int)$_POST['mileage_at_assignment'],
            'fuel_level_at_assignment' => sanitizeInput($_POST['fuel_level_at_assignment']),
            'car_condition_at_assignment' => sanitizeInput($_POST['car_condition_at_assignment']),
            'accessories_included' => $_POST['accessories_included'] ?? [],
            'assigned_by' => $_SESSION['user_id'],
            'notes' => sanitizeInput($_POST['notes'])
        ];
        
        $assignment_id = assignCar($pdo, $data);
        
        // به‌روزرسانی وضعیت خودرو
        $stmt = $pdo->prepare("UPDATE company_cars SET status = 'تحویل داده شده', current_mileage = ? WHERE id = ?");
        $stmt->execute([$data['mileage_at_assignment'], $data['car_id']]);
        
        $pdo->commit();
        
        $_SESSION['success'] = "خودرو با موفقیت انتساب شد!";
        logAction($pdo, 'ASSIGN_CAR', "انتساب خودرو ID: " . $data['car_id'] . " به کاربر ID: " . $data['assigned_to']);
        
        header('Location: car_assignments.php');
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "خطا در انتساب خودرو: " . $e->getMessage();
        logAction($pdo, 'ASSIGN_CAR_ERROR', "خطا در انتساب خودرو: " . $e->getMessage());
    }
}

// برگشت خودرو
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['return_car'])) {
    verifyCsrfToken();
    
    try {
        $pdo->beginTransaction();
        
        $assignment_id = (int)$_POST['assignment_id'];
        $data = [
            'return_date' => $_POST['return_date'],
            'mileage_at_return' => (int)$_POST['mileage_at_return'],
            'fuel_level_at_return' => sanitizeInput($_POST['fuel_level_at_return']),
            'car_condition_at_return' => sanitizeInput($_POST['car_condition_at_return']),
            'accessories_returned' => $_POST['accessories_returned'] ?? [],
            'notes' => sanitizeInput($_POST['return_notes'])
        ];
        
        returnCar($pdo, $assignment_id, $data);
        
        // به‌روزرسانی وضعیت خودرو
        $stmt = $pdo->prepare("UPDATE company_cars SET status = 'فعال', current_mileage = ? WHERE id = (SELECT car_id FROM car_assignments WHERE id = ?)");
        $stmt->execute([$data['mileage_at_return'], $assignment_id]);
        
        $pdo->commit();
        
        $_SESSION['success'] = "خودرو با موفقیت برگشت داده شد!";
        logAction($pdo, 'RETURN_CAR', "برگشت خودرو - انتساب ID: " . $assignment_id);
        
        header('Location: car_assignments.php');
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "خطا در برگشت خودرو: " . $e->getMessage();
        logAction($pdo, 'RETURN_CAR_ERROR', "خطا در برگشت خودرو: " . $e->getMessage());
    }
}

// دریافت فیلترها
$car_filter = $_GET['car_id'] ?? '';
$user_filter = $_GET['assigned_to'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// دریافت لیست انتساب‌ها
$filters = [];
if ($car_filter) $filters['car_id'] = $car_filter;
if ($user_filter) $filters['assigned_to'] = $user_filter;
if ($status_filter) $filters['status'] = $status_filter;
if ($date_from) $filters['date_from'] = $date_from;
if ($date_to) $filters['date_to'] = $date_to;

$assignments = getCarAssignments($pdo, $filters);

// دریافت لیست خودروها و کاربران
$cars = $pdo->query("SELECT id, car_plate, car_name, car_model, current_mileage, status FROM company_cars WHERE status IN ('فعال', 'آماده تحویل') ORDER BY car_name")->fetchAll();
$users = $pdo->query("SELECT id, full_name, username FROM users WHERE is_active = 1 ORDER BY full_name")->fetchAll();

// آمار
$total_assignments = $pdo->query("SELECT COUNT(*) as total FROM car_assignments")->fetch()['total'];
$active_assignments = $pdo->query("SELECT COUNT(*) as total FROM car_assignments WHERE assignment_status = 'فعال'")->fetch()['total'];
$completed_assignments = $pdo->query("SELECT COUNT(*) as total FROM car_assignments WHERE assignment_status = 'تکمیل شده'")->fetch()['total'];
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت انتساب خودروها - اعلا نیرو</title>
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
        .assignment-details {
            display: none;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-user-check"></i> مدیریت انتساب خودروها</h2>
                    <div>
                        <span class="filter-active">
                            <i class="fas fa-filter"></i> 
                            <?= count($assignments) ?> مورد
                        </span>
                    </div>
                </div>

                <!-- آمار کلی -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="stats-card">
                            <h3><?= $total_assignments ?></h3>
                            <p><i class="fas fa-list"></i> کل انتساب‌ها</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <h3><?= $active_assignments ?></h3>
                            <p><i class="fas fa-clock"></i> انتساب‌های فعال</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <h3><?= $completed_assignments ?></h3>
                            <p><i class="fas fa-check-circle"></i> انتساب‌های تکمیل شده</p>
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
                                <select name="assigned_to" class="form-select">
                                    <option value="">همه کاربران</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?= $user['id'] ?>" <?= $user_filter == $user['id'] ? 'selected' : '' ?>>
                                            <?= $user['full_name'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="status" class="form-select">
                                    <option value="">همه وضعیت‌ها</option>
                                    <option value="فعال" <?= $status_filter == 'فعال' ? 'selected' : '' ?>>فعال</option>
                                    <option value="تکمیل شده" <?= $status_filter == 'تکمیل شده' ? 'selected' : '' ?>>تکمیل شده</option>
                                    <option value="لغو شده" <?= $status_filter == 'لغو شده' ? 'selected' : '' ?>>لغو شده</option>
                                    <option value="تاخیر" <?= $status_filter == 'تاخیر' ? 'selected' : '' ?>>تاخیر</option>
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

                <!-- فرم انتساب خودرو -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-plus-circle"></i> انتساب خودرو جدید</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="assignmentForm">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            
                            <div class="row mb-4">
                                <div class="col-md-4">
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
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="assigned_to" class="form-label">انتخاب کاربر *</label>
                                        <select class="form-select" id="assigned_to" name="assigned_to" required>
                                            <option value="">-- انتخاب کاربر --</option>
                                            <?php foreach ($users as $user): ?>
                                                <option value="<?= $user['id'] ?>"><?= $user['full_name'] ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="assignment_type" class="form-label">نوع انتساب *</label>
                                        <select class="form-select" id="assignment_type" name="assignment_type" required>
                                            <option value="تحویل">تحویل</option>
                                            <option value="تعمیر">تعمیر</option>
                                            <option value="سرویس">سرویس</option>
                                            <option value="سایر">سایر</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="assignment_date" class="form-label">تاریخ انتساب *</label>
                                        <input type="datetime-local" class="form-control" id="assignment_date" name="assignment_date" required value="<?= date('Y-m-d\TH:i') ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="expected_return_date" class="form-label">تاریخ بازگشت مورد انتظار</label>
                                        <input type="datetime-local" class="form-control" id="expected_return_date" name="expected_return_date">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="mileage_at_assignment" class="form-label">کیلومتر در زمان انتساب *</label>
                                        <input type="number" class="form-control" id="mileage_at_assignment" name="mileage_at_assignment" required>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="fuel_level_at_assignment" class="form-label">سطح سوخت *</label>
                                        <select class="form-select" id="fuel_level_at_assignment" name="fuel_level_at_assignment" required>
                                            <option value="کم">کم</option>
                                            <option value="متوسط" selected>متوسط</option>
                                            <option value="پر">پر</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="assignment_reason" class="form-label">دلیل انتساب *</label>
                                        <textarea class="form-control" id="assignment_reason" name="assignment_reason" rows="2" required></textarea>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="destination" class="form-label">مقصد</label>
                                        <input type="text" class="form-control" id="destination" name="destination">
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="assignment_location" class="form-label">مکان تحویل</label>
                                        <input type="text" class="form-control" id="assignment_location" name="assignment_location">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="car_condition_at_assignment" class="form-label">وضعیت خودرو در زمان تحویل</label>
                                        <textarea class="form-control" id="car_condition_at_assignment" name="car_condition_at_assignment" rows="2"></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">لوازم همراه</label>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="accessories_included[]" value="کلید" id="key">
                                            <label class="form-check-label" for="key">کلید</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="accessories_included[]" value="کارت سوخت" id="fuel_card">
                                            <label class="form-check-label" for="fuel_card">کارت سوخت</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="accessories_included[]" value="گواهینامه" id="license">
                                            <label class="form-check-label" for="license">گواهینامه</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="accessories_included[]" value="بیمه" id="insurance">
                                            <label class="form-check-label" for="insurance">بیمه</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="notes" class="form-label">توضیحات</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                            </div>

                            <button type="submit" name="assign_car" class="btn btn-success">
                                <i class="fas fa-user-check"></i> انتساب خودرو
                            </button>
                        </form>
                    </div>
                </div>

                <!-- لیست انتساب‌ها -->
                <div class="card mt-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-list"></i> لیست انتساب‌ها</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($assignments) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>خودرو</th>
                                            <th>کاربر</th>
                                            <th>نوع</th>
                                            <th>تاریخ انتساب</th>
                                            <th>تاریخ بازگشت</th>
                                            <th>وضعیت</th>
                                            <th>کیلومتر</th>
                                            <th>عملیات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($assignments as $assignment): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($assignment['car_plate']) ?></strong><br>
                                                <small><?= htmlspecialchars($assignment['car_name']) ?></small>
                                            </td>
                                            <td><?= htmlspecialchars($assignment['assigned_to_name']) ?></td>
                                            <td>
                                                <span class="badge bg-secondary"><?= $assignment['assignment_type'] ?></span>
                                            </td>
                                            <td><?= jalali_format($assignment['assignment_date'], 'Y/m/d H:i') ?></td>
                                            <td>
                                                <?= $assignment['return_date'] ? jalali_format($assignment['return_date'], 'Y/m/d H:i') : '--' ?>
                                            </td>
                                            <td>
                                                <?php
                                                $status_class = 'secondary';
                                                if ($assignment['assignment_status'] == 'فعال') $status_class = 'success';
                                                if ($assignment['assignment_status'] == 'تکمیل شده') $status_class = 'info';
                                                if ($assignment['assignment_status'] == 'لغو شده') $status_class = 'danger';
                                                if ($assignment['assignment_status'] == 'تاخیر') $status_class = 'warning';
                                                ?>
                                                <span class="badge bg-<?= $status_class ?> badge-status"><?= $assignment['assignment_status'] ?></span>
                                            </td>
                                            <td>
                                                <?= number_format($assignment['mileage_at_assignment']) ?>
                                                <?php if ($assignment['mileage_at_return']): ?>
                                                    → <?= number_format($assignment['mileage_at_return']) ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="action-buttons">
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#detailsModal<?= $assignment['id'] ?>" title="جزئیات">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($assignment['assignment_status'] == 'فعال'): ?>
                                                <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#returnModal<?= $assignment['id'] ?>" title="برگشت خودرو">
                                                    <i class="fas fa-undo"></i>
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
                                <i class="fas fa-info-circle"></i> هیچ انتسابی یافت نشد.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal های جزئیات -->
    <?php foreach ($assignments as $assignment): ?>
    <div class="modal fade" id="detailsModal<?= $assignment['id'] ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">جزئیات انتساب #<?= $assignment['id'] ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>خودرو:</strong> <?= $assignment['car_plate'] ?> - <?= $assignment['car_name'] ?></p>
                            <p><strong>کاربر:</strong> <?= $assignment['assigned_to_name'] ?></p>
                            <p><strong>نوع انتساب:</strong> <?= $assignment['assignment_type'] ?></p>
                            <p><strong>تاریخ انتساب:</strong> <?= jalali_format($assignment['assignment_date'], 'Y/m/d H:i') ?></p>
                            <p><strong>تاریخ بازگشت:</strong> <?= $assignment['return_date'] ? jalali_format($assignment['return_date'], 'Y/m/d H:i') : 'هنوز برگشت داده نشده' ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>وضعیت:</strong> <?= $assignment['assignment_status'] ?></p>
                            <p><strong>کیلومتر انتساب:</strong> <?= number_format($assignment['mileage_at_assignment']) ?></p>
                            <p><strong>کیلومتر بازگشت:</strong> <?= $assignment['mileage_at_return'] ? number_format($assignment['mileage_at_return']) : '--' ?></p>
                            <p><strong>سطح سوخت انتساب:</strong> <?= $assignment['fuel_level_at_assignment'] ?></p>
                            <p><strong>سطح سوخت بازگشت:</strong> <?= $assignment['fuel_level_at_return'] ?: '--' ?></p>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-12">
                            <p><strong>دلیل انتساب:</strong> <?= $assignment['assignment_reason'] ?></p>
                            <p><strong>مقصد:</strong> <?= $assignment['destination'] ?: '--' ?></p>
                            <p><strong>وضعیت خودرو در انتساب:</strong> <?= $assignment['car_condition_at_assignment'] ?: '--' ?></p>
                            <p><strong>وضعیت خودرو در بازگشت:</strong> <?= $assignment['car_condition_at_return'] ?: '--' ?></p>
                            <p><strong>توضیحات:</strong> <?= $assignment['notes'] ?: '--' ?></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal برگشت خودرو -->
    <?php if ($assignment['assignment_status'] == 'فعال'): ?>
    <div class="modal fade" id="returnModal<?= $assignment['id'] ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">برگشت خودرو #<?= $assignment['id'] ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="assignment_id" value="<?= $assignment['id'] ?>">
                        
                        <div class="mb-3">
                            <label for="return_date_<?= $assignment['id'] ?>" class="form-label">تاریخ بازگشت *</label>
                            <input type="datetime-local" class="form-control" id="return_date_<?= $assignment['id'] ?>" name="return_date" required value="<?= date('Y-m-d\TH:i') ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="mileage_at_return_<?= $assignment['id'] ?>" class="form-label">کیلومتر در زمان بازگشت *</label>
                            <input type="number" class="form-control" id="mileage_at_return_<?= $assignment['id'] ?>" name="mileage_at_return" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="fuel_level_at_return_<?= $assignment['id'] ?>" class="form-label">سطح سوخت در زمان بازگشت *</label>
                            <select class="form-select" id="fuel_level_at_return_<?= $assignment['id'] ?>" name="fuel_level_at_return" required>
                                <option value="کم">کم</option>
                                <option value="متوسط" selected>متوسط</option>
                                <option value="پر">پر</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="car_condition_at_return_<?= $assignment['id'] ?>" class="form-label">وضعیت خودرو در زمان بازگشت</label>
                            <textarea class="form-control" id="car_condition_at_return_<?= $assignment['id'] ?>" name="car_condition_at_return" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">لوازم برگشت داده شده</label>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="accessories_returned[]" value="کلید" id="return_key_<?= $assignment['id'] ?>">
                                        <label class="form-check-label" for="return_key_<?= $assignment['id'] ?>">کلید</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="accessories_returned[]" value="کارت سوخت" id="return_fuel_card_<?= $assignment['id'] ?>">
                                        <label class="form-check-label" for="return_fuel_card_<?= $assignment['id'] ?>">کارت سوخت</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="accessories_returned[]" value="گواهینامه" id="return_license_<?= $assignment['id'] ?>">
                                        <label class="form-check-label" for="return_license_<?= $assignment['id'] ?>">گواهینامه</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="accessories_returned[]" value="بیمه" id="return_insurance_<?= $assignment['id'] ?>">
                                        <label class="form-check-label" for="return_insurance_<?= $assignment['id'] ?>">بیمه</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="return_notes_<?= $assignment['id'] ?>" class="form-label">توضیحات</label>
                            <textarea class="form-control" id="return_notes_<?= $assignment['id'] ?>" name="return_notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">لغو</button>
                        <button type="submit" name="return_car" class="btn btn-warning">برگشت خودرو</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php endforeach; ?>

    <script>
    function loadCarDetails() {
        const carSelect = document.getElementById('car_id');
        const selectedOption = carSelect.options[carSelect.selectedIndex];
        
        if (carSelect.value) {
            const mileage = selectedOption.getAttribute('data-mileage');
            document.getElementById('mileage_at_assignment').value = mileage;
        }
    }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>