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
    SELECT aa.*, ad.*, a.name as asset_name, a.device_model as asset_device_model, a.device_serial as asset_device_serial,
           c.full_name as customer_name, c.phone as customer_phone
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
    $assignment_date = $_POST['assignment_date'];
    $notes = $_POST['notes'];
    
    $installation_date = $_POST['installation_date'];
    $delivery_person = $_POST['delivery_person'];
    $installation_address = $_POST['installation_address'];
    $warranty_start_date = $_POST['warranty_start_date'];
    $warranty_conditions = $_POST['warranty_conditions'];
    $employer_name = $_POST['employer_name'];
    $employer_phone = $_POST['employer_phone'];
    $recipient_name = $_POST['recipient_name'];
    $recipient_phone = $_POST['recipient_phone'];
    $installer_name = $_POST['installer_name'];
    $installation_start_date = $_POST['installation_start_date'];
    $installation_end_date = $_POST['installation_end_date'];
    $temporary_delivery_date = $_POST['temporary_delivery_date'];
    $permanent_delivery_date = $_POST['permanent_delivery_date'];
    $first_service_date = $_POST['first_service_date'];
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

// لیست‌ها به صورت AJAX Select2 بارگیری می‌شوند
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ویرایش انتساب - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .image-preview {
            max-width: 200px;
            max-height: 200px;
            margin-top: 10px;
        }
        .select2-container--default .select2-selection--single { height: 38px; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 38px; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 38px; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

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
                                    <option value="<?= $assignment['customer_id'] ?>" selected data-phone="<?= htmlspecialchars($assignment['customer_phone']) ?>">
                                        <?= htmlspecialchars($assignment['customer_name']) ?><?= $assignment['customer_phone'] ? ' - ' . htmlspecialchars($assignment['customer_phone']) : '' ?>
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="asset_id" class="form-label">انتخاب دستگاه *</label>
                                <select class="form-select" id="asset_id" name="asset_id" required>
                                    <option value="<?= $assignment['asset_id'] ?>" selected data-model="<?= htmlspecialchars($assignment['asset_device_model']) ?>" data-serial="<?= htmlspecialchars($assignment['asset_device_serial']) ?>">
                                        <?= htmlspecialchars($assignment['asset_name']) ?><?= $assignment['asset_device_model'] ? ' (مدل: ' . htmlspecialchars($assignment['asset_device_model']) . ')' : '' ?>
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="assignment_date" class="form-label">تاریخ انتساب *</label>
                                <input type="text" class="form-control jalali-date" id="assignment_date" name="assignment_date" 
                                       value="<?php echo $assignment['assignment_date']; ?>" required placeholder="YYYY/MM/DD">
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
                                       value="<?php echo $assignment['installation_date']; ?>" placeholder="YYYY/MM/DD">
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
                                       value="<?php echo $assignment['warranty_start_date']; ?>" placeholder="YYYY/MM/DD">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="installation_start_date" class="form-label">تاریخ آغاز نصب</label>
                                <input type="text" class="form-control jalali-date" id="installation_start_date" name="installation_start_date" 
                                       value="<?php echo $assignment['installation_start_date']; ?>" placeholder="YYYY/MM/DD">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="installation_end_date" class="form-label">تاریخ اتمام نصب</label>
                                <input type="text" class="form-control jalali-date" id="installation_end_date" name="installation_end_date" 
                                       value="<?php echo $assignment['installation_end_date']; ?>" placeholder="YYYY/MM/DD">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="temporary_delivery_date" class="form-label">تاریخ تحویل موقت</label>
                                <input type="text" class="form-control jalali-date" id="temporary_delivery_date" name="temporary_delivery_date" 
                                       value="<?php echo $assignment['temporary_delivery_date']; ?>" placeholder="YYYY/MM/DD">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="permanent_delivery_date" class="form-label">تاریخ تحویل دائم</label>
                                <input type="text" class="form-control jalali-date" id="permanent_delivery_date" name="permanent_delivery_date" 
                                       value="<?php echo $assignment['permanent_delivery_date']; ?>" placeholder="YYYY/MM/DD">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="first_service_date" class="form-label">تاریخ سرویس اولیه</label>
                                <input type="text" class="form-control jalali-date" id="first_service_date" name="first_service_date" 
                                       value="<?php echo $assignment['first_service_date']; ?>" placeholder="YYYY/MM/DD">
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

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
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
    
    // Select2 + AJAX with initial selection
    $(function(){
        $('#customer_id').select2({
            placeholder: '-- انتخاب مشتری --', dir:'rtl', width:'100%',
            ajax:{ url:'search_customers.php', dataType:'json', delay:250,
                data: params => ({ q: params.term || '', page: params.page || 1 }),
                processResults: data => ({ results: data.items }), cache:true }
        }).on('select2:select', function(e){
            const d = e.params.data || {};
            document.getElementById('employer_name').value = d.text || '';
            document.getElementById('employer_phone').value = d.phone || '';
            if ($('#asset_id').val()) document.getElementById('assignmentDetails').style.display = 'block';
        });

        $('#asset_id').select2({
            placeholder: '-- انتخاب دستگاه --', dir:'rtl', width:'100%',
            ajax:{ url:'search_assets.php', dataType:'json', delay:250,
                data: params => ({ q: params.term || '', page: params.page || 1 }),
                processResults: data => ({ results: data.items }), cache:true }
        }).on('select2:select', function(e){
            const d = e.params.data || {};
            document.getElementById('device_model_display').value = d.device_model || '';
            document.getElementById('device_serial_display').value = d.device_serial || '';
            if ($('#customer_id').val()) document.getElementById('assignmentDetails').style.display = 'block';
        });

        // مقداردهی اولیه نمایش دستگاه
        document.getElementById('device_model_display').value = <?= json_encode($assignment['asset_device_model'] ?? '') ?>;
        document.getElementById('device_serial_display').value = <?= json_encode($assignment['asset_device_serial'] ?? '') ?>;
        document.getElementById('assignmentDetails').style.display = 'block';
    });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>