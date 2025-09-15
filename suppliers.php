<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once __DIR__ . '/config.php';

// بررسی embed mode
$is_embed = isset($_GET['embed']) && $_GET['embed'] == '1';

// تنظیمات اپلود فایل
$upload_base_dir = __DIR__ . '/uploads/suppliers/';
$upload_web_base = 'uploads/suppliers/';
$max_file_size = 10 * 1024 * 1024; // 10MB
$allowed_extensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif', 'txt', 'xls', 'xlsx'];

// ایجاد پوشه‌های اپلود
if (!file_exists($upload_base_dir)) {
    mkdir($upload_base_dir, 0755, true);
}
if (!file_exists($upload_base_dir . 'documents/')) {
    mkdir($upload_base_dir . 'documents/', 0755, true);
}
if (!file_exists($upload_base_dir . 'correspondences/')) {
    mkdir($upload_base_dir . 'correspondences/', 0755, true);
}

// ایجاد جداول مورد نیاز
try {
    // جدول suppliers
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
    
    // جدول مدارک تامین‌کنندگان
    $pdo->exec("CREATE TABLE IF NOT EXISTS supplier_documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        supplier_id INT NOT NULL,
        document_type ENUM('مجوز_فعالیت', 'پروانه_کسب', 'گواهینامه_کیفیت', 'بیمه', 'قرارداد', 'سایر') NOT NULL,
        document_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        file_size INT,
        file_type VARCHAR(100),
        upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        description TEXT,
        is_active BOOLEAN DEFAULT 1,
        INDEX idx_supplier_id (supplier_id),
        INDEX idx_document_type (document_type),
        FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // جدول مکاتبات تامین‌کنندگان
    $pdo->exec("CREATE TABLE IF NOT EXISTS supplier_correspondences (
        id INT AUTO_INCREMENT PRIMARY KEY,
        supplier_id INT NOT NULL,
        correspondence_type ENUM('ایمیل', 'نامه', 'فکس', 'تماس_تلفنی', 'جلسه', 'سایر') NOT NULL,
        subject VARCHAR(500) NOT NULL,
        content TEXT,
        correspondence_date DATE NOT NULL,
        file_path VARCHAR(500),
        file_name VARCHAR(255),
        file_size INT,
        file_type VARCHAR(100),
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_important BOOLEAN DEFAULT 0,
        INDEX idx_supplier_id (supplier_id),
        INDEX idx_correspondence_type (correspondence_type),
        INDEX idx_correspondence_date (correspondence_date),
        FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
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
                supplier_code, company_name, contact_person, supplier_type, business_category, logo_path,
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
                null, // logo_path - فعلاً null
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
            
            $supplier_id = $pdo->lastInsertId();
            
            // اپلود مدارک
            if (isset($_FILES['documents']) && !empty($_FILES['documents']['name'][0])) {
                foreach ($_FILES['documents']['name'] as $key => $filename) {
                    if ($_FILES['documents']['error'][$key] === UPLOAD_ERR_OK) {
                        $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                        if (in_array($file_extension, $allowed_extensions)) {
                            $new_filename = 'doc_' . $supplier_id . '_' . time() . '_' . $key . '.' . $file_extension;
                            $upload_path = $upload_base_dir . 'documents/' . $new_filename;
                            
                            if (move_uploaded_file($_FILES['documents']['tmp_name'][$key], $upload_path)) {
                                $stmt = $pdo->prepare("INSERT INTO supplier_documents (supplier_id, document_type, document_name, file_path, file_size, file_type, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
                                $stmt->execute([
                                    $supplier_id,
                                    $_POST['document_types'][$key] ?? 'سایر',
                                    $filename,
                                    $upload_web_base . 'documents/' . $new_filename,
                                    $_FILES['documents']['size'][$key],
                                    $_FILES['documents']['type'][$key],
                                    $_POST['document_descriptions'][$key] ?? ''
                                ]);
                            }
                        }
                    }
                }
            }
            
            // اپلود مکاتبات
            if (isset($_FILES['correspondences']) && !empty($_FILES['correspondences']['name'][0])) {
                foreach ($_FILES['correspondences']['name'] as $key => $filename) {
                    if ($_FILES['correspondences']['error'][$key] === UPLOAD_ERR_OK) {
                        $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                        if (in_array($file_extension, $allowed_extensions)) {
                            $new_filename = 'corr_' . $supplier_id . '_' . time() . '_' . $key . '.' . $file_extension;
                            $upload_path = $upload_base_dir . 'correspondences/' . $new_filename;
                            
                            if (move_uploaded_file($_FILES['correspondences']['tmp_name'][$key], $upload_path)) {
                                $stmt = $pdo->prepare("INSERT INTO supplier_correspondences (supplier_id, correspondence_type, subject, content, correspondence_date, file_path, file_name, file_size, file_type, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                                $stmt->execute([
                                    $supplier_id,
                                    $_POST['correspondence_types'][$key] ?? 'سایر',
                                    $_POST['correspondence_subjects'][$key] ?? 'مکاتبه',
                                    $_POST['correspondence_contents'][$key] ?? '',
                                    $_POST['correspondence_dates'][$key] ?? date('Y-m-d'),
                                    $upload_web_base . 'correspondences/' . $new_filename,
                                    $filename,
                                    $_FILES['correspondences']['size'][$key],
                                    $_FILES['correspondences']['type'][$key],
                                    $_SESSION['user_id']
                                ]);
                            }
                        }
                    }
                }
            }
            
            $success_message = "تامین‌کننده با موفقیت اضافه شد!";
            
        } catch (Exception $e) {
            $error_message = "خطا در اضافه کردن تامین‌کننده: " . $e->getMessage();
        }
    }
    
    // ویرایش تامین‌کننده
    if (isset($_POST['edit_supplier'])) {
        try {
            $supplier_id = (int)$_POST['supplier_id'];
            
            $stmt = $pdo->prepare("UPDATE suppliers SET 
                company_name = ?, contact_person = ?, supplier_type = ?, business_category = ?, logo_path = ?,
                address = ?, city = ?, state = ?, country = ?, postal_code = ?, phone = ?, mobile = ?, fax = ?, email = ?, website = ?,
                linkedin = ?, whatsapp = ?, instagram = ?, contact_person_name = ?, contact_person_position = ?, contact_person_phone = ?,
                bank_account = ?, iban = ?, bank_name = ?, bank_branch = ?, economic_code = ?, national_id = ?, registration_number = ?,
                vat_number = ?, payment_terms = ?, main_products = ?, brands = ?, moq = ?, lead_time = ?, shipping_terms = ?,
                quality_score = ?, cooperation_start_date = ?, satisfaction_level = ?, importance_level = ?, internal_notes = ?,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = ?");
            
            $stmt->execute([
                $_POST['company_name'],
                $_POST['contact_person'],
                $_POST['supplier_type'],
                $_POST['business_category'],
                null, // logo_path - فعلاً null
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
                $_POST['internal_notes'],
                $supplier_id
            ]);
            
            $success_message = "تامین‌کننده با موفقیت ویرایش شد!";
            
        } catch (Exception $e) {
            $error_message = "خطا در ویرایش تامین‌کننده: " . $e->getMessage();
        }
    }
    
    // حذف تامین‌کننده
    if (isset($_POST['delete_supplier'])) {
        try {
            $supplier_id = (int)$_POST['supplier_id'];
            
            // حذف فایل‌های مرتبط (اگر وجود داشته باشد)
            $supplier = $pdo->query("SELECT logo_path FROM suppliers WHERE id = $supplier_id")->fetch();
            if ($supplier && $supplier['logo_path'] && file_exists($supplier['logo_path'])) {
                unlink($supplier['logo_path']);
            }
            
            // حذف تامین‌کننده
            $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id = ?");
            $stmt->execute([$supplier_id]);
            
            $success_message = "تامین‌کننده با موفقیت حذف شد!";
            
        } catch (Exception $e) {
            $error_message = "خطا در حذف تامین‌کننده: " . $e->getMessage();
        }
    }
    
    // افزودن مدرک جدید
    if (isset($_POST['add_document'])) {
        try {
            $supplier_id = (int)$_POST['supplier_id'];
            $document_type = $_POST['document_type'];
            $document_description = $_POST['document_description'] ?? '';
            
            if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
                $file_extension = strtolower(pathinfo($_FILES['document_file']['name'], PATHINFO_EXTENSION));
                
                if (in_array($file_extension, $allowed_extensions)) {
                    $new_filename = 'doc_' . $supplier_id . '_' . time() . '.' . $file_extension;
                    $upload_path = $upload_base_dir . 'documents/' . $new_filename;
                    
                    if (move_uploaded_file($_FILES['document_file']['tmp_name'], $upload_path)) {
                        $stmt = $pdo->prepare("INSERT INTO supplier_documents (supplier_id, document_type, document_name, file_path, file_size, file_type, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $supplier_id,
                            $document_type,
                            $_FILES['document_file']['name'],
                            $upload_web_base . 'documents/' . $new_filename,
                            $_FILES['document_file']['size'],
                            $_FILES['document_file']['type'],
                            $document_description
                        ]);
                        
                        $success_message = "مدرک با موفقیت اضافه شد!";
                    } else {
                        $error_message = "خطا در آپلود فایل!";
                    }
                } else {
                    $error_message = "فرمت فایل مجاز نیست!";
                }
            } else {
                $error_message = "لطفاً فایل را انتخاب کنید!";
            }
            
        } catch (Exception $e) {
            $error_message = "خطا در افزودن مدرک: " . $e->getMessage();
        }
    }
    
    // افزودن مکاتبه جدید
    if (isset($_POST['add_correspondence'])) {
        try {
            $supplier_id = (int)$_POST['supplier_id'];
            $correspondence_type = $_POST['correspondence_type'];
            $correspondence_subject = $_POST['correspondence_subject'];
            $correspondence_content = $_POST['correspondence_content'] ?? '';
            $correspondence_date = $_POST['correspondence_date'];
            
            $file_path = null;
            $file_name = null;
            $file_size = null;
            $file_type = null;
            
            if (isset($_FILES['correspondence_file']) && $_FILES['correspondence_file']['error'] === UPLOAD_ERR_OK) {
                $file_extension = strtolower(pathinfo($_FILES['correspondence_file']['name'], PATHINFO_EXTENSION));
                
                if (in_array($file_extension, $allowed_extensions)) {
                    $new_filename = 'corr_' . $supplier_id . '_' . time() . '.' . $file_extension;
                    $upload_path = $upload_base_dir . 'correspondences/' . $new_filename;
                    
                    if (move_uploaded_file($_FILES['correspondence_file']['tmp_name'], $upload_path)) {
                        $file_path = $upload_web_base . 'correspondences/' . $new_filename;
                        $file_name = $_FILES['correspondence_file']['name'];
                        $file_size = $_FILES['correspondence_file']['size'];
                        $file_type = $_FILES['correspondence_file']['type'];
                    }
                }
            }
            
            $stmt = $pdo->prepare("INSERT INTO supplier_correspondences (supplier_id, correspondence_type, subject, content, correspondence_date, file_path, file_name, file_size, file_type, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $supplier_id,
                $correspondence_type,
                $correspondence_subject,
                $correspondence_content,
                $correspondence_date,
                $file_path,
                $file_name,
                $file_size,
                $file_type,
                $_SESSION['user_id']
            ]);
            
            $success_message = "مکاتبه با موفقیت اضافه شد!";
            
        } catch (Exception $e) {
            $error_message = "خطا در افزودن مکاتبه: " . $e->getMessage();
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
        
        <?php if ($is_embed): ?>
        body {
            background: #f8f9fa;
            padding-top: 20px;
        }
        .main-container {
            background: white;
            box-shadow: none;
        }
        <?php endif; ?>
        
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
        
        .info-item {
            margin-bottom: 1rem;
        }
        
        .info-label {
            font-weight: 600;
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
            display: block;
        }
        
        .info-value {
            color: #212529;
            font-size: 1rem;
            padding: 0.5rem;
            background-color: #f8f9fa;
            border-radius: 0.375rem;
            border: 1px solid #e9ecef;
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
        
        .nav-tabs {
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 20px;
        }
        
        .nav-tabs .nav-link {
            border: none;
            border-radius: 15px 15px 0 0;
            margin-left: 8px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            color: #495057;
            font-weight: 600;
            padding: 15px 25px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .nav-tabs .nav-link:hover {
            background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
            color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .nav-tabs .nav-link.active {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(44, 62, 80, 0.3);
        }
        
        .nav-tabs .nav-link.active::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #ffd700, #ffed4e);
        }
        
        .nav-tabs .nav-link i {
            margin-left: 8px;
            font-size: 1.1em;
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
    <?php if (!$is_embed && file_exists('navbar.php')) include 'navbar.php'; ?>
    
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
                            <div class="card supplier-card importance-<?php echo strtolower($supplier['importance_level']); ?>">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <img src="<?php echo $supplier['logo_path'] ?: 'https://via.placeholder.com/60x60?text=' . substr($supplier['company_name'], 0, 1); ?>" 
                                             class="supplier-logo me-3" alt="لوگو">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($supplier['company_name']); ?></h6>
                                            <small class="text-muted"><?php echo htmlspecialchars($supplier['supplier_code']); ?></small>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="status-badge status-<?php echo strtolower($supplier['status']); ?>">
                                                <?php echo $supplier['status']; ?>
                                            </span>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                                        data-bs-toggle="modal" data-bs-target="#supplierModal<?php echo $supplier['id']; ?>"
                                                        title="مشاهده جزئیات">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-warning" 
                                                        data-bs-toggle="modal" data-bs-target="#editSupplierModal<?php echo $supplier['id']; ?>"
                                                        title="ویرایش">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        data-bs-toggle="modal" data-bs-target="#deleteSupplierModal<?php echo $supplier['id']; ?>"
                                                        title="حذف">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
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
                    <form method="POST" id="addSupplierForm" enctype="multipart/form-data">
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
                                <button class="nav-link" id="evaluation-tab" data-bs-toggle="tab" data-bs-target="#evaluation" type="button" role="tab">
                                    <i class="fas fa-star me-1"></i>ارزیابی
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents" type="button" role="tab">
                                    <i class="fas fa-file-alt me-1"></i>مدارک
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="correspondences-tab" data-bs-toggle="tab" data-bs-target="#correspondences" type="button" role="tab">
                                    <i class="fas fa-envelope me-1"></i>مکاتبات
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
                            
                            <!-- مدارک -->
                            <div class="tab-pane fade" id="documents" role="tabpanel">
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <h5 class="mb-3">مدارک تامین‌کننده</h5>
                                        <div id="documents-container">
                                            <div class="document-item mb-3 p-3 border rounded">
                                                <div class="row">
                                                    <div class="col-md-3">
                                                        <label class="form-label">نوع مدرک</label>
                                                        <select class="form-select" name="document_types[]">
                                                            <option value="مجوز_فعالیت">مجوز فعالیت</option>
                                                            <option value="پروانه_کسب">پروانه کسب</option>
                                                            <option value="گواهینامه_کیفیت">گواهینامه کیفیت</option>
                                                            <option value="بیمه">بیمه</option>
                                                            <option value="قرارداد">قرارداد</option>
                                                            <option value="سایر">سایر</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label">فایل مدرک</label>
                                                        <input type="file" class="form-control" name="documents[]" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif,.txt,.xls,.xlsx">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label">توضیحات</label>
                                                        <input type="text" class="form-control" name="document_descriptions[]" placeholder="توضیحات مدرک">
                                                    </div>
                                                    <div class="col-md-1">
                                                        <label class="form-label">&nbsp;</label>
                                                        <button type="button" class="btn btn-danger btn-sm w-100" onclick="removeDocument(this)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-outline-primary" onclick="addDocument()">
                                            <i class="fas fa-plus me-1"></i>افزودن مدرک جدید
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- مکاتبات -->
                            <div class="tab-pane fade" id="correspondences" role="tabpanel">
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <h5 class="mb-3">مکاتبات تامین‌کننده</h5>
                                        <div id="correspondences-container">
                                            <div class="correspondence-item mb-3 p-3 border rounded">
                                                <div class="row">
                                                    <div class="col-md-3">
                                                        <label class="form-label">نوع مکاتبه</label>
                                                        <select class="form-select" name="correspondence_types[]">
                                                            <option value="ایمیل">ایمیل</option>
                                                            <option value="نامه">نامه</option>
                                                            <option value="فکس">فکس</option>
                                                            <option value="تماس_تلفنی">تماس تلفنی</option>
                                                            <option value="جلسه">جلسه</option>
                                                            <option value="سایر">سایر</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label">موضوع</label>
                                                        <input type="text" class="form-control" name="correspondence_subjects[]" placeholder="موضوع مکاتبه">
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label">تاریخ</label>
                                                        <input type="date" class="form-control" name="correspondence_dates[]" value="<?php echo date('Y-m-d'); ?>">
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label">فایل ضمیمه</label>
                                                        <input type="file" class="form-control" name="correspondences[]" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif,.txt,.xls,.xlsx">
                                                    </div>
                                                    <div class="col-md-1">
                                                        <label class="form-label">&nbsp;</label>
                                                        <button type="button" class="btn btn-danger btn-sm w-100" onclick="removeCorrespondence(this)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="row mt-2">
                                                    <div class="col-12">
                                                        <label class="form-label">محتوای مکاتبه</label>
                                                        <textarea class="form-control" name="correspondence_contents[]" rows="2" placeholder="محتوای مکاتبه"></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-outline-primary" onclick="addCorrespondence()">
                                            <i class="fas fa-plus me-1"></i>افزودن مکاتبه جدید
                                        </button>
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

    <!-- مودال‌های مشاهده، ویرایش و حذف برای هر تامین‌کننده -->
    <?php foreach ($suppliers as $supplier): ?>
    <!-- مودال مشاهده جزئیات تامین‌کننده -->
    <div class="modal fade" id="supplierModal<?php echo $supplier['id']; ?>" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">جزئیات تامین‌کننده: <?php echo htmlspecialchars($supplier['company_name']); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <ul class="nav nav-tabs" id="viewTabs<?php echo $supplier['id']; ?>" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#viewBasic<?php echo $supplier['id']; ?>" type="button">اطلاعات پایه</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#viewContact<?php echo $supplier['id']; ?>" type="button">اطلاعات تماس</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#viewFinancial<?php echo $supplier['id']; ?>" type="button">اطلاعات مالی</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#viewProducts<?php echo $supplier['id']; ?>" type="button">محصولات و خدمات</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#viewEvaluation<?php echo $supplier['id']; ?>" type="button">ارزیابی</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#viewDocuments<?php echo $supplier['id']; ?>" type="button">مدارک</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#viewCorrespondences<?php echo $supplier['id']; ?>" type="button">مکاتبات</button>
                        </li>
                    </ul>
                    <div class="tab-content mt-3">
                        <!-- اطلاعات پایه -->
                        <div class="tab-pane fade show active" id="viewBasic<?php echo $supplier['id']; ?>">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <label class="info-label">نام شرکت / شخص:</label>
                                        <div class="info-value"><?php echo htmlspecialchars($supplier['company_name']); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <label class="info-label">کد تامین‌کننده:</label>
                                        <div class="info-value"><span class="badge bg-primary"><?php echo htmlspecialchars($supplier['supplier_code']); ?></span></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <label class="info-label">شخص رابط:</label>
                                        <div class="info-value"><?php echo htmlspecialchars($supplier['contact_person'] ?: '-'); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <label class="info-label">نوع تامین‌کننده:</label>
                                        <div class="info-value">
                                            <span class="badge bg-<?php echo $supplier['supplier_type'] === 'حقوقی' ? 'info' : 'success'; ?>">
                                                <?php echo htmlspecialchars($supplier['supplier_type']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="info-item">
                                        <label class="info-label">زمینه فعالیت:</label>
                                        <div class="info-value"><?php echo htmlspecialchars($supplier['business_category'] ?: '-'); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- اطلاعات تماس -->
                        <div class="tab-pane fade" id="viewContact<?php echo $supplier['id']; ?>">
                            <div class="row">
                                <div class="col-12">
                                    <div class="info-item">
                                        <label class="info-label">آدرس کامل:</label>
                                        <div class="info-value"><?php echo htmlspecialchars($supplier['address'] ?: '-'); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="info-item">
                                        <label class="info-label">شهر:</label>
                                        <div class="info-value"><?php echo htmlspecialchars($supplier['city'] ?: '-'); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="info-item">
                                        <label class="info-label">استان:</label>
                                        <div class="info-value"><?php echo htmlspecialchars($supplier['state'] ?: '-'); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="info-item">
                                        <label class="info-label">کشور:</label>
                                        <div class="info-value"><?php echo htmlspecialchars($supplier['country'] ?: '-'); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <label class="info-label">تلفن ثابت:</label>
                                        <div class="info-value">
                                            <?php if ($supplier['phone']): ?>
                                                <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($supplier['phone']); ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <label class="info-label">موبایل:</label>
                                        <div class="info-value">
                                            <?php if ($supplier['mobile']): ?>
                                                <i class="fas fa-mobile-alt me-1"></i><?php echo htmlspecialchars($supplier['mobile']); ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <label class="info-label">ایمیل:</label>
                                        <div class="info-value">
                                            <?php if ($supplier['email']): ?>
                                                <i class="fas fa-envelope me-1"></i>
                                                <a href="mailto:<?php echo htmlspecialchars($supplier['email']); ?>"><?php echo htmlspecialchars($supplier['email']); ?></a>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <label class="info-label">وب‌سایت:</label>
                                        <div class="info-value">
                                            <?php if ($supplier['website']): ?>
                                                <i class="fas fa-globe me-1"></i>
                                                <a href="<?php echo htmlspecialchars($supplier['website']); ?>" target="_blank"><?php echo htmlspecialchars($supplier['website']); ?></a>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- اطلاعات مالی -->
                        <div class="tab-pane fade" id="viewFinancial<?php echo $supplier['id']; ?>">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <label class="info-label">شماره حساب بانکی:</label>
                                        <div class="info-value"><?php echo htmlspecialchars($supplier['bank_account'] ?: '-'); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <label class="info-label">شماره شبا / IBAN:</label>
                                        <div class="info-value"><?php echo htmlspecialchars($supplier['iban'] ?: '-'); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <label class="info-label">نام بانک:</label>
                                        <div class="info-value"><?php echo htmlspecialchars($supplier['bank_name'] ?: '-'); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <label class="info-label">شعبه:</label>
                                        <div class="info-value"><?php echo htmlspecialchars($supplier['bank_branch'] ?: '-'); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <label class="info-label">کد اقتصادی:</label>
                                        <div class="info-value"><?php echo htmlspecialchars($supplier['economic_code'] ?: '-'); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <label class="info-label">شماره ملی:</label>
                                        <div class="info-value"><?php echo htmlspecialchars($supplier['national_id'] ?: '-'); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- محصولات و خدمات -->
                        <div class="tab-pane fade" id="viewProducts<?php echo $supplier['id']; ?>">
                            <div class="row">
                                <div class="col-12">
                                    <div class="info-item">
                                        <label class="info-label">کالاها / خدمات اصلی:</label>
                                        <div class="info-value"><?php echo nl2br(htmlspecialchars($supplier['main_products'] ?: '-')); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <label class="info-label">برندهای ارائه‌شده:</label>
                                        <div class="info-value"><?php echo htmlspecialchars($supplier['brands'] ?: '-'); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <label class="info-label">حداقل مقدار سفارش (MOQ):</label>
                                        <div class="info-value"><?php echo htmlspecialchars($supplier['moq'] ?: '-'); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- ارزیابی -->
                        <div class="tab-pane fade" id="viewEvaluation<?php echo $supplier['id']; ?>">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <label class="info-label">امتیاز کیفیت:</label>
                                        <div class="info-value">
                                            <div class="d-flex align-items-center">
                                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                                    <i class="fas fa-star<?php echo $i <= $supplier['quality_score'] ? '' : '-o'; ?> text-warning me-1"></i>
                                                <?php endfor; ?>
                                                <span class="ms-2">(<?php echo $supplier['quality_score']; ?>/10)</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <label class="info-label">تاریخ شروع همکاری:</label>
                                        <div class="info-value"><?php echo $supplier['cooperation_start_date'] ? jalali_format($supplier['cooperation_start_date']) : '-'; ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <label class="info-label">سطح رضایت:</label>
                                        <div class="info-value">
                                            <span class="badge bg-<?php 
                                                echo $supplier['satisfaction_level'] === 'عالی' ? 'success' : 
                                                    ($supplier['satisfaction_level'] === 'خوب' ? 'info' : 
                                                    ($supplier['satisfaction_level'] === 'متوسط' ? 'warning' : 'danger')); 
                                            ?>">
                                                <?php echo htmlspecialchars($supplier['satisfaction_level']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <label class="info-label">سطح اهمیت:</label>
                                        <div class="info-value">
                                            <span class="badge bg-<?php 
                                                echo $supplier['importance_level'] === 'Critical' ? 'danger' : 
                                                    ($supplier['importance_level'] === 'Preferred' ? 'warning' : 'success'); 
                                            ?>">
                                                <?php echo htmlspecialchars($supplier['importance_level']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="info-item">
                                        <label class="info-label">یادداشت‌ها و توضیحات داخلی:</label>
                                        <div class="info-value"><?php echo nl2br(htmlspecialchars($supplier['internal_notes'] ?: '-')); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- مدارک -->
                        <div class="tab-pane fade" id="viewDocuments<?php echo $supplier['id']; ?>">
                            <div class="row">
                                <div class="col-12">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="mb-0">مدارک تامین‌کننده</h6>
                                        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addDocumentModal<?php echo $supplier['id']; ?>">
                                            <i class="fas fa-plus me-1"></i>افزودن مدرک جدید
                                        </button>
                                    </div>
                                    <?php
                                    // دریافت مدارک تامین‌کننده
                                    $documents_stmt = $pdo->prepare("SELECT * FROM supplier_documents WHERE supplier_id = ? ORDER BY upload_date DESC");
                                    $documents_stmt->execute([$supplier['id']]);
                                    $documents = $documents_stmt->fetchAll();
                                    ?>
                                    <?php if (count($documents) > 0): ?>
                                        <div class="row">
                                            <?php foreach ($documents as $doc): ?>
                                                <div class="col-md-6 mb-3">
                                                    <div class="card">
                                                        <div class="card-body">
                                                            <h6 class="card-title">
                                                                <i class="fas fa-file-alt me-2"></i>
                                                                <?php echo htmlspecialchars($doc['document_name']); ?>
                                                            </h6>
                                                            <p class="card-text">
                                                                <small class="text-muted">
                                                                    <strong>نوع:</strong> <?php echo htmlspecialchars($doc['document_type']); ?><br>
                                                                    <strong>تاریخ آپلود:</strong> <?php echo jalali_format($doc['upload_date']); ?><br>
                                                                    <strong>حجم:</strong> <?php echo number_format($doc['file_size'] / 1024, 2); ?> KB
                                                                </small>
                                                            </p>
                                                            <?php if ($doc['description']): ?>
                                                                <p class="card-text"><?php echo htmlspecialchars($doc['description']); ?></p>
                                                            <?php endif; ?>
                                                            <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" class="btn btn-primary btn-sm" target="_blank">
                                                                <i class="fas fa-download me-1"></i>دانلود
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-2"></i>
                                            هیچ مدرکی برای این تامین‌کننده ثبت نشده است.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- مکاتبات -->
                        <div class="tab-pane fade" id="viewCorrespondences<?php echo $supplier['id']; ?>">
                            <div class="row">
                                <div class="col-12">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="mb-0">مکاتبات تامین‌کننده</h6>
                                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCorrespondenceModal<?php echo $supplier['id']; ?>">
                                            <i class="fas fa-plus me-1"></i>افزودن مکاتبه جدید
                                        </button>
                                    </div>
                                    <?php
                                    // دریافت مکاتبات تامین‌کننده
                                    $correspondences_stmt = $pdo->prepare("SELECT * FROM supplier_correspondences WHERE supplier_id = ? ORDER BY correspondence_date DESC");
                                    $correspondences_stmt->execute([$supplier['id']]);
                                    $correspondences = $correspondences_stmt->fetchAll();
                                    ?>
                                    <?php if (count($correspondences) > 0): ?>
                                        <div class="row">
                                            <?php foreach ($correspondences as $corr): ?>
                                                <div class="col-12 mb-3">
                                                    <div class="card">
                                                        <div class="card-body">
                                                            <div class="d-flex justify-content-between align-items-start">
                                                                <h6 class="card-title">
                                                                    <i class="fas fa-envelope me-2"></i>
                                                                    <?php echo htmlspecialchars($corr['subject']); ?>
                                                                </h6>
                                                                <span class="badge bg-<?php 
                                                                    echo $corr['correspondence_type'] === 'ایمیل' ? 'primary' : 
                                                                        ($corr['correspondence_type'] === 'نامه' ? 'info' : 
                                                                        ($corr['correspondence_type'] === 'فکس' ? 'warning' : 
                                                                        ($corr['correspondence_type'] === 'تماس_تلفنی' ? 'success' : 
                                                                        ($corr['correspondence_type'] === 'جلسه' ? 'danger' : 'secondary')))); 
                                                                ?>">
                                                                    <?php echo htmlspecialchars($corr['correspondence_type']); ?>
                                                                </span>
                                                            </div>
                                                            <p class="card-text">
                                                                <small class="text-muted">
                                                                    <strong>تاریخ:</strong> <?php echo jalali_format($corr['correspondence_date']); ?><br>
                                                                    <?php if ($corr['file_name']): ?>
                                                                        <strong>فایل ضمیمه:</strong> <?php echo htmlspecialchars($corr['file_name']); ?>
                                                                    <?php endif; ?>
                                                                </small>
                                                            </p>
                                                            <?php if ($corr['content']): ?>
                                                                <p class="card-text"><?php echo nl2br(htmlspecialchars($corr['content'])); ?></p>
                                                            <?php endif; ?>
                                                            <?php if ($corr['file_path']): ?>
                                                                <a href="<?php echo htmlspecialchars($corr['file_path']); ?>" class="btn btn-outline-primary btn-sm" target="_blank">
                                                                    <i class="fas fa-download me-1"></i>دانلود فایل
                                                                </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-2"></i>
                                            هیچ مکاتبه‌ای برای این تامین‌کننده ثبت نشده است.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                    <button type="button" class="btn btn-warning" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#editSupplierModal<?php echo $supplier['id']; ?>">
                        <i class="fas fa-edit me-1"></i>ویرایش
                    </button>
                </div>
            </div>
        </div>
    </div>
    <!-- مودال ویرایش تامین‌کننده -->
    <div class="modal fade" id="editSupplierModal<?php echo $supplier['id']; ?>" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">ویرایش تامین‌کننده: <?php echo htmlspecialchars($supplier['company_name']); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editSupplierForm<?php echo $supplier['id']; ?>">
                    <input type="hidden" name="supplier_id" value="<?php echo $supplier['id']; ?>">
                    <div class="modal-body">
                        <ul class="nav nav-tabs" id="editTabs<?php echo $supplier['id']; ?>" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#editBasic<?php echo $supplier['id']; ?>" type="button">اطلاعات پایه</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#editContact<?php echo $supplier['id']; ?>" type="button">اطلاعات تماس</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#editFinancial<?php echo $supplier['id']; ?>" type="button">اطلاعات مالی</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#editProducts<?php echo $supplier['id']; ?>" type="button">محصولات و خدمات</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#editEvaluation<?php echo $supplier['id']; ?>" type="button">ارزیابی</button>
                            </li>
                        </ul>
                        <div class="tab-content mt-3">
                            <!-- اطلاعات پایه -->
                            <div class="tab-pane fade show active" id="editBasic<?php echo $supplier['id']; ?>">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label">نام شرکت / شخص *</label>
                                        <input type="text" class="form-control" name="company_name" value="<?php echo htmlspecialchars($supplier['company_name']); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">شخص رابط</label>
                                        <input type="text" class="form-control" name="contact_person" value="<?php echo htmlspecialchars($supplier['contact_person']); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">نوع تامین‌کننده *</label>
                                        <select class="form-select" name="supplier_type" required>
                                            <option value="حقوقی" <?php echo $supplier['supplier_type'] === 'حقوقی' ? 'selected' : ''; ?>>حقوقی</option>
                                            <option value="حقیقی" <?php echo $supplier['supplier_type'] === 'حقیقی' ? 'selected' : ''; ?>>حقیقی</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">زمینه فعالیت</label>
                                        <input type="text" class="form-control" name="business_category" value="<?php echo htmlspecialchars($supplier['business_category']); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- اطلاعات تماس -->
                            <div class="tab-pane fade" id="editContact<?php echo $supplier['id']; ?>">
                                <div class="row">
                                    <div class="col-12">
                                        <label class="form-label">آدرس کامل</label>
                                        <textarea class="form-control" name="address" rows="3"><?php echo htmlspecialchars($supplier['address']); ?></textarea>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">شهر</label>
                                        <input type="text" class="form-control" name="city" value="<?php echo htmlspecialchars($supplier['city']); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">استان</label>
                                        <input type="text" class="form-control" name="state" value="<?php echo htmlspecialchars($supplier['state']); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">کشور</label>
                                        <input type="text" class="form-control" name="country" value="<?php echo htmlspecialchars($supplier['country']); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">تلفن ثابت</label>
                                        <input type="text" class="form-control" name="phone" value="<?php echo htmlspecialchars($supplier['phone']); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">موبایل</label>
                                        <input type="text" class="form-control" name="mobile" value="<?php echo htmlspecialchars($supplier['mobile']); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">ایمیل</label>
                                        <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($supplier['email']); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">وب‌سایت</label>
                                        <input type="url" class="form-control" name="website" value="<?php echo htmlspecialchars($supplier['website']); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- اطلاعات مالی -->
                            <div class="tab-pane fade" id="editFinancial<?php echo $supplier['id']; ?>">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label">شماره حساب بانکی</label>
                                        <input type="text" class="form-control" name="bank_account" value="<?php echo htmlspecialchars($supplier['bank_account']); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">شماره شبا / IBAN</label>
                                        <input type="text" class="form-control" name="iban" value="<?php echo htmlspecialchars($supplier['iban']); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">نام بانک</label>
                                        <input type="text" class="form-control" name="bank_name" value="<?php echo htmlspecialchars($supplier['bank_name']); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">شعبه</label>
                                        <input type="text" class="form-control" name="bank_branch" value="<?php echo htmlspecialchars($supplier['bank_branch']); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">کد اقتصادی</label>
                                        <input type="text" class="form-control" name="economic_code" value="<?php echo htmlspecialchars($supplier['economic_code']); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">شماره ملی</label>
                                        <input type="text" class="form-control" name="national_id" value="<?php echo htmlspecialchars($supplier['national_id']); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- محصولات و خدمات -->
                            <div class="tab-pane fade" id="editProducts<?php echo $supplier['id']; ?>">
                                <div class="row">
                                    <div class="col-12">
                                        <label class="form-label">کالاها / خدمات اصلی</label>
                                        <textarea class="form-control" name="main_products" rows="3"><?php echo htmlspecialchars($supplier['main_products']); ?></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">برندهای ارائه‌شده</label>
                                        <input type="text" class="form-control" name="brands" value="<?php echo htmlspecialchars($supplier['brands']); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">حداقل مقدار سفارش (MOQ)</label>
                                        <input type="text" class="form-control" name="moq" value="<?php echo htmlspecialchars($supplier['moq']); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- ارزیابی -->
                            <div class="tab-pane fade" id="editEvaluation<?php echo $supplier['id']; ?>">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label">امتیاز کیفیت (0-10)</label>
                                        <input type="number" class="form-control" name="quality_score" min="0" max="10" step="0.1" value="<?php echo $supplier['quality_score']; ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">تاریخ شروع همکاری</label>
                                        <input type="date" class="form-control" name="cooperation_start_date" value="<?php echo $supplier['cooperation_start_date']; ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">سطح رضایت</label>
                                        <select class="form-select" name="satisfaction_level">
                                            <option value="عالی" <?php echo $supplier['satisfaction_level'] === 'عالی' ? 'selected' : ''; ?>>عالی</option>
                                            <option value="خوب" <?php echo $supplier['satisfaction_level'] === 'خوب' ? 'selected' : ''; ?>>خوب</option>
                                            <option value="متوسط" <?php echo $supplier['satisfaction_level'] === 'متوسط' ? 'selected' : ''; ?>>متوسط</option>
                                            <option value="ضعیف" <?php echo $supplier['satisfaction_level'] === 'ضعیف' ? 'selected' : ''; ?>>ضعیف</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">سطح اهمیت</label>
                                        <select class="form-select" name="importance_level">
                                            <option value="Normal" <?php echo $supplier['importance_level'] === 'Normal' ? 'selected' : ''; ?>>عادی</option>
                                            <option value="Preferred" <?php echo $supplier['importance_level'] === 'Preferred' ? 'selected' : ''; ?>>ترجیحی</option>
                                            <option value="Critical" <?php echo $supplier['importance_level'] === 'Critical' ? 'selected' : ''; ?>>حیاتی</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">یادداشت‌ها و توضیحات داخلی</label>
                                        <textarea class="form-control" name="internal_notes" rows="3"><?php echo htmlspecialchars($supplier['internal_notes']); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" name="edit_supplier" class="btn btn-warning">
                            <i class="fas fa-save me-1"></i>ویرایش تامین‌کننده
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- مودال حذف تامین‌کننده -->
    <div class="modal fade" id="deleteSupplierModal<?php echo $supplier['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">حذف تامین‌کننده</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        آیا مطمئن هستید که می‌خواهید تامین‌کننده زیر را حذف کنید؟
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title"><?php echo htmlspecialchars($supplier['company_name']); ?></h6>
                            <p class="card-text">
                                <strong>کد:</strong> <?php echo htmlspecialchars($supplier['supplier_code']); ?><br>
                                <strong>نوع:</strong> <?php echo htmlspecialchars($supplier['supplier_type']); ?><br>
                                <strong>زمینه فعالیت:</strong> <?php echo htmlspecialchars($supplier['business_category'] ?: '-'); ?>
                            </p>
                        </div>
                    </div>
                    <div class="alert alert-danger mt-3">
                        <i class="fas fa-info-circle me-2"></i>
                        این عمل قابل بازگشت نیست و تمام اطلاعات مربوط به این تامین‌کننده حذف خواهد شد.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="supplier_id" value="<?php echo $supplier['id']; ?>">
                        <button type="submit" name="delete_supplier" class="btn btn-danger">
                            <i class="fas fa-trash me-1"></i>حذف تامین‌کننده
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

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
    // توابع مدیریت مدارک
    function addDocument() {
        const container = document.getElementById('documents-container');
        const newDocument = document.createElement('div');
        newDocument.className = 'document-item mb-3 p-3 border rounded';
        newDocument.innerHTML = `
            <div class="row">
                <div class="col-md-3">
                    <label class="form-label">نوع مدرک</label>
                    <select class="form-select" name="document_types[]">
                        <option value="مجوز_فعالیت">مجوز فعالیت</option>
                        <option value="پروانه_کسب">پروانه کسب</option>
                        <option value="گواهینامه_کیفیت">گواهینامه کیفیت</option>
                        <option value="بیمه">بیمه</option>
                        <option value="قرارداد">قرارداد</option>
                        <option value="سایر">سایر</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">فایل مدرک</label>
                    <input type="file" class="form-control" name="documents[]" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif,.txt,.xls,.xlsx">
                </div>
                <div class="col-md-4">
                    <label class="form-label">توضیحات</label>
                    <input type="text" class="form-control" name="document_descriptions[]" placeholder="توضیحات مدرک">
                </div>
                <div class="col-md-1">
                    <label class="form-label">&nbsp;</label>
                    <button type="button" class="btn btn-danger btn-sm w-100" onclick="removeDocument(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
        container.appendChild(newDocument);
    }
    
    function removeDocument(button) {
        button.closest('.document-item').remove();
    }
    
    // توابع مدیریت مکاتبات
    function addCorrespondence() {
        const container = document.getElementById('correspondences-container');
        const newCorrespondence = document.createElement('div');
        newCorrespondence.className = 'correspondence-item mb-3 p-3 border rounded';
        newCorrespondence.innerHTML = `
            <div class="row">
                <div class="col-md-3">
                    <label class="form-label">نوع مکاتبه</label>
                    <select class="form-select" name="correspondence_types[]">
                        <option value="ایمیل">ایمیل</option>
                        <option value="نامه">نامه</option>
                        <option value="فکس">فکس</option>
                        <option value="تماس_تلفنی">تماس تلفنی</option>
                        <option value="جلسه">جلسه</option>
                        <option value="سایر">سایر</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">موضوع</label>
                    <input type="text" class="form-control" name="correspondence_subjects[]" placeholder="موضوع مکاتبه">
                </div>
                <div class="col-md-2">
                    <label class="form-label">تاریخ</label>
                    <input type="date" class="form-control" name="correspondence_dates[]" value="${new Date().toISOString().split('T')[0]}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">فایل ضمیمه</label>
                    <input type="file" class="form-control" name="correspondences[]" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif,.txt,.xls,.xlsx">
                </div>
                <div class="col-md-1">
                    <label class="form-label">&nbsp;</label>
                    <button type="button" class="btn btn-danger btn-sm w-100" onclick="removeCorrespondence(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <div class="row mt-2">
                <div class="col-12">
                    <label class="form-label">محتوای مکاتبه</label>
                    <textarea class="form-control" name="correspondence_contents[]" rows="2" placeholder="محتوای مکاتبه"></textarea>
                </div>
            </div>
        `;
        container.appendChild(newCorrespondence);
    }
    
    function removeCorrespondence(button) {
        button.closest('.correspondence-item').remove();
    }
    </script>

    <!-- مودال افزودن مدرک جدید -->
    <?php foreach ($suppliers as $supplier): ?>
    <div class="modal fade" id="addDocumentModal<?php echo $supplier['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">افزودن مدرک جدید</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="supplier_id" value="<?php echo $supplier['id']; ?>">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">نوع مدرک</label>
                                <select class="form-select" name="document_type" required>
                                    <option value="">انتخاب کنید</option>
                                    <option value="مجوز_فعالیت">مجوز فعالیت</option>
                                    <option value="پروانه_کسب">پروانه کسب</option>
                                    <option value="گواهینامه_کیفیت">گواهینامه کیفیت</option>
                                    <option value="بیمه">بیمه</option>
                                    <option value="قرارداد">قرارداد</option>
                                    <option value="سایر">سایر</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">فایل مدرک</label>
                                <input type="file" class="form-control" name="document_file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif,.txt,.xls,.xlsx" required>
                            </div>
                            <div class="col-12 mt-3">
                                <label class="form-label">توضیحات</label>
                                <textarea class="form-control" name="document_description" rows="3" placeholder="توضیحات مدرک"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" name="add_document" class="btn btn-success">
                            <i class="fas fa-save me-1"></i>ذخیره مدرک
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- مودال افزودن مکاتبه جدید -->
    <div class="modal fade" id="addCorrespondenceModal<?php echo $supplier['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">افزودن مکاتبه جدید</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="supplier_id" value="<?php echo $supplier['id']; ?>">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">نوع مکاتبه</label>
                                <select class="form-select" name="correspondence_type" required>
                                    <option value="">انتخاب کنید</option>
                                    <option value="ایمیل">ایمیل</option>
                                    <option value="نامه">نامه</option>
                                    <option value="فکس">فکس</option>
                                    <option value="تماس_تلفنی">تماس تلفنی</option>
                                    <option value="جلسه">جلسه</option>
                                    <option value="سایر">سایر</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">تاریخ</label>
                                <input type="date" class="form-control" name="correspondence_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-12 mt-3">
                                <label class="form-label">موضوع</label>
                                <input type="text" class="form-control" name="correspondence_subject" placeholder="موضوع مکاتبه" required>
                            </div>
                            <div class="col-12 mt-3">
                                <label class="form-label">محتوای مکاتبه</label>
                                <textarea class="form-control" name="correspondence_content" rows="4" placeholder="محتوای مکاتبه"></textarea>
                            </div>
                            <div class="col-12 mt-3">
                                <label class="form-label">فایل ضمیمه (اختیاری)</label>
                                <input type="file" class="form-control" name="correspondence_file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif,.txt,.xls,.xlsx">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" name="add_correspondence" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>ذخیره مکاتبه
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</body>
</html>