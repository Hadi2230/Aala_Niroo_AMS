<?php
session_start();
require_once 'config.php';

// بررسی احراز هویت
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// دریافت customer_id از URL
$customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
$asset_id = isset($_GET['asset_id']) ? (int)$_GET['asset_id'] : 0;

// ایجاد جداول اگر وجود ندارند
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS surveys (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        is_active BOOLEAN DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

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

} catch (Exception $e) {
    $error_message = "خطا در ایجاد جداول: " . $e->getMessage();
}

// دریافت نظرسنجی فعال
$active_survey = null;
$questions = [];

try {
    $stmt = $pdo->query("SELECT * FROM surveys WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
    $active_survey = $stmt->fetch();
    
    if (!$active_survey) {
        // ایجاد نظرسنجی نمونه
        $stmt = $pdo->prepare("INSERT INTO surveys (title, description, is_active) VALUES (?, ?, ?)");
        $stmt->execute([
            'نظرسنجی رضایت مشتریان',
            'نظرسنجی عمومی رضایت مشتریان از خدمات',
            1
        ]);
        $survey_id = $pdo->lastInsertId();
        
        // ایجاد سوالات نمونه
        $sample_questions = [
            ['نام و نام خانوادگی', 'text', 1, 1],
            ['شماره تماس', 'text', 1, 2],
            ['آیا از خدمات ما راضی هستید؟', 'radio', 1, 3],
            ['نظر شما در مورد کیفیت خدمات چیست؟', 'textarea', 0, 4],
            ['امتیاز کلی (1-10)', 'number', 1, 5]
        ];
        
        foreach ($sample_questions as $q) {
            $options = null;
            if ($q[1] === 'radio') {
                $options = json_encode(['بله', 'خیر', 'تا حدودی']);
            }
            
            $stmt = $pdo->prepare("INSERT INTO survey_questions (survey_id, question_text, answer_type, is_required, sort_order, options) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$survey_id, $q[0], $q[1], $q[2], $q[3], $options]);
        }
        
        $active_survey = ['id' => $survey_id, 'title' => 'نظرسنجی رضایت مشتریان', 'description' => 'نظرسنجی عمومی رضایت مشتریان از خدمات'];
    }
    
    // دریافت سوالات
    $stmt = $pdo->prepare("SELECT * FROM survey_questions WHERE survey_id = ? ORDER BY sort_order, id");
    $stmt->execute([$active_survey['id']]);
    $questions = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error_message = "خطا در دریافت نظرسنجی: " . $e->getMessage();
}

// پردازش ارسال فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_survey'])) {
    try {
        // ایجاد ثبت نظرسنجی
        $stmt = $pdo->prepare("INSERT INTO survey_submissions (survey_id, customer_id, asset_id, status, submitted_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $active_survey['id'],
            $customer_id ?: null,
            $asset_id ?: null,
            'completed',
            $_SESSION['user_id']
        ]);
        $submission_id = $pdo->lastInsertId();
        
        // ذخیره پاسخ‌ها
        foreach ($questions as $question) {
            $response_text = $_POST['question_' . $question['id']] ?? '';
            if (!empty($response_text)) {
                $stmt = $pdo->prepare("INSERT INTO survey_responses (submission_id, question_id, response_text) VALUES (?, ?, ?)");
                $stmt->execute([$submission_id, $question['id'], $response_text]);
            }
        }
        
        $success_message = "نظرسنجی با موفقیت ثبت شد!";
        
        // رفرش صفحه
        header("Location: survey_list.php");
        exit();
        
    } catch (Exception $e) {
        $error_message = "خطا در ثبت نظرسنجی: " . $e->getMessage();
    }
}

// دریافت اطلاعات مشتری یا دستگاه
$customer_info = null;
$asset_info = null;

if ($customer_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->execute([$customer_id]);
        $customer_info = $stmt->fetch();
    } catch (Exception $e) {
        // خطا در دریافت اطلاعات مشتری
    }
}

if ($asset_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM assets WHERE id = ?");
        $stmt->execute([$asset_id]);
        $asset_info = $stmt->fetch();
    } catch (Exception $e) {
        // خطا در دریافت اطلاعات دستگاه
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظرسنجی - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <style>
        body { 
            font-family: Vazirmatn, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding-top: 80px;
        }
        .main-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            margin: 20px 0;
        }
        .card { 
            border: none; 
            border-radius: 15px; 
            box-shadow: 0 8px 25px rgba(0,0,0,0.1); 
            margin-bottom: 20px; 
            overflow: hidden;
        }
        .card-header {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white; 
            border-radius: 15px 15px 0 0 !important;
            padding: 20px 25px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 25px;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(44, 62, 80, 0.4);
        }
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s;
        }
        .form-control:focus, .form-select:focus {
            border-color: #2c3e50;
            box-shadow: 0 0 0 0.2rem rgba(44, 62, 80, 0.25);
        }
        .question-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #2c3e50;
        }
        .required {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <?php if (file_exists('navbar.php')) include 'navbar.php'; ?>
    
    <div class="container">
        <div class="main-container">
            <div class="container mt-4">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0"><i class="fas fa-poll me-2"></i><?php echo htmlspecialchars($active_survey['title'] ?? 'نظرسنجی'); ?></h4>
                        <small class="opacity-75"><?php echo htmlspecialchars($active_survey['description'] ?? ''); ?></small>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error_message)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($success_message)): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($customer_info): ?>
                            <div class="alert alert-info">
                                <h6><i class="fas fa-user me-2"></i>اطلاعات مشتری:</h6>
                                <p class="mb-0">
                                    <strong>نام:</strong> <?php echo htmlspecialchars($customer_info['full_name'] ?: $customer_info['company'] ?: '-'); ?><br>
                                    <strong>تلفن:</strong> <?php echo htmlspecialchars($customer_info['phone'] ?: $customer_info['company_phone'] ?: '-'); ?><br>
                                    <strong>آدرس:</strong> <?php echo htmlspecialchars($customer_info['address'] ?: '-'); ?>
                                </p>
                            </div>
                        <?php endif; ?>

                        <?php if ($asset_info): ?>
                            <div class="alert alert-info">
                                <h6><i class="fas fa-cog me-2"></i>اطلاعات دستگاه:</h6>
                                <p class="mb-0">
                                    <strong>نام:</strong> <?php echo htmlspecialchars($asset_info['name'] ?: '-'); ?><br>
                                    <strong>سریال:</strong> <?php echo htmlspecialchars($asset_info['serial_number'] ?: '-'); ?><br>
                                    <strong>مدل:</strong> <?php echo htmlspecialchars($asset_info['model'] ?: '-'); ?>
                                </p>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <?php foreach ($questions as $question): ?>
                                <div class="question-card">
                                    <h6>
                                        <?php echo htmlspecialchars($question['question_text']); ?>
                                        <?php if ($question['is_required']): ?>
                                            <span class="required">*</span>
                                        <?php endif; ?>
                                    </h6>
                                    
                                    <?php if ($question['answer_type'] === 'text'): ?>
                                        <input type="text" class="form-control" name="question_<?php echo $question['id']; ?>" 
                                               <?php echo $question['is_required'] ? 'required' : ''; ?>>
                                    
                                    <?php elseif ($question['answer_type'] === 'textarea'): ?>
                                        <textarea class="form-control" rows="3" name="question_<?php echo $question['id']; ?>" 
                                                  <?php echo $question['is_required'] ? 'required' : ''; ?>></textarea>
                                    
                                    <?php elseif ($question['answer_type'] === 'radio'): ?>
                                        <?php 
                                        $options = json_decode($question['options'] ?? '[]', true);
                                        foreach ($options as $option): 
                                        ?>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="question_<?php echo $question['id']; ?>" 
                                                       value="<?php echo htmlspecialchars($option); ?>" 
                                                       <?php echo $question['is_required'] ? 'required' : ''; ?>>
                                                <label class="form-check-label"><?php echo htmlspecialchars($option); ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    
                                    <?php elseif ($question['answer_type'] === 'number'): ?>
                                        <input type="number" class="form-control" name="question_<?php echo $question['id']; ?>" 
                                               min="1" max="10" 
                                               <?php echo $question['is_required'] ? 'required' : ''; ?>>
                                    
                                    <?php else: ?>
                                        <input type="text" class="form-control" name="question_<?php echo $question['id']; ?>" 
                                               <?php echo $question['is_required'] ? 'required' : ''; ?>>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            
                            <div class="d-flex justify-content-between">
                                <a href="survey_list.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-right me-1"></i>بازگشت
                                </a>
                                <button type="submit" name="submit_survey" class="btn btn-primary">
                                    <i class="fas fa-check me-1"></i>ارسال نظرسنجی
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-hide alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>