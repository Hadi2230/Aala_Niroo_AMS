<?php
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}
if (!isset($_SESSION['user_id'])) { 
    header('Location: login.php'); 
    exit(); 
}
require_once __DIR__ . '/config.php';

if (!headers_sent()) { 
    header('Content-Type: text/html; charset=utf-8'); 
}

$pdo = pdo();
function h($s){ 
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); 
}
$embed = isset($_GET['embed']) && $_GET['embed'] === '1';
$alerts = [];

/* ======================= Schema Ensure (Idempotent) ======================= */
try {
    // ستون‌های کم‌خطر روی assets
    $cols = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='assets'")->fetchAll(PDO::FETCH_COLUMN);
    $alter = [];
    if (!in_array('supply_method', $cols, true)) {
        $alter[] = "ADD COLUMN supply_method VARCHAR(50) NULL";
    }
    if (!in_array('location', $cols, true)) {
        $alter[] = "ADD COLUMN location VARCHAR(255) NULL";
    }
    if ($alter) {
        $pdo->exec("ALTER TABLE assets " . implode(', ', $alter));
    }
    
    // جدول گردش موجودی
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

    // اطمینان از وجود ستون created_by اگر جدول از قبل وجود داشت
    try {
        $cols = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='inventory_movements'")->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('created_by', $cols, true)) {
            $pdo->exec("ALTER TABLE inventory_movements ADD COLUMN created_by INT NULL AFTER reason");
        }
    } catch (Throwable $e) {
        $alerts[] = ['type'=>'warning','text'=>'ستون created_by وجود دارد یا خطا: '.h($e->getMessage())];
    }
    
    // ایندکس‌های کاربردی
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_assets_type_supply ON assets(type_id, supply_method)");
} catch (Throwable $e) {
    $alerts[] = ['type'=>'danger','text'=>'خطای اسکیما: '.h($e->getMessage())];
}

/* ======================= Resolve Type IDs ======================= */
$typeIds = ['consumable'=>null, 'parts'=>null];
try {
    $stmt = $pdo->query("SELECT id, name FROM asset_types");
    foreach ($stmt->fetchAll() as $t) {
        if ($t['name'] === 'consumable') $typeIds['consumable'] = (int)$t['id'];
        if ($t['name'] === 'parts') $typeIds['parts'] = (int)$t['id'];
    }
    // اگر وجود ندارند، بساز
    $now = date('Y-m-d H:i:s');
    if (!$typeIds['consumable']) {
        $pdo->prepare("INSERT INTO asset_types(name, display_name, created_at, updated_at) VALUES('consumable','اقلام مصرفی',NOW(),NOW())")->execute();
        $typeIds['consumable'] = (int)$pdo->lastInsertId();
    }
    if (!$typeIds['parts']) {
        $pdo->prepare("INSERT INTO asset_types(name, display_name, created_at, updated_at) VALUES('parts','قطعات',NOW(),NOW())")->execute();
        $typeIds['parts'] = (int)$pdo->lastInsertId();
    }
} catch (Throwable $e) {
    $alerts[] = ['type'=>'danger','text'=>'خطا در بارگذاری انواع: '.h($e->getMessage())];
}

/* ======================= POST Actions ======================= */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try { 
        verifyCsrfToken(); 
    } catch (Throwable $e) { 
        http_response_code(403); 
        die(h($e->getMessage())); 
    }

    // ایجاد آیتم جدید در انبار (اقلام مصرفی/قطعات) + ثبت موجودی اولیه
    if (isset($_POST['create_item'])) {
        try {
            $name = trim($_POST['name'] ?? '');
            $type_key = ($_POST['type_key'] ?? '');
            $type_id = $type_key === 'consumable' ? $typeIds['consumable'] : ($type_key === 'parts' ? $typeIds['parts'] : null);
            if (!$name || !$type_id) { 
                throw new Exception('نام و نوع کالا الزامی است.'); 
            }

            $brand = trim($_POST['brand'] ?? '');
            $model = trim($_POST['model'] ?? '');
            $serial_number = trim($_POST['serial_number'] ?? '');
            $location = trim($_POST['location'] ?? '');
            $initial_qty = (int)($_POST['initial_qty'] ?? 0);

            $pdo->beginTransaction();
            $ins = $pdo->prepare("
                INSERT INTO assets (name, type_id, serial_number, purchase_date, status, brand, model, location, supply_method, description)
                VALUES (?, ?, ?, NULL, 'فعال', ?, ?, ?, 'انبار', NULL)
            ");
            $ins->execute([$name, $type_id, $serial_number ?: null, $brand ?: null, $model ?: null, $location ?: null]);
            $newAssetId = (int)$pdo->lastInsertId();

            if ($initial_qty !== 0) {
                $mv = $pdo->prepare("INSERT INTO inventory_movements(asset_id, delta, reason, created_by) VALUES(?,?,?,?)");
                $mv->execute([$newAssetId, $initial_qty, 'موجودی اولیه', $_SESSION['user_id'] ?? null]);
            }
            $pdo->commit();
            $alerts[] = ['type'=>'success','text'=>'آیتم با موفقیت ایجاد شد.'];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { 
                $pdo->rollBack(); 
            }
            $alerts[] = ['type'=>'danger','text'=>'خطا در ایجاد آیتم: '.h($e->getMessage())];
        }
    }

    // ویرایش آیتم انبار (اطلاعات پایه)
    if (isset($_POST['edit_item'])) {
        try {
            $asset_id = (int)($_POST['asset_id'] ?? 0);
            if ($asset_id <= 0) { 
                throw new Exception('شناسه معتبر نیست.'); 
            }

            $name = trim($_POST['name'] ?? '');
            $brand = trim($_POST['brand'] ?? '');
            $model = trim($_POST['model'] ?? '');
            $serial_number = trim($_POST['serial_number'] ?? '');
            $location = trim($_POST['location'] ?? '');

            $upd = $pdo->prepare("UPDATE assets SET name=?, brand=?, model=?, serial_number=?, location=? WHERE id=?");
            $upd->execute([$name ?: null, $brand ?: null, $model ?: null, $serial_number ?: null, $location ?: null, $asset_id]);

            $alerts[] = ['type'=>'success','text'=>'آیتم با موفقیت ویرایش شد.'];
        } catch (Throwable $e) {
            $alerts[] = ['type'=>'danger','text'=>'خطا در ویرایش: '.h($e->getMessage())];
        }
    }

    // حذف آیتم انبار (فقط برای اقلام با supply_method=انبار)
    if (isset($_POST['delete_item'])) {
        try {
            $asset_id = (int)($_POST['asset_id'] ?? 0);
            if ($asset_id <= 0) { 
                throw new Exception('شناسه معتبر نیست.'); 
            }
            // ایمنی: فقط اگر supply_method='انبار'
            $chk = $pdo->prepare("SELECT id FROM assets WHERE id=? AND supply_method='انبار'");
            $chk->execute([$asset_id]);
            if (!$chk->fetch()) { 
                throw new Exception('حذف فقط برای آیتم‌های انبار مجاز است.'); 
            }

            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM inventory_movements WHERE asset_id=?")->execute([$asset_id]);
            $pdo->prepare("DELETE FROM assets WHERE id=?")->execute([$asset_id]);
            $pdo->commit();
            $alerts[] = ['type'=>'success','text'=>'آیتم حذف شد.'];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { 
                $pdo->rollBack(); 
            }
            $alerts[] = ['type'=>'danger','text'=>'خطا در حذف: '.h($e->getMessage())];
        }
    }

    // ثبت حرکت موجودی (افزایش/کاهش)
    if (isset($_POST['add_movement'])) {
        try {
            $asset_id = (int)($_POST['asset_id'] ?? 0);
            $delta = (int)($_POST['delta'] ?? 0);
            $reason = trim($_POST['reason'] ?? '');
            if ($asset_id <= 0 || $delta === 0) { 
                throw new Exception('شناسه و مقدار جابجایی صحیح نیست.'); 
            }

            $mv = $pdo->prepare("INSERT INTO inventory_movements(asset_id, delta, reason, created_by) VALUES(?,?,?,?)");
            $mv->execute([$asset_id, $delta, $reason ?: null, $_SESSION['user_id'] ?? null]);

            $alerts[] = ['type'=>'success','text'=>'ثبت موجودی انجام شد.'];
        } catch (Throwable $e) {
            $alerts[] = ['type'=>'danger','text'=>'خطا در ثبت موجودی: '.h($e->getMessage())];
        }
    }
}

/* ======================= Filters ======================= */
$q = trim($_GET['q'] ?? '');
$type_filter = $_GET['type'] ?? 'all'; // all|consumable|parts

$filterSql = [];
$params = [];
$filterSql[] = "a.supply_method = 'انبار'"; // فقط اقلامی که در انبار هستند
if ($q !== '') {
    $filterSql[] = "(a.name LIKE ? OR a.brand LIKE ? OR a.model LIKE ? OR a.serial_number LIKE ?)";
    $like = "%{$q}%";
    array_push($params, $like, $like, $like, $like);
}
if ($type_filter === 'consumable' && $typeIds['consumable']) {
    $filterSql[] = "a.type_id = " . (int)$typeIds['consumable'];
} elseif ($type_filter === 'parts' && $typeIds['parts']) {
    $filterSql[] = "a.type_id = " . (int)$typeIds['parts'];
}
$where = $filterSql ? ("WHERE " . implode(' AND ', $filterSql)) : "";

/* ======================= Query Inventory List ======================= */
$rows = [];
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
        {$where}
        GROUP BY a.id
        ORDER BY a.id DESC
        LIMIT 500
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
} catch (Throwable $e) {
    $alerts[] = ['type'=>'danger','text'=>'خطا در بارگذاری لیست: '.h($e->getMessage())];
}

/* ======================= Page ======================= */
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت انبار - شرکت اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <style>
        body { 
            font-family: Vazirmatn, sans-serif; 
            <?php echo $embed ? '' : 'padding-top:80px;'; ?> 
            background:#f8f9fa; 
        }
        .dark-mode { background-color: #1a1a1a !important; color: #ffffff !important; }
        .card { 
            border: none; 
            border-radius: 12px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.08); 
            margin-bottom: 20px;
        }
        .dark-mode .card { background-color: #2d3748; }
        .card-header { 
            background: linear-gradient(135deg,#2c3e50 0%,#34495e 100%); 
            color:#fff; 
            border-radius:12px 12px 0 0 !important; 
            font-weight: 600;
        }
        .form-control, .form-select { 
            border-radius:10px; 
        }
        .badge-qty { 
            font-size: .95rem; 
        }
        .table > :not(caption) > * > * { 
            vertical-align: middle; 
        }
        .btn-primary { 
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); 
            border: none; 
            border-radius: 6px; 
            padding: 6px 18px; 
            transition: all 0.3s; 
            font-size: 0.85rem; 
        }
        .btn-primary:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 4px 12px rgba(52,152,219,0.3); 
        }
    </style>
</head>
<body class="<?php echo isset($_COOKIE['theme']) && $_COOKIE['theme']==='dark' ? 'dark-mode' : ''; ?>">
<?php if (!$embed) { include __DIR__ . '/navbar.php'; } ?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="m-0"><i class="fas fa-warehouse"></i> مدیریت انبار</h3>
        <a href="?embed=1" class="btn btn-outline-secondary btn-sm"><i class="fa fa-window-restore ms-1"></i>نمایش قابل‌جاسازی</a>
    </div>

    <?php foreach ($alerts as $a): ?>
        <div class="alert alert-<?php echo h($a['type']); ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?php echo $a['type'] === 'success' ? 'check-circle' : ($a['type'] === 'danger' ? 'exclamation-triangle' : 'info-circle'); ?>"></i>
            <?php echo h($a['text']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endforeach; ?>

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
                        <select name="type_key" class="form-select" required>
                            <option value="">انتخاب...</option>
                            <option value="consumable">اقلام مصرفی</option>
                            <option value="parts">قطعات</option>
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

    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-search"></i> جستجو و فیلتر</div>
        <div class="card-body">
            <form method="get" class="row g-3">
                <?php if ($embed): ?>
                    <input type="hidden" name="embed" value="1">
                <?php endif; ?>
                <div class="col-md-4">
                    <label class="form-label">کلمه کلیدی</label>
                    <input type="text" name="q" class="form-control" value="<?php echo h($q); ?>" placeholder="نام، برند، مدل، سریال">
                </div>
                <div class="col-md-3">
                    <label class="form-label">نوع</label>
                    <select name="type" class="form-select">
                        <option value="all" <?php echo $type_filter==='all'?'selected':''; ?>>همه</option>
                        <option value="consumable" <?php echo $type_filter==='consumable'?'selected':''; ?>>اقلام مصرفی</option>
                        <option value="parts" <?php echo $type_filter==='parts'?'selected':''; ?>>قطعات</option>
                    </select>
                </div>
                <div class="col-md-2 align-self-end">
                    <button class="btn btn-secondary w-100"><i class="fa fa-search ms-1"></i>جستجو</button>
                </div>
            </form>
        </div>
    </div>

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
                        <th class="text-center" style="width:320px">اقدامات</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted"><i class="fas fa-box-open fa-2x mb-2"></i><br>موردی یافت نشد.</td></tr>
                    <?php else: foreach ($rows as $r): ?>
                        <tr>
                            <td><?php echo (int)$r['id']; ?></td>
                            <td><?php echo h($r['name']); ?></td>
                            <td><span class="badge bg-info-subtle text-dark"><?php echo h($r['type_name'] ?? '-'); ?></span></td>
                            <td><?php echo h(trim(($r['brand'] ?? '') . ' ' . ($r['model'] ?? '')) ?: '-'); ?></td>
                            <td><?php echo h($r['serial_number'] ?? '-'); ?></td>
                            <td><?php echo h($r['location'] ?? '-'); ?></td>
                            <td class="text-center">
                                <span class="badge bg-<?php echo ((int)$r['current_qty']>=0?'success':'danger'); ?> badge-qty">
                                    <?php echo (int)$r['current_qty']; ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="d-flex flex-wrap justify-content-center gap-2">
                                    <!-- + Add -->
                                    <form method="post" class="d-inline-flex align-items-center gap-2">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="add_movement" value="1">
                                        <input type="hidden" name="asset_id" value="<?php echo (int)$r['id']; ?>">
                                        <input type="number" name="delta" class="form-control form-control-sm" style="width:90px" placeholder="+تعداد">
                                        <input type="hidden" name="reason" value="افزایش دستی">
                                        <button class="btn btn-sm btn-success"><i class="fa fa-plus"></i></button>
                                    </form>
                                    <!-- - Remove -->
                                    <form method="post" class="d-inline-flex align-items-center gap-2">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="add_movement" value="1">
                                        <input type="hidden" name="asset_id" value="<?php echo (int)$r['id']; ?>">
                                        <input type="number" name="delta" class="form-control form-control-sm" style="width:90px" placeholder="-تعداد">
                                        <input type="hidden" name="reason" value="کاهش دستی">
                                        <button class="btn btn-sm btn-warning"><i class="fa fa-minus"></i></button>
                                    </form>
                                    <!-- Edit Modal trigger -->
                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editModal-<?php echo (int)$r['id']; ?>">
                                        <i class="fa fa-pen"></i>
                                    </button>
                                    <!-- Delete -->
                                    <form method="post" onsubmit="return confirm('حذف آیتم از انبار؟');" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="delete_item" value="1">
                                        <input type="hidden" name="asset_id" value="<?php echo (int)$r['id']; ?>">
                                        <button class="btn btn-sm btn-outline-danger"><i class="fa fa-trash"></i></button>
                                    </form>
                                </div>

                                <!-- Edit Modal -->
                                <div class="modal fade" id="editModal-<?php echo (int)$r['id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h6 class="modal-title">ویرایش آیتم #<?php echo (int)$r['id']; ?></h6>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <form method="post">
                                                <div class="modal-body">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <input type="hidden" name="edit_item" value="1">
                                                    <input type="hidden" name="asset_id" value="<?php echo (int)$r['id']; ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label">نام</label>
                                                        <input type="text" name="name" class="form-control" value="<?php echo h($r['name']); ?>" required>
                                                    </div>
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label">برند</label>
                                                            <input type="text" name="brand" class="form-control" value="<?php echo h($r['brand']); ?>">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">مدل</label>
                                                            <input type="text" name="model" class="form-control" value="<?php echo h($r['model']); ?>">
                                                        </div>
                                                    </div>
                                                    <div class="row g-3 mt-1">
                                                        <div class="col-md-6">
                                                            <label class="form-label">سریال</label>
                                                            <input type="text" name="serial_number" class="form-control" value="<?php echo h($r['serial_number']); ?>">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">مکان/قفسه</label>
                                                            <input type="text" name="location" class="form-control" value="<?php echo h($r['location']); ?>">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">بستن</button>
                                                    <button class="btn btn-primary">ذخیره تغییرات</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
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