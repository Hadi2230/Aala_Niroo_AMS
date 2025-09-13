# 🔧 راهنمای رفع مشکل دیتابیس

## ❌ مشکل:
```
خطای دیتابیس: SQLSTATE[42S22]: Column not found: 1054 Unknown column 'email' in 'field list'
```

## ✅ راه حل:

### روش 1: اجرای اسکریپت PHP
1. به آدرس زیر بروید:
   ```
   http://localhost/Aala_Niroo_AMS/fix_database.php
   ```

### روش 2: اجرای دستی SQL
1. به phpMyAdmin بروید
2. دیتابیس خود را انتخاب کنید
3. SQL زیر را اجرا کنید:

```sql
-- اضافه کردن فیلد email
ALTER TABLE customers ADD COLUMN email VARCHAR(255) DEFAULT '' AFTER phone;

-- اضافه کردن فیلد company_email
ALTER TABLE customers ADD COLUMN company_email VARCHAR(255) DEFAULT '' AFTER responsible_phone;

-- اضافه کردن فیلد notification_type
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
```

## 🎯 بعد از رفع مشکل:

1. **صفحه مشتریان:** `http://localhost/Aala_Niroo_AMS/customers.php`
2. **صفحه مدیریت قالب‌ها:** `http://localhost/Aala_Niroo_AMS/notification_templates.php`

## ✅ بررسی موفقیت:

- صفحه مشتریان بدون خطا باز می‌شود
- می‌توانید مشتری جدید با ایمیل اضافه کنید
- می‌توانید نوع اطلاع‌رسانی انتخاب کنید
- صفحه مدیریت قالب‌ها کار می‌کند

## 🆘 اگر هنوز مشکل دارید:

1. بررسی کنید که فایل `config.php` درست تنظیم شده باشد
2. بررسی کنید که دیتابیس در دسترس باشد
3. بررسی کنید که کاربر دیتابیس دسترسی CREATE و ALTER دارد