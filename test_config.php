<?php
// فعال کردن نمایش خطاها
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>تست config.php</h1>";

try {
    include 'config.php';
    echo "<p>✅ config.php بارگذاری شد</p>";
    
    if (isset($pdo)) {
        echo "<p>✅ متغیر PDO موجود است</p>";
    } else {
        echo "<p>❌ متغیر PDO موجود نیست</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ خطا در config.php: " . $e->getMessage() . "</p>";
    echo "<p>فایل: " . $e->getFile() . "</p>";
    echo "<p>خط: " . $e->getLine() . "</p>";
    echo "<p>جزئیات: " . $e->getTraceAsString() . "</p>";
}
?>