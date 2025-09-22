<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
include 'config.php';

$isEmbed = isset($_GET['embed']) && $_GET['embed'] === '1';

// تابع برای تولید شماره سریال کارت گارانتی
function generateWarrantySerial() {
    return 'WN' . date('Ymd') . strtoupper(substr(uniqid(), -6));
}

$success = $error = '';

try {
    $pdo->beginTransaction();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_asset'])) {
        $asset_id = $_POST['asset_id'];
        $customer_id = $_POST['customer_id'];
        $assignment_date = jalaliToGregorianForDB($_POST['assignment_date']);
        $notes = $_POST['notes'];

        $stmt = $pdo->prepare("INSERT INTO asset_assignments (asset_id, customer_id, assignment_date, notes) VALUES (?, ?, ?, ?)");
        $stmt->execute([$asset_id, $customer_id, $assignment_date, $notes]);
        $assignment_id = $pdo->lastInsertId();

        $stmt = $pdo->prepare("SELECT full_name, phone FROM customers WHERE id = ?");
        $stmt->execute([$customer_id]);
        $customer = $stmt->fetch();

        $stmt = $pdo->prepare("SELECT name, model, serial_number FROM assets WHERE id = ?");
        $stmt->execute([$asset_id]);
        $asset = $stmt->fetch();

        $fields = [
            'installation_date', 'delivery_person', 'installation_address',
            'warranty_start_date', 'warranty_end_date', 'warranty_conditions', 'recipient_name',
            'recipient_phone', 'installer_name', 'installation_start_date',
            'installation_end_date', 'temporary_delivery_date', 'permanent_delivery_date',
            'first_service_date', 'post_installation_commitments', 'additional_notes'
        ];
        foreach ($fields as $field) {
            $$field = isset($_POST[$field]) && !empty($_POST[$field]) ? jalaliToGregorianForDB($_POST[$field]) : null;
        }

        $employer_name = $customer['full_name'];
        $employer_phone = $customer['phone'];
        $warranty_serial = generateWarrantySerial();

        $installation_photo = '';
        $upload_dir = 'uploads/installations/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        if (!empty($_FILES['installation_photo']['name'])) {
            $file_ext = pathinfo($_FILES['installation_photo']['name'], PATHINFO_EXTENSION);
            $file_name = time() . '_' . uniqid() . '_installation.' . $file_ext;
            $target_file = $upload_dir . $file_name;

            if (in_array(strtolower($file_ext), ['jpg','jpeg','png','gif']) &&
                move_uploaded_file($_FILES['installation_photo']['tmp_name'], $target_file)) {
                $installation_photo = $target_file;
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
        $success = "دستگاه با موفقیت به مشتری انتساب شد و اطلاعات نصب ثبت گردید!";
        $_SESSION['last_assignment_id'] = $assignment_id;
        $_SESSION['last_warranty_serial'] = $warranty_serial;
        
        // ثبت لاگ
        logAction($pdo, 'ASSIGNMENT_CREATED', "انتساب جدید ایجاد شد - دستگاه ID: $asset_id, مشتری ID: $customer_id");
    }
} catch (Exception $e) {
    $pdo->rollBack();
    $error = "خطا در انتساب دستگاه: " . $e->getMessage();
    logAction($pdo, 'ASSIGNMENT_ERROR', "خطا در ایجاد انتساب: " . $e->getMessage(), 'error');
}

try {
    $stmt = $pdo->query("
        SELECT aa.*, a.name AS asset_name, a.model AS asset_model, a.serial_number AS asset_serial,
               c.full_name AS customer_name, c.phone AS customer_phone,
               ad.*
        FROM asset_assignments aa
        JOIN assets a ON aa.asset_id = a.id
        JOIN customers c ON aa.customer_id = c.id
        LEFT JOIN assignment_details ad ON aa.id = ad.assignment_id
        ORDER BY aa.created_at DESC
    ");
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "خطا در دریافت انتساب‌ها: " . $e->getMessage();
    $assignments = [];
    logAction($pdo, 'ASSIGNMENT_LIST_ERROR', "خطا در دریافت لیست انتساب‌ها: " . $e->getMessage(), 'error');
}

$last_assignment = null;
if (isset($_SESSION['last_assignment_id'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT aa.*, a.name AS asset_name, a.model AS asset_model, a.serial_number AS asset_serial,
                   c.full_name AS customer_name, c.phone AS customer_phone, c.address AS customer_address,
                   ad.*
            FROM asset_assignments aa
            JOIN assets a ON aa.asset_id = a.id
            JOIN customers c ON aa.customer_id = c.id
            LEFT JOIN assignment_details ad ON aa.id = ad.assignment_id
            WHERE aa.id = ?
        ");
        $stmt->execute([$_SESSION['last_assignment_id']]);
        $last_assignment = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($last_assignment) {
            $last_assignment['warranty_serial'] = $_SESSION['last_warranty_serial'] ?? generateWarrantySerial();
        }
    } catch (Exception $e) {
        $error = "خطا در دریافت اطلاعات انتساب: " . $e->getMessage();
        logAction($pdo, 'ASSIGNMENT_DETAILS_ERROR', "خطا در دریافت جزئیات انتساب: " . $e->getMessage(), 'error');
    }
}

try {
    $assets_stmt = $pdo->query("SELECT id, name, model, serial_number FROM assets 
                                 WHERE status = 'فعال' OR status IS NULL OR status IN ('Active','active','ACTIVE')
                                 ORDER BY name");
    $assets = $assets_stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$assets) {
        $assets_stmt = $pdo->query("SELECT id, name, model, serial_number FROM assets ORDER BY name");
        $assets = $assets_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $error = "خطا در دریافت دستگاه‌ها: " . $e->getMessage();
    $assets = [];
    logAction($pdo, 'ASSETS_LIST_ERROR', "خطا در دریافت لیست دارایی‌ها: " . $e->getMessage(), 'error');
}

try {
    $customers_stmt = $pdo->query("SELECT id, full_name, phone FROM customers ORDER BY full_name");
    $customers = $customers_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "خطا در دریافت مشتریان: " . $e->getMessage();
    $customers = [];
    logAction($pdo, 'CUSTOMERS_LIST_ERROR', "خطا در دریافت لیست مشتریان: " . $e->getMessage(), 'error');
}

// ثبت لاگ مشاهده صفحه
logAction($pdo, 'VIEW_ASSIGNMENTS', 'مشاهده صفحه انتساب دستگاه‌ها');
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>انتساب دستگاه به مشتری - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <!-- Persian DatePicker -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css">
    <?php if ($isEmbed): ?>
    <style>
        body{padding-top:10px;background:transparent}
    </style>
    <?php endif; ?>
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

        html, body { 
            font-family: Vazirmatn, Tahoma, Arial, sans-serif; 
            background-color: var(--light-color);
        }
        
        .assignment-details { display: none; }
        .image-preview { max-width: 200px; max-height: 200px; margin-top: 10px; display: none; }

        .warranty-card {
            border: 2px solid var(--primary-color);
            border-radius: 15px;
            padding: 20px;
            margin: 20px 0;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .warranty-header { 
            text-align: center; 
            border-bottom: 2px solid var(--primary-color); 
            padding-bottom: 15px; 
            margin-bottom: 20px; 
        }
        .warranty-logo { 
            font-size: 28px; 
            font-weight: bold; 
            color: var(--primary-color); 
            margin-bottom: 10px;
        }
        .warranty-body { margin-bottom: 20px; }
        .warranty-field { 
            margin-bottom: 12px; 
            display: flex; 
            padding: 8px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .warranty-label { 
            font-weight: bold; 
            min-width: 140px; 
            color: var(--primary-color);
        }
        .warranty-footer { 
            text-align: center; 
            border-top: 1px dashed #ccc; 
            padding-top: 15px; 
            font-size: 12px; 
            color: #6c757d; 
        }
        
        .card {
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border: none;
            margin-bottom: 2rem;
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            font-weight: bold;
            padding: 1rem 1.5rem;
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
        
        .jalali-date {
            background-color: white;
            cursor: pointer;
        }

        .jalali-date:focus {
            background-color: white;
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
            text-align: center;
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
            margin: 0 auto 1rem;
        }
        
        @media print {
            body * { visibility: hidden; }
            .warranty-card, .warranty-card * { visibility: visible; }
            .warranty-card { position: absolute; left: 0; top: 0; width: 100%; border: none; box-shadow: none; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
<?php if (!$isEmbed) include 'navbar.php'; ?>

<div class="container mt-5">
    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-0">
                        <i class="fas fa-link me-3"></i>انتساب دستگاه به مشتری
                    </h1>
                    <p class="mb-0 mt-2">انتساب دستگاه‌ها به مشتریان و مدیریت جزئیات نصب</p>
                </div>
                <div class="col-md-4 text-end">
                    <button class="btn btn-success btn-lg" onclick="toggleAssignmentForm()">
                        <i class="fas fa-plus me-2"></i>انتساب جدید
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-icon" style="background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));">
                    <i class="fas fa-link"></i>
                </div>
                <h3 class="mb-1"><?php echo count($assignments); ?></h3>
                <p class="text-muted mb-0">کل انتساب‌ها</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-icon" style="background: linear-gradient(135deg, var(--success-color), #56ab2f);">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3 class="mb-1"><?php echo count($assets); ?></h3>
                <p class="text-muted mb-0">دستگاه‌های فعال</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-icon" style="background: linear-gradient(135deg, var(--info-color), #4facfe);">
                    <i class="fas fa-users"></i>
                </div>
                <h3 class="mb-1"><?php echo count($customers); ?></h3>
                <p class="text-muted mb-0">مشتریان</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-icon" style="background: linear-gradient(135deg, var(--warning-color), #f093fb);">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <h3 class="mb-1"><?php echo count(array_filter($assignments, function($a) { return !empty($a['installation_date']); })); ?></h3>
                <p class="text-muted mb-0">نصب‌های انجام شده</p>
            </div>
        </div>
    </div>

    <!-- Alerts -->
    <?php if(!empty($success)): ?>
        <div class='alert alert-success alert-dismissible fade show' role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
            <?php if(isset($last_assignment) && !empty($last_assignment['warranty_serial'])): ?>
            <div class="mt-2">
                <a href="javascript:void(0);" onclick="printWarranty()" class="btn btn-success btn-sm">
                    <i class="fas fa-print"></i> چاپ کارت گارانتی
                </a>
            </div>
            <?php endif; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if(!empty($error)): ?>
        <div class='alert alert-danger alert-dismissible fade show' role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Warranty Card -->
    <?php if(isset($last_assignment) && !empty($last_assignment['warranty_serial'])): ?>
    <div class="warranty-card no-print" id="warrantyCard">
        <div class="warranty-header">
            <div class="warranty-logo">اعلا نیرو</div>
            <h3>کارت گارانتی دستگاه</h3>
        </div>
        <div class="warranty-body">
            <div class="warranty-field"><span class="warranty-label">شماره کارت:</span><span><?php echo $last_assignment['warranty_serial']; ?></span></div>
            <div class="warranty-field"><span class="warranty-label">مشتری:</span><span><?php echo $last_assignment['customer_name']; ?></span></div>
            <div class="warranty-field"><span class="warranty-label">دستگاه:</span><span><?php echo $last_assignment['asset_name']; ?> (<?php echo $last_assignment['asset_model']; ?>)</span></div>
            <div class="warranty-field"><span class="warranty-label">سریال دستگاه:</span><span><?php echo $last_assignment['asset_serial']; ?></span></div>
            <div class="warranty-field"><span class="warranty-label">تاریخ نصب:</span><span><?php echo gregorianToJalaliFromDB($last_assignment['installation_date']); ?></span></div>
            <div class="warranty-field"><span class="warranty-label">شروع گارانتی:</span><span><?php echo gregorianToJalaliFromDB($last_assignment['warranty_start_date']); ?></span></div>
            <div class="warranty-field"><span class="warranty-label">پایان گارانتی:</span><span><?php echo gregorianToJalaliFromDB($last_assignment['warranty_end_date']); ?></span></div>
            <div class="warranty-field"><span class="warranty-label">شرایط گارانتی:</span><span><?php echo $last_assignment['warranty_conditions']; ?></span></div>
        </div>
        <div class="warranty-footer">
            <p>این سند به منزله تأیید گارانتی دستگاه فوق می‌باشد | شماره تماس: ۰۲۱-۱۲۳۴۵۶۷۸</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Assignment Form -->
    <div class="card" id="assignmentForm" style="display: none;">
        <div class="card-header">
            <i class="fas fa-plus me-2"></i>انتساب جدید
        </div>
        <div class="card-body">
            <form method="POST" id="assignmentFormElement" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="customer_id" class="form-label">انتخاب مشتری *</label>
                            <select class="form-select" id="customer_id" name="customer_id" required>
                                <option value="">-- انتخاب مشتری --</option>
                                <?php foreach ($customers as $customer): ?>
                                    <option value="<?php echo $customer['id']; ?>" data-phone="<?php echo htmlspecialchars($customer['phone']); ?>">
                                        <?php echo htmlspecialchars($customer['full_name']); ?> - <?php echo htmlspecialchars($customer['phone']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="asset_id" class="form-label">انتخاب دستگاه *</label>
                            <select class="form-select" id="asset_id" name="asset_id" required>
                                <option value="">-- انتخاب دستگاه --</option>
                                <?php foreach ($assets as $asset): ?>
                                    <option value="<?php echo $asset['id']; ?>" 
                                            data-model="<?php echo htmlspecialchars($asset['model']); ?>" 
                                            data-serial="<?php echo htmlspecialchars($asset['serial_number']); ?>">
                                        <?php echo htmlspecialchars($asset['name']); ?> (<?php echo htmlspecialchars($asset['serial_number']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="assignment_date" class="form-label">تاریخ انتساب *</label>
                            <input type="text" class="form-control jalali-date" id="assignment_date" name="assignment_date" 
                                   required value="<?php echo jalali_format(date('Y-m-d')); ?>" readonly>
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
                    <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                </div>

                <div id="assignmentDetails" class="assignment-details">
                    <h4 class="mb-4 mt-4">
                        <i class="fas fa-tools me-2"></i>اطلاعات کامل نصب و راه‌اندازی
                    </h4>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="installation_date">تاریخ نصب</label>
                                <input type="text" class="form-control jalali-date" id="installation_date" name="installation_date" readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="delivery_person">نام تحویل دهنده</label>
                                <input type="text" class="form-control" id="delivery_person" name="delivery_person">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="recipient_name">نام تحویل گیرنده</label>
                                <input type="text" class="form-control" id="recipient_name" name="recipient_name">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="recipient_phone">شماره تماس تحویل گیرنده</label>
                                <input type="text" class="form-control" id="recipient_phone" name="recipient_phone">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="installer_name">نام نصاب</label>
                                <input type="text" class="form-control" id="installer_name" name="installer_name">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="installation_address">آدرس محل نصب</label>
                        <textarea class="form-control" id="installation_address" name="installation_address" rows="3"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="warranty_start_date">تاریخ آغاز گارانتی</label>
                                <input type="text" class="form-control jalali-date" id="warranty_start_date" name="warranty_start_date" readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="warranty_end_date">تاریخ پایان گارانتی</label>
                                <input type="text" class="form-control jalali-date" id="warranty_end_date" name="warranty_end_date" readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="installation_start_date">تاریخ آغاز نصب</label>
                                <input type="text" class="form-control jalali-date" id="installation_start_date" name="installation_start_date" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="installation_end_date">تاریخ اتمام نصب</label>
                                <input type="text" class="form-control jalali-date" id="installation_end_date" name="installation_end_date" readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="temporary_delivery_date">تاریخ تحویل موقت</label>
                                <input type="text" class="form-control jalali-date" id="temporary_delivery_date" name="temporary_delivery_date" readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="permanent_delivery_date">تاریخ تحویل دائم</label>
                                <input type="text" class="form-control jalali-date" id="permanent_delivery_date" name="permanent_delivery_date" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="first_service_date">تاریخ سرویس اولیه</label>
                                <input type="text" class="form-control jalali-date" id="first_service_date" name="first_service_date" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="warranty_conditions">شرایط گارانتی</label>
                        <textarea class="form-control" id="warranty_conditions" name="warranty_conditions" rows="3" placeholder="شرایط و ضوابط گارانتی دستگاه"></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="post_installation_commitments">تعهدات پس از راه‌اندازی</label>
                        <textarea class="form-control" id="post_installation_commitments" name="post_installation_commitments" rows="3"></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="additional_notes">توضیحات تکمیلی</label>
                        <textarea class="form-control" id="additional_notes" name="additional_notes" rows="3"></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="installation_photo">عکس نصب نهایی دستگاه</label>
                        <input type="file" class="form-control" id="installation_photo" name="installation_photo" accept="image/*" onchange="previewImage(this,'installation_photo_preview')">
                        <img id="installation_photo_preview" class="image-preview" src="#" alt="پیش‌نمایش عکس نصب">
                    </div>
                </div>

                <div class="text-end">
                    <button type="button" class="btn btn-secondary me-2" onclick="toggleAssignmentForm()">انصراف</button>
                    <button type="submit" name="assign_asset" class="btn btn-primary">
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
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>دستگاه</th>
                            <th>مشتری</th>
                            <th>تاریخ انتساب</th>
                            <th>تحویل گیرنده</th>
                            <th>آدرس نصب</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assignments as $assignment): ?>
                        <tr>
                            <td><strong>#<?php echo $assignment['id']; ?></strong></td>
                            <td>
                                <div>
                                    <strong><?php echo $assignment['asset_name']; ?></strong>
                                    <br>
                                    <small class="text-muted">سریال: <?php echo $assignment['asset_serial']; ?></small>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <strong><?php echo $assignment['customer_name']; ?></strong>
                                    <br>
                                    <small class="text-muted"><?php echo $assignment['customer_phone']; ?></small>
                                </div>
                            </td>
                            <td><?php echo gregorianToJalaliFromDB($assignment['assignment_date']); ?></td>
                            <td><?php echo $assignment['recipient_name'] ?? '-'; ?></td>
                            <td>
                                <?php 
                                if (!empty($assignment['installation_address'])) {
                                    $address = $assignment['installation_address'];
                                    echo strlen($address) > 30 ? substr($address, 0, 30) . '...' : $address;
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#detailsModal<?php echo $assignment['id']; ?>">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <a href="edit_assignment.php?id=<?php echo $assignment['id']; ?>" class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Details Modals -->
    <?php foreach ($assignments as $assignment): ?>
    <div class="modal fade" id="detailsModal<?php echo $assignment['id']; ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-eye me-2"></i>جزئیات انتساب #<?php echo $assignment['id']; ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>دستگاه:</strong> <?php echo $assignment['asset_name']; ?></p>
                            <p><strong>مشتری:</strong> <?php echo $assignment['customer_name']; ?></p>
                            <p><strong>تاریخ انتساب:</strong> <?php echo gregorianToJalaliFromDB($assignment['assignment_date']); ?></p>
                            <p><strong>نام تحویل دهنده:</strong> <?php echo $assignment['delivery_person'] ?? '-'; ?></p>
                            <p><strong>نام تحویل گیرنده:</strong> <?php echo $assignment['recipient_name'] ?? '-'; ?></p>
                            <p><strong>تلفن تحویل گیرنده:</strong> <?php echo $assignment['recipient_phone'] ?? '-'; ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>تاریخ نصب:</strong> <?php echo gregorianToJalaliFromDB($assignment['installation_date']); ?></p>
                            <p><strong>تاریخ آغاز گارانتی:</strong> <?php echo gregorianToJalaliFromDB($assignment['warranty_start_date']); ?></p>
                            <p><strong>تاریخ پایان گارانتی:</strong> <?php echo gregorianToJalaliFromDB($assignment['warranty_end_date']); ?></p>
                            <p><strong>نام نصاب:</strong> <?php echo $assignment['installer_name'] ?? '-'; ?></p>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-12">
                            <p><strong>آدرس نصب:</strong> <?php echo $assignment['installation_address'] ?? '-'; ?></p>
                            <p><strong>شرایط گارانتی:</strong> <?php echo $assignment['warranty_conditions'] ?? '-'; ?></p>
                            <p><strong>تعهدات پس از راه‌اندازی:</strong> <?php echo $assignment['post_installation_commitments'] ?? '-'; ?></p>
                            <?php if(!empty($assignment['installation_photo'])): ?>
                            <p><strong>عکس نصب:</strong></p>
                            <img src="<?php echo $assignment['installation_photo']; ?>" class="img-thumbnail" style="max-width:300px;">
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="edit_assignment.php?id=<?php echo $assignment['id']; ?>" class="btn btn-warning">
                        <i class="fas fa-edit me-2"></i>ویرایش این انتساب
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/persian-date@1.1.0/dist/persian-date.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js"></script>

<script>
// Toggle assignment form
function toggleAssignmentForm() {
    console.log('Toggle function called');
    var form = document.getElementById('assignmentForm');
    if (form.style.display === 'none' || form.style.display === '') {
        form.style.display = 'block';
        form.scrollIntoView({ behavior: 'smooth' });
    } else {
        form.style.display = 'none';
    }
}

function loadCustomerInfo() {
    var customerSelect = document.getElementById('customer_id');
    var assetSelect = document.getElementById('asset_id');
    var assignmentDetails = document.getElementById('assignmentDetails');

    if (customerSelect.value && assetSelect.value) {
        assignmentDetails.style.display = 'block';
    } else {
        assignmentDetails.style.display = 'none';
    }
}

function loadAssetDetails() {
    var assetSelect = document.getElementById('asset_id');
    var deviceModel = document.getElementById('device_model_display');
    var deviceSerial = document.getElementById('device_serial_display');

    if (assetSelect.value) {
        var selectedAsset = assetSelect.options[assetSelect.selectedIndex];
        if (deviceModel) deviceModel.value = selectedAsset.getAttribute('data-model') || '';
        if (deviceSerial) deviceSerial.value = selectedAsset.getAttribute('data-serial') || '';
    }
    loadCustomerInfo();
}

function previewImage(input, imgId) {
    var preview = document.getElementById(imgId);
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        }
        reader.readAsDataURL(input.files[0]);
    }
}

function printWarranty(assignmentId = null) {
    var warrantyCard = document.getElementById('warrantyCard');
    if (warrantyCard) {
        window.print();
    }
}

document.addEventListener('DOMContentLoaded', function() {
    var cid = document.getElementById('customer_id');
    var aid = document.getElementById('asset_id');
    if (cid) cid.addEventListener('change', loadCustomerInfo);
    if (aid) aid.addEventListener('change', loadAssetDetails);
    loadAssetDetails();
    
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
    }
});
</script>
</body>
</html>