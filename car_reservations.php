<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// ثبت لاگ
logAction($pdo, 'VIEW_CAR_RESERVATIONS', 'مشاهده صفحه رزرو خودروها');

// درخواست رزرو جدید
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_reservation'])) {
    verifyCsrfToken();
    
    try {
        $pdo->beginTransaction();
        
        $data = [
            'car_id' => (int)$_POST['car_id'],
            'requested_by' => $_SESSION['user_id'],
            'reservation_date' => date('Y-m-d H:i:s'),
            'start_date' => $_POST['start_date'],
            'end_date' => $_POST['end_date'],
            'purpose' => sanitizeInput($_POST['purpose']),
            'destination' => sanitizeInput($_POST['destination']),
            'passengers_count' => (int)$_POST['passengers_count'],
            'special_requirements' => sanitizeInput($_POST['special_requirements']),
            'notes' => sanitizeInput($_POST['notes'])
        ];
        
        $reservation_id = requestCarReservation($pdo, $data);
        
        $pdo->commit();
        
        $_SESSION['success'] = "درخواست رزرو با موفقیت ثبت شد!";
        logAction($pdo, 'REQUEST_CAR_RESERVATION', "درخواست رزرو جدید برای خودرو ID: " . $data['car_id']);
        
        header('Location: car_reservations.php');
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "خطا در ثبت درخواست رزرو: " . $e->getMessage();
        logAction($pdo, 'REQUEST_CAR_RESERVATION_ERROR', "خطا در ثبت درخواست رزرو: " . $e->getMessage());
    }
}

// تایید/رد رزرو
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['approve_reservation'])) {
    verifyCsrfToken();
    
    try {
        $reservation_id = (int)$_POST['reservation_id'];
        $action = $_POST['action']; // 'approve' or 'reject'
        $notes = sanitizeInput($_POST['notes']);
        
        $status = $action === 'approve' ? 'تایید شده' : 'رد شده';
        
        $stmt = $pdo->prepare("UPDATE car_reservations SET 
            reservation_status = ?, approved_by = ?, approval_date = NOW(), 
            rejection_reason = ?, notes = CONCAT(IFNULL(notes, ''), '\n', ?)
            WHERE id = ?");
        
        $stmt->execute([
            $status, 
            $_SESSION['user_id'], 
            $action === 'reject' ? $notes : null,
            $notes,
            $reservation_id
        ]);
        
        $_SESSION['success'] = "رزرو " . ($action === 'approve' ? 'تایید' : 'رد') . " شد!";
        logAction($pdo, 'APPROVE_CAR_RESERVATION', "رزرو ID: $reservation_id " . ($action === 'approve' ? 'تایید' : 'رد') . " شد");
        
        header('Location: car_reservations.php');
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = "خطا در " . ($action === 'approve' ? 'تایید' : 'رد') . " رزرو: " . $e->getMessage();
    }
}

// دریافت فیلترها
$status_filter = $_GET['status'] ?? '';
$car_filter = $_GET['car_id'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// ساخت کوئری بر اساس فیلترها
$query = "SELECT cr.*, cc.car_plate, cc.car_name, cc.car_model, 
          u1.full_name as requested_by_name, u2.full_name as approved_by_name
          FROM car_reservations cr 
          LEFT JOIN company_cars cc ON cr.car_id = cc.id 
          LEFT JOIN users u1 ON cr.requested_by = u1.id 
          LEFT JOIN users u2 ON cr.approved_by = u2.id 
          WHERE 1=1";
$params = [];

if ($status_filter) {
    $query .= " AND cr.reservation_status = ?";
    $params[] = $status_filter;
}
if ($car_filter) {
    $query .= " AND cr.car_id = ?";
    $params[] = $car_filter;
}
if ($date_from) {
    $query .= " AND cr.start_date >= ?";
    $params[] = $date_from;
}
if ($date_to) {
    $query .= " AND cr.end_date <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY cr.reservation_date DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$reservations = $stmt->fetchAll();

// دریافت لیست خودروها
$cars = $pdo->query("SELECT id, car_plate, car_name, car_model FROM company_cars WHERE status = 'فعال' ORDER BY car_name")->fetchAll();

// آمار
$total_reservations = $pdo->query("SELECT COUNT(*) as total FROM car_reservations")->fetch()['total'];
$pending_reservations = $pdo->query("SELECT COUNT(*) as total FROM car_reservations WHERE reservation_status = 'در انتظار تایید'")->fetch()['total'];
$approved_reservations = $pdo->query("SELECT COUNT(*) as total FROM car_reservations WHERE reservation_status = 'تایید شده'")->fetch()['total'];
$rejected_reservations = $pdo->query("SELECT COUNT(*) as total FROM car_reservations WHERE reservation_status = 'رد شده'")->fetch()['total'];
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>رزرو خودروها - اعلا نیرو</title>
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
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-calendar-check"></i> رزرو خودروها</h2>
                    <div>
                        <span class="filter-active">
                            <i class="fas fa-filter"></i> 
                            <?= count($reservations) ?> مورد
                        </span>
                    </div>
                </div>

                <!-- آمار کلی -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h3><?= $total_reservations ?></h3>
                            <p><i class="fas fa-calendar"></i> کل رزروها</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <h3><?= $pending_reservations ?></h3>
                            <p><i class="fas fa-clock"></i> در انتظار تایید</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <h3><?= $approved_reservations ?></h3>
                            <p><i class="fas fa-check-circle"></i> تایید شده</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                            <h3><?= $rejected_reservations ?></h3>
                            <p><i class="fas fa-times-circle"></i> رد شده</p>
                        </div>
                    </div>
                </div>

                <!-- جستجو و فیلتر -->
                <div class="card search-box">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <select name="status" class="form-select">
                                    <option value="">همه وضعیت‌ها</option>
                                    <option value="در انتظار تایید" <?= $status_filter == 'در انتظار تایید' ? 'selected' : '' ?>>در انتظار تایید</option>
                                    <option value="تایید شده" <?= $status_filter == 'تایید شده' ? 'selected' : '' ?>>تایید شده</option>
                                    <option value="رد شده" <?= $status_filter == 'رد شده' ? 'selected' : '' ?>>رد شده</option>
                                    <option value="لغو شده" <?= $status_filter == 'لغو شده' ? 'selected' : '' ?>>لغو شده</option>
                                    <option value="تکمیل شده" <?= $status_filter == 'تکمیل شده' ? 'selected' : '' ?>>تکمیل شده</option>
                                </select>
                            </div>
                            <div class="col-md-3">
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

                <!-- فرم درخواست رزرو -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-plus-circle"></i> درخواست رزرو جدید</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="reservationForm">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            
                            <div class="row mb-4">
                                <div class="col-md-4">
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
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="start_date" class="form-label">تاریخ شروع *</label>
                                        <input type="datetime-local" class="form-control" id="start_date" name="start_date" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="end_date" class="form-label">تاریخ پایان *</label>
                                        <input type="datetime-local" class="form-control" id="end_date" name="end_date" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="purpose" class="form-label">هدف استفاده *</label>
                                        <input type="text" class="form-control" id="purpose" name="purpose" required>
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
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="passengers_count" class="form-label">تعداد مسافر</label>
                                        <input type="number" class="form-control" id="passengers_count" name="passengers_count" min="1" value="1">
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label for="special_requirements" class="form-label">نیازهای خاص</label>
                                        <input type="text" class="form-control" id="special_requirements" name="special_requirements">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="notes" class="form-label">توضیحات</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                            </div>

                            <button type="submit" name="request_reservation" class="btn btn-success">
                                <i class="fas fa-calendar-plus"></i> درخواست رزرو
                            </button>
                        </form>
                    </div>
                </div>

                <!-- لیست رزروها -->
                <div class="card mt-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-list"></i> لیست رزروها</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($reservations) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>خودرو</th>
                                            <th>درخواست‌کننده</th>
                                            <th>تاریخ شروع</th>
                                            <th>تاریخ پایان</th>
                                            <th>هدف</th>
                                            <th>وضعیت</th>
                                            <th>عملیات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reservations as $reservation): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($reservation['car_plate']) ?></strong><br>
                                                <small><?= htmlspecialchars($reservation['car_name']) ?></small>
                                            </td>
                                            <td><?= $reservation['requested_by_name'] ?: '--' ?></td>
                                            <td><?= jalali_format($reservation['start_date'], 'Y/m/d H:i') ?></td>
                                            <td><?= jalali_format($reservation['end_date'], 'Y/m/d H:i') ?></td>
                                            <td><?= htmlspecialchars($reservation['purpose']) ?></td>
                                            <td>
                                                <?php
                                                $status_class = 'secondary';
                                                if ($reservation['reservation_status'] == 'تایید شده') $status_class = 'success';
                                                if ($reservation['reservation_status'] == 'رد شده') $status_class = 'danger';
                                                if ($reservation['reservation_status'] == 'در انتظار تایید') $status_class = 'warning';
                                                if ($reservation['reservation_status'] == 'لغو شده') $status_class = 'dark';
                                                ?>
                                                <span class="badge bg-<?= $status_class ?> badge-status"><?= $reservation['reservation_status'] ?></span>
                                            </td>
                                            <td class="action-buttons">
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#detailsModal<?= $reservation['id'] ?>" title="جزئیات">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($is_admin && $reservation['reservation_status'] == 'در انتظار تایید'): ?>
                                                <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#approveModal<?= $reservation['id'] ?>" title="تایید">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?= $reservation['id'] ?>" title="رد">
                                                    <i class="fas fa-times"></i>
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
                                <i class="fas fa-info-circle"></i> هیچ رزروی یافت نشد.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal های جزئیات -->
    <?php foreach ($reservations as $reservation): ?>
    <div class="modal fade" id="detailsModal<?= $reservation['id'] ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">جزئیات رزرو #<?= $reservation['id'] ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>خودرو:</strong> <?= $reservation['car_plate'] ?> - <?= $reservation['car_name'] ?></p>
                            <p><strong>درخواست‌کننده:</strong> <?= $reservation['requested_by_name'] ?: '--' ?></p>
                            <p><strong>تاریخ درخواست:</strong> <?= jalali_format($reservation['reservation_date'], 'Y/m/d H:i') ?></p>
                            <p><strong>تاریخ شروع:</strong> <?= jalali_format($reservation['start_date'], 'Y/m/d H:i') ?></p>
                            <p><strong>تاریخ پایان:</strong> <?= jalali_format($reservation['end_date'], 'Y/m/d H:i') ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>وضعیت:</strong> <?= $reservation['reservation_status'] ?></p>
                            <p><strong>هدف:</strong> <?= $reservation['purpose'] ?></p>
                            <p><strong>مقصد:</strong> <?= $reservation['destination'] ?: '--' ?></p>
                            <p><strong>تعداد مسافر:</strong> <?= $reservation['passengers_count'] ?></p>
                            <p><strong>نیازهای خاص:</strong> <?= $reservation['special_requirements'] ?: '--' ?></p>
                        </div>
                    </div>
                    <?php if ($reservation['notes']): ?>
                    <div class="row mt-3">
                        <div class="col-md-12">
                            <p><strong>توضیحات:</strong></p>
                            <p><?= nl2br(htmlspecialchars($reservation['notes'])) ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($reservation['approved_by_name']): ?>
                    <div class="row mt-3">
                        <div class="col-md-12">
                            <p><strong>تایید کننده:</strong> <?= $reservation['approved_by_name'] ?></p>
                            <p><strong>تاریخ تایید:</strong> <?= jalali_format($reservation['approval_date'], 'Y/m/d H:i') ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>