<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// دریافت آمار محتوای آموزشی
$stats = [
    'forms' => $pdo->query("SELECT COUNT(*) as count FROM training_forms WHERE is_active = 1")->fetch()['count'],
    'gallery' => $pdo->query("SELECT COUNT(*) as count FROM training_gallery WHERE is_active = 1")->fetch()['count'],
    'videos' => $pdo->query("SELECT COUNT(*) as count FROM training_videos WHERE is_active = 1")->fetch()['count'],
    'articles' => $pdo->query("SELECT COUNT(*) as count FROM training_articles WHERE is_active = 1")->fetch()['count']
];

// دریافت آخرین محتوای اضافه شده
$latest_content = [];

// آخرین فرم‌ها
$latest_forms = $pdo->query("
    SELECT f.*, c.name as category_name, u.full_name as uploader_name
    FROM training_forms f
    LEFT JOIN training_categories c ON f.category_id = c.id
    LEFT JOIN users u ON f.uploaded_by = u.id
    WHERE f.is_active = 1
    ORDER BY f.created_at DESC
    LIMIT 3
")->fetchAll();

// آخرین ویدیوها
$latest_videos = $pdo->query("
    SELECT v.*, c.name as category_name
    FROM training_videos v
    LEFT JOIN training_categories c ON v.category_id = c.id
    WHERE v.is_active = 1
    ORDER BY v.created_at DESC
    LIMIT 3
")->fetchAll();

// آخرین مقالات
$latest_articles = $pdo->query("
    SELECT a.*, c.name as category_name
    FROM training_articles a
    LEFT JOIN training_categories c ON a.category_id = c.id
    WHERE a.is_active = 1
    ORDER BY a.created_at DESC
    LIMIT 3
")->fetchAll();

$is_admin = $_SESSION['role'] === 'ادمین';
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>آموزش - سامانه مدیریت اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);
            --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --info-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --dark-gradient: linear-gradient(135deg, #30cfd0 0%, #330867 100%);
        }

        .hero-section {
            background: var(--primary-gradient);
            color: white;
            padding: 60px 0;
            border-radius: 0 0 50px 50px;
            margin-bottom: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .hero-section h1 {
            font-size: 3rem;
            font-weight: bold;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }

        .module-card {
            border: none;
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.4s ease;
            height: 100%;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .module-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }

        .module-card .card-header {
            padding: 30px;
            color: white;
            border: none;
        }

        .module-card.forms .card-header { background: var(--success-gradient); }
        .module-card.gallery .card-header { background: var(--warning-gradient); }
        .module-card.videos .card-header { background: var(--info-gradient); }
        .module-card.articles .card-header { background: var(--dark-gradient); }

        .module-card .card-header i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.9;
        }

        .module-card .card-body {
            padding: 30px;
        }

        .stat-badge {
            background: rgba(0,0,0,0.1);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 1.2rem;
            font-weight: bold;
        }

        .content-item {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 10px;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }

        .content-item:hover {
            background: #e9ecef;
            transform: translateX(-5px);
        }

        .btn-module {
            border-radius: 25px;
            padding: 10px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-module:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .recent-section {
            background: #f8f9fa;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
        }

        .recent-section h3 {
            color: #333;
            margin-bottom: 20px;
            font-weight: bold;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .floating-admin-btn {
            position: fixed;
            bottom: 30px;
            left: 30px;
            z-index: 1000;
        }

        .floating-admin-btn .btn {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            font-size: 1.5rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .pulse-animation {
            animation: pulse 2s infinite;
        }

        .progress-bar-animated {
            background: linear-gradient(45deg, rgba(255,255,255,.15) 25%, transparent 25%, transparent 50%, rgba(255,255,255,.15) 50%, rgba(255,255,255,.15) 75%, transparent 75%, transparent);
            background-size: 1rem 1rem;
            animation: progress-bar-stripes 1s linear infinite;
        }

        @keyframes progress-bar-stripes {
            from { background-position: 1rem 0; }
            to { background-position: 0 0; }
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
                        <i class="fas fa-graduation-cap"></i>
                        مرکز آموزش اعلا نیرو
                    </h1>
                    <p class="lead">دسترسی به منابع آموزشی، فرم‌ها، ویدیوها و مقالات تخصصی</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="d-flex justify-content-end gap-3">
                        <div class="text-center">
                            <div class="stat-badge"><?php echo array_sum($stats); ?></div>
                            <small>کل محتوا</small>
                        </div>
                        <div class="text-center">
                            <div class="stat-badge"><?php echo $stats['videos']; ?></div>
                            <small>ویدیو</small>
                        </div>
                        <div class="text-center">
                            <div class="stat-badge"><?php echo $stats['articles']; ?></div>
                            <small>مقاله</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- ماژول‌های اصلی -->
        <div class="row mb-5">
            <!-- فرم‌های شرکت -->
            <div class="col-md-6 col-lg-3 mb-4">
                <div class="module-card forms">
                    <div class="card-header text-center">
                        <i class="fas fa-file-alt"></i>
                        <h4>فرم‌های شرکت</h4>
                        <div class="stat-badge mt-2"><?php echo $stats['forms']; ?> فرم</div>
                    </div>
                    <div class="card-body text-center">
                        <p class="text-muted mb-3">دانلود فرم‌های رسمی و قراردادهای شرکت</p>
                        <a href="training_forms.php" class="btn btn-success btn-module">
                            <i class="fas fa-download"></i> مشاهده فرم‌ها
                        </a>
                        <?php if ($is_admin): ?>
                            <a href="training_forms_admin.php" class="btn btn-outline-success btn-sm mt-2 w-100">
                                <i class="fas fa-upload"></i> مدیریت
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- گالری تصاویر -->
            <div class="col-md-6 col-lg-3 mb-4">
                <div class="module-card gallery">
                    <div class="card-header text-center">
                        <i class="fas fa-images"></i>
                        <h4>گالری تصاویر</h4>
                        <div class="stat-badge mt-2"><?php echo $stats['gallery']; ?> تصویر</div>
                    </div>
                    <div class="card-body text-center">
                        <p class="text-muted mb-3">مشاهده تصاویر آموزشی و راهنما</p>
                        <a href="training_gallery.php" class="btn btn-warning btn-module text-white">
                            <i class="fas fa-eye"></i> مشاهده گالری
                        </a>
                        <?php if ($is_admin): ?>
                            <a href="training_gallery_admin.php" class="btn btn-outline-warning btn-sm mt-2 w-100">
                                <i class="fas fa-upload"></i> مدیریت
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ویدیوهای آموزشی -->
            <div class="col-md-6 col-lg-3 mb-4">
                <div class="module-card videos">
                    <div class="card-header text-center">
                        <i class="fas fa-video"></i>
                        <h4>ویدیوهای آموزشی</h4>
                        <div class="stat-badge mt-2"><?php echo $stats['videos']; ?> ویدیو</div>
                    </div>
                    <div class="card-body text-center">
                        <p class="text-muted mb-3">تماشای ویدیوهای آموزشی و دستورالعمل‌ها</p>
                        <a href="training_videos.php" class="btn btn-info btn-module text-white">
                            <i class="fas fa-play"></i> مشاهده ویدیوها
                        </a>
                        <?php if ($is_admin): ?>
                            <a href="training_videos_admin.php" class="btn btn-outline-info btn-sm mt-2 w-100">
                                <i class="fas fa-upload"></i> مدیریت
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- مقالات و نشریات -->
            <div class="col-md-6 col-lg-3 mb-4">
                <div class="module-card articles">
                    <div class="card-header text-center">
                        <i class="fas fa-newspaper"></i>
                        <h4>مقالات و نشریات</h4>
                        <div class="stat-badge mt-2"><?php echo $stats['articles']; ?> مقاله</div>
                    </div>
                    <div class="card-body text-center">
                        <p class="text-muted mb-3">مطالعه مقالات تخصصی و نشریات</p>
                        <a href="training_articles.php" class="btn btn-dark btn-module">
                            <i class="fas fa-book-open"></i> مشاهده مقالات
                        </a>
                        <?php if ($is_admin): ?>
                            <a href="training_articles_admin.php" class="btn btn-outline-dark btn-sm mt-2 w-100">
                                <i class="fas fa-edit"></i> مدیریت
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- آخرین محتوای اضافه شده -->
        <div class="row">
            <!-- آخرین فرم‌ها -->
            <div class="col-md-4 mb-4">
                <div class="recent-section">
                    <h3><i class="fas fa-file-alt text-success"></i> آخرین فرم‌ها</h3>
                    <?php if (count($latest_forms) > 0): ?>
                        <?php foreach ($latest_forms as $form): ?>
                            <div class="content-item">
                                <h6><?php echo htmlspecialchars($form['title']); ?></h6>
                                <small class="text-muted">
                                    <i class="fas fa-folder"></i> <?php echo $form['category_name'] ?? 'بدون دسته'; ?>
                                    | <i class="fas fa-calendar"></i> <?php echo jalaliDate($form['created_at']); ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                        <div class="text-center mt-3">
                            <a href="training_forms.php" class="btn btn-sm btn-outline-success">
                                مشاهده همه <i class="fas fa-arrow-left"></i>
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>هنوز فرمی اضافه نشده است</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- آخرین ویدیوها -->
            <div class="col-md-4 mb-4">
                <div class="recent-section">
                    <h3><i class="fas fa-video text-info"></i> آخرین ویدیوها</h3>
                    <?php if (count($latest_videos) > 0): ?>
                        <?php foreach ($latest_videos as $video): ?>
                            <div class="content-item">
                                <h6><?php echo htmlspecialchars($video['title']); ?></h6>
                                <small class="text-muted">
                                    <i class="fas fa-folder"></i> <?php echo $video['category_name'] ?? 'بدون دسته'; ?>
                                    | <i class="fas fa-eye"></i> <?php echo $video['view_count']; ?> بازدید
                                </small>
                            </div>
                        <?php endforeach; ?>
                        <div class="text-center mt-3">
                            <a href="training_videos.php" class="btn btn-sm btn-outline-info">
                                مشاهده همه <i class="fas fa-arrow-left"></i>
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-film"></i>
                            <p>هنوز ویدیویی اضافه نشده است</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- آخرین مقالات -->
            <div class="col-md-4 mb-4">
                <div class="recent-section">
                    <h3><i class="fas fa-newspaper text-dark"></i> آخرین مقالات</h3>
                    <?php if (count($latest_articles) > 0): ?>
                        <?php foreach ($latest_articles as $article): ?>
                            <div class="content-item">
                                <h6><?php echo htmlspecialchars($article['title']); ?></h6>
                                <small class="text-muted">
                                    <i class="fas fa-user"></i> <?php echo $article['author'] ?? 'ناشناس'; ?>
                                    | <i class="fas fa-eye"></i> <?php echo $article['view_count']; ?> بازدید
                                </small>
                            </div>
                        <?php endforeach; ?>
                        <div class="text-center mt-3">
                            <a href="training_articles.php" class="btn btn-sm btn-outline-dark">
                                مشاهده همه <i class="fas fa-arrow-left"></i>
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-file-alt"></i>
                            <p>هنوز مقاله‌ای منتشر نشده است</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($is_admin): ?>
    <!-- دکمه شناور مدیریت -->
    <div class="floating-admin-btn">
        <div class="dropdown dropup">
            <button class="btn btn-primary pulse-animation" data-bs-toggle="dropdown">
                <i class="fas fa-cog"></i>
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="training_forms_admin.php">
                    <i class="fas fa-file-alt"></i> مدیریت فرم‌ها
                </a></li>
                <li><a class="dropdown-item" href="training_gallery_admin.php">
                    <i class="fas fa-images"></i> مدیریت گالری
                </a></li>
                <li><a class="dropdown-item" href="training_videos_admin.php">
                    <i class="fas fa-video"></i> مدیریت ویدیوها
                </a></li>
                <li><a class="dropdown-item" href="training_articles_admin.php">
                    <i class="fas fa-newspaper"></i> مدیریت مقالات
                </a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="training_categories.php">
                    <i class="fas fa-folder"></i> مدیریت دسته‌بندی‌ها
                </a></li>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>