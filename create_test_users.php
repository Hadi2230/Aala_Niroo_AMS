<?php
/**
 * create_test_users.php - ایجاد کاربران نمونه برای تست
 */

require_once 'config_simple.php';

echo "<h2>ایجاد کاربران نمونه</h2>";

try {
    // بررسی وجود جدول users
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $user_count = $stmt->fetchColumn();
    echo "تعداد کاربران موجود: $user_count<br><br>";
    
    // ایجاد کاربران نمونه
    $test_users = [
        [
            'username' => 'admin',
            'password' => password_hash('admin123', PASSWORD_DEFAULT),
            'full_name' => 'مدیر سیستم',
            'email' => 'admin@example.com',
            'role' => 'admin',
            'is_active' => 1
        ],
        [
            'username' => 'user1',
            'password' => password_hash('user123', PASSWORD_DEFAULT),
            'full_name' => 'کاربر اول',
            'email' => 'user1@example.com',
            'role' => 'user',
            'is_active' => 1
        ],
        [
            'username' => 'user2',
            'password' => password_hash('user123', PASSWORD_DEFAULT),
            'full_name' => 'کاربر دوم',
            'email' => 'user2@example.com',
            'role' => 'user',
            'is_active' => 1
        ],
        [
            'username' => 'manager',
            'password' => password_hash('manager123', PASSWORD_DEFAULT),
            'full_name' => 'مدیر بخش',
            'email' => 'manager@example.com',
            'role' => 'manager',
            'is_active' => 1
        ]
    ];
    
    foreach ($test_users as $user) {
        // بررسی وجود کاربر
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$user['username']]);
        $existing_user = $stmt->fetch();
        
        if ($existing_user) {
            echo "⚠️ کاربر {$user['username']} قبلاً وجود دارد<br>";
        } else {
            // ایجاد کاربر جدید
            $stmt = $pdo->prepare("
                INSERT INTO users (username, password, full_name, email, role, is_active, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            if ($stmt->execute([
                $user['username'],
                $user['password'],
                $user['full_name'],
                $user['email'],
                $user['role'],
                $user['is_active']
            ])) {
                echo "✅ کاربر {$user['username']} با موفقیت ایجاد شد<br>";
            } else {
                echo "❌ خطا در ایجاد کاربر {$user['username']}<br>";
            }
        }
    }
    
    echo "<br><a href='test_request_workflow.php'>برو به تست سیستم</a>";
    
} catch (Exception $e) {
    echo "❌ خطا: " . $e->getMessage() . "<br>";
}
?>