# 🚀 سیستم مدیریت کاربران کامل - شرکت اعلا نیرو

## 📋 ویژگی‌های کلیدی

### ✅ مدیریت کامل کاربران
- **ایجاد کاربر جدید** با رمز عبور امن
- **ویرایش اطلاعات کاربر** شامل تغییر رمز عبور
- **حذف کاربر** با تأیید امنیتی
- **تغییر وضعیت** (فعال/غیرفعال)
- **مدیریت دسترسی‌های سفارشی**

### 🔐 سیستم دسترسی پیشرفته
- **نقش‌های استاندارد**: ادمین، مدیر عملیات، تکنسین، اپراتور، پشتیبانی، کاربر عادی
- **نقش سفارشی** با دسترسی‌های انتخابی
- **دسترسی‌های جزئی** برای هر بخش سیستم
- **مدیریت دسترسی‌های زنده** بدون نیاز به بازنشانی صفحه

### 🎨 رابط کاربری حرفه‌ای
- **طراحی ریسپانسیو** با Bootstrap 5
- **Modal های تعاملی** برای ویرایش و مدیریت دسترسی‌ها
- **نمایش زنده تعداد دسترسی‌های انتخاب شده**
- **انیمیشن‌های نرم** و تجربه کاربری عالی
- **پشتیبانی کامل از زبان فارسی**

## 📁 فایل‌های سیستم

### فایل‌های اصلی
- `users_complete.php` - صفحه اصلی مدیریت کاربران
- `get_user_permissions.php` - API دریافت دسترسی‌های کاربر
- `get_user_password.php` - API دریافت رمز عبور کاربر
- `test_users_complete.php` - تست کامل سیستم

### فایل‌های پشتیبان
- `users_fixed.php` - نسخه قبلی (پشتیبان)
- `test_users_system.php` - تست نسخه قبلی

## 🗄️ ساختار دیتابیس

### جدول `users`
```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(255),
    email VARCHAR(255) UNIQUE,
    role ENUM('ادمین', 'کاربر عادی', 'اپراتور', 'مدیر عملیات', 'تکنسین', 'پشتیبانی', 'سفارشی'),
    is_active BOOLEAN DEFAULT true,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### جدول `custom_roles`
```sql
CREATE TABLE custom_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    role_name VARCHAR(100) NOT NULL,
    permissions JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

## 🔧 نصب و راه‌اندازی

### 1. کپی فایل‌ها
```bash
# کپی فایل‌های اصلی
cp users_complete.php /path/to/your/project/
cp get_user_permissions.php /path/to/your/project/
cp get_user_password.php /path/to/your/project/
```

### 2. بررسی وابستگی‌ها
- `config.php` - فایل پیکربندی اصلی
- `navbar.php` - نوار ناوبری (اختیاری)
- PHP 7.4+ با PDO MySQL
- Bootstrap 5 و Font Awesome 6

### 3. تست سیستم
```bash
# اجرای تست کامل
php test_users_complete.php
```

## 📖 راهنمای استفاده

### ایجاد کاربر جدید
1. **پر کردن فرم** با اطلاعات کاربر
2. **انتخاب نقش** از لیست موجود
3. **تعریف دسترسی‌های سفارشی** (در صورت انتخاب نقش سفارشی)
4. **تأیید و ذخیره**

### ویرایش کاربر
1. **کلیک روی دکمه ویرایش** در جدول کاربران
2. **تغییر اطلاعات** مورد نیاز
3. **تغییر رمز عبور** (اختیاری)
4. **ذخیره تغییرات**

### مدیریت دسترسی‌ها
1. **کلیک روی آیکون دسترسی** در جدول کاربران
2. **انتخاب دسترسی‌های مورد نظر** از بخش‌های مختلف
3. **ذخیره دسترسی‌ها**

## 🎯 دسترسی‌های موجود

### داشبورد
- `dashboard.view` - مشاهده داشبورد
- `dashboard.stats` - مشاهده آمار

### مدیریت کاربران
- `users.view` - مشاهده کاربران
- `users.create` - ایجاد کاربر
- `users.edit` - ویرایش کاربر
- `users.delete` - حذف کاربر
- `users.permissions` - مدیریت دسترسی‌ها

### مدیریت مشتریان
- `customers.view` - مشاهده مشتریان
- `customers.create` - ایجاد مشتری
- `customers.edit` - ویرایش مشتری
- `customers.delete` - حذف مشتری
- `customers.export` - خروجی مشتریان

### مدیریت دارایی‌ها
- `assets.view` - مشاهده دارایی‌ها
- `assets.create` - ایجاد دارایی
- `assets.edit` - ویرایش دارایی
- `assets.delete` - حذف دارایی
- `assets.assign` - تخصیص دارایی
- `assets.maintenance` - تعمیرات دارایی

### مدیریت تیکت‌ها
- `tickets.view` - مشاهده تیکت‌ها
- `tickets.create` - ایجاد تیکت
- `tickets.edit` - ویرایش تیکت
- `tickets.assign` - تخصیص تیکت
- `tickets.resolve` - حل تیکت
- `tickets.close` - بستن تیکت

### مدیریت تعمیرات
- `maintenance.view` - مشاهده تعمیرات
- `maintenance.create` - ایجاد تعمیرات
- `maintenance.edit` - ویرایش تعمیرات
- `maintenance.assign` - تخصیص تعمیرات
- `maintenance.complete` - تکمیل تعمیرات
- `maintenance.schedule` - برنامه‌ریزی تعمیرات

### گزارش‌ها و آمار
- `reports.view` - مشاهده گزارش‌ها
- `reports.create` - ایجاد گزارش
- `reports.export` - خروجی گزارش‌ها
- `reports.analytics` - تحلیل آمار

### پیام‌ها و اطلاع‌رسانی
- `messages.view` - مشاهده پیام‌ها
- `messages.send` - ارسال پیام
- `messages.receive` - دریافت پیام
- `messages.broadcast` - ارسال همگانی

### تنظیمات سیستم
- `settings.view` - مشاهده تنظیمات
- `settings.edit` - ویرایش تنظیمات
- `settings.backup` - پشتیبان‌گیری
- `settings.logs` - مشاهده لاگ‌ها

### مدیریت بازدید از کارخانه
- `visit.view` - مشاهده بازدیدها
- `visit.create` - ایجاد درخواست بازدید
- `visit.edit` - ویرایش بازدید
- `visit.schedule` - برنامه‌ریزی بازدید
- `visit.checkin` - ورود بازدیدکنندگان
- `visit.reports` - گزارش بازدیدها

## 🔒 امنیت

### ویژگی‌های امنیتی
- **رمزگذاری رمز عبور** با `password_hash()`
- **محافظت CSRF** با توکن‌های امنیتی
- **اعتبارسنجی ورودی** با `sanitizeInput()`
- **بررسی دسترسی** با `hasPermission()`
- **جلوگیری از حذف خود** توسط کاربر

### نکات امنیتی
- همیشه از HTTPS استفاده کنید
- رمزهای عبور قوی تعریف کنید
- دسترسی‌ها را به صورت منظم بررسی کنید
- لاگ‌های سیستم را مانیتور کنید

## 🐛 عیب‌یابی

### مشکلات رایج
1. **خطای 500**: بررسی `config.php` و جداول دیتابیس
2. **دسترسی غیرمجاز**: بررسی `hasPermission()` در `config.php`
3. **Modal کار نمی‌کند**: بررسی Bootstrap و JavaScript
4. **دسترسی‌ها ذخیره نمی‌شوند**: بررسی `custom_roles` table

### تست سیستم
```bash
# تست کامل
php test_users_complete.php

# تست API ها
curl -X GET "http://localhost/Aala_Niroo_AMS/get_user_permissions.php?user_id=1"
curl -X GET "http://localhost/Aala_Niroo_AMS/get_user_password.php?user_id=1"
```

## 📞 پشتیبانی

برای پشتیبانی و گزارش مشکلات:
- بررسی لاگ‌های سیستم
- اجرای تست‌های موجود
- بررسی فایل‌های پیکربندی

---

**نسخه**: 1.0.0  
**تاریخ**: 2024  
**توسعه‌دهنده**: سیستم مدیریت شرکت اعلا نیرو