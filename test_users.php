<?php
session_start();
require_once 'config.php';

echo "<h2>تست کاربران موجود در سیستم</h2>";

try {
    // بررسی اتصال دیتابیس
    echo "<p>✅ اتصال به دیتابیس موفق</p>";
    
    // بررسی جدول users
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        echo "<p>✅ جدول users موجود است</p>";
        
        // نمایش ساختار جدول
        $stmt = $pdo->query("DESCRIBE users");
        $columns = $stmt->fetchAll();
        echo "<h3>ساختار جدول users:</h3>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>" . $column['Field'] . "</td>";
            echo "<td>" . $column['Type'] . "</td>";
            echo "<td>" . $column['Null'] . "</td>";
            echo "<td>" . $column['Key'] . "</td>";
            echo "<td>" . $column['Default'] . "</td>";
            echo "<td>" . $column['Extra'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // نمایش کاربران موجود
        $stmt = $pdo->query("SELECT id, username, full_name, role, department, status FROM users ORDER BY id");
        $users = $stmt->fetchAll();
        
        echo "<h3>کاربران موجود (" . count($users) . " نفر):</h3>";
        if (count($users) > 0) {
            echo "<table border='1' style='border-collapse: collapse;'>";
            echo "<tr><th>ID</th><th>Username</th><th>Full Name</th><th>Role</th><th>Department</th><th>Status</th></tr>";
            foreach ($users as $user) {
                echo "<tr>";
                echo "<td>" . $user['id'] . "</td>";
                echo "<td>" . htmlspecialchars($user['username']) . "</td>";
                echo "<td>" . htmlspecialchars($user['full_name'] ?? '') . "</td>";
                echo "<td>" . htmlspecialchars($user['role'] ?? '') . "</td>";
                echo "<td>" . htmlspecialchars($user['department'] ?? '') . "</td>";
                echo "<td>" . htmlspecialchars($user['status'] ?? '') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>❌ هیچ کاربری در جدول users یافت نشد</p>";
        }
        
    } else {
        echo "<p>❌ جدول users وجود ندارد</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ خطا: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h3>تست کوئری درخواست‌ها:</h3>";

try {
    $stmt = $pdo->query("SELECT id, username, full_name, role, department FROM users WHERE status = 'active' ORDER BY full_name, username");
    $active_users = $stmt->fetchAll();
    
    echo "<p>کاربران فعال (" . count($active_users) . " نفر):</p>";
    if (count($active_users) > 0) {
        echo "<ul>";
        foreach ($active_users as $user) {
            $display_name = $user['full_name'] ?: $user['username'];
            echo "<li>" . htmlspecialchars($display_name) . " (" . htmlspecialchars($user['role'] ?? '') . ")</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>❌ هیچ کاربر فعالی یافت نشد</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ خطا در کوئری کاربران فعال: " . $e->getMessage() . "</p>";
}
?>