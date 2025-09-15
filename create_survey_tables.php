<?php
require_once 'config.php';

echo "<h2>ایجاد جداول نظرسنجی</h2>";

try {
    // ایجاد جدول surveys
    $pdo->exec("CREATE TABLE IF NOT EXISTS surveys (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        is_active BOOLEAN DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<p style='color: green;'>✅ جدول surveys ایجاد شد</p>";

    // ایجاد جدول survey_questions
    $pdo->exec("CREATE TABLE IF NOT EXISTS survey_questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        survey_id INT NOT NULL,
        question_text TEXT NOT NULL,
        answer_type ENUM('text', 'textarea', 'radio', 'checkbox', 'select', 'number', 'date') DEFAULT 'text',
        options JSON NULL,
        is_required BOOLEAN DEFAULT 1,
        sort_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<p style='color: green;'>✅ جدول survey_questions ایجاد شد</p>";

    // ایجاد جدول survey_submissions
    $pdo->exec("CREATE TABLE IF NOT EXISTS survey_submissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        survey_id INT NOT NULL,
        customer_id INT NULL,
        asset_id INT NULL,
        status ENUM('draft', 'completed', 'pending') DEFAULT 'draft',
        submitted_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE,
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
        FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE SET NULL,
        FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<p style='color: green;'>✅ جدول survey_submissions ایجاد شد</p>";

    // ایجاد جدول survey_responses
    $pdo->exec("CREATE TABLE IF NOT EXISTS survey_responses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        submission_id INT NOT NULL,
        question_id INT NOT NULL,
        response_text TEXT,
        response_data JSON NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (submission_id) REFERENCES survey_submissions(id) ON DELETE CASCADE,
        FOREIGN KEY (question_id) REFERENCES survey_questions(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<p style='color: green;'>✅ جدول survey_responses ایجاد شد</p>";

    // اضافه کردن نظرسنجی نمونه
    $stmt = $pdo->prepare("INSERT IGNORE INTO surveys (id, title, description) VALUES (1, 'نظرسنجی رضایت مشتری', 'نظرسنجی عمومی رضایت مشتریان از خدمات')");
    $stmt->execute();
    echo "<p style='color: blue;'>ℹ️ نظرسنجی نمونه اضافه شد</p>";

    // اضافه کردن سوالات نمونه
    $sample_questions = [
        [1, 'نام و نام خانوادگی', 'text', 1, 1],
        [1, 'شماره تماس', 'text', 1, 2],
        [1, 'آیا از خدمات ما راضی هستید؟', 'radio', 1, 3],
        [1, 'نظر شما در مورد کیفیت خدمات چیست؟', 'textarea', 0, 4],
        [1, 'امتیاز کلی (1-10)', 'number', 1, 5]
    ];

    foreach ($sample_questions as $q) {
        $options = null;
        if ($q[2] === 'radio') {
            $options = json_encode(['بله', 'خیر', 'تا حدودی']);
        }
        
        $stmt = $pdo->prepare("INSERT IGNORE INTO survey_questions (survey_id, question_text, answer_type, is_required, sort_order, options) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$q[0], $q[1], $q[2], $q[3], $q[4], $options]);
    }
    echo "<p style='color: blue;'>ℹ️ سوالات نمونه اضافه شدند</p>";

    echo "<h3 style='color: green;'>✅ همه جداول با موفقیت ایجاد شدند!</h3>";
    echo "<p><a href='survey_list.php'>برو به لیست نظرسنجی‌ها</a></p>";

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ خطا: " . $e->getMessage() . "</p>";
}
?>