<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['user_id'])) { 
    header('Location: login.php'); 
    exit(); 
}
require_once __DIR__ . '/config.php';

$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'ادمین';

$submission_id = isset($_GET['submission_id']) ? (int)$_GET['submission_id'] : 0;

// ایجاد جداول اگر وجود ندارند
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS surveys (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        is_active BOOLEAN DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS survey_questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        survey_id INT NOT NULL,
        question_text TEXT NOT NULL,
        answer_type ENUM('text', 'textarea', 'radio', 'checkbox', 'select', 'number', 'date') DEFAULT 'text',
        options JSON NULL,
        is_required BOOLEAN DEFAULT 1,
        sort_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS survey_submissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        survey_id INT NOT NULL,
        customer_id INT NULL,
        asset_id INT NULL,
        status ENUM('draft', 'completed', 'pending') DEFAULT 'draft',
        submitted_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE,
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
        FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE SET NULL,
        FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS survey_responses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        submission_id INT NOT NULL,
        question_id INT NOT NULL,
        response_text TEXT,
        response_data JSON NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (submission_id) REFERENCES survey_submissions(id) ON DELETE CASCADE,
        FOREIGN KEY (question_id) REFERENCES survey_questions(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // اضافه کردن نظرسنجی نمونه اگر وجود ندارد
    $stmt = $pdo->prepare("INSERT IGNORE INTO surveys (id, title, description) VALUES (1, 'نظرسنجی رضایت مشتری', 'نظرسنجی عمومی رضایت مشتریان از خدمات')");
    $stmt->execute();

    // اضافه کردن سوالات نمونه
    $sample_questions = [
        [1, 'نام و نام خانوادگی', 'text', 1, 1, null],
        [1, 'شماره تماس', 'text', 1, 2, null],
        [1, 'آیا از خدمات ما راضی هستید؟', 'radio', 1, 3, json_encode(['بله', 'خیر', 'تا حدودی'])],
        [1, 'نظر شما در مورد کیفیت خدمات چیست؟', 'textarea', 0, 4, null],
        [1, 'امتیاز کلی (1-10)', 'number', 1, 5, null]
    ];

    foreach ($sample_questions as $q) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO survey_questions (survey_id, question_text, answer_type, is_required, sort_order, options) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute($q);
    }

} catch (Exception $e) {
    // خطا در ایجاد جداول
}

// لیست ثبت‌ها
$subs = [];
try {
    $st = $pdo->query("SELECT s.id, s.created_at, s.status, sv.title AS survey_title,
                              c.full_name AS customer_name, c.company AS customer_company,
                              a.name AS asset_name, a.serial_number,
                              u.username AS submitted_by_name
                       FROM survey_submissions s
                       JOIN surveys sv ON sv.id = s.survey_id
                       LEFT JOIN customers c ON c.id = s.customer_id
                       LEFT JOIN assets a ON a.id = s.asset_id
                       LEFT JOIN users u ON u.id = s.submitted_by
                       ORDER BY s.id DESC LIMIT 200");
    $subs = $st ? $st->fetchAll() : [];
} catch (Throwable $e) {
    $error_message = "خطا در دریافت لیست نظرسنجی‌ها: " . $e->getMessage();
}

// پیش‌نمایش
$viewSubmission = null; 
$viewResponses = [];
if ($submission_id > 0) {
    try {
        $st = $pdo->prepare("SELECT s.*, sv.title AS survey_title, 
                                    c.full_name AS customer_name, c.company AS customer_company,
                                    a.name AS asset_name, a.serial_number,
                                    u.username AS submitted_by_name
                             FROM survey_submissions s
                             JOIN surveys sv ON sv.id = s.survey_id
                             LEFT JOIN customers c ON c.id = s.customer_id
                             LEFT JOIN assets a ON a.id = s.asset_id
                             LEFT JOIN users u ON u.id = s.submitted_by
                             WHERE s.id = ?");
        $st->execute([$submission_id]);
        $viewSubmission = $st->fetch();
        
        if ($viewSubmission) {
            $rt = $pdo->prepare("SELECT r.response_text, q.question_text, q.answer_type
                                 FROM survey_responses r
                                 JOIN survey_questions q ON q.id = r.question_id
                                 WHERE r.submission_id = ?
                                 ORDER BY q.sort_order, q.id");
            $rt->execute([$submission_id]);
            $viewResponses = $rt->fetchAll();
        }
    } catch (Exception $e) {
        $view_error = "خطا در دریافت جزئیات نظرسنجی: " . $e->getMessage();
    }
}

// حذف ثبت
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_submission'])) {
    if ($is_admin && isset($_POST['submission_id'])) {
        try {
            $submission_id_to_delete = (int)$_POST['submission_id'];
            $pdo->prepare("DELETE FROM survey_submissions WHERE id = ?")->execute([$submission_id_to_delete]);
            $success_message = "ثبت با موفقیت حذف شد";
            // رفرش صفحه
            header("Location: survey_list.php");
            exit();
        } catch (Exception $e) {
            $error_message = "خطا در حذف ثبت: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ثبت‌های نظرسنجی - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <style>
        body { 
            font-family: Vazirmatn, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding-top: 80px;
        }
        .main-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            margin: 20px 0;
        }
        .card { 
            border: none; 
            border-radius: 15px; 
            box-shadow: 0 8px 25px rgba(0,0,0,0.1); 
            margin-bottom: 20px; 
            overflow: hidden;
        }
        .card-header {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white; 
            border-radius: 15px 15px 0 0 !important;
            padding: 20px 25px;
        }
        .table th {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            border: none;
            font-weight: 600;
        }
        .btn-primary {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            border: none;
            border-radius: 10px;
            padding: 8px 16px;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(44, 62, 80, 0.4);
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
        }
        .status-draft { background-color: #ffc107; color: #000; }
        .status-completed { background-color: #28a745; color: #fff; }
        .status-pending { background-color: #17a2b8; color: #fff; }
    </style>
</head>
<body>
    <?php if (file_exists('navbar.php')) include 'navbar.php'; ?>
    
    <div class="container">
        <div class="main-container">
            <div class="container mt-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="mb-0"><i class="fas fa-clipboard-list me-2"></i>ثبت‌های نظرسنجی</h4>
                    <div>
                        <a href="survey_customer_search.php" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i>نظرسنجی جدید
                        </a>
                        <a href="create_survey_tables.php" class="btn btn-outline-info btn-sm">
                            <i class="fas fa-database me-1"></i>بررسی جداول
                        </a>
                    </div>
                </div>

                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>لیست نظرسنجی‌ها</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($subs)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                                <p class="text-muted">هیچ نظرسنجی‌ای ثبت نشده است.</p>
                                <a href="survey_customer_search.php" class="btn btn-primary">
                                    <i class="fas fa-plus me-1"></i>شروع نظرسنجی جدید
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>نظرسنجی</th>
                                            <th>مشتری</th>
                                            <th>دستگاه</th>
                                            <th>سریال</th>
                                            <th>وضعیت</th>
                                            <th>تاریخ</th>
                                            <th>ثبت کننده</th>
                                            <th>عملیات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($subs as $s): ?>
                                            <tr>
                                                <td><?php echo (int)$s['id']; ?></td>
                                                <td><?php echo htmlspecialchars($s['survey_title']); ?></td>
                                                <td>
                                                    <?php 
                                                    $customer_name = $s['customer_name'] ?: $s['customer_company'] ?: '-';
                                                    echo htmlspecialchars($customer_name);
                                                    ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($s['asset_name'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($s['serial_number'] ?? '-'); ?></td>
                                                <td>
                                                    <span class="status-badge status-<?php echo $s['status']; ?>">
                                                        <?php 
                                                        $status_labels = [
                                                            'draft' => 'پیش‌نویس',
                                                            'completed' => 'تکمیل شده',
                                                            'pending' => 'در انتظار'
                                                        ];
                                                        echo $status_labels[$s['status']] ?? $s['status'];
                                                        ?>
                                                    </span>
                                                </td>
                                                <td><?php echo jalali_format($s['created_at']); ?></td>
                                                <td><?php echo htmlspecialchars($s['submitted_by_name'] ?? '-'); ?></td>
                                                <td class="d-flex gap-1">
                                                    <a class="btn btn-sm btn-outline-primary" href="survey_list.php?submission_id=<?php echo (int)$s['id']; ?>">
                                                        <i class="fas fa-eye"></i> مشاهده
                                                    </a>
                                                    <?php if ($is_admin): ?>
                                                        <a class="btn btn-sm btn-outline-secondary" href="survey_edit.php?submission_id=<?php echo (int)$s['id']; ?>">
                                                            <i class="fas fa-edit"></i> ویرایش
                                                        </a>
                                                        <form method="post" action="survey_list.php" onsubmit="return confirm('آیا مطمئن هستید که می‌خواهید این ثبت را حذف کنید؟');" style="display: inline;">
                                                            <input type="hidden" name="submission_id" value="<?php echo (int)$s['id']; ?>">
                                                            <button class="btn btn-sm btn-outline-danger" name="delete_submission" type="submit">
                                                                <i class="fas fa-trash"></i> حذف
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($viewSubmission): ?>
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-eye me-2"></i>پیش‌نمایش ثبت #<?php echo (int)$viewSubmission['id']; ?> (<?php echo htmlspecialchars($viewSubmission['survey_title']); ?>)</span>
                        <button class="btn btn-sm btn-outline-primary" onclick="window.print()">
                            <i class="fas fa-print me-1"></i>چاپ
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>مشتری:</strong> <?php echo htmlspecialchars($viewSubmission['customer_name'] ?: $viewSubmission['customer_company'] ?: '-'); ?>
                            </div>
                            <div class="col-md-6">
                                <strong>دستگاه:</strong> <?php echo htmlspecialchars($viewSubmission['asset_name'] ?? '-'); ?>
                            </div>
                            <div class="col-md-6">
                                <strong>سریال:</strong> <?php echo htmlspecialchars($viewSubmission['serial_number'] ?? '-'); ?>
                            </div>
                            <div class="col-md-6">
                                <strong>وضعیت:</strong> 
                                <span class="status-badge status-<?php echo $viewSubmission['status']; ?>">
                                    <?php 
                                    $status_labels = [
                                        'draft' => 'پیش‌نویس',
                                        'completed' => 'تکمیل شده',
                                        'pending' => 'در انتظار'
                                    ];
                                    echo $status_labels[$viewSubmission['status']] ?? $viewSubmission['status'];
                                    ?>
                                </span>
                            </div>
                            <div class="col-md-6">
                                <strong>تاریخ:</strong> <?php echo jalali_format($viewSubmission['created_at']); ?>
                            </div>
                            <div class="col-md-6">
                                <strong>ثبت کننده:</strong> <?php echo htmlspecialchars($viewSubmission['submitted_by_name'] ?? '-'); ?>
                            </div>
                        </div>
                        
                        <?php if (empty($viewResponses)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>پاسخی ثبت نشده است.
                            </div>
                        <?php else: ?>
                            <h6 class="mb-3">پاسخ‌ها:</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped">
                                    <thead>
                                        <tr>
                                            <th>سوال</th>
                                            <th>پاسخ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($viewResponses as $r): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($r['question_text']); ?></td>
                                                <td><?php echo htmlspecialchars($r['response_text']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
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