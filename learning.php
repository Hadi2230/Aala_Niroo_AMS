<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';
include 'navbar.php';

// دریافت آمار آموزشی
$forms_count = $pdo->query("SELECT COUNT(*) as total FROM company_forms WHERE is_active = 1")->fetch()['total'];
$images_count = $pdo->query("SELECT COUNT(*) as total FROM image_gallery WHERE is_active = 1")->fetch()['total'];
$videos_count = $pdo->query("SELECT COUNT(*) as total FROM training_videos WHERE is_active = 1")->fetch()['total'];
$articles_count = $pdo->query("SELECT COUNT(*) as total FROM articles WHERE is_published = 1")->fetch()['total'];

// دریافت آخرین محتوای اضافه شده
$latest_forms = $pdo->query("SELECT title, created_at FROM company_forms WHERE is_active = 1 ORDER BY created_at DESC LIMIT 3")->fetchAll();
$latest_images = $pdo->query("SELECT title, created_at FROM image_gallery WHERE is_active = 1 ORDER BY created_at DESC LIMIT 3")->fetchAll();
$latest_videos = $pdo->query("SELECT title, created_at FROM training_videos WHERE is_active = 1 ORDER BY created_at DESC LIMIT 3")->fetchAll();
$latest_articles = $pdo->query("SELECT title, created_at FROM articles WHERE is_published = 1 ORDER BY created_at DESC LIMIT 3")->fetchAll();

$is_admin = ($_SESSION['role'] ?? '') === 'ادمین';
?>

<style>
    .learning-dashboard {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        padding-top: 2rem;
    }
    
    .dashboard-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 20px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        transition: all 0.3s ease;
        overflow: hidden;
    }
    
    .dashboard-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
    }
    
    .stat-card {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        color: white;
        border-radius: 15px;
        padding: 2rem;
        text-align: center;
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    .stat-card:hover {
        transform: scale(1.05);
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
    }
    
    .stat-card.forms { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
    .stat-card.images { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
    .stat-card.videos { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
    .stat-card.articles { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
    
    .stat-number {
        font-size: 3rem;
        font-weight: 700;
        margin: 1rem 0;
    }
    
    .stat-icon {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.8;
    }
    
    .welcome-section {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(10px);
        border-radius: 20px;
        padding: 3rem;
        text-align: center;
        margin-bottom: 3rem;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    }
    
    .recent-items {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(10px);
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .recent-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.8rem 0;
        border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    }
    
    .recent-item:last-child {
        border-bottom: none;
    }
    
    .quick-actions {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(10px);
        border-radius: 15px;
        padding: 2rem;
    }
    
    .action-btn {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        border-radius: 10px;
        color: white;
        padding: 1rem 2rem;
        margin: 0.5rem;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-block;
    }
    
    .action-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        color: white;
    }
    
    .floating-animation {
        animation: floating 3s ease-in-out infinite;
    }
    
    @keyframes floating {
        0%, 100% { transform: translateY(0px); }
        50% { transform: translateY(-10px); }
    }
    
    .pulse-animation {
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }
</style>

<div class="learning-dashboard">
    <div class="container">
        <!-- بخش خوش‌آمدگویی -->
        <div class="welcome-section floating-animation">
            <h1 class="display-4 mb-4">
                <i class="fas fa-graduation-cap text-primary"></i>
                سامانه آموزش اعلا نیرو
            </h1>
            <p class="lead">مرکز جامع منابع آموزشی، فرم‌ها، تصاویر و محتوای تخصصی شرکت</p>
            <div class="mt-4">
                <span class="badge bg-primary fs-6 me-2">
                    <i class="fas fa-user"></i> <?php echo $_SESSION['username']; ?>
                </span>
                <span class="badge bg-success fs-6">
                    <i class="fas fa-shield-alt"></i> <?php echo $_SESSION['role']; ?>
                </span>
            </div>
        </div>

        <!-- آمار کلی -->
        <div class="row mb-5">
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stat-card forms pulse-animation" onclick="location.href='learning_forms.php'">
                    <div class="stat-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="stat-number"><?php echo $forms_count; ?></div>
                    <h5>فرم‌های شرکت</h5>
                    <p class="mb-0">فرم‌های اداری و فنی</p>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stat-card images pulse-animation" onclick="location.href='learning_images.php'">
                    <div class="stat-icon">
                        <i class="fas fa-images"></i>
                    </div>
                    <div class="stat-number"><?php echo $images_count; ?></div>
                    <h5>گالری تصاویر</h5>
                    <p class="mb-0">تصاویر محصولات و آموزشی</p>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stat-card videos pulse-animation" onclick="location.href='learning_videos.php'">
                    <div class="stat-icon">
                        <i class="fas fa-video"></i>
                    </div>
                    <div class="stat-number"><?php echo $videos_count; ?></div>
                    <h5>ویدیوهای آموزشی</h5>
                    <p class="mb-0">آموزش‌های تصویری</p>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stat-card articles pulse-animation" onclick="location.href='learning_articles.php'">
                    <div class="stat-icon">
                        <i class="fas fa-newspaper"></i>
                    </div>
                    <div class="stat-number"><?php echo $articles_count; ?></div>
                    <h5>مقالات و نشریات</h5>
                    <p class="mb-0">محتوای تخصصی</p>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- آخرین محتوای اضافه شده -->
            <div class="col-lg-8">
                <div class="dashboard-card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-clock"></i>
                            آخرین محتوای اضافه شده
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="recent-items">
                                    <h6 class="text-primary">
                                        <i class="fas fa-file-alt"></i> آخرین فرم‌ها
                                    </h6>
                                    <?php if (empty($latest_forms)): ?>
                                        <p class="text-muted">هیچ فرمی یافت نشد</p>
                                    <?php else: ?>
                                        <?php foreach ($latest_forms as $form): ?>
                                            <div class="recent-item">
                                                <span><?php echo htmlspecialchars($form['title']); ?></span>
                                                <small class="text-muted"><?php echo date('Y/m/d', strtotime($form['created_at'])); ?></small>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="recent-items">
                                    <h6 class="text-danger">
                                        <i class="fas fa-images"></i> آخرین تصاویر
                                    </h6>
                                    <?php if (empty($latest_images)): ?>
                                        <p class="text-muted">هیچ تصویری یافت نشد</p>
                                    <?php else: ?>
                                        <?php foreach ($latest_images as $image): ?>
                                            <div class="recent-item">
                                                <span><?php echo htmlspecialchars($image['title']); ?></span>
                                                <small class="text-muted"><?php echo date('Y/m/d', strtotime($image['created_at'])); ?></small>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="recent-items">
                                    <h6 class="text-info">
                                        <i class="fas fa-video"></i> آخرین ویدیوها
                                    </h6>
                                    <?php if (empty($latest_videos)): ?>
                                        <p class="text-muted">هیچ ویدیویی یافت نشد</p>
                                    <?php else: ?>
                                        <?php foreach ($latest_videos as $video): ?>
                                            <div class="recent-item">
                                                <span><?php echo htmlspecialchars($video['title']); ?></span>
                                                <small class="text-muted"><?php echo date('Y/m/d', strtotime($video['created_at'])); ?></small>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="recent-items">
                                    <h6 class="text-success">
                                        <i class="fas fa-newspaper"></i> آخرین مقالات
                                    </h6>
                                    <?php if (empty($latest_articles)): ?>
                                        <p class="text-muted">هیچ مقاله‌ای یافت نشد</p>
                                    <?php else: ?>
                                        <?php foreach ($latest_articles as $article): ?>
                                            <div class="recent-item">
                                                <span><?php echo htmlspecialchars($article['title']); ?></span>
                                                <small class="text-muted"><?php echo date('Y/m/d', strtotime($article['created_at'])); ?></small>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- عملیات سریع -->
            <div class="col-lg-4">
                <div class="dashboard-card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-bolt"></i>
                            عملیات سریع
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="quick-actions text-center">
                            <h6 class="mb-4">دسترسی سریع به بخش‌ها</h6>
                            
                            <a href="learning_forms.php" class="action-btn d-block mb-3">
                                <i class="fas fa-file-alt"></i>
                                مشاهده فرم‌ها
                            </a>
                            
                            <a href="learning_images.php" class="action-btn d-block mb-3">
                                <i class="fas fa-images"></i>
                                گالری تصاویر
                            </a>
                            
                            <a href="learning_videos.php" class="action-btn d-block mb-3">
                                <i class="fas fa-video"></i>
                                ویدیوهای آموزشی
                            </a>
                            
                            <a href="learning_articles.php" class="action-btn d-block mb-3">
                                <i class="fas fa-newspaper"></i>
                                مقالات و نشریات
                            </a>
                            
                            <?php if ($is_admin): ?>
                                <hr class="my-4">
                                <h6 class="text-muted mb-3">عملیات مدیریتی</h6>
                                
                                <a href="learning_forms.php?action=upload" class="action-btn d-block mb-2" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                                    <i class="fas fa-upload"></i>
                                    آپلود فرم جدید
                                </a>
                                
                                <a href="learning_images.php?action=upload" class="action-btn d-block mb-2" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                                    <i class="fas fa-camera"></i>
                                    آپلود تصویر جدید
                                </a>
                                
                                <a href="learning_videos.php?action=upload" class="action-btn d-block mb-2" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                                    <i class="fas fa-video"></i>
                                    آپلود ویدیو جدید
                                </a>
                                
                                <a href="learning_articles.php?action=create" class="action-btn d-block" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                                    <i class="fas fa-pen"></i>
                                    نوشتن مقاله جدید
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // انیمیشن‌های تعاملی
    document.addEventListener('DOMContentLoaded', function() {
        // افکت hover برای کارت‌های آمار
        const statCards = document.querySelectorAll('.stat-card');
        statCards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.05) rotate(2deg)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1) rotate(0deg)';
            });
        });
        
        // انیمیشن ظاهر شدن تدریجی
        const cards = document.querySelectorAll('.dashboard-card, .stat-card');
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(30px)';
            
            setTimeout(() => {
                card.style.transition = 'all 0.6s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 200);
        });
    });
    
    // شمارنده انیمیشنی
    function animateCounter(element, target) {
        let current = 0;
        const increment = target / 50;
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                current = target;
                clearInterval(timer);
            }
            element.textContent = Math.floor(current);
        }, 30);
    }
    
    // اجرای انیمیشن شمارنده
    document.querySelectorAll('.stat-number').forEach(element => {
        const target = parseInt(element.textContent);
        element.textContent = '0';
        setTimeout(() => animateCounter(element, target), 1000);
    });
</script>

</body>
</html>