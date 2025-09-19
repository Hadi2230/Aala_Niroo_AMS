<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// دریافت اطلاعات انتساب
$assignment_id = $_GET['id'] ?? null;
if (!$assignment_id) {
    header('Location: assignments.php');
    exit();
}

// دریافت اطلاعات انتساب
$stmt = $pdo->prepare("
    SELECT aa.*, ad.*, a.name as asset_name, c.full_name as customer_name, c.phone as customer_phone
    FROM asset_assignments aa
    JOIN assets a ON aa.asset_id = a.id
    JOIN customers c ON aa.customer_id = c.id
    LEFT JOIN assignment_details ad ON aa.id = ad.assignment_id
    WHERE aa.id = ?
");
$stmt->execute([$assignment_id]);
$assignment = $stmt->fetch();

if (!$assignment) {
    header('Location: assignments.php');
    exit();
}

// ویرایش انتساب
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_assignment'])) {
    $asset_id = $_POST['asset_id'];
    $customer_id = $_POST['customer_id'];
    $assignment_date = jalaliToGregorianForDB($_POST['assignment_date']);
    $notes = $_POST['notes'];
    
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
    
    try {
        $pdo->beginTransaction();
        
        // آپدیت انتساب اصلی
        $stmt = $pdo->prepare("UPDATE asset_assignments SET asset_id = ?, customer_id = ?, assignment_date = ?, notes = ? WHERE id = ?");
        $stmt->execute([$asset_id, $customer_id, $assignment_date, $notes, $assignment_id]);
        
        // آپلود عکس جدید اگر ارائه شده
        $upload_dir = 'uploads/installations/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $installation_photo = $assignment['installation_photo'];
        if (!empty($_FILES['installation_photo']['name'])) {
            // حذف عکس قبلی اگر وجود دارد
            if (!empty($installation_photo) && file_exists($installation_photo)) {
                unlink($installation_photo);
            }
            
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
        
        // بررسی آیا اطلاعات کامل انتساب از قبل وجود دارد
        if ($assignment['assignment_id']) {
            // آپدیت اطلاعات موجود
            $stmt = $pdo->prepare("UPDATE assignment_details SET 
                installation_date = ?, delivery_person = ?, installation_address = ?, 
                warranty_start_date = ?, warranty_conditions = ?, employer_name = ?, employer_phone = ?,
                recipient_name = ?, recipient_phone = ?, installer_name = ?, installation_start_date = ?,
                installation_end_date = ?, temporary_delivery_date = ?, permanent_delivery_date = ?,
                first_service_date = ?, post_installation_commitments = ?, notes = ?, installation_photo = ?
                WHERE assignment_id = ?");
            
            $stmt->execute([
                $installation_date, $delivery_person, $installation_address,
                $warranty_start_date, $warranty_conditions, $employer_name, $employer_phone,
                $recipient_name, $recipient_phone, $installer_name, $installation_start_date,
                $installation_end_date, $temporary_delivery_date, $permanent_delivery_date,
                $first_service_date, $post_installation_commitments, $additional_notes, $installation_photo,
                $assignment_id
            ]);
        } else {
            // درج اطلاعات جدید
            $stmt = $pdo->prepare("INSERT INTO assignment_details 
                (assignment_id, installation_date, delivery_person, installation_address, 
                warranty_start_date, warranty_conditions, employer_name, employer_phone,
                recipient_name, recipient_phone, installer_name, installation_start_date,
                installation_end_date, temporary_delivery_date, permanent_delivery_date,
                first_service_date, post_installation_commitments, notes, installation_photo) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $assignment_id, $installation_date, $delivery_person, $installation_address,
                $warranty_start_date, $warranty_conditions, $employer_name, $employer_phone,
                $recipient_name, $recipient_phone, $installer_name, $installation_start_date,
                $installation_end_date, $temporary_delivery_date, $permanent_delivery_date,
                $first_service_date, $post_installation_commitments, $additional_notes, $installation_photo
            ]);
        }
        
        $pdo->commit();
        $success = "انتساب با موفقیت ویرایش شد!";
        header('Location: assignments.php?success=' . urlencode($success));
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "خطا در ویرایش انتساب: " . $e->getMessage();
    }
}

// دریافت لیست دستگاه‌ها و مشتریان
$assets = $pdo->query("SELECT id, name, device_model, device_serial, engine_model, engine_serial FROM assets WHERE status = 'فعال' ORDER BY name")->fetchAll();
$customers = $pdo->query("SELECT id, full_name, phone FROM customers ORDER BY full_name")->fetchAll();
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ویرایش انتساب - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .image-preview {
            max-width: 200px;
            max-height: 200px;
            margin-top: 10px;
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
                        <a class="nav-link" href="logout.php">خروج</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <h2 class="text-center">ویرایش انتساب #<?php echo $assignment['id']; ?></h2>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card mt-4">
            <div class="card-header">ویرایش اطلاعات انتساب</div>
            <div class="card-body">
                <form method="POST" id="editAssignmentForm" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="customer_id" class="form-label">انتخاب مشتری *</label>
                                <select class="form-select" id="customer_id" name="customer_id" required>
                                    <option value="">-- انتخاب مشتری --</option>
                                    <?php foreach ($customers as $customer): ?>
                                        <option value="<?php echo $customer['id']; ?>" 
                                            <?php echo $customer['id'] == $assignment['customer_id'] ? 'selected' : ''; ?>
                                            data-phone="<?php echo $customer['phone']; ?>">
                                            <?php echo $customer['full_name']; ?>
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
                                            <?php echo $asset['id'] == $assignment['asset_id'] ? 'selected' : ''; ?>
                                            data-model="<?php echo $asset['device_model']; ?>"
                                            data-serial="<?php echo $asset['device_serial']; ?>">
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
                                <input type="text" class="form-control jalali-date" id="assignment_date" name="assignment_date" 
                                       value="<?php echo gregorianToJalaliFromDB($assignment['assignment_date']); ?>" required readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">مدل دستگاه</label>
                                <input type="text" class="form-control" id="device_model_display" 
                                       value="<?php echo $assignment['device_model']; ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">سریال دستگاه</label>
                                <input type="text" class="form-control" id="device_serial_display" 
                                       value="<?php echo $assignment['device_serial']; ?>" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="notes" class="form-label">توضیحات اولیه (اختیاری)</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2"><?php echo $assignment['notes']; ?></textarea>
                    </div>

                    <h4 class="mb-4 mt-4">اطلاعات کامل نصب و راه‌اندازی</h4>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="installation_date" class="form-label">تاریخ نصب</label>
                                <input type="text" class="form-control jalali-date" id="installation_date" name="installation_date" 
                                       value="<?php echo gregorianToJalaliFromDB($assignment['installation_date']); ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="delivery_person" class="form-label">نام تحویل دهنده</label>
                                <input type="text" class="form-control" id="delivery_person" name="delivery_person" 
                                       value="<?php echo $assignment['delivery_person']; ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="recipient_name" class="form-label">نام تحویل گیرنده</label>
                                <input type="text" class="form-control" id="recipient_name" name="recipient_name" 
                                       value="<?php echo $assignment['recipient_name']; ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="recipient_phone" class="form-label">شماره تماس تحویل گیرنده</label>
                                <input type="text" class="form-control" id="recipient_phone" name="recipient_phone" 
                                       value="<?php echo $assignment['recipient_phone']; ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="installer_name" class="form-label">نام نصاب</label>
                                <input type="text" class="form-control" id="installer_name" name="installer_name" 
                                       value="<?php echo $assignment['installer_name']; ?>">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="installation_address" class="form-label">آدرس محل نصب</label>
                        <textarea class="form-control" id="installation_address" name="installation_address" rows="3"><?php echo $assignment['installation_address']; ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="warranty_start_date" class="form-label">تاریخ آغاز گارانتی</label>
                                <input type="text" class="form-control jalali-date" id="warranty_start_date" name="warranty_start_date" 
                                       value="<?php echo gregorianToJalaliFromDB($assignment['warranty_start_date']); ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="installation_start_date" class="form-label">تاریخ آغاز نصب</label>
                                <input type="text" class="form-control jalali-date" id="installation_start_date" name="installation_start_date" 
                                       value="<?php echo gregorianToJalaliFromDB($assignment['installation_start_date']); ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="installation_end_date" class="form-label">تاریخ اتمام نصب</label>
                                <input type="text" class="form-control jalali-date" id="installation_end_date" name="installation_end_date" 
                                       value="<?php echo gregorianToJalaliFromDB($assignment['installation_end_date']); ?>" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="temporary_delivery_date" class="form-label">تاریخ تحویل موقت</label>
                                <input type="text" class="form-control jalali-date" id="temporary_delivery_date" name="temporary_delivery_date" 
                                       value="<?php echo gregorianToJalaliFromDB($assignment['temporary_delivery_date']); ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="permanent_delivery_date" class="form-label">تاریخ تحویل دائم</label>
                                <input type="text" class="form-control jalali-date" id="permanent_delivery_date" name="permanent_delivery_date" 
                                       value="<?php echo gregorianToJalaliFromDB($assignment['permanent_delivery_date']); ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="first_service_date" class="form-label">تاریخ سرویس اولیه</label>
                                <input type="text" class="form-control jalali-date" id="first_service_date" name="first_service_date" 
                                       value="<?php echo gregorianToJalaliFromDB($assignment['first_service_date']); ?>" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="warranty_conditions" class="form-label">شرایط گارانتی</label>
                        <textarea class="form-control" id="warranty_conditions" name="warranty_conditions" rows="3"><?php echo $assignment['warranty_conditions']; ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="post_installation_commitments" class="form-label">تعهدات پس از راه‌اندازی</label>
                        <textarea class="form-control" id="post_installation_commitments" name="post_installation_commitments" rows="3"><?php echo $assignment['post_installation_commitments']; ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="additional_notes" class="form-label">توضیحات تکمیلی</label>
                        <textarea class="form-control" id="additional_notes" name="additional_notes" rows="3"><?php echo $assignment['notes']; ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="installation_photo" class="form-label">عکس نصب نهایی دستگاه</label>
                        <input type="file" class="form-control" id="installation_photo" name="installation_photo" accept="image/*" 
                               onchange="previewImage(this, 'installation_photo_preview')">
                        <?php if (!empty($assignment['installation_photo'])): ?>
                            <div class="mt-2">
                                <p>عکس فعلی:</p>
                                <img src="<?php echo $assignment['installation_photo']; ?>" class="img-thumbnail" style="max-width: 200px;">
                                <br>
                                <a href="<?php echo $assignment['installation_photo']; ?>" target="_blank" class="btn btn-sm btn-info mt-2">مشاهده کامل</a>
                                <button type="button" class="btn btn-sm btn-danger mt-2" onclick="removePhoto()">حذف عکس</button>
                            </div>
                        <?php endif; ?>
                        <img id="installation_photo_preview" class="image-preview" src="#" alt="پیش‌نمایش عکس جدید">
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="employer_name" class="form-label">نام کارفرما</label>
                                <input type="text" class="form-control" id="employer_name" name="employer_name" 
                                       value="<?php echo $assignment['employer_name']; ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="employer_phone" class="form-label">شماره تماس کارفرما</label>
                                <input type="text" class="form-control" id="employer_phone" name="employer_phone" 
                                       value="<?php echo $assignment['employer_phone']; ?>">
                            </div>
                        </div>
                    </div>

                    <button type="submit" name="edit_assignment" class="btn btn-primary">ذخیره تغییرات</button>
                    <a href="assignments.php" class="btn btn-secondary">انصراف</a>
                </form>
            </div>
        </div>
    </div>

    <script>
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
    
    function removePhoto() {
        if (confirm('آیا از حذف این عکس مطمئن هستید؟')) {
            // اینجا می‌توانید یک فیلد مخفی اضافه کنید تا به سرور بفهمانید که عکس باید حذف شود
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'remove_photo';
            hiddenInput.value = '1';
            document.getElementById('editAssignmentForm').appendChild(hiddenInput);
            
            // حذف نمایش عکس
            const photoContainer = document.querySelector('.mt-2');
            if (photoContainer) {
                photoContainer.remove();
            }
        }
    }
    
    // بارگذاری اطلاعات دستگاه هنگام تغییر
    document.getElementById('asset_id').addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption.value) {
            document.getElementById('device_model_display').value = selectedOption.getAttribute('data-model');
            document.getElementById('device_serial_display').value = selectedOption.getAttribute('data-serial');
        }
    });
    
    // بارگذاری اطلاعات مشتری هنگام تغییر
    document.getElementById('customer_id').addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption.value) {
            document.getElementById('employer_name').value = selectedOption.text;
            document.getElementById('employer_phone').value = selectedOption.getAttribute('data-phone');
        }
    });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>