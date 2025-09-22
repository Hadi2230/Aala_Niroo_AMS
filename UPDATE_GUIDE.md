# 🔄 راهنمای بروزرسانی و توسعه سیستم Aala Niroo AMS

## 📋 **قابلیت‌های بروزرسانی:**

### **✅ بروزرسانی کد:**
- Git Integration کامل
- Hot Deploy بدون توقف سیستم
- Version Control و Rollback
- Automated Testing

### **✅ اضافه کردن قابلیت‌های جدید:**
- ساختار ماژولی
- Plugin System
- API Ready
- Database Schema Updates

### **✅ تغییرات در دیتابیس:**
- Migration System
- Data Backup
- Schema Versioning

---

## 🚀 **روش‌های بروزرسانی:**

### **روش 1: Git Pull (توصیه شده)**
```bash
# 1. ورود به سرور
ssh user@your-server.com

# 2. رفتن به پوشه پروژه
cd /var/www/html/Aala_Niroo_AMS

# 3. پشتیبان‌گیری
./backup.sh

# 4. دریافت تغییرات جدید
git pull origin main

# 5. نصب وابستگی‌های جدید
composer install

# 6. بروزرسانی دیتابیس (اگر نیاز باشد)
php database_migration.php

# 7. پاک کردن cache
sudo systemctl reload apache2
```

### **روش 2: Manual Update**
```bash
# 1. آپلود فایل‌های جدید
scp -r updated_files/ user@server:/var/www/html/Aala_Niroo_AMS/

# 2. تنظیم مجوزها
sudo chown -R www-data:www-data /var/www/html/Aala_Niroo_AMS/
sudo chmod -R 755 /var/www/html/Aala_Niroo_AMS/

# 3. ریستارت سرویس‌ها
sudo systemctl restart apache2
```

### **روش 3: CI/CD Pipeline**
```yaml
# .github/workflows/deploy.yml
name: Deploy to Production
on:
  push:
    branches: [ main ]
jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
    - uses: actions/checkout@v2
    - name: Deploy to server
      run: |
        # Deploy commands
        scp -r . user@server:/var/www/html/Aala_Niroo_AMS/
        ssh user@server "cd /var/www/html/Aala_Niroo_AMS && composer install"
```

---

## 🔧 **بروزرسانی دیتابیس:**

### **1. Migration System:**
```php
// database_migration.php
<?php
require_once 'config.php';

function runMigrations($pdo) {
    $migrations = [
        '001_add_new_table' => "
            CREATE TABLE IF NOT EXISTS new_table (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
        ",
        '002_add_column' => "
            ALTER TABLE existing_table 
            ADD COLUMN new_column VARCHAR(255) AFTER existing_column
        "
    ];
    
    foreach ($migrations as $version => $sql) {
        try {
            $pdo->exec($sql);
            echo "Migration $version completed successfully\n";
        } catch (Exception $e) {
            echo "Error in migration $version: " . $e->getMessage() . "\n";
        }
    }
}

runMigrations($pdo);
?>
```

### **2. Schema Versioning:**
```sql
-- جدول version control
CREATE TABLE IF NOT EXISTS schema_versions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    version VARCHAR(50) NOT NULL,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## 📁 **ساختار فایل‌ها برای توسعه:**

### **پوشه‌های پیشنهادی:**
```
/
├── src/                    # کدهای اصلی
│   ├── Controllers/        # کنترلرها
│   ├── Models/            # مدل‌ها
│   ├── Views/             # ویوها
│   └── Helpers/           # توابع کمکی
├── migrations/            # فایل‌های migration
├── plugins/               # پلاگین‌ها
├── api/                   # API endpoints
├── tests/                 # تست‌ها
└── docs/                  # مستندات
```

### **فایل‌های پیکربندی:**
```
/
├── config/
│   ├── database.php       # تنظیمات دیتابیس
│   ├── app.php           # تنظیمات کلی
│   └── plugins.php       # تنظیمات پلاگین‌ها
├── .env                   # متغیرهای محیطی
└── composer.json          # وابستگی‌ها
```

---

## 🔐 **امنیت در بروزرسانی:**

### **1. Backup Strategy:**
```bash
#!/bin/bash
# backup_before_update.sh
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/var/backups/aala_niroo_ams"

# پشتیبان‌گیری از دیتابیس
mysqldump -u aala_user -p aala_niroo_ams > $BACKUP_DIR/database_$DATE.sql

# پشتیبان‌گیری از فایل‌ها
tar -czf $BACKUP_DIR/files_$DATE.tar.gz /var/www/html/Aala_Niroo_AMS/

# پشتیبان‌گیری از تنظیمات
cp /etc/apache2/sites-available/aala_niroo_ams.conf $BACKUP_DIR/
cp /etc/php/8.1/apache2/php.ini $BACKUP_DIR/

echo "Backup completed: $DATE"
```

### **2. Rollback Strategy:**
```bash
#!/bin/bash
# rollback.sh
BACKUP_DATE=$1

if [ -z "$BACKUP_DATE" ]; then
    echo "Usage: ./rollback.sh YYYYMMDD_HHMMSS"
    exit 1
fi

# بازگردانی دیتابیس
mysql -u aala_user -p aala_niroo_ams < /var/backups/aala_niroo_ams/database_$BACKUP_DATE.sql

# بازگردانی فایل‌ها
tar -xzf /var/backups/aala_niroo_ams/files_$BACKUP_DATE.tar.gz -C /

# ریستارت سرویس‌ها
sudo systemctl restart apache2

echo "Rollback completed to: $BACKUP_DATE"
```

---

## 🧪 **تست قبل از بروزرسانی:**

### **1. Staging Environment:**
```bash
# ایجاد محیط تست
cp -r /var/www/html/Aala_Niroo_AMS /var/www/html/Aala_Niroo_AMS_staging
```

### **2. Automated Tests:**
```php
// tests/system_test.php
<?php
require_once 'config.php';

function runSystemTests() {
    $tests = [
        'Database Connection' => testDatabaseConnection(),
        'File Permissions' => testFilePermissions(),
        'PHP Extensions' => testPHPExtensions(),
        'Upload Functionality' => testUploadFunctionality(),
        'User Authentication' => testUserAuthentication(),
        'Request Workflow' => testRequestWorkflow()
    ];
    
    foreach ($tests as $test => $result) {
        echo $test . ': ' . ($result ? 'PASS' : 'FAIL') . "\n";
    }
}

runSystemTests();
?>
```

---

## 📊 **مانیتورینگ پس از بروزرسانی:**

### **1. Health Check:**
```bash
# health_check.sh
#!/bin/bash
URL="http://your-server.com/Aala_Niroo_AMS/quick_test_simple.php"
RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" $URL)

if [ $RESPONSE -eq 200 ]; then
    echo "✅ System is healthy"
else
    echo "❌ System error: HTTP $RESPONSE"
    # ارسال اعلان
    ./send_alert.sh "System health check failed"
fi
```

### **2. Performance Monitoring:**
```php
// monitoring.php
<?php
function checkSystemPerformance() {
    $metrics = [
        'response_time' => measureResponseTime(),
        'memory_usage' => memory_get_usage(true),
        'database_connections' => countDatabaseConnections(),
        'error_rate' => getErrorRate(),
        'active_users' => getActiveUsers()
    ];
    
    return $metrics;
}
?>
```

---

## 🔄 **چرخه بروزرسانی:**

### **1. Development:**
- توسعه قابلیت‌های جدید
- تست در محیط محلی
- Code Review

### **2. Staging:**
- تست در محیط شبیه‌سازی
- تست عملکرد
- تست امنیت

### **3. Production:**
- پشتیبان‌گیری
- بروزرسانی
- مانیتورینگ
- Rollback (در صورت نیاز)

---

## 📋 **چک‌لیست بروزرسانی:**

### **قبل از بروزرسانی:**
- [ ] پشتیبان‌گیری کامل
- [ ] تست در محیط staging
- [ ] بررسی تغییرات
- [ ] آماده‌سازی rollback

### **حین بروزرسانی:**
- [ ] توقف سرویس‌ها (اختیاری)
- [ ] آپلود فایل‌های جدید
- [ ] بروزرسانی دیتابیس
- [ ] تنظیم مجوزها

### **پس از بروزرسانی:**
- [ ] تست عملکرد
- [ ] مانیتورینگ خطاها
- [ ] بررسی لاگ‌ها
- [ ] اطلاع‌رسانی به کاربران

---

## 🎯 **نتیجه:**

**✅ سیستم کاملاً قابل بروزرسانی و توسعه است!**

**قابلیت‌های کلیدی:**
- 🔄 بروزرسانی بدون توقف
- 🔒 امنیت کامل
- 📊 مانیتورینگ پیشرفته
- 🔙 قابلیت بازگشت
- 🧪 تست خودکار
- 📈 مقیاس‌پذیری

**🚀 آماده برای توسعه و بروزرسانی مداوم!** 🎉