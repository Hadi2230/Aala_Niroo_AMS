<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// دریافت آمار آموزش
$total_forms = $pdo->query("SELECT COUNT(*) as total FROM education_forms WHERE is_active = 1")->fetch()['total'];
$total_images = $pdo->query("SELECT COUNT(*) as total FROM education_images WHERE is_active = 1")->fetch()['total'];
$total_videos = $pdo->query("SELECT COUNT(*) as total FROM education_videos WHERE is_active = 1")->fetch()['total'];
$total_articles = $pdo->query("SELECT COUNT(*) as total FROM education_articles WHERE is_active = 1")->fetch()['total'];

// دریافت آخرین فایل‌ها
$recent_forms = $pdo->query("SELECT * FROM education_forms WHERE is_active = 1 ORDER BY created_at DESC LIMIT 3")->fetchAll();
$recent_images = $pdo->query("SELECT * FROM education_images WHERE is_active = 1 ORDER BY created_at DESC LIMIT 6")->fetchAll();
$recent_videos = $pdo->query("SELECT * FROM education_videos WHERE is_active = 1 ORDER BY created_at DESC LIMIT 3")->fetchAll();
$recent_articles = $pdo->query("SELECT * FROM education_articles WHERE is_active = 1 ORDER BY created_at DESC LIMIT 3")->fetchAll();

$is_admin = ($_SESSION['role'] === 'ادمین');
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
        .education-hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px 0;
            margin-bottom: 40px;
        }
        
        .education-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .education-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .education-card .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 20px;
        }
        
        .education-card .card-body {
            padding: 30px;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .stats-card h3 {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .section-title {
            color: #2c3e50;
            font-weight: bold;
            margin-bottom: 30px;
            position: relative;
            padding-bottom: 15px;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            right: 0;
            width: 50px;
            height: 3px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .file-item {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }
        
        .file-item:hover {
            transform: translateX(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.12);
        }
        
        .file-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            margin-left: 15px;
        }
        
        .file-icon.pdf { background: linear-gradient(135deg, #ff6b6b, #ee5a24); }
        .file-icon.doc { background: linear-gradient(135deg, #4ecdc4, #44a08d); }
        .file-icon.image { background: linear-gradient(135deg, #a8edea, #fed6e3); }
        .file-icon.video { background: linear-gradient(135deg, #ff9a9e, #fecfef); }
        .file-icon.article { background: linear-gradient(135deg, #ffecd2, #fcb69f); }
        
        .image-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .gallery-item {
            position: relative;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .gallery-item:hover {
            transform: scale(1.05);
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
        }
        
        .gallery-item img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        
        .gallery-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.7));
            display: flex;
            align-items: flex-end;
            padding: 15px;
            opacity: 0;
            transition: all 0.3s ease;
        }
        
        .gallery-item:hover .gallery-overlay {
            opacity: 1;
        }
        
        .gallery-overlay h6 {
            color: white;
            margin: 0;
            font-weight: bold;
        }
        
        .btn-education {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
            color: white;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .btn-education:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="education-hero">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="display-4 fw-bold mb-3">
                        <i class="fas fa-graduation-cap me-3"></i>
                        بخش آموزش
                    </h1>
                    <p class="lead mb-4">
                        دسترسی به فرم‌ها، تصاویر، ویدیوها و مقالات آموزشی شرکت اعلا نیرو
                    </p>
                </div>
                <div class="col-lg-4 text-center">
                    <i class="fas fa-book-open" style="font-size: 8rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- نمایش پیام‌ها -->
        <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- آمار کلی -->
        <div class="row mb-5">
            <div class="col-lg-3 col-md-6">
                <div class="stats-card">
                    <i class="fas fa-file-pdf mb-3" style="font-size: 2rem;"></i>
                    <h3><?php echo $total_forms; ?></h3>
                    <p class="mb-0">فرم‌های آموزشی</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stats-card">
                    <i class="fas fa-images mb-3" style="font-size: 2rem;"></i>
                    <h3><?php echo $total_images; ?></h3>
                    <p class="mb-0">تصاویر آموزشی</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stats-card">
                    <i class="fas fa-video mb-3" style="font-size: 2rem;"></i>
                    <h3><?php echo $total_videos; ?></h3>
                    <p class="mb-0">ویدیوهای آموزشی</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stats-card">
                    <i class="fas fa-newspaper mb-3" style="font-size: 2rem;"></i>
                    <h3><?php echo $total_articles; ?></h3>
                    <p class="mb-0">مقالات و نشریات</p>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- فرم‌های آموزشی -->
            <div class="col-lg-6 mb-5">
                <div class="education-card">
                    <div class="card-header">
                        <h4 class="mb-0">
                            <i class="fas fa-file-pdf me-2"></i>
                            فرم‌های آموزشی
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if ($is_admin): ?>
                        <div class="mb-4">
                            <button class="btn btn-education" data-bs-toggle="modal" data-bs-target="#uploadFormModal">
                                <i class="fas fa-upload me-2"></i>
                                آپلود فرم جدید
                            </button>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (empty($recent_forms)): ?>
                        <div class="empty-state">
                            <i class="fas fa-file-pdf"></i>
                            <h5>هیچ فرمی موجود نیست</h5>
                            <p>هنوز فرم آموزشی آپلود نشده است</p>
                        </div>
                        <?php else: ?>
                        <div class="recent-files">
                            <?php foreach ($recent_forms as $form): ?>
                            <div class="file-item d-flex align-items-center">
                                <div class="file-icon pdf">
                                    <i class="fas fa-file-pdf"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($form['title']); ?></h6>
                                    <small class="text-muted">
                                        <?php echo date('Y/m/d', strtotime($form['created_at'])); ?> - 
                                        <?php echo number_format($form['file_size'] / 1024, 1); ?> KB
                                    </small>
                                </div>
                                <div>
                                    <a href="download_form.php?id=<?php echo $form['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-download"></i>
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="text-center mt-3">
                            <a href="education_forms.php" class="btn btn-outline-primary">مشاهده همه فرم‌ها</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- تصاویر آموزشی -->
            <div class="col-lg-6 mb-5">
                <div class="education-card">
                    <div class="card-header">
                        <h4 class="mb-0">
                            <i class="fas fa-images me-2"></i>
                            تصاویر آموزشی
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if ($is_admin): ?>
                        <div class="mb-4">
                            <button class="btn btn-education" data-bs-toggle="modal" data-bs-target="#uploadImageModal">
                                <i class="fas fa-upload me-2"></i>
                                آپلود تصویر جدید
                            </button>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (empty($recent_images)): ?>
                        <div class="empty-state">
                            <i class="fas fa-images"></i>
                            <h5>هیچ تصویری موجود نیست</h5>
                            <p>هنوز تصویر آموزشی آپلود نشده است</p>
                        </div>
                        <?php else: ?>
                        <div class="image-gallery">
                            <?php foreach ($recent_images as $image): ?>
                            <div class="gallery-item">
                                <img src="<?php echo $image['thumbnail_path'] ?: $image['image_path']; ?>" alt="<?php echo htmlspecialchars($image['title']); ?>">
                                <div class="gallery-overlay">
                                    <h6><?php echo htmlspecialchars($image['title']); ?></h6>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="text-center mt-3">
                            <a href="education_images.php" class="btn btn-outline-primary">مشاهده همه تصاویر</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ویدیوهای آموزشی -->
            <div class="col-lg-6 mb-5">
                <div class="education-card">
                    <div class="card-header">
                        <h4 class="mb-0">
                            <i class="fas fa-video me-2"></i>
                            ویدیوهای آموزشی
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if ($is_admin): ?>
                        <div class="mb-4">
                            <button class="btn btn-education" data-bs-toggle="modal" data-bs-target="#uploadVideoModal">
                                <i class="fas fa-upload me-2"></i>
                                آپلود ویدیو جدید
                            </button>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (empty($recent_videos)): ?>
                        <div class="empty-state">
                            <i class="fas fa-video"></i>
                            <h5>هیچ ویدیویی موجود نیست</h5>
                            <p>هنوز ویدیوی آموزشی آپلود نشده است</p>
                        </div>
                        <?php else: ?>
                        <div class="recent-files">
                            <?php foreach ($recent_videos as $video): ?>
                            <div class="file-item d-flex align-items-center">
                                <div class="file-icon video">
                                    <i class="fas fa-play"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($video['title']); ?></h6>
                                    <small class="text-muted">
                                        <?php echo date('Y/m/d', strtotime($video['created_at'])); ?> - 
                                        <?php echo gmdate("H:i:s", $video['duration']); ?>
                                    </small>
                                </div>
                                <div>
                                    <a href="education_videos.php?view=<?php echo $video['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-play"></i>
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="text-center mt-3">
                            <a href="education_videos.php" class="btn btn-outline-primary">مشاهده همه ویدیوها</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- مقالات و نشریات -->
            <div class="col-lg-6 mb-5">
                <div class="education-card">
                    <div class="card-header">
                        <h4 class="mb-0">
                            <i class="fas fa-newspaper me-2"></i>
                            مقالات و نشریات
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if ($is_admin): ?>
                        <div class="mb-4">
                            <button class="btn btn-education" data-bs-toggle="modal" data-bs-target="#uploadArticleModal">
                                <i class="fas fa-upload me-2"></i>
                                آپلود مقاله جدید
                            </button>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (empty($recent_articles)): ?>
                        <div class="empty-state">
                            <i class="fas fa-newspaper"></i>
                            <h5>هیچ مقاله‌ای موجود نیست</h5>
                            <p>هنوز مقاله یا نشریه‌ای آپلود نشده است</p>
                        </div>
                        <?php else: ?>
                        <div class="recent-files">
                            <?php foreach ($recent_articles as $article): ?>
                            <div class="file-item d-flex align-items-center">
                                <div class="file-icon article">
                                    <i class="fas fa-newspaper"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($article['title']); ?></h6>
                                    <small class="text-muted">
                                        <?php echo date('Y/m/d', strtotime($article['created_at'])); ?>
                                    </small>
                                </div>
                                <div>
                                    <a href="education_articles.php?view=<?php echo $article['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($article['file_path']): ?>
                                    <a href="download_article.php?id=<?php echo $article['id']; ?>" class="btn btn-sm btn-outline-success">
                                        <i class="fas fa-download"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="text-center mt-3">
                            <a href="education_articles.php" class="btn btn-outline-primary">مشاهده همه مقالات</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal های آپلود -->
    <?php if ($is_admin): ?>
    <!-- Modal آپلود فرم -->
    <div class="modal fade" id="uploadFormModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">آپلود فرم جدید</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="upload_form.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="mb-3">
                            <label for="form_title" class="form-label">عنوان فرم</label>
                            <input type="text" class="form-control" id="form_title" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="form_description" class="form-label">توضیحات</label>
                            <textarea class="form-control" id="form_description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="form_file" class="form-label">فایل فرم</label>
                            <input type="file" class="form-control" id="form_file" name="file" accept=".pdf,.doc,.docx" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" class="btn btn-primary">آپلود</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal آپلود تصویر -->
    <div class="modal fade" id="uploadImageModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">آپلود تصویر جدید</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="upload_image.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="mb-3">
                            <label for="image_title" class="form-label">عنوان تصویر</label>
                            <input type="text" class="form-control" id="image_title" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="image_description" class="form-label">توضیحات</label>
                            <textarea class="form-control" id="image_description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="image_file" class="form-label">فایل تصویر</label>
                            <input type="file" class="form-control" id="image_file" name="file" accept="image/*" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" class="btn btn-primary">آپلود</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal آپلود ویدیو -->
    <div class="modal fade" id="uploadVideoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">آپلود ویدیو جدید</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="upload_video.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="mb-3">
                            <label for="video_title" class="form-label">عنوان ویدیو</label>
                            <input type="text" class="form-control" id="video_title" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="video_description" class="form-label">توضیحات</label>
                            <textarea class="form-control" id="video_description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="video_file" class="form-label">فایل ویدیو</label>
                            <input type="file" class="form-control" id="video_file" name="file" accept="video/*" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" class="btn btn-primary">آپلود</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal آپلود مقاله -->
    <div class="modal fade" id="uploadArticleModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">آپلود مقاله جدید</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="upload_article.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="mb-3">
                            <label for="article_title" class="form-label">عنوان مقاله</label>
                            <input type="text" class="form-control" id="article_title" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="article_content" class="form-label">محتوای مقاله</label>
                            <textarea class="form-control" id="article_content" name="content" rows="6"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="article_file" class="form-label">فایل مقاله (اختیاری)</label>
                            <input type="file" class="form-control" id="article_file" name="file" accept=".pdf,.doc,.docx">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" class="btn btn-primary">آپلود</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>