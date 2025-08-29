<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// افزودن مشتری جدید
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_customer'])) {
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $company = trim($_POST['company']);
    $address = trim($_POST['address']);
    
    // اعتبارسنجی داده‌ها
    if (empty($full_name) || empty($phone)) {
        $error = "نام کامل و تلفن اجباری هستند!";
    } elseif (!preg_match('/^[0-9]{10,11}$/', $phone)) {
        $error = "شماره تلفن معتبر نیست!";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO customers (full_name, phone, company, address) VALUES (?, ?, ?, ?)");
            $stmt->execute([$full_name, $phone, $company, $address]);
            $success = "مشتری با موفقیت افزوده شد!";
            
            // رفرش صفحه برای جلوگیری از ارسال مجدد فرم
            header("Location: customers.php?success=" . urlencode($success));
            exit();
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = "شماره تلفن تکراری است!";
            } else {
                $error = "خطا در افزودن مشتری: " . $e->getMessage();
            }
        }
    }
}

// حذف مشتری (فقط برای ادمین)
if (isset($_GET['delete_id']) && $_SESSION['role'] == 'ادمین') {
    $delete_id = $_GET['delete_id'];
    
    // بررسی وجود مشتری قبل از حذف
    $stmt = $pdo->prepare("SELECT id FROM customers WHERE id = ?");
    $stmt->execute([$delete_id]);
    $customer = $stmt->fetch();
    
    if ($customer) {
        try {
            $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
            $stmt->execute([$delete_id]);
            $success = "مشتری با موفقیت حذف شد!";
            header("Location: customers.php?success=" . urlencode($success));
            exit();
        } catch (PDOException $e) {
            $error = "خطا در حذف مشتری: " . $e->getMessage();
        }
    } else {
        $error = "مشتری مورد نظر یافت نشد!";
    }
}

// دریافت کلیه مشتریان با امکان جستجو
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

if (!empty($search)) {
    $stmt = $pdo->prepare("SELECT * FROM customers 
                          WHERE full_name LIKE ? OR phone LIKE ? OR company LIKE ? 
                          ORDER BY id DESC 
                          LIMIT :limit OFFSET :offset");
    $search_term = "%$search%";
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute([$search_term, $search_term, $search_term]);
    
    // تعداد کل نتایج برای pagination
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM customers 
                                WHERE full_name LIKE ? OR phone LIKE ? OR company LIKE ?");
    $count_stmt->execute([$search_term, $search_term, $search_term]);
    $total_customers = $count_stmt->fetchColumn();
} else {
    $stmt = $pdo->prepare("SELECT * FROM customers ORDER BY id DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $total_customers = $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
}

$customers = $stmt->fetchAll();
$total_pages = ceil($total_customers / $per_page);

// نمایش پیام‌ها
if (isset($_GET['success'])) {
    $success = urldecode($_GET['success']);
}
if (isset($_GET['error'])) {
    $error = urldecode($_GET['error']);
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت مشتریان - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .table-hover tbody tr:hover {
            background-color: rgba(52, 152, 219, 0.1);
        }
        .badge-success {
            background-color: #28a745;
        }
        .badge-warning {
            background-color: #ffc107;
        }
        .search-box {
            max-width: 300px;
        }
    </style>
</head>
<body>
    <!-- افزودن نوار ناوبری -->
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <h2 class="text-center mb-4">مدیریت مشتریان</h2>

        <!-- نمایش پیام‌ها -->
        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- جستجو و فیلتر -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>جستجو و فیلتر</span>
                <span class="badge bg-info">تعداد کل: <?php echo $total_customers; ?></span>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-8">
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" placeholder="جستجو بر اساس نام، تلفن یا شرکت" value="<?php echo htmlspecialchars($search); ?>">
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-search"></i> جستجو
                            </button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <?php if (!empty($search)): ?>
                            <a href="customers.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i> پاک کردن فیلتر
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- فرم افزودن مشتری -->
        <div class="card mb-4">
            <div class="card-header">افزودن مشتری جدید</div>
            <div class="card-body">
                <form method="POST" id="customerForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="full_name" class="form-label">نام کامل *</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" required 
                                       value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">تلفن *</label>
                                <input type="text" class="form-control" id="phone" name="phone" required 
                                       value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                                <small class="form-text text-muted">فرمت: 09123456789</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="company" class="form-label">شرکت</label>
                                <input type="text" class="form-control" id="company" name="company" 
                                       value="<?php echo isset($_POST['company']) ? htmlspecialchars($_POST['company']) : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="address" class="form-label">آدرس</label>
                                <textarea class="form-control" id="address" name="address" rows="1"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" name="add_customer" class="btn btn-primary">
                        <i class="fas fa-plus"></i> افزودن مشتری
                    </button>
                </form>
            </div>
        </div>

        <!-- جدول نمایش مشتریان -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>لیست مشتریان</span>
                <div>
                    <span class="badge bg-secondary">صفحه <?php echo $page; ?> از <?php echo $total_pages; ?></span>
                </div>
            </div>
            <div class="card-body">
                <?php if (count($customers) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>نام کامل</th>
                                    <th>تلفن</th>
                                    <th>شرکت</th>
                                    <th>آدرس</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($customers as $customer): ?>
                                <tr>
                                    <td><?php echo $customer['id']; ?></td>
                                    <td><?php echo htmlspecialchars($customer['full_name']); ?></td>
                                    <td>
                                        <span class="badge badge-success"><?php echo htmlspecialchars($customer['phone']); ?></span>
                                    </td>
                                    <td>
                                        <?php if (!empty($customer['company'])): ?>
                                            <span class="badge badge-warning"><?php echo htmlspecialchars($customer['company']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">ندارد</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($customer['address'])): ?>
                                            <span title="<?php echo htmlspecialchars($customer['address']); ?>">
                                                <?php echo strlen($customer['address']) > 30 ? substr($customer['address'], 0, 30) . '...' : $customer['address']; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">ندارد</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="edit_customer.php?id=<?php echo $customer['id']; ?>" class="btn btn-warning" title="ویرایش">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($_SESSION['role'] == 'ادمین'): ?>
                                            <a href="customers.php?delete_id=<?php echo $customer['id']; ?>" 
                                               class="btn btn-danger" 
                                               title="حذف"
                                               onclick="return confirm('آیا از حذف مشتری \"<?php echo addslashes($customer['full_name']); ?>\" مطمئن هستید؟')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <p class="text-muted">هیچ مشتری یافت نشد.</p>
                        <?php if (!empty($search)): ?>
                            <a href="customers.php" class="btn btn-primary">مشاهده همه مشتریان</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // اعتبارسنجی فرم سمت کلاینت
        document.getElementById('customerForm').addEventListener('submit', function(e) {
            const phone = document.getElementById('phone').value;
            const phoneRegex = /^[0-9]{10,11}$/;
            
            if (!phoneRegex.test(phone)) {
                e.preventDefault();
                alert('شماره تلفن باید بین ۱۰ تا ۱۱ رقم و فقط شامل عدد باشد.');
                document.getElementById('phone').focus();
            }
        });
        
        // نمایش خودکار پیام‌ها
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000);
        });
    </script>
</body>
</html>