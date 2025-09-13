<?php
// اسکریپت ساده برای رفع مشکل دیتابیس
include 'config.php';

try {
    // اضافه کردن فیلد email
    $pdo->exec("ALTER TABLE customers ADD COLUMN email VARCHAR(255) DEFAULT '' AFTER phone");
    echo "✅ فیلد email اضافه شد\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "ℹ️ فیلد email قبلاً موجود است\n";
    } else {
        echo "❌ خطا در اضافه کردن email: " . $e->getMessage() . "\n";
    }
}

try {
    // اضافه کردن فیلد company_email
    $pdo->exec("ALTER TABLE customers ADD COLUMN company_email VARCHAR(255) DEFAULT '' AFTER responsible_phone");
    echo "✅ فیلد company_email اضافه شد\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "ℹ️ فیلد company_email قبلاً موجود است\n";
    } else {
        echo "❌ خطا در اضافه کردن company_email: " . $e->getMessage() . "\n";
    }
}

try {
    // اضافه کردن فیلد notification_type
    $pdo->exec("ALTER TABLE customers ADD COLUMN notification_type ENUM('none','email','sms','both') DEFAULT 'none' AFTER notes");
    echo "✅ فیلد notification_type اضافه شد\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "ℹ️ فیلد notification_type قبلاً موجود است\n";
    } else {
        echo "❌ خطا در اضافه کردن notification_type: " . $e->getMessage() . "\n";
    }
}

try {
    // ایجاد جدول notification_templates
    $pdo->exec("CREATE TABLE IF NOT EXISTS notification_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type ENUM('email','sms') NOT NULL,
        name VARCHAR(255) NOT NULL,
        subject VARCHAR(500) DEFAULT '',
        content TEXT NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ جدول notification_templates ایجاد شد\n";
} catch (Exception $e) {
    echo "❌ خطا در ایجاد جدول notification_templates: " . $e->getMessage() . "\n";
}

try {
    // درج قالب‌های پیش‌فرض
    $pdo->exec("INSERT IGNORE INTO notification_templates (type, name, subject, content) VALUES 
        ('email', 'خوش‌آمدگویی مشتری حقیقی', 'خوش‌آمدید به سیستم مدیریت اعلا نیرو', 'سلام {full_name} عزیز،\n\nبه سیستم مدیریت اعلا نیرو خوش‌آمدید!\n\nاطلاعات شما:\nنام: {full_name}\nتلفن: {phone}\nآدرس: {address}\n\nبا تشکر\nتیم اعلا نیرو'),
        ('email', 'خوش‌آمدگویی مشتری حقوقی', 'خوش‌آمدید به سیستم مدیریت اعلا نیرو', 'سلام {responsible_name} عزیز،\n\nشرکت {company} به سیستم مدیریت اعلا نیرو خوش‌آمدید!\n\nاطلاعات شرکت:\nنام شرکت: {company}\nمسئول: {responsible_name}\nتلفن شرکت: {company_phone}\nآدرس: {address}\n\nبا تشکر\nتیم اعلا نیرو'),
        ('sms', 'خوش‌آمدگویی SMS', '', 'سلام {full_name} عزیز، به سیستم مدیریت اعلا نیرو خوش‌آمدید! تیم اعلا نیرو')
    ");
    echo "✅ قالب‌های پیش‌فرض اضافه شدند\n";
} catch (Exception $e) {
    echo "❌ خطا در اضافه کردن قالب‌های پیش‌فرض: " . $e->getMessage() . "\n";
}

echo "\n🎉 به‌روزرسانی دیتابیس تکمیل شد!\n";
echo "حالا می‌توانید به صفحه customers.php بروید.\n";
?>