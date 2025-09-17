<?php
// test_survey_simple.php - تست ساده survey.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html lang='fa' dir='rtl'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>تست ساده Survey</title>
    <style>
        body { font-family: Tahoma, Arial, sans-serif; padding: 20px; background: #f8f9fa; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #27ae60; background: #d5f4e6; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: #e74c3c; background: #fadbd8; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { color: #3498db; background: #d6eaf8; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .btn { background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px; }
        h1 { text-align: center; color: #2c3e50; }
        .code { background: #f8f9fa; padding: 10px; border-radius: 5px; font-family: monospace; margin: 10px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🧪 تست ساده Survey</h1>";

// تست اتصال به دیتابیس
echo "<h3>1. تست اتصال به دیتابیس</h3>";
try {
    require_once 'config.php';
    echo "<div class='success'>✅ اتصال به دیتابیس موفق</div>";
} catch (Exception $e) {
    echo "<div class='error'>❌ خطا در اتصال به دیتابیس: " . $e->getMessage() . "</div>";
    exit;
}

// تست جداول
echo "<h3>2. تست جداول</h3>";
$tables = ['surveys', 'survey_questions', 'survey_submissions', 'survey_responses', 'customers', 'assets'];
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
        $count = $stmt->fetch()['count'];
        echo "<div class='info'>📊 جدول $table: $count رکورد</div>";
    } catch (Exception $e) {
        echo "<div class='error'>❌ خطا در جدول $table: " . $e->getMessage() . "</div>";
    }
}

// تست نظرسنجی‌ها
echo "<h3>3. تست نظرسنجی‌ها</h3>";
try {
    $stmt = $pdo->query("SELECT * FROM surveys WHERE is_active = 1 ORDER BY id DESC");
    $surveys = $stmt->fetchAll();
    
    if (empty($surveys)) {
        echo "<div class='error'>❌ هیچ نظرسنجی فعالی یافت نشد</div>";
    } else {
        echo "<div class='success'>✅ " . count($surveys) . " نظرسنجی فعال یافت شد</div>";
        foreach ($surveys as $survey) {
            echo "<div class='info'>📝 " . htmlspecialchars($survey['title']) . " (ID: " . $survey['id'] . ")</div>";
        }
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ خطا در دریافت نظرسنجی‌ها: " . $e->getMessage() . "</div>";
}

// تست مشتریان
echo "<h3>4. تست مشتریان</h3>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM customers");
    $customer_count = $stmt->fetch()['count'];
    echo "<div class='info'>👥 تعداد مشتریان: $customer_count</div>";
} catch (Exception $e) {
    echo "<div class='error'>❌ خطا در دریافت مشتریان: " . $e->getMessage() . "</div>";
}

// تست دارایی‌ها
echo "<h3>5. تست دارایی‌ها</h3>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM assets");
    $asset_count = $stmt->fetch()['count'];
    echo "<div class='info'>🏭 تعداد دارایی‌ها: $asset_count</div>";
} catch (Exception $e) {
    echo "<div class='error'>❌ خطا در دریافت دارایی‌ها: " . $e->getMessage() . "</div>";
}

// تست فایل survey.php
echo "<h3>6. تست فایل survey.php</h3>";
if (file_exists('survey.php')) {
    echo "<div class='success'>✅ فایل survey.php موجود است</div>";
    
    // تست syntax
    $syntax_check = shell_exec('php -l survey.php 2>&1');
    if (strpos($syntax_check, 'No syntax errors') !== false) {
        echo "<div class='success'>✅ Syntax فایل survey.php صحیح است</div>";
    } else {
        echo "<div class='error'>❌ خطای Syntax در survey.php:</div>";
        echo "<div class='code'>$syntax_check</div>";
    }
} else {
    echo "<div class='error'>❌ فایل survey.php یافت نشد</div>";
}

// لینک‌های تست
echo "<h3>7. لینک‌های تست</h3>";
echo "<a href='survey.php' class='btn'>📝 صفحه نظرسنجی</a>";
echo "<a href='survey.php?survey_id=1' class='btn'>📝 نظرسنجی ID=1</a>";
echo "<a href='survey.php?survey_id=2' class='btn'>📝 نظرسنجی ID=2</a>";
echo "<a href='survey_list.php' class='btn'>📋 لیست نظرسنجی‌ها</a>";

echo "
        <div style='text-align: center; margin-top: 30px;'>
            <a href='survey.php' class='btn' style='background: #27ae60; font-size: 18px; padding: 15px 30px;'>
                🚀 شروع نظرسنجی
            </a>
        </div>
    </div>
</body>
</html>";
?>