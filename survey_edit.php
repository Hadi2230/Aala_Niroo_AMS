<?php
session_start();
require_once 'config.php';

// بررسی احراز هویت
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$submission_id = isset($_GET['submission_id']) ? (int)$_GET['submission_id'] : 0;

if (!$submission_id) {
    $_SESSION['error_message'] = "شناسه نظرسنجی نامعتبر است.";
    header("Location: survey_list.php");
    exit();
}

// دریافت اطلاعات نظرسنجی
$submission_info = null;
$survey_info = null;
$customer_info = null;
$asset_info = null;
$questions = [];
$responses = [];

try {
    // دریافت اطلاعات submission
    $stmt = $pdo->prepare("
        SELECT s.*, su.title as survey_title, su.description as survey_description,
               c.full_name, c.company, c.phone, c.company_phone, c.customer_type,
               a.name as asset_name, a.serial_number as asset_serial,
               u.full_name as submitted_by_name
        FROM survey_submissions s
        LEFT JOIN surveys su ON s.survey_id = su.id
        LEFT JOIN customers c ON s.customer_id = c.id
        LEFT JOIN assets a ON s.asset_id = a.id
        LEFT JOIN users u ON s.submitted_by = u.id
        WHERE s.id = ?
    ");
    $stmt->execute([$submission_id]);
    $submission_info = $stmt->fetch();
    
    if (!$submission_info) {
        $_SESSION['error_message'] = "نظرسنجی یافت نشد.";
        header("Location: survey_list.php");
        exit();
    }
    
    // دریافت سوالات
    $stmt = $pdo->prepare("SELECT * FROM survey_questions WHERE survey_id = ? ORDER BY order_index, id");
    $stmt->execute([$submission_info['survey_id']]);
    $questions = $stmt->fetchAll();
    
    // دریافت پاسخ‌ها
    $stmt = $pdo->prepare("SELECT * FROM survey_responses WHERE submission_id = ?");
    $stmt->execute([$submission_id]);
    $responses = $stmt->fetchAll();
    
    // تبدیل پاسخ‌ها به آرایه برای دسترسی آسان
    $responses_array = [];
    foreach ($responses as $response) {
        $responses_array[$response['question_id']] = $response;
    }
    
} catch (Exception $e) {
    $_SESSION['error_message'] = "خطا در دریافت اطلاعات: " . $e->getMessage();
    header("Location: survey_list.php");
    exit();
}

// پردازش ویرایش
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_survey'])) {
    try {
        verifyCsrfToken();
        
        $pdo->beginTransaction();
        
        // حذف پاسخ‌های قبلی
        $stmt = $pdo->prepare("DELETE FROM survey_responses WHERE submission_id = ?");
        $stmt->execute([$submission_id]);
        
        // ذخیره پاسخ‌های جدید
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
        
        // به‌روزرسانی تاریخ ویرایش
        $stmt = $pdo->prepare("UPDATE survey_submissions SET updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$submission_id]);
        
        $pdo->commit();
        
        // لاگ‌گیری
        if (function_exists('logAction')) {
            logAction($pdo, 'survey_edit', 'ویرایش نظرسنجی با شناسه: ' . $submission_id);
        }
        
        $_SESSION['success_message'] = "نظرسنجی با موفقیت ویرایش شد!";
        header("Location: survey_list.php");
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "خطا در ویرایش نظرسنجی: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ویرایش نظرسنجی - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <style>
        :root { --primary-color:#3498db; --secondary-color:#2c3e50; --accent-color:#e74c3c; --light-bg:#f8f9fa; --dark-bg:#343a40; }
        body { font-family: Vazirmatn, sans-serif; background-color:#f5f7f9; padding-top:80px; color:#333; }
        .survey-container { max-width:900px; margin:0 auto; background:#fff; border-radius:15px; box-shadow:0 5px 25px rgba(0,0,0,.1); overflow:hidden; }
        .survey-header { background:linear-gradient(135deg,var(--secondary-color) 0%,var(--primary-color) 100%); color:#fff; padding:25px; text-align:center; }
        .survey-body { padding:30px; }
        .info-card { background:linear-gradient(135deg,#e3f2fd 0%,#bbdefb 100%); border-radius:10px; padding:20px; margin:20px 0; }
        .info-row { display:flex; justify-content:space-between; align-items:center; margin:10px 0; padding:8px 0; border-bottom:1px solid rgba(0,0,0,0.1); }
        .info-label { font-weight:bold; color:#2c3e50; }
        .info-value { color:#34495e; }
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
        .character-counter { font-size:.85rem; color:#6c757d; text-align:left; }
        @media (max-width:768px){ .survey-body{padding:20px} .rating-stars label{width:35px; height:35px; font-size:20px} .info-row{flex-direction:column; align-items:flex-start;} }
    </style>
</head>
<body>
    <?php if (file_exists('navbar.php')) include 'navbar.php'; ?>
    
    <div class="container mb-5">
        <div class="survey-container">
            <div class="survey-header">
                <h2><i class="bi bi-pencil-square"></i> ویرایش نظرسنجی</h2>
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
                
                <!-- اطلاعات نظرسنجی -->
                <div class="info-card">
                    <h5><i class="bi bi-info-circle"></i> اطلاعات نظرسنجی</h5>
                    
                    <div class="info-row">
                        <span class="info-label">شناسه نظرسنجی:</span>
                        <span class="info-value">#<?php echo $submission_id; ?></span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">عنوان نظرسنجی:</span>
                        <span class="info-value"><?php echo htmlspecialchars($submission_info['survey_title'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">مشتری:</span>
                        <span class="info-value">
                            <?php 
                            $customer_name = '';
                            if ($submission_info['customer_type'] === 'حقوقی' && !empty($submission_info['company'])) {
                                $customer_name = $submission_info['company'];
                            } elseif (!empty($submission_info['full_name'])) {
                                $customer_name = $submission_info['full_name'];
                            } else {
                                $customer_name = 'مشتری #' . $submission_info['customer_id'];
                            }
                            echo htmlspecialchars($customer_name, ENT_QUOTES, 'UTF-8');
                            ?>
                        </span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">تلفن مشتری:</span>
                        <span class="info-value"><?php echo htmlspecialchars($submission_info['phone'] ?: $submission_info['company_phone'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    
                    <?php if ($submission_info['asset_name']): ?>
                    <div class="info-row">
                        <span class="info-label">دستگاه:</span>
                        <span class="info-value"><?php echo htmlspecialchars($submission_info['asset_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">سریال دستگاه:</span>
                        <span class="info-value"><?php echo htmlspecialchars($submission_info['asset_serial'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="info-row">
                        <span class="info-label">تاریخ ثبت:</span>
                        <span class="info-value"><?php echo jalali_format($submission_info['created_at']); ?></span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">ثبت کننده:</span>
                        <span class="info-value"><?php echo htmlspecialchars($submission_info['submitted_by_name'] ?: 'نامشخص', ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">وضعیت:</span>
                        <span class="info-value">
                            <?php
                            $status_text = '';
                            switch ($submission_info['status']) {
                                case 'completed':
                                    $status_text = 'تکمیل شده';
                                    break;
                                case 'draft':
                                    $status_text = 'پیش‌نویس';
                                    break;
                                case 'pending':
                                    $status_text = 'در انتظار';
                                    break;
                                default:
                                    $status_text = 'نامشخص';
                            }
                            echo $status_text;
                            ?>
                        </span>
                    </div>
                </div>
                
                <!-- فرم ویرایش -->
                <form method="POST" id="surveyForm">
                    <?php csrf_field(); ?>
                    
                    <div class="questions-container">
                        <?php foreach ($questions as $index => $question): 
                            $response = $responses_array[$question['id']] ?? null;
                            $current_value = $response ? $response['response_text'] : '';
                        ?>
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
                                                value="بله" <?php echo ($current_value === 'بله') ? 'checked' : ''; ?>
                                                <?php echo $question['is_required'] ? 'required' : ''; ?>>
                                            <label class="form-check-label" for="q<?php echo (int)$question['id']; ?>_yes">بله</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" 
                                                name="question_<?php echo (int)$question['id']; ?>" 
                                                id="q<?php echo (int)$question['id']; ?>_no" 
                                                value="خیر" <?php echo ($current_value === 'خیر') ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="q<?php echo (int)$question['id']; ?>_no">خیر</label>
                                        </div>
                                        
                                    <?php elseif ($question['question_type'] === 'rating'): ?>
                                        <div class="rating-stars">
                                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                                <input type="radio" 
                                                    name="question_<?php echo (int)$question['id']; ?>" 
                                                    id="q<?php echo (int)$question['id']; ?>_star<?php echo $i; ?>" 
                                                    value="<?php echo $i; ?>" 
                                                    <?php echo ($current_value == $i) ? 'checked' : ''; ?>
                                                    <?php echo $question['is_required'] ? 'required' : ''; ?>>
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
                                            <?php echo $question['is_required'] ? 'required' : ''; ?>><?php echo htmlspecialchars($current_value, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                        <div class="character-counter">
                                            <span id="charCounter<?php echo (int)$question['id']; ?>"><?php echo mb_strlen($current_value); ?></span> کاراکتر
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="text-center mt-4">
                        <button type="submit" name="update_survey" class="btn btn-submit">
                            <i class="bi bi-check-circle-fill"></i> ذخیره تغییرات
                        </button>
                        <a href="survey_list.php" class="btn btn-secondary ms-3">
                            <i class="bi bi-arrow-right"></i> بازگشت
                        </a>
                    </div>
                </form>
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