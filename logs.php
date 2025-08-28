<?php
session_start();
include 'config.php';

// فقط ادمین
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'ادمین') {
    header('Location: login.php');
    exit();
}

$user_filter = $_GET['user_id'] ?? '';
$action_filter = $_GET['action'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$query = "SELECT sl.*, u.username FROM system_logs sl LEFT JOIN users u ON sl.user_id = u.id WHERE 1=1";
$params = [];

if ($user_filter !== '') { $query .= " AND sl.user_id = ?"; $params[] = (int)$user_filter; }
if ($action_filter !== '') { $query .= " AND sl.action = ?"; $params[] = $action_filter; }
if ($date_from !== '') { $query .= " AND sl.created_at >= ?"; $params[] = $date_from . ' 00:00:00'; }
if ($date_to !== '') { $query .= " AND sl.created_at <= ?"; $params[] = $date_to . ' 23:59:59'; }

$query .= " ORDER BY sl.created_at DESC LIMIT 500";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$users = $pdo->query("SELECT id, username FROM users ORDER BY username")->fetchAll();
$actions = $pdo->query("SELECT DISTINCT action FROM system_logs ORDER BY action")->fetchAll();
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لاگ سیستم - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>لاگ فعالیت سیستم</span>
                <span class="badge bg-info">حداکثر 500 رکورد آخر</span>
            </div>
            <div class="card-body">
                <form method="get" class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label">کاربر</label>
                        <select name="user_id" class="form-select">
                            <option value="">همه</option>
                            <?php foreach($users as $u): ?>
                                <option value="<?= $u['id'] ?>" <?= $user_filter == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">عملیات</label>
                        <select name="action" class="form-select">
                            <option value="">همه</option>
                            <?php foreach($actions as $a): ?>
                                <option value="<?= htmlspecialchars($a['action']) ?>" <?= $action_filter == $a['action'] ? 'selected' : '' ?>><?= htmlspecialchars($a['action']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">از تاریخ</label>
                        <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">تا تاریخ</label>
                        <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>">
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button class="btn btn-primary" type="submit">اعمال فیلتر</button>
                        <a class="btn btn-secondary" href="logs.php">حذف فیلتر</a>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>کاربر</th>
                                <th>عملیات</th>
                                <th>شرح</th>
                                <th>IP</th>
                                <th>Agent</th>
                                <th>زمان</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($logs as $i => $row): ?>
                                <tr>
                                    <td><?= $i+1 ?></td>
                                    <td><?= htmlspecialchars($row['username'] ?? '-') ?></td>
                                    <td><span class="badge bg-info"><?= htmlspecialchars($row['action']) ?></span></td>
                                    <td><?= htmlspecialchars($row['description'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($row['ip_address'] ?? '-') ?></td>
                                    <td class="text-truncate" style="max-width:240px;"><?= htmlspecialchars($row['user_agent'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($row['created_at']) ?></td>
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
