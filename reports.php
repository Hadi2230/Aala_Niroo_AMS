<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// حذف دستگاه
if (isset($_GET['delete_id']) && $_SESSION['role'] == 'ادمین') {
    $delete_id = $_GET['delete_id'];
    $stmt = $pdo->prepare("DELETE FROM assets WHERE id = ?");
    $stmt->execute([$delete_id]);
    $success = "دستگاه با موفقیت حذف شد!";
    header('Location: reports.php?success=' . urlencode($success));
    exit();
}

// دریافت همه دستگاه‌ها با اطلاعات کامل
$stmt = $pdo->query("
    SELECT a.*, at.display_name as type_name, at.name as type_code 
    FROM assets a
    JOIN asset_types at ON a.type_id = at.id
    ORDER BY a.created_at DESC
");
$assets = $stmt->fetchAll();

// دریافت فیلدهای هر نوع برای نمایش در Modal
$field_names = [];
$types = $pdo->query("SELECT * FROM asset_types")->fetchAll();
foreach ($types as $type) {
    $stmt = $pdo->prepare("SELECT id, field_name FROM asset_fields WHERE type_id = ?");
    $stmt->execute([$type['id']]);
    $fields = $stmt->fetchAll();
    foreach ($fields as $field) {
        $field_names[$type['name']][$field['id']] = $field['field_name'];
    }
}

// دریافت عکس‌های هر دستگاه
$asset_images = [];
foreach ($assets as $asset) {
    $stmt = $pdo->prepare("SELECT * FROM asset_images WHERE asset_id = ?");
    $stmt->execute([$asset['id']]);
    $asset_images[$asset['id']] = $stmt->fetchAll();
}

// پردازش داده‌های JSON
foreach ($assets as &$asset) {
    $asset['custom_data'] = json_decode($asset['custom_data'], true);
}
unset($asset);

// نمایش پیام موفقیت
if (isset($_GET['success'])) {
    $success = urldecode($_GET['success']);
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>گزارشات دستگاه‌ها - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">اعلا نیرو</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">داشبورد</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="assets.php">ثبت دستگاه جدید</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="reports.php">گزارشات دستگاه‌ها</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="assignments.php">انتساب دستگاه</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">خروج</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <h2 class="text-center">گزارشات دستگاه‌های ثبت شده</h2>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>همه دستگاه‌ها</span>
                <a href="assets.php" class="btn btn-success btn-sm">ثبت دستگاه جدید</a>
            </div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>نام دستگاه</th>
                            <th>نوع دستگاه</th>
                            <th>شماره سریال</th>
                            <th>تاریخ خرید</th>
                            <th>وضعیت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assets as $asset): ?>
                        <tr>
                            <td><?php echo $asset['id']; ?></td>
                            <td><?php echo $asset['name']; ?></td>
                            <td><?php echo $asset['type_name']; ?></td>
                            <td><?php echo $asset['serial_number']; ?></td>
                            <td><?php echo $asset['purchase_date']; ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    if ($asset['status'] == 'فعال') echo 'success';
                                    elseif ($asset['status'] == 'غیرفعال') echo 'danger';
                                    else echo 'warning';
                                ?>">
                                    <?php echo $asset['status']; ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" 
                                            data-bs-target="#detailsModal<?php echo $asset['id']; ?>">
                                        جزئیات
                                    </button>
                                    <a href="edit_asset.php?id=<?php echo $asset['id']; ?>" class="btn btn-sm btn-warning">ویرایش</a>
                                    <?php if ($_SESSION['role'] == 'ادمین'): ?>
                                    <a href="reports.php?delete_id=<?php echo $asset['id']; ?>" class="btn btn-sm btn-danger" 
                                       onclick="return confirm('آیا از حذف این دستگاه مطمئن هستید؟')">حذف</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal های جزئیات -->
    <?php foreach ($assets as $asset): ?>
    <div class="modal fade" id="detailsModal<?php echo $asset['id']; ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">جزئیات دستگاه #<?php echo $asset['id']; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>نام دستگاه:</strong> <?php echo $asset['name']; ?></p>
                            <p><strong>نوع دستگاه:</strong> <?php echo $asset['type_name']; ?></p>
                            <p><strong>شماره سریال:</strong> <?php echo $asset['serial_number']; ?></p>
                            <p><strong>تاریخ خرید:</strong> <?php echo $asset['purchase_date']; ?></p>
                            <p><strong>وضعیت:</strong> 
                                <span class="badge bg-<?php 
                                    if ($asset['status'] == 'فعال') echo 'success';
                                    elseif ($asset['status'] == 'غیرفعال') echo 'danger';
                                    else echo 'warning';
                                ?>">
                                    <?php echo $asset['status']; ?>
                                </span>
                            </p>
                            <p><strong>تاریخ ثبت:</strong> <?php echo $asset['created_at']; ?></p>
                        </div>
                        <div class="col-md-6">
                            <?php if ($asset['asset_type'] === 'ژنراتور'): ?>
                                <h6>مشخصات ژنراتور:</h6>
                                <p><strong>برند:</strong> <?php echo $asset['brand']; ?></p>
                                <p><strong>مدل موتور:</strong> <?php echo $asset['engine_model']; ?></p>
                                <p><strong>سریال موتور:</strong> <?php echo $asset['engine_serial']; ?></p>
                                <p><strong>مدل آلترناتور:</strong> <?php echo $asset['alternator_model']; ?></p>
                                <p><strong>سریال آلترناتور:</strong> <?php echo $asset['alternator_serial']; ?></p>
                                <p><strong>ظرفیت توان:</strong> <?php echo $asset['power_capacity']; ?> کیلووات</p>
                                
                                <?php if (!empty($asset_images[$asset['id']])): ?>
                                    <h6 class="mt-3">عکس‌ها:</h6>
                                    <div class="row">
                                        <?php foreach ($asset_images[$asset['id']] as $image): ?>
                                            <div class="col-md-6 mb-2">
                                                <strong><?php echo $image['field_name']; ?>:</strong><br>
                                                <img src="<?php echo $image['image_path']; ?>" class="img-thumbnail" style="max-width: 150px;">
                                                <a href="<?php echo $image['image_path']; ?>" target="_blank" class="d-block mt-1">مشاهده کامل</a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <h6>مشخصات اختصاصی:</h6>
                                <?php if ($asset['custom_data'] && !empty($asset['custom_data'])): ?>
                                    <?php foreach ($asset['custom_data'] as $field_id => $value): ?>
                                        <?php if (!empty($value)): ?>
                                            <p>
                                                <strong>
                                                    <?php echo $field_names[$asset['type_code']][$field_id] ?? 'فیلد ' . $field_id; ?>:
                                                </strong> 
                                                <?php echo $value; ?>
                                            </p>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted">هیچ مشخصه اختصاصی ثبت نشده است.</p>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="edit_asset.php?id=<?php echo $asset['id']; ?>" class="btn btn-warning">ویرایش این دستگاه</a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>