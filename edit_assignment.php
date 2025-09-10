<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
include 'config.php';

$assignment_id = $_GET['id'] ?? null;
if (!$assignment_id) {
    header('Location: assignments.php');
    exit();
}

$success = $error = '';

// دریافت اطلاعات انتساب
try {
    $stmt = $pdo->prepare("
        SELECT aa.*, a.name AS asset_name, a.model AS asset_model, a.serial_number AS asset_serial,
               c.full_name AS customer_name, c.phone AS customer_phone,
               ad.*
        FROM asset_assignments aa
        JOIN assets a ON aa.asset_id = a.id
        JOIN customers c ON aa.customer_id = c.id
        LEFT JOIN assignment_details ad ON aa.id = ad.assignment_id
        WHERE aa.id = ?
    ");
    $stmt->execute([$assignment_id]);
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$assignment) {
        header('Location: assignments.php');
        exit();
    }
} catch (Exception $e) {
    $error = "خطا در دریافت اطلاعات انتساب: " . $e->getMessage();
    $assignment = null;
}

// پردازش فرم ویرایش
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_assignment'])) {
    try {
        $pdo->beginTransaction();
        
        // به‌روزرسانی انتساب اصلی
        $assignment_date = $_POST['assignment_date'];
        $notes = $_POST['notes'];
        
        $stmt = $pdo->prepare("UPDATE asset_assignments SET assignment_date = ?, notes = ? WHERE id = ?");
        $stmt->execute([$assignment_date, $notes, $assignment_id]);
        
        // به‌روزرسانی جزئیات انتساب
        $fields = [
            'installation_date', 'delivery_person', 'installation_address',
            'warranty_start_date', 'warranty_end_date', 'warranty_conditions', 'recipient_name',
            'recipient_phone', 'installer_name', 'installation_start_date',
            'installation_end_date', 'temporary_delivery_date', 'permanent_delivery_date',
            'first_service_date', 'post_installation_commitments', 'additional_notes'
        ];
        
        $update_data = [];
        foreach ($fields as $field) {
            $update_data[$field] = $_POST[$field] ?? null;
        }
        
        // آپلود عکس جدید
        $installation_photo = $assignment['installation_photo'] ?? '';
        if (!empty($_FILES['installation_photo']['name'])) {
            $upload_dir = 'uploads/installations/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $file_ext = pathinfo($_FILES['installation_photo']['name'], PATHINFO_EXTENSION);
            $file_name = time() . '_' . uniqid() . '_installation.' . $file_ext;
            $target_file = $upload_dir . $file_name;
            
            if (in_array(strtolower($file_ext), ['jpg','jpeg','png','gif']) &&
                move_uploaded_file($_FILES['installation_photo']['tmp_name'], $target_file)) {
                // حذف عکس قدیمی
                if (!empty($installation_photo) && file_exists($installation_photo)) {
                    unlink($installation_photo);
                }
                $installation_photo = $target_file;
            }
        }
        
        // بررسی وجود رکورد در assignment_details
        $stmt = $pdo->prepare("SELECT id FROM assignment_details WHERE assignment_id = ?");
        $stmt->execute([$assignment_id]);
        $details_exists = $stmt->fetch();
        
        if ($details_exists) {
            // به‌روزرسانی رکورد موجود
            $stmt = $pdo->prepare("
                UPDATE assignment_details SET
                installation_date = ?, delivery_person = ?, installation_address = ?,
                warranty_start_date = ?, warranty_end_date = ?, warranty_conditions = ?,
                recipient_name = ?, recipient_phone = ?, installer_name = ?,
                installation_start_date = ?, installation_end_date = ?,
                temporary_delivery_date = ?, permanent_delivery_date = ?,
                first_service_date = ?, post_installation_commitments = ?,
                notes = ?, installation_photo = ?
                WHERE assignment_id = ?
            ");
            $stmt->execute([
                $update_data['installation_date'], $update_data['delivery_person'], $update_data['installation_address'],
                $update_data['warranty_start_date'], $update_data['warranty_end_date'], $update_data['warranty_conditions'],
                $update_data['recipient_name'], $update_data['recipient_phone'], $update_data['installer_name'],
                $update_data['installation_start_date'], $update_data['installation_end_date'],
                $update_data['temporary_delivery_date'], $update_data['permanent_delivery_date'],
                $update_data['first_service_date'], $update_data['post_installation_commitments'],
                $update_data['additional_notes'], $installation_photo, $assignment_id
            ]);
        } else {
            // ایجاد رکورد جدید
            $stmt = $pdo->prepare("
                INSERT INTO assignment_details
                (assignment_id, installation_date, delivery_person, installation_address,
                 warranty_start_date, warranty_end_date, warranty_conditions,
                 recipient_name, recipient_phone, installer_name, installation_start_date,
                 installation_end_date, temporary_delivery_date, permanent_delivery_date,
                 first_service_date, post_installation_commitments, notes, installation_photo)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $assignment_id, $update_data['installation_date'], $update_data['delivery_person'], $update_data['installation_address'],
                $update_data['warranty_start_date'], $update_data['warranty_end_date'], $update_data['warranty_conditions'],
                $update_data['recipient_name'], $update_data['recipient_phone'], $update_data['installer_name'],
                $update_data['installation_start_date'], $update_data['installation_end_date'],
                $update_data['temporary_delivery_date'], $update_data['permanent_delivery_date'],
                $update_data['first_service_date'], $update_data['post_installation_commitments'],
                $update_data['additional_notes'], $installation_photo
            ]);
        }
        
        $pdo->commit();
        $success = "اطلاعات انتساب با موفقیت به‌روزرسانی شد!";
        
        // دریافت اطلاعات به‌روزرسانی شده
        $stmt = $pdo->prepare("
            SELECT aa.*, a.name AS asset_name, a.model AS asset_model, a.serial_number AS asset_serial,
                   c.full_name AS customer_name, c.phone AS customer_phone,
                   ad.*
            FROM asset_assignments aa
            JOIN assets a ON aa.asset_id = a.id
            JOIN customers c ON aa.customer_id = c.id
            LEFT JOIN assignment_details ad ON aa.id = ad.assignment_id
            WHERE aa.id = ?
        ");
        $stmt->execute([$assignment_id]);
        $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "خطا در به‌روزرسانی انتساب: " . $e->getMessage();
    }
}

// دریافت لیست دستگاه‌ها و مشتریان
try {
    $assets = $pdo->query("SELECT id, name, model, serial_number FROM assets ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $customers = $pdo->query("SELECT id, full_name, phone FROM customers ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "خطا در دریافت اطلاعات: " . $e->getMessage();
    $assets = $customers = [];
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ویرایش انتساب دستگاه - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <style>
        html, body { font-family: Vazirmatn, Tahoma, Arial, sans-serif; }
        .image-preview { max-width: 200px; max-height: 200px; margin-top: 10px; display: block; }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>ویرایش انتساب دستگاه</h2>
        <a href="assignments.php" class="btn btn-secondary">
            <i class="fas fa-arrow-right"></i> بازگشت به لیست
        </a>
    </div>

    <?php if(!empty($success)): ?>
        <div class='alert alert-success'><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if(!empty($error)): ?>
        <div class='alert alert-danger'><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if($assignment): ?>
    <div class="card">
        <div class="card-header"><i class="fas fa-edit"></i> ویرایش انتساب #<?php echo $assignment['id']; ?></div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">مشتری</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($assignment['customer_name']); ?>" readonly>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">دستگاه</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($assignment['asset_name']); ?>" readonly>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="assignment_date" class="form-label">تاریخ انتساب *</label>
                            <input type="date" class="form-control" id="assignment_date" name="assignment_date" 
                                   value="<?php echo $assignment['assignment_date']; ?>" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">مدل دستگاه</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($assignment['asset_model']); ?>" readonly>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">سریال دستگاه</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($assignment['asset_serial']); ?>" readonly>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="notes" class="form-label">توضیحات اولیه</label>
                    <textarea class="form-control" id="notes" name="notes" rows="2"><?php echo htmlspecialchars($assignment['notes']); ?></textarea>
                </div>

                <h4 class="mb-4 mt-4">اطلاعات کامل نصب و راه‌اندازی</h4>

                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="installation_date">تاریخ نصب</label>
                            <input type="date" class="form-control" id="installation_date" name="installation_date" 
                                   value="<?php echo $assignment['installation_date']; ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="delivery_person">نام تحویل دهنده</label>
                            <input type="text" class="form-control" id="delivery_person" name="delivery_person" 
                                   value="<?php echo htmlspecialchars($assignment['delivery_person']); ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="recipient_name">نام تحویل گیرنده</label>
                            <input type="text" class="form-control" id="recipient_name" name="recipient_name" 
                                   value="<?php echo htmlspecialchars($assignment['recipient_name']); ?>">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="recipient_phone">شماره تماس تحویل گیرنده</label>
                            <input type="text" class="form-control" id="recipient_phone" name="recipient_phone" 
                                   value="<?php echo htmlspecialchars($assignment['recipient_phone']); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="installer_name">نام نصاب</label>
                            <input type="text" class="form-control" id="installer_name" name="installer_name" 
                                   value="<?php echo htmlspecialchars($assignment['installer_name']); ?>">
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="installation_address">آدرس محل نصب</label>
                    <textarea class="form-control" id="installation_address" name="installation_address" rows="3"><?php echo htmlspecialchars($assignment['installation_address']); ?></textarea>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="warranty_start_date">تاریخ آغاز گارانتی</label>
                            <input type="date" class="form-control" id="warranty_start_date" name="warranty_start_date" 
                                   value="<?php echo $assignment['warranty_start_date']; ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="warranty_end_date">تاریخ پایان گارانتی</label>
                            <input type="date" class="form-control" id="warranty_end_date" name="warranty_end_date" 
                                   value="<?php echo $assignment['warranty_end_date']; ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="installation_start_date">تاریخ آغاز نصب</label>
                            <input type="date" class="form-control" id="installation_start_date" name="installation_start_date" 
                                   value="<?php echo $assignment['installation_start_date']; ?>">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="installation_end_date">تاریخ اتمام نصب</label>
                            <input type="date" class="form-control" id="installation_end_date" name="installation_end_date" 
                                   value="<?php echo $assignment['installation_end_date']; ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="temporary_delivery_date">تاریخ تحویل موقت</label>
                            <input type="date" class="form-control" id="temporary_delivery_date" name="temporary_delivery_date" 
                                   value="<?php echo $assignment['temporary_delivery_date']; ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="permanent_delivery_date">تاریخ تحویل دائم</label>
                            <input type="date" class="form-control" id="permanent_delivery_date" name="permanent_delivery_date" 
                                   value="<?php echo $assignment['permanent_delivery_date']; ?>">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="first_service_date">تاریخ سرویس اولیه</label>
                            <input type="date" class="form-control" id="first_service_date" name="first_service_date" 
                                   value="<?php echo $assignment['first_service_date']; ?>">
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="warranty_conditions">شرایط گارانتی</label>
                    <textarea class="form-control" id="warranty_conditions" name="warranty_conditions" rows="3"><?php echo htmlspecialchars($assignment['warranty_conditions']); ?></textarea>
                </div>

                <div class="mb-3">
                    <label for="post_installation_commitments">تعهدات پس از راه‌اندازی</label>
                    <textarea class="form-control" id="post_installation_commitments" name="post_installation_commitments" rows="3"><?php echo htmlspecialchars($assignment['post_installation_commitments']); ?></textarea>
                </div>

                <div class="mb-3">
                    <label for="additional_notes">توضیحات تکمیلی</label>
                    <textarea class="form-control" id="additional_notes" name="additional_notes" rows="3"><?php echo htmlspecialchars($assignment['additional_notes']); ?></textarea>
                </div>

                <div class="mb-3">
                    <label for="installation_photo">عکس نصب نهایی دستگاه</label>
                    <input type="file" class="form-control" id="installation_photo" name="installation_photo" accept="image/*" onchange="previewImage(this,'installation_photo_preview')">
                    <?php if(!empty($assignment['installation_photo'])): ?>
                        <div class="mt-2">
                            <p><strong>عکس فعلی:</strong></p>
                            <img src="<?php echo $assignment['installation_photo']; ?>" class="image-preview" alt="عکس فعلی">
                        </div>
                    <?php endif; ?>
                    <img id="installation_photo_preview" class="image-preview" src="#" alt="پیش‌نمایش عکس جدید" style="display: none;">
                </div>

                <div class="d-flex justify-content-between">
                    <a href="assignments.php" class="btn btn-secondary">انصراف</a>
                    <button type="submit" name="update_assignment" class="btn btn-primary">
                        <i class="fas fa-save"></i> ذخیره تغییرات
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php else: ?>
        <div class="alert alert-danger">انتساب مورد نظر یافت نشد!</div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function previewImage(input, imgId) {
    const preview = document.getElementById(imgId);
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        }
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
</body>
</html>