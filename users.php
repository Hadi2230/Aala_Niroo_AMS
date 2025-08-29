<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'ادمین') {
    header('Location: login.php');
    exit();
}

include 'config.php';

// افزودن کاربر جدید
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    $email = trim($_POST['email']);
    $full_name = trim($_POST['full_name']);
    
    // اعتبارسنجی
    $errors = [];
    
    if (empty($username) || empty($password) || empty($role)) {
        $errors[] = "فیلدهای ضروری را پر کنید.";
    }
    
    if (strlen($password) < 6) {
        $errors[] = "رمز عبور باید حداقل ۶ کاراکتر باشد.";
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "فرمت ایمیل نامعتبر است.";
    }
    
    // بررسی تکراری نبودن username
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        $errors[] = "نام کاربری already exists.";
    }
    
    if (empty($errors)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, email, full_name) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$username, $password_hash, $role, $email, $full_name])) {
            $_SESSION['success'] = "کاربر با موفقیت افزوده شد!";
            header('Location: users.php');
            exit();
        } else {
            $errors[] = "خطا در افزودن کاربر.";
        }
    }
}

// حذف کاربر
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    
    // جلوگیری از حذف خود کاربر
    if ($delete_id != $_SESSION['user_id']) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt->execute([$delete_id])) {
            $_SESSION['success'] = "کاربر با موفقیت حذف شد!";
        } else {
            $_SESSION['error'] = "خطا در حذف کاربر.";
        }
    } else {
        $_SESSION['error'] = "شما نمی‌توانید حساب خودتان را حذف کنید.";
    }
    
    header('Location: users.php');
    exit();
}

// ویرایش کاربر
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_user'])) {
    $user_id = $_POST['user_id'];
    $username = trim($_POST['username']);
    $role = $_POST['role'];
    $email = trim($_POST['email']);
    $full_name = trim($_POST['full_name']);
    $password = $_POST['password'];
    
    $errors = [];
    
    // بررسی تکراری نبودن username (به جز برای کاربر فعلی)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmt->execute([$username, $user_id]);
    if ($stmt->fetch()) {
        $errors[] = "نام کاربری already exists.";
    }
    
    if (empty($errors)) {
        if (!empty($password)) {
            // اگر رمز عبور جدید ارائه شده
            if (strlen($password) < 6) {
                $errors[] = "رمز عبور باید حداقل ۶ کاراکتر باشد.";
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, role = ?, email = ?, full_name = ? WHERE id = ?");
                $stmt->execute([$username, $password_hash, $role, $email, $full_name, $user_id]);
            }
        } else {
            // بدون تغییر رمز عبور
            $stmt = $pdo->prepare("UPDATE users SET username = ?, role = ?, email = ?, full_name = ? WHERE id = ?");
            $stmt->execute([$username, $role, $email, $full_name, $user_id]);
        }
        
        if (empty($errors)) {
            $_SESSION['success'] = "کاربر با موفقیت ویرایش شد!";
            header('Location: users.php');
            exit();
        }
    }
}

// دریافت کلیه کاربران
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

if (!empty($search)) {
    $stmt = $pdo->prepare("SELECT * FROM users 
                          WHERE username LIKE ? OR full_name LIKE ? OR email LIKE ? 
                          ORDER BY id DESC 
                          LIMIT :limit OFFSET :offset");
    $search_term = "%$search%";
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute([$search_term, $search_term, $search_term]);
    
    // تعداد کل نتایج
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM users 
                                WHERE username LIKE ? OR full_name LIKE ? OR email LIKE ?");
    $count_stmt->execute([$search_term, $search_term, $search_term]);
    $total_users = $count_stmt->fetchColumn();
} else {
    $stmt = $pdo->prepare("SELECT * FROM users ORDER BY id DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
}

$users = $stmt->fetchAll();
$total_pages = ceil($total_users / $per_page);

// نمایش پیام‌ها
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت کاربران - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .table-hover tbody tr:hover {
            background-color: rgba(52, 152, 219, 0.1);
        }
        .badge-admin {
            background-color: #dc3545;
        }
        .badge-user {
            background-color: #28a745;
        }
        .search-box {
            max-width: 300px;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <h2 class="text-center mb-4">مدیریت کاربران سیستم</h2>

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

        <!-- جستجو -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>جستجو و فیلتر</span>
                <span class="badge bg-info">تعداد کاربران: <?php echo $total_users; ?></span>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-8">
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" placeholder="جستجو بر اساس نام کاربری، نام کامل یا ایمیل" value="<?php echo htmlspecialchars($search); ?>">
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-search"></i> جستجو
                            </button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <?php if (!empty($search)): ?>
                            <a href="users.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i> پاک کردن فیلتر
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- فرم افزودن کاربر جدید -->
        <div class="card mb-4">
            <div class="card-header">افزودن کاربر جدید</div>
            <div class="card-body">
                <form method="POST" id="addUserForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="username" class="form-label">نام کاربری *</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password" class="form-label">رمز عبور *</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <small class="form-text text-muted">حداقل ۶ کاراکتر</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="full_name" class="form-label">نام کامل</label>
                                <input type="text" class="form-control" id="full_name" name="full_name">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">ایمیل</label>
                                <input type="email" class="form-control" id="email" name="email">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="role" class="form-label">نقش *</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="کاربر عادی">کاربر عادی</option>
                                    <option value="ادمین">ادمین</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" name="add_user" class="btn btn-primary">
                        <i class="fas fa-plus"></i> افزودن کاربر
                    </button>
                </form>
            </div>
        </div>

        <!-- جدول نمایش کاربران -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>لیست کاربران</span>
                <div>
                    <span class="badge bg-secondary">صفحه <?php echo $page; ?> از <?php echo $total_pages; ?></span>
                </div>
            </div>
            <div class="card-body">
                <?php if (count($users) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>نام کاربری</th>
                                    <th>نام کامل</th>
                                    <th>ایمیل</th>
                                    <th>نقش</th>
                                    <th>تاریخ ایجاد</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['full_name'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($user['email'] ?? '-'); ?></td>
                                    <td>
                                        <span class="badge <?php echo $user['role'] == 'ادمین' ? 'badge-admin' : 'badge-user'; ?>">
                                            <?php echo $user['role']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $user['created_at']; ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-warning" data-bs-toggle="modal" 
                                                    data-bs-target="#editUserModal" 
                                                    data-user-id="<?php echo $user['id']; ?>"
                                                    data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                    data-full-name="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>"
                                                    data-email="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                                                    data-role="<?php echo $user['role']; ?>"
                                                    title="ویرایش">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <a href="users.php?delete_id=<?php echo $user['id']; ?>" 
                                               class="btn btn-danger" 
                                               title="حذف"
                                               onclick="return confirm('آیا از حذف کاربر \"<?php echo addslashes($user['username']); ?>\" مطمئن هستید؟')">
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
                        <p class="text-muted">هیچ کاربری یافت نشد.</p>
                        <?php if (!empty($search)): ?>
                            <a href="users.php" class="btn btn-primary">مشاهده همه کاربران</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal ویرایش کاربر -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">ویرایش کاربر</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="editUserForm">
                    <div class="modal-body">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <input type="hidden" name="edit_user" value="1">
                        
                        <div class="mb-3">
                            <label for="edit_username" class="form-label">نام کاربری *</label>
                            <input type="text" class="form-control" id="edit_username" name="username" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_full_name" class="form-label">نام کامل</label>
                            <input type="text" class="form-control" id="edit_full_name" name="full_name">
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">ایمیل</label>
                            <input type="email" class="form-control" id="edit_email" name="email">
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_role" class="form-label">نقش *</label>
                            <select class="form-select" id="edit_role" name="role" required>
                                <option value="کاربر عادی">کاربر عادی</option>
                                <option value="ادمین">ادمین</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_password" class="form-label">رمز عبور جدید (اختیاری)</label>
                            <input type="password" class="form-control" id="edit_password" name="password">
                            <small class="form-text text-muted">در صورت تغییر رمز عبور، حداقل ۶ کاراکتر</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" class="btn btn-primary">ذخیره تغییرات</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // مدیریت modal ویرایش
        const editUserModal = document.getElementById('editUserModal');
        editUserModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const userId = button.getAttribute('data-user-id');
            const username = button.getAttribute('data-username');
            const fullName = button.getAttribute('data-full-name');
            const email = button.getAttribute('data-email');
            const role = button.getAttribute('data-role');
            
            document.getElementById('edit_user_id').value = userId;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_full_name').value = fullName;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_role').value = role;
        });

        // اعتبارسنجی فرم‌ها
        document.getElementById('addUserForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            if (password.length < 6) {
                e.preventDefault();
                alert('رمز عبور باید حداقل ۶ کاراکتر باشد.');
                document.getElementById('password').focus();
            }
        });

        document.getElementById('editUserForm').addEventListener('submit', function(e) {
            const password = document.getElementById('edit_password').value;
            if (password && password.length < 6) {
                e.preventDefault();
                alert('رمز عبور باید حداقل ۶ کاراکتر باشد.');
                document.getElementById('edit_password').focus();
            }
        });
    </script>
</body>
</html>