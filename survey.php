<?php
require_once 'config.php';
require_once 'sms.php';

// بررسی احراز هویت
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// بررسی وجود نظرسنجی فعال
$active_survey = null;
$questions = [];

try {
    $stmt = $pdo->prepare("
        SELECT s.*, COUNT(sq.id) as question_count 
        FROM surveys s 
        LEFT JOIN survey_questions sq ON s.id = sq.survey_id 
        WHERE s.id = (SELECT MAX(id) FROM surveys WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY))
        GROUP BY s.id
    ");
    $stmt->execute();
    $active_survey = $stmt->fetch();
    
    if ($active_survey) {
        $stmt = $pdo->prepare("SELECT id, question_text, question_type FROM survey_questions WHERE survey_id = ? ORDER BY id");
        $stmt->execute([$active_survey['id']]);
        $questions = $stmt->fetchAll();
    } else {
        // اگر نظرسنجی فعالی وجود ندارد، یک نظرسنجی نمونه ایجاد کن
        try {
            // ایجاد نظرسنجی نمونه
            $stmt = $pdo->prepare("INSERT INTO surveys (title, description, is_active) VALUES (?, ?, ?)");
            $stmt->execute([
                'نظرسنجی رضایت مشتریان - شرکت اعلا نیرو',
                'این نظرسنجی به منظور ارزیابی کیفیت خدمات و رضایت مشتریان از خدمات شرکت اعلا نیرو طراحی شده است.',
                true
            ]);
            $survey_id = $pdo->lastInsertId();
            
            // ایجاد سوالات نمونه
            $sample_questions = [
                [
                    'question_text' => 'آیا از کیفیت خدمات ارائه شده راضی هستید؟',
                    'question_type' => 'yes_no',
                    'is_required' => true,
                    'order_index' => 1
                ],
                [
                    'question_text' => 'نحوه برخورد کارکنان را چگونه ارزیابی می‌کنید؟',
                    'question_type' => 'rating',
                    'is_required' => true,
                    'order_index' => 2
                ],
                [
                    'question_text' => 'آیا در زمان مقرر خدمات به شما ارائه شده است؟',
                    'question_type' => 'yes_no',
                    'is_required' => true,
                    'order_index' => 3
                ],
                [
                    'question_text' => 'کیفیت تجهیزات و محصولات را چگونه ارزیابی می‌کنید؟',
                    'question_type' => 'rating',
                    'is_required' => true,
                    'order_index' => 4
                ],
                [
                    'question_text' => 'آیا مایل به استفاده مجدد از خدمات شرکت هستید؟',
                    'question_type' => 'yes_no',
                    'is_required' => true,
                    'order_index' => 5
                ],
                [
                    'question_text' => 'نظرات و پیشنهادات خود را در مورد خدمات شرکت بیان کنید:',
                    'question_type' => 'text',
                    'is_required' => false,
                    'order_index' => 6
                ]
            ];
            
            foreach ($sample_questions as $question) {
                $stmt = $pdo->prepare("
                    INSERT INTO survey_questions (survey_id, question_text, question_type, is_required, order_index) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $survey_id,
                    $question['question_text'],
                    $question['question_type'],
                    $question['is_required'],
                    $question['order_index']
                ]);
            }
            
            // دریافت نظرسنجی ایجاد شده
            $stmt = $pdo->prepare("
                SELECT s.*, COUNT(sq.id) as question_count 
                FROM surveys s 
                LEFT JOIN survey_questions sq ON s.id = sq.survey_id 
                WHERE s.id = ?
                GROUP BY s.id
            ");
            $stmt->execute([$survey_id]);
            $active_survey = $stmt->fetch();
            
            $stmt = $pdo->prepare("SELECT id, question_text, question_type FROM survey_questions WHERE survey_id = ? ORDER BY id");
            $stmt->execute([$survey_id]);
            $questions = $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("Sample survey creation error: " . $e->getMessage());
        }
    }
} catch (PDOException $e) {
    error_log("Survey load error: " . $e->getMessage());
    try {
        if ($active_survey) {
            $stmt = $pdo->prepare("SELECT id, question_text, question_type FROM survey_questions WHERE survey_id = ? ORDER BY id");
            $stmt->execute([$active_survey['id']]);
            $questions = $stmt->fetchAll();
        }
    } catch (PDOException $e2) {
        error_log("Alternative survey load error: " . $e2->getMessage());
    }
}

// پردازش ارسال فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_survey'])) {
    verifyCsrfToken();
    
    // بررسی اجباری بودن مشتری
    $customer_id = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
    if (!$customer_id) {
        $_SESSION['error_message'] = "لطفاً یک مشتری انتخاب کنید.";
        header("Location: survey.php");
        exit();
    }
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            INSERT INTO survey_submissions (survey_id, customer_id, asset_id, started_by, status) 
            VALUES (?, ?, ?, ?, 'completed')
        ");
        $asset_id = !empty($_POST['asset_id']) ? (int)$_POST['asset_id'] : null;
        $stmt->execute([$active_survey['id'], $customer_id, $asset_id, $_SESSION['user_id']]);
        $submission_id = $pdo->lastInsertId();
        
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'question_') === 0) {
                $question_id = (int)str_replace('question_', '', $key);
                $ins = $pdo->prepare("
                    INSERT INTO survey_responses 
                    (survey_id, question_id, customer_id, asset_id, response_text, responded_by, submission_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $response_text = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : trim((string)$value);
                $ins->execute([
                    $active_survey['id'], 
                    $question_id, 
                    $customer_id, 
                    $asset_id, 
                    $response_text, 
                    $_SESSION['user_id'],
                    $submission_id
                ]);
            }
        }
        
        $pdo->commit();
        logAction($pdo, 'survey_submission', 'ارسال نظرسنجی با شناسه: ' . $submission_id);
        
        // دریافت اطلاعات مشتری برای نمایش در مودال
        $stmt = $pdo->prepare("
            SELECT 
                id,
                CASE
                    WHEN customer_type='حقوقی' AND COALESCE(company,'')<>'' THEN company
                    WHEN COALESCE(full_name,'')<>'' THEN full_name
                    ELSE COALESCE(company, full_name, CONCAT('مشتری ', id))
                END AS display_name,
                phone, company_phone, responsible_phone
            FROM customers 
            WHERE id = ?
        ");
        $stmt->execute([$customer_id]);
        $customer = $stmt->fetch();
        
        // پیدا کردن شماره تلفن مناسب
        $phone = null;
        if ($customer) {
            $phones = array_filter([
                $customer['phone'] ?? null, 
                $customer['company_phone'] ?? null, 
                $customer['responsible_phone'] ?? null
            ]);
            $phone = !empty($phones) ? $phones[0] : null;
        }
        
        // ایجاد متن پیامک
        $sms_message = "مشتری گرامی {$customer['display_name']},\n";
        $sms_message .= "نظرسنجی شما با موفقیت ثبت شد.\n";
        $sms_message .= "از مشارکت شما در بهبود خدمات شرکت اعلا نیرو سپاسگزاریم.\n";
        $sms_message .= "ارتباط با ما: ۰۲۱-۱۲۳۴۵۶۷۸";
        
        // ذخیره اطلاعات برای نمایش مودال ارسال پیامک
        $_SESSION['survey_completed'] = true;
        $_SESSION['submission_id'] = $submission_id;
        $_SESSION['customer_id'] = $customer_id;
        $_SESSION['customer_name'] = $customer['display_name'] ?? 'مشتری';
        $_SESSION['customer_phone'] = $phone;
        $_SESSION['sms_message'] = $sms_message;
        
        header("Location: survey.php");
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Survey submission error: " . $e->getMessage());
        $_SESSION['error_message'] = "خطا در ثبت نظرسنجی. لطفاً مجدداً تلاش کنید.";
    }
}

// پردازش درخواست ارسال پیامک
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_sms'])) {
    verifyCsrfToken();
    
    $submission_id = $_SESSION['submission_id'] ?? null;
    $customer_id = $_SESSION['customer_id'] ?? null;
    $phone = $_SESSION['customer_phone'] ?? null;
    $sms_message = $_SESSION['sms_message'] ?? null;
    
    if ($submission_id && $customer_id && $phone) {
        try {
            // ارسال پیامک
            $sms_result = send_sms($phone, $sms_message);
            
            if ($sms_result['success']) {
                // ذخیره اطلاعات ارسال پیامک
                $stmt = $pdo->prepare("
                    UPDATE survey_submissions 
                    SET sms_sent = 1, sms_sent_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$submission_id]);
                
                $_SESSION['success_message'] = "نظرسنجی با موفقیت ثبت و پیامک برای مشتری ارسال شد.";
                logAction($pdo, 'sms_sent', 'پیامک برای مشتری ' . $customer_id . ' ارسال شد');
            } else {
                $_SESSION['success_message'] = "نظرسنجی با موفقیت ثبت شد، اما ارسال پیامک با مشکل مواجه شد.";
                logAction($pdo, 'sms_failed', 'خطا در ارسال پیامک برای مشتری ' . $customer_id);
            }
            
        } catch (Exception $e) {
            error_log("SMS processing error: " . $e->getMessage());
            $_SESSION['error_message'] = "خطا در پردازش درخواست ارسال پیامک.";
        }
    } else {
        $_SESSION['success_message'] = "نظرسنجی با موفقیت ثبت شد، اما شماره تلفنی برای ارسال پیامک یافت نشد.";
    }
    
    // پاک کردن session variables
    unset($_SESSION['survey_completed'], $_SESSION['submission_id'], $_SESSION['customer_id'], 
          $_SESSION['customer_name'], $_SESSION['customer_phone'], $_SESSION['sms_message']);
    
    header("Location: survey.php");
    exit();
}

// اگر کاربر مودال را بست بدون اینکه پیامک ارسال کند
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['close_modal'])) {
    verifyCsrfToken();
    unset($_SESSION['survey_completed'], $_SESSION['submission_id'], $_SESSION['customer_id'],
          $_SESSION['customer_name'], $_SESSION['customer_phone'], $_SESSION['sms_message']);
    $_SESSION['success_message'] = "نظرسنجی با موفقیت ثبت شد.";
    header("Location: survey.php");
    exit();
}

// دریافت لیست مشتریان و دارایی‌ها برای dropdownها
$customers = [];
$assets    = [];

try {
    // نمایش نام مشتری: شرکت (حقوقی) یا نام کامل (حقیقی) + تلفن‌ها
    $stmt = $pdo->query("
        SELECT 
            id,
            CASE
                WHEN customer_type='حقوقی' AND COALESCE(company,'')<>'' THEN company
                WHEN COALESCE(full_name,'')<>'' THEN full_name
                ELSE COALESCE(company, full_name, CONCAT('مشتری ', id))
            END AS display_name,
            NULLIF(phone,'')            AS phone,
            NULLIF(company_phone,'')    AS company_phone,
            NULLIF(responsible_phone,'')AS responsible_phone
        FROM customers
        ORDER BY display_name
    ");
    $customers = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Customers load error: " . $e->getMessage());
    try {
        $stmt = $pdo->query("
            SELECT 
                id,
                COALESCE(company, full_name, CONCAT('مشتری ', id)) AS display_name,
                NULL AS phone, NULL AS company_phone, NULL AS responsible_phone
            FROM customers
            ORDER BY display_name
        ");
        $customers = $stmt->fetchAll();
    } catch (Throwable $e2) {}
}

try {
    // دارایی‌های فعال + سازگاری با مقادیر انگلیسی/NULL
    $stmt = $pdo->query("
        SELECT id, name, serial_number 
        FROM assets 
        WHERE status = 'فعال' OR status IS NULL OR status IN ('Active','active','ACTIVE')
        ORDER BY name
    ");
    $assets = $stmt->fetchAll();
    if (!$assets) {
        // اگر چیزی نبود، همه را بیاور
        $stmt = $pdo->query("SELECT id, name, serial_number FROM assets ORDER BY name");
        $assets = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log("Assets load error: " . $e->getMessage());
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
        .modal-content { border-radius:15px; overflow:hidden; }
        .modal-header { background:linear-gradient(135deg,var(--secondary-color) 0%,var(--primary-color) 100%); color:#fff; }
        .btn-sms { padding:10px 25px; border-radius:8px; font-weight:bold; }
        .btn-sms-yes { background:linear-gradient(135deg,#28a745 0%,#20c997 100%); color:#fff; }
        .btn-sms-no { background:linear-gradient(135deg,#6c757d 0%,#adb5bd 100%); color:#fff; }
        .sms-preview { background-color:#f8f9fa; border-radius:10px; padding:15px; margin:15px 0; border:1px dashed #dee2e6; }
        .customer-info { background:linear-gradient(135deg,#e3f2fd 0%,#bbdefb 100%); border-radius:10px; padding:15px; margin:15px 0; }
        @media (max-width:768px){ .survey-body{padding:20px} .rating-stars label{width:35px; height:35px; font-size:20px} }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
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
                
                <?php if ($active_survey): ?>
                    <div class="mb-4">
                        <h4><?php echo htmlspecialchars($active_survey['title'], ENT_QUOTES, 'UTF-8'); ?></h4>
                        <p class="text-muted"><?php echo htmlspecialchars($active_survey['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                        <p class="text-muted"><small>تعداد سوالات: <?php echo (int)$active_survey['question_count']; ?> سوال</small></p>
                    </div>
                    
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
                                        <option value="<?php echo (int)$c['id']; ?>">
                                            <?php echo htmlspecialchars($c['display_name'], ENT_QUOTES, 'UTF-8') . $suffix; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text text-danger">انتخاب مشتری اجباری است</div>
                            </div>
                            <div class="col-md-6">
                                <label for="asset_id" class="form-label">دارایی (اختیاری)</label>
                                <select class="form-select" id="asset_id" name="asset_id">
                                    <option value="">انتخاب دارایی...</option>
                                    <?php foreach ($assets as $a): 
                                        $label = $a['name'] . (empty($a['serial_number']) ? '' : (' - ' . $a['serial_number']));
                                    ?>
                                        <option value="<?php echo (int)$a['id']; ?>">
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
                                    </h5>
                                    
                                    <div class="question-body mt-3">
                                        <?php if ($question['question_type'] === 'yes_no'): ?>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" 
                                                    name="question_<?php echo (int)$question['id']; ?>" 
                                                    id="q<?php echo (int)$question['id']; ?>_yes" 
                                                    value="بله" required>
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
                                                        value="<?php echo $i; ?>" <?php if ($i === 5) echo 'required'; ?>>
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
                                                required></textarea>
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
                        <a href="dashboard.php" class="btn btn-primary mt-3">
                            <i class="bi bi-house-door"></i> بازگشت به داشبورد
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- مودال ارسال پیامک -->
    <?php if (isset($_SESSION['survey_completed']) && $_SESSION['survey_completed']): ?>
    <div class="modal fade show" id="smsModal" tabindex="-1" aria-labelledby="smsModalLabel" style="display: block; padding-right: 17px;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="smsModalLabel"><i class="bi bi-chat-dots-fill"></i> ارسال پیامک به مشتری</h5>
                </div>
                <div class="modal-body py-4">
                    <div class="customer-info">
                        <h6><i class="bi bi-person-circle"></i> اطلاعات مشتری</h6>
                        <p class="mb-1"><strong>نام:</strong> <?php echo htmlspecialchars($_SESSION['customer_name'] ?? 'نامشخص', ENT_QUOTES, 'UTF-8'); ?></p>
                        <p class="mb-0"><strong>شماره تلفن:</strong> <?php echo htmlspecialchars($_SESSION['customer_phone'] ?? 'ثبت نشده', ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                    
                    <div class="sms-preview">
                        <h6><i class="bi bi-chat-text"></i> پیش نمایش پیامک</h6>
                        <p class="mb-0 text-muted"><?php echo nl2br(htmlspecialchars($_SESSION['sms_message'] ?? '', ENT_QUOTES, 'UTF-8')); ?></p>
                    </div>
                    
                    <div class="text-center mt-3">
                        <p class="text-muted">آیا مایل به ارسال این پیامک برای مشتری هستید؟</p>
                    </div>
                    
                    <form method="POST" id="smsForm">
                        <?php csrf_field(); ?>
                        <div class="d-flex justify-content-center gap-3 mt-4">
                            <button type="submit" name="send_sms" value="yes" class="btn btn-sms btn-sms-yes">
                                <i class="bi bi-check-lg"></i> بله، ارسال کن
                            </button>
                            <button type="submit" name="close_modal" class="btn btn-sms btn-sms-no">
                                <i class="bi bi-x-lg"></i> خیر، ارسال نکن
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show"></div>
    <?php endif; ?>

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
            
            // مدیریت مودال
            const smsModal = document.getElementById('smsModal');
            if (smsModal) {
                document.body.classList.add('modal-open');
                document.body.style.overflow = 'hidden';
                document.body.style.paddingRight = '17px';
            }
        });
    </script>
</body>
</html>