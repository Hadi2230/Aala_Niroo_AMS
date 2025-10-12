<?php
session_start();
require_once __DIR__ . '/config.php';
require_auth('مدیر عملیات');

logAction($pdo, 'VIEW_FLEET_REPORTS', 'گزارش‌های ناوگان');

$date_from = sanitizeInput($_GET['date_from'] ?? date('Y-m-01'));
$date_to = sanitizeInput($_GET['date_to'] ?? date('Y-m-t'));
$vehicle_id = isset($_GET['vehicle_id']) && $_GET['vehicle_id'] !== '' ? (int)$_GET['vehicle_id'] : null;
$driver_id = isset($_GET['driver_id']) && $_GET['driver_id'] !== '' ? (int)$_GET['driver_id'] : null;

$where = ["vu.status='completed'", "vu.start_datetime >= ?", "vu.end_datetime <= ?"];
$params = [$date_from . ' 00:00:00', $date_to . ' 23:59:59'];
if ($vehicle_id) { $where[] = 'vu.vehicle_id = ?'; $params[] = $vehicle_id; }
if ($driver_id) { $where[] = 'vu.driver_user_id = ?'; $params[] = $driver_id; }
$where_sql = 'WHERE ' . implode(' AND ', $where);

// جمع‌ها
$stmt = $pdo->prepare("SELECT COUNT(*) trips, COALESCE(SUM(distance_km),0) total_km FROM vehicle_usages vu $where_sql");
$stmt->execute($params);
$totals = $stmt->fetch() ?: ['trips'=>0,'total_km'=>0];

// کارکرد خودروها
$stmt = $pdo->prepare("SELECT v.id, v.plate_number, v.make, v.model, COUNT(vu.id) trips, COALESCE(SUM(vu.distance_km),0) km FROM vehicles v LEFT JOIN vehicle_usages vu ON v.id=vu.vehicle_id AND vu.status='completed' AND vu.start_datetime>=? AND vu.end_datetime<=? GROUP BY v.id ORDER BY km DESC");
$stmt->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
$by_vehicle = $stmt->fetchAll();

// کارکرد رانندگان
$stmt = $pdo->prepare("SELECT u.id, COALESCE(u.full_name,u.username) driver_name, COUNT(vu.id) trips, COALESCE(SUM(vu.distance_km),0) km FROM users u LEFT JOIN vehicle_usages vu ON u.id=vu.driver_user_id AND vu.status='completed' AND vu.start_datetime>=? AND vu.end_datetime<=? GROUP BY u.id ORDER BY km DESC");
$stmt->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
$by_driver = $stmt->fetchAll();

// لیست جزئیات سفرها
$stmt = $pdo->prepare("SELECT vu.*, v.plate_number, COALESCE(u.full_name,u.username) driver_name FROM vehicle_usages vu JOIN vehicles v ON vu.vehicle_id=v.id LEFT JOIN users u ON vu.driver_user_id=u.id $where_sql ORDER BY vu.start_datetime DESC");
$stmt->execute($params);
$trips = $stmt->fetchAll();

$vehicles = $pdo->query("SELECT id, plate_number FROM vehicles ORDER BY plate_number")->fetchAll();
$users = $pdo->query("SELECT id, COALESCE(full_name, username) name FROM users WHERE is_active=1 ORDER BY name")->fetchAll();

?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>گزارش ناوگان</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .kpi { font-weight: 700; font-size: 1.2rem; }
  </style>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
<div class="container mt-4">
  <h3><i class="fas fa-chart-line"></i> گزارش ناوگان</h3>

  <div class="card mb-3">
    <div class="card-header">فیلتر</div>
    <div class="card-body">
      <form method="GET" class="row g-2">
        <div class="col-md-3">
          <label class="form-label">از تاریخ</label>
          <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">تا تاریخ</label>
          <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">خودرو</label>
          <select name="vehicle_id" class="form-select">
            <option value="">همه</option>
            <?php foreach ($vehicles as $v): ?>
            <option value="<?php echo (int)$v['id']; ?>" <?php echo ($vehicle_id===$v['id']?'selected':''); ?>><?php echo htmlspecialchars($v['plate_number']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">راننده</label>
          <select name="driver_id" class="form-select">
            <option value="">همه</option>
            <?php foreach ($users as $u): ?>
            <option value="<?php echo (int)$u['id']; ?>" <?php echo ($driver_id===$u['id']?'selected':''); ?>><?php echo htmlspecialchars($u['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12">
          <button class="btn btn-primary"><i class="fas fa-filter"></i> اعمال</button>
        </div>
      </form>
    </div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-md-4">
      <div class="card">
        <div class="card-body">
          <div class="kpi">مجموع سفرها: <?php echo number_format((int)$totals['trips']); ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card">
        <div class="card-body">
          <div class="kpi">مجموع کیلومتر: <?php echo number_format((int)$totals['total_km']); ?> Km</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card">
        <div class="card-body">
          <div class="kpi">میانگین مسافت/سفر: <?php echo (int)$totals['trips'] ? number_format((int)$totals['total_km']/(int)$totals['trips'], 1) : 0; ?> Km</div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-6">
      <div class="card">
        <div class="card-header">کارکرد خودروها</div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-striped">
              <thead>
                <tr>
                  <th>پلاک</th>
                  <th>سازنده/مدل</th>
                  <th>تعداد سفر</th>
                  <th>کیلومتر</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($by_vehicle as $r): ?>
                <tr>
                  <td><?php echo htmlspecialchars($r['plate_number']); ?></td>
                  <td><?php echo htmlspecialchars($r['make'] . ' ' . $r['model']); ?></td>
                  <td><?php echo number_format((int)$r['trips']); ?></td>
                  <td><?php echo number_format((int)$r['km']); ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card">
        <div class="card-header">کارکرد رانندگان</div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-striped">
              <thead>
                <tr>
                  <th>راننده</th>
                  <th>تعداد سفر</th>
                  <th>کیلومتر</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($by_driver as $r): ?>
                <tr>
                  <td><?php echo htmlspecialchars($r['driver_name']); ?></td>
                  <td><?php echo number_format((int)$r['trips']); ?></td>
                  <td><?php echo number_format((int)$r['km']); ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="card mt-3">
    <div class="card-header">جزئیات سفرها</div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-striped">
          <thead>
            <tr>
              <th>#</th>
              <th>پلاک</th>
              <th>راننده</th>
              <th>شروع</th>
              <th>پایان</th>
              <th>کیلومتر</th>
              <th>سوخت (شروع/پایان)</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($trips as $t): ?>
            <tr>
              <td><?php echo (int)$t['id']; ?></td>
              <td><?php echo htmlspecialchars($t['plate_number']); ?></td>
              <td><?php echo htmlspecialchars($t['driver_name'] ?: $t['driver_name']); ?></td>
              <td><?php echo htmlspecialchars($t['start_datetime']); ?></td>
              <td><?php echo htmlspecialchars($t['end_datetime']); ?></td>
              <td><?php echo number_format((int)$t['distance_km']); ?></td>
              <td><?php echo htmlspecialchars((string)$t['start_fuel_percent']) . '% / ' . htmlspecialchars((string)$t['end_fuel_percent']) . '%'; ?></td>
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
