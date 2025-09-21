<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// بررسی دسترسی ادمین
$is_admin = ($_SESSION['role'] === 'ادمین' || $_SESSION['role'] === 'admin');

// ثبت لاگ مشاهده صفحه
logAction($pdo, 'VIEW_SYSTEM_TEST', 'مشاهده صفحه تست سیستم');

$test_results = [];
$overall_status = 'success';

// تابع برای اجرای تست
function runTest($test_name, $test_function) {
    global $test_results, $overall_status;
    
    $start_time = microtime(true);
    $result = ['name' => $test_name, 'status' => 'success', 'message' => '', 'duration' => 0];
    
    try {
        $test_result = $test_function();
        if (is_array($test_result)) {
            $result = array_merge($result, $test_result);
        } else {
            $result['message'] = $test_result;
        }
    } catch (Exception $e) {
        $result['status'] = 'error';
        $result['message'] = $e->getMessage();
        $overall_status = 'error';
    }
    
    $result['duration'] = round((microtime(true) - $start_time) * 1000, 2);
    $test_results[] = $result;
}

// تست‌های دیتابیس
runTest('اتصال به دیتابیس', function() {
    global $pdo;
    $stmt = $pdo->query("SELECT 1");
    return 'اتصال موفق - ' . $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS);
});

runTest('بررسی جداول اصلی', function() {
    global $pdo;
    $tables = ['users', 'assets', 'customers', 'asset_assignments', 'assignment_details', 'system_logs'];
    $existing_tables = [];
    $missing_tables = [];
    
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                $existing_tables[] = $table;
            } else {
                $missing_tables[] = $table;
            }
        } catch (Exception $e) {
            $missing_tables[] = $table;
        }
    }
    
    return [
        'status' => empty($missing_tables) ? 'success' : 'warning',
        'message' => 'جداول موجود: ' . implode(', ', $existing_tables) . 
                    (empty($missing_tables) ? '' : ' | جداول مفقود: ' . implode(', ', $missing_tables)),
        'data' => ['existing' => $existing_tables, 'missing' => $missing_tables]
    ];
});

runTest('تعداد رکوردها در جداول', function() {
    global $pdo;
    $tables = ['users', 'assets', 'customers', 'asset_assignments', 'assignment_details', 'system_logs'];
    $counts = [];
    
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
            $count = $stmt->fetch()['count'];
            $counts[$table] = $count;
        } catch (Exception $e) {
            $counts[$table] = 'خطا';
        }
    }
    
    return [
        'message' => 'تعداد رکوردها در هر جدول',
        'data' => $counts
    ];
});

// تست‌های عملکرد
runTest('تست توابع تاریخ شمسی', function() {
    $test_date = '1403/01/01';
    $gregorian = jalaliToGregorianForDB($test_date);
    $jalali = gregorianToJalaliFromDB($gregorian);
    
    return [
        'status' => ($jalali === $test_date) ? 'success' : 'error',
        'message' => "تست تبدیل تاریخ: $test_date → $gregorian → $jalali"
    ];
});

runTest('تست توابع کمکی', function() {
    $functions = ['sanitizeInput', 'validateEmail', 'generateRandomCode', 'formatPhoneNumber'];
    $working_functions = [];
    $broken_functions = [];
    
    foreach ($functions as $func) {
        if (function_exists($func)) {
            try {
                if ($func === 'sanitizeInput') {
                    $result = sanitizeInput('test');
                } elseif ($func === 'validateEmail') {
                    $result = validateEmail('test@example.com');
                } elseif ($func === 'generateRandomCode') {
                    $result = generateRandomCode(6);
                } elseif ($func === 'formatPhoneNumber') {
                    $result = formatPhoneNumber('09123456789');
                }
                $working_functions[] = $func;
            } catch (Exception $e) {
                $broken_functions[] = $func;
            }
        } else {
            $broken_functions[] = $func;
        }
    }
    
    return [
        'status' => empty($broken_functions) ? 'success' : 'warning',
        'message' => 'توابع کار: ' . implode(', ', $working_functions) . 
                    (empty($broken_functions) ? '' : ' | توابع مشکل‌دار: ' . implode(', ', $broken_functions)),
        'data' => ['working' => $working_functions, 'broken' => $broken_functions]
    ];
});

// تست‌های فایل‌ها
runTest('بررسی فایل‌های اصلی', function() {
    $files = [
        'config.php' => 'فایل تنظیمات',
        'navbar.php' => 'منوی ناوبری',
        'login.php' => 'صفحه ورود',
        'assets.php' => 'مدیریت دارایی‌ها',
        'customers.php' => 'مدیریت مشتریان',
        'assignments.php' => 'مدیریت انتساب‌ها',
        'system_logs.php' => 'لاگ سیستم'
    ];
    
    $existing_files = [];
    $missing_files = [];
    
    foreach ($files as $file => $description) {
        if (file_exists($file)) {
            $existing_files[] = "$description ($file)";
        } else {
            $missing_files[] = "$description ($file)";
        }
    }
    
    return [
        'status' => empty($missing_files) ? 'success' : 'warning',
        'message' => 'فایل‌های موجود: ' . count($existing_files) . ' | فایل‌های مفقود: ' . count($missing_files),
        'data' => ['existing' => $existing_files, 'missing' => $missing_files]
    ];
});

runTest('بررسی پوشه‌های آپلود', function() {
    $upload_dirs = ['uploads/', 'uploads/installations/', 'uploads/assets/', 'uploads/customers/', 'logs/'];
    $existing_dirs = [];
    $missing_dirs = [];
    
    foreach ($upload_dirs as $dir) {
        if (is_dir($dir)) {
            $existing_dirs[] = $dir;
        } else {
            $missing_dirs[] = $dir;
        }
    }
    
    return [
        'status' => empty($missing_dirs) ? 'success' : 'warning',
        'message' => 'پوشه‌های موجود: ' . implode(', ', $existing_dirs) . 
                    (empty($missing_dirs) ? '' : ' | پوشه‌های مفقود: ' . implode(', ', $missing_dirs)),
        'data' => ['existing' => $existing_dirs, 'missing' => $missing_dirs]
    ];
});

// تست‌های امنیت
runTest('بررسی تنظیمات امنیت', function() {
    $security_checks = [];
    
    // بررسی session
    $security_checks['session_started'] = session_status() === PHP_SESSION_ACTIVE;
    
    // بررسی CSRF token
    $security_checks['csrf_token'] = isset($_SESSION['csrf_token']);
    
    // بررسی error reporting
    $security_checks['error_reporting'] = error_reporting() !== 0;
    
    // بررسی display_errors
    $security_checks['display_errors'] = ini_get('display_errors') == 0;
    
    $passed = array_sum($security_checks);
    $total = count($security_checks);
    
    return [
        'status' => $passed === $total ? 'success' : 'warning',
        'message' => "بررسی امنیت: $passed/$total موفق",
        'data' => $security_checks
    ];
});

// تست‌های عملکرد سیستم
runTest('بررسی حافظه و زمان اجرا', function() {
    $memory_usage = memory_get_usage(true);
    $memory_peak = memory_get_peak_usage(true);
    $memory_limit = ini_get('memory_limit');
    $execution_time = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
    
    return [
        'message' => "حافظه: " . round($memory_usage / 1024 / 1024, 2) . "MB | " .
                    "حداکثر: " . round($memory_peak / 1024 / 1024, 2) . "MB | " .
                    "حد: $memory_limit | " .
                    "زمان: " . round($execution_time, 3) . "s",
        'data' => [
            'memory_usage' => $memory_usage,
            'memory_peak' => $memory_peak,
            'memory_limit' => $memory_limit,
            'execution_time' => $execution_time
        ]
    ];
});

// تست‌های کاربران و دسترسی
runTest('بررسی کاربران سیستم', function() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
        $roles = $stmt->fetchAll();
        
        $role_counts = [];
        foreach ($roles as $role) {
            $role_counts[$role['role']] = $role['count'];
        }
        
        return [
            'message' => 'توزیع نقش‌ها: ' . implode(', ', array_map(function($role, $count) {
                return "$role: $count";
            }, array_keys($role_counts), $role_counts)),
            'data' => $role_counts
        ];
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => 'خطا در دریافت اطلاعات کاربران: ' . $e->getMessage()
        ];
    }
});

// تست‌های عملکرد صفحات
runTest('تست بارگذاری صفحات', function() {
    $pages = ['assets.php', 'customers.php', 'assignments.php', 'system_logs.php'];
    $working_pages = [];
    $broken_pages = [];
    
    foreach ($pages as $page) {
        if (file_exists($page)) {
            $working_pages[] = $page;
        } else {
            $broken_pages[] = $page;
        }
    }
    
    return [
        'status' => empty($broken_pages) ? 'success' : 'warning',
        'message' => 'صفحات کار: ' . implode(', ', $working_pages) . 
                    (empty($broken_pages) ? '' : ' | صفحات مشکل‌دار: ' . implode(', ', $broken_pages)),
        'data' => ['working' => $working_pages, 'broken' => $broken_pages]
    ];
});

// تست‌های لاگ سیستم
runTest('بررسی سیستم لاگ', function() {
    global $pdo;
    
    try {
        // تست نوشتن لاگ
        $test_message = 'تست سیستم لاگ - ' . date('Y-m-d H:i:s');
        logAction($pdo, 'SYSTEM_TEST', $test_message);
        
        // بررسی آخرین لاگ
        $stmt = $pdo->query("SELECT * FROM system_logs ORDER BY created_at DESC LIMIT 1");
        $last_log = $stmt->fetch();
        
        return [
            'status' => $last_log ? 'success' : 'warning',
            'message' => $last_log ? 'سیستم لاگ فعال' : 'مشکل در سیستم لاگ',
            'data' => $last_log
        ];
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => 'خطا در سیستم لاگ: ' . $e->getMessage()
        ];
    }
});

// تست‌های عملکرد دیتابیس
runTest('تست عملکرد دیتابیس', function() {
    global $pdo;
    
    $start_time = microtime(true);
    
    try {
        // تست SELECT
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        $user_count = $stmt->fetchColumn();
        
        // تست INSERT (تست)
        $test_data = [
            'test_action' => 'SYSTEM_TEST_INSERT',
            'test_message' => 'تست عملکرد دیتابیس',
            'test_timestamp' => date('Y-m-d H:i:s')
        ];
        
        $stmt = $pdo->prepare("INSERT INTO system_logs (action, message, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$test_data['test_action'], $test_data['test_message']]);
        
        // تست UPDATE
        $log_id = $pdo->lastInsertId();
        $stmt = $pdo->prepare("UPDATE system_logs SET message = ? WHERE id = ?");
        $stmt->execute(['تست به‌روزرسانی موفق', $log_id]);
        
        // تست DELETE
        $stmt = $pdo->prepare("DELETE FROM system_logs WHERE id = ?");
        $stmt->execute([$log_id]);
        
        $end_time = microtime(true);
        $duration = round(($end_time - $start_time) * 1000, 2);
        
        return [
            'message' => "عملیات CRUD موفق - زمان: {$duration}ms - کاربران: $user_count",
            'data' => ['duration' => $duration, 'user_count' => $user_count]
        ];
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => 'خطا در تست عملکرد دیتابیس: ' . $e->getMessage()
        ];
    }
});

// تست‌های شبکه و اتصال
runTest('بررسی تنظیمات PHP', function() {
    $php_settings = [
        'PHP Version' => PHP_VERSION,
        'Memory Limit' => ini_get('memory_limit'),
        'Max Execution Time' => ini_get('max_execution_time'),
        'Upload Max Filesize' => ini_get('upload_max_filesize'),
        'Post Max Size' => ini_get('post_max_size'),
        'Max Input Vars' => ini_get('max_input_vars'),
        'Session Save Path' => ini_get('session.save_path'),
        'Default Timezone' => date_default_timezone_get()
    ];
    
    return [
        'message' => 'تنظیمات PHP سیستم',
        'data' => $php_settings
    ];
});

// تست‌های فایل سیستم
runTest('بررسی مجوزهای فایل', function() {
    $directories = ['uploads/', 'logs/', '.'];
    $permissions = [];
    
    foreach ($directories as $dir) {
        if (is_dir($dir)) {
            $perms = fileperms($dir);
            $permissions[$dir] = [
                'readable' => is_readable($dir),
                'writable' => is_writable($dir),
                'permissions' => substr(sprintf('%o', $perms), -4)
            ];
        }
    }
    
    return [
        'message' => 'مجوزهای فایل و پوشه',
        'data' => $permissions
    ];
});

// محاسبه آمار کلی
$total_tests = count($test_results);
$successful_tests = count(array_filter($test_results, function($test) {
    return $test['status'] === 'success';
}));
$warning_tests = count(array_filter($test_results, function($test) {
    return $test['status'] === 'warning';
}));
$error_tests = count(array_filter($test_results, function($test) {
    return $test['status'] === 'error';
}));

$success_rate = round(($successful_tests / $total_tests) * 100, 1);
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تست سیستم - اعلا نیرو</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Persian Font -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #56ab2f;
            --warning-color: #f093fb;
            --danger-color: #ff416c;
            --info-color: #4facfe;
            --dark-color: #2c3e50;
            --light-color: #f8f9fa;
        }

        body {
            font-family: Vazirmatn, Tahoma, Arial, sans-serif;
            background-color: var(--light-color);
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

        .test-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 1rem;
            border: none;
        }

        .test-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .test-status {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.9rem;
        }

        .status-success {
            background: linear-gradient(135deg, var(--success-color), #56ab2f);
            color: white;
        }

        .status-warning {
            background: linear-gradient(135deg, var(--warning-color), #f093fb);
            color: white;
        }

        .status-error {
            background: linear-gradient(135deg, var(--danger-color), #ff6b6b);
            color: white;
        }

        .test-duration {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .test-data {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
        }

        .overall-status {
            text-align: center;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
        }

        .overall-success {
            background: linear-gradient(135deg, var(--success-color), #56ab2f);
            color: white;
        }

        .overall-warning {
            background: linear-gradient(135deg, var(--warning-color), #f093fb);
            color: white;
        }

        .overall-error {
            background: linear-gradient(135deg, var(--danger-color), #ff6b6b);
            color: white;
        }

        .progress-bar {
            height: 20px;
            border-radius: 10px;
        }

        .btn-refresh {
            background: linear-gradient(135deg, var(--info-color), #4facfe);
            border: none;
            border-radius: 10px;
            color: white;
            padding: 0.75rem 2rem;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .btn-refresh:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(79, 172, 254, 0.4);
            color: white;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <!-- Page Header -->
        <div class="page-header">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="mb-0">
                            <i class="fas fa-vial me-3"></i>تست سیستم
                        </h1>
                        <p class="mb-0 mt-2">بررسی کامل عملکرد و وضعیت سیستم</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <button class="btn btn-refresh" onclick="location.reload()">
                            <i class="fas fa-sync-alt me-2"></i>تست مجدد
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Overall Status -->
        <div class="overall-status <?php echo $overall_status === 'success' ? 'overall-success' : ($overall_status === 'warning' ? 'overall-warning' : 'overall-error'); ?>">
            <h2 class="mb-3">
                <i class="fas fa-<?php echo $overall_status === 'success' ? 'check-circle' : ($overall_status === 'warning' ? 'exclamation-triangle' : 'times-circle'); ?> me-2"></i>
                وضعیت کلی سیستم
            </h2>
            <h3 class="mb-3"><?php echo $success_rate; ?>% موفق</h3>
            <div class="progress mb-3" style="height: 20px;">
                <div class="progress-bar" role="progressbar" style="width: <?php echo $success_rate; ?>%" 
                     aria-valuenow="<?php echo $success_rate; ?>" aria-valuemin="0" aria-valuemax="100">
                </div>
            </div>
            <p class="mb-0">
                <strong><?php echo $successful_tests; ?></strong> موفق | 
                <strong><?php echo $warning_tests; ?></strong> هشدار | 
                <strong><?php echo $error_tests; ?></strong> خطا
            </p>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon" style="background: linear-gradient(135deg, var(--success-color), #56ab2f);">
                        <i class="fas fa-check"></i>
                    </div>
                    <h3 class="mb-1"><?php echo $successful_tests; ?></h3>
                    <p class="text-muted mb-0">تست‌های موفق</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon" style="background: linear-gradient(135deg, var(--warning-color), #f093fb);">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h3 class="mb-1"><?php echo $warning_tests; ?></h3>
                    <p class="text-muted mb-0">تست‌های هشدار</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon" style="background: linear-gradient(135deg, var(--danger-color), #ff6b6b);">
                        <i class="fas fa-times"></i>
                    </div>
                    <h3 class="mb-1"><?php echo $error_tests; ?></h3>
                    <p class="text-muted mb-0">تست‌های خطا</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon" style="background: linear-gradient(135deg, var(--info-color), #4facfe);">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3 class="mb-1"><?php echo $total_tests; ?></h3>
                    <p class="text-muted mb-0">کل تست‌ها</p>
                </div>
            </div>
        </div>

        <!-- Test Results -->
        <div class="row">
            <div class="col-12">
                <h3 class="mb-4">
                    <i class="fas fa-list me-2"></i>نتایج تست‌ها
                </h3>
                
                <?php foreach ($test_results as $index => $test): ?>
                <div class="test-card">
                    <div class="test-header">
                        <div>
                            <h5 class="mb-1">
                                <i class="fas fa-<?php echo $test['status'] === 'success' ? 'check-circle text-success' : ($test['status'] === 'warning' ? 'exclamation-triangle text-warning' : 'times-circle text-danger'); ?> me-2"></i>
                                <?php echo $test['name']; ?>
                            </h5>
                            <p class="mb-0 text-muted"><?php echo $test['message']; ?></p>
                        </div>
                        <div class="text-end">
                            <span class="test-status status-<?php echo $test['status']; ?>">
                                <?php echo $test['status'] === 'success' ? 'موفق' : ($test['status'] === 'warning' ? 'هشدار' : 'خطا'); ?>
                            </span>
                            <div class="test-duration mt-1">
                                <i class="fas fa-clock me-1"></i><?php echo $test['duration']; ?>ms
                            </div>
                        </div>
                    </div>
                    
                    <?php if (isset($test['data']) && !empty($test['data'])): ?>
                    <div class="test-data">
                        <strong>جزئیات:</strong><br>
                        <?php if (is_array($test['data'])): ?>
                            <pre><?php echo json_encode($test['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></pre>
                        <?php else: ?>
                            <?php echo $test['data']; ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- System Information -->
        <div class="row mt-5">
            <div class="col-12">
                <div class="test-card">
                    <h4 class="mb-3">
                        <i class="fas fa-info-circle me-2"></i>اطلاعات سیستم
                    </h4>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></p>
                            <p><strong>Server:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'نامشخص'; ?></p>
                            <p><strong>OS:</strong> <?php echo PHP_OS; ?></p>
                            <p><strong>Memory Limit:</strong> <?php echo ini_get('memory_limit'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Max Execution Time:</strong> <?php echo ini_get('max_execution_time'); ?>s</p>
                            <p><strong>Upload Max Filesize:</strong> <?php echo ini_get('upload_max_filesize'); ?></p>
                            <p><strong>Timezone:</strong> <?php echo date_default_timezone_get(); ?></p>
                            <p><strong>Current Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto refresh every 30 seconds
        setTimeout(function() {
            location.reload();
        }, 30000);
        
        // Add animation to test cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.test-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>