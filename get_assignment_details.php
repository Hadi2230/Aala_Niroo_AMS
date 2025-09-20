<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

include 'config.php';

$assignment_id = $_GET['id'] ?? 0;

try {
    $stmt = $pdo->prepare("
        SELECT 
            aa.*,
            a.name as asset_name,
            a.serial_number,
            a.brand,
            a.model,
            a.power_capacity,
            a.device_model,
            a.device_serial,
            a.engine_model,
            a.engine_serial,
            a.alternator_model,
            a.alternator_serial,
            a.control_panel_model,
            a.breaker_model,
            a.fuel_tank_specs,
            a.battery,
            a.battery_charger,
            a.heater,
            a.oil_capacity,
            a.radiator_capacity,
            a.antifreeze,
            a.other_items,
            a.description as asset_description,
            c.full_name as customer_name,
            c.phone as customer_phone,
            c.company,
            c.customer_type,
            c.address as customer_address,
            c.city,
            ad.*
        FROM asset_assignments aa
        LEFT JOIN assets a ON aa.asset_id = a.id
        LEFT JOIN customers c ON aa.customer_id = c.id
        LEFT JOIN assignment_details ad ON aa.id = ad.assignment_id
        WHERE aa.id = ?
    ");
    
    $stmt->execute([$assignment_id]);
    $assignment = $stmt->fetch();
    
    if (!$assignment) {
        echo '<div class="alert alert-danger">انتساب مورد نظر یافت نشد.</div>';
        exit;
    }
    
    $is_admin = ($_SESSION['role'] === 'ادمین' || $_SESSION['role'] === 'admin');
    
    ?>
    <div class="row">
        <!-- اطلاعات دستگاه -->
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0"><i class="fas fa-server me-2"></i>اطلاعات دستگاه</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <td><strong>نام دستگاه:</strong></td>
                            <td><?php echo htmlspecialchars($assignment['asset_name']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>شماره سریال:</strong></td>
                            <td><?php echo htmlspecialchars($assignment['serial_number']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>برند:</strong></td>
                            <td><?php echo htmlspecialchars($assignment['brand']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>مدل:</strong></td>
                            <td><?php echo htmlspecialchars($assignment['model']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>قدرت:</strong></td>
                            <td><?php echo htmlspecialchars($assignment['power_capacity']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>مدل دستگاه:</strong></td>
                            <td><?php echo htmlspecialchars($assignment['device_model']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>سریال دستگاه:</strong></td>
                            <td><?php echo htmlspecialchars($assignment['device_serial']); ?></td>
                        </tr>
                        <?php if ($assignment['engine_model']): ?>
                        <tr>
                            <td><strong>مدل موتور:</strong></td>
                            <td><?php echo htmlspecialchars($assignment['engine_model']); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($assignment['engine_serial']): ?>
                        <tr>
                            <td><strong>سریال موتور:</strong></td>
                            <td><?php echo htmlspecialchars($assignment['engine_serial']); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($assignment['alternator_model']): ?>
                        <tr>
                            <td><strong>مدل آلترناتور:</strong></td>
                            <td><?php echo htmlspecialchars($assignment['alternator_model']); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($assignment['alternator_serial']): ?>
                        <tr>
                            <td><strong>سریال آلترناتور:</strong></td>
                            <td><?php echo htmlspecialchars($assignment['alternator_serial']); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>

        <!-- اطلاعات مشتری -->
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0"><i class="fas fa-user me-2"></i>اطلاعات مشتری</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <td><strong>نام:</strong></td>
                            <td><?php echo htmlspecialchars($assignment['customer_name']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>تلفن:</strong></td>
                            <td><?php echo htmlspecialchars($assignment['customer_phone']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>شرکت:</strong></td>
                            <td><?php echo htmlspecialchars($assignment['company'] ?? '-'); ?></td>
                        </tr>
                        <tr>
                            <td><strong>نوع مشتری:</strong></td>
                            <td><?php echo htmlspecialchars($assignment['customer_type']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>آدرس:</strong></td>
                            <td><?php echo htmlspecialchars($assignment['customer_address'] ?? '-'); ?></td>
                        </tr>
                        <tr>
                            <td><strong>شهر:</strong></td>
                            <td><?php echo htmlspecialchars($assignment['city'] ?? '-'); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- اطلاعات انتساب -->
    <div class="row">
        <div class="col-12">
            <div class="card mb-3">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="fas fa-link me-2"></i>اطلاعات انتساب</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <p><strong>تاریخ انتساب:</strong> <?php echo gregorianToJalaliFromDB($assignment['assignment_date']); ?></p>
                        </div>
                        <div class="col-md-4">
                            <p><strong>وضعیت:</strong> 
                                <span class="badge bg-success"><?php echo $assignment['assignment_status']; ?></span>
                            </p>
                        </div>
                        <div class="col-md-4">
                            <p><strong>تاریخ ایجاد:</strong> <?php echo jalali_format($assignment['created_at']); ?></p>
                        </div>
                    </div>
                    <?php if ($assignment['notes']): ?>
                    <div class="row">
                        <div class="col-12">
                            <p><strong>یادداشت‌ها:</strong></p>
                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($assignment['notes'])); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- جزئیات نصب -->
    <?php if ($assignment['installation_date'] || $assignment['delivery_person']): ?>
    <div class="row">
        <div class="col-12">
            <div class="card mb-3">
                <div class="card-header bg-warning text-dark">
                    <h6 class="mb-0"><i class="fas fa-tools me-2"></i>جزئیات نصب</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php if ($assignment['installation_date']): ?>
                        <div class="col-md-3">
                            <p><strong>تاریخ نصب:</strong><br><?php echo gregorianToJalaliFromDB($assignment['installation_date']); ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if ($assignment['delivery_person']): ?>
                        <div class="col-md-3">
                            <p><strong>مسئول تحویل:</strong><br><?php echo htmlspecialchars($assignment['delivery_person']); ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if ($assignment['installer_name']): ?>
                        <div class="col-md-3">
                            <p><strong>نصب کننده:</strong><br><?php echo htmlspecialchars($assignment['installer_name']); ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if ($assignment['warranty_start_date']): ?>
                        <div class="col-md-3">
                            <p><strong>شروع گارانتی:</strong><br><?php echo gregorianToJalaliFromDB($assignment['warranty_start_date']); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($assignment['installation_address']): ?>
                    <div class="row">
                        <div class="col-12">
                            <p><strong>آدرس نصب:</strong></p>
                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($assignment['installation_address'])); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($assignment['warranty_conditions']): ?>
                    <div class="row">
                        <div class="col-12">
                            <p><strong>شرایط گارانتی:</strong></p>
                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($assignment['warranty_conditions'])); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($assignment['post_installation_commitments']): ?>
                    <div class="row">
                        <div class="col-12">
                            <p><strong>تعهدات پس از نصب:</strong></p>
                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($assignment['post_installation_commitments'])); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($assignment['additional_notes']): ?>
                    <div class="row">
                        <div class="col-12">
                            <p><strong>یادداشت‌های اضافی:</strong></p>
                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($assignment['additional_notes'])); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($assignment['installation_photo']): ?>
                    <div class="row">
                        <div class="col-12">
                            <p><strong>عکس نصب:</strong></p>
                            <img src="<?php echo htmlspecialchars($assignment['installation_photo']); ?>" 
                                 class="img-fluid rounded" style="max-width: 300px;" 
                                 alt="عکس نصب">
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- اطلاعات کارفرما و گیرنده -->
    <?php if ($assignment['employer_name'] || $assignment['recipient_name']): ?>
    <div class="row">
        <div class="col-md-6">
            <?php if ($assignment['employer_name']): ?>
            <div class="card mb-3">
                <div class="card-header bg-secondary text-white">
                    <h6 class="mb-0"><i class="fas fa-building me-2"></i>اطلاعات کارفرما</h6>
                </div>
                <div class="card-body">
                    <p><strong>نام کارفرما:</strong> <?php echo htmlspecialchars($assignment['employer_name']); ?></p>
                    <?php if ($assignment['employer_phone']): ?>
                    <p><strong>تلفن کارفرما:</strong> <?php echo htmlspecialchars($assignment['employer_phone']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="col-md-6">
            <?php if ($assignment['recipient_name']): ?>
            <div class="card mb-3">
                <div class="card-header bg-dark text-white">
                    <h6 class="mb-0"><i class="fas fa-user-check me-2"></i>اطلاعات گیرنده</h6>
                </div>
                <div class="card-body">
                    <p><strong>نام گیرنده:</strong> <?php echo htmlspecialchars($assignment['recipient_name']); ?></p>
                    <?php if ($assignment['recipient_phone']): ?>
                    <p><strong>تلفن گیرنده:</strong> <?php echo htmlspecialchars($assignment['recipient_phone']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- عملیات (فقط برای ادمین) -->
    <?php if ($is_admin): ?>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h6 class="mb-0"><i class="fas fa-cogs me-2"></i>عملیات مدیریتی</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex gap-2">
                        <button class="btn btn-warning" onclick="editAssignment(<?php echo $assignment['id']; ?>)">
                            <i class="fas fa-edit me-1"></i>ویرایش
                        </button>
                        <button class="btn btn-danger" onclick="deleteAssignment(<?php echo $assignment['id']; ?>)">
                            <i class="fas fa-trash me-1"></i>حذف
                        </button>
                        <button class="btn btn-info" onclick="printAssignment(<?php echo $assignment['id']; ?>)">
                            <i class="fas fa-print me-1"></i>چاپ
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
    function printAssignment(assignmentId) {
        window.open(`print_assignment.php?id=${assignmentId}`, '_blank');
    }
    </script>
    <?php
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">خطا در بارگذاری جزئیات: ' . $e->getMessage() . '</div>';
    logAction($pdo, 'ASSIGNMENT_DETAILS_ERROR', "خطا در بارگذاری جزئیات انتساب: " . $e->getMessage(), 'error');
}
?>