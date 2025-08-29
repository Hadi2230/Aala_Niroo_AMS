<?php
session_start();
include 'config.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

$asset_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($asset_id <= 0) { header('Location: reports.php'); exit(); }

// اطلاعات دستگاه
try {
    $stmt = $pdo->prepare("SELECT a.*, at.display_name AS type_name FROM assets a LEFT JOIN asset_types at ON a.type_id = at.id WHERE a.id = ?");
    $stmt->execute([$asset_id]);
    $asset = $stmt->fetch();
} catch (Throwable $ex) {
    error_log('profile.php asset fetch error: ' . $ex->getMessage());
    $asset = false;
}
if (!$asset) { header('Location: reports.php'); exit(); }

// انتساب‌های مرتبط و مشتری فعلی (آخرین انتساب)
$assign = $pdo->prepare("SELECT aa.*, c.full_name, c.phone, c.company FROM asset_assignments aa JOIN customers c ON aa.customer_id=c.id WHERE aa.asset_id = ? ORDER BY aa.created_at DESC LIMIT 1");
$assign->execute([$asset_id]);
$current = $assign->fetch();

// تصاویر
try {
    $images = $pdo->prepare("SELECT * FROM asset_images WHERE asset_id = ?");
    $images->execute([$asset_id]);
    $images = $images->fetchAll();
} catch (Throwable $ex) {
    error_log('profile.php images fetch error: ' . $ex->getMessage());
    $images = [];
}

// سرویس‌ها و تسک‌ها
try {
    $svc = $pdo->prepare("SELECT * FROM asset_services WHERE asset_id = ? ORDER BY service_date DESC, id DESC");
    $svc->execute([$asset_id]);
    $services = $svc->fetchAll();
} catch (Throwable $ex) {
    error_log('profile.php services fetch error: ' . $ex->getMessage());
    $services = [];
}

try {
    $tsk = $pdo->prepare("SELECT * FROM maintenance_tasks WHERE asset_id = ? ORDER BY FIELD(status,'برنامه‌ریزی','در حال انجام','انجام شده','لغو'), planned_date ASC, id DESC");
    $tsk->execute([$asset_id]);
    $tasks = $tsk->fetchAll();
} catch (Throwable $ex) {
    error_log('profile.php tasks fetch error: ' . $ex->getMessage());
    $tasks = [];
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پروفایل دستگاه #<?= $asset['id'] ?> - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4" id="print-area">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2><i class="fas fa-id-card"></i> پروفایل دستگاه #<?= $asset['id'] ?></h2>
            <button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print"></i> چاپ</button>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">اطلاعات کلی</div>
                    <div class="card-body">
                        <p><strong>نام:</strong> <?= htmlspecialchars($asset['name']) ?></p>
                        <p><strong>نوع:</strong> <?= htmlspecialchars($asset['type_name'] ?? '-') ?></p>
                        <p><strong>وضعیت:</strong> <?= htmlspecialchars($asset['status']) ?></p>
                        <p><strong>سریال:</strong> <?= htmlspecialchars($asset['serial_number'] ?? '-') ?></p>
                        <p><strong>تاریخ خرید:</strong> <?= htmlspecialchars($asset['purchase_date'] ?? '-') ?></p>
                        <p><strong>برند/مدل:</strong> <?= htmlspecialchars(($asset['brand'] ?? '') . (($asset['model'] ?? '') ? ' / ' . $asset['model'] : '')) ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">مشتری و انتساب فعلی</div>
                    <div class="card-body">
                        <?php if ($current): ?>
                            <p><strong>مشتری:</strong> <?= htmlspecialchars($current['full_name']) ?> (<?= htmlspecialchars($current['company'] ?? '-') ?>)</p>
                            <p><strong>تلفن:</strong> <?= htmlspecialchars($current['phone'] ?? '-') ?></p>
                            <p><strong>تاریخ انتساب:</strong> <?= htmlspecialchars($current['assignment_date'] ?? '-') ?></p>
                            <p><strong>یادداشت:</strong> <?= htmlspecialchars($current['notes'] ?? '-') ?></p>
                        <?php else: ?>
                            <p class="text-muted">فعلاً به مشتریی منتسب نیست.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-2">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">مشخصات فنی</div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3"><strong>مدل موتور:</strong> <?= htmlspecialchars($asset['engine_model'] ?? '-') ?></div>
                            <div class="col-md-3"><strong>سریال موتور:</strong> <?= htmlspecialchars($asset['engine_serial'] ?? '-') ?></div>
                            <div class="col-md-3"><strong>مدل آلترناتور:</strong> <?= htmlspecialchars($asset['alternator_model'] ?? '-') ?></div>
                            <div class="col-md-3"><strong>سریال آلترناتور:</strong> <?= htmlspecialchars($asset['alternator_serial'] ?? '-') ?></div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-3"><strong>مدل دستگاه:</strong> <?= htmlspecialchars($asset['device_model'] ?? '-') ?></div>
                            <div class="col-md-3"><strong>سریال دستگاه:</strong> <?= htmlspecialchars($asset['device_serial'] ?? '-') ?></div>
                            <div class="col-md-3"><strong>ظرفیت توان:</strong> <?= htmlspecialchars($asset['power_capacity'] ?? '-') ?></div>
                            <div class="col-md-3"><strong>نوع سوخت:</strong> <?= htmlspecialchars($asset['engine_type'] ?? '-') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($images): ?>
        <div class="row g-3 mt-2">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">تصاویر</div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($images as $img): ?>
                                <div class="col-md-3 mb-2">
                                    <div class="border p-2 rounded">
                                        <div class="small text-muted mb-1"><?= htmlspecialchars($img['field_name']) ?></div>
                                        <img src="<?= htmlspecialchars($img['image_path']) ?>" class="img-fluid rounded">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="row g-3 mt-2">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>سوابق سرویس</span>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addServiceModal">ثبت سرویس</button>
                    </div>
                    <div class="card-body">
                        <?php if (!$services): ?>
                            <p class="text-muted">سرویسی ثبت نشده است.</p>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>تاریخ</th>
                                        <th>نوع</th>
                                        <th>مجری</th>
                                        <th>خلاصه</th>
                                        <th>بعدی</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($services as $s): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($s['service_date']) ?></td>
                                            <td><?= htmlspecialchars($s['service_type']) ?></td>
                                            <td><?= htmlspecialchars($s['performed_by'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($s['summary'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($s['next_due_date'] ?? '-') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>تسک‌های نگهداشت</span>
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addTaskModal">افزودن تسک</button>
                    </div>
                    <div class="card-body">
                        <?php if (!$tasks): ?>
                            <p class="text-muted">تسکی ثبت نشده است.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>عنوان</th>
                                            <th>وضعیت</th>
                                            <th>اولویت</th>
                                            <th>تاریخ برنامه</th>
                                            <th>انجام</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($tasks as $t): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($t['title']) ?></td>
                                                <td><span class="badge bg-info"><?= htmlspecialchars($t['status']) ?></span></td>
                                                <td><span class="badge bg-secondary"><?= htmlspecialchars($t['priority']) ?></span></td>
                                                <td><?= htmlspecialchars($t['planned_date'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars($t['done_date'] ?? '-') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modals -->
        <div class="modal fade" id="addServiceModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="post" action="save_service.php">
                        <div class="modal-header">
                            <h5 class="modal-title">ثبت سرویس</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="asset_id" value="<?= $asset_id ?>">
                            <div class="mb-3">
                                <label class="form-label">تاریخ سرویس</label>
                                <input type="text" class="form-control jalali-date" name="service_date" required placeholder="YYYY/MM/DD">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">نوع سرویس</label>
                                <select class="form-select" name="service_type">
                                    <option value="دوره‌ای">دوره‌ای</option>
                                    <option value="اضطراری">اضطراری</option>
                                    <option value="نصب">نصب</option>
                                    <option value="بازدید">بازدید</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">مجری</label>
                                <input type="text" class="form-control" name="performed_by">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">خلاصه</label>
                                <input type="text" class="form-control" name="summary">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">تاریخ سررسید بعدی</label>
                                <input type="text" class="form-control jalali-date" name="next_due_date" placeholder="YYYY/MM/DD">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">توضیحات</label>
                                <textarea class="form-control" name="notes" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">انصراف</button>
                            <button class="btn btn-primary" type="submit">ذخیره</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="addTaskModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="post" action="save_task.php">
                        <div class="modal-header">
                            <h5 class="modal-title">افزودن تسک نگهداشت</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="asset_id" value="<?= $asset_id ?>">
                            <div class="mb-3">
                                <label class="form-label">عنوان *</label>
                                <input type="text" class="form-control" name="title" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">توضیحات</label>
                                <textarea class="form-control" name="description" rows="3"></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">وضعیت</label>
                                        <select class="form-select" name="status">
                                            <option value="برنامه‌ریزی">برنامه‌ریزی</option>
                                            <option value="در حال انجام">در حال انجام</option>
                                            <option value="انجام شده">انجام شده</option>
                                            <option value="لغو">لغو</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">اولویت</label>
                                        <select class="form-select" name="priority">
                                            <option value="متوسط">متوسط</option>
                                            <option value="بالا">بالا</option>
                                            <option value="کم">کم</option>
                                            <option value="فوری">فوری</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">تاریخ برنامه</label>
                                        <input type="text" class="form-control jalali-date" name="planned_date" placeholder="YYYY/MM/DD">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">تاریخ انجام</label>
                                        <input type="text" class="form-control jalali-date" name="done_date" placeholder="YYYY/MM/DD">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">انصراف</button>
                            <button class="btn btn-primary" type="submit">ذخیره</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
