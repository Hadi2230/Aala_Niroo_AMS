<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'ادمین') {
    header('Location: training.php');
    exit();
}

include 'config.php';

// تابع ایجاد slug از عنوان فارسی
function createSlug($string) {
    $string = trim($string);
    $string = mb_strtolower($string, 'UTF-8');
    $string = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $string);
    $string = preg_replace('/[\s-]+/', '-', $string);
    $string = preg_replace('/^-+|-+$/', '', $string);
    return $string ?: 'article-' . uniqid();
}

// پردازش عملیات
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_article') {
        try {
            $title = sanitizeInput($_POST['title']);
            $slug = createSlug($title);
            $summary = sanitizeInput($_POST['summary']);
            $content = $_POST['content']; // Rich text content
            $author = sanitizeInput($_POST['author']);
            $category_id = $_POST['category_id'] ?: null;
            $tags = sanitizeInput($_POST['tags']);
            $reading_time = (int)$_POST['reading_time'];
            $is_featured = isset($_POST['is_featured']) ? 1 : 0;
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            // آپلود تصویر شاخص
            $featured_image = null;
            if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
                $img_dir = __DIR__ . '/uploads/training/articles/images/';
                if (!is_dir($img_dir)) mkdir($img_dir, 0755, true);
                
                $img_ext = strtolower(pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION));
                if (in_array($img_ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                    $img_name = time() . '_featured_' . uniqid() . '.' . $img_ext;
                    $img_path = $img_dir . $img_name;
                    
                    if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $img_path)) {
                        $featured_image = 'uploads/training/articles/images/' . $img_name;
                    }
                }
            }
            
            // آپلود فایل PDF
            $pdf_file = null;
            if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
                $pdf_dir = __DIR__ . '/uploads/training/articles/pdfs/';
                if (!is_dir($pdf_dir)) mkdir($pdf_dir, 0755, true);
                
                $pdf_ext = strtolower(pathinfo($_FILES['pdf_file']['name'], PATHINFO_EXTENSION));
                if ($pdf_ext === 'pdf') {
                    $pdf_name = time() . '_article_' . uniqid() . '.pdf';
                    $pdf_path = $pdf_dir . $pdf_name;
                    
                    if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $pdf_path)) {
                        $pdf_file = 'uploads/training/articles/pdfs/' . $pdf_name;
                    }
                }
            }
            
            if (isset($_POST['article_id']) && $_POST['article_id']) {
                // ویرایش مقاله
                $article_id = (int)$_POST['article_id'];
                $stmt = $pdo->prepare("
                    UPDATE training_articles SET
                    title = ?, slug = ?, summary = ?, content = ?, featured_image = COALESCE(?, featured_image),
                    pdf_file = COALESCE(?, pdf_file), author = ?, category_id = ?, tags = ?,
                    reading_time = ?, is_featured = ?, is_active = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $title, $slug, $summary, $content, $featured_image, $pdf_file,
                    $author, $category_id, $tags, $reading_time, $is_featured, $is_active, $article_id
                ]);
                $_SESSION['success_message'] = 'مقاله با موفقیت ویرایش شد.';
            } else {
                // ایجاد مقاله جدید
                $stmt = $pdo->prepare("
                    INSERT INTO training_articles 
                    (title, slug, summary, content, featured_image, pdf_file, author, category_id, tags,
                     reading_time, published_by, is_featured, is_active, published_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $title, $slug, $summary, $content, $featured_image, $pdf_file,
                    $author, $category_id, $tags, $reading_time, $_SESSION['user_id'],
                    $is_featured, $is_active
                ]);
                $_SESSION['success_message'] = 'مقاله با موفقیت منتشر شد.';
            }
            
            header('Location: training_articles_admin.php');
            exit();
            
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'خطا: ' . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'delete' && isset($_POST['article_id'])) {
        try {
            $article_id = (int)$_POST['article_id'];
            
            // دریافت مسیر فایل‌ها
            $stmt = $pdo->prepare("SELECT featured_image, pdf_file FROM training_articles WHERE id = ?");
            $stmt->execute([$article_id]);
            $article = $stmt->fetch();
            
            if ($article) {
                // حذف فایل‌های فیزیکی
                if ($article['featured_image'] && file_exists(__DIR__ . '/' . $article['featured_image'])) {
                    unlink(__DIR__ . '/' . $article['featured_image']);
                }
                if ($article['pdf_file'] && file_exists(__DIR__ . '/' . $article['pdf_file'])) {
                    unlink(__DIR__ . '/' . $article['pdf_file']);
                }
                
                // حذف از دیتابیس
                $stmt = $pdo->prepare("DELETE FROM training_articles WHERE id = ?");
                $stmt->execute([$article_id]);
                
                $_SESSION['success_message'] = 'مقاله با موفقیت حذف شد.';
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'خطا در حذف مقاله: ' . $e->getMessage();
        }
        
        header('Location: training_articles_admin.php');
        exit();
    }
}

// دریافت مقاله برای ویرایش
$edit_article = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM training_articles WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_article = $stmt->fetch();
}

// دریافت لیست مقالات
$articles = $pdo->query("
    SELECT a.*, c.name as category_name, u.full_name as publisher_name
    FROM training_articles a
    LEFT JOIN training_categories c ON a.category_id = c.id
    LEFT JOIN users u ON a.published_by = u.id
    ORDER BY a.created_at DESC
")->fetchAll();

// دریافت دسته‌بندی‌ها
$categories = $pdo->query("SELECT * FROM training_categories WHERE is_active = 1 ORDER BY sort_order")->fetchAll();
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت مقالات و نشریات - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <style>
        .article-form-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .article-list {
            background: white;
            border-radius: 15px;
            padding: 20px;
        }
        
        .article-item {
            border-bottom: 1px solid #dee2e6;
            padding: 20px 0;
            transition: all 0.3s ease;
        }
        
        .article-item:hover {
            background: #f8f9fa;
            padding-right: 10px;
        }
        
        .article-item:last-child {
            border-bottom: none;
        }
        
        .article-thumbnail {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 10px;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        #editor {
            background: white;
            min-height: 300px;
            border-radius: 0 0 10px 10px;
        }
        
        .ql-toolbar {
            border-radius: 10px 10px 0 0;
        }
        
        .featured-badge {
            background: linear-gradient(135deg, #ffd700, #ff8c00);
            color: white;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
        }
        
        .tag-badge {
            background: #e9ecef;
            color: #495057;
            padding: 3px 8px;
            border-radius: 10px;
            margin: 2px;
            display: inline-block;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="fas fa-newspaper"></i> مدیریت مقالات و نشریات</h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">داشبورد</a></li>
                        <li class="breadcrumb-item"><a href="training.php">آموزش</a></li>
                        <li class="breadcrumb-item active">مدیریت مقالات</li>
                    </ol>
                </nav>
            </div>
            <a href="training_articles.php" class="btn btn-outline-primary">
                <i class="fas fa-eye"></i> نمایش عمومی
            </a>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $_SESSION['error_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- آمار -->
        <div class="row">
            <div class="col-md-3">
                <div class="stats-card">
                    <h3><?php echo count($articles); ?></h3>
                    <p class="mb-0">کل مقالات</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <h3><?php echo count(array_filter($articles, function($a) { return $a['is_featured']; })); ?></h3>
                    <p class="mb-0">مقالات ویژه</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <h3><?php echo array_sum(array_column($articles, 'view_count')); ?></h3>
                    <p class="mb-0">کل بازدیدها</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);">
                    <h3><?php echo array_sum(array_column($articles, 'download_count')); ?></h3>
                    <p class="mb-0">کل دانلودها</p>
                </div>
            </div>
        </div>

        <!-- فرم ایجاد/ویرایش مقاله -->
        <div class="article-form-section">
            <h4 class="mb-4">
                <?php echo $edit_article ? 'ویرایش مقاله' : 'نوشتن مقاله جدید'; ?>
            </h4>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_article">
                <?php if ($edit_article): ?>
                    <input type="hidden" name="article_id" value="<?php echo $edit_article['id']; ?>">
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-8">
                        <div class="mb-3">
                            <label class="form-label">عنوان مقاله *</label>
                            <input type="text" class="form-control" name="title" 
                                   value="<?php echo $edit_article ? htmlspecialchars($edit_article['title']) : ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">خلاصه مقاله</label>
                            <textarea class="form-control" name="summary" rows="3"><?php echo $edit_article ? htmlspecialchars($edit_article['summary']) : ''; ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">محتوای مقاله</label>
                            <div id="editor"><?php echo $edit_article ? $edit_article['content'] : ''; ?></div>
                            <input type="hidden" name="content" id="content">
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">نویسنده</label>
                            <input type="text" class="form-control" name="author" 
                                   value="<?php echo $edit_article ? htmlspecialchars($edit_article['author']) : $_SESSION['full_name'] ?? ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">دسته‌بندی</label>
                            <select class="form-select" name="category_id">
                                <option value="">انتخاب دسته‌بندی...</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" 
                                            <?php echo ($edit_article && $edit_article['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">برچسب‌ها</label>
                            <input type="text" class="form-control" name="tags" 
                                   value="<?php echo $edit_article ? htmlspecialchars($edit_article['tags']) : ''; ?>"
                                   placeholder="با کاما جدا کنید">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">زمان مطالعه (دقیقه)</label>
                            <input type="number" class="form-control" name="reading_time" 
                                   value="<?php echo $edit_article ? $edit_article['reading_time'] : '5'; ?>" min="1">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">تصویر شاخص</label>
                            <input type="file" class="form-control" name="featured_image" accept="image/*">
                            <?php if ($edit_article && $edit_article['featured_image']): ?>
                                <img src="<?php echo $edit_article['featured_image']; ?>" class="img-thumbnail mt-2" width="100">
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">فایل PDF</label>
                            <input type="file" class="form-control" name="pdf_file" accept=".pdf">
                            <?php if ($edit_article && $edit_article['pdf_file']): ?>
                                <small class="text-success">فایل PDF موجود است</small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="is_featured" id="is_featured"
                                   <?php echo ($edit_article && $edit_article['is_featured']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_featured">
                                مقاله ویژه
                            </label>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                                   <?php echo (!$edit_article || $edit_article['is_active']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_active">
                                منتشر شود
                            </label>
                        </div>
                        
                        <button type="submit" class="btn btn-success w-100">
                            <i class="fas fa-save"></i> 
                            <?php echo $edit_article ? 'ذخیره تغییرات' : 'انتشار مقاله'; ?>
                        </button>
                        
                        <?php if ($edit_article): ?>
                            <a href="training_articles_admin.php" class="btn btn-secondary w-100 mt-2">
                                <i class="fas fa-times"></i> انصراف
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- لیست مقالات -->
        <div class="article-list">
            <h4 class="mb-4">مقالات منتشر شده</h4>
            
            <?php foreach ($articles as $article): ?>
                <div class="article-item">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <?php if ($article['featured_image']): ?>
                                <img src="<?php echo $article['featured_image']; ?>" class="article-thumbnail">
                            <?php else: ?>
                                <div class="article-thumbnail bg-light d-flex align-items-center justify-content-center">
                                    <i class="fas fa-newspaper fa-2x text-muted"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col">
                            <h5>
                                <?php echo htmlspecialchars($article['title']); ?>
                                <?php if ($article['is_featured']): ?>
                                    <span class="featured-badge">ویژه</span>
                                <?php endif; ?>
                                <?php if (!$article['is_active']): ?>
                                    <span class="badge bg-danger">پیش‌نویس</span>
                                <?php endif; ?>
                            </h5>
                            
                            <?php if ($article['summary']): ?>
                                <p class="text-muted mb-2"><?php echo htmlspecialchars(mb_substr($article['summary'], 0, 150)); ?>...</p>
                            <?php endif; ?>
                            
                            <div class="d-flex flex-wrap align-items-center gap-3">
                                <small class="text-muted">
                                    <i class="fas fa-user"></i> <?php echo $article['author'] ?? 'ناشناس'; ?>
                                </small>
                                <small class="text-muted">
                                    <i class="fas fa-eye"></i> <?php echo $article['view_count']; ?> بازدید
                                </small>
                                <small class="text-muted">
                                    <i class="fas fa-clock"></i> <?php echo $article['reading_time']; ?> دقیقه
                                </small>
                                <small class="text-muted">
                                    <i class="fas fa-calendar"></i> <?php echo jalaliDate($article['published_at']); ?>
                                </small>
                                <?php if ($article['category_name']): ?>
                                    <span class="badge bg-secondary"><?php echo $article['category_name']; ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($article['tags']): ?>
                                <div class="mt-2">
                                    <?php foreach (explode(',', $article['tags']) as $tag): ?>
                                        <span class="tag-badge"><?php echo trim($tag); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-auto">
                            <div class="btn-group">
                                <a href="training_articles.php?article=<?php echo $article['slug']; ?>" 
                                   class="btn btn-sm btn-info" target="_blank">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="?edit=<?php echo $article['id']; ?>" class="btn btn-sm btn-warning">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form method="POST" style="display: inline;" 
                                      onsubmit="return confirm('آیا از حذف این مقاله اطمینان دارید؟');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="article_id" value="<?php echo $article['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php if (count($articles) === 0): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-newspaper fa-3x mb-3"></i>
                    <p>هنوز مقاله‌ای منتشر نشده است</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    
    <script>
        // Initialize Quill editor
        var quill = new Quill('#editor', {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ 'header': [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    ['blockquote', 'code-block'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    [{ 'direction': 'rtl' }],
                    [{ 'align': [] }],
                    ['link', 'image', 'video'],
                    ['clean']
                ]
            }
        });
        
        // Save content before form submission
        document.querySelector('form').onsubmit = function() {
            document.getElementById('content').value = quill.root.innerHTML;
        };
    </script>
</body>
</html>