<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';
include 'navbar.php';

$is_admin = ($_SESSION['role'] ?? '') === 'ادمین';
$action = $_GET['action'] ?? 'list';

// پردازش ایجاد/ویرایش مقاله
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['create', 'edit']) && $is_admin) {
    verifyCsrfToken();
    
    try {
        $title = sanitizeInput($_POST['title']);
        $content = $_POST['content']; // محتوای HTML را پاک نمی‌کنیم
        $summary = sanitizeInput($_POST['summary']);
        $category_id = (int)$_POST['category_id'];
        $is_published = isset($_POST['is_published']) ? 1 : 0;
        
        if (empty($title) || empty($content)) {
            throw new Exception('لطفاً عنوان و محتوای مقاله را وارد کنید');
        }
        
        $file_path = null;
        $file_name = null;
        $file_size = null;
        
        // پردازش فایل ضمیمه (اختیاری)
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['pdf', 'doc', 'docx', 'txt'];
            $file_path = uploadFile($_FILES['attachment'], __DIR__ . '/uploads/learning/articles/', $allowed_types);
            $file_name = $_FILES['attachment']['name'];
            $file_size = $_FILES['attachment']['size'];
        }
        
        if ($action === 'create') {
            // ایجاد مقاله جدید
            $stmt = $pdo->prepare("INSERT INTO articles (title, content, summary, file_path, file_name, file_size, category_id, author_id, is_published) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $title,
                $content,
                $summary,
                $file_path,
                $file_name,
                $file_size,
                $category_id ?: null,
                $_SESSION['user_id'],
                $is_published
            ]);
            
            logAction($pdo, 'article_create', "ایجاد مقاله: $title");
            $success_message = 'مقاله با موفقیت ایجاد شد';
            
        } else {
            // ویرایش مقاله
            $article_id = (int)$_POST['article_id'];
            
            if ($file_path) {
                // اگر فایل جدید آپلود شده، فایل قبلی را حذف کنیم
                $old_article = $pdo->prepare("SELECT file_path FROM articles WHERE id = ?");
                $old_article->execute([$article_id]);
                $old_file = $old_article->fetch();
                
                if ($old_file && $old_file['file_path'] && file_exists($old_file['file_path'])) {
                    unlink($old_file['file_path']);
                }
                
                $stmt = $pdo->prepare("UPDATE articles SET title = ?, content = ?, summary = ?, file_path = ?, file_name = ?, file_size = ?, category_id = ?, is_published = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$title, $content, $summary, $file_path, $file_name, $file_size, $category_id ?: null, $is_published, $article_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE articles SET title = ?, content = ?, summary = ?, category_id = ?, is_published = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$title, $content, $summary, $category_id ?: null, $is_published, $article_id]);
            }
            
            logAction($pdo, 'article_edit', "ویرایش مقاله: $title");
            $success_message = 'مقاله با موفقیت ویرایش شد';
        }
        
        $action = 'list'; // بازگشت به لیست
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// پردازش دانلود فایل ضمیمه
if ($action === 'download' && isset($_GET['id'])) {
    $article_id = (int)$_GET['id'];
    
    $stmt = $pdo->prepare("SELECT * FROM articles WHERE id = ? AND is_published = 1");
    $stmt->execute([$article_id]);
    $article = $stmt->fetch();
    
    if ($article && $article['file_path'] && file_exists($article['file_path'])) {
        // افزایش شمارنده دانلود
        $pdo->prepare("UPDATE articles SET download_count = download_count + 1 WHERE id = ?")->execute([$article_id]);
        
        // ارسال فایل
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $article['file_name'] . '"');
        header('Content-Length: ' . filesize($article['file_path']));
        readfile($article['file_path']);
        exit();
    }
}

// پردازش مشاهده مقاله
if ($action === 'read' && isset($_GET['id'])) {
    $article_id = (int)$_GET['id'];
    
    $stmt = $pdo->prepare("SELECT a.*, lc.name as category_name, u.username as author_name 
                          FROM articles a 
                          LEFT JOIN learning_categories lc ON a.category_id = lc.id 
                          LEFT JOIN users u ON a.author_id = u.id 
                          WHERE a.id = ? AND a.is_published = 1");
    $stmt->execute([$article_id]);
    $current_article = $stmt->fetch();
    
    if ($current_article) {
        // افزایش شمارنده مشاهده
        $pdo->prepare("UPDATE articles SET view_count = view_count + 1 WHERE id = ?")->execute([$article_id]);
    }
}

// حذف مقاله (فقط ادمین)
if ($action === 'delete' && isset($_GET['id']) && $is_admin) {
    $article_id = (int)$_GET['id'];
    
    $stmt = $pdo->prepare("SELECT * FROM articles WHERE id = ?");
    $stmt->execute([$article_id]);
    $article = $stmt->fetch();
    
    if ($article) {
        // حذف فایل ضمیمه
        if ($article['file_path'] && file_exists($article['file_path'])) {
            unlink($article['file_path']);
        }
        
        // حذف از دیتابیس
        $pdo->prepare("DELETE FROM articles WHERE id = ?")->execute([$article_id]);
        
        logAction($pdo, 'article_delete', "حذف مقاله: " . $article['title']);
        $success_message = 'مقاله با موفقیت حذف شد';
    }
}

// دریافت مقاله برای ویرایش
if ($action === 'edit' && isset($_GET['id']) && $is_admin) {
    $article_id = (int)$_GET['id'];
    
    $stmt = $pdo->prepare("SELECT * FROM articles WHERE id = ?");
    $stmt->execute([$article_id]);
    $edit_article = $stmt->fetch();
}

// دریافت دسته‌بندی‌ها
$categories = $pdo->query("SELECT * FROM learning_categories WHERE type = 'articles' ORDER BY name")->fetchAll();

// دریافت مقالات
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';

$where_conditions = ["is_published = 1"];
$params = [];

if ($search) {
    $where_conditions[] = "(title LIKE ? OR content LIKE ? OR summary LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category_filter) {
    $where_conditions[] = "category_id = ?";
    $params[] = $category_filter;
}

$where_clause = implode(' AND ', $where_conditions);

$stmt = $pdo->prepare("SELECT a.*, lc.name as category_name, u.username as author_name 
                      FROM articles a 
                      LEFT JOIN learning_categories lc ON a.category_id = lc.id 
                      LEFT JOIN users u ON a.author_id = u.id 
                      WHERE $where_clause 
                      ORDER BY a.created_at DESC");
$stmt->execute($params);
$articles = $stmt->fetchAll();
?>

<style>
    .articles-container {
        background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        min-height: 100vh;
        padding-top: 2rem;
    }
    
    .articles-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 20px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        overflow: hidden;
    }
    
    .article-item {
        background: white;
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        border-left: 4px solid #43e97b;
    }
    
    .article-item:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    }
    
    .article-title {
        font-weight: 600;
        margin-bottom: 1rem;
        color: #333;
    }
    
    .article-summary {
        color: #666;
        margin-bottom: 1rem;
        line-height: 1.6;
    }
    
    .article-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.9rem;
        color: #999;
        margin-bottom: 1rem;
    }
    
    .article-content {
        background: white;
        border-radius: 15px;
        padding: 2rem;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        line-height: 1.8;
    }
    
    .article-content h1, .article-content h2, .article-content h3 {
        color: #333;
        margin-top: 2rem;
        margin-bottom: 1rem;
    }
    
    .article-content p {
        margin-bottom: 1rem;
        text-align: justify;
    }
    
    .article-content img {
        max-width: 100%;
        height: auto;
        border-radius: 10px;
        margin: 1rem 0;
    }
    
    .category-badge {
        background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        color: white;
        padding: 0.3rem 0.8rem;
        border-radius: 20px;
        font-size: 0.8rem;
    }
    
    .stats-info {
        display: flex;
        gap: 1rem;
        align-items: center;
    }
    
    .search-box {
        background: rgba(255, 255, 255, 0.9);
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        backdrop-filter: blur(10px);
    }
    
    .editor-container {
        background: white;
        border-radius: 15px;
        padding: 2rem;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }
    
    .attachment-info {
        background: rgba(67, 233, 123, 0.1);
        border-radius: 10px;
        padding: 1rem;
        margin-top: 1rem;
    }
    
    .btn-read {
        background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        border: none;
        border-radius: 10px;
        color: white;
        padding: 0.5rem 1rem;
        transition: all 0.3s ease;
    }
    
    .btn-read:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        color: white;
    }
    
    .btn-edit {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        border: none;
        border-radius: 10px;
        color: white;
        padding: 0.5rem 1rem;
        transition: all 0.3s ease;
    }
    
    .btn-edit:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        color: white;
    }
    
    .btn-delete {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        border: none;
        border-radius: 10px;
        color: white;
        padding: 0.5rem 1rem;
        transition: all 0.3s ease;
    }
    
    .btn-delete:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        color: white;
    }
    
    .article-header {
        background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        color: white;
        padding: 2rem;
        border-radius: 15px;
        margin-bottom: 2rem;
        text-align: center;
    }
    
    .back-button {
        position: fixed;
        top: 100px;
        right: 20px;
        z-index: 1000;
        background: rgba(67, 233, 123, 0.9);
        border: none;
        border-radius: 50px;
        color: white;
        padding: 1rem 1.5rem;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        transition: all 0.3s ease;
    }
    
    .back-button:hover {
        background: rgba(67, 233, 123, 1);
        transform: translateY(-2px);
        color: white;
    }
</style>

<div class="articles-container">
    <div class="container">
        <!-- هدر صفحه -->
        <div class="text-center mb-5">
            <h1 class="text-white display-5 mb-3">
                <i class="fas fa-newspaper"></i>
                مقالات و نشریات
            </h1>
            <p class="text-white-50 lead">مجموعه کامل مقالات فنی و نشریات تخصصی شرکت</p>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($action === 'read' && isset($current_article) && $current_article): ?>
            <!-- نمایش مقاله -->
            <a href="?" class="back-button">
                <i class="fas fa-arrow-left"></i> بازگشت
            </a>
            
            <div class="article-header">
                <h1 class="mb-3"><?php echo htmlspecialchars($current_article['title']); ?></h1>
                <div class="d-flex justify-content-center align-items-center flex-wrap gap-3">
                    <?php if ($current_article['category_name']): ?>
                        <span class="badge bg-light text-dark">
                            <i class="fas fa-tag"></i> <?php echo htmlspecialchars($current_article['category_name']); ?>
                        </span>
                    <?php endif; ?>
                    <span class="badge bg-light text-dark">
                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($current_article['author_name']); ?>
                    </span>
                    <span class="badge bg-light text-dark">
                        <i class="fas fa-calendar"></i> <?php echo date('Y/m/d', strtotime($current_article['created_at'])); ?>
                    </span>
                    <span class="badge bg-light text-dark">
                        <i class="fas fa-eye"></i> <?php echo $current_article['view_count']; ?> مشاهده
                    </span>
                    <?php if ($current_article['file_path']): ?>
                        <a href="?action=download&id=<?php echo $current_article['id']; ?>" class="badge bg-warning text-dark text-decoration-none">
                            <i class="fas fa-download"></i> دانلود ضمیمه
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($current_article['summary']): ?>
                <div class="articles-card mb-4">
                    <div class="card-body">
                        <h5 class="text-primary mb-3">
                            <i class="fas fa-info-circle"></i> خلاصه مقاله
                        </h5>
                        <p class="lead"><?php echo nl2br(htmlspecialchars($current_article['summary'])); ?></p>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="article-content">
                <?php echo $current_article['content']; ?>
            </div>
            
            <?php if ($is_admin): ?>
                <div class="text-center mt-4">
                    <a href="?action=edit&id=<?php echo $current_article['id']; ?>" class="btn btn-edit me-2">
                        <i class="fas fa-edit"></i> ویرایش مقاله
                    </a>
                    <a href="?action=delete&id=<?php echo $current_article['id']; ?>" 
                       class="btn btn-delete"
                       onclick="return confirm('آیا از حذف این مقاله اطمینان دارید؟')">
                        <i class="fas fa-trash"></i> حذف مقاله
                    </a>
                </div>
            <?php endif; ?>
            
        <?php elseif (in_array($action, ['create', 'edit']) && $is_admin): ?>
            <!-- فرم ایجاد/ویرایش مقاله -->
            <div class="editor-container">
                <h3 class="mb-4">
                    <i class="fas fa-<?php echo $action === 'create' ? 'plus' : 'edit'; ?>"></i>
                    <?php echo $action === 'create' ? 'نوشتن مقاله جدید' : 'ویرایش مقاله'; ?>
                </h3>
                
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <?php if ($action === 'edit' && isset($edit_article)): ?>
                        <input type="hidden" name="article_id" value="<?php echo $edit_article['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">عنوان مقاله *</label>
                            <input type="text" name="title" class="form-control" 
                                   value="<?php echo isset($edit_article) ? htmlspecialchars($edit_article['title']) : ''; ?>" required>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">دسته‌بندی</label>
                            <select name="category_id" class="form-select">
                                <option value="">انتخاب دسته‌بندی</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" 
                                            <?php echo (isset($edit_article) && $edit_article['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">خلاصه مقاله</label>
                            <textarea name="summary" class="form-control" rows="3"><?php echo isset($edit_article) ? htmlspecialchars($edit_article['summary']) : ''; ?></textarea>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">محتوای مقاله *</label>
                            <textarea name="content" id="articleContent" class="form-control" rows="15" required><?php echo isset($edit_article) ? $edit_article['content'] : ''; ?></textarea>
                            <small class="form-text text-muted">
                                می‌توانید از HTML برای قالب‌بندی متن استفاده کنید
                            </small>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">فایل ضمیمه (اختیاری)</label>
                            <input type="file" name="attachment" class="form-control" accept=".pdf,.doc,.docx,.txt">
                            <small class="form-text text-muted">فرمت‌های مجاز: PDF, DOC, DOCX, TXT</small>
                            
                            <?php if (isset($edit_article) && $edit_article['file_path']): ?>
                                <div class="attachment-info mt-2">
                                    <small class="text-success">
                                        <i class="fas fa-paperclip"></i>
                                        فایل فعلی: <?php echo htmlspecialchars($edit_article['file_name']); ?>
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">وضعیت انتشار</label>
                            <div class="form-check mt-2">
                                <input type="checkbox" name="is_published" class="form-check-input" id="isPublished" 
                                       <?php echo (!isset($edit_article) || $edit_article['is_published']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="isPublished">
                                    منتشر شود
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <button type="submit" class="btn btn-success me-2">
                                <i class="fas fa-save"></i> 
                                <?php echo $action === 'create' ? 'انتشار مقاله' : 'ذخیره تغییرات'; ?>
                            </button>
                            <a href="?" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> بازگشت
                            </a>
                        </div>
                    </div>
                </form>
            </div>
            
        <?php else: ?>
            <!-- جستجو و فیلتر -->
            <div class="search-box">
                <form method="GET" class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">جستجو در مقالات</label>
                        <input type="text" name="search" class="form-control" placeholder="عنوان، محتوا یا خلاصه..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">دسته‌بندی</label>
                        <select name="category" class="form-select">
                            <option value="">همه دسته‌ها</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary d-block w-100">
                            <i class="fas fa-search"></i> جستجو
                        </button>
                    </div>
                </form>
                
                <?php if ($is_admin): ?>
                    <div class="text-center mt-3">
                        <a href="?action=create" class="btn btn-success">
                            <i class="fas fa-plus"></i> نوشتن مقاله جدید
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- لیست مقالات -->
            <div class="articles-card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-newspaper"></i>
                        مقالات و نشریات (<?php echo count($articles); ?>)
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($articles)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-newspaper fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">هیچ مقاله‌ای یافت نشد</h5>
                            <p class="text-muted">
                                <?php if ($is_admin): ?>
                                    برای شروع، اولین مقاله را بنویسید
                                <?php else: ?>
                                    در حال حاضر مقاله‌ای برای نمایش وجود ندارد
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($articles as $article): ?>
                            <div class="article-item">
                                <h5 class="article-title">
                                    <a href="?action=read&id=<?php echo $article['id']; ?>" class="text-decoration-none text-dark">
                                        <?php echo htmlspecialchars($article['title']); ?>
                                    </a>
                                </h5>
                                
                                <?php if ($article['summary']): ?>
                                    <p class="article-summary"><?php echo htmlspecialchars($article['summary']); ?></p>
                                <?php endif; ?>
                                
                                <div class="article-meta">
                                    <div class="d-flex flex-wrap align-items-center gap-3">
                                        <?php if ($article['category_name']): ?>
                                            <span class="category-badge">
                                                <i class="fas fa-tag"></i> <?php echo htmlspecialchars($article['category_name']); ?>
                                            </span>
                                        <?php endif; ?>
                                        
                                        <div class="stats-info">
                                            <small class="text-muted">
                                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($article['author_name']); ?>
                                            </small>
                                            <small class="text-muted">
                                                <i class="fas fa-calendar"></i> <?php echo date('Y/m/d', strtotime($article['created_at'])); ?>
                                            </small>
                                            <small class="text-muted">
                                                <i class="fas fa-eye"></i> <?php echo $article['view_count']; ?> مشاهده
                                            </small>
                                            <?php if ($article['file_path']): ?>
                                                <small class="text-muted">
                                                    <i class="fas fa-paperclip"></i> دارای ضمیمه
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <a href="?action=read&id=<?php echo $article['id']; ?>" class="btn btn-read btn-sm me-2">
                                            <i class="fas fa-book-open"></i> مطالعه
                                        </a>
                                        <?php if ($article['file_path']): ?>
                                            <a href="?action=download&id=<?php echo $article['id']; ?>" class="btn btn-outline-success btn-sm">
                                                <i class="fas fa-download"></i> دانلود ضمیمه
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($is_admin): ?>
                                        <div>
                                            <a href="?action=edit&id=<?php echo $article['id']; ?>" class="btn btn-edit btn-sm me-2">
                                                <i class="fas fa-edit"></i> ویرایش
                                            </a>
                                            <a href="?action=delete&id=<?php echo $article['id']; ?>" 
                                               class="btn btn-delete btn-sm"
                                               onclick="return confirm('آیا از حذف این مقاله اطمینان دارید؟')">
                                                <i class="fas fa-trash"></i> حذف
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // انیمیشن ظاهر شدن مقالات
        const articleItems = document.querySelectorAll('.article-item');
        articleItems.forEach((item, index) => {
            item.style.opacity = '0';
            item.style.transform = 'translateY(30px)';
            
            setTimeout(() => {
                item.style.transition = 'all 0.6s ease';
                item.style.opacity = '1';
                item.style.transform = 'translateY(0)';
            }, index * 100);
        });
        
        // بهبود ویرایشگر متن
        const contentTextarea = document.getElementById('articleContent');
        if (contentTextarea) {
            // اضافه کردن toolbar ساده برای قالب‌بندی
            const toolbar = document.createElement('div');
            toolbar.className = 'mb-2';
            toolbar.innerHTML = `
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-secondary" onclick="insertTag('h2')">
                        <i class="fas fa-heading"></i> عنوان
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="insertTag('p')">
                        <i class="fas fa-paragraph"></i> پاراگراف
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="insertTag('strong')">
                        <i class="fas fa-bold"></i> ضخیم
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="insertTag('em')">
                        <i class="fas fa-italic"></i> کج
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="insertList()">
                        <i class="fas fa-list"></i> لیست
                    </button>
                </div>
            `;
            
            contentTextarea.parentNode.insertBefore(toolbar, contentTextarea);
        }
        
        // تابع‌های کمکی برای ویرایشگر
        window.insertTag = function(tag) {
            const textarea = document.getElementById('articleContent');
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const selectedText = textarea.value.substring(start, end);
            
            let replacement;
            if (tag === 'h2') {
                replacement = `<h2>${selectedText || 'عنوان'}</h2>`;
            } else if (tag === 'p') {
                replacement = `<p>${selectedText || 'متن پاراگراف'}</p>`;
            } else {
                replacement = `<${tag}>${selectedText || 'متن'}</${tag}>`;
            }
            
            textarea.value = textarea.value.substring(0, start) + replacement + textarea.value.substring(end);
            textarea.focus();
        };
        
        window.insertList = function() {
            const textarea = document.getElementById('articleContent');
            const start = textarea.selectionStart;
            const listHtml = `<ul>
    <li>مورد اول</li>
    <li>مورد دوم</li>
    <li>مورد سوم</li>
</ul>`;
            
            textarea.value = textarea.value.substring(0, start) + listHtml + textarea.value.substring(start);
            textarea.focus();
        };
        
        // پیش‌نمایش زنده محتوا
        if (contentTextarea) {
            const previewBtn = document.createElement('button');
            previewBtn.type = 'button';
            previewBtn.className = 'btn btn-info btn-sm mt-2';
            previewBtn.innerHTML = '<i class="fas fa-eye"></i> پیش‌نمایش';
            previewBtn.onclick = function() {
                const content = contentTextarea.value;
                const previewWindow = window.open('', '_blank', 'width=800,height=600');
                previewWindow.document.write(`
                    <html>
                        <head>
                            <title>پیش‌نمایش مقاله</title>
                            <meta charset="UTF-8">
                            <style>
                                body { font-family: Tahoma, Arial; direction: rtl; padding: 20px; line-height: 1.8; }
                                h1, h2, h3 { color: #333; }
                                p { margin-bottom: 1rem; text-align: justify; }
                            </style>
                        </head>
                        <body>${content}</body>
                    </html>
                `);
                previewWindow.document.close();
            };
            
            contentTextarea.parentNode.appendChild(previewBtn);
        }
    });
</script>

</body>
</html>