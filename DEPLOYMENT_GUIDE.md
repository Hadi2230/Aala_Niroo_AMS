# 🚀 راهنمای کامل نصب روی سرور واقعی

## 📋 **مراحل نصب مرحله به مرحله:**

### **مرحله 1: آماده‌سازی سرور**

#### **1.1 نصب Apache:**
```bash
# Ubuntu/Debian
sudo apt update
sudo apt install apache2
sudo systemctl enable apache2
sudo systemctl start apache2

# CentOS/RHEL
sudo yum install httpd
sudo systemctl enable httpd
sudo systemctl start httpd
```

#### **1.2 نصب PHP 8.1:**
```bash
# Ubuntu/Debian
sudo apt install software-properties-common
sudo add-apt-repository ppa:ondrej/php
sudo apt update
sudo apt install php8.1 php8.1-mysql php8.1-mbstring php8.1-xml php8.1-curl php8.1-gd php8.1-zip php8.1-json php8.1-fileinfo php8.1-openssl php8.1-session php8.1-tokenizer php8.1-ctype php8.1-filter php8.1-hash php8.1-pcre php8.1-spl php8.1-standard php8.1-date php8.1-calendar php8.1-simplexml php8.1-xmlreader php8.1-xmlwriter php8.1-dom php8.1-libxml php8.1-opcache php8.1-readline php8.1-sockets php8.1-sysvmsg php8.1-sysvsem php8.1-sysvshm php8.1-wddx php8.1-xmlrpc php8.1-xsl php8.1-zlib

# CentOS/RHEL
sudo yum install epel-release
sudo yum install php81 php81-php-mysqlnd php81-php-mbstring php81-php-xml php81-php-curl php81-php-gd php81-php-zip php81-php-json php81-php-fileinfo php81-php-openssl php81-php-session php81-php-tokenizer php81-php-ctype php81-php-filter php81-php-hash php81-php-pcre php81-php-spl php81-php-standard php81-php-date php81-php-calendar php81-php-simplexml php81-php-xmlreader php81-php-xmlwriter php81-php-dom php81-php-libxml php81-php-opcache php81-php-readline php81-php-sockets php81-php-sysvmsg php81-php-sysvsem php81-php-sysvshm php81-php-wddx php81-php-xmlrpc php81-php-xsl php81-php-zlib
```

#### **1.3 نصب MySQL 8.0:**
```bash
# Ubuntu/Debian
sudo apt install mysql-server-8.0
sudo systemctl enable mysql
sudo systemctl start mysql
sudo mysql_secure_installation

# CentOS/RHEL
sudo yum install mysql-server
sudo systemctl enable mysqld
sudo systemctl start mysqld
sudo mysql_secure_installation
```

#### **1.4 فعال‌سازی mod_rewrite:**
```bash
# Apache
sudo a2enmod rewrite
sudo systemctl restart apache2

# یا در CentOS
sudo systemctl restart httpd
```

### **مرحله 2: تنظیم دیتابیس**

#### **2.1 ورود به MySQL:**
```bash
sudo mysql -u root -p
```

#### **2.2 ایجاد دیتابیس و کاربر:**
```sql
-- ایجاد دیتابیس
CREATE DATABASE aala_niroo_ams 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_persian_ci;

-- ایجاد کاربر
CREATE USER 'aala_user'@'localhost' IDENTIFIED BY 'YourStrongPassword123!';

-- اعطای مجوزها
GRANT ALL PRIVILEGES ON aala_niroo_ams.* TO 'aala_user'@'localhost';
FLUSH PRIVILEGES;

-- خروج
EXIT;
```

#### **2.3 تست اتصال:**
```bash
mysql -u aala_user -p aala_niroo_ams
```

### **مرحله 3: آپلود فایل‌ها**

#### **3.1 کپی فایل‌ها:**
```bash
# از طریق SCP
scp -r Aala_Niroo_AMS/ user@your-server:/var/www/html/

# یا از طریق Git
cd /var/www/html
git clone https://github.com/Hadi2230/Aala_Niroo_AMS.git
```

#### **3.2 تنظیم مجوزها:**
```bash
sudo chown -R www-data:www-data /var/www/html/Aala_Niroo_AMS/
sudo chmod -R 755 /var/www/html/Aala_Niroo_AMS/
sudo chmod -R 777 /var/www/html/Aala_Niroo_AMS/uploads/
sudo chmod -R 777 /var/www/html/Aala_Niroo_AMS/logs/
```

### **مرحله 4: تنظیم PHP**

#### **4.1 ویرایش php.ini:**
```bash
sudo nano /etc/php/8.1/apache2/php.ini
```

#### **4.2 تنظیمات مورد نیاز:**
```ini
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

#### **4.3 ریستارت Apache:**
```bash
sudo systemctl restart apache2
```

### **مرحله 5: تنظیم config.php**

#### **5.1 ویرایش فایل config.php:**
```bash
sudo nano /var/www/html/Aala_Niroo_AMS/config.php
```

#### **5.2 تغییر تنظیمات دیتابیس:**
```php
// تغییر این خطوط
$host = 'localhost';
$dbname = 'aala_niroo_ams';
$username = 'aala_user';
$password = 'YourStrongPassword123!';
```

### **مرحله 6: ایجاد فایل .htaccess**

#### **6.1 ایجاد فایل .htaccess:**
```bash
sudo nano /var/www/html/Aala_Niroo_AMS/.htaccess
```

#### **6.2 محتوای .htaccess:**
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

# محافظت از پوشه vendor
<Directory "vendor">
    Order allow,deny
    Deny from all
</Directory>

# فعال‌سازی mod_rewrite
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# تنظیمات امنیتی
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; font-src 'self' https://cdn.jsdelivr.net; img-src 'self' data:;"
```

### **مرحله 7: نصب Composer (اختیاری)**

#### **7.1 نصب Composer:**
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

#### **7.2 نصب وابستگی‌ها:**
```bash
cd /var/www/html/Aala_Niroo_AMS
composer install
```

### **مرحله 8: تست سیستم**

#### **8.1 تست اولیه:**
```bash
# تست PHP
php -v

# تست MySQL
mysql -u aala_user -p aala_niroo_ams -e "SELECT 1;"

# تست Apache
sudo systemctl status apache2
```

#### **8.2 تست از طریق مرورگر:**
1. `http://your-server-ip/Aala_Niroo_AMS/quick_test_simple.php`
2. `http://your-server-ip/Aala_Niroo_AMS/system_test_simple.php`
3. `http://your-server-ip/Aala_Niroo_AMS/login.php`

### **مرحله 9: تنظیم SSL (اختیاری اما توصیه می‌شود)**

#### **9.1 نصب Certbot:**
```bash
sudo apt install certbot python3-certbot-apache
```

#### **9.2 دریافت گواهی SSL:**
```bash
sudo certbot --apache -d your-domain.com
```

### **مرحله 10: پشتیبان‌گیری**

#### **10.1 اسکریپت پشتیبان‌گیری:**
```bash
sudo nano /var/www/html/Aala_Niroo_AMS/backup.sh
```

#### **10.2 محتوای اسکریپت:**
```bash
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/var/backups/aala_niroo_ams"
mkdir -p $BACKUP_DIR

# پشتیبان‌گیری از دیتابیس
mysqldump -u aala_user -p aala_niroo_ams > $BACKUP_DIR/database_$DATE.sql

# پشتیبان‌گیری از فایل‌ها
tar -czf $BACKUP_DIR/files_$DATE.tar.gz /var/www/html/Aala_Niroo_AMS/

# حذف پشتیبان‌های قدیمی (بیش از 30 روز)
find $BACKUP_DIR -name "*.sql" -mtime +30 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +30 -delete

echo "پشتیبان‌گیری در $DATE انجام شد"
```

#### **10.3 اجرای خودکار:**
```bash
sudo chmod +x /var/www/html/Aala_Niroo_AMS/backup.sh
sudo crontab -e

# اضافه کردن این خط برای اجرای روزانه در ساعت 2 صبح
0 2 * * * /var/www/html/Aala_Niroo_AMS/backup.sh
```

---

## 🔧 **تنظیمات اضافی:**

### **1. تنظیم فایروال:**
```bash
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

### **2. تنظیم swap (اگر RAM کم است):**
```bash
sudo fallocate -l 2G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

### **3. تنظیم logrotate:**
```bash
sudo nano /etc/logrotate.d/aala_niroo_ams
```

```bash
/var/www/html/Aala_Niroo_AMS/logs/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
}
```

---

## ✅ **چک‌لیست نهایی:**

- [ ] Apache نصب و فعال است
- [ ] PHP 8.1+ نصب شده
- [ ] MySQL 8.0+ نصب شده
- [ ] دیتابیس ایجاد شده
- [ ] کاربر دیتابیس ایجاد شده
- [ ] فایل‌ها آپلود شده
- [ ] مجوزها تنظیم شده
- [ ] config.php تنظیم شده
- [ ] .htaccess ایجاد شده
- [ ] SSL تنظیم شده (اختیاری)
- [ ] پشتیبان‌گیری تنظیم شده
- [ ] فایروال تنظیم شده
- [ ] سیستم تست شده

---

## 🎯 **نتیجه:**
سیستم شما آماده استفاده روی سرور واقعی است! 🚀