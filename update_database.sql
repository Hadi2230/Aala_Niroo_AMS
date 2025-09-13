-- به‌روزرسانی جدول customers برای اضافه کردن فیلدهای ایمیل و اطلاع‌رسانی

-- اضافه کردن فیلد email برای مشتریان حقیقی
ALTER TABLE customers ADD COLUMN email VARCHAR(255) DEFAULT '' AFTER phone;

-- اضافه کردن فیلد company_email برای مشتریان حقوقی
ALTER TABLE customers ADD COLUMN company_email VARCHAR(255) DEFAULT '' AFTER responsible_phone;

-- اضافه کردن فیلد notification_type برای انتخاب نوع اطلاع‌رسانی
ALTER TABLE customers ADD COLUMN notification_type ENUM('none','email','sms','both') DEFAULT 'none' AFTER notes;

-- ایجاد جدول notification_templates
CREATE TABLE IF NOT EXISTS notification_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('email','sms') NOT NULL,
    name VARCHAR(255) NOT NULL,
    subject VARCHAR(500) DEFAULT '',
    content TEXT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- درج قالب‌های پیش‌فرض
INSERT IGNORE INTO notification_templates (type, name, subject, content) VALUES 
('email', 'خوش‌آمدگویی مشتری حقیقی', 'خوش‌آمدید به سیستم مدیریت اعلا نیرو', 'سلام {full_name} عزیز،\n\nبه سیستم مدیریت اعلا نیرو خوش‌آمدید!\n\nاطلاعات شما:\nنام: {full_name}\nتلفن: {phone}\nآدرس: {address}\n\nبا تشکر\nتیم اعلا نیرو'),
('email', 'خوش‌آمدگویی مشتری حقوقی', 'خوش‌آمدید به سیستم مدیریت اعلا نیرو', 'سلام {responsible_name} عزیز،\n\nشرکت {company} به سیستم مدیریت اعلا نیرو خوش‌آمدید!\n\nاطلاعات شرکت:\nنام شرکت: {company}\nمسئول: {responsible_name}\nتلفن شرکت: {company_phone}\nآدرس: {address}\n\nبا تشکر\nتیم اعلا نیرو'),
('sms', 'خوش‌آمدگویی SMS', '', 'سلام {full_name} عزیز، به سیستم مدیریت اعلا نیرو خوش‌آمدید! تیم اعلا نیرو');