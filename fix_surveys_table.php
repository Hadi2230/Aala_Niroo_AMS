<?php
// اسکریپت اصلاح جدول surveys
session_start();
include 'config.php';

echo "<h2>اصلاح جدول surveys</h2>";

try {
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
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Default</th></tr>";
    
    $stmt = $pdo->query("SHOW COLUMNS FROM surveys");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td>" . ($column['Null'] === 'YES' ? 'Yes' : 'No') . "</td>";
        echo "<td>" . htmlspecialchars($column['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // تست کوئری
    echo "<h3>تست کوئری:</h3>";
    $stmt = $pdo->query("SELECT * FROM surveys WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
    $result = $stmt->fetch();
    
    if ($result) {
        echo "<p style='color: green;'>✅ کوئری با موفقیت اجرا شد!</p>";
        echo "<pre>" . print_r($result, true) . "</pre>";
    } else {
        echo "<p style='color: orange;'>⚠️ هیچ نظرسنجی فعالی یافت نشد.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ خطا: " . $e->getMessage() . "</p>";
}

echo "<p><a href='survey.php'>بازگشت به صفحه نظرسنجی</a></p>";
?>