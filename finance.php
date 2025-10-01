<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
include 'config.php';

// فهرست پروژه‌ها با جمع تراکنش‌ها
$projects = $pdo->query("SELECT p.*, c.full_name as customer_name
  FROM finance_projects p
  LEFT JOIN customers c ON p.customer_id = c.id
  ORDER BY p.created_at DESC")->fetchAll();

function sumTransactions($pdo, $project_id, $type) {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS total FROM finance_transactions WHERE project_id = ? AND tx_type = ?");
    $stmt->execute([$project_id, $type]);
    $row = $stmt->fetch();
    return (float)($row['total'] ?? 0);
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>امور مالی - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2><i class="fas fa-coins"></i> مدیریت مالی پروژه‌ها</h2>
            <p class="text-muted mb-0">نمای کلی وضعیت مالی (پیش‌پرداخت، میان‌پرداخت، تسویه)</p>
        </div>
        <?php if (($_SESSION['role'] ?? '') === 'ادمین'): ?>
        <a href="finance_admin.php" class="btn btn-primary"><i class="fas fa-sliders-h"></i> مدیریت مالی (ادمین)</a>
        <?php endif; ?>
    </div>

    <div class="row g-3">
        <?php foreach ($projects as $p): ?>
        <?php
            $prePaid = sumTransactions($pdo, $p['id'], 'prepayment');
            $midPaid = sumTransactions($pdo, $p['id'], 'midpayment');
            $settled = sumTransactions($pdo, $p['id'], 'settlement');
            $received = $prePaid + $midPaid + $settled;
            $remaining = max(0, (float)$p['total_amount'] - $received);
        ?>
        <div class="col-md-6">
            <div class="card edu-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0"><?php echo htmlspecialchars($p['name']); ?></h5>
                        <span class="badge bg-secondary"><?php echo $p['project_code']; ?></span>
                    </div>
                    <small class="text-muted d-block mb-2">
                        <i class="fas fa-user"></i> <?php echo $p['customer_name'] ?: 'بدون مشتری'; ?> |
                        <i class="fas fa-money-bill"></i> مبلغ کل: <?php echo number_format((float)$p['total_amount']); ?>
                    </small>

                    <div class="row text-center mb-2">
                        <div class="col">
                            <div class="edu-stat p-2" style="background:linear-gradient(135deg,#84fab0,#8fd3f4)">
                                <div class="fw-bold">پیش‌پرداخت</div>
                                <div><?php echo number_format($prePaid); ?></div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="edu-stat p-2" style="background:linear-gradient(135deg,#f093fb,#f5576c)">
                                <div class="fw-bold">میان‌پرداخت</div>
                                <div><?php echo number_format($midPaid); ?></div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="edu-stat p-2" style="background:linear-gradient(135deg,#4facfe,#00f2fe)">
                                <div class="fw-bold">تسویه</div>
                                <div><?php echo number_format($settled); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">دریافتی: <?php echo number_format($received); ?></small>
                        <span class="badge bg-<?php echo $remaining > 0 ? 'warning' : 'success'; ?>">
                            باقیمانده: <?php echo number_format($remaining); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (count($projects) === 0): ?>
            <div class="col-12 text-center text-muted py-5">
                <i class="fas fa-info-circle fa-2x mb-2"></i>
                <div>پروژه‌ای ثبت نشده است</div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

