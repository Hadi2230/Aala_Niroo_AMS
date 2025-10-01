<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// پردازش دانلود
if (isset($_GET['download']) && is_numeric($_GET['download'])) {
    $form_id = (int)$_GET['download'];
    
    // افزایش شمارنده دانلود
    $stmt = $pdo->prepare("UPDATE training_forms SET download_count = download_count + 1 WHERE id = ? AND is_active = 1");
    $stmt->execute([$form_id]);
    
    // دریافت اطلاعات فایل
    $stmt = $pdo->prepare("SELECT file_path, file_name FROM training_forms WHERE id = ? AND is_active = 1");
    $stmt->execute([$form_id]);
    $form = $stmt->fetch();
    
    if ($form) {
        $file_path = __DIR__ . '/' . $form['file_path'];
        if (file_exists($file_path)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $form['file_name'] . '"');
            header('Content-Length: ' . filesize($file_path));
            readfile($file_path);
            exit();
        }
    }
}

// فیلتر دسته‌بندی
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : null;
$search_query = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

// ساخت کوئری
$query = "
    SELECT f.*, c.name as category_name, c.icon as category_icon, u.full_name as uploader_name
    FROM training_forms f
    LEFT JOIN training_categories c ON f.category_id = c.id
    LEFT JOIN users u ON f.uploaded_by = u.id
    WHERE f.is_active = 1
";

$params = [];

if ($category_filter) {
    $query .= " AND f.category_id = ?";
    $params[] = $category_filter;
}

if ($search_query) {
    $query .= " AND (f.title LIKE ? OR f.description LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

$query .= " ORDER BY f.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$forms = $stmt->fetchAll();

// دریافت دسته‌بندی‌ها برای فیلتر
$categories = $pdo->query("
    SELECT c.*, COUNT(f.id) as form_count 
    FROM training_categories c
    LEFT JOIN training_forms f ON c.id = f.category_id AND f.is_active = 1
    WHERE c.is_active = 1
    GROUP BY c.id
    ORDER BY c.sort_order
")->fetchAll();
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فرم‌های آموزشی - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);
            color: white;
            padding: 40px 0;
            border-radius: 0 0 30px 30px;
            margin-bottom: 30px;
        }
        
        .category-filter {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 20px;
        }
        
        .category-item {
            padding: 10px 15px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 5px;
        }
        
        .category-item:hover {
            background: #f8f9fa;
            transform: translateX(-5px);
        }
        
        .category-item.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .form-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .form-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .form-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 15px;
        }
        
        .form-icon.pdf { background: #fee; color: #dc3545; }
        .form-icon.word { background: #e3f2fd; color: #2196f3; }
        .form-icon.excel { background: #e8f5e9; color: #4caf50; }
        .form-icon.powerpoint { background: #fff3e0; color: #ff9800; }
        
        .download-btn {
            border-radius: 25px;
            padding: 8px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .download-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
        }
        
        .search-box {
            background: white;
            border-radius: 50px;
            padding: 10px 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .search-box input {
            border: none;
            outline: none;
            background: transparent;
            width: 100%;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-state i {
            font-size: 5rem;
            color: #dee2e6;
            margin-bottom: 20px;
        }
        
        .stats-badge {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
        }
        
        .form-meta {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 10px;
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .form-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <!-- Hero Section -->
    <div class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1>
                        <i class="fas fa-file-alt"></i>
                        فرم‌های شرکت
                    </h1>
                    <p class="lead mb-0">دانلود فرم‌های رسمی، قراردادها و مستندات شرکت</p>
                </div>
                <div class="col-md-4">
                    <div class="search-box">
                        <form method="GET" class="d-flex align-items-center">
                            <i class="fas fa-search text-muted me-2"></i>
                            <input type="text" name="search" placeholder="جستجوی فرم..." 
                                   value="<?php echo htmlspecialchars($search_query); ?>">
                            <?php if ($category_filter): ?>
                                <input type="hidden" name="category" value="<?php echo $category_filter; ?>">
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="row">
            <!-- سایدبار دسته‌بندی‌ها -->
            <div class="col-md-3">
                <div class="category-filter">
                    <h5 class="mb-3">
                        <i class="fas fa-filter"></i>
                        دسته‌بندی‌ها
                    </h5>
                    
                    <a href="?" class="text-decoration-none">
                        <div class="category-item <?php echo !$category_filter ? 'active' : ''; ?>">
                            <i class="fas fa-th-large"></i>
                            همه فرم‌ها
                            <span class="stats-badge float-end"><?php echo count($forms); ?></span>
                        </div>
                    </a>
                    
                    <?php foreach ($categories as $category): ?>
                        <a href="?category=<?php echo $category['id']; ?>" class="text-decoration-none">
                            <div class="category-item <?php echo $category_filter == $category['id'] ? 'active' : ''; ?>">
                                <i class="fas <?php echo $category['icon']; ?>"></i>
                                <?php echo htmlspecialchars($category['name']); ?>
                                <span class="stats-badge float-end"><?php echo $category['form_count']; ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- آمار -->
                <div class="mt-4 p-3 bg-light rounded">
                    <h6 class="mb-3">آمار کلی</h6>
                    <div class="mb-2">
                        <small class="text-muted">تعداد کل فرم‌ها:</small>
                        <strong class="float-end"><?php echo count($forms); ?></strong>
                    </div>
                    <div class="mb-2">
                        <small class="text-muted">کل دانلودها:</small>
                        <strong class="float-end"><?php echo array_sum(array_column($forms, 'download_count')); ?></strong>
                    </div>
                    <div>
                        <small class="text-muted">حجم کل:</small>
                        <strong class="float-end">
                            <?php echo round(array_sum(array_column($forms, 'file_size')) / (1024 * 1024), 2); ?> MB
                        </strong>
                    </div>
                </div>
            </div>

            <!-- لیست فرم‌ها -->
            <div class="col-md-9">
                <?php if (count($forms) > 0): ?>
                    <div class="row">
                        <?php foreach ($forms as $form): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="form-card">
                                    <div class="form-icon <?php 
                                        echo match($form['file_type']) {
                                            'pdf' => 'pdf',
                                            'doc', 'docx' => 'word',
                                            'xls', 'xlsx' => 'excel',
                                            'ppt', 'pptx' => 'powerpoint',
                                            default => ''
                                        };
                                    ?>">
                                        <i class="fas fa-file-<?php 
                                            echo match($form['file_type']) {
                                                'pdf' => 'pdf',
                                                'doc', 'docx' => 'word',
                                                'xls', 'xlsx' => 'excel',
                                                'ppt', 'pptx' => 'powerpoint',
                                                default => 'alt'
                                            };
                                        ?>"></i>
                                    </div>
                                    
                                    <h5 class="mb-2"><?php echo htmlspecialchars($form['title']); ?></h5>
                                    
                                    <?php if ($form['description']): ?>
                                        <p class="text-muted small mb-3">
                                            <?php echo htmlspecialchars(mb_substr($form['description'], 0, 100)); ?>
                                            <?php echo mb_strlen($form['description']) > 100 ? '...' : ''; ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <div class="form-meta mt-auto">
                                        <span>
                                            <i class="fas fa-file"></i>
                                            <?php echo strtoupper($form['file_type']); ?>
                                        </span>
                                        <span>
                                            <i class="fas fa-hdd"></i>
                                            <?php echo round($form['file_size'] / 1024, 2); ?> KB
                                        </span>
                                        <span>
                                            <i class="fas fa-download"></i>
                                            <?php echo $form['download_count']; ?>
                                        </span>
                                    </div>
                                    
                                    <?php if ($form['category_name']): ?>
                                        <div class="mt-3">
                                            <span class="badge bg-secondary">
                                                <i class="fas <?php echo $form['category_icon']; ?>"></i>
                                                <?php echo $form['category_name']; ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="mt-3">
                                        <a href="?download=<?php echo $form['id']; ?>" class="btn btn-success download-btn w-100">
                                            <i class="fas fa-download"></i>
                                            دانلود فرم
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-folder-open"></i>
                        <h4>فرمی یافت نشد</h4>
                        <p class="text-muted">
                            <?php if ($search_query): ?>
                                هیچ فرمی با عبارت "<?php echo htmlspecialchars($search_query); ?>" یافت نشد.
                            <?php elseif ($category_filter): ?>
                                در این دسته‌بندی فرمی وجود ندارد.
                            <?php else: ?>
                                هنوز فرمی آپلود نشده است.
                            <?php endif; ?>
                        </p>
                        <?php if ($search_query || $category_filter): ?>
                            <a href="training_forms.php" class="btn btn-primary mt-3">
                                <i class="fas fa-arrow-right"></i>
                                مشاهده همه فرم‌ها
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>