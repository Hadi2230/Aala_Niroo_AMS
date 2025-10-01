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

// تابع دریافت مدت زمان ویدیو (ساده شده)
function getVideoDuration($file_path) {
    // در یک پیاده‌سازی واقعی، از FFmpeg استفاده می‌شود
    // اینجا مقدار پیش‌فرض برمی‌گردانیم
    return "نامشخص";
}

// تابع ایجاد thumbnail برای ویدیو
function createVideoThumbnail($source, $destination) {
    // در یک پیاده‌سازی واقعی، از FFmpeg برای ایجاد thumbnail استفاده می‌شود
    // اینجا یک تصویر پیش‌فرض کپی می‌کنیم
    $default_thumb = __DIR__ . '/assets/video-placeholder.jpg';
    if (file_exists($default_thumb)) {
        copy($default_thumb, $destination);
        return true;
    }
    return false;
}

// پردازش آپلود ویدیو جدید
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'upload' && $is_admin) {
    verifyCsrfToken();
    
    try {
        $title = sanitizeInput($_POST['title']);
        $description = sanitizeInput($_POST['description']);
        $category_id = (int)$_POST['category_id'];
        
        if (empty($title) || !isset($_FILES['video_file'])) {
            throw new Exception('لطفاً تمام فیلدهای الزامی را پر کنید');
        }
        
        // بررسی نوع فایل
        $allowed_types = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm'];
        $file_ext = strtolower(pathinfo($_FILES['video_file']['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_ext, $allowed_types)) {
            throw new Exception('نوع فایل مجاز نیست. فرمت‌های مجاز: ' . implode(', ', $allowed_types));
        }
        
        // بررسی حجم فایل (حداکثر 100MB)
        if ($_FILES['video_file']['size'] > 100 * 1024 * 1024) {
            throw new Exception('حجم فایل بیش از حد مجاز است (حداکثر 100MB)');
        }
        
        // آپلود ویدیو
        $file_path = uploadFile($_FILES['video_file'], __DIR__ . '/uploads/learning/videos/', $allowed_types);
        
        // ایجاد thumbnail
        $thumbnail_name = 'thumb_' . pathinfo(basename($file_path), PATHINFO_FILENAME) . '.jpg';
        $thumbnail_path = __DIR__ . '/uploads/learning/videos/thumbnails/' . $thumbnail_name;
        createVideoThumbnail($file_path, $thumbnail_path);
        
        // دریافت مدت زمان ویدیو
        $duration = getVideoDuration($file_path);
        
        // ذخیره در دیتابیس
        $stmt = $pdo->prepare("INSERT INTO training_videos (title, description, file_name, file_path, thumbnail_path, duration, file_size, category_id, uploaded_by) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $title,
            $description,
            $_FILES['video_file']['name'],
            $file_path,
            'uploads/learning/videos/thumbnails/' . $thumbnail_name,
            $duration,
            $_FILES['video_file']['size'],
            $category_id ?: null,
            $_SESSION['user_id']
        ]);
        
        logAction($pdo, 'video_upload', "آپلود ویدیو: $title");
        $success_message = 'ویدیو با موفقیت آپلود شد';
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// پردازش مشاهده ویدیو
if ($action === 'watch' && isset($_GET['id'])) {
    $video_id = (int)$_GET['id'];
    
    $stmt = $pdo->prepare("SELECT * FROM training_videos WHERE id = ? AND is_active = 1");
    $stmt->execute([$video_id]);
    $video = $stmt->fetch();
    
    if ($video) {
        // افزایش شمارنده مشاهده
        $pdo->prepare("UPDATE training_videos SET view_count = view_count + 1 WHERE id = ?")->execute([$video_id]);
    }
}

// حذف ویدیو (فقط ادمین)
if ($action === 'delete' && isset($_GET['id']) && $is_admin) {
    $video_id = (int)$_GET['id'];
    
    $stmt = $pdo->prepare("SELECT * FROM training_videos WHERE id = ?");
    $stmt->execute([$video_id]);
    $video = $stmt->fetch();
    
    if ($video) {
        // حذف فایل‌ها
        if (file_exists($video['file_path'])) {
            unlink($video['file_path']);
        }
        if ($video['thumbnail_path'] && file_exists($video['thumbnail_path'])) {
            unlink($video['thumbnail_path']);
        }
        
        // حذف از دیتابیس
        $pdo->prepare("DELETE FROM training_videos WHERE id = ?")->execute([$video_id]);
        
        logAction($pdo, 'video_delete', "حذف ویدیو: " . $video['title']);
        $success_message = 'ویدیو با موفقیت حذف شد';
    }
}

// دریافت دسته‌بندی‌ها
$categories = $pdo->query("SELECT * FROM learning_categories WHERE type = 'videos' ORDER BY name")->fetchAll();

// دریافت ویدیوها
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';

$where_conditions = ["is_active = 1"];
$params = [];

if ($search) {
    $where_conditions[] = "(title LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category_filter) {
    $where_conditions[] = "category_id = ?";
    $params[] = $category_filter;
}

$where_clause = implode(' AND ', $where_conditions);

$stmt = $pdo->prepare("SELECT tv.*, lc.name as category_name, u.username as uploader_name 
                      FROM training_videos tv 
                      LEFT JOIN learning_categories lc ON tv.category_id = lc.id 
                      LEFT JOIN users u ON tv.uploaded_by = u.id 
                      WHERE $where_clause 
                      ORDER BY tv.created_at DESC");
$stmt->execute($params);
$videos = $stmt->fetchAll();

// اگر در حالت تماشای ویدیو هستیم
if ($action === 'watch' && isset($_GET['id'])) {
    $video_id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT tv.*, lc.name as category_name, u.username as uploader_name 
                          FROM training_videos tv 
                          LEFT JOIN learning_categories lc ON tv.category_id = lc.id 
                          LEFT JOIN users u ON tv.uploaded_by = u.id 
                          WHERE tv.id = ? AND tv.is_active = 1");
    $stmt->execute([$video_id]);
    $current_video = $stmt->fetch();
}
?>

<style>
    .videos-container {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        min-height: 100vh;
        padding-top: 2rem;
    }
    
    .videos-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 20px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        overflow: hidden;
    }
    
    .video-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 1.5rem;
        padding: 1rem;
    }
    
    .video-item {
        background: white;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        position: relative;
    }
    
    .video-item:hover {
        transform: translateY(-10px);
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
    }
    
    .video-thumbnail {
        width: 100%;
        height: 200px;
        object-fit: cover;
        cursor: pointer;
        transition: all 0.3s ease;
        position: relative;
    }
    
    .video-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.6);
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    .video-item:hover .video-overlay {
        opacity: 1;
    }
    
    .play-button {
        width: 80px;
        height: 80px;
        background: rgba(255, 255, 255, 0.9);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        color: #4facfe;
        transition: all 0.3s ease;
    }
    
    .play-button:hover {
        background: white;
        transform: scale(1.1);
    }
    
    .video-info {
        padding: 1rem;
    }
    
    .video-title {
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: #333;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    .video-description {
        color: #666;
        font-size: 0.9rem;
        margin-bottom: 1rem;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    .video-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.8rem;
        color: #999;
    }
    
    .category-badge {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        color: white;
        padding: 0.3rem 0.8rem;
        border-radius: 20px;
        font-size: 0.8rem;
    }
    
    .duration-badge {
        position: absolute;
        bottom: 10px;
        right: 10px;
        background: rgba(0, 0, 0, 0.8);
        color: white;
        padding: 0.2rem 0.5rem;
        border-radius: 5px;
        font-size: 0.8rem;
    }
    
    .upload-zone {
        border: 2px dashed #4facfe;
        border-radius: 15px;
        padding: 3rem;
        text-align: center;
        background: rgba(79, 172, 254, 0.1);
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    .upload-zone:hover {
        border-color: #00f2fe;
        background: rgba(0, 242, 254, 0.1);
    }
    
    .upload-zone.dragover {
        border-color: #43e97b;
        background: rgba(67, 233, 123, 0.1);
    }
    
    .search-box {
        background: rgba(255, 255, 255, 0.9);
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        backdrop-filter: blur(10px);
    }
    
    .video-player-container {
        background: #000;
        border-radius: 15px;
        overflow: hidden;
        margin-bottom: 2rem;
    }
    
    .video-player {
        width: 100%;
        height: 500px;
    }
    
    .video-details {
        background: white;
        border-radius: 15px;
        padding: 2rem;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }
    
    .stats-info {
        display: flex;
        gap: 1rem;
        align-items: center;
    }
    
    .progress-bar {
        height: 4px;
        background: #e9ecef;
        border-radius: 2px;
        overflow: hidden;
        margin-top: 0.5rem;
    }
    
    .progress-fill {
        height: 100%;
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        width: 0%;
        transition: width 0.3s ease;
    }
</style>

<div class="videos-container">
    <div class="container">
        <!-- هدر صفحه -->
        <div class="text-center mb-5">
            <h1 class="text-white display-5 mb-3">
                <i class="fas fa-video"></i>
                ویدیوهای آموزشی
            </h1>
            <p class="text-white-50 lead">مجموعه کامل ویدیوهای آموزشی و راهنمای فنی</p>
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

        <?php if ($action === 'watch' && isset($current_video) && $current_video): ?>
            <!-- پخش ویدیو -->
            <div class="videos-card mb-4">
                <div class="video-player-container">
                    <video class="video-player" controls>
                        <source src="<?php echo htmlspecialchars($current_video['file_path']); ?>" type="video/mp4">
                        مرورگر شما از پخش ویدیو پشتیبانی نمی‌کند.
                    </video>
                </div>
                
                <div class="video-details">
                    <div class="row">
                        <div class="col-lg-8">
                            <h2 class="mb-3"><?php echo htmlspecialchars($current_video['title']); ?></h2>
                            <?php if ($current_video['description']): ?>
                                <p class="text-muted mb-3"><?php echo nl2br(htmlspecialchars($current_video['description'])); ?></p>
                            <?php endif; ?>
                            
                            <div class="d-flex flex-wrap align-items-center mb-3">
                                <?php if ($current_video['category_name']): ?>
                                    <span class="category-badge me-3">
                                        <i class="fas fa-tag"></i> <?php echo htmlspecialchars($current_video['category_name']); ?>
                                    </span>
                                <?php endif; ?>
                                
                                <div class="stats-info">
                                    <small class="text-muted">
                                        <i class="fas fa-eye"></i> <?php echo $current_video['view_count']; ?> مشاهده
                                    </small>
                                    <small class="text-muted">
                                        <i class="fas fa-calendar"></i> <?php echo date('Y/m/d', strtotime($current_video['created_at'])); ?>
                                    </small>
                                    <small class="text-muted">
                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($current_video['uploader_name']); ?>
                                    </small>
                                    <?php if ($current_video['duration'] !== 'نامشخص'): ?>
                                        <small class="text-muted">
                                            <i class="fas fa-clock"></i> <?php echo $current_video['duration']; ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-4 text-end">
                            <a href="?" class="btn btn-primary mb-2 d-block">
                                <i class="fas fa-arrow-left"></i> بازگشت به لیست
                            </a>
                            <?php if ($is_admin): ?>
                                <a href="?action=delete&id=<?php echo $current_video['id']; ?>" 
                                   class="btn btn-danger d-block"
                                   onclick="return confirm('آیا از حذف این ویدیو اطمینان دارید؟')">
                                    <i class="fas fa-trash"></i> حذف ویدیو
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- جستجو و فیلتر -->
            <div class="search-box">
                <form method="GET" class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">جستجو در ویدیوها</label>
                        <input type="text" name="search" class="form-control" placeholder="نام ویدیو یا توضیحات..." value="<?php echo htmlspecialchars($search); ?>">
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
                        <a href="?action=upload" class="btn btn-success">
                            <i class="fas fa-plus"></i> آپلود ویدیو جدید
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($action === 'upload' && $is_admin): ?>
                <!-- فرم آپلود -->
                <div class="videos-card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-upload"></i>
                            آپلود ویدیو جدید
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>توجه:</strong> حداکثر حجم مجاز برای آپلود ویدیو 100 مگابایت است. فرمت‌های مجاز: MP4, AVI, MOV, WMV, FLV, WEBM
                        </div>
                        
                        <form method="POST" enctype="multipart/form-data" class="row g-3">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            
                            <div class="col-md-6">
                                <label class="form-label">عنوان ویدیو *</label>
                                <input type="text" name="title" class="form-control" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">دسته‌بندی</label>
                                <select name="category_id" class="form-select">
                                    <option value="">انتخاب دسته‌بندی</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>">
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">توضیحات</label>
                                <textarea name="description" class="form-control" rows="4"></textarea>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">فایل ویدیو *</label>
                                <div class="upload-zone" id="uploadZone">
                                    <i class="fas fa-video fa-2x mb-3 text-primary"></i>
                                    <p>ویدیو را اینجا بکشید یا کلیک کنید</p>
                                    <small class="text-muted">حداکثر حجم: 100MB | فرمت‌های مجاز: MP4, AVI, MOV, WMV, FLV, WEBM</small>
                                    <input type="file" name="video_file" id="videoFile" class="d-none" accept="video/*" required>
                                </div>
                                <div id="uploadProgress" class="mt-3 d-none">
                                    <div class="progress-bar">
                                        <div class="progress-fill" id="progressFill"></div>
                                    </div>
                                    <small class="text-muted mt-2 d-block">در حال آپلود... <span id="progressText">0%</span></small>
                                </div>
                            </div>
                            
                            <div class="col-12">
                                <button type="submit" class="btn btn-success me-2" id="uploadBtn">
                                    <i class="fas fa-upload"></i> آپلود ویدیو
                                </button>
                                <a href="?" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> بازگشت
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <!-- لیست ویدیوها -->
            <div class="videos-card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-video"></i>
                        ویدیوهای آموزشی (<?php echo count($videos); ?>)
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($videos)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-video fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">هیچ ویدیویی یافت نشد</h5>
                            <p class="text-muted">
                                <?php if ($is_admin): ?>
                                    برای شروع، اولین ویدیو را آپلود کنید
                                <?php else: ?>
                                    در حال حاضر ویدیویی برای نمایش وجود ندارد
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="video-grid">
                            <?php foreach ($videos as $video): ?>
                                <div class="video-item">
                                    <div class="position-relative">
                                        <?php 
                                        $thumbnail_src = $video['thumbnail_path'] && file_exists($video['thumbnail_path']) 
                                            ? $video['thumbnail_path'] 
                                            : 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="350" height="200" viewBox="0 0 350 200"><rect width="350" height="200" fill="#f8f9fa"/><text x="50%" y="50%" text-anchor="middle" dy=".3em" fill="#6c757d" font-size="16">پیش‌نمایش ویدیو</text></svg>');
                                        ?>
                                        <img src="<?php echo htmlspecialchars($thumbnail_src); ?>" 
                                             alt="<?php echo htmlspecialchars($video['title']); ?>" 
                                             class="video-thumbnail">
                                        
                                        <?php if ($video['duration'] !== 'نامشخص'): ?>
                                            <div class="duration-badge">
                                                <?php echo $video['duration']; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="video-overlay" onclick="location.href='?action=watch&id=<?php echo $video['id']; ?>'">
                                            <div class="play-button">
                                                <i class="fas fa-play"></i>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="video-info">
                                        <h6 class="video-title"><?php echo htmlspecialchars($video['title']); ?></h6>
                                        <?php if ($video['description']): ?>
                                            <p class="video-description"><?php echo htmlspecialchars($video['description']); ?></p>
                                        <?php endif; ?>
                                        
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <?php if ($video['category_name']): ?>
                                                <span class="category-badge">
                                                    <i class="fas fa-tag"></i> <?php echo htmlspecialchars($video['category_name']); ?>
                                                </span>
                                            <?php endif; ?>
                                            
                                            <div class="stats-info">
                                                <small class="text-muted">
                                                    <i class="fas fa-eye"></i> <?php echo $video['view_count']; ?>
                                                </small>
                                            </div>
                                        </div>
                                        
                                        <div class="video-meta">
                                            <small>
                                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($video['uploader_name']); ?>
                                            </small>
                                            <small>
                                                <i class="fas fa-calendar"></i> <?php echo date('Y/m/d', strtotime($video['created_at'])); ?>
                                            </small>
                                        </div>
                                        
                                        <div class="mt-3">
                                            <a href="?action=watch&id=<?php echo $video['id']; ?>" class="btn btn-primary btn-sm me-2">
                                                <i class="fas fa-play"></i> تماشا
                                            </a>
                                            <?php if ($is_admin): ?>
                                                <a href="?action=delete&id=<?php echo $video['id']; ?>" 
                                                   class="btn btn-danger btn-sm"
                                                   onclick="return confirm('آیا از حذف این ویدیو اطمینان دارید؟')">
                                                    <i class="fas fa-trash"></i> حذف
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // مدیریت آپلود فایل
        const uploadZone = document.getElementById('uploadZone');
        const fileInput = document.getElementById('videoFile');
        const uploadProgress = document.getElementById('uploadProgress');
        const progressFill = document.getElementById('progressFill');
        const progressText = document.getElementById('progressText');
        const uploadBtn = document.getElementById('uploadBtn');
        
        if (uploadZone && fileInput) {
            uploadZone.addEventListener('click', () => fileInput.click());
            
            fileInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    const file = this.files[0];
                    const fileSize = (file.size / (1024 * 1024)).toFixed(2);
                    
                    if (file.size > 100 * 1024 * 1024) {
                        alert('حجم فایل بیش از حد مجاز است (حداکثر 100MB)');
                        this.value = '';
                        return;
                    }
                    
                    uploadZone.innerHTML = `
                        <i class="fas fa-check-circle fa-2x mb-3 text-success"></i>
                        <p class="text-success">فایل انتخاب شد: ${file.name}</p>
                        <small class="text-muted">حجم: ${fileSize} MB</small>
                    `;
                    uploadZone.classList.add('border-success');
                }
            });
            
            // Drag & Drop
            uploadZone.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('dragover');
            });
            
            uploadZone.addEventListener('dragleave', function() {
                this.classList.remove('dragover');
            });
            
            uploadZone.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    const file = files[0];
                    
                    if (file.size > 100 * 1024 * 1024) {
                        alert('حجم فایل بیش از حد مجاز است (حداکثر 100MB)');
                        return;
                    }
                    
                    fileInput.files = files;
                    const fileSize = (file.size / (1024 * 1024)).toFixed(2);
                    
                    this.innerHTML = `
                        <i class="fas fa-check-circle fa-2x mb-3 text-success"></i>
                        <p class="text-success">فایل انتخاب شد: ${file.name}</p>
                        <small class="text-muted">حجم: ${fileSize} MB</small>
                    `;
                    this.classList.add('border-success');
                }
            });
        }
        
        // شبیه‌سازی progress bar برای آپلود
        if (uploadBtn) {
            uploadBtn.addEventListener('click', function(e) {
                if (fileInput && fileInput.files.length > 0) {
                    // نمایش progress bar
                    uploadProgress.classList.remove('d-none');
                    
                    let progress = 0;
                    const interval = setInterval(() => {
                        progress += Math.random() * 10;
                        if (progress >= 95) {
                            progress = 95;
                            clearInterval(interval);
                        }
                        
                        progressFill.style.width = progress + '%';
                        progressText.textContent = Math.round(progress) + '%';
                    }, 200);
                }
            });
        }
        
        // انیمیشن ظاهر شدن ویدیوها
        const videoItems = document.querySelectorAll('.video-item');
        videoItems.forEach((item, index) => {
            item.style.opacity = '0';
            item.style.transform = 'translateY(30px)';
            
            setTimeout(() => {
                item.style.transition = 'all 0.6s ease';
                item.style.opacity = '1';
                item.style.transform = 'translateY(0)';
            }, index * 100);
        });
        
        // مدیریت پخش ویدیو
        const videoPlayer = document.querySelector('.video-player');
        if (videoPlayer) {
            videoPlayer.addEventListener('loadedmetadata', function() {
                console.log('ویدیو بارگذاری شد');
            });
            
            videoPlayer.addEventListener('error', function() {
                console.error('خطا در بارگذاری ویدیو');
                this.parentElement.innerHTML = `
                    <div class="text-center text-white p-5">
                        <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                        <h5>خطا در بارگذاری ویدیو</h5>
                        <p>لطفاً دوباره تلاش کنید</p>
                    </div>
                `;
            });
        }
    });
</script>

</body>
</html>