<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// بررسی دسترسی
if (!hasPermission('inventory.view')) {
    header('Location: dashboard.php');
    exit();
}

$alerts = [];

// ایجاد جدول inventory_movements اگر وجود نداشته باشد
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS inventory_movements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            asset_id INT NOT NULL,
            delta INT NOT NULL,
            reason VARCHAR(255) NULL,
            created_by INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_asset_id (asset_id),
            FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
    ");
} catch (Exception $e) {
    $alerts[] = ['type' => 'danger', 'text' => 'خطا در ایجاد جدول: ' . $e->getMessage()];
}

// پردازش درخواست‌ها
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    verifyCsrfToken();
    
    if (isset($_POST['create_item'])) {
        try {
            $name = sanitizeInput($_POST['name']);
            $type_id = (int)$_POST['type_id'];
            $brand = sanitizeInput($_POST['brand'] ?? '');
            $model = sanitizeInput($_POST['model'] ?? '');
            $serial_number = sanitizeInput($_POST['serial_number'] ?? '');
            $location = sanitizeInput($_POST['location'] ?? '');
            $initial_qty = (int)($_POST['initial_qty'] ?? 0);

            $pdo->beginTransaction();
            
            // ایجاد دارایی
            $stmt = $pdo->prepare("
                INSERT INTO assets (name, type_id, serial_number, purchase_date, status, brand, model, location, supply_method, description)
                VALUES (?, ?, ?, NULL, 'فعال', ?, ?, ?, 'انبار', NULL)
            ");
            $stmt->execute([$name, $type_id, $serial_number ?: null, $brand ?: null, $model ?: null, $location ?: null]);
            $asset_id = $pdo->lastInsertId();

            // ثبت موجودی اولیه
            if ($initial_qty != 0) {
                $stmt = $pdo->prepare("INSERT INTO inventory_movements(asset_id, delta, reason, created_by) VALUES(?,?,?,?)");
                $stmt->execute([$asset_id, $initial_qty, 'موجودی اولیه', $_SESSION['user_id']]);
            }
            
            $pdo->commit();
            $alerts[] = ['type' => 'success', 'text' => 'آیتم با موفقیت ایجاد شد.'];
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $alerts[] = ['type' => 'danger', 'text' => 'خطا در ایجاد آیتم: ' . $e->getMessage()];
        }
    }
    
    if (isset($_POST['add_movement'])) {
        try {
            $asset_id = (int)$_POST['asset_id'];
            $delta = (int)$_POST['delta'];
            $reason = sanitizeInput($_POST['reason'] ?? 'تغییر دستی');

            $stmt = $pdo->prepare("INSERT INTO inventory_movements(asset_id, delta, reason, created_by) VALUES(?,?,?,?)");
            $stmt->execute([$asset_id, $delta, $reason, $_SESSION['user_id']]);

            $alerts[] = ['type' => 'success', 'text' => 'ثبت موجودی انجام شد.'];
        } catch (Exception $e) {
            $alerts[] = ['type' => 'danger', 'text' => 'خطا در ثبت موجودی: ' . $e->getMessage()];
        }
    }
}

// دریافت انواع دارایی
try {
    $asset_types = $pdo->query("SELECT id, name, display_name FROM asset_types ORDER BY name")->fetchAll();
} catch (Exception $e) {
    $asset_types = [];
}

// دریافت اقلام انبار
try {
    $sql = "
        SELECT
            a.id,
            a.name,
            a.brand,
            a.model,
            a.serial_number,
            a.location,
            at.display_name AS type_name,
            COALESCE(SUM(m.delta), 0) AS current_qty
        FROM assets a
        LEFT JOIN asset_types at ON at.id = a.type_id
        LEFT JOIN inventory_movements m ON m.asset_id = a.id
        WHERE a.supply_method = 'انبار'
        GROUP BY a.id
        ORDER BY a.id DESC
        LIMIT 100
    ";
    $inventory_items = $pdo->query($sql)->fetchAll();
} catch (Exception $e) {
    $inventory_items = [];
    $alerts[] = ['type' => 'danger', 'text' => 'خطا در بارگذاری لیست: ' . $e->getMessage()];
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت انبار - شرکت اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <style>
        body { font-family: Vazirmatn, sans-serif; background-color: #f8f9fa; padding-top: 80px; }
        .dark-mode { background-color: #1a1a1a !important; color: #ffffff !important; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .dark-mode .card { background-color: #2d3748; }
        .card-header { background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); color: white; border-radius: 12px 12px 0 0 !important; font-weight: 600; }
        .btn-primary { background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); border: none; border-radius: 6px; padding: 6px 18px; transition: all 0.3s; font-size: 0.85rem; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(52,152,219,0.3); }
        .badge-qty { font-size: 0.95rem; }
    </style>
</head>
<body class="<?php echo isset($_COOKIE['theme']) && $_COOKIE['theme']==='dark' ? 'dark-mode' : ''; ?>">
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="m-0"><i class="fas fa-warehouse"></i> مدیریت انبار</h3>
        </div>

        <?php foreach ($alerts as $alert): ?>
            <div class="alert alert-<?php echo $alert['type']; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $alert['type'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo htmlspecialchars($alert['text']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endforeach; ?>

        <!-- فرم افزودن آیتم جدید -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-plus"></i> افزودن آیتم جدید به انبار</span>
                <button class="btn btn-light btn-sm" data-bs-toggle="collapse" data-bs-target="#createBox">
                    <i class="fa fa-plus ms-1"></i>باز/بسته
                </button>
            </div>
            <div id="createBox" class="collapse show">
                <div class="card-body">
                    <form method="post" class="row g-3">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="create_item" value="1">
                        
                        <div class="col-md-3">
                            <label class="form-label">نام کالا/قطعه *</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">نوع *</label>
                            <select name="type_id" class="form-select" required>
                                <option value="">انتخاب...</option>
                                <?php foreach ($asset_types as $type): ?>
                                    <option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars($type['display_name'] ?? $type['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">برند</label>
                            <input type="text" name="brand" class="form-control">
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">مدل</label>
                            <input type="text" name="model" class="form-control">
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">سریال</label>
                            <input type="text" name="serial_number" class="form-control">
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">مکان/قفسه</label>
                            <input type="text" name="location" class="form-control" placeholder="انبار A - قفسه 3">
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">موجودی اولیه</label>
                            <input type="number" name="initial_qty" class="form-control" value="0">
                        </div>
                        
                        <div class="col-12">
                            <button class="btn btn-primary"><i class="fa fa-save ms-1"></i>ثبت آیتم</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- لیست اقلام انبار -->
        <div class="card">
            <div class="card-header"><i class="fas fa-boxes"></i> اقلام موجود در انبار</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover m-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:70px">#</th>
                                <th>نام</th>
                                <th>نوع</th>
                                <th>برند/مدل</th>
                                <th>سریال</th>
                                <th>مکان</th>
                                <th class="text-center" style="width:120px">موجودی</th>
                                <th class="text-center" style="width:200px">اقدامات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($inventory_items)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">
                                        <i class="fas fa-box-open fa-2x mb-2"></i><br>موردی یافت نشد.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($inventory_items as $item): ?>
                                    <tr>
                                        <td><?php echo (int)$item['id']; ?></td>
                                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                                        <td><span class="badge bg-info-subtle text-dark"><?php echo htmlspecialchars($item['type_name'] ?? '-'); ?></span></td>
                                        <td><?php echo htmlspecialchars(trim(($item['brand'] ?? '') . ' ' . ($item['model'] ?? '')) ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($item['serial_number'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($item['location'] ?? '-'); ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-<?php echo ((int)$item['current_qty'] >= 0 ? 'success' : 'danger'); ?> badge-qty">
                                                <?php echo (int)$item['current_qty']; ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex flex-wrap justify-content-center gap-2">
                                                <!-- + Add -->
                                                <form method="post" class="d-inline-flex align-items-center gap-2">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <input type="hidden" name="add_movement" value="1">
                                                    <input type="hidden" name="asset_id" value="<?php echo (int)$item['id']; ?>">
                                                    <input type="number" name="delta" class="form-control form-control-sm" style="width:80px" placeholder="+تعداد">
                                                    <input type="hidden" name="reason" value="افزایش دستی">
                                                    <button class="btn btn-sm btn-success"><i class="fa fa-plus"></i></button>
                                                </form>
                                                
                                                <!-- - Remove -->
                                                <form method="post" class="d-inline-flex align-items-center gap-2">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <input type="hidden" name="add_movement" value="1">
                                                    <input type="hidden" name="asset_id" value="<?php echo (int)$item['id']; ?>">
                                                    <input type="number" name="delta" class="form-control form-control-sm" style="width:80px" placeholder="-تعداد">
                                                    <input type="hidden" name="reason" value="کاهش دستی">
                                                    <button class="btn btn-sm btn-warning"><i class="fa fa-minus"></i></button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <p class="text-muted small mt-3">
            <i class="fas fa-info-circle"></i> منبع داده‌ها: اقلام ثبت شده در «دارایی‌ها» با supply_method = «انبار» + محاسبه موجودی از جدول «inventory_movements».
        </p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-hide alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>