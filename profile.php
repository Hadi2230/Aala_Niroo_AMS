<?php
session_start();
include 'config.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

$asset_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($asset_id <= 0) { header('Location: reports.php'); exit(); }

// اطلاعات دستگاه
$stmt = $pdo->prepare("SELECT a.*, at.display_name AS type_name FROM assets a LEFT JOIN asset_types at ON a.type_id = at.id WHERE a.id = ?");
$stmt->execute([$asset_id]);
$asset = $stmt->fetch();
if (!$asset) { header('Location: reports.php'); exit(); }

// انتساب‌های مرتبط و مشتری فعلی (آخرین انتساب)
$assign = $pdo->prepare("SELECT aa.*, c.full_name, c.phone, c.company FROM asset_assignments aa JOIN customers c ON aa.customer_id=c.id WHERE aa.asset_id = ? ORDER BY aa.created_at DESC LIMIT 1");
$assign->execute([$asset_id]);
$current = $assign->fetch();

// تصاویر
$images = $pdo->prepare("SELECT * FROM asset_images WHERE asset_id = ?");
$images->execute([$asset_id]);
$images = $images->fetchAll();
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>