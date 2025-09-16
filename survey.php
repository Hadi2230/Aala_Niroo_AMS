<?php
session_start();
require_once 'config.php';

// بررسی احراز هویت
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// تعریف توابع مورد نیاز
function verifyCsrfToken() {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])) {
        throw new Exception('CSRF token mismatch');
    }
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        throw new Exception('CSRF token mismatch');
    }
}

function csrf_field() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    echo '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
}

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
        question_type ENUM('text', 'textarea', 'radio', 'checkbox', 'select', 'number', 'date', 'yes_no', 'rating') DEFAULT 'text',
        is_required BOOLEAN DEFAULT 1,
        order_index INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS survey_submissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        survey_id INT NOT NULL,
        customer_id INT,
        asset_id INT,
        status ENUM('draft', 'completed', 'pending') DEFAULT 'draft',
        submitted_by INT,
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
        response_data JSON,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (submission_id) REFERENCES survey_submissions(id) ON DELETE CASCADE,
        FOREIGN KEY (question_id) REFERENCES survey_questions(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

} catch (Exception $e) {
    $error_message = "خطا در ایجاد جداول: " . $e->getMessage();
}

// دریافت customer_id از URL
$customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
$asset_id = isset($_GET['asset_id']) ? (int)$_GET['asset_id'] : 0;

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
            'نظرسنجی رضایت مشتریان - شرکت اعلا نیرو',
            'این نظرسنجی به منظور ارزیابی کیفیت خدمات و رضایت مشتریان از خدمات شرکت اعلا نیرو طراحی شده است.',
            1
        ]);
        $survey_id = $pdo->lastInsertId();
        
        // ایجاد سوالات نمونه
        $sample_questions = [
            ['آیا از کیفیت خدمات ارائه شده راضی هستید؟', 'yes_no', 1, 1],
            ['نحوه برخورد کارکنان را چگونه ارزیابی می‌کنید؟', 'rating', 1, 2],
            ['آیا در زمان مقرر خدمات به شما ارائه شده است؟', 'yes_no', 1, 3],
            ['کیفیت تجهیزات و محصولات را چگونه ارزیابی می‌کنید؟', 'rating', 1, 4],
            ['آیا مایل به استفاده مجدد از خدمات شرکت هستید؟', 'yes_no', 1, 5],
            ['نظرات و پیشنهادات خود را در مورد خدمات شرکت بیان کنید:', 'textarea', 0, 6]
        ];
        
        foreach ($sample_questions as $q) {
            $stmt = $pdo->prepare("INSERT INTO survey_questions (survey_id, question_text, question_type, is_required, order_index) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$survey_id, $q[0], $q[1], $q[2], $q[3]]);
        }
        
        $active_survey = ['id' => $survey_id, 'title' => 'نظرسنجی رضایت مشتریان - شرکت اعلا نیرو', 'description' => 'این نظرسنجی به منظور ارزیابی کیفیت خدمات و رضایت مشتریان از خدمات شرکت اعلا نیرو طراحی شده است.'];
    }
    
    // دریافت سوالات
    $stmt = $pdo->prepare("SELECT * FROM survey_questions WHERE survey_id = ? ORDER BY order_index, id");
    $stmt->execute([$active_survey['id']]);
    $questions = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error_message = "خطا در دریافت نظرسنجی: " . $e->getMessage();
}

// پردازش ارسال فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_survey'])) {
    try {
        verifyCsrfToken();
        
        $customer_id = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
        if (!$customer_id) {
            $_SESSION['error_message'] = "لطفاً یک مشتری انتخاب کنید.";
            header("Location: survey_fixed.php");
            exit();
        }
        
        $pdo->beginTransaction();
        
        // ایجاد ثبت نظرسنجی
        $stmt = $pdo->prepare("INSERT INTO survey_submissions (survey_id, customer_id, asset_id, status, submitted_by) VALUES (?, ?, ?, ?, ?)");
        $asset_id = !empty($_POST['asset_id']) ? (int)$_POST['asset_id'] : null;
        $stmt->execute([$active_survey['id'], $customer_id, $asset_id, 'completed', $_SESSION['user_id']]);
        $submission_id = $pdo->lastInsertId();
        
        // ذخیره پاسخ‌ها
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'question_') === 0) {
                $question_id = (int)str_replace('question_', '', $key);
                $response_text = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : trim((string)$value);
                
                if (!empty($response_text)) {
                    $stmt = $pdo->prepare("INSERT INTO survey_responses (submission_id, question_id, response_text) VALUES (?, ?, ?)");
                    $stmt->execute([$submission_id, $question_id, $response_text]);
                }
            }
        }
        
        $pdo->commit();
        
        // لاگ‌گیری
        if (function_exists('logAction')) {
            logAction($pdo, 'survey_submission', 'ارسال نظرسنجی با شناسه: ' . $submission_id);
        }
        
        $_SESSION['success_message'] = "نظرسنجی با موفقیت ثبت شد!";
        header("Location: survey_list.php");
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "خطا در ثبت نظرسنجی: " . $e->getMessage();
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

// دریافت لیست مشتریان و دارایی‌ها
$customers = [];
$assets = [];

try {
    $stmt = $pdo->query("
        SELECT 
            id,
            CASE
                WHEN customer_type='حقوقی' AND COALESCE(company,'')<>'' THEN company
                WHEN COALESCE(full_name,'')<>'' THEN full_name
                ELSE COALESCE(company, full_name, CONCAT('مشتری ', id))
            END AS display_name,
            phone, company_phone, responsible_phone
        FROM customers
        ORDER BY display_name
    ");
    $customers = $stmt->fetchAll();
} catch (Exception $e) {
    $customers = [];
}

try {
    $stmt = $pdo->query("SELECT id, name, serial_number FROM assets ORDER BY name");
    $assets = $stmt->fetchAll();
} catch (Exception $e) {
    $assets = [];
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظرسنجی - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <style>
        :root { --primary-color:#3498db; --secondary-color:#2c3e50; --accent-color:#e74c3c; --light-bg:#f8f9fa; --dark-bg:#343a40; }
        body { font-family: Vazirmatn, sans-serif; background-color:#f5f7f9; padding-top:80px; color:#333; }
        .survey-container { max-width:800px; margin:0 auto; background:#fff; border-radius:15px; box-shadow:0 5px 25px rgba(0,0,0,.1); overflow:hidden; }
        .survey-header { background:linear-gradient(135deg,var(--secondary-color) 0%,var(--primary-color) 100%); color:#fff; padding:25px; text-align:center; }
        .survey-body { padding:30px; }
        .question-card { background:#fff; border:1px solid #e0e0e0; border-radius:10px; padding:20px; margin-bottom:20px; box-shadow:0 2px 10px rgba(0,0,0,.05); transition:all .3s ease; }
        .question-card:hover { box-shadow:0 5px 15px rgba(0,0,0,.1); transform:translateY(-2px); }
        .question-number { display:inline-block; width:30px; height:30px; background:var(--primary-color); color:#fff; text-align:center; line-height:30px; border-radius:50%; margin-left:10px; }
        .rating-stars { display:flex; justify-content:center; margin:15px 0; direction:ltr; }
        .rating-stars input { display:none; }
        .rating-stars label { cursor:pointer; width:40px; height:40px; margin:0 2px; color:#ddd; font-size:24px; display:flex; align-items:center; justify-content:center; transition:color .2s; }
        .rating-stars label:hover, .rating-stars input:checked ~ label { color:#f39c12; }
        .btn-submit { background:linear-gradient(135deg,var(--primary-color) 0%,var(--secondary-color) 100%); border:none; padding:12px 30px; font-size:18px; font-weight:bold; border-radius:50px; transition:all .3s ease; box-shadow:0 4px 15px rgba(0,0,0,.2); }
        .btn-submit:hover { transform:translateY(-3px); box-shadow:0 7px 20px rgba(0,0,0,.3); }
        .form-select { border-radius:8px; padding:10px 15px; border:1px solid #ced4da; transition:all .3s; }
        .form-select:focus { border-color:var(--primary-color); box-shadow:0 0 0 .25rem rgba(52,152,219,.25); }
        .alert-survey { border-radius:10px; padding:15px 20px; margin-bottom:20px; }
        .no-survey { text-align:center; padding:40px 20px; }
        .no-survey i { font-size:5rem; color:#ddd; margin-bottom:20px; }
        .character-counter { font-size:.85rem; color:#6c757d; text-align:left; }
        .customer-info { background:linear-gradient(135deg,#e3f2fd 0%,#bbdefb 100%); border-radius:10px; padding:15px; margin:15px 0; }
        @media (max-width:768px){ .survey-body{padding:20px} .rating-stars label{width:35px; height:35px; font-size:20px} }
    </style>
</head>
<body>
    <?php if (file_exists('navbar.php')) include 'navbar.php'; ?>
    
    <div class="container mb-5">
        <div class="survey-container">
            <div class="survey-header">
                <h2><i class="bi bi-clipboard-check"></i> سامانه نظرسنجی</h2>
                <p class="mb-0">شرکت اعلا نیرو</p>
            </div>
            
            <div class="survey-body">
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-survey">
                        <i class="bi bi-check-circle-fill"></i>
                        <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-survey">
                        <i class="bi bi-exclamation-circle-fill"></i>
                        <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-survey">
                        <i class="bi bi-exclamation-circle-fill"></i>
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($active_survey): ?>
                    <div class="mb-4">
                        <h4><?php echo htmlspecialchars($active_survey['title'], ENT_QUOTES, 'UTF-8'); ?></h4>
                        <p class="text-muted"><?php echo htmlspecialchars($active_survey['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                    
                    <?php if ($customer_info): ?>
                        <div class="customer-info">
                            <h6><i class="bi bi-person-circle"></i> اطلاعات مشتری</h6>
                            <p class="mb-1"><strong>نام:</strong> <?php echo htmlspecialchars($customer_info['full_name'] ?: $customer_info['company'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="mb-1"><strong>تلفن:</strong> <?php echo htmlspecialchars($customer_info['phone'] ?: $customer_info['company_phone'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="mb-0"><strong>آدرس:</strong> <?php echo htmlspecialchars($customer_info['address'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="surveyForm">
                        <?php csrf_field(); ?>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="customer_id" class="form-label">مشتری <span class="text-danger">*</span></label>
                                <select class="form-select" id="customer_id" name="customer_id" required>
                                    <option value="">انتخاب مشتری...</option>
                                    <?php foreach ($customers as $c): 
                                        $phones = array_filter([$c['phone'] ?? null, $c['company_phone'] ?? null, $c['responsible_phone'] ?? null]);
                                        $suffix = $phones ? (' — ' . htmlspecialchars(implode(' | ', $phones), ENT_QUOTES, 'UTF-8')) : '';
                                    ?>
                                        <option value="<?php echo (int)$c['id']; ?>" <?php echo ($customer_id == $c['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($c['display_name'], ENT_QUOTES, 'UTF-8') . $suffix; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="asset_id" class="form-label">دارایی (اختیاری)</label>
                                <select class="form-select" id="asset_id" name="asset_id">
                                    <option value="">انتخاب دارایی...</option>
                                    <?php foreach ($assets as $a): 
                                        $label = $a['name'] . (empty($a['serial_number']) ? '' : (' - ' . $a['serial_number']));
                                    ?>
                                        <option value="<?php echo (int)$a['id']; ?>" <?php echo ($asset_id == $a['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="questions-container">
                            <?php foreach ($questions as $index => $question): ?>
                                <div class="question-card">
                                    <h5>
                                        <span class="question-number"><?php echo $index + 1; ?></span>
                                        <?php echo htmlspecialchars($question['question_text'], ENT_QUOTES, 'UTF-8'); ?>
                                        <?php if ($question['is_required']): ?>
                                            <span class="text-danger">*</span>
                                        <?php endif; ?>
                                    </h5>
                                    
                                    <div class="question-body mt-3">
                                        <?php if ($question['question_type'] === 'yes_no'): ?>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" 
                                                    name="question_<?php echo (int)$question['id']; ?>" 
                                                    id="q<?php echo (int)$question['id']; ?>_yes" 
                                                    value="بله" <?php echo $question['is_required'] ? 'required' : ''; ?>>
                                                <label class="form-check-label" for="q<?php echo (int)$question['id']; ?>_yes">بله</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" 
                                                    name="question_<?php echo (int)$question['id']; ?>" 
                                                    id="q<?php echo (int)$question['id']; ?>_no" 
                                                    value="خیر">
                                                <label class="form-check-label" for="q<?php echo (int)$question['id']; ?>_no">خیر</label>
                                            </div>
                                            
                                        <?php elseif ($question['question_type'] === 'rating'): ?>
                                            <div class="rating-stars">
                                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                                    <input type="radio" 
                                                        name="question_<?php echo (int)$question['id']; ?>" 
                                                        id="q<?php echo (int)$question['id']; ?>_star<?php echo $i; ?>" 
                                                        value="<?php echo $i; ?>" <?php echo $question['is_required'] ? 'required' : ''; ?>>
                                                    <label for="q<?php echo (int)$question['id']; ?>_star<?php echo $i; ?>">
                                                        <i class="bi bi-star-fill"></i>
                                                    </label>
                                                <?php endfor; ?>
                                            </div>
                                            <div class="text-center mt-2">
                                                <small class="text-muted">(1: بسیار ضعیف - 5: عالی)</small>
                                            </div>
                                            
                                        <?php else: ?>
                                            <textarea class="form-control" 
                                                name="question_<?php echo (int)$question['id']; ?>" 
                                                rows="3" 
                                                placeholder="پاسخ خود را وارد کنید..." 
                                                oninput="countChars(this, 'charCounter<?php echo (int)$question['id']; ?>')"
                                                <?php echo $question['is_required'] ? 'required' : ''; ?>></textarea>
                                            <div class="character-counter">
                                                <span id="charCounter<?php echo (int)$question['id']; ?>">0</span> کاراکتر
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="text-center mt-4">
                            <button type="submit" name="submit_survey" class="btn btn-submit">
                                <i class="bi bi-send-fill"></i> ارسال نظرسنجی
                            </button>
                        </div>
                    </form>
                    
                <?php else: ?>
                    <div class="no-survey">
                        <i class="bi bi-clipboard-x"></i>
                        <h4>نظرسنجی فعالی موجود نیست</h4>
                        <p class="text-muted">در حال حاضر هیچ نظرسنجی فعالی برای شرکت وجود ندارد.</p>
                        <a href="survey_list.php" class="btn btn-primary mt-3">
                            <i class="bi bi-house-door"></i> بازگشت به لیست
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function countChars(textarea, counterId) {
            const counter = document.getElementById(counterId);
            if (counter) {
                counter.textContent = textarea.value.length;
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('surveyForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    let valid = true;
                    const customerSelect = document.getElementById('customer_id');
                    
                    // بررسی انتخاب مشتری
                    if (!customerSelect.value) {
                        valid = false;
                        customerSelect.classList.add('is-invalid');
                    } else {
                        customerSelect.classList.remove('is-invalid');
                    }
                    
                    // بررسی سوالات اجباری
                    const requiredInputs = form.querySelectorAll('input[required], textarea[required]');
                    requiredInputs.forEach(input => {
                        if (input.type === 'radio') {
                            const group = form.querySelectorAll('input[name="'+input.name+'"]');
                            const anyChecked = Array.from(group).some(r => r.checked);
                            if (!anyChecked) valid = false;
                        } else {
                            if (!input.value.trim()) valid = false;
                        }
                    });
                    
                    if (!valid) {
                        e.preventDefault();
                        alert('لطفاً فیلدهای اجباری را تکمیل کنید.');
                    }
                });
            }
        });
    </script>
</body>
</html>