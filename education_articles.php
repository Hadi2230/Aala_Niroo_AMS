<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

$is_admin = ($_SESSION['role'] === 'ادمین');

// دریافت مقالات
$search = $_GET['search'] ?? '';
$view_id = (int)($_GET['view'] ?? 0);
$page = (int)($_GET['page'] ?? 1);
$limit = 9;
$offset = ($page - 1) * $limit;

$where_clause = "WHERE is_active = 1";
$params = [];

if (!empty($search)) {
    $where_clause .= " AND (title LIKE ? OR content LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$count_sql = "SELECT COUNT(*) as total FROM education_articles $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_articles = $count_stmt->fetch()['total'];
$total_pages = ceil($total_articles / $limit);

$sql = "SELECT ea.*, u.username as uploaded_by_name 
        FROM education_articles ea 
        LEFT JOIN users u ON ea.uploaded_by = u.id 
        $where_clause 
        ORDER BY ea.created_at DESC 
        LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$articles = $stmt->fetchAll();

// دریافت مقاله برای نمایش
$selected_article = null;
if ($view_id > 0) {
    $stmt = $pdo->prepare("SELECT ea.*, u.username as uploaded_by_name 
                          FROM education_articles ea 
                          LEFT JOIN users u ON ea.uploaded_by = u.id 
                          WHERE ea.id = ? AND ea.is_active = 1");
    $stmt->execute([$view_id]);
    $selected_article = $stmt->fetch();
    
    if ($selected_article) {
        // افزایش view count
        $stmt = $pdo->prepare("UPDATE education_articles SET view_count = view_count + 1 WHERE id = ?");
        $stmt->execute([$view_id]);
        
        // ثبت لاگ
        logAction($pdo, 'view_article', "مقاله مشاهده شد: " . $selected_article['title']);
    }
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مقالات و نشریات - سامانه مدیریت اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .articles-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 0;
            margin-bottom: 40px;
        }
        
        .article-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            overflow: hidden;
            margin-bottom: 30px;
            height: 100%;
        }
        
        .article-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .article-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.8rem;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, #ffecd2, #fcb69f);
        }
        
        .article-content {
            padding: 30px;
        }
        
        .article-title {
            font-weight: bold;
            margin-bottom: 15px;
            color: #2c3e50;
            line-height: 1.4;
        }
        
        .article-excerpt {
            color: #6c757d;
            margin-bottom: 20px;
            line-height: 1.6;
        }
        
        .article-meta {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 20px;
        }
        
        .article-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-article {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 20px;
            padding: 8px 20px;
            color: white;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .btn-article:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .btn-article-outline {
            background: transparent;
            border: 2px solid #667eea;
            color: #667eea;
            border-radius: 20px;
            padding: 8px 20px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .btn-article-outline:hover {
            background: #667eea;
            color: white;
        }
        
        .article-viewer {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .article-viewer h1 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-weight: bold;
        }
        
        .article-viewer .content {
            line-height: 1.8;
            color: #444;
            font-size: 1.1rem;
        }
        
        .search-box {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 30px;
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
        
        .file-attachment {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            border-right: 4px solid #667eea;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="articles-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="display-5 fw-bold mb-3">
                        <i class="fas fa-newspaper me-3"></i>
                        مقالات و نشریات
                    </h1>
                    <p class="lead mb-0">
                        مطالعه مقالات تخصصی و نشریات آموزشی شرکت اعلا نیرو
                    </p>
                </div>
                <div class="col-lg-4 text-center">
                    <i class="fas fa-book" style="font-size: 6rem; opacity: 0.3;"></i>
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
                               placeholder="جستجو در مقالات...">
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
                    تعداد کل مقالات: <strong><?php echo $total_articles; ?></strong>
                    <?php if (!empty($search)): ?>
                    - نتایج جستجو برای: <strong>"<?php echo htmlspecialchars($search); ?>"</strong>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- نمایش مقاله -->
        <?php if ($selected_article): ?>
        <div class="article-viewer">
            <h1><?php echo htmlspecialchars($selected_article['title']); ?></h1>
            
            <div class="row mb-4">
                <div class="col-md-6">
                    <small class="text-muted">
                        <i class="fas fa-calendar me-2"></i>
                        <?php echo date('Y/m/d', strtotime($selected_article['created_at'])); ?>
                    </small>
                </div>
                <div class="col-md-6">
                    <small class="text-muted">
                        <i class="fas fa-eye me-2"></i>
                        <?php echo $selected_article['view_count']; ?> مشاهده
                    </small>
                </div>
            </div>
            
            <?php if (!empty($selected_article['content'])): ?>
            <div class="content">
                <?php echo nl2br(htmlspecialchars($selected_article['content'])); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($selected_article['file_path']): ?>
            <div class="file-attachment">
                <h6><i class="fas fa-paperclip me-2"></i>فایل ضمیمه</h6>
                <p class="mb-2"><?php echo htmlspecialchars($selected_article['title']); ?></p>
                <small class="text-muted">
                    <i class="fas fa-file me-2"></i>
                    <?php echo number_format($selected_article['file_size'] / 1024, 1); ?> KB
                </small>
                <div class="mt-3">
                    <a href="download_article.php?id=<?php echo $selected_article['id']; ?>" 
                       class="btn btn-article">
                        <i class="fas fa-download me-2"></i>
                        دانلود فایل
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- لیست مقالات -->
        <?php if (empty($articles)): ?>
        <div class="empty-state">
            <i class="fas fa-newspaper"></i>
            <h4>هیچ مقاله‌ای یافت نشد</h4>
            <p>
                <?php if (!empty($search)): ?>
                نتیجه‌ای برای جستجوی شما یافت نشد. لطفاً کلمات کلیدی دیگری امتحان کنید.
                <?php else: ?>
                هنوز مقاله یا نشریه‌ای آپلود نشده است.
                <?php endif; ?>
            </p>
        </div>
        <?php else: ?>
        <div class="row">
            <?php foreach ($articles as $article): ?>
            <div class="col-lg-4 col-md-6">
                <div class="article-card">
                    <div class="article-content">
                        <div class="article-icon">
                            <i class="fas fa-newspaper"></i>
                        </div>
                        
                        <h5 class="article-title"><?php echo htmlspecialchars($article['title']); ?></h5>
                        
                        <?php if (!empty($article['content'])): ?>
                        <div class="article-excerpt">
                            <?php echo htmlspecialchars(substr($article['content'], 0, 150)) . (strlen($article['content']) > 150 ? '...' : ''); ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="article-meta">
                            <div class="mb-2">
                                <i class="fas fa-calendar me-2"></i>
                                <?php echo date('Y/m/d', strtotime($article['created_at'])); ?>
                            </div>
                            <div class="mb-2">
                                <i class="fas fa-eye me-2"></i>
                                <?php echo $article['view_count']; ?> مشاهده
                            </div>
                            <div>
                                <i class="fas fa-user me-2"></i>
                                <?php echo htmlspecialchars($article['uploaded_by_name']); ?>
                            </div>
                        </div>
                        
                        <div class="article-actions">
                            <a href="?view=<?php echo $article['id']; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page; ?>" 
                               class="btn btn-article">
                                <i class="fas fa-eye me-2"></i>
                                مطالعه
                            </a>
                            <?php if ($article['file_path']): ?>
                            <a href="download_article.php?id=<?php echo $article['id']; ?>" 
                               class="btn btn-article-outline">
                                <i class="fas fa-download me-2"></i>
                                دانلود
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- صفحه‌بندی -->
        <?php if ($total_pages > 1): ?>
        <nav aria-label="صفحه‌بندی مقالات" class="mt-5">
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