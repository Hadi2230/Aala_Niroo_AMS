<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'ادمین') {
    header('Location: finance.php');
    exit();
}
include 'config.php';

// ایجاد پروژه جدید
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_project') {
    try {
        $name = sanitizeInput($_POST['name'] ?? '');
        $project_code = sanitizeInput($_POST['project_code'] ?? '');
        $customer_id = isset($_POST['customer_id']) && $_POST['customer_id'] !== '' ? (int)$_POST['customer_id'] : null;
        $total_amount = (float)($_POST['total_amount'] ?? 0);
        $currency = sanitizeInput($_POST['currency'] ?? 'IRR');
        $pre_due = (float)($_POST['prepayment_due'] ?? 0);
        $mid_due = (float)($_POST['midpayment_due'] ?? 0);
        $set_due = (float)($_POST['settlement_due'] ?? 0);
        $notes = sanitizeInput($_POST['notes'] ?? '');

        if ($name === '') { throw new Exception('نام پروژه الزامی است'); }
        if ($project_code === '') { $project_code = 'PRJ-' . date('Ymd') . '-' . random_int(1000, 9999); }

        $stmt = $pdo->prepare("INSERT INTO finance_projects (project_code, name, customer_id, total_amount, currency, prepayment_due, midpayment_due, settlement_due, status, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'در حال انجام', ?, ?)");
        $stmt->execute([$project_code, $name, $customer_id, $total_amount, $currency, $pre_due, $mid_due, $set_due, $notes, $_SESSION['user_id']]);
        $_SESSION['success_message'] = 'پروژه با موفقیت ایجاد شد';
        header('Location: finance_admin.php');
        exit();
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
}

// ثبت تراکنش
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_transaction') {
    try {
        $project_id = (int)($_POST['project_id'] ?? 0);
        $tx_type = sanitizeInput($_POST['tx_type'] ?? '');
        $amount = (float)($_POST['amount'] ?? 0);
        $tx_date = sanitizeInput($_POST['tx_date'] ?? date('Y-m-d'));
        $method = sanitizeInput($_POST['method'] ?? '');
        $reference_no = sanitizeInput($_POST['reference_no'] ?? '');
        $notes = sanitizeInput($_POST['notes'] ?? '');
        $receipt_path = null;

        if (!in_array($tx_type, ['prepayment','midpayment','settlement','refund','adjustment'], true)) {
            throw new Exception('نوع تراکنش نامعتبر است');
        }
        if ($project_id <= 0) { throw new Exception('پروژه معتبر نیست'); }
        if ($amount <= 0 && $tx_type !== 'adjustment') { throw new Exception('مبلغ نامعتبر است'); }

        if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
            $dir = __DIR__ . '/uploads/finance/receipts/';
            if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
            @chmod($dir, 0775);
            $ext = strtolower(pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION));
            // اجازه به pdf، تصاویر و اسناد متنی رایج
            $allowed = ['pdf','jpg','jpeg','png','webp','gif','doc','docx'];
            if (!in_array($ext, $allowed, true)) { throw new Exception('فرمت رسید مجاز نیست'); }
            $fname = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            $full = $dir . $fname;
            if (!move_uploaded_file($_FILES['receipt']['tmp_name'], $full)) { throw new Exception('آپلود رسید ناموفق بود'); }
            $receipt_path = 'uploads/finance/receipts/' . $fname;
        }

        $stmt = $pdo->prepare("INSERT INTO finance_transactions (project_id, tx_type, amount, tx_date, method, reference_no, receipt_path, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$project_id, $tx_type, $amount, $tx_date, $method, $reference_no, $receipt_path, $notes, $_SESSION['user_id']]);
        $_SESSION['success_message'] = 'تراکنش ثبت شد';
        header('Location: finance_admin.php#p-' . $project_id);
        exit();
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
}

// تغییر وضعیت پروژه
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $pid = (int)($_POST['project_id'] ?? 0);
    $status = sanitizeInput($_POST['status'] ?? 'در حال انجام');
    $allowedStatus = ['در حال انجام','تسویه شده','متوقف','لغو شده'];
    if (in_array($status, $allowedStatus, true) && $pid > 0) {
        $stmt = $pdo->prepare("UPDATE finance_projects SET status = ? WHERE id = ?");
        $stmt->execute([$status, $pid]);
        $_SESSION['success_message'] = 'وضعیت پروژه بروزرسانی شد';
    }
    header('Location: finance_admin.php#p-' . $pid);
    exit();
}

// داده‌ها
$customers = $pdo->query("SELECT id, full_name FROM customers ORDER BY full_name")->fetchAll();
$projects = $pdo->query("SELECT p.*, c.full_name as customer_name
  FROM finance_projects p LEFT JOIN customers c ON p.customer_id = c.id ORDER BY p.created_at DESC")->fetchAll();

function getTransactions($pdo, $project_id) {
    $st = $pdo->prepare("SELECT * FROM finance_transactions WHERE project_id = ? ORDER BY tx_date DESC, id DESC");
    $st->execute([$project_id]);
    return $st->fetchAll();
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت مالی (ادمین) - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="fas fa-sliders-h"></i> مدیریت مالی (ادمین)</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">داشبورد</a></li>
                    <li class="breadcrumb-item"><a href="finance.php">امور مالی</a></li>
                    <li class="breadcrumb-item active">مدیریت</li>
                </ol>
            </nav>
        </div>
        <a href="#createProject" class="btn btn-primary" data-bs-toggle="collapse"><i class="fas fa-plus"></i> پروژه جدید</a>
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

    <div id="createProject" class="collapse mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white"><i class="fas fa-folder-plus"></i> ایجاد پروژه جدید</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="create_project">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">نام پروژه *</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">کد پروژه</label>
                            <input type="text" name="project_code" class="form-control" placeholder="اختیاری">
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
                        <div class="col-md-3">
                            <label class="form-label">مبلغ کل</label>
                            <input type="number" step="0.01" name="total_amount" class="form-control" value="0">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">ارز</label>
                            <input type="text" name="currency" class="form-control" value="IRR">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">پیش‌پرداخت (سررسید)</label>
                            <input type="number" step="0.01" name="prepayment_due" class="form-control" value="0">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">میان‌پرداخت (سررسید)</label>
                            <input type="number" step="0.01" name="midpayment_due" class="form-control" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">تسویه (سررسید)</label>
                            <input type="number" step="0.01" name="settlement_due" class="form-control" value="0">
                        </div>
                        <div class="col-12">
                            <label class="form-label">توضیحات</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="text-end mt-3">
                        <button class="btn btn-success"><i class="fas fa-save"></i> ذخیره</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php foreach ($projects as $p): $txs = getTransactions($pdo, $p['id']); ?>
        <div id="p-<?php echo $p['id']; ?>" class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <strong><?php echo htmlspecialchars($p['name']); ?></strong>
                    <span class="badge bg-secondary me-2"><?php echo $p['project_code']; ?></span>
                    <small class="text-muted">
                        <i class="fas fa-user"></i> <?php echo $p['customer_name'] ?: 'بدون مشتری'; ?> |
                        <i class="fas fa-money-bill"></i> مبلغ کل: <?php echo number_format((float)$p['total_amount']); ?>
                    </small>
                </div>
                <form method="POST" class="d-flex gap-2 m-0">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="project_id" value="<?php echo $p['id']; ?>">
                    <select name="status" class="form-select form-select-sm">
                        <?php foreach (['در حال انجام','تسویه شده','متوقف','لغو شده'] as $st): ?>
                            <option value="<?php echo $st; ?>" <?php echo $p['status']===$st?'selected':''; ?>><?php echo $st; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-sm btn-outline-primary"><i class="fas fa-sync"></i></button>
                </form>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-lg-4">
                        <div class="p-3 bg-light rounded">
                            <h6 class="mb-3"><i class="fas fa-plus-circle"></i> ثبت تراکنش</h6>
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="add_transaction">
                                <input type="hidden" name="project_id" value="<?php echo $p['id']; ?>">
                                <div class="mb-2">
                                    <label class="form-label">نوع</label>
                                    <select name="tx_type" class="form-select" required>
                                        <option value="prepayment">پیش‌پرداخت</option>
                                        <option value="midpayment">میان‌پرداخت</option>
                                        <option value="settlement">تسویه</option>
                                        <option value="refund">استرداد</option>
                                        <option value="adjustment">اصلاح</option>
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">مبلغ</label>
                                    <input type="number" step="0.01" name="amount" class="form-control" required>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">تاریخ</label>
                                    <input type="date" name="tx_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">روش پرداخت</label>
                                    <input type="text" name="method" class="form-control" placeholder="کارت، واریز، چک...">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">شماره مرجع</label>
                                    <input type="text" name="reference_no" class="form-control" placeholder="شناسه پیگیری...">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">رسید</label>
                                    <input type="file" name="receipt" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp,.gif,.doc,.docx">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">توضیحات</label>
                                    <textarea name="notes" class="form-control" rows="2"></textarea>
                                </div>
                                <div class="text-end">
                                    <button class="btn btn-success"><i class="fas fa-save"></i> ذخیره</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="col-lg-8">
                        <h6 class="mb-3"><i class="fas fa-list"></i> تراکنش‌ها</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>تاریخ</th>
                                        <th>نوع</th>
                                        <th>مبلغ</th>
                                        <th>روش</th>
                                        <th>مرجع</th>
                                        <th>رسید</th>
                                        <th>توضیحات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($txs as $t): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($t['tx_date']); ?></td>
                                        <td><?php echo $t['tx_type']; ?></td>
                                        <td><?php echo number_format((float)$t['amount']); ?></td>
                                        <td><?php echo htmlspecialchars($t['method']); ?></td>
                                        <td><?php echo htmlspecialchars($t['reference_no']); ?></td>
                                        <td>
                                            <?php if ($t['receipt_path']): ?>
                                                <a href="<?php echo $t['receipt_path']; ?>" target="_blank" class="btn btn-sm btn-outline-info"><i class="fas fa-file"></i></a>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($t['notes']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (count($txs) === 0): ?>
                                    <tr><td colspan="7" class="text-muted text-center">تراکنشی ثبت نشده است</td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

