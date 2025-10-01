<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// مشاهده مقاله خاص
$current_article = null;
if (isset($_GET['article'])) {
    $slug = sanitizeInput($_GET['article']);
    
    // افزایش شمارنده بازدید
    $stmt = $pdo->prepare("UPDATE training_articles SET view_count = view_count + 1 WHERE slug = ? AND is_active = 1");
    $stmt->execute([$slug]);
    
    // دریافت مقاله
    $stmt = $pdo->prepare("
        SELECT a.*, c.name as category_name, u.full_name as publisher_name
        FROM training_articles a
        LEFT JOIN training_categories c ON a.category_id = c.id
        LEFT JOIN users u ON a.published_by = u.id
        WHERE a.slug = ? AND a.is_active = 1
    ");
    $stmt->execute([$slug]);
    $current_article = $stmt->fetch();
}

// پردازش دانلود PDF
if (isset($_GET['download']) && is_numeric($_GET['download'])) {
    $article_id = (int)$_GET['download'];
    
    // افزایش شمارنده دانلود
    $stmt = $pdo->prepare("UPDATE training_articles SET download_count = download_count + 1 WHERE id = ? AND is_active = 1");
    $stmt->execute([$article_id]);
    
    // دریافت فایل PDF
    $stmt = $pdo->prepare("SELECT pdf_file, title FROM training_articles WHERE id = ? AND is_active = 1");
    $stmt->execute([$article_id]);
    $article = $stmt->fetch();
    
    if ($article && $article['pdf_file']) {
        $file_path = __DIR__ . '/' . $article['pdf_file'];
        if (file_exists($file_path)) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $article['title'] . '.pdf"');
            header('Content-Length: ' . filesize($file_path));
            readfile($file_path);
            exit();
        }
    }
}

// فیلتر دسته‌بندی
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : null;
$search_query = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

// دریافت مقالات ویژه
$featured_articles = $pdo->query("
    SELECT a.*, c.name as category_name
    FROM training_articles a
    LEFT JOIN training_categories c ON a.category_id = c.id
    WHERE a.is_active = 1 AND a.is_featured = 1
    ORDER BY a.published_at DESC
    LIMIT 3
")->fetchAll();

// ساخت کوئری مقالات
$query = "
    SELECT a.*, c.name as category_name, c.icon as category_icon, u.full_name as publisher_name
    FROM training_articles a
    LEFT JOIN training_categories c ON a.category_id = c.id
    LEFT JOIN users u ON a.published_by = u.id
    WHERE a.is_active = 1
";

$params = [];

if ($category_filter) {
    $query .= " AND a.category_id = ?";
    $params[] = $category_filter;
}

if ($search_query) {
    $query .= " AND (a.title LIKE ? OR a.summary LIKE ? OR a.content LIKE ? OR a.tags LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

$query .= " ORDER BY a.published_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$articles = $stmt->fetchAll();

// دریافت دسته‌بندی‌ها برای فیلتر
$categories = $pdo->query("
    SELECT c.*, COUNT(a.id) as article_count 
    FROM training_categories c
    LEFT JOIN training_articles a ON c.id = a.category_id AND a.is_active = 1
    WHERE c.is_active = 1
    GROUP BY c.id
    ORDER BY c.sort_order
")->fetchAll();

// دریافت مقالات پربازدید
$popular_articles = $pdo->query("
    SELECT title, slug, view_count, reading_time
    FROM training_articles
    WHERE is_active = 1
    ORDER BY view_count DESC
    LIMIT 5
")->fetchAll();
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $current_article ? htmlspecialchars($current_article['title']) . ' - ' : ''; ?>مقالات و نشریات - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);
            color: white;
            padding: 40px 0;
            border-radius: 0 0 30px 30px;
            margin-bottom: 30px;
        }
        
        .article-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }
        
        .article-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
        }
        
        .article-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .article-image {
            height: 200px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: relative;
            overflow: hidden;
        }
        
        .article-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .article-image .placeholder-icon {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 3rem;
            color: rgba(255,255,255,0.5);
        }
        
        .article-content {
            padding: 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .article-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .article-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: #333;
            text-decoration: none;
        }
        
        .article-title:hover {
            color: #667eea;
        }
        
        .article-summary {
            color: #6c757d;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 15px;
            flex: 1;
        }
        
        .article-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid #dee2e6;
        }
        
        .featured-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: linear-gradient(135deg, #ffd700, #ff8c00);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            z-index: 1;
        }
        
        .sidebar {
            position: sticky;
            top: 20px;
        }
        
        .sidebar-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .category-list {
            list-style: none;
            padding: 0;
        }
        
        .category-list li {
            padding: 10px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .category-list li:hover {
            background: #f8f9fa;
            padding-right: 15px;
        }
        
        .category-list li.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
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
        
        .tag-cloud {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .tag {
            background: #e9ecef;
            color: #495057;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.85rem;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .tag:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }
        
        /* Article View Styles */
        .article-view {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .article-view h1 {
            font-size: 2rem;
            margin-bottom: 20px;
            color: #333;
        }
        
        .article-view .article-info {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #dee2e6;
            color: #6c757d;
        }
        
        .article-view .article-body {
            font-size: 1.1rem;
            line-height: 1.8;
            color: #333;
        }
        
        .article-view .article-body img {
            max-width: 100%;
            height: auto;
            border-radius: 10px;
            margin: 20px 0;
        }
        
        .article-view .article-body h2 {
            font-size: 1.5rem;
            margin-top: 30px;
            margin-bottom: 15px;
            color: #333;
        }
        
        .article-view .article-body h3 {
            font-size: 1.3rem;
            margin-top: 25px;
            margin-bottom: 15px;
            color: #333;
        }
        
        .article-view .article-body p {
            margin-bottom: 15px;
        }
        
        .article-view .article-body blockquote {
            border-right: 4px solid #667eea;
            padding-right: 20px;
            margin: 20px 0;
            font-style: italic;
            color: #6c757d;
        }
        
        .download-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
            text-align: center;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <?php if ($current_article): ?>
        <!-- نمایش مقاله -->
        <div class="container mt-4">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="training.php">آموزش</a></li>
                    <li class="breadcrumb-item"><a href="training_articles.php">مقالات</a></li>
                    <li class="breadcrumb-item active"><?php echo htmlspecialchars($current_article['title']); ?></li>
                </ol>
            </nav>

            <div class="row">
                <div class="col-lg-8">
                    <div class="article-view">
                        <?php if ($current_article['featured_image']): ?>
                            <img src="<?php echo $current_article['featured_image']; ?>" 
                                 alt="<?php echo htmlspecialchars($current_article['title']); ?>"
                                 class="w-100 mb-4" style="border-radius: 15px;">
                        <?php endif; ?>
                        
                        <h1><?php echo htmlspecialchars($current_article['title']); ?></h1>
                        
                        <div class="article-info">
                            <span><i class="fas fa-user"></i> <?php echo $current_article['author'] ?? 'ناشناس'; ?></span>
                            <span><i class="fas fa-calendar"></i> <?php echo jalaliDate($current_article['published_at']); ?></span>
                            <span><i class="fas fa-clock"></i> <?php echo $current_article['reading_time']; ?> دقیقه مطالعه</span>
                            <span><i class="fas fa-eye"></i> <?php echo $current_article['view_count']; ?> بازدید</span>
                            <?php if ($current_article['category_name']): ?>
                                <span><i class="fas fa-folder"></i> <?php echo $current_article['category_name']; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($current_article['summary']): ?>
                            <div class="alert alert-light">
                                <strong>خلاصه:</strong> <?php echo nl2br(htmlspecialchars($current_article['summary'])); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="article-body">
                            <?php echo $current_article['content']; ?>
                        </div>
                        
                        <?php if ($current_article['tags']): ?>
                            <div class="mt-4">
                                <strong>برچسب‌ها:</strong>
                                <div class="tag-cloud mt-2">
                                    <?php foreach (explode(',', $current_article['tags']) as $tag): ?>
                                        <a href="?search=<?php echo urlencode(trim($tag)); ?>" class="tag">
                                            <?php echo trim($tag); ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($current_article['pdf_file']): ?>
                            <div class="download-section">
                                <h5>دانلود نسخه PDF</h5>
                                <p>می‌توانید نسخه PDF این مقاله را دانلود کنید</p>
                                <a href="?download=<?php echo $current_article['id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-download"></i> دانلود PDF
                                    (<?php echo $current_article['download_count']; ?> دانلود)
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="sidebar">
                        <!-- مقالات پربازدید -->
                        <div class="sidebar-card">
                            <h5 class="mb-3">مقالات پربازدید</h5>
                            <?php foreach ($popular_articles as $article): ?>
                                <div class="mb-3">
                                    <a href="?article=<?php echo $article['slug']; ?>" class="text-decoration-none">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($article['title']); ?></h6>
                                    </a>
                                    <small class="text-muted">
                                        <i class="fas fa-eye"></i> <?php echo $article['view_count']; ?> |
                                        <i class="fas fa-clock"></i> <?php echo $article['reading_time']; ?> دقیقه
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- لیست مقالات -->
        <!-- Hero Section -->
        <div class="hero-section">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1>
                            <i class="fas fa-newspaper"></i>
                            مقالات و نشریات
                        </h1>
                        <p class="lead mb-0">مطالعه مقالات تخصصی، راهنماها و نشریات شرکت</p>
                    </div>
                    <div class="col-md-4">
                        <div class="search-box">
                            <form method="GET" class="d-flex align-items-center">
                                <i class="fas fa-search text-muted me-2"></i>
                                <input type="text" name="search" placeholder="جستجوی مقاله..." 
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
                <!-- سایدبار -->
                <div class="col-lg-3">
                    <div class="sidebar">
                        <!-- دسته‌بندی‌ها -->
                        <div class="sidebar-card">
                            <h5 class="mb-3">دسته‌بندی‌ها</h5>
                            <ul class="category-list">
                                <li class="<?php echo !$category_filter ? 'active' : ''; ?>">
                                    <a href="?" class="text-decoration-none d-flex justify-content-between">
                                        <span>همه مقالات</span>
                                        <span><?php echo count($articles); ?></span>
                                    </a>
                                </li>
                                <?php foreach ($categories as $category): ?>
                                    <li class="<?php echo $category_filter == $category['id'] ? 'active' : ''; ?>">
                                        <a href="?category=<?php echo $category['id']; ?>" 
                                           class="text-decoration-none d-flex justify-content-between">
                                            <span>
                                                <i class="fas <?php echo $category['icon']; ?>"></i>
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </span>
                                            <span><?php echo $category['article_count']; ?></span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        
                        <!-- مقالات پربازدید -->
                        <div class="sidebar-card">
                            <h5 class="mb-3">پربازدیدترین‌ها</h5>
                            <?php foreach ($popular_articles as $article): ?>
                                <div class="mb-3">
                                    <a href="?article=<?php echo $article['slug']; ?>" class="text-decoration-none">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($article['title']); ?></h6>
                                    </a>
                                    <small class="text-muted">
                                        <i class="fas fa-eye"></i> <?php echo $article['view_count']; ?> بازدید
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- لیست مقالات -->
                <div class="col-lg-9">
                    <?php if (count($featured_articles) > 0 && !$search_query && !$category_filter): ?>
                        <!-- مقالات ویژه -->
                        <div class="mb-4">
                            <h3 class="mb-3">
                                <i class="fas fa-star text-warning"></i>
                                مقالات ویژه
                            </h3>
                            <div class="row">
                                <?php foreach ($featured_articles as $article): ?>
                                    <div class="col-md-4 mb-3">
                                        <div class="article-card h-100">
                                            <div class="article-image">
                                                <?php if ($article['featured_image']): ?>
                                                    <img src="<?php echo $article['featured_image']; ?>" alt="">
                                                <?php else: ?>
                                                    <i class="fas fa-newspaper placeholder-icon"></i>
                                                <?php endif; ?>
                                                <span class="featured-badge">ویژه</span>
                                            </div>
                                            <div class="article-content">
                                                <a href="?article=<?php echo $article['slug']; ?>" class="article-title">
                                                    <?php echo htmlspecialchars($article['title']); ?>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- همه مقالات -->
                    <?php if (count($articles) > 0): ?>
                        <h4 class="mb-3">
                            <?php if ($search_query): ?>
                                نتایج جستجو برای: "<?php echo htmlspecialchars($search_query); ?>"
                            <?php elseif ($category_filter): ?>
                                مقالات دسته‌بندی انتخاب شده
                            <?php else: ?>
                                همه مقالات
                            <?php endif; ?>
                        </h4>
                        
                        <div class="article-grid">
                            <?php foreach ($articles as $article): ?>
                                <div class="article-card">
                                    <div class="article-image">
                                        <?php if ($article['featured_image']): ?>
                                            <img src="<?php echo $article['featured_image']; ?>" alt="">
                                        <?php else: ?>
                                            <i class="fas fa-newspaper placeholder-icon"></i>
                                        <?php endif; ?>
                                        <?php if ($article['is_featured']): ?>
                                            <span class="featured-badge">ویژه</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="article-content">
                                        <div class="article-meta">
                                            <span><i class="fas fa-user"></i> <?php echo $article['author'] ?? 'ناشناس'; ?></span>
                                            <span><i class="fas fa-clock"></i> <?php echo $article['reading_time']; ?> دقیقه</span>
                                        </div>
                                        
                                        <a href="?article=<?php echo $article['slug']; ?>" class="article-title">
                                            <?php echo htmlspecialchars($article['title']); ?>
                                        </a>
                                        
                                        <?php if ($article['summary']): ?>
                                            <p class="article-summary">
                                                <?php echo htmlspecialchars(mb_substr($article['summary'], 0, 150)); ?>...
                                            </p>
                                        <?php endif; ?>
                                        
                                        <div class="article-footer">
                                            <div>
                                                <small class="text-muted">
                                                    <i class="fas fa-eye"></i> <?php echo $article['view_count']; ?> |
                                                    <i class="fas fa-download"></i> <?php echo $article['download_count']; ?>
                                                </small>
                                            </div>
                                            <a href="?article=<?php echo $article['slug']; ?>" class="btn btn-sm btn-outline-primary">
                                                مطالعه <i class="fas fa-arrow-left"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-newspaper fa-4x text-muted mb-3"></i>
                            <h4>مقاله‌ای یافت نشد</h4>
                            <p class="text-muted">
                                <?php if ($search_query): ?>
                                    هیچ مقاله‌ای با عبارت "<?php echo htmlspecialchars($search_query); ?>" یافت نشد.
                                <?php elseif ($category_filter): ?>
                                    در این دسته‌بندی مقاله‌ای وجود ندارد.
                                <?php else: ?>
                                    هنوز مقاله‌ای منتشر نشده است.
                                <?php endif; ?>
                            </p>
                            <?php if ($search_query || $category_filter): ?>
                                <a href="training_articles.php" class="btn btn-primary mt-3">
                                    <i class="fas fa-arrow-right"></i>
                                    مشاهده همه مقالات
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>