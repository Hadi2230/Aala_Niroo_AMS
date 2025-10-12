<?php
session_start();
require_once __DIR__ . '/config.php';
require_auth('اپراتور');

logAction($pdo, 'VIEW_VEHICLE_USAGE', 'مدیریت تحویل/عودت خودرو');

$success = $error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'start_usage') {
            $vehicle_id = (int)($_POST['vehicle_id'] ?? 0);
            $driver_user_id = !empty($_POST['driver_user_id']) ? (int)$_POST['driver_user_id'] : null;
            $driver_name = sanitizeInput($_POST['driver_name'] ?? '');
            $purpose = sanitizeInput($_POST['purpose'] ?? '');
            $start_datetime = sanitizeInput($_POST['start_datetime'] ?? date('Y-m-d H:i:s'));
            $start_odometer = (int)($_POST['start_odometer'] ?? 0);
            $start_fuel_percent = $_POST['start_fuel_percent'] !== '' ? (int)$_POST['start_fuel_percent'] : null;
            $handover_method = sanitizeInput($_POST['handover_method'] ?? 'حضوری');
            $delivered_by_user_id = $_SESSION['user_id'] ?? null;
            $start_location = sanitizeInput($_POST['start_location'] ?? '');
            $route_notes = sanitizeInput($_POST['route_notes'] ?? '');

            // جلوگیری از شروع موازی
            $stmt = $pdo->prepare("SELECT COUNT(*) c FROM vehicle_usages WHERE vehicle_id=? AND status='ongoing'");
            $stmt->execute([$vehicle_id]);
            if ((int)$stmt->fetch()['c'] > 0) throw new Exception('برای این خودرو یک ماموریت در حال انجام است');

            $stmt = $pdo->prepare("INSERT INTO vehicle_usages (vehicle_id, driver_user_id, driver_name, purpose, start_datetime, start_odometer, start_fuel_percent, handover_method, delivered_by_user_id, start_location, route_notes, status) VALUES (?,?,?,?,?,?,?,?,?,?,?, 'ongoing')");
            $stmt->execute([$vehicle_id, $driver_user_id, $driver_name, $purpose, $start_datetime, $start_odometer, $start_fuel_percent, $handover_method, $delivered_by_user_id, $start_location, $route_notes]);
            $usage_id = $pdo->lastInsertId();

            // به‌روزرسانی وضعیت خودرو
            $pdo->prepare("UPDATE vehicles SET status='در حال استفاده', current_odometer=? WHERE id=?")->execute([$start_odometer, $vehicle_id]);

            // عکس‌های شروع
            if (!empty($_FILES['start_photos']['name'][0])) {
                for ($i=0; $i<count($_FILES['start_photos']['name']); $i++) {
                    if (!$_FILES['start_photos']['name'][$i]) continue;
                    $tmp = [
                        'name' => $_FILES['start_photos']['name'][$i],
                        'type' => $_FILES['start_photos']['type'][$i],
                        'tmp_name' => $_FILES['start_photos']['tmp_name'][$i],
                        'error' => $_FILES['start_photos']['error'][$i],
                        'size' => $_FILES['start_photos']['size'][$i]
                    ];
                    $saved = uploadFile($tmp, __DIR__ . '/uploads/vehicle_usages', ['jpg','jpeg','png']);
                    $rel = 'uploads/vehicle_usages/' . basename($saved);
                    $pdo->prepare("INSERT INTO vehicle_usage_photos (usage_id, photo_type, file_path) VALUES (?,?,?)")
                        ->execute([$usage_id, 'start', $rel]);
                }
            }

            $success = 'ماموریت خودرو شروع شد';
            logAction($pdo, 'START_VEHICLE_USAGE', "usage_id=$usage_id,vehicle_id=$vehicle_id");
        }
        elseif ($action === 'end_usage') {
            $usage_id = (int)($_POST['usage_id'] ?? 0);
            $end_datetime = sanitizeInput($_POST['end_datetime'] ?? date('Y-m-d H:i:s'));
            $end_odometer = (int)($_POST['end_odometer'] ?? 0);
            $end_fuel_percent = $_POST['end_fuel_percent'] !== '' ? (int)$_POST['end_fuel_percent'] : null;
            $returned_to_user_id = $_SESSION['user_id'] ?? null;
            $end_location = sanitizeInput($_POST['end_location'] ?? '');
            $damage_notes = sanitizeInput($_POST['damage_notes'] ?? '');

            // دریافت رکورد برای محاسبه کیلومتر
            $stmt = $pdo->prepare("SELECT vehicle_id, start_odometer FROM vehicle_usages WHERE id=? AND status='ongoing'");
            $stmt->execute([$usage_id]);
            $row = $stmt->fetch();
            if (!$row) throw new Exception('رکورد یافت نشد یا قبلاً خاتمه یافته است');

            $distance_km = max(0, $end_odometer - (int)$row['start_odometer']);

            $stmt = $pdo->prepare("UPDATE vehicle_usages SET end_datetime=?, end_odometer=?, end_fuel_percent=?, returned_to_user_id=?, end_location=?, damage_notes=?, status='completed', distance_km=? WHERE id=?");
            $stmt->execute([$end_datetime, $end_odometer, $end_fuel_percent, $returned_to_user_id, $end_location, $damage_notes, $distance_km, $usage_id]);

            // عکس‌های پایان
            if (!empty($_FILES['end_photos']['name'][0])) {
                for ($i=0; $i<count($_FILES['end_photos']['name']); $i++) {
                    if (!$_FILES['end_photos']['name'][$i]) continue;
                    $tmp = [
                        'name' => $_FILES['end_photos']['name'][$i],
                        'type' => $_FILES['end_photos']['type'][$i],
                        'tmp_name' => $_FILES['end_photos']['tmp_name'][$i],
                        'error' => $_FILES['end_photos']['error'][$i],
                        'size' => $_FILES['end_photos']['size'][$i]
                    ];
                    $saved = uploadFile($tmp, __DIR__ . '/uploads/vehicle_usages', ['jpg','jpeg','png']);
                    $rel = 'uploads/vehicle_usages/' . basename($saved);
                    $pdo->prepare("INSERT INTO vehicle_usage_photos (usage_id, photo_type, file_path) VALUES (?,?,?)")
                        ->execute([$usage_id, 'end', $rel]);
                }
            }

            // به‌روزرسانی کیلومتر و وضعیت خودرو
            $pdo->prepare("UPDATE vehicles SET current_odometer=?, status='فعال' WHERE id=?")
                ->execute([$end_odometer, (int)$row['vehicle_id']]);

            $success = 'ماموریت خودرو خاتمه یافت';
            logAction($pdo, 'END_VEHICLE_USAGE', "usage_id=$usage_id");
        }
        elseif ($action === 'cancel_usage') {
            $usage_id = (int)($_POST['usage_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT vehicle_id FROM vehicle_usages WHERE id=? AND status='ongoing'");
            $stmt->execute([$usage_id]);
            $row = $stmt->fetch();
            if ($row) {
                $pdo->prepare("UPDATE vehicle_usages SET status='cancelled' WHERE id=?")->execute([$usage_id]);
                $pdo->prepare("UPDATE vehicles SET status='فعال' WHERE id=?")->execute([(int)$row['vehicle_id']]);
            }
            $success = 'ماموریت لغو شد';
            logAction($pdo, 'CANCEL_VEHICLE_USAGE', "usage_id=$usage_id");
        }
    } catch (Exception $e) {
        $error = 'خطا: ' . $e->getMessage();
    }
}

// لیست خودروهای فعال
$vehicles = $pdo->query("SELECT id, plate_number, make, model, current_odometer, status FROM vehicles ORDER BY plate_number")->fetchAll();
// ماموریت‌های جاری
$ongoing = $pdo->query("SELECT vu.*, v.plate_number FROM vehicle_usages vu JOIN vehicles v ON vu.vehicle_id=v.id WHERE vu.status='ongoing' ORDER BY vu.start_datetime DESC")->fetchAll();
// کاربران برای انتخاب راننده
$users = $pdo->query("SELECT id, full_name, username FROM users WHERE is_active = 1 ORDER BY full_name IS NULL, full_name, username")->fetchAll();

?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>تحویل/عودت خودرو</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .image-preview { max-width: 160px; max-height: 120px; margin: .25rem; display: none; }
  </style>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
<div class="container mt-4">
  <h3><i class="fas fa-route"></i> تحویل/عودت خودرو</h3>

  <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

  <div class="row g-4">
    <div class="col-lg-6">
      <div class="card">
        <div class="card-header bg-primary text-white">شروع ماموریت</div>
        <div class="card-body">
          <form method="POST" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="start_usage">
            <div class="mb-3">
              <label class="form-label">خودرو *</label>
              <select class="form-select" name="vehicle_id" required>
                <option value="">-- انتخاب --</option>
                <?php foreach ($vehicles as $v): ?>
                  <?php if ($v['status'] !== 'در حال استفاده'): ?>
                  <option value="<?php echo (int)$v['id']; ?>"><?php echo htmlspecialchars($v['plate_number'] . ' - ' . $v['make'] . ' ' . $v['model']); ?></option>
                  <?php endif; ?>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="row g-2">
              <div class="col-md-6">
                <label class="form-label">راننده (کاربر سیستم)</label>
                <select class="form-select" name="driver_user_id">
                  <option value="">- انتخاب کاربر -</option>
                  <?php foreach ($users as $u): ?>
                    <option value="<?php echo (int)$u['id']; ?>"><?php echo htmlspecialchars($u['full_name'] ?: $u['username']); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">نام راننده (غیرکاربر)</label>
                <input type="text" class="form-control" name="driver_name" placeholder="در صورت غیرکاربر">
              </div>
            </div>
            <div class="row g-2 mt-1">
              <div class="col-md-6">
                <label class="form-label">تاریخ و ساعت شروع *</label>
                <input type="datetime-local" class="form-control" name="start_datetime" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">کیلومتر شروع *</label>
                <input type="number" class="form-control" name="start_odometer" required>
              </div>
            </div>
            <div class="row g-2 mt-1">
              <div class="col-md-4">
                <label class="form-label">درصد سوخت</label>
                <input type="number" class="form-control" name="start_fuel_percent" min="0" max="100">
              </div>
              <div class="col-md-8">
                <label class="form-label">نحوه تحویل</label>
                <input type="text" class="form-control" name="handover_method" placeholder="حضوری، فرم، ...">
              </div>
            </div>
            <div class="row g-2 mt-1">
              <div class="col-md-6">
                <label class="form-label">محل شروع</label>
                <input type="text" class="form-control" name="start_location">
              </div>
              <div class="col-md-6">
                <label class="form-label">هدف/ماموریت</label>
                <input type="text" class="form-control" name="purpose">
              </div>
            </div>
            <div class="mb-2 mt-1">
              <label class="form-label">توضیحات مسیر</label>
              <textarea class="form-control" name="route_notes" rows="2"></textarea>
            </div>
            <div class="mb-2">
              <label class="form-label">عکس‌های شروع (اختیاری)</label>
              <input type="file" class="form-control" name="start_photos[]" accept=".jpg,.jpeg,.png" multiple>
            </div>
            <button class="btn btn-success" type="submit"><i class="fas fa-play"></i> شروع</button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card">
        <div class="card-header bg-success text-white">پایان ماموریت</div>
        <div class="card-body">
          <form method="POST" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="end_usage">
            <div class="mb-3">
              <label class="form-label">انتخاب ماموریت جاری *</label>
              <select class="form-select" name="usage_id" required>
                <option value="">-- انتخاب --</option>
                <?php foreach ($ongoing as $o): ?>
                  <option value="<?php echo (int)$o['id']; ?>"><?php echo htmlspecialchars($o['plate_number'] . ' | شروع: ' . $o['start_datetime']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="row g-2">
              <div class="col-md-6">
                <label class="form-label">تاریخ و ساعت پایان *</label>
                <input type="datetime-local" class="form-control" name="end_datetime" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">کیلومتر پایان *</label>
                <input type="number" class="form-control" name="end_odometer" required>
              </div>
            </div>
            <div class="row g-2 mt-1">
              <div class="col-md-4">
                <label class="form-label">درصد سوخت</label>
                <input type="number" class="form-control" name="end_fuel_percent" min="0" max="100">
              </div>
              <div class="col-md-8">
                <label class="form-label">محل پایان</label>
                <input type="text" class="form-control" name="end_location">
              </div>
            </div>
            <div class="mb-2 mt-1">
              <label class="form-label">گزارش خسارت/عیب</label>
              <textarea class="form-control" name="damage_notes" rows="2"></textarea>
            </div>
            <div class="mb-2">
              <label class="form-label">عکس‌های پایان (اختیاری)</label>
              <input type="file" class="form-control" name="end_photos[]" accept=".jpg,.jpeg,.png" multiple>
            </div>
            <div class="d-flex gap-2">
              <button class="btn btn-success" type="submit"><i class="fas fa-flag-checkered"></i> پایان</button>
            </div>
          </form>
          <hr>
          <form method="POST" class="mt-2">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="cancel_usage">
            <div class="row g-2">
              <div class="col-md-8">
                <select class="form-select" name="usage_id" required>
                  <option value="">لغو ماموریت جاری</option>
                  <?php foreach ($ongoing as $o): ?>
                    <option value="<?php echo (int)$o['id']; ?>"><?php echo htmlspecialchars($o['plate_number'] . ' | شروع: ' . $o['start_datetime']); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-4">
                <button class="btn btn-outline-danger w-100" type="submit"><i class="fas fa-ban"></i> لغو</button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="card mt-4">
    <div class="card-header">ماموریت‌های جاری</div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-striped">
          <thead>
            <tr>
              <th>#</th>
              <th>پلاک</th>
              <th>شروع</th>
              <th>کیلومتر شروع</th>
              <th>سوخت شروع</th>
              <th>راننده</th>
              <th>هدف</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($ongoing as $o): ?>
            <tr>
              <td><?php echo (int)$o['id']; ?></td>
              <td><?php echo htmlspecialchars($o['plate_number']); ?></td>
              <td><?php echo htmlspecialchars($o['start_datetime']); ?></td>
              <td><?php echo number_format((int)$o['start_odometer']); ?></td>
              <td><?php echo htmlspecialchars($o['start_fuel_percent']); ?>%</td>
              <td><?php echo htmlspecialchars($o['driver_name']); ?></td>
              <td><?php echo htmlspecialchars($o['purpose']); ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
