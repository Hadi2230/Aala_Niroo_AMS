<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// بررسی CSRF token
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_customer'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "خطای امنیتی: توکن CSRF نامعتبر است!";
        header('Location: customers.php');
        exit();
    }
}

// دریافت اطلاعات مشتری
$customer_id = $_GET['id'] ?? null;
if (!$customer_id) {
    $_SESSION['error'] = "شناسه مشتری مشخص نشده است!";
    header('Location: customers.php');
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch();

if (!$customer) {
    $_SESSION['error'] = "مشتری مورد نظر یافت نشد!";
    header('Location: customers.php');
    exit();
}

// ویرایش مشتری
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_customer'])) {
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $company = trim($_POST['company']);
    $address = trim($_POST['address']);
    
    // اعتبارسنجی داده‌ها
    $errors = [];
    
    if (empty($full_name)) {
        $errors[] = "نام کامل اجباری است!";
    }
    
    if (empty($phone)) {
        $errors[] = "شماره تلفن اجباری است!";
    } elseif (!preg_match('/^[0-9]{10,11}$/', $phone)) {
        $errors[] = "شماره تلفن معتبر نیست!";
    } else {
        // بررسی تکراری نبودن شماره تلفن (به جز برای همین مشتری)
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE phone = ? AND id != ?");
        $stmt->execute([$phone, $customer_id]);
        if ($stmt->fetch()) {
            $errors[] = "شماره تلفن تکراری است!";
        }
    }
    
    // اگر خطایی وجود ندارد، عملیات update را انجام دهید
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("UPDATE customers SET full_name = ?, phone = ?, company = ?, address = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$full_name, $phone, $company, $address, $customer_id]);
            
            $_SESSION['success'] = "مشتری با موفقیت ویرایش شد!";
            header('Location: customers.php');
            exit();
        } catch (PDOException $e) {
            $errors[] = "خطا در ویرایش مشتری: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ویرایش مشتری - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .card {
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        .card-header {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            border-radius: 10px 10px 0 0 !important;
        }
        .btn-primary {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #2980b9 0%, #3498db 100%);
        }
    </style>
</head>
<body>
    <!-- افزودن نوار ناوبری -->
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <h2 class="text-center mb-4">ویرایش مشتری</h2>

        <!-- نمایش خطاها -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <h5><i class="fas fa-exclamation-triangle"></i> خطا!</h5>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-user-edit"></i> ویرایش اطلاعات مشتری</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="editCustomerForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="full_name" class="form-label">نام کامل *</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" 
                                       value="<?php echo htmlspecialchars($customer['full_name']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">تلفن *</label>
                                <input type="text" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($customer['phone']); ?>" required
                                       pattern="[0-9]{10,11}" title="شماره تلفن باید ۱۰ یا ۱۱ رقم باشد">
                                <small class="form-text text-muted">فرمت: ۰۹۱۲۳۴۵۶۷۸۹</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="company" class="form-label">شرکت</label>
                                <input type="text" class="form-control" id="company" name="company" 
                                       value="<?php echo htmlspecialchars($customer['company']); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label">آدرس</label>
                        <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($customer['address']); ?></textarea>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" name="edit_customer" class="btn btn-primary">
                            <i class="fas fa-save"></i> ذخیره تغییرات
                        </button>
                        <a href="customers.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> انصراف
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // اعتبارسنجی سمت کلاینت
        document.getElementById('editCustomerForm').addEventListener('submit', function(e) {
            const phone = document.getElementById('phone').value;
            const phoneRegex = /^[0-9]{10,11}$/;
            
            if (!phoneRegex.test(phone)) {
                e.preventDefault();
                alert('شماره تلفن باید بین ۱۰ تا ۱۱ رقم و فقط شامل عدد باشد.');
                document.getElementById('phone').focus();
            }
        });
        
        // نمایش خودکار پیام‌های session
        <?php if (isset($_SESSION['error'])): ?>
            document.addEventListener('DOMContentLoaded', function() {
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-danger alert-dismissible fade show';
                alertDiv.innerHTML = `
                    <h5><i class="fas fa-exclamation-triangle"></i> خطا!</h5>
                    <p><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></p>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                document.querySelector('.container').prepend(alertDiv);
            });
        <?php endif; ?>
    </script>
</body>
</html>