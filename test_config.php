<?php
// Test script for config.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing config.php...\n";

try {
    require_once 'config.php';
    echo "✓ config.php loaded successfully\n";
    
    // Test database connection
    if (isset($pdo)) {
        echo "✓ Database connection established\n";
        
        // Test a simple query
        $stmt = $pdo->query("SELECT 1 as test");
        $result = $stmt->fetch();
        if ($result && $result['test'] == 1) {
            echo "✓ Database query test passed\n";
        } else {
            echo "✗ Database query test failed\n";
        }
    } else {
        echo "✗ Database connection not established\n";
    }
    
    // Test functions
    if (function_exists('hasPermission')) {
        echo "✓ hasPermission function exists\n";
    } else {
        echo "✗ hasPermission function missing\n";
    }
    
    if (function_exists('logAction')) {
        echo "✓ logAction function exists\n";
    } else {
        echo "✗ logAction function missing\n";
    }
    
    if (function_exists('verifyCsrfToken')) {
        echo "✓ verifyCsrfToken function exists\n";
    } else {
        echo "✗ verifyCsrfToken function missing\n";
    }
    
    if (function_exists('createTicket')) {
        echo "✓ createTicket function exists\n";
    } else {
        echo "✗ createTicket function missing\n";
    }
    
    if (function_exists('logToolHistory')) {
        echo "✓ logToolHistory function exists\n";
    } else {
        echo "✗ logToolHistory function missing\n";
    }
    
    echo "\nConfig.php test completed successfully!\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
} catch (Error $e) {
    echo "✗ Fatal Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
?>