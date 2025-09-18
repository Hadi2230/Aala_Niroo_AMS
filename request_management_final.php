<?php
/**
 * request_management_final.php - مدیریت درخواست‌های کالا/خدمات - نسخه نهایی
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'config_simple.php';

$page_title = 'مدیریت درخواست‌های کالا/خدمات';

// پردازش فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_request') {
        try {
            // پردازش آیتم‌های متعدد
            $items = [];
            if (isset($_POST['items']) && is_array($_POST['items'])) {
                foreach ($_POST['items'] as $item) {
                    if (!empty($item['item_name'])) {
                        // تبدیل قیمت فارسی به انگلیسی
                        $price = $item['price'];
                        $price = str_replace(['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'], 
                                           ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'], $price);
                        $price = str_replace(',', '', $price);
                        
                        $items[] = [
                            'item_name' => sanitizeInput($item['item_name']),
                            'quantity' => (int)$item['quantity'],
                            'price' => floatval($price),
                            'priority' => sanitizeInput($item['priority'])
                        ];
                    }
                }
            }
            
            if (empty($items)) {
                throw new Exception('حداقل یک آیتم باید وارد شود');
            }
            
            // ایجاد درخواست برای هر آیتم
            $request_ids = [];
            foreach ($items as $item) {
                $data = [
                    'requester_id' => $_SESSION['user_id'],
                    'requester_name' => $_SESSION['username'],
                    'item_name' => $item['item_name'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'description' => sanitizeInput($_POST['description'] ?? ''),
                    'priority' => $item['priority']
                ];
                
                $request_id = createRequest($pdo, $data);
                if ($request_id) {
                    $request_ids[] = $request_id;
                }
            }
            
            if (!empty($request_ids)) {
                // آپلود فایل‌ها برای اولین درخواست
                if (!empty($_FILES['files']['name'][0])) {
                    foreach ($_FILES['files']['name'] as $key => $name) {
                        if (!empty($name)) {
                            $file = [
                                'name' => $_FILES['files']['name'][$key],
                                'type' => $_FILES['files']['type'][$key],
                                'tmp_name' => $_FILES['files']['tmp_name'][$key],
                                'size' => $_FILES['files']['size'][$key]
                            ];
                            uploadRequestFile($pdo, $request_ids[0], $file);
                        }
                    }
                }
                
                // ایجاد گردش کار برای همه درخواست‌ها
                if (!empty($_POST['assignments'])) {
                    $assignments = json_decode($_POST['assignments'], true);
                    if (is_array($assignments)) {
                        foreach ($request_ids as $request_id) {
                            createRequestWorkflow($pdo, $request_id, $assignments);
                        }
                    }
                }
                
                $_SESSION['success_message'] = 'درخواست‌ها با موفقیت ایجاد شدند!';
                header('Location: request_management_final.php');
                exit();
            } else {
                throw new Exception('خطا در ایجاد درخواست‌ها');
            }
        } catch (Exception $e) {
            error_log("Error creating request: " . $e->getMessage());
            $error_message = 'خطا در ایجاد درخواست: ' . $e->getMessage();
            
            // نمایش جزئیات خطا برای دیباگ
            if (isset($_POST['debug']) && $_POST['debug'] === '1') {
                $error_message .= '<br><small>جزئیات خطا: ' . $e->getMessage() . '</small>';
                $error_message .= '<br><small>Stack trace: ' . $e->getTraceAsString() . '</small>';
            }
        }
    }
}

// دریافت لیست کاربران
$users = [];
try {
    $stmt = $pdo->query("SELECT id, username, full_name, role FROM users WHERE is_active = 1 ORDER BY full_name");
    $users = $stmt->fetchAll();
} catch (Exception $e) {
    $users = [];
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
            --dark-bg: #1a1a1a;
            --light-bg: #f8f9fa;
            --card-bg: #ffffff;
            --text-primary: #2c3e50;
            --text-secondary: #6c757d;
            --border-color: #e9ecef;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-hover: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        body {
            font-family: 'Vazirmatn', 'Tahoma', sans-serif;
            background: #f8f9fa;
            margin: 0;
            padding: 0;
            color: var(--text-primary);
        }

        .main-container {
            padding-top: 80px;
            min-height: 100vh;
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #34495e 100%);
            color: white;
            padding: 40px 0;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .page-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .card {
            background: var(--card-bg);
            border: none;
            border-radius: 15px;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            overflow: hidden;
            margin-bottom: 25px;
        }

        .card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
        }

        .card-header {
            background: linear-gradient(135deg, var(--secondary-color) 0%, #5dade2 100%);
            color: white;
            border: none;
            padding: 20px 25px;
            font-weight: 600;
            font-size: 1.2rem;
        }

        .card-body {
            padding: 25px;
        }

        .form-label {
            font-weight: 600;
            color: var(--text-primary) !important;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }

        .form-control {
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: white;
            color: var(--text-primary) !important;
        }

        .form-control:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
            background: white;
            color: var(--text-primary) !important;
        }

        .form-control::placeholder {
            color: var(--text-secondary);
            opacity: 0.7;
        }

        .btn {
            border-radius: 10px;
            padding: 12px 24px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--secondary-color) 0%, #5dade2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
            background: linear-gradient(135deg, #2980b9 0%, #3498db 100%);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success-color) 0%, #2ecc71 100%);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--accent-color) 0%, #e67e22 100%);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(231, 76, 60, 0.4);
        }

        .btn-warning {
            background: linear-gradient(135deg, var(--warning-color) 0%, #f39c12 100%);
            color: white;
        }

        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(245, 158, 11, 0.4);
        }

        .item-row {
            background: #f8f9fa;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }

        .item-row:hover {
            border-color: var(--secondary-color);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.1);
        }

        .file-upload-area {
            border: 3px dashed var(--border-color);
            border-radius: 12px;
            padding: 40px 20px;
            text-align: center;
            background: #f8f9fa;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .file-upload-area:hover {
            border-color: var(--secondary-color);
            background: #e3f2fd;
        }

        .file-upload-area.dragover {
            border-color: var(--success-color);
            background: #e8f5e8;
        }

        .assignment-item {
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }

        .assignment-item:hover {
            border-color: var(--secondary-color);
            box-shadow: 0 2px 10px rgba(52, 152, 219, 0.1);
        }

        .alert {
            border: none;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            font-weight: 500;
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border-left: 5px solid var(--success-color);
        }

        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border-left: 5px solid var(--accent-color);
        }

        .stats-card {
            background: linear-gradient(135deg, var(--primary-color) 0%, #34495e 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            height: 100%;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
        }

        .stats-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .stats-description {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .icon-large {
            font-size: 3rem;
            margin-bottom: 20px;
            opacity: 0.8;
        }

        .loading {
            display: none;
            text-align: center;
            padding: 40px;
        }

        .spinner-border {
            width: 3rem;
            height: 3rem;
            color: var(--secondary-color);
        }

        .text-muted {
            color: var(--text-secondary) !important;
        }

        .text-primary {
            color: var(--text-primary) !important;
        }

        @media (max-width: 768px) {
            .page-title {
                font-size: 2rem;
            }
            
            .card-body {
                padding: 20px;
            }
            
            .stats-card {
                margin-bottom: 20px;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="main-container">
        <div class="container">
            <!-- Page Header -->
            <div class="page-header text-center">
                <h1 class="page-title">
                    <i class="fas fa-shopping-cart me-3"></i>
                    مدیریت درخواست‌های کالا/خدمات
                </h1>
                <p class="page-subtitle">
                    سیستم پیشرفته و حرفه‌ای برای مدیریت درخواست‌های داخلی
                </p>
            </div>

            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <!-- Main Cards -->
            <div class="row mb-5">
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="stats-card" onclick="showCreateForm()">
                        <i class="fas fa-plus-circle icon-large"></i>
                        <div class="stats-title">ایجاد درخواست جدید</div>
                        <p class="stats-description mb-0">درخواست کالا یا خدمات جدید ایجاد کنید</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="stats-card" onclick="window.location.href='request_tracking_final.php'">
                        <i class="fas fa-search icon-large"></i>
                        <div class="stats-title">پیگیری درخواست‌ها</div>
                        <p class="stats-description mb-0">وضعیت درخواست‌های خود را بررسی کنید</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="stats-card" onclick="showReports()">
                        <i class="fas fa-chart-bar icon-large"></i>
                        <div class="stats-title">گزارش درخواست‌ها</div>
                        <p class="stats-description mb-0">گزارش‌های آماری و تحلیلی</p>
                    </div>
                </div>
            </div>

            <!-- Create Request Form -->
            <div class="card" id="createForm" style="display: none;">
                <div class="card-header">
                    <i class="fas fa-plus-circle me-2"></i>
                    ایجاد درخواست جدید
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" id="requestForm">
                        <input type="hidden" name="action" value="create_request">
                        
                        <!-- Items Section -->
                        <div class="mb-4">
                            <h5 class="mb-3 text-primary">
                                <i class="fas fa-box me-2"></i>
                                آیتم‌های درخواست
                            </h5>
                            <div id="itemsSection">
                                <div class="item-row" data-item="0">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">
                                                    <i class="fas fa-box me-2"></i>
                                                    نام آیتم
                                                </label>
                                                <input type="text" class="form-control" name="items[0][item_name]" required 
                                                       placeholder="نام کالا یا خدمت مورد نیاز">
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-2">
                                            <div class="mb-3">
                                                <label class="form-label">
                                                    <i class="fas fa-hashtag me-2"></i>
                                                    تعداد
                                                </label>
                                                <input type="number" class="form-control" name="items[0][quantity]" required 
                                                       placeholder="تعداد" min="1">
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label class="form-label">
                                                    <i class="fas fa-dollar-sign me-2"></i>
                                                    قیمت (ریال)
                                                </label>
                                                <input type="text" class="form-control" name="items[0][price]" 
                                                       placeholder="قیمت تخمینی" id="priceInput0">
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-2">
                                            <div class="mb-3">
                                                <label class="form-label">
                                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                                    اولویت
                                                </label>
                                                <select class="form-control" name="items[0][priority]" required>
                                                    <option value="کم">کم</option>
                                                    <option value="متوسط" selected>متوسط</option>
                                                    <option value="بالا">بالا</option>
                                                    <option value="فوری">فوری</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-1">
                                            <div class="mb-3">
                                                <label class="form-label">&nbsp;</label>
                                                <button type="button" class="btn btn-danger remove-item" onclick="removeItem(0)" style="display: none;">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="button" class="btn btn-success" onclick="addItem()">
                                <i class="fas fa-plus me-2"></i>
                                اضافه کردن آیتم جدید
                            </button>
                        </div>
                        
                        <!-- Description -->
                        <div class="mb-4">
                            <label class="form-label">
                                <i class="fas fa-align-right me-2"></i>
                                توضیحات کلی
                            </label>
                            <textarea class="form-control" name="description" rows="4" 
                                      placeholder="توضیحات کلی درخواست..."></textarea>
                        </div>
                        
                        <!-- File Upload -->
                        <div class="mb-4">
                            <label class="form-label">
                                <i class="fas fa-paperclip me-2"></i>
                                فایل‌های ضمیمه
                            </label>
                            <div class="file-upload-area" id="fileUploadArea" onclick="document.getElementById('fileInput').click()">
                                <i class="fas fa-cloud-upload-alt fa-3x mb-3" style="color: var(--secondary-color);"></i>
                                <h5 class="text-primary">فایل‌ها را اینجا بکشید یا کلیک کنید</h5>
                                <p class="text-muted">حداکثر 10 فایل، هر فایل تا 5 مگابایت</p>
                            </div>
                            <input type="file" id="fileInput" name="files[]" multiple style="display: none;" 
                                   accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.txt">
                            <div id="fileList" class="mt-3"></div>
                        </div>
                        
                        <!-- User Assignments -->
                        <div class="mb-4">
                            <label class="form-label">
                                <i class="fas fa-users me-2"></i>
                                انتخاب واحدها/کاربران
                            </label>
                            <div class="row">
                                <div class="col-md-6">
                                    <select class="form-control" id="userSelect">
                                        <option value="">انتخاب کاربر...</option>
                                        <?php foreach ($users as $user): ?>
                                            <option value="<?php echo $user['id']; ?>" 
                                                    data-name="<?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?>"
                                                    data-role="<?php echo htmlspecialchars($user['role']); ?>">
                                                <?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?> 
                                                (<?php echo htmlspecialchars($user['role']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <input type="text" class="form-control" id="departmentInput" placeholder="نام واحد/بخش">
                                </div>
                                <div class="col-md-2">
                                    <button type="button" class="btn btn-primary w-100" onclick="addAssignment()">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                            <div id="assignmentsList" class="mt-3"></div>
                        </div>
                        
                        <!-- Debug Mode -->
                        <div class="mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="debug" value="1" id="debugMode">
                                <label class="form-check-label form-label" for="debugMode">
                                    <i class="fas fa-bug me-2"></i>
                                    حالت دیباگ (نمایش جزئیات خطا)
                                </label>
                            </div>
                        </div>
                        
                        <!-- Submit Buttons -->
                        <div class="d-flex gap-3">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-paper-plane me-2"></i>
                                ارسال درخواست
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="hideCreateForm()">
                                <i class="fas fa-times me-2"></i>
                                انصراف
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Loading -->
            <div class="loading" id="loading">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">در حال بارگذاری...</span>
                </div>
                <p class="mt-3 text-primary">در حال پردازش درخواست...</p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let itemCount = 0;
        let assignmentCount = 0;

        // نمایش فرم ایجاد درخواست
        function showCreateForm() {
            document.getElementById('createForm').style.display = 'block';
            document.getElementById('createForm').scrollIntoView({ behavior: 'smooth' });
        }

        // مخفی کردن فرم ایجاد درخواست
        function hideCreateForm() {
            document.getElementById('createForm').style.display = 'none';
        }

        // نمایش گزارش‌ها
        function showReports() {
            alert('بخش گزارش‌ها به زودی اضافه خواهد شد');
        }

        // اضافه کردن آیتم جدید
        function addItem() {
            itemCount++;
            const itemsSection = document.getElementById('itemsSection');
            const newItem = document.createElement('div');
            newItem.className = 'item-row';
            newItem.setAttribute('data-item', itemCount);
            newItem.innerHTML = `
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-box me-2"></i>
                                نام آیتم
                            </label>
                            <input type="text" class="form-control" name="items[${itemCount}][item_name]" required 
                                   placeholder="نام کالا یا خدمت مورد نیاز">
                        </div>
                    </div>
                    
                    <div class="col-md-2">
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-hashtag me-2"></i>
                                تعداد
                            </label>
                            <input type="number" class="form-control" name="items[${itemCount}][quantity]" required 
                                   placeholder="تعداد" min="1">
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-dollar-sign me-2"></i>
                                قیمت (ریال)
                            </label>
                            <input type="text" class="form-control" name="items[${itemCount}][price]" 
                                   placeholder="قیمت تخمینی" id="priceInput${itemCount}">
                        </div>
                    </div>
                    
                    <div class="col-md-2">
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                اولویت
                            </label>
                            <select class="form-control" name="items[${itemCount}][priority]" required>
                                <option value="کم">کم</option>
                                <option value="متوسط" selected>متوسط</option>
                                <option value="بالا">بالا</option>
                                <option value="فوری">فوری</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="col-md-1">
                        <div class="mb-3">
                            <label class="form-label">&nbsp;</label>
                            <button type="button" class="btn btn-danger remove-item" onclick="removeItem(${itemCount})">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            itemsSection.appendChild(newItem);
            updateRemoveButtons();
        }

        // حذف آیتم
        function removeItem(itemIndex) {
            const item = document.querySelector(`[data-item="${itemIndex}"]`);
            if (item) {
                item.remove();
                updateRemoveButtons();
            }
        }

        // به‌روزرسانی دکمه‌های حذف
        function updateRemoveButtons() {
            const items = document.querySelectorAll('.item-row');
            items.forEach((item, index) => {
                const removeBtn = item.querySelector('.remove-item');
                if (items.length > 1) {
                    removeBtn.style.display = 'block';
                } else {
                    removeBtn.style.display = 'none';
                }
            });
        }

        // اضافه کردن انتساب
        function addAssignment() {
            const userSelect = document.getElementById('userSelect');
            const departmentInput = document.getElementById('departmentInput');
            const assignmentsList = document.getElementById('assignmentsList');
            
            if (userSelect.value && departmentInput.value) {
                assignmentCount++;
                const assignment = document.createElement('div');
                assignment.className = 'assignment-item';
                assignment.innerHTML = `
                    <div>
                        <strong class="text-primary">${userSelect.options[userSelect.selectedIndex].text}</strong>
                        <br>
                        <small class="text-muted">${departmentInput.value}</small>
                    </div>
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeAssignment(${assignmentCount})">
                        <i class="fas fa-times"></i>
                    </button>
                    <input type="hidden" name="assignments[${assignmentCount}][user_id]" value="${userSelect.value}">
                    <input type="hidden" name="assignments[${assignmentCount}][department]" value="${departmentInput.value}">
                `;
                assignmentsList.appendChild(assignment);
                
                userSelect.value = '';
                departmentInput.value = '';
            } else {
                alert('لطفاً کاربر و واحد را انتخاب کنید');
            }
        }

        // حذف انتساب
        function removeAssignment(assignmentIndex) {
            const assignment = document.querySelector(`[data-assignment="${assignmentIndex}"]`);
            if (assignment) {
                assignment.remove();
            }
        }

        // مدیریت آپلود فایل
        document.getElementById('fileInput').addEventListener('change', function(e) {
            const files = e.target.files;
            const fileList = document.getElementById('fileList');
            fileList.innerHTML = '';
            
            Array.from(files).forEach((file, index) => {
                const fileItem = document.createElement('div');
                fileItem.className = 'alert alert-info d-flex justify-content-between align-items-center';
                fileItem.innerHTML = `
                    <div>
                        <i class="fas fa-file me-2"></i>
                        <span class="text-primary">${file.name}</span>
                        <small class="text-muted">(${(file.size / 1024 / 1024).toFixed(2)} MB)</small>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeFile(${index})">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                fileList.appendChild(fileItem);
            });
        });

        // Drag & Drop برای فایل‌ها
        const fileUploadArea = document.getElementById('fileUploadArea');
        
        fileUploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            fileUploadArea.classList.add('dragover');
        });
        
        fileUploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            fileUploadArea.classList.remove('dragover');
        });
        
        fileUploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            fileUploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            document.getElementById('fileInput').files = files;
            
            // Trigger change event
            const event = new Event('change', { bubbles: true });
            document.getElementById('fileInput').dispatchEvent(event);
        });

        // فرمت کردن قیمت به فارسی
        function formatPrice(input) {
            let value = input.value.replace(/[^\d]/g, ''); // حذف همه کاراکترهای غیر عددی
            if (value) {
                // تبدیل به عدد و فرمت با ویرگول
                let num = parseInt(value);
                let formatted = num.toLocaleString('fa-IR');
                input.value = formatted;
            }
        }

        // تبدیل قیمت فارسی به انگلیسی برای ارسال
        function convertPriceToEnglish(price) {
            return price.replace(/[۰-۹]/g, function(d) {
                return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d);
            }).replace(/,/g, '');
        }

        // اعمال فرمت قیمت به همه فیلدهای قیمت
        document.addEventListener('DOMContentLoaded', function() {
            const priceInputs = document.querySelectorAll('input[name*="[price]"]');
            priceInputs.forEach(input => {
                input.addEventListener('blur', function() {
                    formatPrice(this);
                });
            });
        });

        // ارسال فرم
        document.getElementById('requestForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // نمایش loading
            document.getElementById('loading').style.display = 'block';
            document.getElementById('createForm').style.display = 'none';
            
            // ارسال فرم به صورت عادی (بدون AJAX)
            this.submit();
        });
    </script>
</body>
</html>