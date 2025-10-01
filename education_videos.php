<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

$is_admin = ($_SESSION['role'] === 'ادمین');

// دریافت ویدیوها
$search = $_GET['search'] ?? '';
$view_id = (int)($_GET['view'] ?? 0);
$page = (int)($_GET['page'] ?? 1);
$limit = 9;
$offset = ($page - 1) * $limit;

$where_clause = "WHERE is_active = 1";
$params = [];

if (!empty($search)) {
    $where_clause .= " AND (title LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$count_sql = "SELECT COUNT(*) as total FROM education_videos $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_videos = $count_stmt->fetch()['total'];
$total_pages = ceil($total_videos / $limit);

$sql = "SELECT ev.*, u.username as uploaded_by_name 
        FROM education_videos ev 
        LEFT JOIN users u ON ev.uploaded_by = u.id 
        $where_clause 
        ORDER BY ev.created_at DESC 
        LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$videos = $stmt->fetchAll();

// دریافت ویدیو برای نمایش
$selected_video = null;
if ($view_id > 0) {
    $stmt = $pdo->prepare("SELECT ev.*, u.username as uploaded_by_name 
                          FROM education_videos ev 
                          LEFT JOIN users u ON ev.uploaded_by = u.id 
                          WHERE ev.id = ? AND ev.is_active = 1");
    $stmt->execute([$view_id]);
    $selected_video = $stmt->fetch();
    
    if ($selected_video) {
        // افزایش view count
        $stmt = $pdo->prepare("UPDATE education_videos SET view_count = view_count + 1 WHERE id = ?");
        $stmt->execute([$view_id]);
        
        // ثبت لاگ
        logAction($pdo, 'view_video', "ویدیو مشاهده شد: " . $selected_video['title']);
    }
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ویدیوهای آموزشی - سامانه مدیریت اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .videos-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 0;
            margin-bottom: 40px;
        }
        
        .video-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .video-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .video-thumbnail {
            position: relative;
            height: 200px;
            overflow: hidden;
        }
        
        .video-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .play-button {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 60px;
            height: 60px;
            background: rgba(0,0,0,0.7);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            transition: all 0.3s ease;
        }
        
        .video-card:hover .play-button {
            background: rgba(0,0,0,0.9);
            transform: translate(-50%, -50%) scale(1.1);
        }
        
        .video-duration {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
        }
        
        .video-info {
            padding: 20px;
        }
        
        .video-title {
            font-weight: bold;
            margin-bottom: 10px;
            color: #2c3e50;
        }
        
        .video-meta {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }
        
        .video-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-video {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 20px;
            padding: 8px 20px;
            color: white;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .btn-video:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .video-player-container {
            background: #000;
            border-radius: 15px;
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .video-player {
            width: 100%;
            height: 500px;
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
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="videos-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="display-5 fw-bold mb-3">
                        <i class="fas fa-video me-3"></i>
                        ویدیوهای آموزشی
                    </h1>
                    <p class="lead mb-0">
                        تماشای ویدیوهای آموزشی و مستندات تصویری شرکت اعلا نیرو
                    </p>
                </div>
                <div class="col-lg-4 text-center">
                    <i class="fas fa-play-circle" style="font-size: 6rem; opacity: 0.3;"></i>
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
                               placeholder="جستجو در ویدیوها...">
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
                    تعداد کل ویدیوها: <strong><?php echo $total_videos; ?></strong>
                    <?php if (!empty($search)): ?>
                    - نتایج جستجو برای: <strong>"<?php echo htmlspecialchars($search); ?>"</strong>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- پخش ویدیو -->
        <?php if ($selected_video): ?>
        <div class="video-player-container">
            <video class="video-player" controls>
                <source src="<?php echo $selected_video['video_path']; ?>" type="video/mp4">
                مرورگر شما از پخش ویدیو پشتیبانی نمی‌کند.
            </video>
        </div>
        
        <div class="card mb-4">
            <div class="card-body">
                <h4 class="card-title"><?php echo htmlspecialchars($selected_video['title']); ?></h4>
                <?php if (!empty($selected_video['description'])): ?>
                <p class="card-text"><?php echo htmlspecialchars($selected_video['description']); ?></p>
                <?php endif; ?>
                <div class="row">
                    <div class="col-md-6">
                        <small class="text-muted">
                            <i class="fas fa-calendar me-2"></i>
                            <?php echo date('Y/m/d', strtotime($selected_video['created_at'])); ?>
                        </small>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">
                            <i class="fas fa-clock me-2"></i>
                            <?php echo gmdate("H:i:s", $selected_video['duration']); ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- لیست ویدیوها -->
        <?php if (empty($videos)): ?>
        <div class="empty-state">
            <i class="fas fa-video"></i>
            <h4>هیچ ویدیویی یافت نشد</h4>
            <p>
                <?php if (!empty($search)): ?>
                نتیجه‌ای برای جستجوی شما یافت نشد. لطفاً کلمات کلیدی دیگری امتحان کنید.
                <?php else: ?>
                هنوز ویدیوی آموزشی آپلود نشده است.
                <?php endif; ?>
            </p>
        </div>
        <?php else: ?>
        <div class="row">
            <?php foreach ($videos as $video): ?>
            <div class="col-lg-4 col-md-6">
                <div class="video-card">
                    <div class="video-thumbnail">
                        <img src="<?php echo $video['thumbnail_path'] ?: '/uploads/education/thumbnails/default_video.jpg'; ?>" 
                             alt="<?php echo htmlspecialchars($video['title']); ?>">
                        <div class="play-button">
                            <i class="fas fa-play"></i>
                        </div>
                        <div class="video-duration">
                            <?php echo gmdate("H:i:s", $video['duration']); ?>
                        </div>
                    </div>
                    <div class="video-info">
                        <h6 class="video-title"><?php echo htmlspecialchars($video['title']); ?></h6>
                        <div class="video-meta">
                            <div class="mb-1">
                                <i class="fas fa-calendar me-2"></i>
                                <?php echo date('Y/m/d', strtotime($video['created_at'])); ?>
                            </div>
                            <div class="mb-1">
                                <i class="fas fa-eye me-2"></i>
                                <?php echo $video['view_count']; ?> مشاهده
                            </div>
                            <div>
                                <i class="fas fa-user me-2"></i>
                                <?php echo htmlspecialchars($video['uploaded_by_name']); ?>
                            </div>
                        </div>
                        <div class="video-actions">
                            <a href="?view=<?php echo $video['id']; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page; ?>" 
                               class="btn btn-video">
                                <i class="fas fa-play me-2"></i>
                                پخش
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- صفحه‌بندی -->
        <?php if ($total_pages > 1): ?>
        <nav aria-label="صفحه‌بندی ویدیوها" class="mt-5">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>