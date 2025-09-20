<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// ثبت لاگ مشاهده صفحه
logAction($pdo, 'VIEW_ASSIGNMENTS', 'مشاهده صفحه انتساب دستگاه‌ها');

// انتساب دستگاه به مشتری
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_asset'])) {
    $asset_id = $_POST['asset_id'];
    $customer_id = $_POST['customer_id'];
    $assignment_date = jalaliToGregorianForDB($_POST['assignment_date']);
    $notes = $_POST['notes'];

    try {
        $pdo->beginTransaction();
        
        // درج انتساب اصلی
        $stmt = $pdo->prepare("INSERT INTO asset_assignments (asset_id, customer_id, assignment_date, notes) VALUES (?, ?, ?, ?)");
        $stmt->execute([$asset_id, $customer_id, $assignment_date, $notes]);
        $assignment_id = $pdo->lastInsertId();
        
        // دریافت اطلاعات مشتری برای پر کردن خودکار فیلدها
        $stmt = $pdo->prepare("SELECT full_name, phone FROM customers WHERE id = ?");
        $stmt->execute([$customer_id]);
        $customer = $stmt->fetch();
        
        // ذخیره اطلاعات کامل انتساب
        $installation_date = jalaliToGregorianForDB($_POST['installation_date']);
        $delivery_person = $_POST['delivery_person'];
        $installation_address = $_POST['installation_address'];
        $warranty_start_date = jalaliToGregorianForDB($_POST['warranty_start_date']);
        $warranty_conditions = $_POST['warranty_conditions'];
        $employer_name = $customer['full_name']; // از اطلاعات مشتری
        $employer_phone = $customer['phone']; // از اطلاعات مشتری
        $recipient_name = $_POST['recipient_name'];
        $recipient_phone = $_POST['recipient_phone'];
        $installer_name = $_POST['installer_name'];
        $installation_start_date = jalaliToGregorianForDB($_POST['installation_start_date']);
        $installation_end_date = jalaliToGregorianForDB($_POST['installation_end_date']);
        $temporary_delivery_date = jalaliToGregorianForDB($_POST['temporary_delivery_date']);
        $permanent_delivery_date = jalaliToGregorianForDB($_POST['permanent_delivery_date']);
        $first_service_date = jalaliToGregorianForDB($_POST['first_service_date']);
        $post_installation_commitments = $_POST['post_installation_commitments'];
        $additional_notes = $_POST['additional_notes'];
        
        // آپلود عکس نصب
        $upload_dir = 'uploads/installations/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $installation_photo = '';
        if (!empty($_FILES['installation_photo']['name'])) {
            $file_ext = pathinfo($_FILES['installation_photo']['name'], PATHINFO_EXTENSION);
            $file_name = time() . '_' . uniqid() . '_installation.' . $file_ext;
            $target_file = $upload_dir . $file_name;
            
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
            if (in_array(strtolower($file_ext), $allowed_types)) {
                if (move_uploaded_file($_FILES['installation_photo']['tmp_name'], $target_file)) {
                    $installation_photo = $target_file;
                }
            }
        }
        
        // درج اطلاعات کامل انتساب
        $stmt = $pdo->prepare("INSERT INTO assignment_details 
            (assignment_id, installation_date, delivery_person, installation_address, 
            warranty_start_date, warranty_end_date, warranty_conditions, employer_name, employer_phone,
            recipient_name, recipient_phone, installer_name, installation_start_date,
            installation_end_date, temporary_delivery_date, permanent_delivery_date,
            first_service_date, post_installation_commitments, notes, installation_photo) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $assignment_id, $installation_date, $delivery_person, $installation_address,
            $warranty_start_date, $warranty_end_date, $warranty_conditions, $employer_name, $employer_phone,
            $recipient_name, $recipient_phone, $installer_name, $installation_start_date,
            $installation_end_date, $temporary_delivery_date, $permanent_delivery_date,
            $first_service_date, $post_installation_commitments, $notes, $installation_photo
        ]);
        
        $pdo->commit();
        $success = "دستگاه با موفقیت به مشتری منتسب شد و اطلاعات نصب ثبت گردید!";
        
        // ثبت لاگ موفقیت
        logAction($pdo, 'ADD_ASSIGNMENT', "افزودن انتساب جدید: دستگاه ID $asset_id به مشتری ID $customer_id", 'info', 'ASSIGNMENTS', [
            'asset_id' => $asset_id,
            'customer_id' => $customer_id,
            'assignment_id' => $assignment_id
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "خطا در انتساب دستگاه: " . $e->getMessage();
        
        // ثبت لاگ خطا
        logAction($pdo, 'ADD_ASSIGNMENT_ERROR', "خطا در افزودن انتساب: " . $e->getMessage(), 'error', 'ASSIGNMENTS', [
            'asset_id' => $asset_id,
            'customer_id' => $customer_id,
            'error' => $e->getMessage()
        ]);
    }
}

// دریافت لیست انتساب‌ها با اطلاعات کامل
$stmt = $pdo->query("
    SELECT aa.*, a.name as asset_name, c.full_name as customer_name,
    ad.installation_date, ad.recipient_name, ad.installation_address
    FROM asset_assignments aa
    JOIN assets a ON aa.asset_id = a.id
    JOIN customers c ON aa.customer_id = c.id
    LEFT JOIN assignment_details ad ON aa.id = ad.assignment_id
    ORDER BY aa.created_at DESC
");
$assignments = $stmt->fetchAll();

// دریافت لیست دستگاه‌ها و مشتریان
$assets = $pdo->query("SELECT id, name, device_model, device_serial, engine_model, engine_serial FROM assets WHERE status = 'فعال' ORDER BY name")->fetchAll();
$customers = $pdo->query("SELECT id, full_name, phone FROM customers ORDER BY full_name")->fetchAll();
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت انتساب دستگاه‌ها - اعلا نیرو</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Persian DatePicker -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css">
    
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Tahoma', sans-serif;
        }
        
        .navbar-brand {
            font-weight: bold;
            font-size: 1.5rem;
        }
        
        .main-content {
            margin-top: 2rem;
            margin-bottom: 2rem;
        }
        
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 10px;
        }
        
        .page-title {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .page-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 1.5rem;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn {
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(102, 126, 234, 0.3);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
            border: none;
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border: none;
        }
        
        .btn-info {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            border: none;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%);
            border: none;
        }
        
        .table {
            border-radius: 10px;
            overflow: hidden;
        }
        
        .table thead th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            font-weight: 600;
            padding: 1rem;
        }
        
        .table tbody tr {
            transition: all 0.3s ease;
        }
        
        .table tbody tr:hover {
            background-color: #f8f9fa;
            transform: scale(1.01);
        }
        
        .assignment-details {
            display: none;
            background-color: #f8f9fa;
            padding: 2rem;
            border-radius: 10px;
            margin-top: 1rem;
        }
        
        .image-preview {
            max-width: 200px;
            max-height: 200px;
            margin-top: 10px;
            display: none;
            border-radius: 8px;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            padding: 1rem 1.5rem;
        }
        
        .modal-content {
            border-radius: 15px;
            border: none;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .stats-number {
            font-size: 3rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .stats-label {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .section-title {
            color: #495057;
            font-weight: bold;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 3px solid #667eea;
        }
        
        .required {
            color: #dc3545;
        }
        
        .form-section {
            background-color: white;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            border: 1px solid #e9ecef;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container-fluid main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="container">
                <h1 class="page-title">
                    <i class="fas fa-link me-3"></i>مدیریت انتساب دستگاه‌ها
                </h1>
                <p class="page-subtitle">انتساب دستگاه‌ها به مشتریان و مدیریت اطلاعات نصب و راه‌اندازی</p>
            </div>
        </div>

        <div class="container">
            <!-- Statistics Cards -->
            <div class="row">
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo count($assignments); ?></div>
                        <div class="stats-label">کل انتساب‌ها</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo count($assets); ?></div>
                        <div class="stats-label">دستگاه‌های فعال</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo count($customers); ?></div>
                        <div class="stats-label">مشتریان</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo count(array_filter($assignments, function($a) { return $a['assignment_status'] == 'فعال'; })); ?></div>
                        <div class="stats-label">انتساب‌های فعال</div>
                    </div>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Assignment Form -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-plus-circle me-2"></i>انتساب جدید دستگاه
                </div>
                <div class="card-body">
                    <form method="POST" id="assignmentForm" enctype="multipart/form-data">
                        <!-- Basic Assignment Info -->
                        <div class="form-section">
                            <h4 class="section-title">
                                <i class="fas fa-info-circle me-2"></i>اطلاعات پایه انتساب
                            </h4>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="customer_id" class="form-label">
                                            انتخاب مشتری <span class="required">*</span>
                                        </label>
                                        <select class="form-select" id="customer_id" name="customer_id" required onchange="loadCustomerInfo()">
                                            <option value="">-- انتخاب مشتری --</option>
                                            <?php foreach ($customers as $customer): ?>
                                                <option value="<?php echo $customer['id']; ?>" data-phone="<?php echo $customer['phone']; ?>">
                                                    <?php echo $customer['full_name']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="asset_id" class="form-label">
                                            انتخاب دستگاه <span class="required">*</span>
                                        </label>
                                        <select class="form-select" id="asset_id" name="asset_id" required onchange="loadAssetDetails()">
                                            <option value="">-- انتخاب دستگاه --</option>
                                            <?php foreach ($assets as $asset): ?>
                                                <option value="<?php echo $asset['id']; ?>"
                                                        data-model="<?php echo $asset['device_model']; ?>"
                                                        data-serial="<?php echo $asset['device_serial']; ?>"
                                                        data-engine-model="<?php echo $asset['engine_model']; ?>"
                                                        data-engine-serial="<?php echo $asset['engine_serial']; ?>">
                                                    <?php echo $asset['name']; ?> (مدل: <?php echo $asset['device_model']; ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="assignment_date" class="form-label">
                                            تاریخ انتساب <span class="required">*</span>
                                        </label>
                                        <input type="text" class="form-control jalali-date" id="assignment_date" name="assignment_date" required value="<?php echo jalali_format(date('Y-m-d')); ?>" readonly>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">مدل دستگاه</label>
                                        <input type="text" class="form-control" id="device_model_display" readonly>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">سریال دستگاه</label>
                                        <input type="text" class="form-control" id="device_serial_display" readonly>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="notes" class="form-label">توضیحات اولیه (اختیاری)</label>
                                <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="توضیحات مربوط به انتساب..."></textarea>
                            </div>
                        </div>

                        <!-- Detailed Assignment Info -->
                        <div id="assignmentDetails" class="assignment-details">
                            <h4 class="section-title">
                                <i class="fas fa-cogs me-2"></i>اطلاعات کامل نصب و راه‌اندازی
                            </h4>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="installation_date" class="form-label">تاریخ نصب</label>
                                        <input type="text" class="form-control jalali-date" id="installation_date" name="installation_date" readonly>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="delivery_person" class="form-label">نام تحویل دهنده</label>
                                        <input type="text" class="form-control" id="delivery_person" name="delivery_person" placeholder="نام شخص تحویل دهنده">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="recipient_name" class="form-label">نام تحویل گیرنده</label>
                                        <input type="text" class="form-control" id="recipient_name" name="recipient_name" placeholder="نام شخص تحویل گیرنده">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="recipient_phone" class="form-label">شماره تماس تحویل گیرنده</label>
                                        <input type="text" class="form-control" id="recipient_phone" name="recipient_phone" placeholder="شماره تماس">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="installer_name" class="form-label">نام نصاب</label>
                                        <input type="text" class="form-control" id="installer_name" name="installer_name" placeholder="نام نصاب">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="installation_address" class="form-label">آدرس محل نصب</label>
                                <textarea class="form-control" id="installation_address" name="installation_address" rows="3" placeholder="آدرس کامل محل نصب دستگاه..."></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="warranty_start_date" class="form-label">تاریخ آغاز گارانتی</label>
                                        <input type="text" class="form-control jalali-date" id="warranty_start_date" name="warranty_start_date" readonly>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="installation_start_date" class="form-label">تاریخ آغاز نصب</label>
                                        <input type="text" class="form-control jalali-date" id="installation_start_date" name="installation_start_date" readonly>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="installation_end_date" class="form-label">تاریخ اتمام نصب</label>
                                        <input type="text" class="form-control jalali-date" id="installation_end_date" name="installation_end_date" readonly>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="temporary_delivery_date" class="form-label">تاریخ تحویل موقت</label>
                                        <input type="text" class="form-control jalali-date" id="temporary_delivery_date" name="temporary_delivery_date" readonly>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="permanent_delivery_date" class="form-label">تاریخ تحویل دائم</label>
                                        <input type="text" class="form-control jalali-date" id="permanent_delivery_date" name="permanent_delivery_date" readonly>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="first_service_date" class="form-label">تاریخ سرویس اولیه</label>
                                        <input type="text" class="form-control jalali-date" id="first_service_date" name="first_service_date" readonly>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="warranty_conditions" class="form-label">شرایط گارانتی</label>
                                <textarea class="form-control" id="warranty_conditions" name="warranty_conditions" rows="3" placeholder="شرایط و قوانین گارانتی..."></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="post_installation_commitments" class="form-label">تعهدات پس از راه‌اندازی</label>
                                <textarea class="form-control" id="post_installation_commitments" name="post_installation_commitments" rows="3" placeholder="تعهدات و خدمات پس از راه‌اندازی..."></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="additional_notes" class="form-label">توضیحات تکمیلی</label>
                                <textarea class="form-control" id="additional_notes" name="additional_notes" rows="3" placeholder="توضیحات اضافی..."></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="installation_photo" class="form-label">عکس نصب نهایی دستگاه</label>
                                <input type="file" class="form-control" id="installation_photo" name="installation_photo" accept="image/*" onchange="previewImage(this, 'installation_photo_preview')">
                                <img id="installation_photo_preview" class="image-preview" src="#" alt="پیش‌نمایش عکس نصب">
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="employer_name" class="form-label">نام کارفرما (از اطلاعات مشتری)</label>
                                        <input type="text" class="form-control" id="employer_name" name="employer_name" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="employer_phone" class="form-label">شماره تماس کارفرما (از اطلاعات مشتری)</label>
                                        <input type="text" class="form-control" id="employer_phone" name="employer_phone" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="text-center">
                            <button type="submit" name="assign_asset" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i>انتساب دستگاه و ثبت اطلاعات
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Assignments List -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-list me-2"></i>لیست انتساب‌های انجام شده
                </div>
                <div class="card-body">
                    <?php if (empty($assignments)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">هیچ انتسابی یافت نشد</h5>
                            <p class="text-muted">برای شروع، یک انتساب جدید ایجاد کنید.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-hashtag me-1"></i>#</th>
                                        <th><i class="fas fa-box me-1"></i>دستگاه</th>
                                        <th><i class="fas fa-user me-1"></i>مشتری</th>
                                        <th><i class="fas fa-calendar me-1"></i>تاریخ انتساب</th>
                                        <th><i class="fas fa-user-check me-1"></i>تحویل گیرنده</th>
                                        <th><i class="fas fa-map-marker-alt me-1"></i>آدرس نصب</th>
                                        <th><i class="fas fa-cogs me-1"></i>عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assignments as $assignment): ?>
                                    <tr>
                                        <td><span class="badge bg-primary"><?php echo $assignment['id']; ?></span></td>
                                        <td>
                                            <strong><?php echo $assignment['asset_name']; ?></strong>
                                        </td>
                                        <td><?php echo $assignment['customer_name']; ?></td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo gregorianToJalaliFromDB($assignment['assignment_date']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $assignment['recipient_name'] ?? '--'; ?></td>
                                        <td>
                                            <?php if ($assignment['installation_address']): ?>
                                                <?php echo substr($assignment['installation_address'], 0, 30); ?>...
                                            <?php else: ?>
                                                --
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" 
                                                        data-bs-target="#detailsModal<?php echo $assignment['id']; ?>">
                                                    <i class="fas fa-eye me-1"></i>جزئیات
                                                </button>
                                                <a href="edit_assignment.php?id=<?php echo $assignment['id']; ?>" class="btn btn-warning btn-sm">
                                                    <i class="fas fa-edit me-1"></i>ویرایش
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Detail Modals -->
    <?php foreach ($assignments as $assignment): ?>
    <div class="modal fade" id="detailsModal<?php echo $assignment['id']; ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-info-circle me-2"></i>جزئیات انتساب #<?php echo $assignment['id']; ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php
                    // دریافت اطلاعات کامل انتساب
                    $stmt = $pdo->prepare("
                        SELECT ad.* 
                        FROM assignment_details ad
                        WHERE ad.assignment_id = ?
                    ");
                    $stmt->execute([$assignment['id']]);
                    $assignment_details = $stmt->fetch();
                    ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">دستگاه:</label>
                                <p><?php echo $assignment['asset_name']; ?></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">مشتری:</label>
                                <p><?php echo $assignment['customer_name']; ?></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">تاریخ انتساب:</label>
                                <p><span class="badge bg-info"><?php echo gregorianToJalaliFromDB($assignment['assignment_date']); ?></span></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">نام تحویل دهنده:</label>
                                <p><?php echo $assignment_details['delivery_person'] ?? '--'; ?></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">نام تحویل گیرنده:</label>
                                <p><?php echo $assignment_details['recipient_name'] ?? '--'; ?></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">تلفن تحویل گیرنده:</label>
                                <p><?php echo $assignment_details['recipient_phone'] ?? '--'; ?></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">تاریخ نصب:</label>
                                <p><span class="badge bg-success"><?php echo gregorianToJalaliFromDB($assignment_details['installation_date']); ?></span></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">تاریخ آغاز گارانتی:</label>
                                <p><span class="badge bg-warning"><?php echo gregorianToJalaliFromDB($assignment_details['warranty_start_date']); ?></span></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">نام نصاب:</label>
                                <p><?php echo $assignment_details['installer_name'] ?? '--'; ?></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">تاریخ تحویل موقت:</label>
                                <p><span class="badge bg-info"><?php echo gregorianToJalaliFromDB($assignment_details['temporary_delivery_date']); ?></span></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">تاریخ تحویل دائم:</label>
                                <p><span class="badge bg-success"><?php echo gregorianToJalaliFromDB($assignment_details['permanent_delivery_date']); ?></span></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label fw-bold">آدرس نصب:</label>
                                <p><?php echo $assignment_details['installation_address'] ?? '--'; ?></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">شرایط گارانتی:</label>
                                <p><?php echo $assignment_details['warranty_conditions'] ?? '--'; ?></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">تعهدات پس از راه‌اندازی:</label>
                                <p><?php echo $assignment_details['post_installation_commitments'] ?? '--'; ?></p>
                            </div>
                            
                            <?php if (!empty($assignment_details['installation_photo'])): ?>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">عکس نصب:</label>
                                    <div>
                                        <img src="<?php echo $assignment_details['installation_photo']; ?>" class="img-thumbnail" style="max-width: 300px;">
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="edit_assignment.php?id=<?php echo $assignment['id']; ?>" class="btn btn-warning">
                        <i class="fas fa-edit me-1"></i>ویرایش این انتساب
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>بستن
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/persian-date@1.1.0/dist/persian-date.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js"></script>
    
    <script>
    function loadCustomerInfo() {
        const customerSelect = document.getElementById('customer_id');
        const selectedOption = customerSelect.options[customerSelect.selectedIndex];
        
        if (customerSelect.value && document.getElementById('asset_id').value) {
            document.getElementById('assignmentDetails').style.display = 'block';
            
            // پر کردن خودکار اطلاعات کارفرما از داده‌های مشتری
            document.getElementById('employer_name').value = selectedOption.text;
            document.getElementById('employer_phone').value = selectedOption.getAttribute('data-phone');
        } else {
            document.getElementById('assignmentDetails').style.display = 'none';
        }
    }
    
    function loadAssetDetails() {
        const assetSelect = document.getElementById('asset_id');
        const selectedOption = assetSelect.options[assetSelect.selectedIndex];
        
        if (assetSelect.value) {
            // نمایش اطلاعات دستگاه
            document.getElementById('device_model_display').value = selectedOption.getAttribute('data-model');
            document.getElementById('device_serial_display').value = selectedOption.getAttribute('data-serial');
            
            if (document.getElementById('customer_id').value) {
                document.getElementById('assignmentDetails').style.display = 'block';
            }
        } else {
            document.getElementById('assignmentDetails').style.display = 'none';
        }
    }
    
    function previewImage(input, previewId) {
        const preview = document.getElementById(previewId);
        const file = input.files[0];
        
        if (file) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
            
            reader.readAsDataURL(file);
        } else {
            preview.style.display = 'none';
        }
    }
    
    // اعتبارسنجی فرم
    document.getElementById('assignmentForm').addEventListener('submit', function(e) {
        const customerId = document.getElementById('customer_id').value;
        const assetId = document.getElementById('asset_id').value;
        
        if (!customerId || !assetId) {
            e.preventDefault();
            alert('لطفاً مشتری و دستگاه را انتخاب کنید.');
        }
    });
    
    // Initialize Persian DatePicker
    $(document).ready(function() {
        $('.jalali-date').persianDatepicker({
            format: 'YYYY/MM/DD',
            altField: '.jalali-date-alt',
            altFormat: 'YYYY/MM/DD',
            observer: true,
            timePicker: {
                enabled: false
            }
        });
    });
    </script>
</body>
</html>