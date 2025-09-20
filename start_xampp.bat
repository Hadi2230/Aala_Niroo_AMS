@echo off
echo ========================================
echo    راه‌اندازی خودکار XAMPP
echo ========================================
echo.

echo بررسی وضعیت XAMPP...
tasklist /FI "IMAGENAME eq httpd.exe" 2>NUL | find /I /N "httpd.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo Apache در حال اجرا است
) else (
    echo Apache متوقف است - در حال راه‌اندازی...
)

tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I /N "mysqld.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo MySQL در حال اجرا است
) else (
    echo MySQL متوقف است - در حال راه‌اندازی...
)

echo.
echo تلاش برای راه‌اندازی XAMPP...
echo.

REM مسیر XAMPP را تغییر دهید اگر متفاوت است
set XAMPP_PATH=C:\xampp
if not exist "%XAMPP_PATH%" (
    set XAMPP_PATH=C:\Program Files\XAMPP
)
if not exist "%XAMPP_PATH%" (
    set XAMPP_PATH=C:\xampp
)

echo مسیر XAMPP: %XAMPP_PATH%

REM راه‌اندازی Apache
echo راه‌اندازی Apache...
"%XAMPP_PATH%\apache\bin\httpd.exe" -k start
if %ERRORLEVEL%==0 (
    echo ✅ Apache با موفقیت راه‌اندازی شد
) else (
    echo ❌ خطا در راه‌اندازی Apache
)

REM راه‌اندازی MySQL
echo راه‌اندازی MySQL...
"%XAMPP_PATH%\mysql\bin\mysqld.exe" --defaults-file="%XAMPP_PATH%\mysql\bin\my.ini" --standalone --console
if %ERRORLEVEL%==0 (
    echo ✅ MySQL با موفقیت راه‌اندازی شد
) else (
    echo ❌ خطا در راه‌اندازی MySQL
)

echo.
echo ========================================
echo    تست اتصال
echo ========================================

timeout /t 3 /nobreak >nul

echo تست اتصال به localhost:8080...
curl -s -o nul -w "HTTP Status: %%{http_code}\n" http://localhost:8080
if %ERRORLEVEL%==0 (
    echo ✅ اتصال به localhost:8080 موفق
) else (
    echo ❌ اتصال به localhost:8080 ناموفق
)

echo.
echo ========================================
echo    لینک‌های مفید
echo ========================================
echo.
echo http://localhost:8080 - صفحه اصلی XAMPP
echo http://localhost:8080/Aala_Niroo_AMS - پروژه شما
echo http://localhost:8080/Aala_Niroo_AMS/test_xampp.php - تست وضعیت
echo http://localhost:8080/Aala_Niroo_AMS/mysql_fix_simple.php - تست MySQL
echo http://localhost:8080/Aala_Niroo_AMS/login.php - ورود به سیستم
echo.

echo برای بستن این پنجره، کلیدی فشار دهید...
pause >nul