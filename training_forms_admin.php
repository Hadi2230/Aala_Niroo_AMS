<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'ادمین') {
    header('Location: training.php');
    exit();
}

include 'config.php';

// پردازش آپلود فایل
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'upload' && isset($_FILES['form_file'])) {
        try {
            $title = sanitizeInput($_POST['title']);
            $description = sanitizeInput($_POST['description']);
            $category_id = $_POST['category_id'] ?: null;
            
            $file = $_FILES['form_file'];
            $allowed_types = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($file_ext, $allowed_types)) {
                throw new Exception('نوع فایل مجاز نیست. فقط PDF، Word، Excel و PowerPoint مجاز هستند.');
            }
            
            // بدون محدودیت حجمی در سطح برنامه — محدودیت‌ها توسط PHP/وب‌سرور کنترل می‌شود
            
            $upload_dir = __DIR__ . '/uploads/training/forms/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_name = time() . '_' . uniqid() . '_' . $file['name'];
            $file_path = $upload_dir . $file_name;
            
            if (!move_uploaded_file($file['tmp_name'], $file_path)) {
                throw new Exception('خطا در آپلود فایل');
            }
            
            // ذخیره در دیتابیس
            $stmt = $pdo->prepare("
                INSERT INTO training_forms (title, description, file_path, file_name, file_size, file_type, category_id, uploaded_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $title,
                $description,
                'uploads/training/forms/' . $file_name,
                $file['name'],
                $file['size'],
                $file_ext,
                $category_id,
                $_SESSION['user_id']
            ]);
            
            $_SESSION['success_message'] = 'فرم با موفقیت آپلود شد.';
            header('Location: training_forms_admin.php');
            exit();
            
        } catch (Exception $e) {
            $_SESSION['error_message'] = $e->getMessage();
        }
    } elseif ($_POST['action'] === 'delete' && isset($_POST['form_id'])) {
        try {
            $form_id = (int)$_POST['form_id'];
            
            // دریافت مسیر فایل
            $stmt = $pdo->prepare("SELECT file_path FROM training_forms WHERE id = ?");
            $stmt->execute([$form_id]);
            $form = $stmt->fetch();
            
            if ($form) {
                // حذف فایل فیزیکی
                $file_path = __DIR__ . '/' . $form['file_path'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
                
                // حذف از دیتابیس
                $stmt = $pdo->prepare("DELETE FROM training_forms WHERE id = ?");
                $stmt->execute([$form_id]);
                
                $_SESSION['success_message'] = 'فرم با موفقیت حذف شد.';
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'خطا در حذف فرم: ' . $e->getMessage();
        }
        
        header('Location: training_forms_admin.php');
        exit();
    } elseif ($_POST['action'] === 'toggle_status' && isset($_POST['form_id'])) {
        $form_id = (int)$_POST['form_id'];
        $stmt = $pdo->prepare("UPDATE training_forms SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$form_id]);
        
        header('Location: training_forms_admin.php');
        exit();
    }
}

// دریافت لیست فرم‌ها
$forms_query = "
    SELECT f.*, c.name as category_name, u.full_name as uploader_name
    FROM training_forms f
    LEFT JOIN training_categories c ON f.category_id = c.id
    LEFT JOIN users u ON f.uploaded_by = u.id
    ORDER BY f.created_at DESC
";
$forms = $pdo->query($forms_query)->fetchAll();

// دریافت دسته‌بندی‌ها
$categories = $pdo->query("SELECT * FROM training_categories WHERE is_active = 1 ORDER BY sort_order")->fetchAll();
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت فرم‌های آموزشی - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        .upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .upload-area:hover {
            border-color: #0d6efd;
            background: #e7f1ff;
        }
        
        .upload-area.dragover {
            border-color: #0d6efd;
            background: #cfe2ff;
        }
        
        .file-preview {
            margin-top: 20px;
            padding: 15px;
            background: white;
            border-radius: 8px;
            display: none;
        }
        
        .form-item {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .form-item:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .badge-status {
            padding: 5px 10px;
            border-radius: 15px;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .stats-card h3 {
            margin: 0;
            font-size: 2rem;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="fas fa-file-alt"></i> مدیریت فرم‌های آموزشی</h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">داشبورد</a></li>
                        <li class="breadcrumb-item"><a href="training.php">آموزش</a></li>
                        <li class="breadcrumb-item active">مدیریت فرم‌ها</li>
                    </ol>
                </nav>
            </div>
            <a href="training_forms.php" class="btn btn-outline-primary">
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
                    <h3><?php echo count($forms); ?></h3>
                    <p class="mb-0">کل فرم‌ها</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <h3><?php echo count(array_filter($forms, function($f) { return $f['is_active']; })); ?></h3>
                    <p class="mb-0">فرم‌های فعال</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <h3><?php echo array_sum(array_column($forms, 'download_count')); ?></h3>
                    <p class="mb-0">کل دانلودها</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <h3><?php echo round(array_sum(array_column($forms, 'file_size')) / (1024 * 1024), 2); ?> MB</h3>
                    <p class="mb-0">حجم کل</p>
                </div>
            </div>
        </div>

        <!-- فرم آپلود -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-upload"></i> آپلود فرم جدید</h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <input type="hidden" name="action" value="upload">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="title" class="form-label">عنوان فرم *</label>
                                <input type="text" class="form-control" id="title" name="title" required>
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
                            <div class="upload-area" id="uploadArea">
                                <i class="fas fa-cloud-upload-alt fa-3x mb-3 text-muted"></i>
                                <h5>فایل فرم را اینجا رها کنید</h5>
                                <p class="text-muted">یا کلیک کنید برای انتخاب فایل</p>
                                <input type="file" class="form-control d-none" id="form_file" name="form_file" 
                                       accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx" required>
                                <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('form_file').click()">
                                    <i class="fas fa-folder-open"></i> انتخاب فایل
                                </button>
                                
                                <div class="file-preview" id="filePreview">
                                    <h6><i class="fas fa-file"></i> <span id="fileName"></span></h6>
                                    <small class="text-muted">حجم: <span id="fileSize"></span></small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-end mt-3">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-upload"></i> آپلود فرم
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- لیست فرم‌ها -->
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fas fa-list"></i> لیست فرم‌های آپلود شده</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="formsTable">
                        <thead>
                            <tr>
                                <th>عنوان</th>
                                <th>دسته‌بندی</th>
                                <th>نوع فایل</th>
                                <th>حجم</th>
                                <th>دانلودها</th>
                                <th>آپلود کننده</th>
                                <th>تاریخ</th>
                                <th>وضعیت</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($forms as $form): ?>
                                <tr>
                                    <td>
                                        <i class="fas fa-file-<?php 
                                            $ft = $form['file_type'];
                                            $cls = 'alt';
                                            switch ($ft) {
                                                case 'pdf': $cls = 'pdf text-danger'; break;
                                                case 'doc':
                                                case 'docx': $cls = 'word text-primary'; break;
                                                case 'xls':
                                                case 'xlsx': $cls = 'excel text-success'; break;
                                                case 'ppt':
                                                case 'pptx': $cls = 'powerpoint text-warning'; break;
                                                default: $cls = 'alt';
                                            }
                                            echo $cls;
                                        ?>"></i>
                                        <?php echo htmlspecialchars($form['title']); ?>
                                    </td>
                                    <td>
                                        <?php if ($form['category_name']): ?>
                                            <span class="badge bg-info"><?php echo $form['category_name']; ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo strtoupper($form['file_type']); ?></span>
                                    </td>
                                    <td><?php echo round($form['file_size'] / 1024, 2); ?> KB</td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo $form['download_count']; ?></span>
                                    </td>
                                    <td><?php echo $form['uploader_name'] ?? 'ناشناس'; ?></td>
                                    <td><?php echo jalaliDate($form['created_at']); ?></td>
                                    <td>
                                        <?php if ($form['is_active']): ?>
                                            <span class="badge bg-success">فعال</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">غیرفعال</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?php echo $form['file_path']; ?>" class="btn btn-info" download>
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="form_id" value="<?php echo $form['id']; ?>">
                                                <button type="submit" class="btn btn-warning" 
                                                        title="<?php echo $form['is_active'] ? 'غیرفعال کردن' : 'فعال کردن'; ?>">
                                                    <i class="fas fa-toggle-<?php echo $form['is_active'] ? 'on' : 'off'; ?>"></i>
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline;" 
                                                  onsubmit="return confirm('آیا از حذف این فرم اطمینان دارید؟');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="form_id" value="<?php echo $form['id']; ?>">
                                                <button type="submit" class="btn btn-danger">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        // DataTable
        $(document).ready(function() {
            $('#formsTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fa.json'
                },
                order: [[6, 'desc']]
            });
        });
        
        // Drag and Drop
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('form_file');
        const filePreview = document.getElementById('filePreview');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');
        
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                showFilePreview(files[0]);
            }
        });
        
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                showFilePreview(e.target.files[0]);
            }
        });
        
        function showFilePreview(file) {
            fileName.textContent = file.name;
            fileSize.textContent = (file.size / 1024).toFixed(2) + ' KB';
            filePreview.style.display = 'block';
        }
    </script>
</body>
</html>