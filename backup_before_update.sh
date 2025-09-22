#!/bin/bash
# backup_before_update.sh - اسکریپت پشتیبان‌گیری قبل از بروزرسانی

# تنظیمات
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/var/backups/aala_niroo_ams"
PROJECT_DIR="/var/www/html/Aala_Niroo_AMS"
DB_NAME="aala_niroo_ams"
DB_USER="aala_user"
DB_PASS="YourStrongPassword123!"

# ایجاد پوشه پشتیبان‌گیری
mkdir -p $BACKUP_DIR

echo "🔄 شروع پشتیبان‌گیری در $DATE..."

# 1. پشتیبان‌گیری از دیتابیس
echo "📊 پشتیبان‌گیری از دیتابیس..."
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME > $BACKUP_DIR/database_$DATE.sql
if [ $? -eq 0 ]; then
    echo "✅ پشتیبان‌گیری دیتابیس موفق"
else
    echo "❌ خطا در پشتیبان‌گیری دیتابیس"
    exit 1
fi

# 2. پشتیبان‌گیری از فایل‌های پروژه
echo "📁 پشتیبان‌گیری از فایل‌های پروژه..."
tar -czf $BACKUP_DIR/files_$DATE.tar.gz -C /var/www/html Aala_Niroo_AMS/
if [ $? -eq 0 ]; then
    echo "✅ پشتیبان‌گیری فایل‌ها موفق"
else
    echo "❌ خطا در پشتیبان‌گیری فایل‌ها"
    exit 1
fi

# 3. پشتیبان‌گیری از تنظیمات Apache
echo "⚙️ پشتیبان‌گیری از تنظیمات Apache..."
cp /etc/apache2/sites-available/000-default.conf $BACKUP_DIR/apache_config_$DATE.conf 2>/dev/null || echo "⚠️ فایل تنظیمات Apache یافت نشد"

# 4. پشتیبان‌گیری از تنظیمات PHP
echo "🐘 پشتیبان‌گیری از تنظیمات PHP..."
cp /etc/php/8.1/apache2/php.ini $BACKUP_DIR/php_ini_$DATE.ini 2>/dev/null || echo "⚠️ فایل php.ini یافت نشد"

# 5. پشتیبان‌گیری از فایل‌های لاگ
echo "📝 پشتیبان‌گیری از لاگ‌ها..."
if [ -d "$PROJECT_DIR/logs" ]; then
    tar -czf $BACKUP_DIR/logs_$DATE.tar.gz -C $PROJECT_DIR logs/
    echo "✅ پشتیبان‌گیری لاگ‌ها موفق"
else
    echo "⚠️ پوشه logs یافت نشد"
fi

# 6. ایجاد فایل اطلاعات پشتیبان‌گیری
echo "📋 ایجاد فایل اطلاعات..."
cat > $BACKUP_DIR/backup_info_$DATE.txt << EOF
تاریخ پشتیبان‌گیری: $DATE
دیتابیس: $DB_NAME
پوشه پروژه: $PROJECT_DIR
حجم دیتابیس: $(du -h $BACKUP_DIR/database_$DATE.sql | cut -f1)
حجم فایل‌ها: $(du -h $BACKUP_DIR/files_$DATE.tar.gz | cut -f1)
حجم لاگ‌ها: $(du -h $BACKUP_DIR/logs_$DATE.tar.gz 2>/dev/null | cut -f1 || echo "N/A")

فایل‌های پشتیبان‌گیری:
- database_$DATE.sql
- files_$DATE.tar.gz
- logs_$DATE.tar.gz
- apache_config_$DATE.conf
- php_ini_$DATE.ini
EOF

# 7. حذف پشتیبان‌های قدیمی (بیش از 30 روز)
echo "🗑️ حذف پشتیبان‌های قدیمی..."
find $BACKUP_DIR -name "*.sql" -mtime +30 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +30 -delete
find $BACKUP_DIR -name "*.conf" -mtime +30 -delete
find $BACKUP_DIR -name "*.ini" -mtime +30 -delete
find $BACKUP_DIR -name "*.txt" -mtime +30 -delete

# 8. نمایش خلاصه
echo ""
echo "🎉 پشتیبان‌گیری با موفقیت انجام شد!"
echo "📅 تاریخ: $DATE"
echo "📊 دیتابیس: $BACKUP_DIR/database_$DATE.sql"
echo "📁 فایل‌ها: $BACKUP_DIR/files_$DATE.tar.gz"
echo "📝 لاگ‌ها: $BACKUP_DIR/logs_$DATE.tar.gz"
echo "📋 اطلاعات: $BACKUP_DIR/backup_info_$DATE.txt"
echo ""
echo "💡 برای بازگردانی از اسکریپت rollback.sh استفاده کنید:"
echo "   ./rollback.sh $DATE"