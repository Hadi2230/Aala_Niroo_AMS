<?php
/**
 * Step 4: تکمیل نصب
 */

if (!isset($_SESSION['install_complete'])) {
    header('Location: ?step=1');
    exit();
}

// حذف فایل نصب
if (isset($_POST['remove_install'])) {
    unlink(__FILE__);
    unlink('install.php');
    rmdir('install_steps');
    header('Location: index.php');
    exit();
}
?>

<div class="card">
    <div class="card-header bg-success text-white">
        <h5 class="mb-0">
            <i class="fas fa-check-circle me-2"></i>
            مرحله 4: نصب با موفقیت تکمیل شد!
        </h5>
    </div>
    <div class="card-body text-center">
        <div class="mb-4">
            <i class="fas fa-trophy text-warning" style="font-size: 4rem;"></i>
        </div>
        
        <h3 class="text-success mb-3">تبریک! سیستم با موفقیت نصب شد</h3>
        
        <div class="alert alert-success">
            <h5>اطلاعات نصب:</h5>
            <ul class="list-unstyled mb-0">
                <li><strong>نام سایت:</strong> <?php echo $_POST['site_name'] ?? 'Aala Niroo AMS'; ?></li>
                <li><strong>نام کاربری ادمین:</strong> <?php echo $_POST['admin_username'] ?? 'admin'; ?></li>
                <li><strong>دیتابیس:</strong> <?php echo $_SESSION['db_config']['database'] ?? 'aala_niroo_ams'; ?></li>
                <li><strong>تاریخ نصب:</strong> <?php echo jalaliDate(); ?></li>
            </ul>
        </div>
        
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card border-primary">
                    <div class="card-body">
                        <h5 class="card-title text-primary">
                            <i class="fas fa-rocket me-2"></i>
                            شروع کار
                        </h5>
                        <p class="card-text">سیستم آماده استفاده است. می‌توانید با حساب کاربری ادمین وارد شوید.</p>
                        <a href="index.php" class="btn btn-primary">
                            <i class="fas fa-home me-2"></i>
                            ورود به سیستم
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-info">
                    <div class="card-body">
                        <h5 class="card-title text-info">
                            <i class="fas fa-cog me-2"></i>
                            تنظیمات
                        </h5>
                        <p class="card-text">برای امنیت بیشتر، فایل‌های نصب را حذف کنید.</p>
                        <form method="POST" class="d-inline">
                            <button type="submit" name="remove_install" class="btn btn-info" 
                                    onclick="return confirm('آیا مطمئن هستید که می‌خواهید فایل‌های نصب را حذف کنید؟')">
                                <i class="fas fa-trash me-2"></i>
                                حذف فایل‌های نصب
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="alert alert-warning mt-4">
            <h6><i class="fas fa-exclamation-triangle me-2"></i>نکات مهم:</h6>
            <ul class="text-start mb-0">
                <li>فایل‌های نصب را حذف کنید تا امنیت سیستم حفظ شود</li>
                <li>رمز عبور ادمین را تغییر دهید</li>
                <li>از سیستم پشتیبان‌گیری منظم استفاده کنید</li>
                <li>لاگ‌های سیستم را بررسی کنید</li>
            </ul>
        </div>
        
        <div class="mt-4">
            <h6>لینک‌های مفید:</h6>
            <div class="btn-group" role="group">
                <a href="index.php" class="btn btn-outline-primary">صفحه اصلی</a>
                <a href="users.php" class="btn btn-outline-secondary">مدیریت کاربران</a>
                <a href="system_logs.php" class="btn btn-outline-info">لاگ سیستم</a>
                <a href="quick_test_simple.php" class="btn btn-outline-success">تست سیستم</a>
            </div>
        </div>
    </div>
</div>