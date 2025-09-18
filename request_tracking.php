<?php
/**
 * request_tracking.php - پیگیری درخواست‌های کالا/خدمات
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'config.php';

// بررسی دسترسی
if (!hasPermission('*')) {
    die('دسترسی غیرمجاز - شما مجوز دسترسی به این بخش را ندارید');
}

$page_title = 'پیگیری درخواست‌های کالا/خدمات';

// دریافت درخواست‌ها
$requests = [];
try {
    $stmt = $pdo->query("
        SELECT r.*, u.full_name as requester_full_name 
        FROM requests r 
        LEFT JOIN users u ON r.requester_id = u.id 
        ORDER BY r.created_at DESC
    ");
    $requests = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching requests: " . $e->getMessage());
}

// دریافت جزئیات درخواست خاص
$request_details = null;
$workflow_steps = [];
$request_files = [];

if (isset($_GET['id'])) {
    $request_id = (int)$_GET['id'];
    
    try {
        // جزئیات درخواست
        $stmt = $pdo->prepare("
            SELECT r.*, u.full_name as requester_full_name 
            FROM requests r 
            LEFT JOIN users u ON r.requester_id = u.id 
            WHERE r.id = ?
        ");
        $stmt->execute([$request_id]);
        $request_details = $stmt->fetch();
        
        if ($request_details) {
            // مراحل گردش کار
            $stmt = $pdo->prepare("
                SELECT rw.*, u.full_name as assigned_full_name 
                FROM request_workflow rw 
                LEFT JOIN users u ON rw.assigned_to = u.id 
                WHERE rw.request_id = ? 
                ORDER BY rw.step_order
            ");
            $stmt->execute([$request_id]);
            $workflow_steps = $stmt->fetchAll();
            
            // فایل‌های ضمیمه
            $stmt = $pdo->prepare("SELECT * FROM request_files WHERE request_id = ? ORDER BY uploaded_at");
            $stmt->execute([$request_id]);
            $request_files = $stmt->fetchAll();
        }
    } catch (Exception $e) {
        error_log("Error fetching request details: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
    <!-- CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --dark-color: #1f2937;
            --light-color: #f8fafc;
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-dark: linear-gradient(135deg, #1f2937 0%, #374151 100%);
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--gradient-dark);
            min-height: 100vh;
            color: white;
        }

        .main-container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            margin: 20px;
            box-shadow: var(--shadow-xl);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .header {
            background: var(--gradient-primary);
            color: white;
            padding: 30px;
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
        }

        .header-content {
            position: relative;
            z-index: 2;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .page-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 0;
        }

        .back-btn {
            position: absolute;
            top: 30px;
            right: 30px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            font-size: 1.2rem;
            transition: all 0.3s ease;
            z-index: 3;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }

        .content-area {
            padding: 30px;
        }

        .request-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .request-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-color);
        }

        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .request-number {
            font-size: 1.2rem;
            font-weight: 700;
            color: #ffffff !important;
        }

        .request-status {
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .status-pending { background: var(--warning-color); color: white; }
        .status-processing { background: var(--primary-color); color: white; }
        .status-approved { background: var(--success-color); color: white; }
        .status-rejected { background: var(--danger-color); color: white; }
        .status-completed { background: #6b7280; color: white; }

        .request-details {
            margin-bottom: 15px;
        }

        .request-item {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: #ffffff !important;
        }

        .request-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9rem;
            color: #ffffff !important;
        }

        .priority-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .priority-low { background: #10b981; color: white; }
        .priority-medium { background: #f59e0b; color: white; }
        .priority-high { background: #ef4444; color: white; }
        .priority-urgent { background: #8b5cf6; color: white; }

        .workflow-timeline {
            position: relative;
            padding: 20px 0;
        }

        .workflow-step {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            position: relative;
        }

        .workflow-step::before {
            content: '';
            position: absolute;
            right: 20px;
            top: 40px;
            width: 2px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
        }

        .workflow-step:last-child::before {
            display: none;
        }

        .step-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 20px;
            font-size: 1.2rem;
            z-index: 2;
            position: relative;
        }

        .step-pending { background: rgba(255, 255, 255, 0.2); color: white; }
        .step-approved { background: var(--success-color); color: white; }
        .step-rejected { background: var(--danger-color); color: white; }

        .step-content {
            flex: 1;
        }

        .step-title {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .step-meta {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.7);
        }

        .file-item {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .file-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 15px;
            font-size: 1.2rem;
        }

        .file-pdf { background: #ef4444; color: white; }
        .file-image { background: #10b981; color: white; }
        .file-doc { background: #3b82f6; color: white; }
        .file-excel { background: #059669; color: white; }

        .btn-primary {
            background: var(--gradient-primary);
            border: none;
            border-radius: 12px;
            padding: 12px 30px;
            font-weight: 700;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            color: white;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 12px 30px;
            font-weight: 700;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            color: white;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.3);
            color: white;
        }

        @media (max-width: 768px) {
            .main-container {
                margin: 10px;
                border-radius: 15px;
            }
            
            .content-area {
                padding: 20px;
            }
            
            .request-card {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php if (file_exists('navbar.php')): ?>
        <?php include 'navbar.php'; ?>
    <?php endif; ?>

    <div class="main-container">
        <!-- Header -->
        <div class="header">
            <button class="back-btn" onclick="window.history.back()" title="بازگشت">
                <i class="fas fa-arrow-right"></i>
            </button>
            <div class="header-content">
                <h1 class="page-title">
                    <i class="fas fa-search me-3"></i>
                    پیگیری درخواست‌های کالا/خدمات
                </h1>
                <p class="page-subtitle">
                    ردیابی وضعیت و جزئیات درخواست‌ها
                </p>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content-area">
            <?php if ($request_details): ?>
                <!-- Request Details -->
                <div class="request-card" data-aos="fade-up">
                    <div class="request-header">
                        <div class="request-number"><?php echo htmlspecialchars($request_details['request_number']); ?></div>
                        <div class="request-status status-<?php echo strtolower(str_replace(' ', '', $request_details['status'])); ?>">
                            <?php echo htmlspecialchars($request_details['status']); ?>
                        </div>
                    </div>
                    
                    <div class="request-details">
                        <div class="request-item">
                            <i class="fas fa-box me-2"></i>
                            <?php echo htmlspecialchars($request_details['item_name']); ?>
                        </div>
                        <div class="request-meta">
                            <span>
                                <i class="fas fa-user me-1"></i>
                                درخواست‌کننده: <?php echo htmlspecialchars($request_details['requester_full_name'] ?: $request_details['requester_name']); ?>
                            </span>
                            <span>
                                <i class="fas fa-hashtag me-1"></i>
                                تعداد: <?php echo number_format($request_details['quantity']); ?>
                            </span>
                        </div>
                        <?php if ($request_details['price']): ?>
                        <div class="request-meta">
                            <span>
                                <i class="fas fa-dollar-sign me-1"></i>
                                قیمت: <?php echo number_format($request_details['price']); ?> ریال
                            </span>
                        </div>
                        <?php endif; ?>
                        <div class="request-meta">
                            <span class="priority-badge priority-<?php echo strtolower($request_details['priority']); ?>">
                                <?php echo htmlspecialchars($request_details['priority']); ?>
                            </span>
                            <span>
                                <i class="fas fa-calendar me-1"></i>
                                <?php echo jalali_format($request_details['created_at']); ?>
                            </span>
                        </div>
                        <?php if ($request_details['description']): ?>
                        <div class="mt-3">
                            <strong>توضیحات:</strong>
                            <p class="mt-2"><?php echo nl2br(htmlspecialchars($request_details['description'])); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Workflow Timeline -->
                <?php if (!empty($workflow_steps)): ?>
                <div class="request-card" data-aos="fade-up" data-aos-delay="200">
                    <h4 class="mb-4">
                        <i class="fas fa-route me-2"></i>
                        روند گردش کار
                    </h4>
                    <div class="workflow-timeline">
                        <?php foreach ($workflow_steps as $step): ?>
                        <div class="workflow-step">
                            <div class="step-icon step-<?php echo strtolower(str_replace(' ', '', $step['status'])); ?>">
                                <?php if ($step['status'] === 'تأیید شده'): ?>
                                    <i class="fas fa-check"></i>
                                <?php elseif ($step['status'] === 'رد شده'): ?>
                                    <i class="fas fa-times"></i>
                                <?php else: ?>
                                    <i class="fas fa-clock"></i>
                                <?php endif; ?>
                            </div>
                            <div class="step-content">
                                <div class="step-title"><?php echo htmlspecialchars($step['assigned_to_name']); ?></div>
                                <div class="step-meta">
                                    <?php echo htmlspecialchars($step['department']); ?>
                                    <?php if ($step['action_date']): ?>
                                        - <?php echo jalali_format($step['action_date']); ?>
                                    <?php endif; ?>
                                </div>
                                <?php if ($step['comments']): ?>
                                <div class="mt-2">
                                    <small><?php echo nl2br(htmlspecialchars($step['comments'])); ?></small>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Attached Files -->
                <?php if (!empty($request_files)): ?>
                <div class="request-card" data-aos="fade-up" data-aos-delay="400">
                    <h4 class="mb-4">
                        <i class="fas fa-paperclip me-2"></i>
                        فایل‌های ضمیمه
                    </h4>
                    <?php foreach ($request_files as $file): ?>
                    <div class="file-item">
                        <div class="d-flex align-items-center">
                            <div class="file-icon file-<?php echo strtolower(pathinfo($file['file_name'], PATHINFO_EXTENSION)); ?>">
                                <?php
                                $extension = strtolower(pathinfo($file['file_name'], PATHINFO_EXTENSION));
                                if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                    <i class="fas fa-image"></i>
                                <?php elseif ($extension === 'pdf'): ?>
                                    <i class="fas fa-file-pdf"></i>
                                <?php elseif (in_array($extension, ['doc', 'docx'])): ?>
                                    <i class="fas fa-file-word"></i>
                                <?php elseif (in_array($extension, ['xls', 'xlsx'])): ?>
                                    <i class="fas fa-file-excel"></i>
                                <?php else: ?>
                                    <i class="fas fa-file"></i>
                                <?php endif; ?>
                            </div>
                            <div>
                                <div class="fw-bold"><?php echo htmlspecialchars($file['file_name']); ?></div>
                                <small class="text-muted">
                                    <?php echo number_format($file['file_size'] / 1024, 1); ?> KB - 
                                    <?php echo jalali_format($file['uploaded_at']); ?>
                                </small>
                            </div>
                        </div>
                        <a href="<?php echo htmlspecialchars($file['file_path']); ?>" target="_blank" class="btn btn-sm btn-primary">
                            <i class="fas fa-download me-1"></i>
                            دانلود
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="text-center mt-4">
                    <a href="request_tracking.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-right me-2"></i>
                        بازگشت به لیست
                    </a>
                </div>

            <?php else: ?>
                <!-- Requests List -->
                <div class="row">
                    <?php if (empty($requests)): ?>
                    <div class="col-12">
                        <div class="request-card text-center">
                            <i class="fas fa-inbox fa-3x mb-3" style="color: var(--primary-color);"></i>
                            <h4>هیچ درخواستی یافت نشد</h4>
                            <p class="text-muted">هنوز درخواستی ایجاد نشده است</p>
                            <a href="request_management.php" class="btn btn-primary mt-3">
                                <i class="fas fa-plus me-2"></i>
                                ایجاد درخواست جدید
                            </a>
                        </div>
                    </div>
                    <?php else: ?>
                    <?php foreach ($requests as $index => $request): ?>
                    <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="<?php echo $index * 100; ?>">
                        <div class="request-card" onclick="window.location.href='request_tracking.php?id=<?php echo $request['id']; ?>'">
                            <div class="request-header">
                                <div class="request-number"><?php echo htmlspecialchars($request['request_number']); ?></div>
                                <div class="request-status status-<?php echo strtolower(str_replace(' ', '', $request['status'])); ?>">
                                    <?php echo htmlspecialchars($request['status']); ?>
                                </div>
                            </div>
                            
                            <div class="request-details">
                                <div class="request-item">
                                    <i class="fas fa-box me-2"></i>
                                    <?php echo htmlspecialchars($request['item_name']); ?>
                                </div>
                                <div class="request-meta">
                                    <span>
                                        <i class="fas fa-user me-1"></i>
                                        <?php echo htmlspecialchars($request['requester_full_name'] ?: $request['requester_name']); ?>
                                    </span>
                                    <span>
                                        <i class="fas fa-hashtag me-1"></i>
                                        <?php echo number_format($request['quantity']); ?>
                                    </span>
                                </div>
                                <div class="request-meta">
                                    <span class="priority-badge priority-<?php echo strtolower($request['priority']); ?>">
                                        <?php echo htmlspecialchars($request['priority']); ?>
                                    </span>
                                    <span>
                                        <i class="fas fa-calendar me-1"></i>
                                        <?php echo jalali_format($request['created_at']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
    
    <script>
        // Initialize AOS
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true
        });
    </script>
</body>
</html>