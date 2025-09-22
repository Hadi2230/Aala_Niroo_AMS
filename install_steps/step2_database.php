<?php
/**
 * Step 2: تنظیم دیتابیس
 */

if ($_POST) {
    $host = $_POST['host'] ?? 'localhost';
    $port = $_POST['port'] ?? '3306';
    $username = $_POST['username'] ?? 'root';
    $password = $_POST['password'] ?? '';
    $database = $_POST['database'] ?? 'aala_niroo_ams';
    
    try {
        // تست اتصال به MySQL
        $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        // ایجاد دیتابیس
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_persian_ci");
        $pdo->exec("USE `$database`");
        
        // اجرای فایل SQL
        $sql = file_get_contents('database.sql');
        $pdo->exec($sql);
        
        // ذخیره تنظیمات
        $_SESSION['db_config'] = [
            'host' => $host,
            'port' => $port,
            'username' => $username,
            'password' => $password,
            'database' => $database
        ];
        
        header('Location: ?step=3');
        exit();
        
    } catch (Exception $e) {
        $error = 'خطا در اتصال به دیتابیس: ' . $e->getMessage();
    }
}
?>

<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">
            <i class="fas fa-database me-2"></i>
            مرحله 2: تنظیم دیتابیس
        </h5>
    </div>
    <div class="card-body">
        <form method="POST">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="host" class="form-label">میزبان دیتابیس</label>
                        <input type="text" class="form-control" id="host" name="host" value="<?php echo $db_config['host']; ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="port" class="form-label">پورت</label>
                        <input type="number" class="form-control" id="port" name="port" value="<?php echo $db_config['port']; ?>" required>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="username" class="form-label">نام کاربری</label>
                        <input type="text" class="form-control" id="username" name="username" value="<?php echo $db_config['username']; ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="password" class="form-label">رمز عبور</label>
                        <input type="password" class="form-control" id="password" name="password" value="<?php echo $db_config['password']; ?>">
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="database" class="form-label">نام دیتابیس</label>
                <input type="text" class="form-control" id="database" name="database" value="<?php echo $db_config['database']; ?>" required>
                <div class="form-text">اگر دیتابیس وجود نداشته باشد، خودکار ایجاد می‌شود.</div>
            </div>
            
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <strong>نکته:</strong> اطمینان حاصل کنید که کاربر دیتابیس مجوز ایجاد دیتابیس و جداول را دارد.
            </div>
            
            <div class="text-center">
                <a href="?step=1" class="btn btn-secondary me-2">
                    <i class="fas fa-arrow-right me-2"></i>
                    مرحله قبل
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-database me-2"></i>
                    اتصال و ایجاد دیتابیس
                </button>
            </div>
        </form>
    </div>
</div>