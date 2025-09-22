#!/bin/bash
# rollback.sh - اسکریپت بازگردانی به نسخه قبلی

# تنظیمات
BACKUP_DIR="/var/backups/aala_niroo_ams"
PROJECT_DIR="/var/www/html/Aala_Niroo_AMS"
DB_NAME="aala_niroo_ams"
DB_USER="aala_user"
DB_PASS="YourStrongPassword123!"

# بررسی پارامتر ورودی
if [ -z "$1" ]; then
    echo "❌ خطا: تاریخ پشتیبان‌گیری را مشخص کنید"
    echo "💡 استفاده: ./rollback.sh YYYYMMDD_HHMMSS"
    echo ""
    echo "📋 پشتیبان‌گیری‌های موجود:"
    ls -la $BACKUP_DIR/*.txt 2>/dev/null | awk '{print $9}' | sed 's/.*backup_info_//' | sed 's/.txt$//' | sort -r
    exit 1
fi

BACKUP_DATE=$1

# بررسی وجود پشتیبان‌گیری
if [ ! -f "$BACKUP_DIR/database_$BACKUP_DATE.sql" ]; then
    echo "❌ خطا: پشتیبان‌گیری دیتابیس یافت نشد: $BACKUP_DIR/database_$BACKUP_DATE.sql"
    exit 1
fi

if [ ! -f "$BACKUP_DIR/files_$BACKUP_DATE.tar.gz" ]; then
    echo "❌ خطا: پشتیبان‌گیری فایل‌ها یافت نشد: $BACKUP_DIR/files_$BACKUP_DATE.tar.gz"
    exit 1
fi

echo "🔄 شروع بازگردانی به نسخه $BACKUP_DATE..."

# 1. توقف سرویس‌ها
echo "⏹️ توقف سرویس‌ها..."
sudo systemctl stop apache2

# 2. پشتیبان‌گیری از وضعیت فعلی (قبل از rollback)
echo "💾 پشتیبان‌گیری از وضعیت فعلی..."
CURRENT_DATE=$(date +%Y%m%d_%H%M%S)
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME > $BACKUP_DIR/current_before_rollback_$CURRENT_DATE.sql
tar -czf $BACKUP_DIR/current_before_rollback_$CURRENT_DATE.tar.gz -C /var/www/html Aala_Niroo_AMS/

# 3. بازگردانی دیتابیس
echo "📊 بازگردانی دیتابیس..."
mysql -u $DB_USER -p$DB_PASS $DB_NAME < $BACKUP_DIR/database_$BACKUP_DATE.sql
if [ $? -eq 0 ]; then
    echo "✅ بازگردانی دیتابیس موفق"
else
    echo "❌ خطا در بازگردانی دیتابیس"
    echo "🔄 بازگردانی وضعیت فعلی..."
    mysql -u $DB_USER -p$DB_PASS $DB_NAME < $BACKUP_DIR/current_before_rollback_$CURRENT_DATE.sql
    sudo systemctl start apache2
    exit 1
fi

# 4. بازگردانی فایل‌ها
echo "📁 بازگردانی فایل‌ها..."
# حذف فایل‌های فعلی
rm -rf $PROJECT_DIR/*
# استخراج فایل‌های پشتیبان‌گیری
tar -xzf $BACKUP_DIR/files_$BACKUP_DATE.tar.gz -C /var/www/html/
if [ $? -eq 0 ]; then
    echo "✅ بازگردانی فایل‌ها موفق"
else
    echo "❌ خطا در بازگردانی فایل‌ها"
    echo "🔄 بازگردانی وضعیت فعلی..."
    tar -xzf $BACKUP_DIR/current_before_rollback_$CURRENT_DATE.tar.gz -C /var/www/html/
    sudo systemctl start apache2
    exit 1
fi

# 5. تنظیم مجوزها
echo "🔐 تنظیم مجوزها..."
sudo chown -R www-data:www-data $PROJECT_DIR
sudo chmod -R 755 $PROJECT_DIR
sudo chmod -R 777 $PROJECT_DIR/uploads/
sudo chmod -R 777 $PROJECT_DIR/logs/

# 6. بازگردانی لاگ‌ها (اختیاری)
if [ -f "$BACKUP_DIR/logs_$BACKUP_DATE.tar.gz" ]; then
    echo "📝 بازگردانی لاگ‌ها..."
    tar -xzf $BACKUP_DIR/logs_$BACKUP_DATE.tar.gz -C $PROJECT_DIR/
    echo "✅ بازگردانی لاگ‌ها موفق"
fi

# 7. راه‌اندازی مجدد سرویس‌ها
echo "🚀 راه‌اندازی مجدد سرویس‌ها..."
sudo systemctl start apache2

# 8. تست سیستم
echo "🧪 تست سیستم..."
sleep 5
HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/Aala_Niroo_AMS/quick_test_simple.php)
if [ "$HTTP_STATUS" = "200" ]; then
    echo "✅ سیستم با موفقیت بازگردانی شد"
else
    echo "⚠️ هشدار: سیستم بازگردانی شد اما تست HTTP ناموفق بود (کد: $HTTP_STATUS)"
fi

# 9. نمایش خلاصه
echo ""
echo "🎉 بازگردانی با موفقیت انجام شد!"
echo "📅 نسخه بازگردانی شده: $BACKUP_DATE"
echo "🕐 زمان بازگردانی: $(date)"
echo "💾 پشتیبان‌گیری از وضعیت قبلی: current_before_rollback_$CURRENT_DATE"
echo ""
echo "🔗 تست سیستم: http://your-server.com/Aala_Niroo_AMS/quick_test_simple.php"
echo "📊 لاگ‌ها: $PROJECT_DIR/logs/"