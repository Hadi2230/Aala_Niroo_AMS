<?php
// profile.php - نسخهٔ نهایی و مقاوم
session_start();
require_once 'config.php';

// تابع کمکی برای خروجی امن HTML
function e($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

// مقداردهی اولیه متغیرها
$assetId = (int)($_GET['id'] ?? $_POST['asset_id'] ?? 0);
$assetData = null;
$allAssets = [];
$services = [];
$tasks = [];
$correspondence = [];
$assignments = [];

// تابع بررسی CSRF
function safeVerifyCsrf($token) {
    if (function_exists('verifyCsrfToken')) {
        return verifyCsrfToken($token);
    }
    return true;
}

// پردازش فرم‌ها (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // CSRF
        $csrfToken = $_POST['csrf_token'] ?? $_POST['_csrf'] ?? '';
        if (!safeVerifyCsrf($csrfToken)) {
            throw new Exception('Invalid CSRF token');
        }

        $postAssetId = (int)($_POST['asset_id'] ?? 0);
        if ($postAssetId <= 0) {
            throw new Exception('شناسهٔ دارایی (asset_id) ارسال نشده است.');
        }

        // ---------- ثبت سرویس ----------
        if (isset($_POST['add_service'])) {
            $service_date = trim($_POST['service_date'] ?? '') ?: null;
            $service_type = trim($_POST['service_type'] ?? '') ?: null;
            $service_provider = trim($_POST['service_provider'] ?? '') ?: null;
            $description = trim($_POST['description'] ?? '') ?: null;
            $cost = $_POST['cost'] !== '' ? $_POST['cost'] : null;

            $ins = $pdo->prepare(
                "INSERT INTO asset_services
                (asset_id, service_date, service_type, performed_by, summary, cost)
                VALUES (?, ?, ?, ?, ?, ?)"
            );
            $ins->execute([
                $postAssetId,
                $service_date,
                $service_type,
                $service_provider,
                $description,
                $cost,
            ]);

            $_SESSION['success'] = 'سرویس با موفقیت ثبت شد.';
            header('Location: profile.php?id=' . $postAssetId);
            exit;
        }

        // ---------- ثبت تسک ----------
        if (isset($_POST['add_task'])) {
            $task_name = trim($_POST['task_name'] ?? '');
            if ($task_name === '') {
                throw new Exception('عنوان تسک الزامی است.');
            }
            $assigned_to = trim($_POST['assigned_to'] ?? '') ?: null;
            $due_date = trim($_POST['due_date'] ?? '') ?: null;
            $status = trim($_POST['status'] ?? 'pending');
            $allowed = ['pending', 'in_progress', 'completed', 'cancelled'];
            if (!in_array($status, $allowed, true)) $status = 'pending';
            $task_description = trim($_POST['description'] ?? '') ?: null;

            $ins = $pdo->prepare(
                "INSERT INTO maintenance_tasks
                (asset_id, title, assigned_to, planned_date, status, description)
                VALUES (?, ?, ?, ?, ?, ?)"
            );
            $ins->execute([
                $postAssetId,
                $task_name,
                $assigned_to,
                $due_date,
                $status,
                $task_description,
            ]);

            $_SESSION['success'] = 'تسک با موفقیت اضافه شد.';
            header('Location: profile.php?id=' . $postAssetId);
            exit;
        }

        // ---------- ثبت مکاتبه ----------
        if (isset($_POST['add_correspondence'])) {
            $corr_date = trim($_POST['corr_date'] ?? '') ?: date('Y-m-d');
            $summary = trim($_POST['summary'] ?? '') ?: null;
            $notes = trim($_POST['notes'] ?? '') ?: null;
            $filePath = null;

            if (!empty($_FILES['corr_file']['name'])) {
                $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'correspondence' . DIRECTORY_SEPARATOR . $postAssetId;
                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
                    throw new Exception('خطا در ایجاد پوشه آپلود.');
                }
                $orig = basename($_FILES['corr_file']['name']);
                $ext = pathinfo($orig, PATHINFO_EXTENSION);
                $filename = time() . '_' . bin2hex(random_bytes(6)) . ($ext ? '.' . $ext : '');
                $targetFile = $uploadDir . DIRECTORY_SEPARATOR . $filename;
                if (!move_uploaded_file($_FILES['corr_file']['tmp_name'], $targetFile)) {
                    throw new Exception('آپلود فایل ناموفق بود.');
                }
                $filePath = 'uploads/correspondence/' . $postAssetId . '/' . $filename;
            }

            $ins = $pdo->prepare(
                "INSERT INTO asset_correspondence
                (asset_id, letter_date, subject, notes, file_path)
                VALUES (?, ?, ?, ?, ?)"
            );
            $ins->execute([
                $postAssetId,
                $corr_date,
                $summary,
                $notes,
                $filePath,
            ]);

            $_SESSION['success'] = 'مکاتبه با موفقیت ثبت شد.';
            header('Location: profile.php?id=' . $postAssetId);
            exit;
        }

    } catch (Throwable $ex) {
        error_log('profile.php POST error: ' . $ex->getMessage());
        $_SESSION['error'] = 'خطا در پردازش فرم: ' . $ex->getMessage();
        header('Location: profile.php?id=' . ($postAssetId ?: $assetId));
        exit;
    }
}

// ---------- بارگذاری اطلاعات برای نمایش ----------
try {
    if ($assetId > 0) {
        // اطلاعات دارایی
        $stmt = $pdo->prepare("SELECT * FROM assets WHERE id = ?");
        $stmt->execute([$assetId]);
        $assetData = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        
        if ($assetData) {
            // سرویس‌های دارایی
            $stmt = $pdo->prepare("SELECT * FROM asset_services WHERE asset_id = ? ORDER BY service_date DESC, created_at DESC");
            $stmt->execute([$assetId]);
            $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // تسک‌های نگهداری
            $stmt = $pdo->prepare("SELECT * FROM maintenance_tasks WHERE asset_id = ? ORDER BY planned_date DESC, created_at DESC");
            $stmt->execute([$assetId]);
            $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // مکاتبات
            $stmt = $pdo->prepare("SELECT * FROM asset_correspondence WHERE asset_id = ? ORDER BY letter_date DESC, created_at DESC");
            $stmt->execute([$assetId]);
            $correspondence = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // انتساب‌های دارایی
            $stmt = $pdo->prepare("
                SELECT aa.*, c.full_name AS customer_name, c.phone AS customer_phone,
                       ad.installation_date, ad.warranty_start_date, ad.warranty_end_date
                FROM asset_assignments aa
                JOIN customers c ON aa.customer_id = c.id
                LEFT JOIN assignment_details ad ON aa.id = ad.assignment_id
                WHERE aa.asset_id = ?
                ORDER BY aa.assignment_date DESC
            ");
            $stmt->execute([$assetId]);
            $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        // لیست همه دارایی‌ها
        $stmt = $pdo->query("SELECT * FROM assets ORDER BY id DESC");
        $allAssets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $ex) {
    error_log('profile.php SELECT error: ' . $ex->getMessage());
    $_SESSION['error'] = 'خطا در بارگذاری اطلاعات: ' . $ex->getMessage();
    $assetData = null;
    $allAssets = [];
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <title><?= $assetId > 0 ? 'پروفایل دارایی ' . e($assetData['device_identifier'] ?? '') : 'لیست دارایی‌ها' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <style>
        html, body { font-family: Vazirmatn, Tahoma, Arial, sans-serif; }
        .status-badge {
            font-size: 0.8em;
            padding: 0.25em 0.5em;
        }
        .file-link {
            color: #0d6efd;
            text-decoration: none;
        }
        .file-link:hover {
            text-decoration: underline;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .nav-tabs .nav-link.active {
            background-color: #667eea;
            color: white;
            border-color: #667eea;
        }
        .nav-tabs .nav-link {
            color: #667eea;
        }
        .nav-tabs .nav-link:hover {
            border-color: #667eea;
            color: #667eea;
        }
    </style>
</head>
<body class="container py-4">

<?php if (!empty($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle"></i> <?= e($_SESSION['success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if (!empty($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-triangle"></i> <?= e($_SESSION['error']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<?php if ($assetId > 0): ?>
    <?php if ($assetData): ?>
        <!-- هدر پروفایل -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">پروفایل دستگاه</h2>
                <h4 class="text-primary mb-0"><?= e($assetData['device_identifier'] ?? $assetData['name'] ?? 'بدون شناسه') ?></h4>
            </div>
            <div>
                <a href="profile.php" class="btn btn-outline-secondary">
                    <i class="fas fa-list"></i> لیست همه دارایی‌ها
                </a>
                <a href="assets.php" class="btn btn-outline-primary">
                    <i class="fas fa-cog"></i> مدیریت دارایی‌ها
                </a>
            </div>
        </div>

        <!-- اطلاعات کلی دارایی -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-info-circle"></i> اطلاعات کلی دستگاه</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>نام دستگاه:</strong> <?= e($assetData['name'] ?? $assetData['device_name'] ?? '-') ?></p>
                        <p><strong>مدل:</strong> <?= e($assetData['model'] ?? '-') ?></p>
                        <p><strong>سریال:</strong> <?= e($assetData['serial_number'] ?? '-') ?></p>
                        <p><strong>نوع:</strong> <?= e($assetData['type'] ?? $assetData['type_name'] ?? '-') ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>مکان:</strong> <?= e($assetData['location'] ?? '-') ?></p>
                        <p><strong>تاریخ خرید:</strong> <?= e($assetData['purchase_date'] ?? '-') ?></p>
                        <p><strong>وضعیت:</strong> 
                            <span class="badge bg-<?= ($assetData['status'] ?? '') === 'فعال' ? 'success' : 'warning' ?>">
                                <?= e($assetData['status'] ?? 'نامشخص') ?>
                            </span>
                        </p>
                        <p><strong>تاریخ ثبت:</strong> <?= e($assetData['created_at'] ?? '-') ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- تب‌ها برای نمایش اطلاعات -->
        <ul class="nav nav-tabs" id="assetTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#services" role="tab">
                    <i class="fas fa-wrench"></i> سرویس‌ها (<?= count($services) ?>)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#tasks" role="tab">
                    <i class="fas fa-tasks"></i> تسک‌ها (<?= count($tasks) ?>)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#correspondence" role="tab">
                    <i class="fas fa-envelope"></i> مکاتبات (<?= count($correspondence) ?>)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#assignments" role="tab">
                    <i class="fas fa-handshake"></i> انتساب‌ها (<?= count($assignments) ?>)
                </a>
            </li>
        </ul>

        <div class="tab-content border border-top-0 p-4" style="min-height: 400px;">
            <!-- تب سرویس‌ها -->
            <div class="tab-pane fade show active" id="services" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5>سرویس‌های انجام شده</h5>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addServiceModal">
                        <i class="fas fa-plus"></i> ثبت سرویس جدید
                    </button>
                </div>
                
                <?php if (!empty($services)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>تاریخ سرویس</th>
                                    <th>نوع سرویس</th>
                                    <th>مجری</th>
                                    <th>خلاصه</th>
                                    <th>هزینه</th>
                                    <th>تاریخ ثبت</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($services as $service): ?>
                                <tr>
                                    <td><?= e($service['service_date'] ?? '-') ?></td>
                                    <td><?= e($service['service_type'] ?? '-') ?></td>
                                    <td><?= e($service['performed_by'] ?? '-') ?></td>
                                    <td><?= e($service['summary'] ?? '-') ?></td>
                                    <td>
                                        <?php if ($service['cost']): ?>
                                            <?= number_format($service['cost'], 0) ?> تومان
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e($service['created_at'] ?? '-') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> هیچ سرویسی برای این دستگاه ثبت نشده است.
                    </div>
                <?php endif; ?>
            </div>

            <!-- تب تسک‌ها -->
            <div class="tab-pane fade" id="tasks" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5>تسک‌های نگهداری</h5>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addTaskModal">
                        <i class="fas fa-plus"></i> ثبت تسک جدید
                    </button>
                </div>
                
                <?php if (!empty($tasks)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>عنوان</th>
                                    <th>مسئول</th>
                                    <th>تاریخ برنامه</th>
                                    <th>وضعیت</th>
                                    <th>توضیحات</th>
                                    <th>تاریخ ثبت</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tasks as $task): ?>
                                <tr>
                                    <td><?= e($task['title'] ?? '-') ?></td>
                                    <td><?= e($task['assigned_to'] ?? '-') ?></td>
                                    <td><?= e($task['planned_date'] ?? '-') ?></td>
                                    <td>
                                        <?php
                                        $statusClass = match($task['status']) {
                                            'completed' => 'success',
                                            'in_progress' => 'warning',
                                            'cancelled' => 'danger',
                                            default => 'secondary'
                                        };
                                        $statusText = match($task['status']) {
                                            'pending' => 'در انتظار',
                                            'in_progress' => 'در حال انجام',
                                            'completed' => 'انجام شده',
                                            'cancelled' => 'لغو شده',
                                            default => 'نامشخص'
                                        };
                                        ?>
                                        <span class="badge bg-<?= $statusClass ?> status-badge">
                                            <?= $statusText ?>
                                        </span>
                                    </td>
                                    <td><?= e($task['description'] ?? '-') ?></td>
                                    <td><?= e($task['created_at'] ?? '-') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> هیچ تسکی برای این دستگاه ثبت نشده است.
                    </div>
                <?php endif; ?>
            </div>

            <!-- تب مکاتبات -->
            <div class="tab-pane fade" id="correspondence" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5>مکاتبات</h5>
                    <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#addCorrespondenceModal">
                        <i class="fas fa-plus"></i> ثبت مکاتبه جدید
                    </button>
                </div>
                
                <?php if (!empty($correspondence)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>تاریخ</th>
                                    <th>موضوع</th>
                                    <th>یادداشت</th>
                                    <th>فایل</th>
                                    <th>تاریخ ثبت</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($correspondence as $corr): ?>
                                <tr>
                                    <td><?= e($corr['letter_date'] ?? '-') ?></td>
                                    <td><?= e($corr['subject'] ?? '-') ?></td>
                                    <td><?= e($corr['notes'] ?? '-') ?></td>
                                    <td>
                                        <?php if ($corr['file_path']): ?>
                                            <a href="<?= e($corr['file_path']) ?>" class="file-link" target="_blank">
                                                <i class="fas fa-download"></i> دانلود
                                            </a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e($corr['created_at'] ?? '-') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> هیچ مکاتبه‌ای برای این دستگاه ثبت نشده است.
                    </div>
                <?php endif; ?>
            </div>

            <!-- تب انتساب‌ها -->
            <div class="tab-pane fade" id="assignments" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5>انتساب‌های دستگاه</h5>
                    <a href="assignments.php" class="btn btn-warning">
                        <i class="fas fa-plus"></i> انتساب جدید
                    </a>
                </div>
                
                <?php if (!empty($assignments)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>مشتری</th>
                                    <th>تلفن</th>
                                    <th>تاریخ انتساب</th>
                                    <th>تاریخ نصب</th>
                                    <th>گارانتی از</th>
                                    <th>گارانتی تا</th>
                                    <th>وضعیت</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assignments as $assignment): ?>
                                <tr>
                                    <td><?= e($assignment['customer_name'] ?? '-') ?></td>
                                    <td><?= e($assignment['customer_phone'] ?? '-') ?></td>
                                    <td><?= e($assignment['assignment_date'] ?? '-') ?></td>
                                    <td><?= e($assignment['installation_date'] ?? '-') ?></td>
                                    <td><?= e($assignment['warranty_start_date'] ?? '-') ?></td>
                                    <td><?= e($assignment['warranty_end_date'] ?? '-') ?></td>
                                    <td>
                                        <span class="badge bg-<?= ($assignment['assignment_status'] ?? '') === 'فعال' ? 'success' : 'warning' ?> status-badge">
                                            <?= e($assignment['assignment_status'] ?? 'نامشخص') ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> این دستگاه به هیچ مشتری انتساب داده نشده است.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- مودال‌ها -->
        <!-- Modal: addService -->
        <div class="modal fade" id="addServiceModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="post">
                        <?php if (function_exists('csrf_field')) csrf_field(); ?>
                        <input type="hidden" name="asset_id" value="<?= e($assetId) ?>">
                        <div class="modal-header">
                            <h5 class="modal-title">ثبت سرویس جدید</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">تاریخ سرویس</label>
                                <input type="date" name="service_date" class="form-control" value="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">نوع سرویس</label>
                                <input type="text" name="service_type" class="form-control" placeholder="مثال: تعمیر، نگهداری، کالیبراسیون">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">مجری سرویس</label>
                                <input type="text" name="service_provider" class="form-control" placeholder="نام شرکت یا فرد مجری">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">خلاصه سرویس</label>
                                <textarea name="description" class="form-control" rows="3" placeholder="توضیحات انجام شده"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">هزینه (تومان)</label>
                                <input type="number" step="0.01" name="cost" class="form-control" placeholder="0">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                            <button type="submit" name="add_service" class="btn btn-primary">ذخیره</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal: addTask -->
        <div class="modal fade" id="addTaskModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="post">
                        <?php if (function_exists('csrf_field')) csrf_field(); ?>
                        <input type="hidden" name="asset_id" value="<?= e($assetId) ?>">
                        <div class="modal-header">
                            <h5 class="modal-title">ثبت تسک جدید</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">عنوان تسک *</label>
                                <input type="text" name="task_name" class="form-control" required placeholder="عنوان تسک">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">مسئول</label>
                                <input type="text" name="assigned_to" class="form-control" placeholder="نام مسئول">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">تاریخ برنامه</label>
                                <input type="date" name="due_date" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">وضعیت</label>
                                <select name="status" class="form-control">
                                    <option value="pending">در انتظار</option>
                                    <option value="in_progress">در حال انجام</option>
                                    <option value="completed">انجام شده</option>
                                    <option value="cancelled">لغو شده</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">توضیحات</label>
                                <textarea name="description" class="form-control" rows="3" placeholder="توضیحات تکمیلی"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                            <button type="submit" name="add_task" class="btn btn-success">ذخیره</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal: addCorrespondence -->
        <div class="modal fade" id="addCorrespondenceModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="post" enctype="multipart/form-data">
                        <?php if (function_exists('csrf_field')) csrf_field(); ?>
                        <input type="hidden" name="asset_id" value="<?= e($assetId) ?>">
                        <div class="modal-header">
                            <h5 class="modal-title">ثبت مکاتبه جدید</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">تاریخ</label>
                                <input type="date" name="corr_date" class="form-control" value="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">موضوع</label>
                                <input type="text" name="summary" class="form-control" placeholder="موضوع مکاتبه">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">یادداشت</label>
                                <textarea name="notes" class="form-control" rows="3" placeholder="توضیحات"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">فایل ضمیمه</label>
                                <input type="file" name="corr_file" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <div class="form-text">فرمت‌های مجاز: PDF, DOC, DOCX, JPG, PNG</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                            <button type="submit" name="add_correspondence" class="btn btn-info">ذخیره</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    <?php else: ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> هیچ دارایی‌ای با این شناسه یافت نشد.
        </div>
        <p><a href="profile.php" class="btn btn-outline-primary">نمایش همه دارایی‌ها</a></p>
    <?php endif; ?>

<?php else: // صفحه لیست همه دارایی‌ها ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>لیست دارایی‌ها</h2>
        <a href="assets.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> افزودن دارایی جدید
        </a>
    </div>
    
    <?php if (!empty($allAssets)): ?>
        <div class="row">
            <?php foreach ($allAssets as $a): ?>
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">
                                <a href="profile.php?id=<?= e($a['id']) ?>" class="text-decoration-none">
                                    <?= e($a['device_identifier'] ?? $a['name'] ?? ('ID:' . $a['id'])) ?>
                                </a>
                            </h5>
                            <p class="card-text">
                                <small class="text-muted">
                                    <i class="fas fa-tag"></i> <?= e($a['type'] ?? 'نامشخص') ?><br>
                                    <i class="fas fa-hashtag"></i> <?= e($a['serial_number'] ?? 'بدون سریال') ?><br>
                                    <i class="fas fa-map-marker-alt"></i> <?= e($a['location'] ?? 'نامشخص') ?>
                                </small>
                            </p>
                        </div>
                        <div class="card-footer">
                            <a href="profile.php?id=<?= e($a['id']) ?>" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-eye"></i> مشاهده پروفایل
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> فعلاً هیچ دارایی‌ای ثبت نشده است.
        </div>
    <?php endif; ?>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>