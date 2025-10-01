<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// افزایش شمارنده بازدید
if (isset($_GET['watch']) && is_numeric($_GET['watch'])) {
    $video_id = (int)$_GET['watch'];
    $stmt = $pdo->prepare("UPDATE training_videos SET view_count = view_count + 1 WHERE id = ? AND is_active = 1");
    $stmt->execute([$video_id]);
    
    // دریافت اطلاعات ویدیو برای نمایش
    $stmt = $pdo->prepare("
        SELECT v.*, c.name as category_name, u.full_name as uploader_name
        FROM training_videos v
        LEFT JOIN training_categories c ON v.category_id = c.id
        LEFT JOIN users u ON v.uploaded_by = u.id
        WHERE v.id = ? AND v.is_active = 1
    ");
    $stmt->execute([$video_id]);
    $current_video = $stmt->fetch();
}

// فیلتر دسته‌بندی
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : null;
$search_query = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

// دریافت ویدیوهای ویژه
$featured_videos = $pdo->query("
    SELECT v.*, c.name as category_name
    FROM training_videos v
    LEFT JOIN training_categories c ON v.category_id = c.id
    WHERE v.is_active = 1 AND v.is_featured = 1
    ORDER BY v.created_at DESC
    LIMIT 3
")->fetchAll();

// ساخت کوئری ویدیوها
$query = "
    SELECT v.*, c.name as category_name, c.icon as category_icon, u.full_name as uploader_name
    FROM training_videos v
    LEFT JOIN training_categories c ON v.category_id = c.id
    LEFT JOIN users u ON v.uploaded_by = u.id
    WHERE v.is_active = 1
";

$params = [];

if ($category_filter) {
    $query .= " AND v.category_id = ?";
    $params[] = $category_filter;
}

if ($search_query) {
    $query .= " AND (v.title LIKE ? OR v.description LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

$query .= " ORDER BY v.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$videos = $stmt->fetchAll();

// دریافت دسته‌بندی‌ها برای فیلتر
$categories = $pdo->query("
    SELECT c.*, COUNT(v.id) as video_count 
    FROM training_categories c
    LEFT JOIN training_videos v ON c.id = v.category_id AND v.is_active = 1
    WHERE c.is_active = 1
    GROUP BY c.id
    ORDER BY c.sort_order
")->fetchAll();

// تابع برای دریافت embed URL
function getEmbedUrl($url, $type) {
    switch ($type) {
        case 'youtube':
            if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\n?#]+)/', $url, $matches)) {
                return "https://www.youtube.com/embed/" . $matches[1];
            }
            break;
        case 'vimeo':
            if (preg_match('/vimeo\.com\/(\d+)/', $url, $matches)) {
                return "https://player.vimeo.com/video/" . $matches[1];
            }
            break;
        case 'aparat':
            if (preg_match('/aparat\.com\/v\/([^\/\?]+)/', $url, $matches)) {
                return "https://www.aparat.com/video/video/embed/videohash/" . $matches[1] . "/vt/frame";
            }
            break;
    }
    return $url;
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ویدیوهای آموزشی - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.plyr.io/3.7.8/plyr.css">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 40px 0;
            border-radius: 0 0 30px 30px;
            margin-bottom: 30px;
        }
        
        .featured-section {
            background: #f8f9fa;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .featured-carousel {
            display: flex;
            gap: 20px;
            overflow-x: auto;
            padding-bottom: 10px;
        }
        
        .featured-carousel::-webkit-scrollbar {
            height: 8px;
        }
        
        .featured-carousel::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        .featured-carousel::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }
        
        .featured-video-card {
            min-width: 350px;
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .video-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 25px;
        }
        
        .video-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .video-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .video-thumbnail {
            position: relative;
            padding-bottom: 56.25%;
            background: #000;
            overflow: hidden;
        }
        
        .video-thumbnail img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .video-thumbnail .play-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .video-card:hover .play-overlay {
            opacity: 1;
        }
        
        .play-button {
            width: 70px;
            height: 70px;
            background: rgba(255,255,255,0.9);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: #333;
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
        
        .duration-badge {
            position: absolute;
            bottom: 10px;
            left: 10px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 2px 8px;
            border-radius: 5px;
            font-size: 0.85rem;
        }
        
        .category-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 30px;
        }
        
        .category-pill {
            padding: 8px 20px;
            border-radius: 25px;
            background: white;
            border: 1px solid #dee2e6;
            color: #495057;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .category-pill:hover {
            background: #f8f9fa;
            transform: translateY(-2px);
        }
        
        .category-pill.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: transparent;
        }
        
        .video-modal .modal-dialog {
            max-width: 900px;
        }
        
        .video-container {
            position: relative;
            padding-bottom: 56.25%;
            height: 0;
            overflow: hidden;
        }
        
        .video-container iframe,
        .video-container video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
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
                        <i class="fas fa-video"></i>
                        ویدیوهای آموزشی
                    </h1>
                    <p class="lead mb-0">مشاهده ویدیوهای آموزشی و دستورالعمل‌های تصویری</p>
                </div>
                <div class="col-md-4">
                    <div class="search-box">
                        <form method="GET" class="d-flex align-items-center">
                            <i class="fas fa-search text-muted me-2"></i>
                            <input type="text" name="search" placeholder="جستجوی ویدیو..." 
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
        <!-- ویدیوهای ویژه -->
        <?php if (count($featured_videos) > 0 && !$search_query && !$category_filter): ?>
            <div class="featured-section">
                <h3 class="mb-4">
                    <i class="fas fa-star text-warning"></i>
                    ویدیوهای ویژه
                </h3>
                <div class="featured-carousel">
                    <?php foreach ($featured_videos as $video): ?>
                        <div class="featured-video-card" onclick="playVideo(<?php echo $video['id']; ?>)">
                            <div class="video-thumbnail">
                                <?php if ($video['thumbnail_path']): ?>
                                    <img src="<?php echo $video['thumbnail_path']; ?>" alt="">
                                <?php else: ?>
                                    <div class="d-flex align-items-center justify-content-center h-100 bg-dark">
                                        <i class="fas fa-video fa-3x text-white-50"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="play-overlay">
                                    <div class="play-button">
                                        <i class="fas fa-play"></i>
                                    </div>
                                </div>
                                <span class="badge bg-warning video-type-badge">
                                    <i class="fas fa-star"></i> ویژه
                                </span>
                            </div>
                            <div class="video-info">
                                <h6><?php echo htmlspecialchars($video['title']); ?></h6>
                                <small class="text-muted">
                                    <i class="fas fa-eye"></i> <?php echo $video['view_count']; ?> بازدید
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- فیلتر دسته‌بندی -->
        <div class="category-pills">
            <a href="?" class="category-pill <?php echo !$category_filter ? 'active' : ''; ?>">
                همه ویدیوها (<?php echo count($videos); ?>)
            </a>
            <?php foreach ($categories as $category): ?>
                <a href="?category=<?php echo $category['id']; ?>" 
                   class="category-pill <?php echo $category_filter == $category['id'] ? 'active' : ''; ?>">
                    <i class="fas <?php echo $category['icon']; ?>"></i>
                    <?php echo htmlspecialchars($category['name']); ?>
                    (<?php echo $category['video_count']; ?>)
                </a>
            <?php endforeach; ?>
        </div>

        <!-- لیست ویدیوها -->
        <?php if (count($videos) > 0): ?>
            <div class="video-grid">
                <?php foreach ($videos as $video): ?>
                    <div class="video-card" onclick="playVideo(<?php echo $video['id']; ?>)">
                        <div class="video-thumbnail">
                            <?php if ($video['thumbnail_path']): ?>
                                <img src="<?php echo $video['thumbnail_path']; ?>" alt="">
                            <?php else: ?>
                                <div class="d-flex align-items-center justify-content-center h-100 bg-dark">
                                    <i class="fas fa-video fa-3x text-white-50"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="play-overlay">
                                <div class="play-button">
                                    <i class="fas fa-play"></i>
                                </div>
                            </div>
                            
                            <span class="badge bg-info video-type-badge">
                                <?php echo strtoupper($video['video_type']); ?>
                            </span>
                            
                            <?php if ($video['duration']): ?>
                                <span class="duration-badge">
                                    <?php echo gmdate("i:s", $video['duration']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="video-info">
                            <h6 class="mb-2"><?php echo htmlspecialchars($video['title']); ?></h6>
                            
                            <?php if ($video['description']): ?>
                                <p class="text-muted small mb-2">
                                    <?php echo htmlspecialchars(mb_substr($video['description'], 0, 80)); ?>...
                                </p>
                            <?php endif; ?>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <i class="fas fa-eye"></i> <?php echo $video['view_count']; ?> بازدید
                                </small>
                                <?php if ($video['category_name']): ?>
                                    <span class="badge bg-secondary">
                                        <?php echo $video['category_name']; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-film"></i>
                <h4>ویدیویی یافت نشد</h4>
                <p class="text-muted">
                    <?php if ($search_query): ?>
                        هیچ ویدیویی با عبارت "<?php echo htmlspecialchars($search_query); ?>" یافت نشد.
                    <?php elseif ($category_filter): ?>
                        در این دسته‌بندی ویدیویی وجود ندارد.
                    <?php else: ?>
                        هنوز ویدیویی آپلود نشده است.
                    <?php endif; ?>
                </p>
                <?php if ($search_query || $category_filter): ?>
                    <a href="training_videos.php" class="btn btn-primary mt-3">
                        <i class="fas fa-arrow-right"></i>
                        مشاهده همه ویدیوها
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal برای پخش ویدیو -->
    <div class="modal fade" id="videoModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="videoTitle"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="videoContainer"></div>
                    <p class="mt-3" id="videoDescription"></p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.plyr.io/3.7.8/plyr.polyfilled.js"></script>
    
    <script>
        const videos = <?php echo json_encode($videos); ?>;
        let currentPlayer = null;
        
        function playVideo(videoId) {
            const video = videos.find(v => v.id == videoId);
            if (!video) return;
            
            // Update view count
            fetch('?watch=' + videoId);
            
            const modal = new bootstrap.Modal(document.getElementById('videoModal'));
            document.getElementById('videoTitle').textContent = video.title;
            document.getElementById('videoDescription').textContent = video.description || '';
            
            const container = document.getElementById('videoContainer');
            container.innerHTML = '';
            
            if (video.video_path) {
                // Local video
                container.innerHTML = `
                    <video controls class="w-100" id="player">
                        <source src="${video.video_path}" type="video/mp4">
                    </video>
                `;
                currentPlayer = new Plyr('#player', {
                    controls: ['play-large', 'play', 'progress', 'current-time', 'mute', 'volume', 'fullscreen']
                });
            } else if (video.video_url) {
                // External video
                const embedUrl = getEmbedUrl(video.video_url, video.video_type);
                container.innerHTML = `
                    <div class="video-container">
                        <iframe src="${embedUrl}" frameborder="0" allowfullscreen></iframe>
                    </div>
                `;
            }
            
            modal.show();
        }
        
        function getEmbedUrl(url, type) {
            switch (type) {
                case 'youtube':
                    const ytMatch = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\n?#]+)/);
                    if (ytMatch) return `https://www.youtube.com/embed/${ytMatch[1]}`;
                    break;
                case 'vimeo':
                    const vmMatch = url.match(/vimeo\.com\/(\d+)/);
                    if (vmMatch) return `https://player.vimeo.com/video/${vmMatch[1]}`;
                    break;
                case 'aparat':
                    const apMatch = url.match(/aparat\.com\/v\/([^\/\?]+)/);
                    if (apMatch) return `https://www.aparat.com/video/video/embed/videohash/${apMatch[1]}/vt/frame`;
                    break;
            }
            return url;
        }
        
        // Clean up player when modal closes
        document.getElementById('videoModal').addEventListener('hidden.bs.modal', function () {
            if (currentPlayer) {
                currentPlayer.destroy();
                currentPlayer = null;
            }
            document.getElementById('videoContainer').innerHTML = '';
        });
    </script>
</body>
</html>