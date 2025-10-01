<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

$is_admin = ($_SESSION['role'] === 'ادمین');

// دریافت فرم‌ها
$search = $_GET['search'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = 12;
$offset = ($page - 1) * $limit;

$where_clause = "WHERE is_active = 1";
$params = [];

if (!empty($search)) {
    $where_clause .= " AND (title LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$count_sql = "SELECT COUNT(*) as total FROM education_forms $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_forms = $count_stmt->fetch()['total'];
$total_pages = ceil($total_forms / $limit);

$sql = "SELECT ef.*, u.username as uploaded_by_name 
        FROM education_forms ef 
        LEFT JOIN users u ON ef.uploaded_by = u.id 
        $where_clause 
        ORDER BY ef.created_at DESC 
        LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$forms = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فرم‌های آموزشی - سامانه مدیریت اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .forms-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 0;
            margin-bottom: 40px;
        }
        
        .form-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            overflow: hidden;
            height: 100%;
        }
        
        .form-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .form-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.8rem;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
        }
        
        .form-meta {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .search-box {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .btn-download {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 25px;
            padding: 10px 25px;
            color: white;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .btn-download:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .pagination-custom .page-link {
            border-radius: 10px;
            margin: 0 5px;
            border: none;
            color: #667eea;
            font-weight: bold;
        }
        
        .pagination-custom .page-item.active .page-link {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 5rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="forms-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="display-5 fw-bold mb-3">
                        <i class="fas fa-file-pdf me-3"></i>
                        فرم‌های آموزشی
                    </h1>
                    <p class="lead mb-0">
                        دسترسی به تمام فرم‌های آموزشی و اسناد شرکت اعلا نیرو
                    </p>
                </div>
                <div class="col-lg-4 text-center">
                    <i class="fas fa-file-contract" style="font-size: 6rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- جستجو -->
        <div class="search-box">
            <form method="GET" class="row g-3">
                <div class="col-md-8">
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" class="form-control" name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="جستجو در فرم‌ها...">
                    </div>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-2"></i>
                        جستجو
                    </button>
                </div>
            </form>
        </div>

        <!-- آمار -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    تعداد کل فرم‌ها: <strong><?php echo $total_forms; ?></strong>
                    <?php if (!empty($search)): ?>
                    - نتایج جستجو برای: <strong>"<?php echo htmlspecialchars($search); ?>"</strong>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- لیست فرم‌ها -->
        <?php if (empty($forms)): ?>
        <div class="empty-state">
            <i class="fas fa-file-pdf"></i>
            <h4>هیچ فرمی یافت نشد</h4>
            <p>
                <?php if (!empty($search)): ?>
                نتیجه‌ای برای جستجوی شما یافت نشد. لطفاً کلمات کلیدی دیگری امتحان کنید.
                <?php else: ?>
                هنوز فرم آموزشی آپلود نشده است.
                <?php endif; ?>
            </p>
        </div>
        <?php else: ?>
        <div class="row">
            <?php foreach ($forms as $form): ?>
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="form-card">
                    <div class="card-body text-center p-4">
                        <div class="form-icon">
                            <i class="fas fa-file-pdf"></i>
                        </div>
                        
                        <h5 class="card-title mb-3"><?php echo htmlspecialchars($form['title']); ?></h5>
                        
                        <?php if (!empty($form['description'])): ?>
                        <p class="card-text text-muted mb-3">
                            <?php echo htmlspecialchars($form['description']); ?>
                        </p>
                        <?php endif; ?>
                        
                        <div class="form-meta mb-3">
                            <div class="mb-2">
                                <i class="fas fa-calendar me-2"></i>
                                <?php echo date('Y/m/d', strtotime($form['created_at'])); ?>
                            </div>
                            <div class="mb-2">
                                <i class="fas fa-file me-2"></i>
                                <?php echo number_format($form['file_size'] / 1024, 1); ?> KB
                            </div>
                            <div class="mb-2">
                                <i class="fas fa-download me-2"></i>
                                <?php echo $form['download_count']; ?> دانلود
                            </div>
                            <div>
                                <i class="fas fa-user me-2"></i>
                                <?php echo htmlspecialchars($form['uploaded_by_name']); ?>
                            </div>
                        </div>
                        
                        <a href="download_form.php?id=<?php echo $form['id']; ?>" 
                           class="btn btn-download w-100">
                            <i class="fas fa-download me-2"></i>
                            دانلود فرم
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- صفحه‌بندی -->
        <?php if ($total_pages > 1): ?>
        <nav aria-label="صفحه‌بندی فرم‌ها" class="mt-5">
            <ul class="pagination pagination-custom justify-content-center">
                <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>