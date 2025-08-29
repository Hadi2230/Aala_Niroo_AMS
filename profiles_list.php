<?php
session_start();
include 'config.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

$search = $_GET['search'] ?? '';
$params = [];
$q = "SELECT a.id, a.name, a.serial_number, at.display_name AS type_name FROM assets a LEFT JOIN asset_types at ON a.type_id=at.id WHERE 1=1";
if (trim($search) !== '') {
    $q .= " AND (a.name LIKE ? OR a.serial_number LIKE ?)";
    $term = "%$search%"; $params[] = $term; $params[] = $term;
}
$q .= " ORDER BY a.created_at DESC LIMIT 200";
$stmt = $pdo->prepare($q);
$stmt->execute($params);
$assets = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پروفایل دستگاه‌ها - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container mt-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>انتخاب دستگاه برای مشاهده پروفایل</span>
                <form class="d-flex" method="get">
                    <input type="text" class="form-control me-2" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="جستجو نام/سریال">
                    <button class="btn btn-primary" type="submit">جستجو</button>
                </form>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <?php foreach($assets as $a): ?>
                        <div class="col-md-4">
                            <div class="card h-100">
                                <div class="card-body d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1"><?= htmlspecialchars($a['name']) ?></h6>
                                        <div class="text-muted small">نوع: <?= htmlspecialchars($a['type_name'] ?? '-') ?></div>
                                        <div class="text-muted small">سریال: <?= htmlspecialchars($a['serial_number'] ?? '-') ?></div>
                                        <a href="profile.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-primary mt-2">ورود به پروفایل</a>
                                    </div>
                                    <div class="text-primary display-6"><i class="fas fa-microchip"></i></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
