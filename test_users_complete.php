<?php
/**
 * test_users_complete.php - تست کامل سیستم مدیریت کاربران
 */

session_start();
$_SESSION['user_id'] = 1; // Simulate logged-in admin
$_SESSION['role'] = 'ادمین'; // Simulate admin role

echo "<h1>🧪 تست کامل سیستم مدیریت کاربران</h1>";
echo "<style>
    body { font-family: Tahoma; direction: rtl; }
    .test-section { background: #f8f9fa; padding: 20px; margin: 10px 0; border-radius: 8px; }
    .success { color: #28a745; font-weight: bold; }
    .error { color: #dc3545; font-weight: bold; }
    .info { color: #17a2b8; }
</style>";

echo "<div class='test-section'>";
echo "<h2>1️⃣ تست بارگذاری فایل اصلی</h2>";
echo "<p>تست بارگذاری users_complete.php...</p>";

ob_start();
try {
    include 'users_complete.php';
    $output = ob_get_clean();
    
    if (strpos($output, 'مدیریت کاربران') !== false) {
        echo "<p class='success'>✅ users_complete.php با موفقیت بارگذاری شد</p>";
    } else {
        echo "<p class='error'>❌ users_complete.php بارگذاری نشد یا محتوا ناقص است</p>";
        echo "<pre>" . htmlspecialchars(substr($output, 0, 500)) . "...</pre>";
    }
} catch (Exception $e) {
    ob_end_clean();
    echo "<p class='error'>❌ خطا در بارگذاری users_complete.php: " . $e->getMessage() . "</p>";
}
echo "</div>";

echo "<div class='test-section'>";
echo "<h2>2️⃣ تست API دریافت دسترسی‌ها</h2>";
echo "<p>تست get_user_permissions.php...</p>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://localhost/Aala_Niroo_AMS/get_user_permissions.php?user_id=1");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code == 200 && strpos($response, 'permissions') !== false) {
    echo "<p class='success'>✅ get_user_permissions.php کار می‌کند</p>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
} else {
    echo "<p class='error'>❌ get_user_permissions.php کار نمی‌کند. HTTP Code: $http_code</p>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
}
echo "</div>";

echo "<div class='test-section'>";
echo "<h2>3️⃣ تست API دریافت رمز عبور</h2>";
echo "<p>تست get_user_password.php...</p>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://localhost/Aala_Niroo_AMS/get_user_password.php?user_id=1");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code == 200 && strpos($response, 'password') !== false) {
    echo "<p class='success'>✅ get_user_password.php کار می‌کند</p>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
} else {
    echo "<p class='error'>❌ get_user_password.php کار نمی‌کند. HTTP Code: $http_code</p>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
}
echo "</div>";

echo "<div class='test-section'>";
echo "<h2>4️⃣ تست اتصال دیتابیس</h2>";
echo "<p>تست اتصال به دیتابیس...</p>";

try {
    require_once 'config.php';
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    echo "<p class='success'>✅ اتصال دیتابیس موفق - تعداد کاربران: " . $result['count'] . "</p>";
} catch (Exception $e) {
    echo "<p class='error'>❌ خطا در اتصال دیتابیس: " . $e->getMessage() . "</p>";
}
echo "</div>";

echo "<div class='test-section'>";
echo "<h2>5️⃣ تست جداول مورد نیاز</h2>";
echo "<p>بررسی وجود جداول users و custom_roles...</p>";

try {
    // تست جدول users
    $stmt = $pdo->query("DESCRIBE users");
    $users_columns = $stmt->fetchAll();
    echo "<p class='success'>✅ جدول users موجود است (" . count($users_columns) . " ستون)</p>";
    
    // تست جدول custom_roles
    $stmt = $pdo->query("DESCRIBE custom_roles");
    $custom_roles_columns = $stmt->fetchAll();
    echo "<p class='success'>✅ جدول custom_roles موجود است (" . count($custom_roles_columns) . " ستون)</p>";
    
} catch (Exception $e) {
    echo "<p class='error'>❌ خطا در بررسی جداول: " . $e->getMessage() . "</p>";
}
echo "</div>";

echo "<div class='test-section'>";
echo "<h2>6️⃣ تست توابع مورد نیاز</h2>";
echo "<p>بررسی وجود توابع ضروری...</p>";

$required_functions = [
    'hasPermission',
    'verifyCsrfToken',
    'csrf_field',
    'sanitizeInput',
    'jalali_format'
];

foreach ($required_functions as $func) {
    if (function_exists($func)) {
        echo "<p class='success'>✅ تابع $func موجود است</p>";
    } else {
        echo "<p class='error'>❌ تابع $func موجود نیست</p>";
    }
}
echo "</div>";

echo "<div class='test-section'>";
echo "<h2>7️⃣ تست ایجاد کاربر نمونه</h2>";
echo "<p>ایجاد کاربر تست...</p>";

try {
    // بررسی وجود کاربر تست
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute(['test_user']);
    $existing_user = $stmt->fetch();
    
    if ($existing_user) {
        echo "<p class='info'>ℹ️ کاربر تست قبلاً وجود دارد</p>";
    } else {
        // ایجاد کاربر تست
        $hashed_password = password_hash('test123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['test_user', $hashed_password, 'کاربر تست', 'test@example.com', 'کاربر عادی']);
        echo "<p class='success'>✅ کاربر تست با موفقیت ایجاد شد</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ خطا در ایجاد کاربر تست: " . $e->getMessage() . "</p>";
}
echo "</div>";

echo "<div class='test-section'>";
echo "<h2>8️⃣ تست دسترسی‌های سفارشی</h2>";
echo "<p>تست ایجاد دسترسی سفارشی...</p>";

try {
    // دریافت ID کاربر تست
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute(['test_user']);
    $test_user = $stmt->fetch();
    
    if ($test_user) {
        $user_id = $test_user['id'];
        
        // ایجاد دسترسی سفارشی
        $permissions = ['users.view', 'customers.view', 'dashboard.view'];
        $stmt = $pdo->prepare("INSERT INTO custom_roles (user_id, role_name, permissions) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE permissions = ?");
        $stmt->execute([$user_id, 'custom_test', json_encode($permissions), json_encode($permissions)]);
        
        // تست دریافت دسترسی‌ها
        $stmt = $pdo->prepare("SELECT permissions FROM custom_roles WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $role_data = $stmt->fetch();
        
        if ($role_data && $role_data['permissions']) {
            $saved_permissions = json_decode($role_data['permissions'], true);
            echo "<p class='success'>✅ دسترسی‌های سفارشی با موفقیت ذخیره و بازیابی شد</p>";
            echo "<pre>" . print_r($saved_permissions, true) . "</pre>";
        } else {
            echo "<p class='error'>❌ خطا در ذخیره یا بازیابی دسترسی‌های سفارشی</p>";
        }
    } else {
        echo "<p class='error'>❌ کاربر تست یافت نشد</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ خطا در تست دسترسی‌های سفارشی: " . $e->getMessage() . "</p>";
}
echo "</div>";

echo "<div class='test-section'>";
echo "<h2>9️⃣ تست رابط کاربری</h2>";
echo "<p>بررسی عناصر رابط کاربری...</p>";

$ui_elements = [
    'فرم ایجاد کاربر' => 'createUserForm',
    'جدول لیست کاربران' => 'table',
    'Modal ویرایش' => 'editUserModal',
    'Modal دسترسی‌ها' => 'permissionsModal',
    'دکمه ایجاد کاربر' => 'btn-primary'
];

foreach ($ui_elements as $name => $element) {
    if (strpos($output, $element) !== false) {
        echo "<p class='success'>✅ $name موجود است</p>";
    } else {
        echo "<p class='error'>❌ $name یافت نشد</p>";
    }
}
echo "</div>";

echo "<div class='test-section'>";
echo "<h2>🔟 خلاصه نتایج</h2>";
echo "<p class='info'>تست کامل سیستم مدیریت کاربران انجام شد.</p>";
echo "<p class='info'>اگر همه تست‌ها موفق بودند، سیستم آماده استفاده است.</p>";
echo "<p class='info'>در صورت وجود خطا، لطفاً فایل‌های مربوطه را بررسی کنید.</p>";
echo "</div>";

echo "<div style='text-align: center; margin: 20px 0;'>";
echo "<a href='users_complete.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🚀 بازگشت به مدیریت کاربران</a>";
echo "</div>";
?>