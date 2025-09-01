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
    <style>
        .no-print { }
        .print-header { display: none; border-bottom: 2px solid #000; margin-bottom: 16px; padding-bottom: 8px; }
        .print-title { font-weight: 700; font-size: 20px; }
        .print-meta { font-size: 12px; color: #555; }

        @media print {
            body { background: #fff !important; color: #000 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            nav, .navbar, .navbar-custom, .theme-switch, .no-print, .btn, .alert { display: none !important; }
            a[href]:after { content: "" !important; }
            .card { border: 1px solid #000 !important; box-shadow: none !important; }
            .card-header { background: #f2f2f2 !important; color: #000 !important; }
            .print-header { display: block !important; }
            /* Only print the area */
            body * { visibility: hidden; }
            #print-area, #print-area * { visibility: visible; }
            #print-area { position: relative !important; overflow: visible !important; }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4" id="print-area">
        <div class="print-header">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="print-title">اعلا نیرو - پروفایل دستگاه</div>
                    <div class="print-meta">تاریخ چاپ: <?= date('Y-m-d H:i') ?></div>
                </div>
                <div class="text-end">
                    <div class="print-meta">نام شرکت: اعلا نیرو</div>
                    <?php if(isset($_SESSION['username'])): ?>
                        <div class="print-meta">کاربر: <?= htmlspecialchars($_SESSION['username']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2><i class="fas fa-id-card"></i> پروفایل دستگاه #<?= $asset['id'] ?></h2>
            <button class="btn btn-primary no-print" onclick="window.print()"><i class="fas fa-print"></i> چاپ</button>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>اطلاعات کلی</span>
                        <button class="btn btn-sm btn-outline-primary no-print" data-bs-toggle="collapse" data-bs-target="#editGeneral">ویرایش</button>
                    </div>
                    <div class="card-body">
                        <p><strong>نام:</strong> <?= htmlspecialchars($asset['name']) ?></p>
                        <p><strong>نوع:</strong> <?= htmlspecialchars($asset['type_name'] ?? '-') ?></p>
                        <p><strong>وضعیت:</strong> <?= htmlspecialchars($asset['status']) ?></p>
                        <p><strong>سریال:</strong> <?= htmlspecialchars($asset['serial_number'] ?? '-') ?></p>
                        <p><strong>تاریخ خرید:</strong> <?= htmlspecialchars($asset['purchase_date'] ?? '-') ?></p>
                        <p><strong>برند/مدل:</strong> <?= htmlspecialchars(($asset['brand'] ?? '') . (($asset['model'] ?? '') ? ' / ' . $asset['model'] : '')) ?></p>

                        <div id="editGeneral" class="collapse mt-3">
                            <form method="post" action="save_asset_profile.php">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="asset_id" value="<?= (int)$asset['id'] ?>">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">نام</label>
                                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($asset['name']) ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">شماره سریال</label>
                                        <input type="text" name="serial_number" class="form-control" value="<?= htmlspecialchars($asset['serial_number'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">تاریخ خرید</label>
                                        <input type="text" name="purchase_date" class="form-control jalali-date" placeholder="YYYY/MM/DD" value="<?= htmlspecialchars($asset['purchase_date'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">وضعیت</label>
                                        <select name="status" class="form-select">
                                            <option value="فعال" <?= ($asset['status']==='فعال'?'selected':'') ?>>فعال</option>
                                            <option value="غیرفعال" <?= ($asset['status']==='غیرفعال'?'selected':'') ?>>غیرفعال</option>
                                            <option value="در حال تعمیر" <?= ($asset['status']==='در حال تعمیر'?'selected':'') ?>>در حال تعمیر</option>
                                            <option value="آماده بهره‌برداری" <?= ($asset['status']==='آماده بهره‌برداری'?'selected':'') ?>>آماده بهره‌برداری</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">برند</label>
                                        <input type="text" name="brand" class="form-control" value="<?= htmlspecialchars($asset['brand'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">مدل</label>
                                        <input type="text" name="model" class="form-control" value="<?= htmlspecialchars($asset['model'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <button class="btn btn-success" type="submit">ذخیره اطلاعات کلی</button>
                                </div>
                            </form>
                        </div>
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
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>مشخصات فنی</span>
                        <button class="btn btn-sm btn-outline-primary no-print" data-bs-toggle="collapse" data-bs-target="#editSpecs">ویرایش مشخصات</button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <tbody>
                                    <tr>
                                        <th style="width:25%">مدل موتور</th>
                                        <td><?= htmlspecialchars($asset['engine_model'] ?? '-') ?></td>
                                        <th style="width:25%">سریال موتور</th>
                                        <td><?= htmlspecialchars($asset['engine_serial'] ?? '-') ?></td>
                                    </tr>
                                    <tr>
                                        <th>مدل آلترناتور</th>
                                        <td><?= htmlspecialchars($asset['alternator_model'] ?? '-') ?></td>
                                        <th>سریال آلترناتور</th>
                                        <td><?= htmlspecialchars($asset['alternator_serial'] ?? '-') ?></td>
                                    </tr>
                                    <tr>
                                        <th>مدل دستگاه</th>
                                        <td><?= htmlspecialchars($asset['device_model'] ?? '-') ?></td>
                                        <th>سریال دستگاه</th>
                                        <td><?= htmlspecialchars($asset['device_serial'] ?? '-') ?></td>
                                    </tr>
                                    <tr>
                                        <th>ظرفیت توان</th>
                                        <td><?= htmlspecialchars($asset['power_capacity'] ?? '-') ?></td>
                                        <th>نوع سوخت</th>
                                        <td><?= htmlspecialchars($asset['engine_type'] ?? '-') ?></td>
                                    </tr>
                                    <tr>
                                        <th>مدل کنترل پنل</th>
                                        <td><?= htmlspecialchars($asset['control_panel_model'] ?? '-') ?></td>
                                        <th>مدل بریکر</th>
                                        <td><?= htmlspecialchars($asset['breaker_model'] ?? '-') ?></td>
                                    </tr>
                                    <tr>
                                        <th>مشخصات تانک سوخت</th>
                                        <td><?= htmlspecialchars($asset['fuel_tank_specs'] ?? '-') ?></td>
                                        <th>باتری</th>
                                        <td><?= htmlspecialchars($asset['battery'] ?? '-') ?></td>
                                    </tr>
                                    <tr>
                                        <th>باتری شارژر</th>
                                        <td><?= htmlspecialchars($asset['battery_charger'] ?? '-') ?></td>
                                        <th>هیتر</th>
                                        <td><?= htmlspecialchars($asset['heater'] ?? '-') ?></td>
                                    </tr>
                                    <tr>
                                        <th>حجم روغن</th>
                                        <td><?= htmlspecialchars($asset['oil_capacity'] ?? '-') ?></td>
                                        <th>حجم آب رادیاتور</th>
                                        <td><?= htmlspecialchars($asset['radiator_capacity'] ?? '-') ?></td>
                                    </tr>
                                    <tr>
                                        <th>ضدیخ</th>
                                        <td><?= htmlspecialchars($asset['antifreeze'] ?? '-') ?></td>
                                        <th>سایر اقلام مولد</th>
                                        <td><?= htmlspecialchars($asset['other_items'] ?? '-') ?></td>
                                    </tr>
                                    <tr>
                                        <th>تاریخ ورود کارگاه</th>
                                        <td><?= htmlspecialchars($asset['workshop_entry_date'] ?? '-') ?></td>
                                        <th>تاریخ خروج کارگاه</th>
                                        <td><?= htmlspecialchars($asset['workshop_exit_date'] ?? '-') ?></td>
                                    </tr>
                                    <tr>
                                        <th>لینک دیتاشیت</th>
                                        <td><?= htmlspecialchars($asset['datasheet_link'] ?? '-') ?></td>
                                        <th>منوال موتور</th>
                                        <td><?= htmlspecialchars($asset['engine_manual_link'] ?? '-') ?></td>
                                    </tr>
                                    <tr>
                                        <th>منوال آلترناتور</th>
                                        <td><?= htmlspecialchars($asset['alternator_manual_link'] ?? '-') ?></td>
                                        <th>منوال کنترل پنل</th>
                                        <td><?= htmlspecialchars($asset['control_panel_manual_link'] ?? '-') ?></td>
                                    </tr>
                                    <tr>
                                        <th>توضیحات</th>
                                        <td colspan="3"><?php echo nl2br(htmlspecialchars($asset['description'] ?? '-')); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div id="editSpecs" class="collapse mt-3">
                            <form method="post" action="save_asset_profile.php">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="asset_id" value="<?= (int)$asset['id'] ?>">
                                <div class="row g-3">
                                    <div class="col-md-3"><label class="form-label">مدل موتور</label><input name="engine_model" class="form-control" value="<?= htmlspecialchars($asset['engine_model'] ?? '') ?>"></div>
                                    <div class="col-md-3"><label class="form-label">سریال موتور</label><input name="engine_serial" class="form-control" value="<?= htmlspecialchars($asset['engine_serial'] ?? '') ?>"></div>
                                    <div class="col-md-3"><label class="form-label">مدل آلترناتور</label><input name="alternator_model" class="form-control" value="<?= htmlspecialchars($asset['alternator_model'] ?? '') ?>"></div>
                                    <div class="col-md-3"><label class="form-label">سریال آلترناتور</label><input name="alternator_serial" class="form-control" value="<?= htmlspecialchars($asset['alternator_serial'] ?? '') ?>"></div>
                                    <div class="col-md-3"><label class="form-label">مدل دستگاه</label><input name="device_model" class="form-control" value="<?= htmlspecialchars($asset['device_model'] ?? '') ?>"></div>
                                    <div class="col-md-3"><label class="form-label">سریال دستگاه</label><input name="device_serial" class="form-control" value="<?= htmlspecialchars($asset['device_serial'] ?? '') ?>"></div>
                                    <div class="col-md-3"><label class="form-label">ظرفیت توان</label><input name="power_capacity" class="form-control" value="<?= htmlspecialchars($asset['power_capacity'] ?? '') ?>"></div>
                                    <div class="col-md-3"><label class="form-label">نوع سوخت/موتور</label><input name="engine_type" class="form-control" value="<?= htmlspecialchars($asset['engine_type'] ?? '') ?>"></div>
                                    <div class="col-md-3"><label class="form-label">مدل کنترل پنل</label><input name="control_panel_model" class="form-control" value="<?= htmlspecialchars($asset['control_panel_model'] ?? '') ?>"></div>
                                    <div class="col-md-3"><label class="form-label">مدل بریکر</label><input name="breaker_model" class="form-control" value="<?= htmlspecialchars($asset['breaker_model'] ?? '') ?>"></div>
                                    <div class="col-md-6"><label class="form-label">مشخصات تانک سوخت</label><input name="fuel_tank_specs" class="form-control" value="<?= htmlspecialchars($asset['fuel_tank_specs'] ?? '') ?>"></div>
                                    <div class="col-md-3"><label class="form-label">باتری</label><input name="battery" class="form-control" value="<?= htmlspecialchars($asset['battery'] ?? '') ?>"></div>
                                    <div class="col-md-3"><label class="form-label">باتری شارژر</label><input name="battery_charger" class="form-control" value="<?= htmlspecialchars($asset['battery_charger'] ?? '') ?>"></div>
                                    <div class="col-md-3"><label class="form-label">هیتر</label><input name="heater" class="form-control" value="<?= htmlspecialchars($asset['heater'] ?? '') ?>"></div>
                                    <div class="col-md-3"><label class="form-label">حجم روغن</label><input name="oil_capacity" class="form-control" value="<?= htmlspecialchars($asset['oil_capacity'] ?? '') ?>"></div>
                                    <div class="col-md-3"><label class="form-label">حجم آب رادیاتور</label><input name="radiator_capacity" class="form-control" value="<?= htmlspecialchars($asset['radiator_capacity'] ?? '') ?>"></div>
                                    <div class="col-md-3"><label class="form-label">ضدیخ</label><input name="antifreeze" class="form-control" value="<?= htmlspecialchars($asset['antifreeze'] ?? '') ?>"></div>
                                    <div class="col-md-6"><label class="form-label">سایر اقلام مولد</label><input name="other_items" class="form-control" value="<?= htmlspecialchars($asset['other_items'] ?? '') ?>"></div>
                                    <div class="col-md-3"><label class="form-label">تاریخ ورود کارگاه</label><input name="workshop_entry_date" class="form-control jalali-date" placeholder="YYYY/MM/DD" value="<?= htmlspecialchars($asset['workshop_entry_date'] ?? '') ?>"></div>
                                    <div class="col-md-3"><label class="form-label">تاریخ خروج کارگاه</label><input name="workshop_exit_date" class="form-control jalali-date" placeholder="YYYY/MM/DD" value="<?= htmlspecialchars($asset['workshop_exit_date'] ?? '') ?>"></div>
                                    <div class="col-md-6"><label class="form-label">لینک دیتاشیت</label><input name="datasheet_link" class="form-control" value="<?= htmlspecialchars($asset['datasheet_link'] ?? '') ?>"></div>
                                    <div class="col-md-6"><label class="form-label">منوال موتور</label><input name="engine_manual_link" class="form-control" value="<?= htmlspecialchars($asset['engine_manual_link'] ?? '') ?>"></div>
                                    <div class="col-md-6"><label class="form-label">منوال آلترناتور</label><input name="alternator_manual_link" class="form-control" value="<?= htmlspecialchars($asset['alternator_manual_link'] ?? '') ?>"></div>
                                    <div class="col-md-6"><label class="form-label">منوال کنترل پنل</label><input name="control_panel_manual_link" class="form-control" value="<?= htmlspecialchars($asset['control_panel_manual_link'] ?? '') ?>"></div>
                                    <div class="col-md-12"><label class="form-label">توضیحات</label><textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($asset['description'] ?? '') ?></textarea></div>
                                </div>
                                <div class="mt-3">
                                    <button class="btn btn-success" type="submit">ذخیره مشخصات فنی</button>
                                </div>
                            </form>
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
