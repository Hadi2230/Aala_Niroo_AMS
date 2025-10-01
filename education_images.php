<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

$is_admin = ($_SESSION['role'] === 'ادمین');

// دریافت تصاویر
$search = $_GET['search'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = 12;
$offset = ($page - 1) * $limit;

$where_clause = "WHERE is_active = 1";
$params = [];

if (!empty($search)) {
    $where_clause .= " AND (title LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$count_sql = "SELECT COUNT(*) as total FROM education_images $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_images = $count_stmt->fetch()['total'];
$total_pages = ceil($total_images / $limit);

$sql = "SELECT ei.*, u.username as uploaded_by_name 
        FROM education_images ei 
        LEFT JOIN users u ON ei.uploaded_by = u.id 
        $where_clause 
        ORDER BY ei.created_at DESC 
        LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$images = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تصاویر آموزشی - سامانه مدیریت اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .gallery-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 0;
            margin-bottom: 40px;
        }
        
        .gallery-item {
            position: relative;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            margin-bottom: 30px;
        }
        
        .gallery-item:hover {
            transform: scale(1.05);
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
        }
        
        .gallery-item img {
            width: 100%;
            height: 250px;
            object-fit: cover;
        }
        
        .gallery-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.8));
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 20px;
            opacity: 0;
            transition: all 0.3s ease;
        }
        
        .gallery-item:hover .gallery-overlay {
            opacity: 1;
        }
        
        .gallery-overlay h6 {
            color: white;
            margin: 0 0 10px 0;
            font-weight: bold;
        }
        
        .gallery-overlay p {
            color: rgba(255,255,255,0.8);
            margin: 0 0 15px 0;
            font-size: 0.9rem;
        }
        
        .gallery-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-gallery {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .btn-gallery:hover {
            background: rgba(255,255,255,0.3);
            color: white;
            transform: translateY(-2px);
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
        
        .image-meta {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="gallery-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="display-5 fw-bold mb-3">
                        <i class="fas fa-images me-3"></i>
                        تصاویر آموزشی
                    </h1>
                    <p class="lead mb-0">
                        گالری تصاویر آموزشی و مستندات تصویری شرکت اعلا نیرو
                    </p>
                </div>
                <div class="col-lg-4 text-center">
                    <i class="fas fa-camera" style="font-size: 6rem; opacity: 0.3;"></i>
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
                               placeholder="جستجو در تصاویر...">
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
                    تعداد کل تصاویر: <strong><?php echo $total_images; ?></strong>
                    <?php if (!empty($search)): ?>
                    - نتایج جستجو برای: <strong>"<?php echo htmlspecialchars($search); ?>"</strong>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- گالری تصاویر -->
        <?php if (empty($images)): ?>
        <div class="empty-state">
            <i class="fas fa-images"></i>
            <h4>هیچ تصویری یافت نشد</h4>
            <p>
                <?php if (!empty($search)): ?>
                نتیجه‌ای برای جستجوی شما یافت نشد. لطفاً کلمات کلیدی دیگری امتحان کنید.
                <?php else: ?>
                هنوز تصویر آموزشی آپلود نشده است.
                <?php endif; ?>
            </p>
        </div>
        <?php else: ?>
        <div class="row">
            <?php foreach ($images as $image): ?>
            <div class="col-lg-4 col-md-6">
                <div class="gallery-item">
                    <img src="<?php echo $image['thumbnail_path'] ?: $image['image_path']; ?>" 
                         alt="<?php echo htmlspecialchars($image['title']); ?>"
                         data-full-src="<?php echo $image['image_path']; ?>">
                    
                    <div class="image-meta">
                        <i class="fas fa-eye me-1"></i>
                        <?php echo $image['view_count']; ?>
                        <i class="fas fa-download ms-2 me-1"></i>
                        <?php echo $image['download_count']; ?>
                    </div>
                    
                    <div class="gallery-overlay">
                        <h6><?php echo htmlspecialchars($image['title']); ?></h6>
                        <?php if (!empty($image['description'])): ?>
                        <p><?php echo htmlspecialchars($image['description']); ?></p>
                        <?php endif; ?>
                        <div class="gallery-actions">
                            <button class="btn btn-gallery" onclick="viewImage('<?php echo $image['image_path']; ?>', '<?php echo htmlspecialchars($image['title']); ?>')">
                                <i class="fas fa-eye me-1"></i>
                                مشاهده
                            </button>
                            <a href="download_image.php?id=<?php echo $image['id']; ?>" class="btn btn-gallery">
                                <i class="fas fa-download me-1"></i>
                                دانلود
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- صفحه‌بندی -->
        <?php if ($total_pages > 1): ?>
        <nav aria-label="صفحه‌بندی تصاویر" class="mt-5">
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

    <!-- Modal نمایش تصویر -->
    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageModalTitle">تصویر</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="imageModalImg" src="" alt="" class="img-fluid rounded">
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewImage(imagePath, title) {
            document.getElementById('imageModalImg').src = imagePath;
            document.getElementById('imageModalTitle').textContent = title;
            new bootstrap.Modal(document.getElementById('imageModal')).show();
        }
        
        // افزایش view count هنگام کلیک روی تصویر
        document.querySelectorAll('.gallery-item img').forEach(img => {
            img.addEventListener('click', function() {
                const imageId = this.closest('.gallery-item').querySelector('a[href*="download_image.php"]').href.split('id=')[1];
                fetch('view_image.php?id=' + imageId, {method: 'POST'});
            });
        });
    </script>
</body>
</html>