<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
include 'config.php';
require_auth('کاربر عادی');

logAction($pdo, 'VIEW_FLEET_REPORTS', 'گزارش ناوگان');

$date_from = $_GET['date_from'] ?? '';
$date_to   = $_GET['date_to'] ?? '';
$stats = getFleetStats($pdo, $date_from ?: null, $date_to ?: null);

// Fuel log add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_fuel'])) {
    try {
        verifyCsrfToken();
        $vehicle_id = (int)$_POST['vehicle_id'];
        $log_date = sanitizeInput($_POST['log_date']);
        $liters = (float)$_POST['liters'];
        $unit_price = (float)($_POST['unit_price'] ?? 0);
        $total_cost = $unit_price > 0 ? ($unit_price * $liters) : (float)($_POST['total_cost'] ?? 0);
        $odometer_km = !empty($_POST['odometer_km']) ? (int)$_POST['odometer_km'] : null;
        $fuel_type = sanitizeInput($_POST['fuel_type'] ?? 'gasoline');
        $station_name = sanitizeInput($_POST['station_name'] ?? '');
        $receipt_path = null;
        if (!empty($_FILES['receipt']['name'] ?? '')) {
            $receipt_path = uploadFile($_FILES['receipt'], __DIR__ . '/uploads/vehicles/docs');
        }
        $stmt = $pdo->prepare("INSERT INTO vehicle_fuel_logs (vehicle_id, log_date, liters, unit_price, total_cost, odometer_km, fuel_type, station_name, receipt_path, created_by) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$vehicle_id, $log_date, $liters, $unit_price, $total_cost, $odometer_km, $fuel_type, $station_name ?: null, $receipt_path, ($_SESSION['user_id'] ?? null)]);
        $_SESSION['success'] = 'ثبت سوخت‌گیری انجام شد';
        redirect('vehicle_reports.php');
    } catch (Exception $e) {
        $_SESSION['error'] = 'خطا در ثبت سوخت‌گیری: ' . $e->getMessage();
    }
}

$vehicles = $pdo->query("SELECT id, license_plate, make, model FROM company_vehicles ORDER BY license_plate")->fetchAll();

?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>گزارش ناوگان خودرو</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
  <?php include 'navbar.php'; ?>
  <div class="container mt-5 pt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2><i class="fas fa-chart-line"></i> گزارش ناوگان</h2>
    </div>

    <?php if (!empty($_SESSION['success'])): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= $_SESSION['success']; unset($_SESSION['success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error'])): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= $_SESSION['error']; unset($_SESSION['error']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <div class="card mb-4">
      <div class="card-header bg-light">
        <form class="row g-2 align-items-end" method="GET">
          <div class="col-md-3">
            <label class="form-label">از تاریخ</label>
            <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" class="form-control">
          </div>
          <div class="col-md-3">
            <label class="form-label">تا تاریخ</label>
            <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" class="form-control">
          </div>
          <div class="col-md-2">
            <button class="btn btn-primary w-100"><i class="fas fa-filter"></i> اعمال</button>
          </div>
          <div class="col-md-2">
            <a href="vehicle_reports.php" class="btn btn-outline-secondary w-100"><i class="fas fa-times"></i> حذف</a>
          </div>
        </form>
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-6">
            <h5 class="mb-3">کیلومتر طی‌شده به تفکیک خودرو</h5>
            <div class="table-responsive">
              <table class="table table-sm table-striped">
                <thead>
                  <tr><th>خودرو</th><th>کیلومتر</th></tr>
                </thead>
                <tbody>
                  <?php if (empty($stats['km_by_vehicle'])): ?>
                    <tr><td colspan="2" class="text-center">داده‌ای نیست</td></tr>
                  <?php else: foreach ($stats['km_by_vehicle'] as $row):
                    $v = $pdo->prepare("SELECT license_plate, make, model FROM company_vehicles WHERE id = ?");
                    $v->execute([(int)$row['vehicle_id']]);
                    $vd = $v->fetch();
                  ?>
                    <tr>
                      <td><?= htmlspecialchars(($vd['license_plate'] ?? 'خودرو #' . (int)$row['vehicle_id']) . ' - ' . trim(($vd['make']??'').' '.($vd['model']??''))) ?></td>
                      <td><?= number_format((int)$row['km_driven']) ?> km</td>
                    </tr>
                  <?php endforeach; endif; ?>
                </tbody>
              </table>
            </div>
          </div>
          <div class="col-md-6">
            <h5 class="mb-3">مصرف سوخت و هزینه</h5>
            <div class="table-responsive">
              <table class="table table-sm table-striped">
                <thead>
                  <tr><th>خودرو</th><th>لیتر</th><th>هزینه</th></tr>
                </thead>
                <tbody>
                  <?php if (empty($stats['fuel_by_vehicle'])): ?>
                    <tr><td colspan="3" class="text-center">داده‌ای نیست</td></tr>
                  <?php else: foreach ($stats['fuel_by_vehicle'] as $row):
                    $v = $pdo->prepare("SELECT license_plate, make, model FROM company_vehicles WHERE id = ?");
                    $v->execute([(int)$row['vehicle_id']]);
                    $vd = $v->fetch();
                  ?>
                    <tr>
                      <td><?= htmlspecialchars(($vd['license_plate'] ?? 'خودرو #' . (int)$row['vehicle_id']) . ' - ' . trim(($vd['make']??'').' '.($vd['model']??''))) ?></td>
                      <td><?= number_format((float)$row['liters'], 2) ?></td>
                      <td><?= number_format((float)$row['cost'], 0) ?> ریال</td>
                    </tr>
                  <?php endforeach; endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="mt-4">
          <h5 class="mb-3">سررسید بیمه/معاینه در 30 روز آینده</h5>
          <div class="table-responsive">
            <table class="table table-sm table-striped">
              <thead>
                <tr><th>پلاک</th><th>برند/مدل</th><th>بیمه</th><th>معاینه</th></tr>
              </thead>
              <tbody>
                <?php if (empty($stats['upcoming_expiries'])): ?>
                  <tr><td colspan="4" class="text-center">هیچ سررسیدی نزدیک نیست</td></tr>
                <?php else: foreach ($stats['upcoming_expiries'] as $row): ?>
                  <tr>
                    <td><span class="badge bg-secondary"><?= htmlspecialchars($row['license_plate']) ?></span></td>
                    <td><?= htmlspecialchars(trim(($row['make']??'').' '.($row['model']??''))) ?></td>
                    <td><?= $row['insurance_expiry'] ? jalaliDate($row['insurance_expiry']) : '-' ?></td>
                    <td><?= $row['inspection_expiry'] ? jalaliDate($row['inspection_expiry']) : '-' ?></td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="card mt-4">
      <div class="card-header bg-success text-white"><i class="fas fa-gas-pump"></i> ثبت سوخت‌گیری</div>
      <div class="card-body">
        <form method="POST" enctype="multipart/form-data" class="row g-3">
          <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
          <div class="col-md-3">
            <label class="form-label">خودرو *</label>
            <select name="vehicle_id" class="form-select" required>
              <option value="">انتخاب خودرو</option>
              <?php foreach ($vehicles as $v): ?>
                <option value="<?= (int)$v['id'] ?>"><?= htmlspecialchars($v['license_plate'] . ' - ' . trim(($v['make']??'').' '.($v['model']??''))) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">تاریخ *</label>
            <input type="date" name="log_date" class="form-control" required value="<?= date('Y-m-d') ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label">لیتر *</label>
            <input type="number" step="0.01" name="liters" class="form-control" required>
          </div>
          <div class="col-md-2">
            <label class="form-label">قیمت واحد</label>
            <input type="number" step="0.01" name="unit_price" class="form-control">
          </div>
          <div class="col-md-3">
            <label class="form-label">هزینه کل</label>
            <input type="number" step="0.01" name="total_cost" class="form-control">
          </div>
          <div class="col-md-2">
            <label class="form-label">کیلومتر</label>
            <input type="number" name="odometer_km" class="form-control">
          </div>
          <div class="col-md-2">
            <label class="form-label">نوع سوخت</label>
            <select name="fuel_type" class="form-select">
              <option value="gasoline">بنزین</option>
              <option value="diesel">گازوئیل</option>
              <option value="cng">CNG</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">جایگاه</label>
            <input type="text" name="station_name" class="form-control">
          </div>
          <div class="col-md-3">
            <label class="form-label">فیش</label>
            <input type="file" name="receipt" class="form-control" accept=".pdf,image/*">
          </div>
          <div class="col-12">
            <button class="btn btn-success" name="add_fuel" type="submit"><i class="fas fa-save"></i> ثبت</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
