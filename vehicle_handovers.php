<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
include 'config.php';
require_auth('کاربر عادی');

logAction($pdo, 'VIEW_FLEET_HANDOVERS', 'مشاهده تحویل/عودت خودرو');

// Create handover
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_handover'])) {
    try {
        $handover_id = createVehicleHandover($pdo, $_POST);
        $_SESSION['success'] = 'تحویل خودرو ثبت شد (کد: ' . $handover_id . ')';
        redirect('vehicle_handovers.php');
    } catch (Exception $e) {
        $_SESSION['error'] = 'خطا در ثبت تحویل: ' . $e->getMessage();
    }
}

// Complete return
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_return'])) {
    try {
        completeVehicleReturn($pdo, (int)$_POST['handover_id'], $_POST);
        $_SESSION['success'] = 'عودت خودرو ثبت و پرونده بسته شد';
        redirect('vehicle_handovers.php');
    } catch (Exception $e) {
        $_SESSION['error'] = 'خطا در ثبت عودت: ' . $e->getMessage();
    }
}

// Load data
$vehicles_available = $pdo->query("SELECT id, license_plate, make, model, current_odometer_km FROM company_vehicles WHERE status IN ('available','reserved') ORDER BY license_plate")->fetchAll();
$users = $pdo->query("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name")->fetchAll();
$open_handovers = $pdo->query("SELECT vh.*, cv.license_plate, cv.make, cv.model FROM vehicle_handovers vh JOIN company_vehicles cv ON vh.vehicle_id = cv.id WHERE vh.status = 'active' ORDER BY vh.handover_datetime DESC")->fetchAll();

?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>تحویل و عودت خودرو</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
  <?php include 'navbar.php'; ?>
  <div class="container mt-5 pt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2><i class="fas fa-key"></i> تحویل و عودت خودرو</h2>
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

    <div class="row g-4">
      <div class="col-lg-6">
        <div class="card">
          <div class="card-header bg-primary text-white"><i class="fas fa-share"></i> ثبت تحویل جدید</div>
          <div class="card-body">
            <form method="POST" enctype="multipart/form-data" class="row g-3">
              <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
              <div class="col-md-6">
                <label class="form-label">خودرو *</label>
                <select name="vehicle_id" class="form-select" required>
                  <option value="">انتخاب خودرو</option>
                  <?php foreach ($vehicles_available as $v): ?>
                    <option value="<?= (int)$v['id'] ?>" data-odo="<?= (int)$v['current_odometer_km'] ?>">
                      <?= htmlspecialchars($v['license_plate']) ?> - <?= htmlspecialchars(trim(($v['make']??'').' '.($v['model']??''))) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">تحویل به (کاربر سیستم)</label>
                <select name="handover_to_user_id" class="form-select">
                  <option value="">-</option>
                  <?php foreach ($users as $u): ?>
                    <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['full_name'] ?: 'کاربر #' . $u['id']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">نام تحویل گیرنده (غیرکاربر)</label>
                <input type="text" name="handover_to_name" class="form-control" placeholder="در صورت عدم انتخاب کاربر">
              </div>
              <div class="col-md-6">
                <label class="form-label">شماره تماس تحویل گیرنده</label>
                <input type="text" name="handover_to_phone" class="form-control">
              </div>
              <div class="col-md-6">
                <label class="form-label">روش تحویل</label>
                <select name="handover_method" class="form-select">
                  <option value="in_person">حضوری</option>
                  <option value="driver_pickup">اعزام راننده</option>
                  <option value="delivery_service">پیک</option>
                  <option value="other">سایر</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">تاریخ/ساعت تحویل</label>
                <input type="datetime-local" name="handover_datetime" class="form-control" value="<?= date('Y-m-d\TH:i') ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">تاریخ/ساعت عودت مورد انتظار</label>
                <input type="datetime-local" name="expected_return_datetime" class="form-control">
              </div>
              <div class="col-md-6">
                <label class="form-label">کیلومتر شروع *</label>
                <input type="number" name="odometer_km_start" class="form-control" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">بنزین (٪ شروع)</label>
                <input type="number" name="fuel_level_start_percent" class="form-control" value="100" min="0" max="100">
              </div>
              <div class="col-md-6">
                <label class="form-label">وضعیت بدنه</label>
                <select name="exterior_condition_start" class="form-select">
                  <option value="excellent">عالی</option>
                  <option value="good" selected>خوب</option>
                  <option value="fair">متوسط</option>
                  <option value="poor">ضعیف</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">وضعیت داخل کابین</label>
                <select name="interior_condition_start" class="form-select">
                  <option value="excellent">عالی</option>
                  <option value="good" selected>خوب</option>
                  <option value="fair">متوسط</option>
                  <option value="poor">ضعیف</option>
                </select>
              </div>
              <div class="col-md-12">
                <label class="form-label">توضیحات</label>
                <textarea name="notes" class="form-control" rows="2"></textarea>
              </div>
              <div class="col-md-6">
                <label class="form-label">عکس هنگام تحویل</label>
                <input type="file" name="handover_photo" class="form-control" accept="image/*">
              </div>
              <div class="col-12">
                <button class="btn btn-success" type="submit" name="create_handover"><i class="fas fa-save"></i> ثبت تحویل</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card">
          <div class="card-header bg-info text-white"><i class="fas fa-undo"></i> عودت‌های باز</div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-striped align-middle">
                <thead>
                  <tr>
                    <th>پلاک</th>
                    <th>تحویل به</th>
                    <th>تاریخ تحویل</th>
                    <th>کیلومتر شروع</th>
                    <th>عملیات</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (count($open_handovers) === 0): ?>
                    <tr><td colspan="5" class="text-center">تحویل بازی وجود ندارد</td></tr>
                  <?php else: foreach ($open_handovers as $h): ?>
                    <tr>
                      <td><span class="badge bg-secondary"><?= htmlspecialchars($h['license_plate']) ?></span></td>
                      <td><?= htmlspecialchars($h['handover_to_name'] ?: ('کاربر #' . (int)$h['handover_to_user_id'])) ?></td>
                      <td><?= htmlspecialchars($h['handover_datetime']) ?></td>
                      <td><?= number_format((int)$h['odometer_km_start']) ?> km</td>
                      <td>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#returnModal<?= (int)$h['id'] ?>"><i class="fas fa-undo"></i> ثبت عودت</button>
                      </td>
                    </tr>

                    <div class="modal fade" id="returnModal<?= (int)$h['id'] ?>" tabindex="-1">
                      <div class="modal-dialog">
                        <div class="modal-content">
                          <form method="POST" enctype="multipart/form-data">
                            <div class="modal-header">
                              <h5 class="modal-title">عودت خودرو - <?= htmlspecialchars($h['license_plate']) ?></h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                              <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                              <input type="hidden" name="handover_id" value="<?= (int)$h['id'] ?>">
                              <div class="mb-2">
                                <label class="form-label">تاریخ/ساعت عودت</label>
                                <input type="datetime-local" name="return_datetime" class="form-control" value="<?= date('Y-m-d\TH:i') ?>">
                              </div>
                              <div class="mb-2">
                                <label class="form-label">کیلومتر پایان *</label>
                                <input type="number" name="odometer_km_end" class="form-control" required min="<?= (int)$h['odometer_km_start'] ?>">
                              </div>
                              <div class="row">
                                <div class="col-md-6">
                                  <label class="form-label">بنزین (٪ پایان)</label>
                                  <input type="number" name="fuel_level_end_percent" class="form-control" value="100" min="0" max="100">
                                </div>
                                <div class="col-md-6">
                                  <label class="form-label">روش عودت</label>
                                  <select name="return_method" class="form-select">
                                    <option value="in_person">حضوری</option>
                                    <option value="dropoff">تحویل در شرکت</option>
                                    <option value="towed">یدک‌کش</option>
                                    <option value="other">سایر</option>
                                  </select>
                                </div>
                              </div>
                              <div class="row mt-2">
                                <div class="col-md-6">
                                  <label class="form-label">وضعیت بدنه</label>
                                  <select name="exterior_condition_end" class="form-select">
                                    <option value="excellent">عالی</option>
                                    <option value="good" selected>خوب</option>
                                    <option value="fair">متوسط</option>
                                    <option value="poor">ضعیف</option>
                                  </select>
                                </div>
                                <div class="col-md-6">
                                  <label class="form-label">وضعیت داخل کابین</label>
                                  <select name="interior_condition_end" class="form-select">
                                    <option value="excellent">عالی</option>
                                    <option value="good" selected>خوب</option>
                                    <option value="fair">متوسط</option>
                                    <option value="poor">ضعیف</option>
                                  </select>
                                </div>
                              </div>
                              <div class="mt-2">
                                <label class="form-label">خسارات/توضیحات</label>
                                <textarea name="damages" class="form-control" rows="2"></textarea>
                              </div>
                              <div class="mt-2">
                                <label class="form-label">عکس هنگام عودت</label>
                                <input type="file" name="return_photo" class="form-control" accept="image/*">
                              </div>
                              <div class="mt-2">
                                <label class="form-label">هزینه‌های اضافی (ریال)</label>
                                <input type="number" step="0.01" name="additional_charges" class="form-control" value="0">
                              </div>
                            </div>
                            <div class="modal-footer">
                              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                              <button type="submit" name="complete_return" class="btn btn-primary">ثبت عودت</button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
