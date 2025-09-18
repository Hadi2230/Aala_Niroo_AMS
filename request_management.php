<?php
/**
 * request_management.php - مدیریت درخواست‌های کالا/خدمات
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

$page_title = 'مدیریت درخواست‌های کالا/خدمات';

// پردازش فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_request') {
        $data = [
            'requester_id' => $_SESSION['user_id'],
            'requester_name' => $_SESSION['username'],
            'item_name' => sanitizeInput($_POST['item_name']),
            'quantity' => (int)$_POST['quantity'],
            'price' => floatval($_POST['price']),
            'description' => sanitizeInput($_POST['description']),
            'priority' => sanitizeInput($_POST['priority'])
        ];
        
        $request_id = createRequest($pdo, $data);
        
        if ($request_id) {
            // آپلود فایل‌ها
            if (!empty($_FILES['files']['name'][0])) {
                foreach ($_FILES['files']['name'] as $key => $name) {
                    if (!empty($name)) {
                        $file = [
                            'name' => $_FILES['files']['name'][$key],
                            'type' => $_FILES['files']['type'][$key],
                            'tmp_name' => $_FILES['files']['tmp_name'][$key],
                            'size' => $_FILES['files']['size'][$key]
                        ];
                        uploadRequestFile($pdo, $request_id, $file);
                    }
                }
            }
            
            // ایجاد گردش کار
            if (!empty($_POST['assignments'])) {
                $assignments = json_decode($_POST['assignments'], true);
                createRequestWorkflow($pdo, $request_id, $assignments);
            }
            
            $_SESSION['success_message'] = 'درخواست با موفقیت ایجاد شد!';
            header('Location: request_management.php');
            exit();
        } else {
            $error_message = 'خطا در ایجاد درخواست!';
        }
    }
}

// دریافت لیست کاربران
$users = [];
try {
    $stmt = $pdo->query("SELECT id, username, full_name, role FROM users WHERE is_active = 1 ORDER BY full_name");
    $users = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching users: " . $e->getMessage());
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

        .form-container {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .form-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 25px;
            color: white;
            text-align: center;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            font-weight: 600;
            margin-bottom: 8px;
            color: white;
            display: block;
        }

        .form-control {
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            padding: 12px 16px;
            color: white;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            color: white;
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }

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

        .file-upload-area {
            border: 2px dashed rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .file-upload-area:hover {
            border-color: var(--primary-color);
            background: rgba(102, 126, 234, 0.1);
        }

        .file-upload-area.dragover {
            border-color: var(--success-color);
            background: rgba(16, 185, 129, 0.1);
        }

        .assignment-item {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .remove-assignment {
            background: var(--danger-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .remove-assignment:hover {
            transform: scale(1.1);
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

        @media (max-width: 768px) {
            .main-container {
                margin: 10px;
                border-radius: 15px;
            }
            
            .content-area {
                padding: 20px;
            }
            
            .form-container {
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
                    <i class="fas fa-shopping-cart me-3"></i>
                    مدیریت درخواست‌های کالا/خدمات
                </h1>
                <p class="page-subtitle">
                    ایجاد درخواست جدید و مدیریت گردش کار
                </p>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content-area">
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Request Form -->
            <div class="form-container" data-aos="fade-up">
                <h3 class="form-title">
                    <i class="fas fa-plus-circle me-2"></i>
                    ایجاد درخواست جدید
                </h3>
                
                <form method="POST" enctype="multipart/form-data" id="requestForm">
                    <input type="hidden" name="action" value="create_request">
                    <?php echo csrf_field(); ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-box me-2"></i>
                                    نام آیتم
                                </label>
                                <input type="text" class="form-control" name="item_name" required 
                                       placeholder="نام کالا یا خدمت مورد نیاز">
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-hashtag me-2"></i>
                                    تعداد
                                </label>
                                <input type="number" class="form-control" name="quantity" required 
                                       placeholder="تعداد" min="1">
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-dollar-sign me-2"></i>
                                    قیمت (ریال)
                                </label>
                                <input type="number" class="form-control" name="price" 
                                       placeholder="قیمت تخمینی">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-align-right me-2"></i>
                            توضیحات
                        </label>
                        <textarea class="form-control" name="description" rows="4" 
                                  placeholder="توضیحات کامل درخواست..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            اولویت
                        </label>
                        <select class="form-control" name="priority" required>
                            <option value="کم">کم</option>
                            <option value="متوسط" selected>متوسط</option>
                            <option value="بالا">بالا</option>
                            <option value="فوری">فوری</option>
                        </select>
                    </div>
                    
                    <!-- File Upload -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-paperclip me-2"></i>
                            فایل‌های ضمیمه
                        </label>
                        <div class="file-upload-area" id="fileUploadArea">
                            <i class="fas fa-cloud-upload-alt fa-3x mb-3" style="color: var(--primary-color);"></i>
                            <p class="mb-2">فایل‌ها را اینجا بکشید یا کلیک کنید</p>
                            <p class="text-muted small">پشتیبانی از: JPG, PNG, PDF, DOC, XLS</p>
                            <input type="file" name="files[]" multiple accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx" 
                                   id="fileInput" style="display: none;">
                        </div>
                        <div id="fileList" class="mt-3"></div>
                    </div>
                    
                    <!-- User Assignments -->
                    <div class="form-group">
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
                            <div class="col-md-6">
                                <button type="button" class="btn btn-primary" onclick="addAssignment()">
                                    <i class="fas fa-plus me-2"></i>
                                    اضافه کردن
                                </button>
                            </div>
                        </div>
                        <div id="assignmentsList" class="mt-3"></div>
                        <input type="hidden" name="assignments" id="assignmentsInput">
                    </div>
                    
                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-paper-plane me-2"></i>
                            ارسال درخواست
                        </button>
                    </div>
                </form>
            </div>
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

        let assignments = [];

        // File upload handling
        const fileUploadArea = document.getElementById('fileUploadArea');
        const fileInput = document.getElementById('fileInput');
        const fileList = document.getElementById('fileList');

        fileUploadArea.addEventListener('click', () => fileInput.click());
        fileUploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            fileUploadArea.classList.add('dragover');
        });
        fileUploadArea.addEventListener('dragleave', () => {
            fileUploadArea.classList.remove('dragover');
        });
        fileUploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            fileUploadArea.classList.remove('dragover');
            fileInput.files = e.dataTransfer.files;
            updateFileList();
        });

        fileInput.addEventListener('change', updateFileList);

        function updateFileList() {
            fileList.innerHTML = '';
            Array.from(fileInput.files).forEach((file, index) => {
                const fileItem = document.createElement('div');
                fileItem.className = 'd-flex align-items-center justify-content-between bg-light p-2 rounded mb-2';
                fileItem.innerHTML = `
                    <div>
                        <i class="fas fa-file me-2"></i>
                        <span>${file.name}</span>
                        <small class="text-muted ms-2">(${(file.size / 1024).toFixed(1)} KB)</small>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeFile(${index})">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                fileList.appendChild(fileItem);
            });
        }

        function removeFile(index) {
            const dt = new DataTransfer();
            Array.from(fileInput.files).forEach((file, i) => {
                if (i !== index) dt.items.add(file);
            });
            fileInput.files = dt.files;
            updateFileList();
        }

        // Assignment handling
        function addAssignment() {
            const userSelect = document.getElementById('userSelect');
            const selectedOption = userSelect.options[userSelect.selectedIndex];
            
            if (!selectedOption.value) {
                alert('لطفاً کاربری را انتخاب کنید');
                return;
            }

            const userId = selectedOption.value;
            const userName = selectedOption.dataset.name;
            const userRole = selectedOption.dataset.role;

            // Check if already assigned
            if (assignments.find(a => a.user_id === userId)) {
                alert('این کاربر قبلاً اضافه شده است');
                return;
            }

            assignments.push({
                user_id: userId,
                user_name: userName,
                department: userRole
            });

            updateAssignmentsList();
            updateAssignmentsInput();
            userSelect.selectedIndex = 0;
        }

        function updateAssignmentsList() {
            const assignmentsList = document.getElementById('assignmentsList');
            assignmentsList.innerHTML = '';

            assignments.forEach((assignment, index) => {
                const assignmentItem = document.createElement('div');
                assignmentItem.className = 'assignment-item';
                assignmentItem.innerHTML = `
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <strong>${assignment.user_name}</strong>
                            <span class="badge bg-secondary ms-2">${assignment.department}</span>
                        </div>
                        <button type="button" class="remove-assignment" onclick="removeAssignment(${index})">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
                assignmentsList.appendChild(assignmentItem);
            });
        }

        function removeAssignment(index) {
            assignments.splice(index, 1);
            updateAssignmentsList();
            updateAssignmentsInput();
        }

        function updateAssignmentsInput() {
            document.getElementById('assignmentsInput').value = JSON.stringify(assignments);
        }

        // Form submission
        document.getElementById('requestForm').addEventListener('submit', function(e) {
            if (assignments.length === 0) {
                e.preventDefault();
                alert('لطفاً حداقل یک کاربر را برای بررسی درخواست انتخاب کنید');
                return;
            }
        });
    </script>
</body>
</html>