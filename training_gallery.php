<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// پردازش دانلود
if (isset($_GET['download']) && is_numeric($_GET['download'])) {
    $image_id = (int)$_GET['download'];
    
    // افزایش شمارنده دانلود
    $stmt = $pdo->prepare("UPDATE training_gallery SET download_count = download_count + 1 WHERE id = ? AND is_active = 1");
    $stmt->execute([$image_id]);
    
    // دریافت اطلاعات فایل
    $stmt = $pdo->prepare("SELECT image_path, title FROM training_gallery WHERE id = ? AND is_active = 1");
    $stmt->execute([$image_id]);
    $image = $stmt->fetch();
    
    if ($image) {
        $file_path = __DIR__ . '/' . $image['image_path'];
        if (file_exists($file_path)) {
            $ext = pathinfo($file_path, PATHINFO_EXTENSION);
            $filename = $image['title'] . '.' . $ext;
            
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($file_path));
            readfile($file_path);
            exit();
        }
    }
}

// افزایش شمارنده بازدید برای تصویر خاص
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $image_id = (int)$_GET['view'];
    $stmt = $pdo->prepare("UPDATE training_gallery SET view_count = view_count + 1 WHERE id = ? AND is_active = 1");
    $stmt->execute([$image_id]);
}

// فیلتر دسته‌بندی
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : null;
$search_query = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

// ساخت کوئری
$query = "
    SELECT g.*, c.name as category_name, c.icon as category_icon, u.full_name as uploader_name
    FROM training_gallery g
    LEFT JOIN training_categories c ON g.category_id = c.id
    LEFT JOIN users u ON g.uploaded_by = u.id
    WHERE g.is_active = 1
";

$params = [];

if ($category_filter) {
    $query .= " AND g.category_id = ?";
    $params[] = $category_filter;
}

if ($search_query) {
    $query .= " AND (g.title LIKE ? OR g.description LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

$query .= " ORDER BY g.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$images = $stmt->fetchAll();

// دریافت دسته‌بندی‌ها برای فیلتر
$categories = $pdo->query("
    SELECT c.*, COUNT(g.id) as image_count 
    FROM training_categories c
    LEFT JOIN training_gallery g ON c.id = g.category_id AND g.is_active = 1
    WHERE c.is_active = 1
    GROUP BY c.id
    ORDER BY c.sort_order
")->fetchAll();
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>گالری تصاویر آموزشی - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/css/lightbox.min.css">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 40px 0;
            border-radius: 0 0 30px 30px;
            margin-bottom: 30px;
        }
        
        .filter-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .filter-btn {
            border: 1px solid #dee2e6;
            background: white;
            padding: 8px 15px;
            border-radius: 20px;
            margin: 5px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .filter-btn:hover {
            background: #f8f9fa;
            transform: translateY(-2px);
        }
        
        .filter-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: transparent;
        }
        
        .gallery-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .gallery-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            position: relative;
        }
        
        .gallery-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .gallery-card .image-wrapper {
            position: relative;
            padding-bottom: 75%;
            overflow: hidden;
            background: #f8f9fa;
        }
        
        .gallery-card img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .gallery-card:hover img {
            transform: scale(1.1);
        }
        
        .gallery-card .overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .gallery-card:hover .overlay {
            opacity: 1;
        }
        
        .overlay-buttons {
            display: flex;
            gap: 10px;
        }
        
        .overlay-btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: rgba(255,255,255,0.9);
            color: #333;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .overlay-btn:hover {
            background: white;
            transform: scale(1.1);
        }
        
        .gallery-card-info {
            padding: 15px;
        }
        
        .gallery-card-info h6 {
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .gallery-stats {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 10px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-state i {
            font-size: 5rem;
            color: #dee2e6;
            margin-bottom: 20px;
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
        
        .masonry-grid {
            column-count: 4;
            column-gap: 20px;
        }
        
        @media (max-width: 1200px) {
            .masonry-grid { column-count: 3; }
        }
        
        @media (max-width: 768px) {
            .masonry-grid { column-count: 2; }
        }
        
        @media (max-width: 480px) {
            .masonry-grid { column-count: 1; }
        }
        
        .masonry-item {
            break-inside: avoid;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <!-- Hero Section -->
    <div class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1>
                        <i class="fas fa-images"></i>
                        گالری تصاویر آموزشی
                    </h1>
                    <p class="lead mb-0">مشاهده و دانلود تصاویر آموزشی و راهنما</p>
                </div>
                <div class="col-md-4">
                    <div class="search-box">
                        <form method="GET" class="d-flex align-items-center">
                            <i class="fas fa-search text-muted me-2"></i>
                            <input type="text" name="search" placeholder="جستجوی تصویر..." 
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
        <!-- فیلترها -->
        <div class="filter-section">
            <h5 class="mb-3">
                <i class="fas fa-filter"></i>
                فیلتر دسته‌بندی
            </h5>
            <div class="d-flex flex-wrap">
                <a href="?" class="text-decoration-none">
                    <span class="filter-btn <?php echo !$category_filter ? 'active' : ''; ?>">
                        همه تصاویر (<?php echo count($images); ?>)
                    </span>
                </a>
                <?php foreach ($categories as $category): ?>
                    <a href="?category=<?php echo $category['id']; ?>" class="text-decoration-none">
                        <span class="filter-btn <?php echo $category_filter == $category['id'] ? 'active' : ''; ?>">
                            <i class="fas <?php echo $category['icon']; ?>"></i>
                            <?php echo htmlspecialchars($category['name']); ?>
                            (<?php echo $category['image_count']; ?>)
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- گالری تصاویر -->
        <?php if (count($images) > 0): ?>
            <div class="gallery-container">
                <?php foreach ($images as $image): ?>
                    <div class="gallery-card">
                        <div class="image-wrapper">
                            <img src="<?php echo $image['thumbnail_path'] ?: $image['image_path']; ?>" 
                                 alt="<?php echo htmlspecialchars($image['title']); ?>"
                                 loading="lazy">
                            <div class="overlay">
                                <div class="overlay-buttons">
                                    <a href="<?php echo $image['image_path']; ?>" 
                                       data-lightbox="gallery" 
                                       data-title="<?php echo htmlspecialchars($image['title']); ?>"
                                       class="overlay-btn"
                                       onclick="updateViewCount(<?php echo $image['id']; ?>)">
                                        <i class="fas fa-search-plus"></i>
                                    </a>
                                    <a href="?download=<?php echo $image['id']; ?>" 
                                       class="overlay-btn">
                                        <i class="fas fa-download"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="gallery-card-info">
                            <h6><?php echo htmlspecialchars($image['title']); ?></h6>
                            <?php if ($image['category_name']): ?>
                                <span class="badge bg-secondary">
                                    <i class="fas <?php echo $image['category_icon']; ?>"></i>
                                    <?php echo $image['category_name']; ?>
                                </span>
                            <?php endif; ?>
                            <div class="gallery-stats">
                                <span><i class="fas fa-eye"></i> <?php echo $image['view_count']; ?></span>
                                <span><i class="fas fa-download"></i> <?php echo $image['download_count']; ?></span>
                                <span><i class="fas fa-expand"></i> <?php echo $image['width']; ?>×<?php echo $image['height']; ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-images"></i>
                <h4>تصویری یافت نشد</h4>
                <p class="text-muted">
                    <?php if ($search_query): ?>
                        هیچ تصویری با عبارت "<?php echo htmlspecialchars($search_query); ?>" یافت نشد.
                    <?php elseif ($category_filter): ?>
                        در این دسته‌بندی تصویری وجود ندارد.
                    <?php else: ?>
                        هنوز تصویری آپلود نشده است.
                    <?php endif; ?>
                </p>
                <?php if ($search_query || $category_filter): ?>
                    <a href="training_gallery.php" class="btn btn-primary mt-3">
                        <i class="fas fa-arrow-right"></i>
                        مشاهده همه تصاویر
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/js/lightbox.min.js"></script>
    
    <script>
        // تنظیمات Lightbox
        lightbox.option({
            'resizeDuration': 200,
            'wrapAround': true,
            'albumLabel': "تصویر %1 از %2"
        });
        
        // آپدیت شمارنده بازدید
        function updateViewCount(imageId) {
            fetch('?view=' + imageId);
        }
    </script>
</body>
</html>