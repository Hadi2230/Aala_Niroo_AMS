<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
include 'config.php';
require_auth('کاربر عادی');

// Log page view
logAction($pdo, 'VIEW_FLEET_VEHICLES', 'مشاهده مدیریت ناوگان');

// Add vehicle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_vehicle'])) {
    try {
        verifyCsrfToken();
        $code = generateVehicleCode($pdo);
        $license_plate = sanitizeInput($_POST['license_plate'] ?? '');
        if ($license_plate === '') { throw new Exception('پلاک الزامی است'); }
        $vin = sanitizeInput($_POST['vin'] ?? '');
        $make = sanitizeInput($_POST['make'] ?? '');
        $model = sanitizeInput($_POST['model'] ?? '');
        $manufacture_year = (int)($_POST['manufacture_year'] ?? 0);
        $color = sanitizeInput($_POST['color'] ?? '');
        $fuel_type = sanitizeInput($_POST['fuel_type'] ?? 'gasoline');
        $transmission = sanitizeInput($_POST['transmission'] ?? 'automatic');
        $seating_capacity = (int)($_POST['seating_capacity'] ?? 5);
        $current_odometer_km = (int)($_POST['current_odometer_km'] ?? 0);
        $insurance_expiry = !empty($_POST['insurance_expiry']) ? sanitizeInput($_POST['insurance_expiry']) : null;
        $inspection_expiry = !empty($_POST['inspection_expiry']) ? sanitizeInput($_POST['inspection_expiry']) : null;
        $description = sanitizeInput($_POST['description'] ?? '');

        $registration_card_path = null;
        $insurance_doc_path = null;
        if (!empty($_FILES['registration_card']['name'] ?? '')) {
            $registration_card_path = uploadFile($_FILES['registration_card'], __DIR__ . '/uploads/vehicles/docs');
        }
        if (!empty($_FILES['insurance_doc']['name'] ?? '')) {
            $insurance_doc_path = uploadFile($_FILES['insurance_doc'], __DIR__ . '/uploads/vehicles/docs');
        }

        $stmt = $pdo->prepare("INSERT INTO company_vehicles (
            code, license_plate, vin, make, model, manufacture_year, color,
            fuel_type, transmission, seating_capacity, status, current_odometer_km,
            insurance_expiry, inspection_expiry, registration_card_path, insurance_doc_path, description
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $code, $license_plate, $vin ?: null, $make ?: null, $model ?: null, $manufacture_year ?: null, $color ?: null,
            $fuel_type, $transmission, $seating_capacity ?: null, 'available', $current_odometer_km,
            $insurance_expiry, $inspection_expiry, $registration_card_path, $insurance_doc_path, $description ?: null
        ]);
        $_SESSION['success'] = 'خودرو با موفقیت ثبت شد';
        logAction($pdo, 'FLEET_VEHICLE_ADD', 'plate=' . $license_plate);
        redirect('vehicles.php');
    } catch (Exception $e) {
        $_SESSION['error'] = 'خطا در ثبت خودرو: ' . $e->getMessage();
    }
}

// Delete vehicle
if (isset($_GET['delete_id'])) {
    try {
        checkPermission('ادمین');
        $vehicle_id = (int)$_GET['delete_id'];
        // Ensure no active handover
        $stmt = $pdo->prepare("SELECT COUNT(*) as c FROM vehicle_handovers WHERE vehicle_id = ? AND status = 'active'");
        $stmt->execute([$vehicle_id]);
        if ((int)$stmt->fetch()['c'] > 0) { throw new Exception('برای این خودرو تحویل فعال وجود دارد'); }
        $stmt = $pdo->prepare("DELETE FROM company_vehicles WHERE id = ?");
        $stmt->execute([$vehicle_id]);
        $_SESSION['success'] = 'خودرو حذف شد';
        logAction($pdo, 'FLEET_VEHICLE_DELETE', 'id=' . $vehicle_id);
        redirect('vehicles.php');
    } catch (Exception $e) {
        $_SESSION['error'] = 'خطا در حذف: ' . $e->getMessage();
    }
}

// Vehicles list
$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$where = [];
$params = [];
if ($search !== '') {
    $where[] = "(license_plate LIKE ? OR make LIKE ? OR model LIKE ? OR vin LIKE ?)";
    for ($i=0;$i<4;$i++) { $params[] = '%' . $search . '%'; }
}
if ($status_filter !== '') { $where[] = "status = ?"; $params[] = $status_filter; }
$where_clause = count($where) ? ('WHERE ' . implode(' AND ', $where)) : '';
$stmt = $pdo->prepare("SELECT * FROM company_vehicles $where_clause ORDER BY created_at DESC");
$stmt->execute($params);
$vehicles = $stmt->fetchAll();
$total_count = (int)($pdo->query("SELECT COUNT(*) as c FROM company_vehicles")->fetch()['c'] ?? 0);

?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>مدیریت ناوگان خودرو</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
  <?php include 'navbar.php'; ?>
  <div class="container mt-5 pt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2><i class="fas fa-car"></i> مدیریت ناوگان خودرو</h2>
      <span class="badge bg-secondary"><?= count($vehicles) ?> / <?= $total_count ?> خودرو</span>
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
      <div class="card-header bg-primary text-white"><i class="fas fa-plus-circle"></i> افزودن خودرو</div>
      <div class="card-body">
        <form method="POST" enctype="multipart/form-data" class="row g-3">
          <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
          <div class="col-md-3">
            <label class="form-label">پلاک *</label>
            <input type="text" name="license_plate" class="form-control" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">VIN</label>
            <input type="text" name="vin" class="form-control">
          </div>
          <div class="col-md-3">
            <label class="form-label">برند</label>
            <input type="text" name="make" class="form-control">
          </div>
          <div class="col-md-3">
            <label class="form-label">مدل</label>
            <input type="text" name="model" class="form-control">
          </div>
          <div class="col-md-2">
            <label class="form-label">سال ساخت</label>
            <input type="number" name="manufacture_year" class="form-control">
          </div>
          <div class="col-md-2">
            <label class="form-label">رنگ</label>
            <input type="text" name="color" class="form-control">
          </div>
          <div class="col-md-2">
            <label class="form-label">نوع سوخت</label>
            <select name="fuel_type" class="form-select">
              <option value="gasoline">بنزین</option>
              <option value="diesel">گازوئیل</option>
              <option value="cng">CNG</option>
              <option value="hybrid">هیبرید</option>
              <option value="electric">برقی</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">گیربکس</label>
            <select name="transmission" class="form-select">
              <option value="automatic">اتوماتیک</option>
              <option value="manual">دنده‌ای</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">ظرفیت سرنشین</label>
            <input type="number" name="seating_capacity" class="form-control" value="5">
          </div>
          <div class="col-md-2">
            <label class="form-label">کیلومتر فعلی</label>
            <input type="number" name="current_odometer_km" class="form-control" value="0">
          </div>
          <div class="col-md-3">
            <label class="form-label">سررسید بیمه</label>
            <input type="date" name="insurance_expiry" class="form-control">
          </div>
          <div class="col-md-3">
            <label class="form-label">سررسید معاینه فنی</label>
            <input type="date" name="inspection_expiry" class="form-control">
          </div>
          <div class="col-md-3">
            <label class="form-label">کارت خودرو (فایل)</label>
            <input type="file" name="registration_card" class="form-control" accept=".pdf,image/*">
          </div>
          <div class="col-md-3">
            <label class="form-label">بیمه‌نامه (فایل)</label>
            <input type="file" name="insurance_doc" class="form-control" accept=".pdf,image/*">
          </div>
          <div class="col-12">
            <label class="form-label">توضیحات</label>
            <textarea name="description" rows="2" class="form-control"></textarea>
          </div>
          <div class="col-12">
            <button type="submit" name="add_vehicle" class="btn btn-success"><i class="fas fa-save"></i> ثبت خودرو</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-header bg-info text-white"><i class="fas fa-list"></i> لیست خودروها</div>
      <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
          <div class="col-md-4"><input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="جستجو: پلاک، برند، مدل، VIN"></div>
          <div class="col-md-3">
            <select name="status" class="form-select">
              <option value="">همه وضعیت‌ها</option>
              <option value="available" <?= $status_filter==='available'?'selected':'' ?>>آزاد</option>
              <option value="assigned" <?= $status_filter==='assigned'?'selected':'' ?>>در اختیار</option>
              <option value="maintenance" <?= $status_filter==='maintenance'?'selected':'' ?>>تعمیرات</option>
              <option value="inactive" <?= $status_filter==='inactive'?'selected':'' ?>>غیرفعال</option>
              <option value="reserved" <?= $status_filter==='reserved'?'selected':'' ?>>رزرو</option>
            </select>
          </div>
          <div class="col-md-2"><button class="btn btn-primary w-100"><i class="fas fa-filter"></i> فیلتر</button></div>
          <div class="col-md-2"><a href="vehicles.php" class="btn btn-outline-secondary w-100"><i class="fas fa-times"></i> حذف فیلتر</a></div>
        </form>
        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead>
              <tr>
                <th>کد</th>
                <th>پلاک</th>
                <th>برند/مدل/سال</th>
                <th>وضعیت</th>
                <th>کیلومتر</th>
                <th>بیمه</th>
                <th>معاینه</th>
                <th>عملیات</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($vehicles) === 0): ?>
                <tr><td colspan="8" class="text-center">خودرویی یافت نشد</td></tr>
              <?php else: foreach ($vehicles as $v): ?>
                <tr>
                  <td><?= htmlspecialchars($v['code']) ?></td>
                  <td><span class="badge bg-secondary"><?= htmlspecialchars($v['license_plate']) ?></span></td>
                  <td><?= htmlspecialchars(trim(($v['make']??'') . ' ' . ($v['model']??''))) ?> <?= $v['manufacture_year']? '(' . (int)$v['manufacture_year'] . ')':'' ?></td>
                  <td>
                    <?php
                      $cls = 'secondary';
                      if ($v['status']==='available') $cls='success';
                      if ($v['status']==='assigned') $cls='primary';
                      if ($v['status']==='maintenance') $cls='warning';
                      if ($v['status']==='inactive') $cls='dark';
                      if ($v['status']==='reserved') $cls='info';
                    ?>
                    <span class="badge bg-<?= $cls ?>"><?= $v['status'] ?></span>
                  </td>
                  <td><?= number_format((int)$v['current_odometer_km']) ?> km</td>
                  <td><?= $v['insurance_expiry'] ? jalaliDate($v['insurance_expiry']) : '-' ?></td>
                  <td><?= $v['inspection_expiry'] ? jalaliDate($v['inspection_expiry']) : '-' ?></td>
                  <td>
                    <?php if (($_SESSION['role'] ?? '') === 'ادمین'): ?>
                      <a href="vehicles.php?delete_id=<?= (int)$v['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('حذف خودرو؟')"><i class="fas fa-trash"></i></a>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
