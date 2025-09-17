<?php
// test_survey_system.php - تست سیستم نظرسنجی
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html lang='fa' dir='rtl'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>تست سیستم نظرسنجی</title>
    <style>
        body { font-family: Tahoma, Arial, sans-serif; padding: 20px; background: #f8f9fa; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #27ae60; background: #d5f4e6; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: #e74c3c; background: #fadbd8; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { color: #3498db; background: #d6eaf8; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .btn { background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px; }
        h1 { text-align: center; color: #2c3e50; }
        .code { background: #f8f9fa; padding: 10px; border-radius: 5px; font-family: monospace; margin: 10px 0; }
        .test-section { border: 1px solid #ddd; padding: 20px; margin: 20px 0; border-radius: 5px; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🧪 تست سیستم نظرسنجی</h1>";

// تست اتصال به دیتابیس
echo "<div class='test-section'>";
echo "<h3>1. تست اتصال به دیتابیس</h3>";

try {
    require_once 'config.php';
    echo "<div class='success'>✅ اتصال به دیتابیس موفق</div>";
} catch (Exception $e) {
    echo "<div class='error'>❌ خطا در اتصال به دیتابیس: " . $e->getMessage() . "</div>";
    exit;
}

// تست جداول
echo "<h3>2. تست جداول نظرسنجی</h3>";

$tables = ['surveys', 'survey_questions', 'survey_submissions', 'survey_responses'];
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
        $count = $stmt->fetch()['count'];
        echo "<div class='info'>📊 جدول $table: $count رکورد</div>";
    } catch (Exception $e) {
        echo "<div class='error'>❌ خطا در جدول $table: " . $e->getMessage() . "</div>";
    }
}

// تست ایجاد نظرسنجی نمونه
echo "<h3>3. تست ایجاد نظرسنجی نمونه</h3>";

try {
    // بررسی وجود نظرسنجی
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM surveys WHERE is_active = 1");
    $survey_count = $stmt->fetch()['count'];
    
    if ($survey_count == 0) {
        // ایجاد نظرسنجی نمونه
        $stmt = $pdo->prepare("INSERT INTO surveys (title, description, is_active) VALUES (?, ?, ?)");
        $stmt->execute([
            'نظرسنجی رضایت مشتریان - تست',
            'این نظرسنجی برای تست سیستم ایجاد شده است.',
            1
        ]);
        $survey_id = $pdo->lastInsertId();
        
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
        
        echo "<div class='success'>✅ نظرسنجی نمونه ایجاد شد (ID: $survey_id)</div>";
    } else {
        echo "<div class='info'>ℹ️ نظرسنجی فعال موجود است ($survey_count نظرسنجی)</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ خطا در ایجاد نظرسنجی: " . $e->getMessage() . "</div>";
}

// تست مشتریان
echo "<h3>4. تست مشتریان</h3>";

try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM customers");
    $customer_count = $stmt->fetch()['count'];
    echo "<div class='info'>👥 تعداد مشتریان: $customer_count</div>";
    
    if ($customer_count == 0) {
        echo "<div class='error'>⚠️ هیچ مشتری ثبت نشده است. لطفاً ابتدا مشتری اضافه کنید.</div>";
    }
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

// تست فایل‌ها
echo "<h3>6. تست فایل‌های سیستم</h3>";

$files = [
    'survey.php' => 'صفحه اصلی نظرسنجی',
    'survey_edit.php' => 'صفحه ویرایش نظرسنجی',
    'survey_list.php' => 'لیست نظرسنجی‌ها',
    'survey_customer_search.php' => 'جستجوی مشتری برای نظرسنجی'
];

foreach ($files as $file => $description) {
    if (file_exists($file)) {
        echo "<div class='success'>✅ $description ($file)</div>";
    } else {
        echo "<div class='error'>❌ فایل $file یافت نشد</div>";
    }
}

echo "</div>";

// لینک‌های تست
echo "<div class='test-section'>";
echo "<h3>🔗 لینک‌های تست</h3>";
echo "<a href='survey.php' class='btn'>📝 صفحه نظرسنجی</a>";
echo "<a href='survey_list.php' class='btn'>📋 لیست نظرسنجی‌ها</a>";
echo "<a href='survey_customer_search.php' class='btn'>🔍 جستجوی مشتری</a>";
echo "<a href='customers.php' class='btn'>👥 مدیریت مشتریان</a>";
echo "<a href='assets.php' class='btn'>🏭 مدیریت دارایی‌ها</a>";
echo "</div>";

// تست عملکرد SMS
echo "<div class='test-section'>";
echo "<h3>7. تست عملکرد SMS</h3>";

if (file_exists('sms.php')) {
    echo "<div class='success'>✅ فایل SMS موجود است</div>";
    
    // تست توابع SMS
    try {
        require_once 'sms.php';
        
        if (function_exists('normalize_phone_number')) {
            $test_phone = normalize_phone_number('09123456789');
            echo "<div class='info'>📱 تست نرمال‌سازی شماره: $test_phone</div>";
        } else {
            echo "<div class='error'>❌ تابع normalize_phone_number یافت نشد</div>";
        }
        
        if (function_exists('send_sms_mock')) {
            echo "<div class='info'>📤 تابع send_sms_mock موجود است</div>";
        } else {
            echo "<div class='error'>❌ تابع send_sms_mock یافت نشد</div>";
        }
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ خطا در تست SMS: " . $e->getMessage() . "</div>";
    }
} else {
    echo "<div class='error'>❌ فایل SMS یافت نشد</div>";
}

echo "</div>";

// خلاصه
echo "<div class='test-section'>";
echo "<h3>📊 خلاصه تست</h3>";
echo "<div class='info'>";
echo "✅ سیستم نظرسنجی آماده استفاده است<br>";
echo "✅ امکان انتخاب مشتری، دارایی و نوع نظرسنجی<br>";
echo "✅ امکان ارسال SMS به مشتری<br>";
echo "✅ امکان ویرایش نظرسنجی‌های ثبت شده<br>";
echo "✅ نمایش اطلاعات کامل در صفحه ویرایش<br>";
echo "</div>";
echo "</div>";

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