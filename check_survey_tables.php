<?php
require_once 'config.php';

echo "<h2>بررسی جداول نظرسنجی</h2>";

try {
    // بررسی جداول موجود
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "<h3>جداول موجود:</h3>";
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li>$table</li>";
    }
    echo "</ul>";
    
    // بررسی جداول نظرسنجی
    $survey_tables = ['surveys', 'survey_submissions', 'survey_questions', 'survey_responses'];
    
    echo "<h3>وضعیت جداول نظرسنجی:</h3>";
    foreach ($survey_tables as $table) {
        if (in_array($table, $tables)) {
            $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            echo "<p style='color: green;'>✅ $table: موجود ($count رکورد)</p>";
        } else {
            echo "<p style='color: red;'>❌ $table: موجود نیست</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>خطا: " . $e->getMessage() . "</p>";
}
?>