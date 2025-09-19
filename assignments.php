<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

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
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "خطا در انتساب دستگاه: " . $e->getMessage();
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
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>انتساب دستگاه به مشتری - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css">
    <style>
        .assignment-details { display: none; }
        .image-preview {
            max-width: 200px;
            max-height: 200px;
            margin-top: 10px;
            display: none;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">اعلا نیرو</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">داشبورد</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="assets.php">مدیریت دارایی‌ها</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="customers.php">مدیریت مشتریان</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="assignments.php">انتساب دستگاه</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">گزارش‌ها</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">خروج</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <h2 class="text-center">انتساب دستگاه به مشتری</h2>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- فرم انتساب دستگاه -->
        <div class="card mt-4">
            <div class="card-header">انتساب جدید</div>
            <div class="card-body">
                <form method="POST" id="assignmentForm" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="customer_id" class="form-label">انتخاب مشتری *</label>
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
                                <label for="asset_id" class="form-label">انتخاب دستگاه *</label>
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
                                <label for="assignment_date" class="form-label">تاریخ انتساب *</label>
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
                        <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                    </div>

                    <!-- اطلاعات کامل انتساب -->
                    <div id="assignmentDetails" class="assignment-details">
                        <h4 class="mb-4 mt-4">اطلاعات کامل نصب و راه‌اندازی</h4>
                        
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
                                    <input type="text" class="form-control" id="delivery_person" name="delivery_person">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="recipient_name" class="form-label">نام تحویل گیرنده</label>
                                    <input type="text" class="form-control" id="recipient_name" name="recipient_name">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="recipient_phone" class="form-label">شماره تماس تحویل گیرنده</label>
                                    <input type="text" class="form-control" id="recipient_phone" name="recipient_phone">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="installer_name" class="form-label">نام نصاب</label>
                                    <input type="text" class="form-control" id="installer_name" name="installer_name">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="installation_address" class="form-label">آدرس محل نصب</label>
                            <textarea class="form-control" id="installation_address" name="installation_address" rows="3"></textarea>
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
                            <textarea class="form-control" id="warranty_conditions" name="warranty_conditions" rows="3"></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="post_installation_commitments" class="form-label">تعهدات پس از راه‌اندازی</label>
                            <textarea class="form-control" id="post_installation_commitments" name="post_installation_commitments" rows="3"></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="additional_notes" class="form-label">توضیحات تکمیلی</label>
                            <textarea class="form-control" id="additional_notes" name="additional_notes" rows="3"></textarea>
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

                    <button type="submit" name="assign_asset" class="btn btn-primary">انتساب دستگاه و ثبت اطلاعات</button>
                </form>
            </div>
        </div>

        <!-- لیست انتساب‌ها -->
        <div class="card mt-5">
            <div class="card-header">لیست انتساب‌های انجام شده</div>
            <div class="card-body">
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
                            <td><?php echo $assignment['id']; ?></td>
                            <td><?php echo $assignment['asset_name']; ?></td>
                            <td><?php echo $assignment['customer_name']; ?></td>
                            <td><?php echo gregorianToJalaliFromDB($assignment['assignment_date']); ?></td>
                            <td><?php echo $assignment['recipient_name']; ?></td>
                            <td><?php echo substr($assignment['installation_address'], 0, 30); ?>...</td>
                            <td>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" 
                                            data-bs-target="#detailsModal<?php echo $assignment['id']; ?>">
                                        جزئیات
                                    </button>
                                    <a href="edit_assignment.php?id=<?php echo $assignment['id']; ?>" class="btn btn-sm btn-warning">ویرایش</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal های جزئیات -->
    <?php foreach ($assignments as $assignment): ?>
    <div class="modal fade" id="detailsModal<?php echo $assignment['id']; ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">جزئیات انتساب #<?php echo $assignment['id']; ?></h5>
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
                            <p><strong>دستگاه:</strong> <?php echo $assignment['asset_name']; ?></p>
                            <p><strong>مشتری:</strong> <?php echo $assignment['customer_name']; ?></p>
                            <p><strong>تاریخ انتساب:</strong> <?php echo gregorianToJalaliFromDB($assignment['assignment_date']); ?></p>
                            <p><strong>نام تحویل دهنده:</strong> <?php echo $assignment_details['delivery_person']; ?></p>
                            <p><strong>نام تحویل گیرنده:</strong> <?php echo $assignment_details['recipient_name']; ?></p>
                            <p><strong>تلفن تحویل گیرنده:</strong> <?php echo $assignment_details['recipient_phone']; ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>تاریخ نصب:</strong> <?php echo gregorianToJalaliFromDB($assignment_details['installation_date']); ?></p>
                            <p><strong>تاریخ آغاز گارانتی:</strong> <?php echo gregorianToJalaliFromDB($assignment_details['warranty_start_date']); ?></p>
                            <p><strong>نام نصاب:</strong> <?php echo $assignment_details['installer_name']; ?></p>
                            <p><strong>تاریخ تحویل موقت:</strong> <?php echo gregorianToJalaliFromDB($assignment_details['temporary_delivery_date']); ?></p>
                            <p><strong>تاریخ تحویل دائم:</strong> <?php echo gregorianToJalaliFromDB($assignment_details['permanent_delivery_date']); ?></p>
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-12">
                            <p><strong>آدرس نصب:</strong> <?php echo $assignment_details['installation_address']; ?></p>
                            <p><strong>شرایط گارانتی:</strong> <?php echo $assignment_details['warranty_conditions']; ?></p>
                            <p><strong>تعهدات پس از راه‌اندازی:</strong> <?php echo $assignment_details['post_installation_commitments']; ?></p>
                            
                            <?php if (!empty($assignment_details['installation_photo'])): ?>
                                <p><strong>عکس نصب:</strong></p>
                                <img src="<?php echo $assignment_details['installation_photo']; ?>" class="img-thumbnail" style="max-width: 300px;">
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="edit_assignment.php?id=<?php echo $assignment['id']; ?>" class="btn btn-warning">ویرایش این انتساب</a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

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
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/persian-date@1.1.0/dist/persian-date.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js"></script>
    <script>
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