<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once __DIR__ . '/config.php';

// ایجاد جدول suppliers اگر وجود ندارد
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS suppliers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        supplier_code VARCHAR(50) UNIQUE NOT NULL,
        company_name VARCHAR(255) NOT NULL,
        contact_person VARCHAR(255),
        supplier_type ENUM('حقیقی', 'حقوقی') DEFAULT 'حقوقی',
        business_category VARCHAR(255),
        logo_path VARCHAR(500),
        
        -- اطلاعات تماس
        address TEXT,
        city VARCHAR(100),
        state VARCHAR(100),
        country VARCHAR(100) DEFAULT 'ایران',
        postal_code VARCHAR(20),
        phone VARCHAR(20),
        mobile VARCHAR(20),
        fax VARCHAR(20),
        email VARCHAR(255),
        website VARCHAR(255),
        linkedin VARCHAR(255),
        whatsapp VARCHAR(255),
        instagram VARCHAR(255),
        contact_person_name VARCHAR(255),
        contact_person_position VARCHAR(255),
        contact_person_phone VARCHAR(20),
        
        -- اطلاعات مالی
        bank_account VARCHAR(50),
        iban VARCHAR(50),
        bank_name VARCHAR(255),
        bank_branch VARCHAR(255),
        economic_code VARCHAR(50),
        national_id VARCHAR(50),
        registration_number VARCHAR(50),
        vat_number VARCHAR(50),
        payment_terms ENUM('نقدی', 'اعتباری', 'مدت‌دار') DEFAULT 'نقدی',
        
        -- اطلاعات محصولات
        main_products TEXT,
        brands TEXT,
        moq VARCHAR(100),
        lead_time VARCHAR(100),
        shipping_terms TEXT,
        
        -- مدارک
        business_license_path VARCHAR(500),
        quality_certificates TEXT,
        insurance_documents TEXT,
        other_documents TEXT,
        major_customers TEXT,
        
        -- ارزیابی
        quality_score DECIMAL(3,1) DEFAULT 0,
        cooperation_start_date DATE,
        satisfaction_level ENUM('عالی', 'خوب', 'متوسط', 'ضعیف') DEFAULT 'متوسط',
        complaints_count INT DEFAULT 0,
        importance_level ENUM('Critical', 'Preferred', 'Normal') DEFAULT 'Normal',
        internal_notes TEXT,
        
        status ENUM('فعال', 'غیرفعال', 'معلق') DEFAULT 'فعال',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        INDEX idx_supplier_code (supplier_code),
        INDEX idx_company_name (company_name),
        INDEX idx_supplier_type (supplier_type),
        INDEX idx_status (status),
        INDEX idx_importance_level (importance_level)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {
    $error_message = "خطا در ایجاد جدول: " . $e->getMessage();
}

// پردازش فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_supplier'])) {
        try {
            // تولید کد تامین‌کننده
            $supplier_code = 'SUP' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            $stmt = $pdo->prepare("INSERT INTO suppliers (
                supplier_code, company_name, contact_person, supplier_type, business_category,
                address, city, state, country, postal_code, phone, mobile, fax, email, website,
                linkedin, whatsapp, instagram, contact_person_name, contact_person_position, contact_person_phone,
                bank_account, iban, bank_name, bank_branch, economic_code, national_id, registration_number,
                vat_number, payment_terms, main_products, brands, moq, lead_time, shipping_terms,
                quality_score, cooperation_start_date, satisfaction_level, importance_level, internal_notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $supplier_code,
                $_POST['company_name'],
                $_POST['contact_person'],
                $_POST['supplier_type'],
                $_POST['business_category'],
                $_POST['address'],
                $_POST['city'],
                $_POST['state'],
                $_POST['country'],
                $_POST['postal_code'],
                $_POST['phone'],
                $_POST['mobile'],
                $_POST['fax'],
                $_POST['email'],
                $_POST['website'],
                $_POST['linkedin'],
                $_POST['whatsapp'],
                $_POST['instagram'],
                $_POST['contact_person_name'],
                $_POST['contact_person_position'],
                $_POST['contact_person_phone'],
                $_POST['bank_account'],
                $_POST['iban'],
                $_POST['bank_name'],
                $_POST['bank_branch'],
                $_POST['economic_code'],
                $_POST['national_id'],
                $_POST['registration_number'],
                $_POST['vat_number'],
                $_POST['payment_terms'],
                $_POST['main_products'],
                $_POST['brands'],
                $_POST['moq'],
                $_POST['lead_time'],
                $_POST['shipping_terms'],
                $_POST['quality_score'],
                $_POST['cooperation_start_date'],
                $_POST['satisfaction_level'],
                $_POST['importance_level'],
                $_POST['internal_notes']
            ]);
            
            $success_message = "تامین‌کننده با موفقیت اضافه شد!";
            
        } catch (Exception $e) {
            $error_message = "خطا در اضافه کردن تامین‌کننده: " . $e->getMessage();
        }
    }
}

// دریافت لیست تامین‌کنندگان
$suppliers = [];
$search = isset($_GET['search']) ? $_GET['search'] : '';
$filter_type = isset($_GET['type']) ? $_GET['type'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

try {
    $query = "SELECT * FROM suppliers WHERE 1=1";
    $params = [];
    
    if ($search) {
        $query .= " AND (company_name LIKE ? OR contact_person LIKE ? OR supplier_code LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    if ($filter_type) {
        $query .= " AND supplier_type = ?";
        $params[] = $filter_type;
    }
    
    if ($filter_status) {
        $query .= " AND status = ?";
        $params[] = $filter_status;
    }
    
    $query .= " ORDER BY created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $suppliers = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error_message = "خطا در دریافت لیست تامین‌کنندگان: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت تامین‌کنندگان - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #34495e;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --info-color: #3498db;
        }
        
        body {
            font-family: Vazirmatn, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding-top: 80px;
        }
        
        .main-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            margin: 20px 0;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 20px 25px;
        }
        
        .supplier-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .supplier-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
        }
        
        .supplier-logo {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #fff;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        .status-suspended { background: #fff3cd; color: #856404; }
        
        .importance-critical { border-left: 4px solid #dc3545; }
        .importance-preferred { border-left: 4px solid #ffc107; }
        .importance-normal { border-left: 4px solid #28a745; }
        
        .rating-stars {
            color: #ffc107;
        }
        
        .nav-tabs .nav-link {
            border: none;
            border-radius: 10px 10px 0 0;
            margin-left: 5px;
            background: #f8f9fa;
            color: #6c757d;
            transition: all 0.3s;
        }
        
        .nav-tabs .nav-link.active {
            background: var(--primary-color);
            color: white;
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(44, 62, 80, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 25px;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(44, 62, 80, 0.4);
        }
        
        .search-box {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .supplier-stats {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .stat-item {
            text-align: center;
            padding: 15px;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <?php if (file_exists('navbar.php')) include 'navbar.php'; ?>
    
    <div class="container">
        <div class="main-container">
            <div class="container mt-4">
                <!-- هدر صفحه -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2><i class="fas fa-truck me-2"></i>مدیریت تامین‌کنندگان</h2>
                        <p class="text-muted mb-0">مدیریت کامل اطلاعات تامین‌کنندگان و شرکای تجاری</p>
                    </div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
                        <i class="fas fa-plus me-1"></i>تامین‌کننده جدید
                    </button>
                </div>

                <!-- آمار کلی -->
                <div class="supplier-stats">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="stat-item">
                                <div class="stat-number"><?php echo count($suppliers); ?></div>
                                <div class="stat-label">کل تامین‌کنندگان</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-item">
                                <div class="stat-number"><?php echo count(array_filter($suppliers, fn($s) => $s['status'] === 'فعال')); ?></div>
                                <div class="stat-label">فعال</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-item">
                                <div class="stat-number"><?php echo count(array_filter($suppliers, fn($s) => $s['importance_level'] === 'Critical')); ?></div>
                                <div class="stat-label">حیاتی</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-item">
                                <div class="stat-number"><?php echo number_format(array_sum(array_column($suppliers, 'quality_score')) / max(count($suppliers), 1), 1); ?></div>
                                <div class="stat-label">میانگین امتیاز</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- جستجو و فیلتر -->
                <div class="search-box">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">جستجو</label>
                            <input type="text" class="form-control" name="search" placeholder="نام شرکت، شخص، کد..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">نوع</label>
                            <select class="form-select" name="type">
                                <option value="">همه</option>
                                <option value="حقیقی" <?php echo $filter_type === 'حقیقی' ? 'selected' : ''; ?>>حقیقی</option>
                                <option value="حقوقی" <?php echo $filter_type === 'حقوقی' ? 'selected' : ''; ?>>حقوقی</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">وضعیت</label>
                            <select class="form-select" name="status">
                                <option value="">همه</option>
                                <option value="فعال" <?php echo $filter_status === 'فعال' ? 'selected' : ''; ?>>فعال</option>
                                <option value="غیرفعال" <?php echo $filter_status === 'غیرفعال' ? 'selected' : ''; ?>>غیرفعال</option>
                                <option value="معلق" <?php echo $filter_status === 'معلق' ? 'selected' : ''; ?>>معلق</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search me-1"></i>جستجو
                            </button>
                        </div>
                    </form>
                </div>

                <!-- لیست تامین‌کنندگان -->
                <div class="row">
                    <?php foreach ($suppliers as $supplier): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card supplier-card importance-<?php echo strtolower($supplier['importance_level']); ?>" 
                                 data-bs-toggle="modal" data-bs-target="#supplierModal<?php echo $supplier['id']; ?>">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <img src="<?php echo $supplier['logo_path'] ?: 'https://via.placeholder.com/60x60?text=' . substr($supplier['company_name'], 0, 1); ?>" 
                                             class="supplier-logo me-3" alt="لوگو">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($supplier['company_name']); ?></h6>
                                            <small class="text-muted"><?php echo htmlspecialchars($supplier['supplier_code']); ?></small>
                                        </div>
                                        <span class="status-badge status-<?php echo strtolower($supplier['status']); ?>">
                                            <?php echo $supplier['status']; ?>
                                        </span>
                                    </div>
                                    
                                    <div class="mb-2">
                                        <small class="text-muted">زمینه فعالیت:</small>
                                        <div><?php echo htmlspecialchars($supplier['business_category'] ?: '-'); ?></div>
                                    </div>
                                    
                                    <div class="mb-2">
                                        <small class="text-muted">امتیاز کیفیت:</small>
                                        <div class="rating-stars">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star<?php echo $i <= $supplier['quality_score'] ? '' : '-o'; ?>"></i>
                                            <?php endfor; ?>
                                            <span class="ms-1">(<?php echo $supplier['quality_score']; ?>)</span>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            <i class="fas fa-calendar me-1"></i>
                                            <?php echo $supplier['cooperation_start_date'] ? jalali_format($supplier['cooperation_start_date']) : '-'; ?>
                                        </small>
                                        <span class="badge bg-<?php echo $supplier['importance_level'] === 'Critical' ? 'danger' : ($supplier['importance_level'] === 'Preferred' ? 'warning' : 'success'); ?>">
                                            <?php echo $supplier['importance_level']; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (empty($suppliers)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-truck fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">هیچ تامین‌کننده‌ای یافت نشد</h5>
                        <p class="text-muted">برای شروع، تامین‌کننده جدیدی اضافه کنید</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
                            <i class="fas fa-plus me-1"></i>تامین‌کننده جدید
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- مودال افزودن تامین‌کننده -->
    <div class="modal fade" id="addSupplierModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>تامین‌کننده جدید</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="addSupplierForm">
                        <ul class="nav nav-tabs" id="supplierTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic" type="button" role="tab">
                                    <i class="fas fa-info-circle me-1"></i>اطلاعات پایه
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="contact-tab" data-bs-toggle="tab" data-bs-target="#contact" type="button" role="tab">
                                    <i class="fas fa-phone me-1"></i>اطلاعات تماس
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="financial-tab" data-bs-toggle="tab" data-bs-target="#financial" type="button" role="tab">
                                    <i class="fas fa-credit-card me-1"></i>اطلاعات مالی
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="products-tab" data-bs-toggle="tab" data-bs-target="#products" type="button" role="tab">
                                    <i class="fas fa-box me-1"></i>محصولات و خدمات
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents" type="button" role="tab">
                                    <i class="fas fa-file-alt me-1"></i>مدارک
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="evaluation-tab" data-bs-toggle="tab" data-bs-target="#evaluation" type="button" role="tab">
                                    <i class="fas fa-star me-1"></i>ارزیابی
                                </button>
                            </li>
                        </ul>
                        
                        <div class="tab-content" id="supplierTabContent">
                            <!-- اطلاعات پایه -->
                            <div class="tab-pane fade show active" id="basic" role="tabpanel">
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <label class="form-label">نام شرکت / شخص <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="company_name" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">شخص رابط</label>
                                        <input type="text" class="form-control" name="contact_person">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">نوع تامین‌کننده <span class="text-danger">*</span></label>
                                        <select class="form-select" name="supplier_type" required>
                                            <option value="حقوقی">حقوقی</option>
                                            <option value="حقیقی">حقیقی</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">زمینه فعالیت</label>
                                        <input type="text" class="form-control" name="business_category" placeholder="مثال: تجهیزات الکترونیکی">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- اطلاعات تماس -->
                            <div class="tab-pane fade" id="contact" role="tabpanel">
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <label class="form-label">نشانی کامل</label>
                                        <textarea class="form-control" name="address" rows="3"></textarea>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">شهر</label>
                                        <input type="text" class="form-control" name="city">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">استان</label>
                                        <input type="text" class="form-control" name="state">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">کشور</label>
                                        <input type="text" class="form-control" name="country" value="ایران">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">کد پستی</label>
                                        <input type="text" class="form-control" name="postal_code">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">تلفن ثابت</label>
                                        <input type="text" class="form-control" name="phone">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">موبایل</label>
                                        <input type="text" class="form-control" name="mobile">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">فکس</label>
                                        <input type="text" class="form-control" name="fax">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">ایمیل</label>
                                        <input type="email" class="form-control" name="email">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">وب‌سایت</label>
                                        <input type="url" class="form-control" name="website">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">LinkedIn</label>
                                        <input type="url" class="form-control" name="linkedin">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">WhatsApp</label>
                                        <input type="text" class="form-control" name="whatsapp">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Instagram</label>
                                        <input type="url" class="form-control" name="instagram">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">نام شخص رابط</label>
                                        <input type="text" class="form-control" name="contact_person_name">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">سمت</label>
                                        <input type="text" class="form-control" name="contact_person_position">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">شماره تماس</label>
                                        <input type="text" class="form-control" name="contact_person_phone">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- اطلاعات مالی -->
                            <div class="tab-pane fade" id="financial" role="tabpanel">
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <label class="form-label">شماره حساب بانکی</label>
                                        <input type="text" class="form-control" name="bank_account">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">شماره شبا / IBAN</label>
                                        <input type="text" class="form-control" name="iban">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">نام بانک</label>
                                        <input type="text" class="form-control" name="bank_name">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">شعبه</label>
                                        <input type="text" class="form-control" name="bank_branch">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">کد اقتصادی</label>
                                        <input type="text" class="form-control" name="economic_code">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">شناسه ملی</label>
                                        <input type="text" class="form-control" name="national_id">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">شماره ثبت</label>
                                        <input type="text" class="form-control" name="registration_number">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">شماره مالیات بر ارزش افزوده</label>
                                        <input type="text" class="form-control" name="vat_number">
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">شرایط پرداخت</label>
                                        <select class="form-select" name="payment_terms">
                                            <option value="نقدی">نقدی</option>
                                            <option value="اعتباری">اعتباری</option>
                                            <option value="مدت‌دار">مدت‌دار</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- محصولات و خدمات -->
                            <div class="tab-pane fade" id="products" role="tabpanel">
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <label class="form-label">کالاها / خدمات اصلی</label>
                                        <textarea class="form-control" name="main_products" rows="3" placeholder="لیست کالاها و خدمات اصلی"></textarea>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">برندهای ارائه‌شده</label>
                                        <textarea class="form-control" name="brands" rows="2" placeholder="نام برندها"></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">حداقل مقدار سفارش (MOQ)</label>
                                        <input type="text" class="form-control" name="moq" placeholder="مثال: 100 عدد">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">زمان تحویل معمولی</label>
                                        <input type="text" class="form-control" name="lead_time" placeholder="مثال: 7-14 روز">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">شرایط ارسال / حمل و نقل</label>
                                        <textarea class="form-control" name="shipping_terms" rows="2" placeholder="شرایط ارسال و حمل و نقل"></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- مدارک -->
                            <div class="tab-pane fade" id="documents" role="tabpanel">
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <label class="form-label">گواهینامه‌های کیفیت</label>
                                        <textarea class="form-control" name="quality_certificates" rows="3" placeholder="ISO 9001, ISO 14001, ..."></textarea>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">بیمه / قراردادها / سایر مدارک</label>
                                        <textarea class="form-control" name="insurance_documents" rows="2" placeholder="مدارک بیمه و قراردادها"></textarea>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">سایر مدارک</label>
                                        <textarea class="form-control" name="other_documents" rows="2" placeholder="سایر مدارک قانونی"></textarea>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">لیست مشتریان مهم</label>
                                        <textarea class="form-control" name="major_customers" rows="2" placeholder="برای اعتبارسنجی"></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- ارزیابی -->
                            <div class="tab-pane fade" id="evaluation" role="tabpanel">
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <label class="form-label">امتیاز کیفیت (1-5)</label>
                                        <input type="number" class="form-control" name="quality_score" min="0" max="5" step="0.1" value="0">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">تاریخ شروع همکاری</label>
                                        <input type="date" class="form-control" name="cooperation_start_date">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">سطح رضایت</label>
                                        <select class="form-select" name="satisfaction_level">
                                            <option value="عالی">عالی</option>
                                            <option value="خوب">خوب</option>
                                            <option value="متوسط" selected>متوسط</option>
                                            <option value="ضعیف">ضعیف</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">سطح اهمیت</label>
                                        <select class="form-select" name="importance_level">
                                            <option value="Normal">عادی</option>
                                            <option value="Preferred">ترجیحی</option>
                                            <option value="Critical">حیاتی</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">یادداشت‌ها و توضیحات داخلی</label>
                                        <textarea class="form-control" name="internal_notes" rows="3" placeholder="یادداشت‌های داخلی"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" form="addSupplierForm" name="add_supplier" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>ذخیره تامین‌کننده
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // مدیریت تب‌ها
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('addSupplierForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    // اعتبارسنجی فیلدهای اجباری
                    const requiredFields = form.querySelectorAll('[required]');
                    let valid = true;
                    
                    requiredFields.forEach(field => {
                        if (!field.value.trim()) {
                            valid = false;
                            field.classList.add('is-invalid');
                        } else {
                            field.classList.remove('is-invalid');
                        }
                    });
                    
                    if (!valid) {
                        e.preventDefault();
                        alert('لطفاً فیلدهای اجباری را تکمیل کنید.');
                    }
                });
            }
        });
    </script>
</body>
</html>