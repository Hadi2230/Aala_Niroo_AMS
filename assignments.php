<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// بررسی دسترسی ادمین
$is_admin = ($_SESSION['role'] === 'ادمین' || $_SESSION['role'] === 'admin');

// ثبت لاگ مشاهده صفحه
logAction($pdo, 'VIEW_ASSIGNMENTS', 'مشاهده صفحه انتساب دستگاه‌ها');

$message = '';
$error = '';

// انتساب دستگاه به مشتری
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_asset'])) {
    try {
        $pdo->beginTransaction();
        
        $asset_id = $_POST['asset_id'];
        $customer_id = $_POST['customer_id'];
        $assignment_date = jalaliToGregorianForDB($_POST['assignment_date']);
        $notes = $_POST['notes'];

        // درج انتساب اصلی
        $stmt = $pdo->prepare("INSERT INTO asset_assignments (asset_id, customer_id, assignment_date, notes, assignment_status) VALUES (?, ?, ?, ?, 'فعال')");
        $stmt->execute([$asset_id, $customer_id, $assignment_date, $notes]);
        $assignment_id = $pdo->lastInsertId();
        
        // ذخیره جزئیات انتساب
        $installation_date = jalaliToGregorianForDB($_POST['installation_date']);
        $delivery_person = $_POST['delivery_person'];
        $installation_address = $_POST['installation_address'];
        $warranty_start_date = jalaliToGregorianForDB($_POST['warranty_start_date']);
        $warranty_conditions = $_POST['warranty_conditions'];
        $employer_name = $_POST['employer_name'];
        $employer_phone = $_POST['employer_phone'];
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
        $installation_photo = '';
        if (isset($_FILES['installation_photo']) && $_FILES['installation_photo']['error'] == 0) {
            $upload_dir = 'uploads/installations/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = pathinfo($_FILES['installation_photo']['name'], PATHINFO_EXTENSION);
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array(strtolower($file_extension), $allowed_extensions)) {
                $file_name = time() . '_' . uniqid() . '.' . $file_extension;
                $target_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['installation_photo']['tmp_name'], $target_path)) {
                    $installation_photo = $target_path;
                }
            }
        }

        // درج جزئیات انتساب
        $stmt = $pdo->prepare("
            INSERT INTO assignment_details (
                assignment_id, installation_date, delivery_person, installation_address, 
                warranty_start_date, warranty_conditions, employer_name, employer_phone, 
                recipient_name, recipient_phone, installer_name, installation_start_date, 
                installation_end_date, temporary_delivery_date, permanent_delivery_date, 
                first_service_date, post_installation_commitments, notes, installation_photo
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $assignment_id, $installation_date, $delivery_person, $installation_address,
            $warranty_start_date, $warranty_conditions, $employer_name, $employer_phone,
            $recipient_name, $recipient_phone, $installer_name, $installation_start_date,
            $installation_end_date, $temporary_delivery_date, $permanent_delivery_date,
            $first_service_date, $post_installation_commitments, $additional_notes, $installation_photo
        ]);

        $pdo->commit();
        $message = 'انتساب با موفقیت ثبت شد.';
        
        // ثبت لاگ
        logAction($pdo, 'ASSIGNMENT_CREATED', "انتساب جدید ایجاد شد - دستگاه ID: $asset_id, مشتری ID: $customer_id");
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'خطا در ثبت انتساب: ' . $e->getMessage();
        logAction($pdo, 'ASSIGNMENT_ERROR', "خطا در ایجاد انتساب: " . $e->getMessage(), 'error');
    }
}

// حذف انتساب (فقط ادمین)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_assignment']) && $is_admin) {
    $assignment_id = $_POST['assignment_id'];
    
    try {
        $pdo->beginTransaction();
        
        // حذف جزئیات انتساب
        $stmt = $pdo->prepare("DELETE FROM assignment_details WHERE assignment_id = ?");
        $stmt->execute([$assignment_id]);
        
        // حذف انتساب اصلی
        $stmt = $pdo->prepare("DELETE FROM asset_assignments WHERE id = ?");
        $stmt->execute([$assignment_id]);
        
        $pdo->commit();
        $message = 'انتساب با موفقیت حذف شد.';
        
        logAction($pdo, 'ASSIGNMENT_DELETED', "انتساب حذف شد - ID: $assignment_id");
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'خطا در حذف انتساب: ' . $e->getMessage();
        logAction($pdo, 'ASSIGNMENT_DELETE_ERROR', "خطا در حذف انتساب: " . $e->getMessage(), 'error');
    }
}

// دریافت لیست انتساب‌ها
$assignments = [];
try {
    $stmt = $pdo->query("
        SELECT 
            aa.*,
            a.name as asset_name,
            a.serial_number,
            a.brand,
            a.model,
            a.power_capacity,
            a.device_model,
            a.device_serial,
            c.full_name as customer_name,
            c.phone as customer_phone,
            c.company,
            ad.installation_date,
            ad.employer_name,
            ad.employer_phone,
            ad.recipient_name,
            ad.recipient_phone,
            ad.installer_name,
            ad.installation_photo
        FROM asset_assignments aa
        LEFT JOIN assets a ON aa.asset_id = a.id
        LEFT JOIN customers c ON aa.customer_id = c.id
        LEFT JOIN assignment_details ad ON aa.id = ad.assignment_id
        ORDER BY aa.created_at DESC
    ");
    $assignments = $stmt->fetchAll();
} catch (Exception $e) {
    $error = 'خطا در دریافت لیست انتساب‌ها: ' . $e->getMessage();
    logAction($pdo, 'ASSIGNMENT_LIST_ERROR', "خطا در دریافت لیست انتساب‌ها: " . $e->getMessage(), 'error');
}

// دریافت لیست دارایی‌ها
$assets = [];
try {
    $stmt = $pdo->query("SELECT id, name, serial_number, brand, model, power_capacity, device_model, device_serial FROM assets WHERE status = 'فعال' ORDER BY name");
    $assets = $stmt->fetchAll();
} catch (Exception $e) {
    logAction($pdo, 'ASSETS_LIST_ERROR', "خطا در دریافت لیست دارایی‌ها: " . $e->getMessage(), 'error');
}

// دریافت لیست مشتریان
$customers = [];
try {
    $stmt = $pdo->query("SELECT id, full_name, phone, company, customer_type FROM customers ORDER BY full_name");
    $customers = $stmt->fetchAll();
} catch (Exception $e) {
    logAction($pdo, 'CUSTOMERS_LIST_ERROR', "خطا در دریافت لیست مشتریان: " . $e->getMessage(), 'error');
}
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
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #56ab2f;
            --warning-color: #f093fb;
            --info-color: #4facfe;
            --danger-color: #ff416c;
            --dark-color: #2c3e50;
            --light-color: #f8f9fa;
            --text-color: #333;
            --border-color: #e9ecef;
        }

        body {
            font-family: 'Tahoma', sans-serif;
            background-color: var(--light-color);
            color: var(--text-color);
        }

        .main-content {
            margin-top: 20px;
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 15px;
        }

        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
            border: none;
        }

        .stats-card:hover {
            transform: translateY(-5px);
        }

        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-bottom: 1rem;
        }

        .form-section {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .form-section h4 {
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--border-color);
        }

        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid var(--border-color);
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger-color) 0%, #ff6b6b 100%);
            border: none;
            border-radius: 10px;
        }

        .btn-warning {
            background: linear-gradient(135deg, var(--warning-color) 0%, #f093fb 100%);
            border: none;
            border-radius: 10px;
        }

        .table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .table thead th {
            background: var(--primary-color);
            color: white;
            border: none;
            font-weight: bold;
            padding: 1rem;
        }

        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-color: var(--border-color);
        }

        .badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: bold;
        }

        .asset-info, .customer-info {
            background: var(--light-color);
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
            border: 2px solid var(--border-color);
        }

        .asset-info h6, .customer-info h6 {
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .asset-info p, .customer-info p {
            margin: 0.25rem 0;
            color: var(--text-color);
        }

        .photo-preview {
            max-width: 100px;
            max-height: 100px;
            border-radius: 10px;
            object-fit: cover;
        }

        .modal-content {
            border-radius: 15px;
            border: none;
        }

        .modal-header {
            background: var(--primary-color);
            color: white;
            border-radius: 15px 15px 0 0;
        }

        .alert {
            border-radius: 10px;
            border: none;
        }

        .jalali-date {
            background-color: white;
            cursor: pointer;
        }

        .jalali-date:focus {
            background-color: white;
        }

        .btn-new-assignment {
            background: linear-gradient(135deg, var(--success-color) 0%, #56ab2f 100%);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: bold;
            color: white;
            transition: all 0.3s ease;
        }

        .btn-new-assignment:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(86, 171, 47, 0.4);
            color: white;
        }

        .assignment-form {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .assignment-form h4 {
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--border-color);
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container-fluid main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="mb-0">
                            <i class="fas fa-link me-3"></i>مدیریت انتساب دستگاه‌ها
                        </h1>
                        <p class="mb-0 mt-2">انتساب دستگاه‌ها به مشتریان و مدیریت جزئیات نصب</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <button class="btn btn-new-assignment btn-lg" id="newAssignmentBtn" onclick="toggleAssignmentForm()">
                            <i class="fas fa-plus me-2"></i>انتساب جدید
                        </button>
                        <button class="btn btn-warning btn-sm ms-2" onclick="testFunction()">
                            <i class="fas fa-bug me-1"></i>تست
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="container">
            <!-- Alerts -->
            <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Assignment Form -->
            <div id="assignmentForm" class="assignment-form" style="display: none;">
                <h4><i class="fas fa-plus me-2"></i>انتساب دستگاه جدید</h4>
                <form method="POST" enctype="multipart/form-data" id="assignmentFormElement">
                    <div class="row">
                        <!-- انتخاب دستگاه -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">دستگاه</label>
                                <select class="form-select" id="asset_id" name="asset_id" required onchange="loadAssetDetails()">
                                    <option value="">انتخاب کنید...</option>
                                    <?php foreach ($assets as $asset): ?>
                                    <option value="<?php echo $asset['id']; ?>" 
                                            data-serial="<?php echo htmlspecialchars($asset['serial_number']); ?>"
                                            data-brand="<?php echo htmlspecialchars($asset['brand']); ?>"
                                            data-model="<?php echo htmlspecialchars($asset['model']); ?>"
                                            data-power="<?php echo htmlspecialchars($asset['power_capacity']); ?>"
                                            data-device-model="<?php echo htmlspecialchars($asset['device_model']); ?>"
                                            data-device-serial="<?php echo htmlspecialchars($asset['device_serial']); ?>">
                                        <?php echo htmlspecialchars($asset['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- اطلاعات دستگاه -->
                            <div id="assetInfo" class="asset-info" style="display: none;">
                                <h6>مشخصات دستگاه:</h6>
                                <p><strong>سریال:</strong> <span id="deviceSerial">-</span></p>
                                <p><strong>برند:</strong> <span id="deviceBrand">-</span></p>
                                <p><strong>مدل:</strong> <span id="deviceModel">-</span></p>
                                <p><strong>قدرت:</strong> <span id="devicePower">-</span></p>
                                <p><strong>مدل دستگاه:</strong> <span id="deviceModelDisplay">-</span></p>
                                <p><strong>سریال دستگاه:</strong> <span id="deviceSerialDisplay">-</span></p>
                            </div>
                        </div>

                        <!-- انتخاب مشتری -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">مشتری</label>
                                <select class="form-select" id="customer_id" name="customer_id" required onchange="loadCustomerInfo()">
                                    <option value="">انتخاب کنید...</option>
                                    <?php foreach ($customers as $customer): ?>
                                    <option value="<?php echo $customer['id']; ?>" 
                                            data-phone="<?php echo htmlspecialchars($customer['phone']); ?>"
                                            data-company="<?php echo htmlspecialchars($customer['company']); ?>"
                                            data-type="<?php echo $customer['customer_type']; ?>">
                                        <?php echo htmlspecialchars($customer['full_name']); ?>
                                        <?php if ($customer['company']): ?>
                                            - <?php echo htmlspecialchars($customer['company']); ?>
                                        <?php endif; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- اطلاعات مشتری -->
                            <div id="customerInfo" class="customer-info" style="display: none;">
                                <h6>اطلاعات مشتری:</h6>
                                <p><strong>تلفن:</strong> <span id="customerPhone">-</span></p>
                                <p><strong>شرکت:</strong> <span id="customerCompany">-</span></p>
                                <p><strong>نوع:</strong> <span id="customerType">-</span></p>
                            </div>
                        </div>
                    </div>

                    <!-- اطلاعات انتساب -->
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">تاریخ انتساب</label>
                                <input type="text" class="form-control jalali-date" id="assignment_date" name="assignment_date" 
                                       value="<?php echo jalali_format(date('Y-m-d')); ?>" required readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">تاریخ نصب</label>
                                <input type="text" class="form-control jalali-date" id="installation_date" name="installation_date" readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">تاریخ شروع گارانتی</label>
                                <input type="text" class="form-control jalali-date" id="warranty_start_date" name="warranty_start_date" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">مسئول تحویل</label>
                                <input type="text" class="form-control" name="delivery_person">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">آدرس نصب</label>
                                <textarea class="form-control" name="installation_address" rows="2"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- اطلاعات کارفرما -->
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">نام کارفرما</label>
                                <input type="text" class="form-control" id="employer_name" name="employer_name">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">تلفن کارفرما</label>
                                <input type="text" class="form-control" id="employer_phone" name="employer_phone">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">شرایط گارانتی</label>
                                <textarea class="form-control" name="warranty_conditions" rows="2"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- اطلاعات گیرنده -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">نام گیرنده</label>
                                <input type="text" class="form-control" name="recipient_name">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">تلفن گیرنده</label>
                                <input type="text" class="form-control" name="recipient_phone">
                            </div>
                        </div>
                    </div>

                    <!-- اطلاعات نصب -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">نام نصب کننده</label>
                                <input type="text" class="form-control" name="installer_name">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">تاریخ شروع نصب</label>
                                <input type="text" class="form-control jalali-date" id="installation_start_date" name="installation_start_date" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">تاریخ پایان نصب</label>
                                <input type="text" class="form-control jalali-date" id="installation_end_date" name="installation_end_date" readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">تاریخ تحویل موقت</label>
                                <input type="text" class="form-control jalali-date" id="temporary_delivery_date" name="temporary_delivery_date" readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">تاریخ تحویل دائمی</label>
                                <input type="text" class="form-control jalali-date" id="permanent_delivery_date" name="permanent_delivery_date" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">تاریخ اولین سرویس</label>
                                <input type="text" class="form-control jalali-date" id="first_service_date" name="first_service_date" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">عکس نصب</label>
                                <input type="file" class="form-control" name="installation_photo" accept="image/*">
                            </div>
                        </div>
                    </div>

                    <!-- توضیحات -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">تعهدات پس از نصب</label>
                                <textarea class="form-control" name="post_installation_commitments" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">یادداشت‌های اضافی</label>
                                <textarea class="form-control" name="additional_notes" rows="3"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">یادداشت‌ها</label>
                        <textarea class="form-control" name="notes" rows="2"></textarea>
                    </div>

                    <div class="text-end">
                        <button type="button" class="btn btn-secondary me-2" id="cancelBtn" onclick="toggleAssignmentForm()">انصراف</button>
                        <button type="submit" name="assign_asset" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>ثبت انتساب
                        </button>
                    </div>
                </form>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stats-card text-center">
                        <div class="stats-icon mx-auto" style="background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));">
                            <i class="fas fa-link"></i>
                        </div>
                        <h3 class="mb-1"><?php echo count($assignments); ?></h3>
                        <p class="text-muted mb-0">کل انتساب‌ها</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card text-center">
                        <div class="stats-icon mx-auto" style="background: linear-gradient(135deg, var(--success-color), #56ab2f);">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3 class="mb-1"><?php echo count(array_filter($assignments, fn($a) => $a['assignment_status'] === 'فعال')); ?></h3>
                        <p class="text-muted mb-0">انتساب‌های فعال</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card text-center">
                        <div class="stats-icon mx-auto" style="background: linear-gradient(135deg, var(--info-color), #4facfe);">
                            <i class="fas fa-server"></i>
                        </div>
                        <h3 class="mb-1"><?php echo count($assets); ?></h3>
                        <p class="text-muted mb-0">دستگاه‌های فعال</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card text-center">
                        <div class="stats-icon mx-auto" style="background: linear-gradient(135deg, var(--warning-color), #f093fb);">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3 class="mb-1"><?php echo count($customers); ?></h3>
                        <p class="text-muted mb-0">مشتریان</p>
                    </div>
                </div>
            </div>

            <!-- Assignments List -->
            <div class="form-section">
                <h4><i class="fas fa-list me-2"></i>لیست انتساب‌های انجام شده</h4>
                
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>شماره انتساب</th>
                                <th>دستگاه</th>
                                <th>مشتری</th>
                                <th>تاریخ انتساب</th>
                                <th>وضعیت</th>
                                <th>نصب کننده</th>
                                <?php if ($is_admin): ?>
                                <th>عملیات</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assignments as $assignment): ?>
                            <tr>
                                <td>
                                    <strong>#<?php echo $assignment['id']; ?></strong>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($assignment['asset_name']); ?></strong>
                                        <br>
                                        <small class="text-muted">سریال: <?php echo htmlspecialchars($assignment['serial_number']); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($assignment['customer_name']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($assignment['customer_phone']); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <?php echo gregorianToJalaliFromDB($assignment['assignment_date']); ?>
                                </td>
                                <td>
                                    <span class="badge bg-success"><?php echo $assignment['assignment_status']; ?></span>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($assignment['installer_name'] ?? 'نامشخص'); ?>
                                </td>
                                <?php if ($is_admin): ?>
                                <td>
                                    <button class="btn btn-sm btn-info me-1" onclick="viewAssignmentDetails(<?php echo $assignment['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-warning me-1" onclick="editAssignment(<?php echo $assignment['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteAssignment(<?php echo $assignment['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                                <?php else: ?>
                                <td>
                                    <button class="btn btn-sm btn-info" onclick="viewAssignmentDetails(<?php echo $assignment['id']; ?>)">
                                        <i class="fas fa-eye"></i> مشاهده
                                    </button>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Assignment Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailsModalLabel">
                        <i class="fas fa-eye me-2"></i>جزئیات انتساب
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="detailsContent">
                    <!-- محتوا از طریق AJAX بارگذاری می‌شود -->
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">
                        <i class="fas fa-exclamation-triangle me-2"></i>تأیید حذف
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>آیا از حذف این انتساب اطمینان دارید؟</p>
                    <p class="text-danger"><strong>این عمل قابل بازگشت نیست!</strong></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="assignment_id" id="deleteAssignmentId">
                        <button type="submit" name="delete_assignment" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>حذف
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/persian-date@1.1.0/dist/persian-date.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js"></script>
    
    <!-- Inline JavaScript - کاملاً ساده و مطمئن -->
    <script>
    // Test function - برای تست
    function testFunction() {
        console.log('Test function called');
        alert('Test function works!');
    }

    // Toggle assignment form
    function toggleAssignmentForm() {
        console.log('Toggle function called - START');
        const form = document.getElementById('assignmentForm');
        console.log('Form element:', form);
        
        if (form) {
            if (form.style.display === 'none' || form.style.display === '') {
                console.log('Showing form');
                form.style.display = 'block';
                
                // Reset form
                const formElement = document.getElementById('assignmentFormElement');
                if (formElement) {
                    formElement.reset();
                }
                
                // Hide info sections
                const assetInfo = document.getElementById('assetInfo');
                const customerInfo = document.getElementById('customerInfo');
                if (assetInfo) assetInfo.style.display = 'none';
                if (customerInfo) customerInfo.style.display = 'none';
                
                // Set default assignment date
                const assignmentDate = document.getElementById('assignment_date');
                if (assignmentDate) {
                    assignmentDate.value = '<?php echo jalali_format(date('Y-m-d')); ?>';
                }
                
                // Scroll to form
                form.scrollIntoView({ behavior: 'smooth' });
                console.log('Form shown successfully');
            } else {
                console.log('Hiding form');
                form.style.display = 'none';
            }
        } else {
            console.error('Form element not found!');
        }
    }

    // Load asset details
    function loadAssetDetails() {
        console.log('Loading asset details');
        const select = document.getElementById('asset_id');
        if (!select) {
            console.error('Asset select not found');
            return;
        }
        
        const option = select.options[select.selectedIndex];
        
        if (option.value) {
            console.log('Asset selected:', option.value);
            document.getElementById('deviceSerial').textContent = option.dataset.serial || '-';
            document.getElementById('deviceBrand').textContent = option.dataset.brand || '-';
            document.getElementById('deviceModel').textContent = option.dataset.model || '-';
            document.getElementById('devicePower').textContent = option.dataset.power || '-';
            document.getElementById('deviceModelDisplay').textContent = option.dataset.deviceModel || '-';
            document.getElementById('deviceSerialDisplay').textContent = option.dataset.deviceSerial || '-';
            document.getElementById('assetInfo').style.display = 'block';
        } else {
            document.getElementById('assetInfo').style.display = 'none';
        }
    }

    // Load customer info
    function loadCustomerInfo() {
        console.log('Loading customer info');
        const select = document.getElementById('customer_id');
        if (!select) {
            console.error('Customer select not found');
            return;
        }
        
        const option = select.options[select.selectedIndex];
        
        if (option.value) {
            console.log('Customer selected:', option.value);
            document.getElementById('customerPhone').textContent = option.dataset.phone || '-';
            document.getElementById('customerCompany').textContent = option.dataset.company || '-';
            document.getElementById('customerType').textContent = option.dataset.type || '-';
            document.getElementById('customerInfo').style.display = 'block';
            
            // Auto-fill employer fields
            document.getElementById('employer_name').value = option.textContent.split(' - ')[0];
            document.getElementById('employer_phone').value = option.dataset.phone || '';
        } else {
            document.getElementById('customerInfo').style.display = 'none';
        }
    }

    // View assignment details
    function viewAssignmentDetails(assignmentId) {
        console.log('Viewing assignment details:', assignmentId);
        fetch(`get_assignment_details.php?id=${assignmentId}`)
        .then(response => response.text())
        .then(data => {
            document.getElementById('detailsContent').innerHTML = data;
            const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
            modal.show();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('خطا در بارگذاری جزئیات');
        });
    }

    // Edit assignment
    function editAssignment(assignmentId) {
        console.log('Edit assignment:', assignmentId);
        alert('قابلیت ویرایش در حال توسعه است');
    }

    // Delete assignment
    function deleteAssignment(assignmentId) {
        console.log('Delete assignment:', assignmentId);
        document.getElementById('deleteAssignmentId').value = assignmentId;
        const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
        modal.show();
    }

    // Initialize when page loads
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOMContentLoaded - Page loaded, initializing...');
        
        // Initialize Persian DatePicker
        if (typeof $ !== 'undefined' && $.fn.persianDatepicker) {
            $('.jalali-date').persianDatepicker({
                format: 'YYYY/MM/DD',
                altField: '.jalali-date-alt',
                altFormat: 'YYYY/MM/DD',
                observer: true,
                timePicker: {
                    enabled: false
                }
            });
            console.log('Persian DatePicker initialized');
        } else {
            console.warn('jQuery or Persian DatePicker not loaded');
        }
        
        console.log('Initialization complete');
    });
    </script>
</body>
</html>