<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'ادمین') {
    header('Location: contracts.php');
    exit();
}
include 'config.php';

// ایجاد/ویرایش قرارداد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_contract') {
    try {
        $title = sanitizeInput($_POST['title'] ?? '');
        $customer_id = isset($_POST['customer_id']) && $_POST['customer_id'] !== '' ? (int)$_POST['customer_id'] : null;
        $project_id = isset($_POST['project_id']) && $_POST['project_id'] !== '' ? (int)$_POST['project_id'] : null;
        $contract_code = sanitizeInput($_POST['contract_code'] ?? '');
        if ($title === '') throw new Exception('عنوان قرارداد الزامی است');

        if (!empty($_POST['contract_id'])) {
            $cid = (int)$_POST['contract_id'];
            $stmt = $pdo->prepare("UPDATE contracts SET title=?, customer_id=?, project_id=?, contract_code=? WHERE id=?");
            $stmt->execute([$title, $customer_id, $project_id, $contract_code, $cid]);
            $_SESSION['success_message'] = 'قرارداد ویرایش شد';
        } else {
            if ($contract_code === '') { $contract_code = 'CNT-' . date('Ymd') . '-' . random_int(1000,9999); }
            $stmt = $pdo->prepare("INSERT INTO contracts (title, customer_id, project_id, contract_code, created_by) VALUES (?,?,?,?,?)");
            $stmt->execute([$title, $customer_id, $project_id, $contract_code, $_SESSION['user_id']]);
            $_SESSION['success_message'] = 'قرارداد ایجاد شد';
        }
        header('Location: contracts_admin.php');
        exit();
    } catch (Exception $e) { $_SESSION['error_message'] = $e->getMessage(); }
}

// آپلود نسخه جدید
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_version') {
    try {
        $contract_id = (int)($_POST['contract_id'] ?? 0);
        $change_log = sanitizeInput($_POST['change_log'] ?? '');
        if ($contract_id <= 0) throw new Exception('قرارداد معتبر نیست');
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) throw new Exception('فایل قرارداد الزامی است');

        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf','doc','docx'];
        if (!in_array($ext, $allowed, true)) throw new Exception('فقط PDF/Word مجاز است');

        $dir = __DIR__ . '/uploads/contracts/versions/';
        if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
        @chmod($dir, 0775);
        $fname = 'c' . $contract_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $full = $dir . $fname;
        if (!move_uploaded_file($_FILES['file']['tmp_name'], $full)) throw new Exception('ذخیره فایل ناموفق بود');

        // تعیین نسخه بعدی
        $st = $pdo->prepare("SELECT COALESCE(MAX(version),0)+1 AS v FROM contract_versions WHERE contract_id = ?");
        $st->execute([$contract_id]);
        $v = (int)($st->fetch()['v'] ?? 1);

        $stmt = $pdo->prepare("INSERT INTO contract_versions (contract_id, version, file_path, file_size, mime_type, change_log, uploaded_by) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([$contract_id, $v, 'uploads/contracts/versions/' . $fname, $_FILES['file']['size'], $_FILES['file']['type'], $change_log, $_SESSION['user_id']]);

        // بروزرسانی نسخه جاری
        $pdo->prepare("UPDATE contracts SET current_version = ?, latest_version_id = ? WHERE id = ?")
            ->execute([$v, $pdo->lastInsertId(), $contract_id]);

        $_SESSION['success_message'] = 'نسخه جدید قرارداد ثبت شد';
        header('Location: contracts_admin.php#c-' . $contract_id);
        exit();
    } catch (Exception $e) { $_SESSION['error_message'] = $e->getMessage(); }
}

// دسترسی‌ها
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_permissions') {
    try {
        $contract_id = (int)($_POST['contract_id'] ?? 0);
        if ($contract_id <= 0) throw new Exception('قرارداد معتبر نیست');
        // پاک‌سازی فعلی
        $pdo->prepare("DELETE FROM contract_permissions WHERE contract_id = ?")->execute([$contract_id]);
        if (!empty($_POST['perm']) && is_array($_POST['perm'])) {
            foreach ($_POST['perm'] as $user_id => $caps) {
                $can_view = isset($caps['view']) ? 1 : 0;
                $can_edit = isset($caps['edit']) ? 1 : 0;
                $can_delete = isset($caps['delete']) ? 1 : 0;
                if ($can_view || $can_edit || $can_delete) {
                    $pdo->prepare("INSERT INTO contract_permissions (contract_id, user_id, can_view, can_edit, can_delete) VALUES (?,?,?,?,?)")
                        ->execute([$contract_id, (int)$user_id, $can_view, $can_edit, $can_delete]);
                }
            }
        }
        $_SESSION['success_message'] = 'دسترسی‌ها بروزرسانی شد';
        header('Location: contracts_admin.php#c-' . $contract_id);
        exit();
    } catch (Exception $e) { $_SESSION['error_message'] = $e->getMessage(); }
}

$customers = $pdo->query("SELECT id, full_name FROM customers ORDER BY full_name")->fetchAll();
$projects = $pdo->query("SELECT id, name FROM finance_projects ORDER BY created_at DESC")->fetchAll();
$users = $pdo->query("SELECT id, full_name, username FROM users WHERE is_active = 1 ORDER BY full_name")->fetchAll();

$contracts = $pdo->query("SELECT c.*, cu.full_name as customer_name, fp.name as project_name
  FROM contracts c
  LEFT JOIN customers cu ON c.customer_id = cu.id
  LEFT JOIN finance_projects fp ON c.project_id = fp.id
  ORDER BY c.updated_at DESC")->fetchAll();

function getVersions($pdo, $cid) {
    $st = $pdo->prepare("SELECT * FROM contract_versions WHERE contract_id = ? ORDER BY version DESC");
    $st->execute([$cid]);
    return $st->fetchAll();
}

function getPerms($pdo, $cid) {
    $st = $pdo->prepare("SELECT * FROM contract_permissions WHERE contract_id = ?");
    $st->execute([$cid]);
    $rows = $st->fetchAll();
    $out = [];
    foreach ($rows as $r) { $out[(int)$r['user_id']] = $r; }
    return $out;
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت قراردادها - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="fas fa-user-shield"></i> مدیریت قراردادها</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">داشبورد</a></li>
                    <li class="breadcrumb-item"><a href="contracts.php">قراردادها</a></li>
                    <li class="breadcrumb-item active">مدیریت</li>
                </ol>
            </nav>
        </div>
        <a href="#createContract" class="btn btn-primary" data-bs-toggle="collapse"><i class="fas fa-plus"></i> قرارداد جدید</a>
    </div>

    <?php if (!empty($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle"></i> <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div id="createContract" class="collapse mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white"><i class="fas fa-file-signature"></i> ایجاد قرارداد</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="save_contract">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">عنوان *</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">کد قرارداد</label>
                            <input type="text" name="contract_code" class="form-control" placeholder="اختیاری">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">مشتری</label>
                            <select name="customer_id" class="form-select">
                                <option value="">انتخاب...</option>
                                <?php foreach ($customers as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['full_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">پروژه مرتبط</label>
                            <select name="project_id" class="form-select">
                                <option value="">انتخاب...</option>
                                <?php foreach ($projects as $p): ?>
                                    <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="text-end mt-3">
                        <button class="btn btn-success"><i class="fas fa-save"></i> ذخیره</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php foreach ($contracts as $c): $versions = getVersions($pdo, $c['id']); $perms = getPerms($pdo, $c['id']); ?>
    <div id="c-<?php echo $c['id']; ?>" class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <strong><?php echo htmlspecialchars($c['title']); ?></strong>
                <span class="badge bg-secondary me-2"><?php echo $c['contract_code']; ?></span>
                <small class="text-muted">
                    مشتری: <?php echo $c['customer_name'] ?: '-'; ?> |
                    پروژه: <?php echo $c['project_name'] ?: '-'; ?> |
                    نسخه جاری: <span class="badge bg-info">v<?php echo (int)$c['current_version']; ?></span>
                </small>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-lg-5">
                    <div class="p-3 bg-light rounded h-100">
                        <h6 class="mb-3"><i class="fas fa-upload"></i> آپلود نسخه جدید</h6>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="upload_version">
                            <input type="hidden" name="contract_id" value="<?php echo $c['id']; ?>">
                            <div class="mb-2">
                                <input type="file" name="file" class="form-control" accept=".pdf,.doc,.docx" required>
                            </div>
                            <div class="mb-2">
                                <textarea name="change_log" class="form-control" rows="2" placeholder="توضیحات/تغییرات نسخه"></textarea>
                            </div>
                            <div class="text-end">
                                <button class="btn btn-success"><i class="fas fa-save"></i> ذخیره</button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="col-lg-7">
                    <h6 class="mb-3"><i class="fas fa-history"></i> نسخه‌ها</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead><tr><th>نسخه</th><th>فایل</th><th>حجم</th><th>تاریخ</th><th>یادداشت</th></tr></thead>
                            <tbody>
                                <?php foreach ($versions as $v): ?>
                                    <tr>
                                        <td>v<?php echo (int)$v['version']; ?></td>
                                        <td>
                                            <a class="btn btn-sm btn-outline-primary" href="contracts_file.php?version_id=<?php echo $v['id']; ?>&disposition=inline" target="_blank">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                        <td><?php echo round(((int)$v['file_size']) / 1024, 2); ?> KB</td>
                                        <td><?php echo htmlspecialchars($v['created_at']); ?></td>
                                        <td><?php echo htmlspecialchars($v['change_log']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (count($versions) === 0): ?>
                                    <tr><td colspan="5" class="text-muted text-center">نسخه‌ای ثبت نشده است</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <hr>
            <h6 class="mb-3"><i class="fas fa-user-lock"></i> دسترسی کاربران</h6>
            <form method="POST">
                <input type="hidden" name="action" value="save_permissions">
                <input type="hidden" name="contract_id" value="<?php echo $c['id']; ?>">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead><tr><th>کاربر</th><th>مشاهده</th><th>ویرایش</th><th>حذف</th></tr></thead>
                        <tbody>
                        <?php foreach ($users as $u): $perm = $perms[$u['id']] ?? null; ?>
                            <tr>
                                <td><?php echo htmlspecialchars($u['full_name'] ?: $u['username']); ?></td>
                                <td><input type="checkbox" name="perm[<?php echo $u['id']; ?>][view]" <?php echo $perm && $perm['can_view'] ? 'checked' : ''; ?>></td>
                                <td><input type="checkbox" name="perm[<?php echo $u['id']; ?>][edit]" <?php echo $perm && $perm['can_edit'] ? 'checked' : ''; ?>></td>
                                <td><input type="checkbox" name="perm[<?php echo $u['id']; ?>][delete]" <?php echo $perm && $perm['can_delete'] ? 'checked' : ''; ?>></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-end">
                    <button class="btn btn-primary"><i class="fas fa-save"></i> ذخیره دسترسی‌ها</button>
                </div>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

