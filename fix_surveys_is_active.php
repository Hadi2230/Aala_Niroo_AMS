<?php
// اسکریپت اصلاح جدول surveys برای اضافه کردن ستون is_active
session_start();

// تنظیمات دیتابیس
$host = 'localhost:3306';
$dbname = 'aala_niroo';
$username = 'root';
$password = '';

echo "<h2>اصلاح جدول surveys - اضافه کردن ستون is_active</h2>";

try {
    // اتصال به دیتابیس
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "<p style='color: green;'>✅ اتصال به دیتابیس موفق بود</p>";
    
    // بررسی وجود ستون is_active
    $stmt = $pdo->query("SHOW COLUMNS FROM surveys LIKE 'is_active'");
    $has_is_active = $stmt->rowCount() > 0;
    
    if (!$has_is_active) {
        echo "<p>ستون is_active وجود ندارد. در حال اضافه کردن...</p>";
        
        // اضافه کردن ستون is_active
        $pdo->exec("ALTER TABLE surveys ADD COLUMN is_active BOOLEAN DEFAULT 1");
        
        // به‌روزرسانی تمام رکوردهای موجود
        $pdo->exec("UPDATE surveys SET is_active = 1 WHERE is_active IS NULL");
        
        echo "<p style='color: green;'>✅ ستون is_active با موفقیت اضافه شد!</p>";
    } else {
        echo "<p style='color: blue;'>ℹ️ ستون is_active قبلاً وجود دارد.</p>";
    }
    
    // بررسی ساختار جدول
    echo "<h3>ساختار جدول surveys:</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Default</th><th>Extra</th></tr>";
    
    $stmt = $pdo->query("SHOW COLUMNS FROM surveys");
    $columns = $stmt->fetchAll();
    
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td>" . ($column['Null'] === 'YES' ? 'Yes' : 'No') . "</td>";
        echo "<td>" . htmlspecialchars($column['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($column['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // تست کوئری
    echo "<h3>تست کوئری:</h3>";
    $stmt = $pdo->query("SELECT * FROM surveys WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
    $result = $stmt->fetch();
    
    if ($result) {
        echo "<p style='color: green;'>✅ کوئری با موفقیت اجرا شد!</p>";
        echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<strong>نظرسنجی فعال:</strong><br>";
        echo "ID: " . $result['id'] . "<br>";
        echo "عنوان: " . htmlspecialchars($result['title']) . "<br>";
        echo "توضیحات: " . htmlspecialchars($result['description'] ?? '') . "<br>";
        echo "فعال: " . ($result['is_active'] ? 'بله' : 'خیر') . "<br>";
        echo "تاریخ ایجاد: " . $result['created_at'];
        echo "</div>";
    } else {
        echo "<p style='color: orange;'>⚠️ هیچ نظرسنجی فعالی یافت نشد.</p>";
        
        // ایجاد نظرسنجی نمونه
        echo "<p>در حال ایجاد نظرسنجی نمونه...</p>";
        $stmt = $pdo->prepare("INSERT INTO surveys (title, description, is_active) VALUES (?, ?, ?)");
        $stmt->execute(['نظرسنجی رضایت مشتریان', 'نظرسنجی عمومی رضایت مشتریان از خدمات', 1]);
        
        echo "<p style='color: green;'>✅ نظرسنجی نمونه ایجاد شد</p>";
    }
    
    // بررسی تمام نظرسنجی‌ها
    echo "<h3>تمام نظرسنجی‌ها:</h3>";
    $stmt = $pdo->query("SELECT id, title, is_active, created_at FROM surveys ORDER BY id DESC");
    $surveys = $stmt->fetchAll();
    
    if ($surveys) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>عنوان</th><th>فعال</th><th>تاریخ ایجاد</th></tr>";
        
        foreach ($surveys as $survey) {
            echo "<tr>";
            echo "<td>" . $survey['id'] . "</td>";
            echo "<td>" . htmlspecialchars($survey['title']) . "</td>";
            echo "<td>" . ($survey['is_active'] ? 'بله' : 'خیر') . "</td>";
            echo "<td>" . $survey['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'>⚠️ هیچ نظرسنجی‌ای یافت نشد.</p>";
    }
    
    echo "<h3 style='color: green;'>🎉 اصلاح جدول surveys با موفقیت تکمیل شد!</h3>";
    echo "<p><a href='survey.php?customer_id=2' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>تست صفحه نظرسنجی</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ خطا در اتصال به دیتابیس: " . $e->getMessage() . "</p>";
    echo "<p>لطفاً مطمئن شوید که:</p>";
    echo "<ul>";
    echo "<li>سرور MariaDB/MySQL در حال اجرا است</li>";
    echo "<li>نام کاربری و رمز عبور صحیح است</li>";
    echo "<li>دیتابیس '$dbname' وجود دارد</li>";
    echo "</ul>";
}
?>