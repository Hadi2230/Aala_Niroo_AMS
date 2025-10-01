<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';
include 'navbar.php';

$is_admin = ($_SESSION['role'] ?? '') === 'ادمین';
$action = $_GET['action'] ?? 'gallery';

// تابع ایجاد thumbnail
function createThumbnail($source, $destination, $width = 300, $height = 300) {
    $info = getimagesize($source);
    if (!$info) return false;
    
    $mime = $info['mime'];
    
    switch ($mime) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($source);
            break;
        case 'image/png':
            $image = imagecreatefrompng($source);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($source);
            break;
        default:
            return false;
    }
    
    $original_width = imagesx($image);
    $original_height = imagesy($image);
    
    $ratio = min($width / $original_width, $height / $original_height);
    $new_width = $original_width * $ratio;
    $new_height = $original_height * $ratio;
    
    $thumbnail = imagecreatetruecolor($new_width, $new_height);
    
    if ($mime == 'image/png' || $mime == 'image/gif') {
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
        $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
        imagefilledrectangle($thumbnail, 0, 0, $new_width, $new_height, $transparent);
    }
    
    imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $new_width, $new_height, $original_width, $original_height);
    
    switch ($mime) {
        case 'image/jpeg':
            imagejpeg($thumbnail, $destination, 85);
            break;
        case 'image/png':
            imagepng($thumbnail, $destination);
            break;
        case 'image/gif':
            imagegif($thumbnail, $destination);
            break;
    }
    
    imagedestroy($image);
    imagedestroy($thumbnail);
    return true;
}

// پردازش آپلود تصویر جدید
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'upload' && $is_admin) {
    verifyCsrfToken();
    
    try {
        $title = sanitizeInput($_POST['title']);
        $description = sanitizeInput($_POST['description']);
        $category_id = (int)$_POST['category_id'];
        
        if (empty($title) || !isset($_FILES['image_file'])) {
            throw new Exception('لطفاً تمام فیلدهای الزامی را پر کنید');
        }
        
        // آپلود تصویر
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        $file_path = uploadFile($_FILES['image_file'], __DIR__ . '/uploads/learning/images/', $allowed_types);
        
        // ایجاد thumbnail
        $thumbnail_name = 'thumb_' . basename($file_path);
        $thumbnail_path = __DIR__ . '/uploads/learning/images/thumbnails/' . $thumbnail_name;
        createThumbnail($file_path, $thumbnail_path);
        
        // ذخیره در دیتابیس
        $stmt = $pdo->prepare("INSERT INTO image_gallery (title, description, file_name, file_path, thumbnail_path, file_size, category_id, uploaded_by) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $title,
            $description,
            $_FILES['image_file']['name'],
            $file_path,
            'uploads/learning/images/thumbnails/' . $thumbnail_name,
            $_FILES['image_file']['size'],
            $category_id ?: null,
            $_SESSION['user_id']
        ]);
        
        logAction($pdo, 'image_upload', "آپلود تصویر: $title");
        $success_message = 'تصویر با موفقیت آپلود شد';
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// پردازش دانلود تصویر
if ($action === 'download' && isset($_GET['id'])) {
    $image_id = (int)$_GET['id'];
    
    $stmt = $pdo->prepare("SELECT * FROM image_gallery WHERE id = ? AND is_active = 1");
    $stmt->execute([$image_id]);
    $image = $stmt->fetch();
    
    if ($image && file_exists($image['file_path'])) {
        // افزایش شمارنده دانلود
        $pdo->prepare("UPDATE image_gallery SET download_count = download_count + 1 WHERE id = ?")->execute([$image_id]);
        
        // ارسال فایل
        header('Content-Type: ' . mime_content_type($image['file_path']));
        header('Content-Disposition: attachment; filename="' . $image['file_name'] . '"');
        header('Content-Length: ' . filesize($image['file_path']));
        readfile($image['file_path']);
        exit();
    }
}

// پردازش مشاهده تصویر
if ($action === 'view' && isset($_GET['id'])) {
    $image_id = (int)$_GET['id'];
    
    $stmt = $pdo->prepare("SELECT * FROM image_gallery WHERE id = ? AND is_active = 1");
    $stmt->execute([$image_id]);
    $image = $stmt->fetch();
    
    if ($image) {
        // افزایش شمارنده مشاهده
        $pdo->prepare("UPDATE image_gallery SET view_count = view_count + 1 WHERE id = ?")->execute([$image_id]);
    }
}

// حذف تصویر (فقط ادمین)
if ($action === 'delete' && isset($_GET['id']) && $is_admin) {
    $image_id = (int)$_GET['id'];
    
    $stmt = $pdo->prepare("SELECT * FROM image_gallery WHERE id = ?");
    $stmt->execute([$image_id]);
    $image = $stmt->fetch();
    
    if ($image) {
        // حذف فایل‌ها
        if (file_exists($image['file_path'])) {
            unlink($image['file_path']);
        }
        if ($image['thumbnail_path'] && file_exists($image['thumbnail_path'])) {
            unlink($image['thumbnail_path']);
        }
        
        // حذف از دیتابیس
        $pdo->prepare("DELETE FROM image_gallery WHERE id = ?")->execute([$image_id]);
        
        logAction($pdo, 'image_delete', "حذف تصویر: " . $image['title']);
        $success_message = 'تصویر با موفقیت حذف شد';
    }
}

// دریافت دسته‌بندی‌ها
$categories = $pdo->query("SELECT * FROM learning_categories WHERE type = 'images' ORDER BY name")->fetchAll();

// دریافت تصاویر
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

$stmt = $pdo->prepare("SELECT ig.*, lc.name as category_name, u.username as uploader_name 
                      FROM image_gallery ig 
                      LEFT JOIN learning_categories lc ON ig.category_id = lc.id 
                      LEFT JOIN users u ON ig.uploaded_by = u.id 
                      WHERE $where_clause 
                      ORDER BY ig.created_at DESC");
$stmt->execute($params);
$images = $stmt->fetchAll();
?>

<style>
    .gallery-container {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        min-height: 100vh;
        padding-top: 2rem;
    }
    
    .gallery-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 20px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        overflow: hidden;
    }
    
    .image-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1.5rem;
        padding: 1rem;
    }
    
    .image-item {
        background: white;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        position: relative;
    }
    
    .image-item:hover {
        transform: translateY(-10px);
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
    }
    
    .image-preview {
        width: 100%;
        height: 200px;
        object-fit: cover;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .image-preview:hover {
        transform: scale(1.05);
    }
    
    .image-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.7);
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    .image-item:hover .image-overlay {
        opacity: 1;
    }
    
    .overlay-buttons {
        display: flex;
        gap: 1rem;
    }
    
    .overlay-btn {
        background: rgba(255, 255, 255, 0.9);
        border: none;
        border-radius: 50%;
        width: 50px;
        height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
        color: #333;
    }
    
    .overlay-btn:hover {
        background: white;
        transform: scale(1.1);
    }
    
    .image-info {
        padding: 1rem;
    }
    
    .image-title {
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: #333;
    }
    
    .image-description {
        color: #666;
        font-size: 0.9rem;
        margin-bottom: 1rem;
    }
    
    .image-meta {
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
    
    .stats-info {
        display: flex;
        gap: 1rem;
    }
    
    .upload-zone {
        border: 2px dashed #f093fb;
        border-radius: 15px;
        padding: 3rem;
        text-align: center;
        background: rgba(240, 147, 251, 0.1);
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    .upload-zone:hover {
        border-color: #f5576c;
        background: rgba(245, 87, 108, 0.1);
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
    
    /* Modal styles */
    .image-modal .modal-dialog {
        max-width: 90vw;
        max-height: 90vh;
    }
    
    .image-modal .modal-content {
        background: rgba(0, 0, 0, 0.9);
        border: none;
        border-radius: 15px;
    }
    
    .image-modal .modal-body {
        padding: 0;
        text-align: center;
    }
    
    .modal-image {
        max-width: 100%;
        max-height: 80vh;
        object-fit: contain;
    }
    
    .image-modal .btn-close {
        background: white;
        border-radius: 50%;
        opacity: 1;
        position: absolute;
        top: 1rem;
        right: 1rem;
        z-index: 1000;
    }
</style>

<div class="gallery-container">
    <div class="container">
        <!-- هدر صفحه -->
        <div class="text-center mb-5">
            <h1 class="text-white display-5 mb-3">
                <i class="fas fa-images"></i>
                گالری تصاویر
            </h1>
            <p class="text-white-50 lead">مجموعه کامل تصاویر محصولات و مطالب آموزشی</p>
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

        <!-- جستجو و فیلتر -->
        <div class="search-box">
            <form method="GET" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">جستجو در تصاویر</label>
                    <input type="text" name="search" class="form-control" placeholder="نام تصویر یا توضیحات..." value="<?php echo htmlspecialchars($search); ?>">
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
                        <i class="fas fa-plus"></i> آپلود تصویر جدید
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($action === 'upload' && $is_admin): ?>
            <!-- فرم آپلود -->
            <div class="gallery-card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-upload"></i>
                        آپلود تصویر جدید
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" class="row g-3">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div class="col-md-6">
                            <label class="form-label">عنوان تصویر *</label>
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
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">فایل تصویر *</label>
                            <div class="upload-zone" id="uploadZone">
                                <i class="fas fa-camera fa-2x mb-3 text-primary"></i>
                                <p>تصویر را اینجا بکشید یا کلیک کنید</p>
                                <small class="text-muted">فرمت‌های مجاز: JPG, PNG, GIF</small>
                                <input type="file" name="image_file" id="imageFile" class="d-none" accept="image/*" required>
                            </div>
                            <div id="imagePreview" class="mt-3 d-none">
                                <img id="previewImg" class="img-fluid rounded" style="max-height: 200px;">
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <button type="submit" class="btn btn-success me-2">
                                <i class="fas fa-upload"></i> آپلود تصویر
                            </button>
                            <a href="?" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> بازگشت
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- گالری تصاویر -->
        <div class="gallery-card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-images"></i>
                    گالری تصاویر (<?php echo count($images); ?>)
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($images)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-images fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">هیچ تصویری یافت نشد</h5>
                        <p class="text-muted">
                            <?php if ($is_admin): ?>
                                برای شروع، اولین تصویر را آپلود کنید
                            <?php else: ?>
                                در حال حاضر تصویری برای نمایش وجود ندارد
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="image-grid">
                        <?php foreach ($images as $image): ?>
                            <div class="image-item">
                                <div class="position-relative">
                                    <img src="<?php echo htmlspecialchars($image['thumbnail_path'] ?: $image['file_path']); ?>" 
                                         alt="<?php echo htmlspecialchars($image['title']); ?>" 
                                         class="image-preview"
                                         data-bs-toggle="modal" 
                                         data-bs-target="#imageModal"
                                         data-image="<?php echo htmlspecialchars($image['file_path']); ?>"
                                         data-title="<?php echo htmlspecialchars($image['title']); ?>"
                                         data-id="<?php echo $image['id']; ?>">
                                    
                                    <div class="image-overlay">
                                        <div class="overlay-buttons">
                                            <button class="overlay-btn" title="مشاهده" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#imageModal"
                                                    data-image="<?php echo htmlspecialchars($image['file_path']); ?>"
                                                    data-title="<?php echo htmlspecialchars($image['title']); ?>"
                                                    data-id="<?php echo $image['id']; ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <a href="?action=download&id=<?php echo $image['id']; ?>" class="overlay-btn" title="دانلود">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <?php if ($is_admin): ?>
                                                <a href="?action=delete&id=<?php echo $image['id']; ?>" 
                                                   class="overlay-btn text-danger" 
                                                   title="حذف"
                                                   onclick="return confirm('آیا از حذف این تصویر اطمینان دارید؟')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="image-info">
                                    <h6 class="image-title"><?php echo htmlspecialchars($image['title']); ?></h6>
                                    <?php if ($image['description']): ?>
                                        <p class="image-description"><?php echo htmlspecialchars($image['description']); ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <?php if ($image['category_name']): ?>
                                            <span class="category-badge">
                                                <i class="fas fa-tag"></i> <?php echo htmlspecialchars($image['category_name']); ?>
                                            </span>
                                        <?php endif; ?>
                                        
                                        <div class="stats-info">
                                            <small class="text-muted">
                                                <i class="fas fa-eye"></i> <?php echo $image['view_count']; ?>
                                            </small>
                                            <small class="text-muted">
                                                <i class="fas fa-download"></i> <?php echo $image['download_count']; ?>
                                            </small>
                                        </div>
                                    </div>
                                    
                                    <div class="image-meta">
                                        <small>
                                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($image['uploader_name']); ?>
                                        </small>
                                        <small>
                                            <i class="fas fa-calendar"></i> <?php echo date('Y/m/d', strtotime($image['created_at'])); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal مشاهده تصویر -->
<div class="modal fade image-modal" id="imageModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            <div class="modal-body">
                <img id="modalImage" class="modal-image" src="" alt="">
                <div class="text-white p-3">
                    <h5 id="modalTitle"></h5>
                    <div class="d-flex justify-content-center gap-3 mt-3">
                        <a id="modalDownload" href="#" class="btn btn-success">
                            <i class="fas fa-download"></i> دانلود
                        </a>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> بستن
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // مدیریت آپلود فایل
        const uploadZone = document.getElementById('uploadZone');
        const fileInput = document.getElementById('imageFile');
        const imagePreview = document.getElementById('imagePreview');
        const previewImg = document.getElementById('previewImg');
        
        if (uploadZone && fileInput) {
            uploadZone.addEventListener('click', () => fileInput.click());
            
            fileInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    const file = this.files[0];
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        previewImg.src = e.target.result;
                        imagePreview.classList.remove('d-none');
                        uploadZone.classList.add('border-success');
                    };
                    
                    reader.readAsDataURL(file);
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
                    fileInput.files = files;
                    const file = files[0];
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        previewImg.src = e.target.result;
                        imagePreview.classList.remove('d-none');
                        uploadZone.classList.add('border-success');
                    };
                    
                    reader.readAsDataURL(file);
                }
            });
        }
        
        // مدیریت modal تصاویر
        const imageModal = document.getElementById('imageModal');
        const modalImage = document.getElementById('modalImage');
        const modalTitle = document.getElementById('modalTitle');
        const modalDownload = document.getElementById('modalDownload');
        
        document.addEventListener('click', function(e) {
            if (e.target.hasAttribute('data-bs-target') && e.target.getAttribute('data-bs-target') === '#imageModal') {
                const imageSrc = e.target.getAttribute('data-image');
                const imageTitle = e.target.getAttribute('data-title');
                const imageId = e.target.getAttribute('data-id');
                
                modalImage.src = imageSrc;
                modalTitle.textContent = imageTitle;
                modalDownload.href = `?action=download&id=${imageId}`;
                
                // ثبت مشاهده
                fetch(`?action=view&id=${imageId}`);
            }
        });
        
        // انیمیشن ظاهر شدن تصاویر
        const imageItems = document.querySelectorAll('.image-item');
        imageItems.forEach((item, index) => {
            item.style.opacity = '0';
            item.style.transform = 'translateY(30px)';
            
            setTimeout(() => {
                item.style.transition = 'all 0.6s ease';
                item.style.opacity = '1';
                item.style.transform = 'translateY(0)';
            }, index * 100);
        });
    });
</script>

</body>
</html>