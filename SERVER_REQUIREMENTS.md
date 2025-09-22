# 🚀 نیازمندی‌های سرور برای سیستم Aala Niroo AMS

## 📋 **خلاصه پروژه:**
سیستم مدیریت دارایی‌ها و انتساب دستگاه‌ها - یک سیستم PHP کامل با قابلیت‌های چندکاربره

---

## 🔧 **نیازمندی‌های Backend:**

### **1. سرور وب:**
- **Apache 2.4+** یا **Nginx 1.18+**
- **PHP 8.0+** (توصیه: PHP 8.1 یا 8.2)
- **MySQL 8.0+** یا **MariaDB 10.6+**

### **2. PHP Extensions مورد نیاز:**
```bash
# Extensions ضروری
php-mysql (PDO)
php-mysqli
php-json
php-mbstring
php-xml
php-curl
php-gd
php-zip
php-fileinfo
php-openssl
php-session
php-tokenizer
php-ctype
php-filter
php-hash
php-pcre
php-spl
php-standard
php-date
php-calendar
php-simplexml
php-xmlreader
php-xmlwriter
php-dom
php-libxml
php-opcache
php-readline
php-sockets
php-sysvmsg
php-sysvsem
php-sysvshm
php-wddx
php-xmlrpc
php-xsl
php-zlib
```

### **3. تنظیمات PHP:**
```ini
# php.ini
memory_limit = 256M
max_execution_time = 300
max_input_time = 300
post_max_size = 50M
upload_max_filesize = 50M
max_file_uploads = 20
date.timezone = Asia/Tehran
default_charset = UTF-8
mbstring.language = Persian
mbstring.internal_encoding = UTF-8
mbstring.http_input = UTF-8
mbstring.http_output = UTF-8
mbstring.encoding_translation = On
mbstring.detect_order = UTF-8
session.gc_maxlifetime = 3600
session.cookie_lifetime = 0
```

### **4. دیتابیس:**
- **MySQL 8.0+** با charset `utf8mb4`
- **Collation:** `utf8mb4_persian_ci`
- **Engine:** InnoDB
- **حداقل 1GB فضای خالی**

---

## 📁 **ساختار فایل‌ها:**

### **فایل‌های اصلی:**
```
/
├── config.php (تنظیمات اصلی)
├── index.php (صفحه اصلی)
├── login.php (ورود)
├── navbar.php (منوی ناوبری)
├── assets.php (مدیریت دارایی‌ها)
├── assignments.php (انتساب دستگاه‌ها)
├── customers.php (مدیریت مشتریان)
├── users.php (مدیریت کاربران)
├── request_management_final.php (مدیریت درخواست‌ها)
├── request_workflow_professional.php (گردش کار)
├── my_assigned_requests.php (درخواست‌های ارجاع شده)
├── system_logs.php (لاگ سیستم)
├── notifications.php (اعلان‌ها)
└── reports.php (گزارش‌ها)
```

### **پوشه‌های مورد نیاز:**
```
/
├── uploads/ (آپلود فایل‌ها)
│   ├── requests/ (فایل‌های درخواست‌ها)
│   ├── assets/ (تصاویر دارایی‌ها)
│   ├── assignments/ (تصاویر انتساب‌ها)
│   ├── visit_documents/ (اسناد بازدید)
│   └── visit_photos/ (عکس‌های بازدید)
├── logs/ (فایل‌های لاگ)
├── vendor/ (کتابخانه‌های PHP)
└── assets/ (فایل‌های استاتیک)
```

---

## 🔐 **تنظیمات امنیتی:**

### **1. فایل .htaccess:**
```apache
# محافظت از فایل‌های حساس
<Files "config.php">
    Order allow,deny
    Deny from all
</Files>

<Files "*.log">
    Order allow,deny
    Deny from all
</Files>

# محافظت از پوشه logs
<Directory "logs">
    Order allow,deny
    Deny from all
</Directory>

# فعال‌سازی mod_rewrite
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

### **2. مجوزهای فایل:**
```bash
# مجوزهای پوشه‌ها
chmod 755 /var/www/html/aala_niroo_ams/
chmod 755 /var/www/html/aala_niroo_ams/uploads/
chmod 755 /var/www/html/aala_niroo_ams/logs/
chmod 644 /var/www/html/aala_niroo_ams/*.php

# مالکیت فایل‌ها
chown -R www-data:www-data /var/www/html/aala_niroo_ams/
```

---

## 🗄️ **تنظیمات دیتابیس:**

### **1. ایجاد دیتابیس:**
```sql
CREATE DATABASE aala_niroo_ams 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_persian_ci;

CREATE USER 'aala_user'@'localhost' IDENTIFIED BY 'strong_password_here';
GRANT ALL PRIVILEGES ON aala_niroo_ams.* TO 'aala_user'@'localhost';
FLUSH PRIVILEGES;
```

### **2. جداول اصلی:**
- `users` (کاربران سیستم)
- `assets` (دارایی‌ها)
- `customers` (مشتریان)
- `asset_assignments` (انتساب‌ها)
- `requests` (درخواست‌ها)
- `request_workflow` (گردش کار)
- `request_notifications` (اعلان‌ها)
- `request_files` (فایل‌های ضمیمه)
- `visit_management` (مدیریت بازدیدها)
- `tools` (ابزارها)
- `suppliers` (تأمین‌کنندگان)

---

## 📧 **تنظیمات ایمیل (اختیاری):**

### **PHPMailer:**
```bash
composer install
```

### **تنظیمات SMTP:**
```php
// در config.php
$smtp_host = 'smtp.gmail.com';
$smtp_port = 587;
$smtp_username = 'your-email@gmail.com';
$smtp_password = 'your-app-password';
$smtp_encryption = 'tls';
```

---

## 🚀 **مراحل نصب روی سرور:**

### **1. آماده‌سازی سرور:**
```bash
# نصب Apache, PHP, MySQL
sudo apt update
sudo apt install apache2 php8.1 php8.1-mysql php8.1-mbstring php8.1-xml php8.1-curl php8.1-gd php8.1-zip
sudo apt install mysql-server-8.0

# فعال‌سازی mod_rewrite
sudo a2enmod rewrite
sudo systemctl restart apache2
```

### **2. آپلود فایل‌ها:**
```bash
# کپی فایل‌ها به سرور
scp -r Aala_Niroo_AMS/ user@server:/var/www/html/

# تنظیم مجوزها
sudo chown -R www-data:www-data /var/www/html/Aala_Niroo_AMS/
sudo chmod -R 755 /var/www/html/Aala_Niroo_AMS/
```

### **3. تنظیم دیتابیس:**
```bash
# وارد شدن به MySQL
mysql -u root -p

# اجرای فایل database.sql
source /var/www/html/Aala_Niroo_AMS/database.sql;
```

### **4. تنظیم config.php:**
```php
// تغییر تنظیمات دیتابیس
$host = 'localhost';
$dbname = 'aala_niroo_ams';
$username = 'aala_user';
$password = 'strong_password_here';
```

---

## 🔍 **تست سیستم:**

### **1. تست‌های اولیه:**
- `http://yourserver.com/Aala_Niroo_AMS/quick_test_simple.php`
- `http://yourserver.com/Aala_Niroo_AMS/system_test_simple.php`

### **2. تست عملکرد:**
- ورود به سیستم
- ایجاد درخواست جدید
- ارجاع درخواست
- آپلود فایل
- مشاهده گزارش‌ها

---

## ⚠️ **نکات مهم:**

### **1. امنیت:**
- رمزهای عبور قوی استفاده کنید
- فایل‌های حساس را محافظت کنید
- SSL/HTTPS فعال کنید
- فایروال مناسب تنظیم کنید

### **2. پشتیبان‌گیری:**
- پشتیبان‌گیری منظم از دیتابیس
- پشتیبان‌گیری از فایل‌های آپلود شده
- پشتیبان‌گیری از فایل‌های لاگ

### **3. مانیتورینگ:**
- مانیتورینگ فضای دیسک
- مانیتورینگ عملکرد دیتابیس
- بررسی لاگ‌های خطا

---

## 📊 **حداقل منابع سرور:**

- **RAM:** 2GB (توصیه: 4GB+)
- **CPU:** 2 Core (توصیه: 4 Core+)
- **Storage:** 20GB (توصیه: 50GB+)
- **Bandwidth:** نامحدود

---

## 🎯 **نتیجه:**
این سیستم کاملاً آماده برای اجرا روی سرور واقعی است و تمام نیازمندی‌های یک سیستم مدیریت دارایی حرفه‌ای را دارد.