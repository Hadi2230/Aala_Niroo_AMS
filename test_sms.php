<?php
session_start();
require_once 'config.php';
require_once 'sms.php';

// بررسی احراز هویت
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$result = null;
$error_message = '';
$success_message = '';

// پردازش فرم ارسال پیامک
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_sms'])) {
    $phone = $_POST['phone'] ?? '';
    $message = $_POST['message'] ?? '';
    $test_mode = isset($_POST['test_mode']);
    
    if (empty($phone) || empty($message)) {
        $error_message = 'لطفاً شماره تلفن و متن پیامک را وارد کنید';
    } else {
        try {
            if ($test_mode) {
                // استفاده از حالت تست
                $result = send_sms_mock($phone, $message);
            } else {
                // استفاده از API واقعی
                $result = send_sms($phone, $message);
            }
            
            if ($result['success']) {
                $success_message = 'پیامک با موفقیت ارسال شد!';
                
                // ثبت در لاگ
                logAction($pdo, 'SEND_SMS', "ارسال پیامک به $phone: " . substr($message, 0, 50) . "...", 'info', 'sms', [
                    'phone' => $phone,
                    'message_length' => strlen($message),
                    'test_mode' => $test_mode,
                    'message_id' => $result['message_id'] ?? null
                ]);
            } else {
                $error_message = 'خطا در ارسال پیامک: ' . $result['error'];
                
                // ثبت خطا در لاگ
                logAction($pdo, 'SEND_SMS_ERROR', "خطا در ارسال پیامک به $phone: " . $result['error'], 'error', 'sms', [
                    'phone' => $phone,
                    'error' => $result['error'],
                    'test_mode' => $test_mode
                ]);
            }
        } catch (Exception $e) {
            $error_message = 'خطای سیستمی: ' . $e->getMessage();
        }
    }
}

// دریافت تاریخچه پیامک‌ها
try {
    $stmt = $pdo->query("SELECT * FROM sms_logs ORDER BY created_at DESC LIMIT 20");
    $sms_logs = $stmt->fetchAll();
} catch (Exception $e) {
    $sms_logs = [];
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تست پیامک - شرکت اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <style>
        body {
            font-family: Vazirmatn, sans-serif;
            background-color: #f8f9fa;
            padding-top: 80px;
        }
        .navbar {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            font-weight: 600;
        }
        .btn-primary {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
        }
        .btn-success {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            border: none;
            border-radius: 10px;
        }
        .btn-warning {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            border: none;
            border-radius: 10px;
        }
        .form-control {
            border-radius: 10px;
            border: 2px solid #ecf0f1;
            padding: 12px 15px;
        }
        .form-control:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }
        .result-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin: 15px 0;
        }
        .log-item {
            border-bottom: 1px solid #eee;
            padding: 10px 0;
        }
        .log-item:last-child {
            border-bottom: none;
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 4px 8px;
            border-radius: 15px;
        }
    </style>
</head>
<body>
    <!-- نوار ناوبری -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-arrow-right"></i> بازگشت به داشبورد
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?>
                </span>
                <a class="nav-link" href="logout.php">
                    <i class="fas fa-sign-out-alt"></i> خروج
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8">
                <!-- فرم تست پیامک -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-sms"></i> تست ارسال پیامک</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($success_message): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">شماره تلفن گیرنده</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                        <input type="text" class="form-control" id="phone" name="phone" 
                                               placeholder="09123456789" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
                                    </div>
                                    <small class="text-muted">شماره را با فرمت 09123456789 وارد کنید</small>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">حالت تست</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="test_mode" id="test_mode" 
                                               <?php echo isset($_POST['test_mode']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="test_mode">
                                            استفاده از حالت تست (بدون ارسال واقعی)
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="message" class="form-label">متن پیامک</label>
                                <textarea class="form-control" id="message" name="message" rows="4" 
                                          placeholder="متن پیامک خود را اینجا وارد کنید..." required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                                <small class="text-muted">حداکثر 160 کاراکتر</small>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="submit" name="send_sms" class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i> ارسال پیامک
                                </button>
                            </div>
                        </form>
                        
                        <?php if ($result): ?>
                            <div class="result-box">
                                <h6><i class="fas fa-info-circle"></i> نتیجه ارسال:</h6>
                                <pre style="background: #fff; padding: 10px; border-radius: 5px; font-size: 12px;"><?php echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></pre>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <!-- اطلاعات پنل -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-info-circle"></i> اطلاعات پنل پیامک</h6>
                    </div>
                    <div class="card-body">
                        <p><strong>API Key:</strong> <code><?php echo substr('OWZlNDE1MjctZWViMi00ZjM2LThjMDItMTAyMTc3NTI3OGFiOWFlNjkzYzIzYTk0OTRhNzVjNzIzMWRlZTY4MTE1Yzc=', 0, 20); ?>...</code></p>
                        <p><strong>شماره خط:</strong> <code>+985000125475</code></p>
                        <p><strong>API URL:</strong> <code>https://ippanel.com/developers/api-keys</code></p>
                        
                        <hr>
                        
                        <h6>نحوه استفاده:</h6>
                        <ol class="small">
                            <li>شماره تلفن را با فرمت صحیح وارد کنید</li>
                            <li>متن پیامک را بنویسید</li>
                            <li>برای تست اولیه، حالت تست را فعال کنید</li>
                            <li>دکمه ارسال را کلیک کنید</li>
                        </ol>
                    </div>
                </div>
                
                <!-- آمار -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-chart-bar"></i> آمار پیامک‌ها</h6>
                    </div>
                    <div class="card-body">
                        <?php
                        $total_sms = count($sms_logs);
                        $successful_sms = count(array_filter($sms_logs, function($log) {
                            return $log['status'] === 'sent';
                        }));
                        ?>
                        <div class="row text-center">
                            <div class="col-6">
                                <h4 class="text-primary"><?php echo $total_sms; ?></h4>
                                <small>کل پیامک‌ها</small>
                            </div>
                            <div class="col-6">
                                <h4 class="text-success"><?php echo $successful_sms; ?></h4>
                                <small>ارسال موفق</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- تاریخچه پیامک‌ها -->
        <?php if ($sms_logs): ?>
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-history"></i> تاریخچه پیامک‌ها</h6>
                    </div>
                    <div class="card-body">
                        <?php foreach ($sms_logs as $log): ?>
                            <div class="log-item">
                                <div class="row align-items-center">
                                    <div class="col-md-2">
                                        <span class="badge status-badge bg-<?php echo $log['status'] === 'sent' ? 'success' : ($log['status'] === 'delivered' ? 'info' : 'danger'); ?>">
                                            <?php echo $log['status'] === 'sent' ? 'ارسال شده' : ($log['status'] === 'delivered' ? 'تحویل شده' : 'ناموفق'); ?>
                                        </span>
                                    </div>
                                    <div class="col-md-3">
                                        <strong><?php echo htmlspecialchars($log['phone']); ?></strong>
                                    </div>
                                    <div class="col-md-4">
                                        <small><?php echo htmlspecialchars(substr($log['message'], 0, 50)) . (strlen($log['message']) > 50 ? '...' : ''); ?></small>
                                    </div>
                                    <div class="col-md-3 text-end">
                                        <small class="text-muted"><?php echo date('Y/m/d H:i', strtotime($log['created_at'])); ?></small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // محدود کردن تعداد کاراکترهای پیامک
        document.getElementById('message').addEventListener('input', function() {
            const maxLength = 160;
            const currentLength = this.value.length;
            const remaining = maxLength - currentLength;
            
            if (remaining < 0) {
                this.value = this.value.substring(0, maxLength);
            }
        });
        
        // فرمت کردن شماره تلفن
        document.getElementById('phone').addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            if (value.length > 0 && !value.startsWith('0')) {
                value = '0' + value;
            }
            this.value = value;
        });
    </script>
</body>
</html>