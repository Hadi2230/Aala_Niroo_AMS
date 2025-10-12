<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// ثبت لاگ
logAction($pdo, 'VIEW_CAR_DRIVERS', 'مشاهده صفحه مدیریت رانندگان');

// ثبت راننده جدید
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_driver'])) {
    verifyCsrfToken();
    
    try {
        $pdo->beginTransaction();
        
        $data = [
            'user_id' => (int)$_POST['user_id'],
            'driver_license_number' => sanitizeInput($_POST['driver_license_number']),
            'license_type' => sanitizeInput($_POST['license_type']),
            'license_expiry_date' => $_POST['license_expiry_date'],
            'license_issue_date' => $_POST['license_issue_date'],
            'license_authority' => sanitizeInput($_POST['license_authority']),
            'medical_certificate_expiry' => $_POST['medical_certificate_expiry'],
            'training_certificate_expiry' => $_POST['training_certificate_expiry'],
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'notes' => sanitizeInput($_POST['notes'])
        ];
        
        $driver_id = registerDriver($pdo, $data);
        
        $pdo->commit();
        
        $_SESSION['success'] = "راننده با موفقیت ثبت شد!";
        logAction($pdo, 'ADD_DRIVER', "افزودن راننده جدید: " . $data['driver_license_number']);
        
        header('Location: car_drivers.php');
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "خطا در ثبت راننده: " . $e->getMessage();
        logAction($pdo, 'ADD_DRIVER_ERROR', "خطا در ثبت راننده: " . $e->getMessage());
    }
}

// دریافت فیلترها
$license_type_filter = $_GET['license_type'] ?? '';
$status_filter = $_GET['is_active'] ?? '';
$expiry_filter = $_GET['expiry_filter'] ?? '';

// ساخت کوئری بر اساس فیلترها
$query = "SELECT cd.*, u.full_name, u.username 
          FROM car_drivers cd 
          LEFT JOIN users u ON cd.user_id = u.id 
          WHERE 1=1";
$params = [];

if ($license_type_filter) {
    $query .= " AND cd.license_type = ?";
    $params[] = $license_type_filter;
}
if ($status_filter !== '') {
    $query .= " AND cd.is_active = ?";
    $params[] = $status_filter;
}
if ($expiry_filter) {
    $query .= " AND cd.license_expiry_date <= ?";
    $params[] = date('Y-m-d', strtotime('+30 days'));
}

$query .= " ORDER BY cd.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$drivers = $stmt->fetchAll();

// دریافت لیست کاربران
$users = $pdo->query("SELECT id, full_name, username FROM users WHERE is_active = 1 ORDER BY full_name")->fetchAll();

// آمار
$total_drivers = $pdo->query("SELECT COUNT(*) as total FROM car_drivers")->fetch()['total'];
$active_drivers = $pdo->query("SELECT COUNT(*) as total FROM car_drivers WHERE is_active = 1")->fetch()['total'];
$expired_licenses = $pdo->query("SELECT COUNT(*) as total FROM car_drivers WHERE license_expiry_date < CURDATE()")->fetch()['total'];
$expiring_licenses = $pdo->query("SELECT COUNT(*) as total FROM car_drivers WHERE license_expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)")->fetch()['total'];
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت رانندگان - اعلا نیرو</title>
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
        .expired {
            color: #dc3545;
            font-weight: bold;
        }
        .expiring {
            color: #ffc107;
            font-weight: bold;
        }
        .valid {
            color: #28a745;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-id-card"></i> مدیریت رانندگان</h2>
                    <div>
                        <span class="filter-active">
                            <i class="fas fa-filter"></i> 
                            <?= count($drivers) ?> مورد
                        </span>
                    </div>
                </div>

                <!-- آمار کلی -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h3><?= $total_drivers ?></h3>
                            <p><i class="fas fa-users"></i> کل رانندگان</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <h3><?= $active_drivers ?></h3>
                            <p><i class="fas fa-check-circle"></i> رانندگان فعال</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <h3><?= $expiring_licenses ?></h3>
                            <p><i class="fas fa-exclamation-triangle"></i> گواهینامه‌های در حال انقضا</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                            <h3><?= $expired_licenses ?></h3>
                            <p><i class="fas fa-times-circle"></i> گواهینامه‌های منقضی</p>
                        </div>
                    </div>
                </div>

                <!-- جستجو و فیلتر -->
                <div class="card search-box">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <select name="license_type" class="form-select">
                                    <option value="">همه انواع گواهینامه</option>
                                    <option value="A" <?= $license_type_filter == 'A' ? 'selected' : '' ?>>A - موتورسیکلت</option>
                                    <option value="B" <?= $license_type_filter == 'B' ? 'selected' : '' ?>>B - خودروی سواری</option>
                                    <option value="C" <?= $license_type_filter == 'C' ? 'selected' : '' ?>>C - وانت</option>
                                    <option value="D" <?= $license_type_filter == 'D' ? 'selected' : '' ?>>D - کامیون</option>
                                    <option value="E" <?= $license_type_filter == 'E' ? 'selected' : '' ?>>E - اتوبوس</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select name="is_active" class="form-select">
                                    <option value="">همه وضعیت‌ها</option>
                                    <option value="1" <?= $status_filter === '1' ? 'selected' : '' ?>>فعال</option>
                                    <option value="0" <?= $status_filter === '0' ? 'selected' : '' ?>>غیرفعال</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select name="expiry_filter" class="form-select">
                                    <option value="">همه گواهینامه‌ها</option>
                                    <option value="expiring" <?= $expiry_filter == 'expiring' ? 'selected' : '' ?>>در حال انقضا (30 روز)</option>
                                    <option value="expired" <?= $expiry_filter == 'expired' ? 'selected' : '' ?>>منقضی شده</option>
                                </select>
                            </div>
                            <div class="col-md-3">
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

                <!-- فرم ثبت راننده -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-plus-circle"></i> ثبت راننده جدید</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="driverForm">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            
                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="user_id" class="form-label">انتخاب کاربر *</label>
                                        <select class="form-select" id="user_id" name="user_id" required>
                                            <option value="">-- انتخاب کاربر --</option>
                                            <?php foreach ($users as $user): ?>
                                                <option value="<?= $user['id'] ?>"><?= $user['full_name'] ?> (<?= $user['username'] ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="driver_license_number" class="form-label">شماره گواهینامه *</label>
                                        <input type="text" class="form-control" id="driver_license_number" name="driver_license_number" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="license_type" class="form-label">نوع گواهینامه *</label>
                                        <select class="form-select" id="license_type" name="license_type" required>
                                            <option value="">-- انتخاب کنید --</option>
                                            <option value="A">A - موتورسیکلت</option>
                                            <option value="B">B - خودروی سواری</option>
                                            <option value="C">C - وانت</option>
                                            <option value="D">D - کامیون</option>
                                            <option value="E">E - اتوبوس</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="license_issue_date" class="form-label">تاریخ صدور گواهینامه</label>
                                        <input type="date" class="form-control" id="license_issue_date" name="license_issue_date">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="license_expiry_date" class="form-label">تاریخ انقضای گواهینامه *</label>
                                        <input type="date" class="form-control" id="license_expiry_date" name="license_expiry_date" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="license_authority" class="form-label">مرجع صدور</label>
                                        <input type="text" class="form-control" id="license_authority" name="license_authority">
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="medical_certificate_expiry" class="form-label">تاریخ انقضای گواهی پزشکی</label>
                                        <input type="date" class="form-control" id="medical_certificate_expiry" name="medical_certificate_expiry">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="training_certificate_expiry" class="form-label">تاریخ انقضای گواهی آموزش</label>
                                        <input type="date" class="form-control" id="training_certificate_expiry" name="training_certificate_expiry">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <div class="form-check mt-4">
                                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                                            <label class="form-check-label" for="is_active">
                                                راننده فعال
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="notes" class="form-label">توضیحات</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                            </div>

                            <button type="submit" name="add_driver" class="btn btn-success">
                                <i class="fas fa-save"></i> ثبت راننده
                            </button>
                        </form>
                    </div>
                </div>

                <!-- لیست رانندگان -->
                <div class="card mt-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-list"></i> لیست رانندگان</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($drivers) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>راننده</th>
                                            <th>شماره گواهینامه</th>
                                            <th>نوع گواهینامه</th>
                                            <th>تاریخ انقضا</th>
                                            <th>وضعیت گواهینامه</th>
                                            <th>وضعیت</th>
                                            <th>عملیات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($drivers as $driver): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($driver['full_name']) ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars($driver['username']) ?></small>
                                            </td>
                                            <td><?= htmlspecialchars($driver['driver_license_number']) ?></td>
                                            <td>
                                                <span class="badge bg-secondary"><?= $driver['license_type'] ?></span>
                                            </td>
                                            <td><?= $driver['license_expiry_date'] ? jalali_format($driver['license_expiry_date'], 'Y/m/d') : '--' ?></td>
                                            <td>
                                                <?php
                                                $expiry_date = $driver['license_expiry_date'];
                                                $today = date('Y-m-d');
                                                $expiry_class = 'valid';
                                                $expiry_text = 'معتبر';
                                                
                                                if ($expiry_date) {
                                                    if ($expiry_date < $today) {
                                                        $expiry_class = 'expired';
                                                        $expiry_text = 'منقضی شده';
                                                    } elseif ($expiry_date <= date('Y-m-d', strtotime('+30 days'))) {
                                                        $expiry_class = 'expiring';
                                                        $expiry_text = 'در حال انقضا';
                                                    }
                                                }
                                                ?>
                                                <span class="<?= $expiry_class ?>"><?= $expiry_text ?></span>
                                            </td>
                                            <td>
                                                <?php if ($driver['is_active']): ?>
                                                    <span class="badge bg-success badge-status">فعال</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger badge-status">غیرفعال</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="action-buttons">
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#detailsModal<?= $driver['id'] ?>" title="جزئیات">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <a href="edit_driver.php?id=<?= $driver['id'] ?>" class="btn btn-sm btn-warning" title="ویرایش">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info text-center">
                                <i class="fas fa-info-circle"></i> هیچ راننده‌ای یافت نشد.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal های جزئیات -->
    <?php foreach ($drivers as $driver): ?>
    <div class="modal fade" id="detailsModal<?= $driver['id'] ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">جزئیات راننده #<?= $driver['id'] ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>نام:</strong> <?= $driver['full_name'] ?></p>
                            <p><strong>نام کاربری:</strong> <?= $driver['username'] ?></p>
                            <p><strong>شماره گواهینامه:</strong> <?= $driver['driver_license_number'] ?></p>
                            <p><strong>نوع گواهینامه:</strong> <?= $driver['license_type'] ?></p>
                            <p><strong>تاریخ صدور:</strong> <?= $driver['license_issue_date'] ? jalali_format($driver['license_issue_date'], 'Y/m/d') : '--' ?></p>
                            <p><strong>تاریخ انقضا:</strong> <?= $driver['license_expiry_date'] ? jalali_format($driver['license_expiry_date'], 'Y/m/d') : '--' ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>مرجع صدور:</strong> <?= $driver['license_authority'] ?: '--' ?></p>
                            <p><strong>انقضای گواهی پزشکی:</strong> <?= $driver['medical_certificate_expiry'] ? jalali_format($driver['medical_certificate_expiry'], 'Y/m/d') : '--' ?></p>
                            <p><strong>انقضای گواهی آموزش:</strong> <?= $driver['training_certificate_expiry'] ? jalali_format($driver['training_certificate_expiry'], 'Y/m/d') : '--' ?></p>
                            <p><strong>وضعیت:</strong> 
                                <?php if ($driver['is_active']): ?>
                                    <span class="badge bg-success">فعال</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">غیرفعال</span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <?php if ($driver['notes']): ?>
                    <div class="row mt-3">
                        <div class="col-md-12">
                            <p><strong>توضیحات:</strong></p>
                            <p><?= nl2br(htmlspecialchars($driver['notes'])) ?></p>
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