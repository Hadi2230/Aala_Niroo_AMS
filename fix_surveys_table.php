<?php
// اسکریپت اصلاح جدول surveys
session_start();
include 'config.php';

echo "<h2>اصلاح جدول surveys</h2>";

try {
    // بررسی وجود ستون is_active
    $stmt = $pdo->query("PRAGMA table_info(surveys)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $has_is_active = false;
    foreach ($columns as $column) {
        if ($column['name'] === 'is_active') {
            $has_is_active = true;
            break;
        }
    }
    
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
    echo "<tr><th>Column</th><th>Type</th><th>Not Null</th><th>Default</th></tr>";
    
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($column['name']) . "</td>";
        echo "<td>" . htmlspecialchars($column['type']) . "</td>";
        echo "<td>" . ($column['notnull'] ? 'Yes' : 'No') . "</td>";
        echo "<td>" . htmlspecialchars($column['dflt_value'] ?? 'NULL') . "</td>";
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