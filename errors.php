<?php
session_start();
include 'config.php';

// فقط ادمین
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'ادمین') {
    header('Location: login.php');
    exit();
}

// خواندن آخرین خطاها از فایل لاگ
$log_path = __DIR__ . '/logs/php-errors.log';
$lines = [];
if (file_exists($log_path)) {
    $content = @file($log_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $lines = array_slice(array_reverse($content), 0, 300); // آخرین 300 خط
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>خطاهای سیستم - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>آخرین خطاهای سیستم (php-errors.log)</span>
                <a href="errors.php" class="btn btn-sm btn-secondary">تازه‌سازی</a>
            </div>
            <div class="card-body">
                <?php if (!$lines): ?>
                    <p class="text-muted">خطایی یافت نشد یا فایل لاگ خالی است.</p>
                <?php else: ?>
                    <pre class="p-3" style="background:#0b1220;color:#e5e7eb;border-radius:8px;max-height:70vh;overflow:auto;">
<?php foreach ($lines as $l) { echo htmlspecialchars($l) . "\n"; } ?>
                    </pre>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
