<?php
// Debug version of notification_templates.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting debug...<br>";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
    echo "Session started<br>";
}

// Set test session
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['role'] = 'ادمین';

echo "Session set<br>";

try {
    require_once 'config.php';
    echo "Config loaded<br>";
    
    // Test database connection
    $stmt = $pdo->prepare("SELECT 1");
    $stmt->execute();
    echo "Database connected<br>";
    
    // Test hasPermission function
    if (function_exists('hasPermission')) {
        echo "hasPermission function exists<br>";
        if (hasPermission('*')) {
            echo "Admin permission granted<br>";
        } else {
            echo "Admin permission denied<br>";
        }
    } else {
        echo "hasPermission function not found<br>";
    }
    
    // Create notification_templates table
    $create_table = "CREATE TABLE IF NOT EXISTS notification_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type ENUM('email', 'sms') NOT NULL,
        name VARCHAR(255) NOT NULL,
        subject VARCHAR(500) DEFAULT NULL,
        content TEXT NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci";
    
    $pdo->exec($create_table);
    echo "Table created/verified<br>";
    
    // Test query
    $stmt = $pdo->prepare("SELECT * FROM notification_templates ORDER BY type, name");
    $stmt->execute();
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Templates retrieved: " . count($templates) . "<br>";
    
    // Test CSRF
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
    }
    echo "CSRF token generated<br>";
    
    echo "<h2>Debug completed successfully!</h2>";
    echo "<p>All systems are working. The issue might be in the HTML output.</p>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
}
?>