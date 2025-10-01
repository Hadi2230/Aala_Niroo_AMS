<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';
include 'navbar.php';

$is_admin = ($_SESSION['role'] ?? '') === 'ادمین';
$action = $_GET['action'] ?? 'list';

// پردازش آپلود فرم جدید
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'upload' && $is_admin) {
    verifyCsrfToken();
    
    try {
        $title = sanitizeInput($_POST['title']);
        $description = sanitizeInput($_POST['description']);
        $category_id = (int)$_POST['category_id'];
        
        if (empty($title) || !isset($_FILES['form_file'])) {
            throw new Exception('لطفاً تمام فیلدهای الزامی را پر کنید');
        }
        
        // آپلود فایل
        $allowed_types = ['pdf', 'doc', 'docx', 'xls', 'xlsx'];
        $file_path = uploadFile($_FILES['form_file'], __DIR__ . '/uploads/learning/forms/', $allowed_types);
        
        // ذخیره در دیتابیس
        $stmt = $pdo->prepare("INSERT INTO company_forms (title, description, file_name, file_path, file_size, file_type, category_id, uploaded_by) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $title,
            $description,
            $_FILES['form_file']['name'],
            $file_path,
            $_FILES['form_file']['size'],
            pathinfo($_FILES['form_file']['name'], PATHINFO_EXTENSION),
            $category_id ?: null,
            $_SESSION['user_id']
        ]);
        
        logAction($pdo, 'form_upload', "آپلود فرم: $title");
        $success_message = 'فرم با موفقیت آپلود شد';
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// پردازش دانلود فرم
if ($action === 'download' && isset($_GET['id'])) {
    $form_id = (int)$_GET['id'];
    
    $stmt = $pdo->prepare("SELECT * FROM company_forms WHERE id = ? AND is_active = 1");
    $stmt->execute([$form_id]);
    $form = $stmt->fetch();
    
    if ($form && file_exists($form['file_path'])) {
        // افزایش شمارنده دانلود
        $pdo->prepare("UPDATE company_forms SET download_count = download_count + 1 WHERE id = ?")->execute([$form_id]);
        
        // ارسال فایل
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $form['file_name'] . '"');
        header('Content-Length: ' . filesize($form['file_path']));
        readfile($form['file_path']);
        exit();
    }
}

// حذف فرم (فقط ادمین)
if ($action === 'delete' && isset($_GET['id']) && $is_admin) {
    $form_id = (int)$_GET['id'];
    
    $stmt = $pdo->prepare("SELECT * FROM company_forms WHERE id = ?");
    $stmt->execute([$form_id]);
    $form = $stmt->fetch();
    
    if ($form) {
        // حذف فایل
        if (file_exists($form['file_path'])) {
            unlink($form['file_path']);
        }
        
        // حذف از دیتابیس
        $pdo->prepare("DELETE FROM company_forms WHERE id = ?")->execute([$form_id]);
        
        logAction($pdo, 'form_delete', "حذف فرم: " . $form['title']);
        $success_message = 'فرم با موفقیت حذف شد';
    }
}

// دریافت دسته‌بندی‌ها
$categories = $pdo->query("SELECT * FROM learning_categories WHERE type = 'forms' ORDER BY name")->fetchAll();

// دریافت فرم‌ها
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

$stmt = $pdo->prepare("SELECT cf.*, lc.name as category_name, u.username as uploader_name 
                      FROM company_forms cf 
                      LEFT JOIN learning_categories lc ON cf.category_id = lc.id 
                      LEFT JOIN users u ON cf.uploaded_by = u.id 
                      WHERE $where_clause 
                      ORDER BY cf.created_at DESC");
$stmt->execute($params);
$forms = $stmt->fetchAll();
?>

<style>
    .forms-container {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        padding-top: 2rem;
    }
    
    .forms-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 20px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        overflow: hidden;
    }
    
    .form-item {
        background: white;
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        border-left: 4px solid #667eea;
    }
    
    .form-item:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    }
    
    .form-icon {
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
        margin-right: 1rem;
    }
    
    .upload-zone {
        border: 2px dashed #667eea;
        border-radius: 15px;
        padding: 3rem;
        text-align: center;
        background: rgba(102, 126, 234, 0.1);
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    .upload-zone:hover {
        border-color: #764ba2;
        background: rgba(118, 75, 162, 0.1);
    }
    
    .upload-zone.dragover {
        border-color: #43e97b;
        background: rgba(67, 233, 123, 0.1);
    }
    
    .btn-download {
        background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        border: none;
        border-radius: 10px;
        color: white;
        padding: 0.5rem 1rem;
        transition: all 0.3s ease;
    }
    
    .btn-download:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        color: white;
    }
    
    .btn-delete {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        border: none;
        border-radius: 10px;
        color: white;
        padding: 0.5rem 1rem;
        transition: all 0.3s ease;
    }
    
    .btn-delete:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        color: white;
    }
    
    .search-box {
        background: rgba(255, 255, 255, 0.9);
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        backdrop-filter: blur(10px);
    }
    
    .file-info {
        background: rgba(102, 126, 234, 0.1);
        border-radius: 10px;
        padding: 1rem;
        margin-top: 1rem;
    }
    
    .category-badge {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        color: white;
        padding: 0.3rem 0.8rem;
        border-radius: 20px;
        font-size: 0.8rem;
    }
    
    .stats-badge {
        background: rgba(0, 0, 0, 0.1);
        padding: 0.2rem 0.6rem;
        border-radius: 15px;
        font-size: 0.8rem;
        margin: 0 0.2rem;
    }
</style>

<div class="forms-container">
    <div class="container">
        <!-- هدر صفحه -->
        <div class="text-center mb-5">
            <h1 class="text-white display-5 mb-3">
                <i class="fas fa-file-alt"></i>
                فرم‌های شرکت
            </h1>
            <p class="text-white-50 lead">مجموعه کامل فرم‌های اداری و فنی شرکت اعلا نیرو</p>
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
                    <label class="form-label">جستجو در فرم‌ها</label>
                    <input type="text" name="search" class="form-control" placeholder="نام فرم یا توضیحات..." value="<?php echo htmlspecialchars($search); ?>">
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
        </div>

        <div class="row">
            <!-- لیست فرم‌ها -->
            <div class="col-lg-<?php echo ($action === 'upload' && $is_admin) ? '8' : '12'; ?>">
                <div class="forms-card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-list"></i>
                            فرم‌های موجود (<?php echo count($forms); ?>)
                        </h5>
                        <?php if ($is_admin): ?>
                            <a href="?action=upload" class="btn btn-light btn-sm">
                                <i class="fas fa-plus"></i> آپلود فرم جدید
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($forms)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">هیچ فرمی یافت نشد</h5>
                                <p class="text-muted">
                                    <?php if ($is_admin): ?>
                                        برای شروع، اولین فرم را آپلود کنید
                                    <?php else: ?>
                                        در حال حاضر فرمی برای نمایش وجود ندارد
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($forms as $form): ?>
                                <div class="form-item">
                                    <div class="d-flex align-items-start">
                                        <div class="form-icon">
                                            <i class="fas fa-file-<?php echo in_array($form['file_type'], ['pdf']) ? 'pdf' : 'alt'; ?>"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-2"><?php echo htmlspecialchars($form['title']); ?></h6>
                                            <?php if ($form['description']): ?>
                                                <p class="text-muted mb-2"><?php echo htmlspecialchars($form['description']); ?></p>
                                            <?php endif; ?>
                                            
                                            <div class="d-flex flex-wrap align-items-center mb-2">
                                                <?php if ($form['category_name']): ?>
                                                    <span class="category-badge me-2">
                                                        <i class="fas fa-tag"></i> <?php echo htmlspecialchars($form['category_name']); ?>
                                                    </span>
                                                <?php endif; ?>
                                                
                                                <span class="stats-badge">
                                                    <i class="fas fa-download"></i> <?php echo $form['download_count']; ?> دانلود
                                                </span>
                                                
                                                <span class="stats-badge">
                                                    <i class="fas fa-calendar"></i> <?php echo date('Y/m/d', strtotime($form['created_at'])); ?>
                                                </span>
                                                
                                                <span class="stats-badge">
                                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($form['uploader_name']); ?>
                                                </span>
                                            </div>
                                            
                                            <div class="file-info">
                                                <small class="text-muted">
                                                    <i class="fas fa-file"></i> <?php echo htmlspecialchars($form['file_name']); ?>
                                                    <span class="ms-2">
                                                        <i class="fas fa-hdd"></i> <?php echo number_format($form['file_size'] / 1024, 1); ?> KB
                                                    </span>
                                                </small>
                                            </div>
                                        </div>
                                        <div class="ms-3">
                                            <a href="?action=download&id=<?php echo $form['id']; ?>" class="btn btn-download btn-sm mb-2 d-block">
                                                <i class="fas fa-download"></i> دانلود
                                            </a>
                                            <?php if ($is_admin): ?>
                                                <a href="?action=delete&id=<?php echo $form['id']; ?>" 
                                                   class="btn btn-delete btn-sm d-block"
                                                   onclick="return confirm('آیا از حذف این فرم اطمینان دارید؟')">
                                                    <i class="fas fa-trash"></i> حذف
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- فرم آپلود (فقط برای ادمین) -->
            <?php if ($action === 'upload' && $is_admin): ?>
                <div class="col-lg-4">
                    <div class="forms-card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-upload"></i>
                                آپلود فرم جدید
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label">عنوان فرم *</label>
                                    <input type="text" name="title" class="form-control" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">توضیحات</label>
                                    <textarea name="description" class="form-control" rows="3"></textarea>
                                </div>
                                
                                <div class="mb-3">
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
                                
                                <div class="mb-3">
                                    <label class="form-label">فایل فرم *</label>
                                    <div class="upload-zone" id="uploadZone">
                                        <i class="fas fa-cloud-upload-alt fa-2x mb-3 text-primary"></i>
                                        <p>فایل را اینجا بکشید یا کلیک کنید</p>
                                        <small class="text-muted">فرمت‌های مجاز: PDF, DOC, DOCX, XLS, XLSX</small>
                                        <input type="file" name="form_file" id="formFile" class="d-none" accept=".pdf,.doc,.docx,.xls,.xlsx" required>
                                    </div>
                                    <div id="fileInfo" class="mt-2 d-none">
                                        <small class="text-success">
                                            <i class="fas fa-check"></i>
                                            <span id="fileName"></span>
                                        </small>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="fas fa-upload"></i> آپلود فرم
                                </button>
                                
                                <a href="?" class="btn btn-secondary w-100 mt-2">
                                    <i class="fas fa-arrow-left"></i> بازگشت
                                </a>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // مدیریت آپلود فایل
    document.addEventListener('DOMContentLoaded', function() {
        const uploadZone = document.getElementById('uploadZone');
        const fileInput = document.getElementById('formFile');
        const fileInfo = document.getElementById('fileInfo');
        const fileName = document.getElementById('fileName');
        
        if (uploadZone && fileInput) {
            // کلیک روی منطقه آپلود
            uploadZone.addEventListener('click', () => fileInput.click());
            
            // تغییر فایل
            fileInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    const file = this.files[0];
                    fileName.textContent = file.name;
                    fileInfo.classList.remove('d-none');
                    uploadZone.classList.add('border-success');
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
                    fileName.textContent = files[0].name;
                    fileInfo.classList.remove('d-none');
                    this.classList.add('border-success');
                }
            });
        }
        
        // انیمیشن ظاهر شدن فرم‌ها
        const formItems = document.querySelectorAll('.form-item');
        formItems.forEach((item, index) => {
            item.style.opacity = '0';
            item.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                item.style.transition = 'all 0.5s ease';
                item.style.opacity = '1';
                item.style.transform = 'translateY(0)';
            }, index * 100);
        });
    });
</script>

</body>
</html>