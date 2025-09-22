<?php
/**
 * Step 1: بررسی نیازمندی‌های سیستم
 */
?>

<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">
            <i class="fas fa-clipboard-check me-2"></i>
            مرحله 1: بررسی نیازمندی‌های سیستم
        </h5>
    </div>
    <div class="card-body">
        <?php
        $requirements = [
            'PHP Version' => [
                'required' => '8.0+',
                'current' => PHP_VERSION,
                'status' => version_compare(PHP_VERSION, '8.0.0', '>='),
                'description' => 'نسخه PHP باید 8.0 یا بالاتر باشد'
            ],
            'MySQL Extension' => [
                'required' => 'PDO MySQL',
                'current' => extension_loaded('pdo_mysql') ? 'موجود' : 'غیرموجود',
                'status' => extension_loaded('pdo_mysql'),
                'description' => 'PDO MySQL extension برای اتصال به دیتابیس'
            ],
            'JSON Extension' => [
                'required' => 'JSON',
                'current' => extension_loaded('json') ? 'موجود' : 'غیرموجود',
                'status' => extension_loaded('json'),
                'description' => 'JSON extension برای پردازش داده‌ها'
            ],
            'MBString Extension' => [
                'required' => 'MBString',
                'current' => extension_loaded('mbstring') ? 'موجود' : 'غیرموجود',
                'status' => extension_loaded('mbstring'),
                'description' => 'MBString extension برای پشتیبانی از زبان فارسی'
            ],
            'GD Extension' => [
                'required' => 'GD',
                'current' => extension_loaded('gd') ? 'موجود' : 'غیرموجود',
                'status' => extension_loaded('gd'),
                'description' => 'GD extension برای پردازش تصاویر'
            ],
            'File Upload' => [
                'required' => 'فعال',
                'current' => ini_get('file_uploads') ? 'فعال' : 'غیرفعال',
                'status' => ini_get('file_uploads'),
                'description' => 'قابلیت آپلود فایل باید فعال باشد'
            ],
            'Write Permissions' => [
                'required' => 'قابل نوشتن',
                'current' => is_writable('.') ? 'قابل نوشتن' : 'غیرقابل نوشتن',
                'status' => is_writable('.'),
                'description' => 'پوشه پروژه باید قابل نوشتن باشد'
            ]
        ];

        $all_requirements_met = true;
        ?>

        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>نیازمندی</th>
                        <th>وضعیت فعلی</th>
                        <th>وضعیت</th>
                        <th>توضیحات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requirements as $name => $req): ?>
                        <tr>
                            <td><strong><?php echo $name; ?></strong></td>
                            <td><?php echo $req['current']; ?></td>
                            <td>
                                <?php if ($req['status']): ?>
                                    <span class="badge bg-success">
                                        <i class="fas fa-check me-1"></i>
                                        OK
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger">
                                        <i class="fas fa-times me-1"></i>
                                        خطا
                                    </span>
                                    <?php $all_requirements_met = false; ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $req['description']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($all_requirements_met): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i>
                <strong>عالی!</strong> تمام نیازمندی‌های سیستم برآورده شده است.
            </div>
            <div class="text-center">
                <a href="?step=2" class="btn btn-primary btn-lg">
                    <i class="fas fa-arrow-right me-2"></i>
                    مرحله بعد
                </a>
            </div>
        <?php else: ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>خطا!</strong> برخی نیازمندی‌ها برآورده نشده است. لطفاً قبل از ادامه، مشکلات را برطرف کنید.
            </div>
            <div class="text-center">
                <button onclick="location.reload()" class="btn btn-warning">
                    <i class="fas fa-refresh me-2"></i>
                    بررسی مجدد
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>