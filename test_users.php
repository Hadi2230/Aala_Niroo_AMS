<?php
/**
 * test_users.php - تست کاربران موجود در سیستم
 */

session_start();
require_once 'config_complete.php';

echo "<h2>تست کاربران موجود در سیستم</h2>";

try {
    // دریافت کاربران
    $users = getUsersForAssignment($pdo);
    
    echo "<h3>تعداد کاربران: " . count($users) . "</h3>";
    
    if (empty($users)) {
        echo "<p style='color: red;'>هیچ کاربری در سیستم یافت نشد!</p>";
        
        // ایجاد کاربر نمونه
        echo "<h4>ایجاد کاربر نمونه...</h4>";
        
        $sample_users = [
            ['username' => 'admin', 'password' => 'admin123', 'full_name' => 'مدیر سیستم', 'role' => 'admin'],
            ['username' => 'user1', 'password' => 'user123', 'full_name' => 'کاربر اول', 'role' => 'user'],
            ['username' => 'user2', 'password' => 'user123', 'full_name' => 'کاربر دوم', 'role' => 'user'],
            ['username' => 'manager1', 'password' => 'manager123', 'full_name' => 'مدیر بخش', 'role' => 'manager']
        ];
        
        foreach ($sample_users as $user_data) {
            $hashed_password = password_hash($user_data['password'], PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                INSERT INTO users (username, password, full_name, role, is_active) 
                VALUES (?, ?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE 
                password = VALUES(password),
                full_name = VALUES(full_name),
                role = VALUES(role),
                is_active = 1
            ");
            
            $stmt->execute([
                $user_data['username'],
                $hashed_password,
                $user_data['full_name'],
                $user_data['role']
            ]);
            
            echo "<p>کاربر '{$user_data['full_name']}' ایجاد شد.</p>";
        }
        
        // دریافت مجدد کاربران
        $users = getUsersForAssignment($pdo);
    }
    
    echo "<h3>لیست کاربران:</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>نام کاربری</th><th>نام کامل</th><th>نقش</th><th>وضعیت</th></tr>";
    
    foreach ($users as $user) {
        echo "<tr>";
        echo "<td>{$user['id']}</td>";
        echo "<td>{$user['username']}</td>";
        echo "<td>{$user['full_name']}</td>";
        echo "<td>{$user['role']}</td>";
        echo "<td>" . ($user['is_active'] ? 'فعال' : 'غیرفعال') . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>خطا: " . $e->getMessage() . "</p>";
}
?>