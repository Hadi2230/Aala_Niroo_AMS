<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'ادمین') {
    header('Location: training.php');
    exit();
}

include 'config.php';

// پردازش آپلود ویدیو
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'upload_file' && isset($_FILES['video_file'])) {
        try {
            $title = sanitizeInput($_POST['title']);
            $description = sanitizeInput($_POST['description']);
            $category_id = $_POST['category_id'] ?: null;
            
            $file = $_FILES['video_file'];
            $allowed_types = ['mp4', 'webm', 'ogg', 'avi', 'mov'];
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($file_ext, $allowed_types)) {
                throw new Exception('نوع فایل ویدیو مجاز نیست.');
            }
            
            // بدون محدودیت حجمی در سطح برنامه — محدودیت‌ها توسط PHP/وب‌سرور کنترل می‌شود
            
            $upload_dir = __DIR__ . '/uploads/training/videos/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_name = time() . '_' . uniqid() . '.' . $file_ext;
            $file_path = $upload_dir . $file_name;
            
            if (!move_uploaded_file($file['tmp_name'], $file_path)) {
                throw new Exception('خطا در آپلود فایل');
            }
            
            // آپلود thumbnail اگر وجود دارد
            $thumbnail_path = null;
            if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
                $thumb_dir = __DIR__ . '/uploads/training/videos/thumbnails/';
                if (!is_dir($thumb_dir)) {
                    mkdir($thumb_dir, 0755, true);
                }
                
                $thumb_ext = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
                if (in_array($thumb_ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                    $thumb_name = time() . '_thumb_' . uniqid() . '.' . $thumb_ext;
                    $thumb_path = $thumb_dir . $thumb_name;
                    
                    if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $thumb_path)) {
                        $thumbnail_path = 'uploads/training/videos/thumbnails/' . $thumb_name;
                    }
                }
            }
            
            // ذخیره در دیتابیس
            $stmt = $pdo->prepare("
                INSERT INTO training_videos 
                (title, description, video_path, thumbnail_path, file_size, video_type, category_id, uploaded_by, is_featured)
                VALUES (?, ?, ?, ?, ?, 'upload', ?, ?, ?)
            ");
            
            $stmt->execute([
                $title,
                $description,
                'uploads/training/videos/' . $file_name,
                $thumbnail_path,
                $file['size'],
                $category_id,
                $_SESSION['user_id'],
                isset($_POST['is_featured']) ? 1 : 0
            ]);
            
            $_SESSION['success_message'] = 'ویدیو با موفقیت آپلود شد.';
            header('Location: training_videos_admin.php');
            exit();
            
        } catch (Exception $e) {
            $_SESSION['error_message'] = $e->getMessage();
        }
    } elseif ($_POST['action'] === 'add_link') {
        try {
            $title = sanitizeInput($_POST['title']);
            $description = sanitizeInput($_POST['description']);
            $video_url = sanitizeInput($_POST['video_url']);
            $category_id = $_POST['category_id'] ?: null;
            $video_type = 'youtube'; // تشخیص نوع ویدیو
            
            if (strpos($video_url, 'youtube.com') !== false || strpos($video_url, 'youtu.be') !== false) {
                $video_type = 'youtube';
            } elseif (strpos($video_url, 'vimeo.com') !== false) {
                $video_type = 'vimeo';
            } elseif (strpos($video_url, 'aparat.com') !== false) {
                $video_type = 'aparat';
            }
            
            // ذخیره در دیتابیس
            $stmt = $pdo->prepare("
                INSERT INTO training_videos 
                (title, description, video_url, video_type, category_id, uploaded_by, is_featured)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $title,
                $description,
                $video_url,
                $video_type,
                $category_id,
                $_SESSION['user_id'],
                isset($_POST['is_featured']) ? 1 : 0
            ]);
            
            $_SESSION['success_message'] = 'لینک ویدیو با موفقیت اضافه شد.';
            header('Location: training_videos_admin.php');
            exit();
            
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'خطا در افزودن لینک: ' . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'delete' && isset($_POST['video_id'])) {
        try {
            $video_id = (int)$_POST['video_id'];
            
            // دریافت مسیر فایل‌ها
            $stmt = $pdo->prepare("SELECT video_path, thumbnail_path FROM training_videos WHERE id = ?");
            $stmt->execute([$video_id]);
            $video = $stmt->fetch();
            
            if ($video) {
                // حذف فایل‌های فیزیکی
                if ($video['video_path']) {
                    $video_file = __DIR__ . '/' . $video['video_path'];
                    if (file_exists($video_file)) unlink($video_file);
                }
                
                if ($video['thumbnail_path']) {
                    $thumb_file = __DIR__ . '/' . $video['thumbnail_path'];
                    if (file_exists($thumb_file)) unlink($thumb_file);
                }
                
                // حذف از دیتابیس
                $stmt = $pdo->prepare("DELETE FROM training_videos WHERE id = ?");
                $stmt->execute([$video_id]);
                
                $_SESSION['success_message'] = 'ویدیو با موفقیت حذف شد.';
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'خطا در حذف ویدیو: ' . $e->getMessage();
        }
        
        header('Location: training_videos_admin.php');
        exit();
    } elseif ($_POST['action'] === 'toggle_status' && isset($_POST['video_id'])) {
        $video_id = (int)$_POST['video_id'];
        $stmt = $pdo->prepare("UPDATE training_videos SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$video_id]);
        
        header('Location: training_videos_admin.php');
        exit();
    } elseif ($_POST['action'] === 'toggle_featured' && isset($_POST['video_id'])) {
        $video_id = (int)$_POST['video_id'];
        $stmt = $pdo->prepare("UPDATE training_videos SET is_featured = NOT is_featured WHERE id = ?");
        $stmt->execute([$video_id]);
        
        header('Location: training_videos_admin.php');
        exit();
    }
}

// دریافت لیست ویدیوها
$videos = $pdo->query("
    SELECT v.*, c.name as category_name, u.full_name as uploader_name
    FROM training_videos v
    LEFT JOIN training_categories c ON v.category_id = c.id
    LEFT JOIN users u ON v.uploaded_by = u.id
    ORDER BY v.created_at DESC
")->fetchAll();

// دریافت دسته‌بندی‌ها
$categories = $pdo->query("SELECT * FROM training_categories WHERE is_active = 1 ORDER BY sort_order")->fetchAll();
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت ویدیوهای آموزشی - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .nav-pills .nav-link {
            border-radius: 20px;
            padding: 10px 20px;
            margin: 0 5px;
        }
        
        .upload-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .video-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .video-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .video-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .video-thumbnail {
            position: relative;
            padding-bottom: 56.25%;
            background: #000;
        }
        
        .video-thumbnail img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .video-thumbnail .play-icon {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 3rem;
            color: rgba(255,255,255,0.8);
        }
        
        .video-info {
            padding: 15px;
        }
        
        .video-type-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1;
        }
        
        .featured-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            z-index: 1;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .drop-zone {
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .drop-zone:hover, .drop-zone.dragover {
            border-color: #0d6efd;
            background: #e7f1ff;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="fas fa-video"></i> مدیریت ویدیوهای آموزشی</h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">داشبورد</a></li>
                        <li class="breadcrumb-item"><a href="training.php">آموزش</a></li>
                        <li class="breadcrumb-item active">مدیریت ویدیوها</li>
                    </ol>
                </nav>
            </div>
            <a href="training_videos.php" class="btn btn-outline-primary">
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
                    <h3><?php echo count($videos); ?></h3>
                    <p class="mb-0">کل ویدیوها</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                    <h3><?php echo count(array_filter($videos, function($v) { return $v['is_featured']; })); ?></h3>
                    <p class="mb-0">ویدیوهای ویژه</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <h3><?php echo array_sum(array_column($videos, 'view_count')); ?></h3>
                    <p class="mb-0">کل بازدیدها</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <h3><?php echo count(array_filter($videos, function($v) { return $v['is_active']; })); ?></h3>
                    <p class="mb-0">ویدیوهای فعال</p>
                </div>
            </div>
        </div>

        <!-- بخش آپلود -->
        <div class="upload-section">
            <h4 class="mb-4">افزودن ویدیو جدید</h4>
            
            <!-- انتخاب نوع آپلود -->
            <ul class="nav nav-pills mb-4" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#upload-file">
                        <i class="fas fa-upload"></i> آپلود فایل
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#add-link">
                        <i class="fas fa-link"></i> افزودن لینک
                    </button>
                </li>
            </ul>
            
            <div class="tab-content">
                <!-- آپلود فایل -->
                <div class="tab-pane fade show active" id="upload-file">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload_file">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="title" class="form-label">عنوان ویدیو *</label>
                                    <input type="text" class="form-control" name="title" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="category_id" class="form-label">دسته‌بندی</label>
                                    <select class="form-select" name="category_id">
                                        <option value="">انتخاب دسته‌بندی...</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>">
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">توضیحات</label>
                                    <textarea class="form-control" name="description" rows="3"></textarea>
                                </div>
                                
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="is_featured" id="is_featured">
                                    <label class="form-check-label" for="is_featured">
                                        ویدیو ویژه
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">فایل ویدیو *</label>
                                    <div class="drop-zone" id="videoDropZone">
                                        <i class="fas fa-cloud-upload-alt fa-3x mb-3 text-muted"></i>
                                        <p>فایل ویدیو را اینجا رها کنید یا کلیک کنید</p>
                                        <input type="file" name="video_file" class="d-none" id="videoFile" 
                                               accept="video/*" required>
                                        <button type="button" class="btn btn-outline-primary" 
                                                onclick="document.getElementById('videoFile').click()">
                                            انتخاب فایل
                                        </button>
                                        <p class="text-muted mt-2">
                                            <small>حداکثر حجم: 100MB | فرمت‌های مجاز: MP4, WebM, OGG</small>
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">تصویر پیش‌نمایش</label>
                                    <input type="file" class="form-control" name="thumbnail" accept="image/*">
                                    <small class="text-muted">اختیاری - برای نمایش قبل از پخش ویدیو</small>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-upload"></i> آپلود ویدیو
                        </button>
                    </form>
                </div>
                
                <!-- افزودن لینک -->
                <div class="tab-pane fade" id="add-link">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_link">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">عنوان ویدیو *</label>
                                    <input type="text" class="form-control" name="title" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">لینک ویدیو *</label>
                                    <input type="url" class="form-control" name="video_url" 
                                           placeholder="https://www.youtube.com/watch?v=..." required>
                                    <small class="text-muted">پشتیبانی از: YouTube, Vimeo, Aparat</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">دسته‌بندی</label>
                                    <select class="form-select" name="category_id">
                                        <option value="">انتخاب دسته‌بندی...</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>">
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">توضیحات</label>
                                    <textarea class="form-control" name="description" rows="5"></textarea>
                                </div>
                                
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="is_featured">
                                    <label class="form-check-label">
                                        ویدیو ویژه
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i> افزودن لینک
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- لیست ویدیوها -->
        <h4 class="mb-3">لیست ویدیوها</h4>
        <div class="video-grid">
            <?php foreach ($videos as $video): ?>
                <div class="video-card">
                    <div class="video-thumbnail">
                        <?php if ($video['is_featured']): ?>
                            <span class="badge bg-warning featured-badge">
                                <i class="fas fa-star"></i> ویژه
                            </span>
                        <?php endif; ?>
                        
                        <span class="badge bg-info video-type-badge">
                            <?php echo strtoupper($video['video_type']); ?>
                        </span>
                        
                        <?php if ($video['thumbnail_path']): ?>
                            <img src="<?php echo $video['thumbnail_path']; ?>" alt="">
                        <?php else: ?>
                            <div class="d-flex align-items-center justify-content-center h-100 bg-dark">
                                <i class="fas fa-video fa-3x text-white-50"></i>
                            </div>
                        <?php endif; ?>
                        
                        <i class="fas fa-play-circle play-icon"></i>
                    </div>
                    
                    <div class="video-info">
                        <h6><?php echo htmlspecialchars($video['title']); ?></h6>
                        
                        <div class="d-flex justify-content-between text-muted small mb-2">
                            <span><i class="fas fa-eye"></i> <?php echo $video['view_count']; ?></span>
                            <span><i class="fas fa-calendar"></i> <?php echo jalaliDate($video['created_at']); ?></span>
                        </div>
                        
                        <?php if ($video['category_name']): ?>
                            <span class="badge bg-secondary mb-2"><?php echo $video['category_name']; ?></span>
                        <?php endif; ?>
                        
                        <div class="btn-group btn-group-sm w-100">
                            <?php if ($video['video_path']): ?>
                                <a href="training_stream.php?id=<?php echo $video['id']; ?>" class="btn btn-info" target="_blank">
                                    <i class="fas fa-play"></i>
                                </a>
                            <?php elseif ($video['video_url']): ?>
                                <a href="<?php echo $video['video_url']; ?>" class="btn btn-info" target="_blank">
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                            <?php endif; ?>
                            
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="toggle_featured">
                                <input type="hidden" name="video_id" value="<?php echo $video['id']; ?>">
                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-star"></i>
                                </button>
                            </form>
                            
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="video_id" value="<?php echo $video['id']; ?>">
                                <button type="submit" class="btn btn-secondary">
                                    <i class="fas fa-toggle-<?php echo $video['is_active'] ? 'on' : 'off'; ?>"></i>
                                </button>
                            </form>
                            
                            <form method="POST" style="display: inline;" 
                                  onsubmit="return confirm('آیا از حذف این ویدیو اطمینان دارید؟');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="video_id" value="<?php echo $video['id']; ?>">
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Drag and Drop for video
        const dropZone = document.getElementById('videoDropZone');
        const fileInput = document.getElementById('videoFile');
        
        if (dropZone) {
            dropZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropZone.classList.add('dragover');
            });
            
            dropZone.addEventListener('dragleave', () => {
                dropZone.classList.remove('dragover');
            });
            
            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropZone.classList.remove('dragover');
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    fileInput.files = files;
                    updateFileInfo(files[0]);
                }
            });
            
            fileInput.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    updateFileInfo(e.target.files[0]);
                }
            });
        }
        
        function updateFileInfo(file) {
            const dropZone = document.getElementById('videoDropZone');
            dropZone.innerHTML = `
                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                <h5>${file.name}</h5>
                <p class="text-muted">حجم: ${(file.size / (1024 * 1024)).toFixed(2)} MB</p>
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="resetUpload()">
                    <i class="fas fa-times"></i> تغییر فایل
                </button>
            `;
        }
        
        function resetUpload() {
            location.reload();
        }
    </script>
</body>
</html>