<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'ادمین') {
    header('Location: training.php');
    exit();
}

include 'config.php';

// تابع ایجاد thumbnail
function createThumbnail($source, $destination, $width = 300, $height = 300) {
    $info = @getimagesize($source);
    if (!$info || empty($info['mime'])) {
        return false;
    }
    $mime = $info['mime'];

    // بررسی وجود GD
    if (!function_exists('imagecreatetruecolor')) {
        // اگر GD در دسترس نیست، به جای ساخت thumbnail، یک کپی ساده می‌گیریم
        return @copy($source, $destination);
    }

    $image = null;
    if ($mime === 'image/jpeg' && function_exists('imagecreatefromjpeg')) {
        $image = @imagecreatefromjpeg($source);
    } elseif ($mime === 'image/png' && function_exists('imagecreatefrompng')) {
        $image = @imagecreatefrompng($source);
    } elseif ($mime === 'image/gif' && function_exists('imagecreatefromgif')) {
        $image = @imagecreatefromgif($source);
    } elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
        $image = @imagecreatefromwebp($source);
    } else {
        // mime ناشناخته یا تابع مربوط در دسترس نیست
        return @copy($source, $destination);
    }
    if (!$image) {
        return @copy($source, $destination);
    }

    $orig_width = imagesx($image);
    $orig_height = imagesy($image);
    if ($orig_width <= 0 || $orig_height <= 0) {
        imagedestroy($image);
        return @copy($source, $destination);
    }

    $ratio = min($width / $orig_width, $height / $orig_height);
    $new_width = max(1, (int)round($orig_width * $ratio));
    $new_height = max(1, (int)round($orig_height * $ratio));

    $thumb = imagecreatetruecolor($new_width, $new_height);
    if ($mime === 'image/png') {
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
    }

    imagecopyresampled($thumb, $image, 0, 0, 0, 0, $new_width, $new_height, $orig_width, $orig_height);

    $saved = false;
    if ($mime === 'image/jpeg' && function_exists('imagejpeg')) {
        $saved = @imagejpeg($thumb, $destination, 85);
    } elseif ($mime === 'image/png' && function_exists('imagepng')) {
        $saved = @imagepng($thumb, $destination, 8);
    } elseif ($mime === 'image/gif' && function_exists('imagegif')) {
        $saved = @imagegif($thumb, $destination);
    } elseif ($mime === 'image/webp' && function_exists('imagewebp')) {
        $saved = @imagewebp($thumb, $destination, 85);
    }

    imagedestroy($image);
    imagedestroy($thumb);
    return $saved ? true : @copy($source, $destination);
}

// پردازش آپلود تصویر
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'upload' && isset($_FILES['images'])) {
        try {
            $title = sanitizeInput($_POST['title']);
            $description = sanitizeInput($_POST['description']);
            $category_id = $_POST['category_id'] ?: null;
            
            $upload_dir = __DIR__ . '/uploads/training/gallery/';
            $thumb_dir = __DIR__ . '/uploads/training/gallery/thumbnails/';
            
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            if (!is_dir($thumb_dir)) mkdir($thumb_dir, 0755, true);
            
            $uploaded_count = 0;
            
            foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['images']['error'][$key] !== UPLOAD_ERR_OK) continue;
                
                $file_name = $_FILES['images']['name'][$key];
                $file_size = $_FILES['images']['size'][$key];
                $file_tmp = $_FILES['images']['tmp_name'][$key];
                
                // بررسی نوع فایل
                $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                if (!in_array($file_ext, $allowed_types)) continue;
                // بدون محدودیت حجمی در سطح برنامه — محدودیت‌ها توسط PHP/وب‌سرور کنترل می‌شود
                
                // دریافت ابعاد تصویر
                $image_info = getimagesize($file_tmp);
                if (!$image_info) continue;
                
                $width = $image_info[0];
                $height = $image_info[1];
                
                // تولید نام یکتا
                $new_name = time() . '_' . uniqid() . '.' . $file_ext;
                $file_path = $upload_dir . $new_name;
                $thumb_path = $thumb_dir . $new_name;
                
                // انتقال فایل
                if (move_uploaded_file($file_tmp, $file_path)) {
                    // ایجاد thumbnail
                    createThumbnail($file_path, $thumb_path, 300, 300);
                    
                    // ذخیره در دیتابیس
                    $stmt = $pdo->prepare("
                        INSERT INTO training_gallery 
                        (title, description, image_path, thumbnail_path, file_size, width, height, category_id, uploaded_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $image_title = $title ?: pathinfo($file_name, PATHINFO_FILENAME);
                    
                    $stmt->execute([
                        $image_title,
                        $description,
                        'uploads/training/gallery/' . $new_name,
                        'uploads/training/gallery/thumbnails/' . $new_name,
                        $file_size,
                        $width,
                        $height,
                        $category_id,
                        $_SESSION['user_id']
                    ]);
                    
                    $uploaded_count++;
                }
            }
            
            $_SESSION['success_message'] = "$uploaded_count تصویر با موفقیت آپلود شد.";
            header('Location: training_gallery_admin.php');
            exit();
            
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'خطا در آپلود: ' . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'delete' && isset($_POST['image_id'])) {
        try {
            $image_id = (int)$_POST['image_id'];
            
            // دریافت مسیر فایل‌ها
            $stmt = $pdo->prepare("SELECT image_path, thumbnail_path FROM training_gallery WHERE id = ?");
            $stmt->execute([$image_id]);
            $image = $stmt->fetch();
            
            if ($image) {
                // حذف فایل‌های فیزیکی
                $image_path = __DIR__ . '/' . $image['image_path'];
                $thumb_path = __DIR__ . '/' . $image['thumbnail_path'];
                
                if (file_exists($image_path)) unlink($image_path);
                if (file_exists($thumb_path)) unlink($thumb_path);
                
                // حذف از دیتابیس
                $stmt = $pdo->prepare("DELETE FROM training_gallery WHERE id = ?");
                $stmt->execute([$image_id]);
                
                $_SESSION['success_message'] = 'تصویر با موفقیت حذف شد.';
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'خطا در حذف تصویر: ' . $e->getMessage();
        }
        
        header('Location: training_gallery_admin.php');
        exit();
    } elseif ($_POST['action'] === 'toggle_status' && isset($_POST['image_id'])) {
        $image_id = (int)$_POST['image_id'];
        $stmt = $pdo->prepare("UPDATE training_gallery SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$image_id]);
        
        header('Location: training_gallery_admin.php');
        exit();
    }
}

// دریافت لیست تصاویر
$images = $pdo->query("
    SELECT g.*, c.name as category_name, u.full_name as uploader_name
    FROM training_gallery g
    LEFT JOIN training_categories c ON g.category_id = c.id
    LEFT JOIN users u ON g.uploaded_by = u.id
    ORDER BY g.created_at DESC
")->fetchAll();

// دریافت دسته‌بندی‌ها
$categories = $pdo->query("SELECT * FROM training_categories WHERE is_active = 1 ORDER BY sort_order")->fetchAll();
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت گالری تصاویر - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .upload-zone {
            border: 3px dashed #dee2e6;
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            background: linear-gradient(45deg, #f8f9fa 25%, transparent 25%, transparent 75%, #f8f9fa 75%, #f8f9fa),
                        linear-gradient(45deg, #f8f9fa 25%, transparent 25%, transparent 75%, #f8f9fa 75%, #f8f9fa);
            background-size: 20px 20px;
            background-position: 0 0, 10px 10px;
            transition: all 0.3s ease;
        }
        
        .upload-zone:hover, .upload-zone.dragover {
            border-color: #0d6efd;
            background-color: #e7f1ff;
        }
        
        .preview-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 20px;
        }
        
        .preview-item {
            position: relative;
            width: 150px;
            height: 150px;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .preview-item .remove-btn {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(220, 53, 69, 0.9);
            color: white;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            cursor: pointer;
        }
        
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .gallery-item {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .gallery-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .gallery-item img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        
        .gallery-item-info {
            padding: 15px;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .image-modal img {
            max-width: 100%;
            height: auto;
        }
        
        .badge-status {
            position: absolute;
            top: 10px;
            left: 10px;
            z-index: 1;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="fas fa-images"></i> مدیریت گالری تصاویر</h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">داشبورد</a></li>
                        <li class="breadcrumb-item"><a href="training.php">آموزش</a></li>
                        <li class="breadcrumb-item active">مدیریت گالری</li>
                    </ol>
                </nav>
            </div>
            <a href="training_gallery.php" class="btn btn-outline-primary">
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
                    <h3><?php echo count($images); ?></h3>
                    <p class="mb-0">کل تصاویر</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <h3><?php echo count(array_filter($images, function($i) { return $i['is_active']; })); ?></h3>
                    <p class="mb-0">تصاویر فعال</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <h3><?php echo array_sum(array_column($images, 'view_count')); ?></h3>
                    <p class="mb-0">کل بازدیدها</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <h3><?php echo round(array_sum(array_column($images, 'file_size')) / (1024 * 1024), 2); ?> MB</h3>
                    <p class="mb-0">حجم کل</p>
                </div>
            </div>
        </div>

        <!-- فرم آپلود -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-upload"></i> آپلود تصاویر جدید</h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <input type="hidden" name="action" value="upload">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="title" class="form-label">عنوان تصاویر</label>
                                <input type="text" class="form-control" id="title" name="title">
                                <small class="text-muted">اگر خالی بگذارید، از نام فایل استفاده می‌شود</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="category_id" class="form-label">دسته‌بندی</label>
                                <select class="form-select" id="category_id" name="category_id">
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
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="upload-zone" id="uploadZone">
                                <i class="fas fa-cloud-upload-alt fa-4x mb-3 text-muted"></i>
                                <h5>تصاویر را اینجا رها کنید</h5>
                                <p class="text-muted">یا کلیک کنید برای انتخاب فایل‌ها</p>
                                <input type="file" class="d-none" id="images" name="images[]" 
                                       accept="image/*" multiple required>
                                <button type="button" class="btn btn-outline-primary" 
                                        onclick="document.getElementById('images').click()">
                                    <i class="fas fa-folder-open"></i> انتخاب تصاویر
                                </button>
                                <p class="text-muted mt-2">
                                    <small>فرمت‌های مجاز: JPG, PNG, GIF, WebP | حداکثر حجم: 5MB</small>
                                </p>
                            </div>
                            
                            <div class="preview-container" id="previewContainer"></div>
                        </div>
                    </div>
                    
                    <div class="text-end mt-3">
                        <button type="submit" class="btn btn-success" id="uploadBtn" disabled>
                            <i class="fas fa-upload"></i> آپلود تصاویر
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- لیست تصاویر -->
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fas fa-th"></i> گالری تصاویر</h5>
            </div>
            <div class="card-body">
                <div class="gallery-grid">
                    <?php foreach ($images as $image): ?>
                        <div class="gallery-item position-relative">
                            <?php if (!$image['is_active']): ?>
                                <span class="badge bg-danger badge-status">غیرفعال</span>
                            <?php endif; ?>
                            
                            <img src="<?php echo $image['thumbnail_path'] ?: $image['image_path']; ?>" 
                                 alt="<?php echo htmlspecialchars($image['title']); ?>"
                                 data-bs-toggle="modal" data-bs-target="#imageModal<?php echo $image['id']; ?>"
                                 style="cursor: pointer;">
                            
                            <div class="gallery-item-info">
                                <h6 class="mb-1"><?php echo htmlspecialchars($image['title']); ?></h6>
                                <small class="text-muted d-block">
                                    <i class="fas fa-eye"></i> <?php echo $image['view_count']; ?> |
                                    <i class="fas fa-download"></i> <?php echo $image['download_count']; ?>
                                </small>
                                <small class="text-muted d-block">
                                    <?php echo $image['width']; ?>×<?php echo $image['height']; ?> |
                                    <?php echo round($image['file_size'] / 1024, 2); ?> KB
                                </small>
                                
                                <div class="btn-group btn-group-sm mt-2 w-100">
                                    <a href="<?php echo $image['image_path']; ?>" class="btn btn-info" download>
                                        <i class="fas fa-download"></i>
                                    </a>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="image_id" value="<?php echo $image['id']; ?>">
                                        <button type="submit" class="btn btn-warning">
                                            <i class="fas fa-toggle-<?php echo $image['is_active'] ? 'on' : 'off'; ?>"></i>
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;" 
                                          onsubmit="return confirm('آیا از حذف این تصویر اطمینان دارید؟');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="image_id" value="<?php echo $image['id']; ?>">
                                        <button type="submit" class="btn btn-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Modal برای نمایش تصویر بزرگ -->
                        <div class="modal fade" id="imageModal<?php echo $image['id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title"><?php echo htmlspecialchars($image['title']); ?></h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body text-center image-modal">
                                        <img src="<?php echo $image['image_path']; ?>" 
                                             alt="<?php echo htmlspecialchars($image['title']); ?>">
                                        <?php if ($image['description']): ?>
                                            <p class="mt-3"><?php echo nl2br(htmlspecialchars($image['description'])); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Drag and Drop
        const uploadZone = document.getElementById('uploadZone');
        const fileInput = document.getElementById('images');
        const previewContainer = document.getElementById('previewContainer');
        const uploadBtn = document.getElementById('uploadBtn');
        let selectedFiles = [];
        
        uploadZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadZone.classList.add('dragover');
        });
        
        uploadZone.addEventListener('dragleave', () => {
            uploadZone.classList.remove('dragover');
        });
        
        uploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadZone.classList.remove('dragover');
            handleFiles(e.dataTransfer.files);
        });
        
        fileInput.addEventListener('change', (e) => {
            handleFiles(e.target.files);
        });
        
        function handleFiles(files) {
            selectedFiles = Array.from(files);
            previewContainer.innerHTML = '';
            uploadBtn.disabled = selectedFiles.length === 0;
            
            selectedFiles.forEach((file, index) => {
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        const div = document.createElement('div');
                        div.className = 'preview-item';
                        div.innerHTML = `
                            <img src="${e.target.result}" alt="${file.name}">
                            <button type="button" class="remove-btn" onclick="removeFile(${index})">
                                <i class="fas fa-times"></i>
                            </button>
                        `;
                        previewContainer.appendChild(div);
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
        
        function removeFile(index) {
            selectedFiles.splice(index, 1);
            
            const dt = new DataTransfer();
            selectedFiles.forEach(file => dt.items.add(file));
            fileInput.files = dt.files;
            
            handleFiles(dt.files);
        }
    </script>
</body>
</html>