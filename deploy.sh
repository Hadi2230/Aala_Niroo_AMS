#!/bin/bash
# deploy.sh - اسکریپت استقرار خودکار روی سرور

# تنظیمات
SERVER_USER="user"
SERVER_HOST="your-server.com"
SERVER_PATH="/var/www/html/Aala_Niroo_AMS"
LOCAL_PATH="."

# رنگ‌ها برای خروجی
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}🚀 شروع استقرار سیستم Aala Niroo AMS${NC}"
echo "=================================================="

# بررسی وجود فایل‌های ضروری
echo -e "${YELLOW}📋 بررسی فایل‌های ضروری...${NC}"
required_files=("config.php" "index.php" "login.php" "database.sql" "composer.json")
for file in "${required_files[@]}"; do
    if [ ! -f "$file" ]; then
        echo -e "${RED}❌ فایل ضروری یافت نشد: $file${NC}"
        exit 1
    fi
done
echo -e "${GREEN}✅ تمام فایل‌های ضروری موجود است${NC}"

# ایجاد پشتیبان‌گیری محلی
echo -e "${YELLOW}💾 ایجاد پشتیبان‌گیری محلی...${NC}"
BACKUP_DIR="backup_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"
tar -czf "$BACKUP_DIR/local_backup.tar.gz" .
echo -e "${GREEN}✅ پشتیبان‌گیری محلی ایجاد شد: $BACKUP_DIR${NC}"

# آپلود فایل‌ها به سرور
echo -e "${YELLOW}📤 آپلود فایل‌ها به سرور...${NC}"
rsync -avz --exclude='.git' --exclude='backup_*' --exclude='*.log' "$LOCAL_PATH/" "$SERVER_USER@$SERVER_HOST:$SERVER_PATH/"
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ فایل‌ها با موفقیت آپلود شدند${NC}"
else
    echo -e "${RED}❌ خطا در آپلود فایل‌ها${NC}"
    exit 1
fi

# اجرای دستورات روی سرور
echo -e "${YELLOW}⚙️ تنظیم مجوزها و وابستگی‌ها...${NC}"
ssh "$SERVER_USER@$SERVER_HOST" << EOF
    cd $SERVER_PATH
    
    # تنظیم مجوزها
    sudo chown -R www-data:www-data .
    sudo chmod -R 755 .
    sudo chmod -R 777 uploads/ logs/
    
    # نصب وابستگی‌ها
    if [ -f composer.json ]; then
        composer install --no-dev --optimize-autoloader
    fi
    
    # ایجاد پوشه‌های مورد نیاز
    mkdir -p uploads/requests uploads/assets uploads/assignments uploads/visit_documents uploads/visit_photos logs
    
    # تنظیم مجوزهای پوشه‌ها
    sudo chmod -R 777 uploads/ logs/
    
    echo "✅ تنظیمات سرور تکمیل شد"
EOF

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ تنظیمات سرور با موفقیت انجام شد${NC}"
else
    echo -e "${RED}❌ خطا در تنظیمات سرور${NC}"
    exit 1
fi

# تست اتصال
echo -e "${YELLOW}🧪 تست اتصال به سیستم...${NC}"
HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "http://$SERVER_HOST/Aala_Niroo_AMS/quick_test_simple.php")
if [ "$HTTP_STATUS" = "200" ]; then
    echo -e "${GREEN}✅ سیستم با موفقیت در دسترس است${NC}"
else
    echo -e "${YELLOW}⚠️ هشدار: تست HTTP ناموفق بود (کد: $HTTP_STATUS)${NC}"
fi

# نمایش خلاصه
echo ""
echo -e "${BLUE}📊 خلاصه استقرار:${NC}"
echo "=================================================="
echo -e "🌐 آدرس سیستم: ${GREEN}http://$SERVER_HOST/Aala_Niroo_AMS${NC}"
echo -e "📁 مسیر سرور: ${GREEN}$SERVER_PATH${NC}"
echo -e "💾 پشتیبان‌گیری: ${GREEN}$BACKUP_DIR${NC}"
echo -e "📅 تاریخ: ${GREEN}$(date)${NC}"
echo ""

# نمایش دستورات مفید
echo -e "${BLUE}🔧 دستورات مفید:${NC}"
echo "=================================================="
echo -e "ورود به سرور: ${YELLOW}ssh $SERVER_USER@$SERVER_HOST${NC}"
echo -e "مشاهده لاگ‌ها: ${YELLOW}tail -f $SERVER_PATH/logs/php-errors.log${NC}"
echo -e "تست سیستم: ${YELLOW}curl http://$SERVER_HOST/Aala_Niroo_AMS/quick_test_simple.php${NC}"
echo -e "بازگردانی: ${YELLOW}tar -xzf $BACKUP_DIR/local_backup.tar.gz${NC}"
echo ""

echo -e "${GREEN}🎉 استقرار با موفقیت تکمیل شد!${NC}"