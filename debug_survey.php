<?php
// debug_survey.php - دیباگ survey.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug_survey.log');

echo "<!DOCTYPE html>
<html lang='fa' dir='rtl'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>دیباگ Survey</title>
    <style>
        body { font-family: Tahoma, Arial, sans-serif; padding: 20px; background: #f8f9fa; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #27ae60; background: #d5f4e6; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: #e74c3c; background: #fadbd8; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { color: #3498db; background: #d6eaf8; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .warning { color: #f39c12; background: #fef9e7; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .code { background: #f8f9fa; padding: 10px; border-radius: 5px; font-family: monospace; margin: 10px 0; white-space: pre-wrap; }
        h1 { text-align: center; color: #2c3e50; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🐛 دیباگ Survey</h1>";

// شروع session
session_start();

// تست مرحله به مرحله
echo "<h3>مرحله 1: تست config.php</h3>";
try {
    require_once 'config.php';
    echo "<div class='success'>✅ config.php بارگذاری شد</div>";
    echo "<div class='info'>📊 PDO: " . (isset($pdo) ? 'موجود' : 'ناموجود') . "</div>";
} catch (Exception $e) {
    echo "<div class='error'>❌ خطا در config.php: " . $e->getMessage() . "</div>";
    echo "<div class='code'>" . $e->getTraceAsString() . "</div>";
    exit;
}

echo "<h3>مرحله 2: تست جداول</h3>";
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS surveys (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        is_active BOOLEAN DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");
    echo "<div class='success'>✅ جدول surveys ایجاد شد</div>";
} catch (Exception $e) {
    echo "<div class='error'>❌ خطا در ایجاد جدول surveys: " . $e->getMessage() . "</div>";
}

echo "<h3>مرحله 3: تست دریافت نظرسنجی‌ها</h3>";
try {
    $stmt = $pdo->query("SELECT * FROM surveys WHERE is_active = 1 ORDER BY id DESC");
    $surveys = $stmt->fetchAll();
    echo "<div class='info'>📊 تعداد نظرسنجی‌های فعال: " . count($surveys) . "</div>";
    
    if (empty($surveys)) {
        echo "<div class='warning'>⚠️ هیچ نظرسنجی فعالی یافت نشد - ایجاد نظرسنجی نمونه</div>";
        
        // ایجاد نظرسنجی نمونه
        $stmt = $pdo->prepare("INSERT INTO surveys (title, description, is_active) VALUES (?, ?, ?)");
        $stmt->execute([
            'نظرسنجی تست - شرکت اعلا نیرو',
            'این نظرسنجی برای تست سیستم ایجاد شده است.',
            1
        ]);
        $survey_id = $pdo->lastInsertId();
        echo "<div class='success'>✅ نظرسنجی نمونه ایجاد شد (ID: $survey_id)</div>";
        
        // ایجاد سوالات نمونه
        $sample_questions = [
            ['آیا از خدمات راضی هستید؟', 'yes_no', 1, 1],
            ['نحوه برخورد کارکنان را چگونه ارزیابی می‌کنید؟', 'rating', 1, 2],
            ['نظرات خود را بیان کنید:', 'textarea', 0, 3]
        ];
        
        foreach ($sample_questions as $q) {
            $stmt = $pdo->prepare("INSERT INTO survey_questions (survey_id, question_text, question_type, is_required, order_index) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$survey_id, $q[0], $q[1], $q[2], $q[3]]);
        }
        echo "<div class='success'>✅ سوالات نمونه ایجاد شد</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ خطا در دریافت نظرسنجی‌ها: " . $e->getMessage() . "</div>";
}

echo "<h3>مرحله 4: تست دریافت مشتریان</h3>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM customers");
    $customer_count = $stmt->fetch()['count'];
    echo "<div class='info'>👥 تعداد مشتریان: $customer_count</div>";
    
    if ($customer_count == 0) {
        echo "<div class='warning'>⚠️ هیچ مشتری ثبت نشده است</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ خطا در دریافت مشتریان: " . $e->getMessage() . "</div>";
}

echo "<h3>مرحله 5: تست دریافت دارایی‌ها</h3>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM assets");
    $asset_count = $stmt->fetch()['count'];
    echo "<div class='info'>🏭 تعداد دارایی‌ها: $asset_count</div>";
} catch (Exception $e) {
    echo "<div class='error'>❌ خطا در دریافت دارایی‌ها: " . $e->getMessage() . "</div>";
}

echo "<h3>مرحله 6: تست توابع</h3>";
$functions = ['verifyCsrfToken', 'csrf_field', 'logAction', 'jalali_format'];
foreach ($functions as $func) {
    if (function_exists($func)) {
        echo "<div class='success'>✅ تابع $func موجود است</div>";
    } else {
        echo "<div class='error'>❌ تابع $func یافت نشد</div>";
    }
}

echo "<h3>مرحله 7: تست session</h3>";
echo "<div class='info'>🔐 Session ID: " . session_id() . "</div>";
echo "<div class='info'>👤 User ID: " . ($_SESSION['user_id'] ?? 'نامشخص') . "</div>";
echo "<div class='info'>🔑 CSRF Token: " . ($_SESSION['csrf_token'] ?? 'نامشخص') . "</div>";

echo "<h3>مرحله 8: تست فایل survey.php</h3>";
if (file_exists('survey.php')) {
    echo "<div class='success'>✅ فایل survey.php موجود است</div>";
    
    // تست syntax
    $output = [];
    $return_var = 0;
    exec('php -l survey.php 2>&1', $output, $return_var);
    
    if ($return_var === 0) {
        echo "<div class='success'>✅ Syntax فایل survey.php صحیح است</div>";
    } else {
        echo "<div class='error'>❌ خطای Syntax در survey.php:</div>";
        echo "<div class='code'>" . implode("\n", $output) . "</div>";
    }
} else {
    echo "<div class='error'>❌ فایل survey.php یافت نشد</div>";
}

echo "<h3>مرحله 9: تست اجرای survey.php</h3>";
echo "<div class='info'>🔗 لینک‌های تست:</div>";
echo "<a href='survey.php' target='_blank' style='display: block; margin: 10px 0; padding: 10px; background: #3498db; color: white; text-decoration: none; border-radius: 5px;'>📝 survey.php</a>";
echo "<a href='survey.php?survey_id=1' target='_blank' style='display: block; margin: 10px 0; padding: 10px; background: #27ae60; color: white; text-decoration: none; border-radius: 5px;'>📝 survey.php?survey_id=1</a>";
echo "<a href='survey.php?survey_id=2' target='_blank' style='display: block; margin: 10px 0; padding: 10px; background: #e74c3c; color: white; text-decoration: none; border-radius: 5px;'>📝 survey.php?survey_id=2</a>";

echo "<h3>مرحله 10: لاگ خطاها</h3>";
if (file_exists('debug_survey.log')) {
    $log_content = file_get_contents('debug_survey.log');
    if (!empty($log_content)) {
        echo "<div class='code'>" . htmlspecialchars($log_content) . "</div>";
    } else {
        echo "<div class='info'>📝 لاگ خالی است</div>";
    }
} else {
    echo "<div class='info'>📝 فایل لاگ وجود ندارد</div>";
}

echo "
        <div style='text-align: center; margin-top: 30px;'>
            <a href='survey.php' style='background: #27ae60; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-size: 18px;'>
                🚀 تست Survey
            </a>
        </div>
    </div>
</body>
</html>";
?>