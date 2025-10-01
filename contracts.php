<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
include 'config.php';

$q = isset($_GET['q']) ? sanitizeInput($_GET['q']) : '';
$params = [];
$sql = "SELECT ct.*, cu.full_name as customer_name, fp.name as project_name
        FROM contracts ct
        LEFT JOIN customers cu ON ct.customer_id = cu.id
        LEFT JOIN finance_projects fp ON ct.project_id = fp.id
        WHERE ct.is_active = 1";
if ($q !== '') {
    $sql .= " AND (ct.title LIKE ? OR ct.contract_code LIKE ? OR cu.full_name LIKE ? OR fp.name LIKE ?)";
    $like = "%$q%"; $params = [$like, $like, $like, $like];
}
$sql .= " ORDER BY ct.updated_at DESC";
$st = $pdo->prepare($sql); $st->execute($params);
$contracts = $st->fetchAll();
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>قراردادها - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="fas fa-file-signature"></i> قراردادها</h2>
        <form method="GET" class="d-flex" role="search">
            <input name="q" class="form-control" type="search" placeholder="جستجو عنوان/کد/مشتری/پروژه" value="<?php echo htmlspecialchars($q); ?>">
            <button class="btn btn-outline-primary ms-2"><i class="fas fa-search"></i></button>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>عنوان</th>
                    <th>کد</th>
                    <th>مشتری</th>
                    <th>پروژه</th>
                    <th>نسخه جاری</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($contracts as $c): ?>
                <tr>
                    <td><?php echo htmlspecialchars($c['title']); ?></td>
                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($c['contract_code']); ?></span></td>
                    <td><?php echo $c['customer_name'] ?: '-'; ?></td>
                    <td><?php echo $c['project_name'] ?: '-'; ?></td>
                    <td>
                        <span class="badge bg-info">v<?php echo (int)$c['current_version']; ?></span>
                    </td>
                    <td>
                        <a class="btn btn-sm btn-outline-primary" href="contracts_file.php?id=<?php echo $c['id']; ?>&disposition=inline" target="_blank">
                            <i class="fas fa-eye"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (count($contracts) === 0): ?>
                    <tr><td colspan="6" class="text-center text-muted">قراردادی یافت نشد</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

