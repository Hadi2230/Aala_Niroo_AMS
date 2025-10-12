<?php
session_start();
require_once __DIR__ . '/config.php';
require_auth('اپراتور');

logAction($pdo, 'VIEW_VEHICLES', 'مشاهده صفحه مدیریت خودروها');

$success = $error = null;

// افزودن/ویرایش خودرو
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $action = $_POST['action'] ?? '';
    $plate_number = sanitizeInput($_POST['plate_number'] ?? '');
    $vin = sanitizeInput($_POST['vin'] ?? '');
    $make = sanitizeInput($_POST['make'] ?? '');
    $model = sanitizeInput($_POST['model'] ?? '');
    $year = (int)($_POST['year'] ?? 0);
    $color = sanitizeInput($_POST['color'] ?? '');
    $fuel_type = sanitizeInput($_POST['fuel_type'] ?? 'بنزین');
    $ownership_type = sanitizeInput($_POST['ownership_type'] ?? 'شرکتی');
    $status = sanitizeInput($_POST['status'] ?? 'فعال');
    $current_odometer = (int)($_POST['current_odometer'] ?? 0);
    $insurance_expiry = sanitizeInput($_POST['insurance_expiry'] ?? null);
    $inspection_expiry = sanitizeInput($_POST['inspection_expiry'] ?? null);
    $purchase_date = sanitizeInput($_POST['purchase_date'] ?? null);
    $notes = sanitizeInput($_POST['notes'] ?? '');

    try {
        if ($action === 'create') {
            $stmt = $pdo->prepare("INSERT INTO vehicles (plate_number, vin, make, model, year, color, fuel_type, ownership_type, status, current_odometer, insurance_expiry, inspection_expiry, purchase_date, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$plate_number, $vin, $make, $model, $year, $color, $fuel_type, $ownership_type, $status, $current_odometer, $insurance_expiry ?: null, $inspection_expiry ?: null, $purchase_date ?: null, $notes]);
            $success = 'خودرو با موفقیت ثبت شد';
            logAction($pdo, 'ADD_VEHICLE', "plate=$plate_number");
        } elseif ($action === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("UPDATE vehicles SET plate_number=?, vin=?, make=?, model=?, year=?, color=?, fuel_type=?, ownership_type=?, status=?, current_odometer=?, insurance_expiry=?, inspection_expiry=?, purchase_date=?, notes=? WHERE id=?");
            $stmt->execute([$plate_number, $vin, $make, $model, $year, $color, $fuel_type, $ownership_type, $status, $current_odometer, $insurance_expiry ?: null, $inspection_expiry ?: null, $purchase_date ?: null, $notes, $id]);
            $success = 'خودرو با موفقیت به‌روزرسانی شد';
            logAction($pdo, 'UPDATE_VEHICLE', "id=$id");
        } elseif ($action === 'upload_doc') {
            $vehicle_id = (int)($_POST['vehicle_id'] ?? 0);
            $doc_type = sanitizeInput($_POST['doc_type'] ?? 'other');
            if (!empty($_FILES['doc_file']['name'])) {
                $path = uploadFile($_FILES['doc_file'], __DIR__ . '/uploads/vehicles', ['jpg','jpeg','png','pdf']);
                $rel = 'uploads/vehicles/' . basename($path);
                $expires_at = sanitizeInput($_POST['expires_at'] ?? null);
                $stmt = $pdo->prepare("INSERT INTO vehicle_documents (vehicle_id, doc_type, file_path, file_name, expires_at, uploaded_by) VALUES (?,?,?,?,?,?)");
                $stmt->execute([$vehicle_id, $doc_type, $rel, $_FILES['doc_file']['name'], $expires_at ?: null, $_SESSION['user_id'] ?? null]);
                $success = 'مدرک با موفقیت بارگذاری شد';
                logAction($pdo, 'UPLOAD_VEHICLE_DOC', "vehicle_id=$vehicle_id,type=$doc_type");
            }
        }
    } catch (Exception $e) {
        $error = 'خطا: ' . $e->getMessage();
    }
}

// حذف خودرو
if (isset($_GET['delete_id'])) {
    checkPermission('ادمین');
    $delete_id = (int)$_GET['delete_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM vehicles WHERE id = ?");
        $stmt->execute([$delete_id]);
        $success = 'حذف انجام شد';
        logAction($pdo, 'DELETE_VEHICLE', "id=$delete_id");
    } catch (Exception $e) {
        $error = 'خطا در حذف: ' . $e->getMessage();
    }
}

// فیلترها
$search = sanitizeInput($_GET['search'] ?? '');
$status_filter = sanitizeInput($_GET['status'] ?? '');
$fuel_filter = sanitizeInput($_GET['fuel'] ?? '');

$where = [];
$params = [];
if ($search !== '') {
    $where[] = "(plate_number LIKE ? OR vin LIKE ? OR make LIKE ? OR model LIKE ?)";
    $params = array_merge($params, array_fill(0, 4, "%$search%"));
}
if ($status_filter !== '') {
    $where[] = "status = ?";
    $params[] = $status_filter;
}
if ($fuel_filter !== '') {
    $where[] = "fuel_type = ?";
    $params[] = $fuel_filter;
}
$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = $pdo->prepare("SELECT * FROM vehicles $where_sql ORDER BY created_at DESC");
$stmt->execute($params);
$vehicles = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>مدیریت خودروها</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
<div class="container mt-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3><i class="fas fa-car"></i> مدیریت خودروها</h3>
  </div>

  <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

  <div class="card mb-4">
    <div class="card-header">جستجو و فیلتر</div>
    <div class="card-body">
      <form method="GET" class="row g-2">
        <div class="col-md-4">
          <input type="text" name="search" class="form-control" placeholder="پلاک، VIN، سازنده، مدل" value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <div class="col-md-3">
          <select name="status" class="form-select">
            <option value="">همه وضعیت‌ها</option>
            <?php foreach (['فعال','در حال استفاده','در تعمیر','غیرفعال'] as $s): ?>
            <option value="<?php echo $s; ?>" <?php echo ($status_filter===$s?'selected':''); ?>><?php echo $s; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <select name="fuel" class="form-select">
            <option value="">همه سوخت‌ها</option>
            <?php foreach (['بنزین','دیزل','گاز','هیبرید','الکتریکی'] as $f): ?>
            <option value="<?php echo $f; ?>" <?php echo ($fuel_filter===$f?'selected':''); ?>><?php echo $f; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <button class="btn btn-primary w-100" type="submit"><i class="fas fa-filter"></i> فیلتر</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header bg-primary text-white">افزودن/ویرایش خودرو</div>
    <div class="card-body">
      <form method="POST">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="create" id="formAction">
        <input type="hidden" name="id" id="vehicleId">
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label">پلاک *</label>
            <input type="text" class="form-control" name="plate_number" id="plate_number" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">VIN</label>
            <input type="text" class="form-control" name="vin" id="vin">
          </div>
          <div class="col-md-3">
            <label class="form-label">سازنده</label>
            <input type="text" class="form-control" name="make" id="make">
          </div>
          <div class="col-md-3">
            <label class="form-label">مدل</label>
            <input type="text" class="form-control" name="model" id="model">
          </div>
          <div class="col-md-2">
            <label class="form-label">سال</label>
            <input type="number" class="form-control" name="year" id="year" min="1970" max="2100">
          </div>
          <div class="col-md-2">
            <label class="form-label">رنگ</label>
            <input type="text" class="form-control" name="color" id="color">
          </div>
          <div class="col-md-2">
            <label class="form-label">نوع سوخت</label>
            <select class="form-select" name="fuel_type" id="fuel_type">
              <option>بنزین</option>
              <option>دیزل</option>
              <option>گاز</option>
              <option>هیبرید</option>
              <option>الکتریکی</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">مالکیت</label>
            <select class="form-select" name="ownership_type" id="ownership_type">
              <option>شرکتی</option>
              <option>اجاره‌ای</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">وضعیت</label>
            <select class="form-select" name="status" id="status">
              <option>فعال</option>
              <option>در حال استفاده</option>
              <option>در تعمیر</option>
              <option>غیرفعال</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">کیلومتر فعلی</label>
            <input type="number" class="form-control" name="current_odometer" id="current_odometer" min="0">
          </div>
          <div class="col-md-2">
            <label class="form-label">انقضای بیمه</label>
            <input type="date" class="form-control" name="insurance_expiry" id="insurance_expiry">
          </div>
          <div class="col-md-2">
            <label class="form-label">انقضای معاینه</label>
            <input type="date" class="form-control" name="inspection_expiry" id="inspection_expiry">
          </div>
          <div class="col-md-2">
            <label class="form-label">تاریخ خرید</label>
            <input type="date" class="form-control" name="purchase_date" id="purchase_date">
          </div>
          <div class="col-md-12">
            <label class="form-label">توضیحات</label>
            <textarea class="form-control" name="notes" id="notes" rows="2"></textarea>
          </div>
        </div>
        <div class="mt-3 d-flex gap-2">
          <button class="btn btn-success" type="submit"><i class="fas fa-save"></i> ذخیره</button>
          <button class="btn btn-secondary" type="button" id="resetForm">ریست</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header bg-info text-white">لیست خودروها</div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-striped align-middle">
          <thead>
            <tr>
              <th>#</th>
              <th>پلاک</th>
              <th>سازنده/مدل</th>
              <th>سال</th>
              <th>سوخت</th>
              <th>وضعیت</th>
              <th>کیلومتر</th>
              <th>بیمه</th>
              <th>معاینه</th>
              <th>عملیات</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($vehicles as $v): ?>
            <tr>
              <td><?php echo (int)$v['id']; ?></td>
              <td><?php echo htmlspecialchars($v['plate_number']); ?></td>
              <td><?php echo htmlspecialchars(trim(($v['make'] ?? '') . ' ' . ($v['model'] ?? ''))); ?></td>
              <td><?php echo htmlspecialchars($v['year']); ?></td>
              <td><?php echo htmlspecialchars($v['fuel_type']); ?></td>
              <td><span class="badge bg-secondary"><?php echo htmlspecialchars($v['status']); ?></span></td>
              <td><?php echo number_format((int)$v['current_odometer']); ?></td>
              <td><?php echo htmlspecialchars($v['insurance_expiry']); ?></td>
              <td><?php echo htmlspecialchars($v['inspection_expiry']); ?></td>
              <td>
                <div class="btn-group btn-group-sm">
                  <button class="btn btn-warning" onclick='editVehicle(<?php echo json_encode($v, JSON_UNESCAPED_UNICODE); ?>)'><i class="fas fa-edit"></i></button>
                  <?php if (($_SESSION['role'] ?? '') === 'ادمین'): ?>
                  <a class="btn btn-danger" href="vehicles.php?delete_id=<?php echo (int)$v['id']; ?>" onclick="return confirm('حذف این خودرو؟');"><i class="fas fa-trash"></i></a>
                  <?php endif; ?>
                  <button class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#uploadDocModal" onclick="setDocVehicle(<?php echo (int)$v['id']; ?>)"><i class="fas fa-file-upload"></i></button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Modal آپلود مدرک -->
<div class="modal fade" id="uploadDocModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">بارگذاری مدرک خودرو</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form method="POST" enctype="multipart/form-data">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="upload_doc">
          <input type="hidden" name="vehicle_id" id="doc_vehicle_id">
          <div class="mb-3">
            <label class="form-label">نوع مدرک</label>
            <select name="doc_type" class="form-select">
              <option value="insurance">بیمه</option>
              <option value="inspection">معاینه</option>
              <option value="registration">سند</option>
              <option value="fuel_card">کارت سوخت</option>
              <option value="other">سایر</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">تاریخ انقضا (اختیاری)</label>
            <input type="date" name="expires_at" class="form-control">
          </div>
          <div class="mb-3">
            <label class="form-label">فایل</label>
            <input type="file" name="doc_file" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required>
          </div>
          <button type="submit" class="btn btn-primary w-100">آپلود</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function editVehicle(v) {
  document.getElementById('formAction').value = 'update';
  document.getElementById('vehicleId').value = v.id || '';
  document.getElementById('plate_number').value = v.plate_number || '';
  document.getElementById('vin').value = v.vin || '';
  document.getElementById('make').value = v.make || '';
  document.getElementById('model').value = v.model || '';
  document.getElementById('year').value = v.year || '';
  document.getElementById('color').value = v.color || '';
  document.getElementById('fuel_type').value = v.fuel_type || 'بنزین';
  document.getElementById('ownership_type').value = v.ownership_type || 'شرکتی';
  document.getElementById('status').value = v.status || 'فعال';
  document.getElementById('current_odometer').value = v.current_odometer || 0;
  document.getElementById('insurance_expiry').value = v.insurance_expiry || '';
  document.getElementById('inspection_expiry').value = v.inspection_expiry || '';
  document.getElementById('purchase_date').value = v.purchase_date || '';
  document.getElementById('notes').value = v.notes || '';
}

document.getElementById('resetForm').addEventListener('click', function() {
  document.getElementById('formAction').value = 'create';
  document.getElementById('vehicleId').value = '';
  document.getElementById('plate_number').value = '';
  document.getElementById('vin').value = '';
  document.getElementById('make').value = '';
  document.getElementById('model').value = '';
  document.getElementById('year').value = '';
  document.getElementById('color').value = '';
  document.getElementById('fuel_type').value = 'بنزین';
  document.getElementById('ownership_type').value = 'شرکتی';
  document.getElementById('status').value = 'فعال';
  document.getElementById('current_odometer').value = '';
  document.getElementById('insurance_expiry').value = '';
  document.getElementById('inspection_expiry').value = '';
  document.getElementById('purchase_date').value = '';
  document.getElementById('notes').value = '';
});

function setDocVehicle(id) {
  document.getElementById('doc_vehicle_id').value = id;
}
</script>
</body>
</html>
