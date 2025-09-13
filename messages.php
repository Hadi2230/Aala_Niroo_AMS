<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// بررسی دسترسی
if (!hasPermission('messages.send') && !hasPermission('messages.receive')) {
    die('دسترسی غیرمجاز - شما مجوز دسترسی به پیام‌ها را ندارید');
}

// ایجاد جدول messages اگر وجود ندارد (بدون Foreign Key)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        subject VARCHAR(500) DEFAULT '',
        message TEXT NOT NULL,
        attachment_path VARCHAR(500) NULL,
        attachment_name VARCHAR(255) NULL,
        attachment_type VARCHAR(50) NULL,
        is_read BOOLEAN DEFAULT 0,
        read_at TIMESTAMP NULL,
        related_ticket_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(sender_id),
        INDEX(receiver_id),
        INDEX(is_read),
        INDEX(created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {
    // جدول ممکن است قبلاً وجود داشته باشد
}

// پردازش فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'send_message':
                $recipient_id = (int)$_POST['recipient_id'];
                $subject = sanitizeInput($_POST['subject']);
                $message = sanitizeInput($_POST['message']);
                
                if (empty($recipient_id) || empty($subject) || empty($message)) {
                    $error_message = 'لطفاً تمام فیلدها را پر کنید';
                } else {
                    try {
                        // بررسی وجود گیرنده
                        $stmt = $pdo->prepare("SELECT id, username, full_name FROM users WHERE id = ? AND id != ?");
                        $stmt->execute([$recipient_id, $_SESSION['user_id']]);
                        $recipient = $stmt->fetch();
                        
                        if (!$recipient) {
                            $error_message = 'گیرنده انتخاب شده معتبر نیست';
                        } else {
                            // آپلود فایل اگر وجود دارد
                            $attachment_path = null;
                            $attachment_name = null;
                            $attachment_type = null;
                            
                            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                                $upload_dir = 'uploads/messages/';
                                if (!is_dir($upload_dir)) {
                                    mkdir($upload_dir, 0755, true);
                                }
                                
                                $file_info = pathinfo($_FILES['attachment']['name']);
                                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'zip', 'rar'];
                                $file_extension = strtolower($file_info['extension']);
                                
                                if (!in_array($file_extension, $allowed_extensions)) {
                                    $error_message = 'نوع فایل مجاز نیست. فایل‌های مجاز: ' . implode(', ', $allowed_extensions);
                                } elseif ($_FILES['attachment']['size'] > 10 * 1024 * 1024) { // 10MB limit
                                    $error_message = 'حجم فایل نباید بیش از 10 مگابایت باشد';
                                } else {
                                    $file_name = time() . '_' . uniqid() . '.' . $file_extension;
                                    $attachment_path = $upload_dir . $file_name;
                                    
                                    if (move_uploaded_file($_FILES['attachment']['tmp_name'], $attachment_path)) {
                                        $attachment_name = $_FILES['attachment']['name'];
                                        $attachment_type = $file_extension;
                                    } else {
                                        $error_message = 'خطا در آپلود فایل';
                                    }
                                }
                            }
                            
                            if (!isset($error_message)) {
                                // ارسال پیام
                                $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, subject, message, attachment_path, attachment_name, attachment_type, is_read) VALUES (?, ?, ?, ?, ?, ?, ?, 0)");
                                $stmt->execute([$_SESSION['user_id'], $recipient_id, $subject, $message, $attachment_path, $attachment_name, $attachment_type]);
                                
                                if ($stmt->rowCount() > 0) {
                                    $message_id = $pdo->lastInsertId();
                                    $success_message = 'پیام با موفقیت ارسال شد';
                                    if ($attachment_name) {
                                        $success_message .= ' و فایل ضمیمه شد';
                                    }
                                    
                                    // لاگ‌گیری ارسال پیام
                                    logAction($pdo, 'SEND_MESSAGE', "ارسال پیام به کاربر $recipient_id: $subject", 'info', 'messages', [
                                        'recipient_id' => $recipient_id,
                                        'subject' => $subject,
                                        'has_attachment' => !empty($attachment_name),
                                        'attachment_name' => $attachment_name
                                    ]);
                                    
                                    // ارسال اعلان
                                    $notification_text = "پیام جدید از " . ($_SESSION['full_name'] ?? $_SESSION['username']);
                                    if ($attachment_name) {
                                        $notification_text .= " با فایل ضمیمه";
                                    }
                                    sendNotification($pdo, $recipient_id, 'پیام جدید', $notification_text, 'message', 'متوسط', $message_id, 'message');
                                } else {
                                    $error_message = 'خطا در ارسال پیام';
                                    logAction($pdo, 'SEND_MESSAGE_ERROR', "خطا در ارسال پیام به کاربر $recipient_id", 'error', 'messages');
                                }
                            }
                        }
                    } catch (Exception $e) {
                        $error_message = 'خطا در ارسال پیام: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'mark_as_read':
                $message_id = (int)$_POST['message_id'];
                try {
                    $stmt = $pdo->prepare("UPDATE messages SET is_read = 1, read_at = CURRENT_TIMESTAMP WHERE id = ? AND receiver_id = ?");
                    $stmt->execute([$message_id, $_SESSION['user_id']]);
                    $success_message = 'پیام به عنوان خوانده شده علامت‌گذاری شد';
                } catch (Exception $e) {
                    $error_message = 'خطا در به‌روزرسانی پیام';
                }
                break;
                
            case 'delete_message':
                $message_id = (int)$_POST['message_id'];
                try {
                    $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ? AND (sender_id = ? OR receiver_id = ?)");
                    $stmt->execute([$message_id, $_SESSION['user_id'], $_SESSION['user_id']]);
                    $success_message = 'پیام با موفقیت حذف شد';
                } catch (Exception $e) {
                    $error_message = 'خطا در حذف پیام';
                }
                break;
        }
    }
}

// دریافت فیلتر
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

// دریافت کاربران برای ارسال پیام
$users = [];
try {
    $stmt = $pdo->prepare("SELECT id, username, full_name FROM users WHERE id != ? AND is_active = 1 ORDER BY full_name, username");
    $stmt->execute([$_SESSION['user_id']]);
    $users = $stmt->fetchAll();
} catch (Exception $e) {
    $error_message = 'خطا در دریافت لیست کاربران: ' . $e->getMessage();
}

// دریافت پیام‌ها
$messages = [];
try {
    $where_conditions = [];
    $params = [$_SESSION['user_id']];
    
    switch ($filter) {
        case 'sent':
            $where_conditions[] = "m.sender_id = ?";
            break;
        case 'received':
            $where_conditions[] = "m.receiver_id = ?";
            break;
        case 'unread':
            $where_conditions[] = "m.receiver_id = ? AND m.is_read = 0";
            break;
        default: // all
            $where_conditions[] = "(m.sender_id = ? OR m.receiver_id = ?)";
            $params[] = $_SESSION['user_id'];
            break;
    }
    
    if (!empty($search)) {
        $where_conditions[] = "(m.subject LIKE ? OR m.message LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    $stmt = $pdo->prepare("
        SELECT m.*, 
               sender.username as sender_username, sender.full_name as sender_name,
               receiver.username as receiver_username, receiver.full_name as receiver_name
        FROM messages m
        LEFT JOIN users sender ON m.sender_id = sender.id
        LEFT JOIN users receiver ON m.receiver_id = receiver.id
        WHERE $where_clause
        ORDER BY m.created_at DESC
    ");
    $stmt->execute($params);
    $messages = $stmt->fetchAll();
} catch (Exception $e) {
    $error_message = 'خطا در دریافت پیام‌ها: ' . $e->getMessage();
}

// آمار پیام‌ها
$stats = [
    'total' => 0,
    'unread' => 0,
    'sent' => 0,
    'received' => 0
];

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM messages WHERE sender_id = ? OR receiver_id = ?");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $stats['total'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as unread FROM messages WHERE receiver_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['unread'] = $stmt->fetch()['unread'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as sent FROM messages WHERE sender_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['sent'] = $stmt->fetch()['sent'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as received FROM messages WHERE receiver_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['received'] = $stmt->fetch()['received'];
} catch (Exception $e) {
    // خطا در آمار
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پیام‌های داخلی - شرکت اعلا نیرو</title>
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
        .dark-mode { 
            background: linear-gradient(135deg, #1a1a1a 0%, #2d3748 100%) !important; 
            color: #ffffff !important; 
        }
        .main-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            margin: 20px 0;
        }
        .dark-mode .main-container {
            background: rgba(45, 55, 72, 0.95);
        }
        .card { 
            border: none; 
            border-radius: 15px; 
            box-shadow: 0 8px 25px rgba(0,0,0,0.1); 
            margin-bottom: 20px; 
            overflow: hidden;
        }
        .dark-mode .card { 
            background-color: #2d3748; 
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        }
        .card-header { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; 
            border: none;
            padding: 20px 25px;
        }
        .btn-primary { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            border: none; 
            border-radius: 10px; 
            padding: 10px 25px; 
            transition: all 0.3s; 
            font-weight: 500;
        }
        .btn-primary:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4); 
        }
        .message-item { 
            border: 1px solid #e9ecef; 
            border-radius: 15px; 
            margin-bottom: 15px; 
            padding: 20px; 
            transition: all 0.3s; 
            background: white;
            position: relative;
            overflow: hidden;
        }
        .message-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            opacity: 0;
            transition: opacity 0.3s;
        }
        .message-item:hover { 
            box-shadow: 0 10px 30px rgba(0,0,0,0.1); 
            transform: translateY(-2px);
        }
        .message-item:hover::before {
            opacity: 1;
        }
        .message-item.unread { 
            background: linear-gradient(135deg, #f8f9ff 0%, #e8f2ff 100%);
            border-left: 4px solid #667eea;
        }
        .message-item.unread::before {
            opacity: 1;
        }
        .dark-mode .message-item { 
            background-color: #374151; 
            border-color: #4b5563; 
        }
        .dark-mode .message-item.unread { 
            background: linear-gradient(135deg, #2d3748 0%, #1a202c 100%);
        }
        .message-meta { 
            font-size: 0.85rem; 
            color: #6c757d; 
            margin-bottom: 10px;
        }
        .dark-mode .message-meta { 
            color: #9ca3af; 
        }
        .message-content { 
            margin-top: 15px; 
            line-height: 1.8; 
            color: #495057;
        }
        .dark-mode .message-content {
            color: #e2e8f0;
        }
        .stats-card { 
            text-align: center; 
            padding: 25px; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; 
            border-radius: 15px; 
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
        }
        .stats-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: rotate(45deg);
        }
        .stats-number { 
            font-size: 2.5rem; 
            font-weight: bold; 
            margin-bottom: 8px; 
            position: relative;
            z-index: 1;
        }
        .stats-label { 
            font-size: 0.9rem; 
            opacity: 0.9; 
            position: relative;
            z-index: 1;
        }
        .filter-buttons .btn { 
            margin-left: 8px; 
            margin-bottom: 8px; 
            border-radius: 25px;
            padding: 8px 20px;
            font-weight: 500;
            transition: all 0.3s;
        }
        .filter-buttons .btn:hover {
            transform: translateY(-2px);
        }
        .search-box { 
            max-width: 350px; 
            border-radius: 25px;
            border: 2px solid #e9ecef;
            padding: 12px 20px;
            transition: all 0.3s;
        }
        .search-box:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .message-actions { 
            margin-top: 15px; 
        }
        .message-actions .btn { 
            margin-left: 8px; 
            border-radius: 20px;
            padding: 6px 15px;
        }
        .user-option { 
            padding: 12px 15px; 
            border-bottom: 1px solid #e9ecef; 
            cursor: pointer; 
            transition: all 0.2s; 
            border-radius: 8px;
            margin-bottom: 5px;
        }
        .user-option:hover { 
            background: linear-gradient(135deg, #f8f9ff 0%, #e8f2ff 100%);
            transform: translateX(-5px);
        }
        .user-option:last-child { 
            border-bottom: none; 
        }
        .user-name { 
            font-weight: 600; 
            color: #495057;
        }
        .user-username { 
            font-size: 0.85rem; 
            color: #6c757d; 
        }
        .message-subject {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
        }
        .dark-mode .message-subject {
            color: #e2e8f0;
        }
        .message-preview {
            color: #6c757d;
            font-size: 0.9rem;
            line-height: 1.6;
        }
        .dark-mode .message-preview {
            color: #9ca3af;
        }
        .badge {
            border-radius: 20px;
            padding: 6px 12px;
            font-weight: 500;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        .dark-mode .empty-state {
            color: #9ca3af;
        }
        .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 15px 15px 0 0;
        }
        .btn-close {
            filter: invert(1);
        }
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s;
        }
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .alert {
            border-radius: 10px;
            border: none;
            padding: 15px 20px;
        }
        .message-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            margin-left: 15px;
        }
        .file-upload-container {
            position: relative;
        }
        .file-upload-container.drag-over {
            background: linear-gradient(135deg, #f8f9ff 0%, #e8f2ff 100%);
            border: 2px dashed #667eea;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
        }
        .file-info {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 10px;
        }
        .dark-mode .file-info {
            background: #374151;
            border-color: #4b5563;
        }
        .attachment-info {
            background: linear-gradient(135deg, #f8f9ff 0%, #e8f2ff 100%);
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 12px;
        }
        .dark-mode .attachment-info {
            background: linear-gradient(135deg, #2d3748 0%, #1a202c 100%);
            border-color: #4b5563;
        }
        .attachment-info a {
            color: #667eea !important;
            text-decoration: none;
            font-weight: 600;
        }
        .attachment-info a:hover {
            color: #5a67d8 !important;
            text-decoration: underline;
        }
    </style>
</head>
<body class="<?php echo isset($_COOKIE['theme']) && $_COOKIE['theme']==='dark' ? 'dark-mode' : ''; ?>">
    <?php include 'navbar.php'; ?>

    <div class="container-fluid">
        <div class="main-container">
            <div class="container mt-4">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <h4 class="mb-0"><i class="fas fa-envelope me-2"></i>پیام‌های داخلی</h4>
                                    <small class="opacity-75">مدیریت و ارسال پیام‌های داخلی</small>
                                </div>
                                <button class="btn btn-light btn-lg" data-bs-toggle="modal" data-bs-target="#composeModal">
                                    <i class="fas fa-plus me-2"></i>ارسال پیام جدید
                                </button>
                            </div>
                            <div class="card-body">
                                <?php if (isset($success_message)): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>

                                <?php if (isset($error_message)): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>

                                <!-- آمار پیام‌ها -->
                                <div class="row mb-4">
                                    <div class="col-md-3">
                                        <div class="stats-card">
                                            <div class="stats-number"><?php echo $stats['total']; ?></div>
                                            <div class="stats-label">کل پیام‌ها</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="stats-card">
                                            <div class="stats-number"><?php echo $stats['unread']; ?></div>
                                            <div class="stats-label">خوانده نشده</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="stats-card">
                                            <div class="stats-number"><?php echo $stats['sent']; ?></div>
                                            <div class="stats-label">ارسالی</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="stats-card">
                                            <div class="stats-number"><?php echo $stats['received']; ?></div>
                                            <div class="stats-label">دریافتی</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- فیلترها و جستجو -->
                                <div class="row mb-4">
                                    <div class="col-md-8">
                                        <div class="filter-buttons">
                                            <a href="?filter=all" class="btn btn-outline-primary <?php echo $filter == 'all' ? 'active' : ''; ?>">
                                                <i class="fas fa-list me-1"></i>همه
                                            </a>
                                            <a href="?filter=unread" class="btn btn-outline-warning <?php echo $filter == 'unread' ? 'active' : ''; ?>">
                                                <i class="fas fa-envelope me-1"></i>خوانده نشده
                                            </a>
                                            <a href="?filter=sent" class="btn btn-outline-info <?php echo $filter == 'sent' ? 'active' : ''; ?>">
                                                <i class="fas fa-paper-plane me-1"></i>ارسالی
                                            </a>
                                            <a href="?filter=received" class="btn btn-outline-success <?php echo $filter == 'received' ? 'active' : ''; ?>">
                                                <i class="fas fa-inbox me-1"></i>دریافتی
                                            </a>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <form method="GET" class="d-flex">
                                            <input type="hidden" name="filter" value="<?php echo $filter; ?>">
                                            <input type="text" class="form-control search-box" name="search" placeholder="جستجو در پیام‌ها..." value="<?php echo htmlspecialchars($search); ?>">
                                            <button type="submit" class="btn btn-outline-secondary ms-2">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                <!-- لیست پیام‌ها -->
                                <div class="messages-list">
                                    <?php if (empty($messages)): ?>
                                        <div class="empty-state">
                                            <i class="fas fa-envelope-open"></i>
                                            <h5>هیچ پیامی یافت نشد</h5>
                                            <p>هنوز پیامی در این بخش وجود ندارد</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($messages as $message): ?>
                                            <div class="message-item <?php echo !$message['is_read'] && $message['receiver_id'] == $_SESSION['user_id'] ? 'unread' : ''; ?>">
                                                <div class="d-flex align-items-start">
                                                    <div class="message-avatar">
                                                        <?php 
                                                        $name = $message['sender_id'] == $_SESSION['user_id'] ? 
                                                            ($message['receiver_name'] ?? $message['receiver_username']) : 
                                                            ($message['sender_name'] ?? $message['sender_username']);
                                                        echo strtoupper(substr($name, 0, 1));
                                                        ?>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                                            <div>
                                                                <h6 class="mb-1">
                                                                    <?php if ($message['sender_id'] == $_SESSION['user_id']): ?>
                                                                        <i class="fas fa-paper-plane text-info me-2"></i>
                                                                        به: <strong><?php echo htmlspecialchars($message['receiver_name'] ?? $message['receiver_username']); ?></strong>
                                                                    <?php else: ?>
                                                                        <i class="fas fa-inbox text-success me-2"></i>
                                                                        از: <strong><?php echo htmlspecialchars($message['sender_name'] ?? $message['sender_username']); ?></strong>
                                                                    <?php endif; ?>
                                                                </h6>
                                                                <div class="message-subject"><?php echo htmlspecialchars($message['subject']); ?></div>
                                                            </div>
                                                            <div class="text-end">
                                                                <div class="message-meta">
                                                                    <i class="fas fa-clock me-1"></i><?php echo jalali_format($message['created_at']); ?>
                                                                    <?php if (!$message['is_read'] && $message['receiver_id'] == $_SESSION['user_id']): ?>
                                                                        <span class="badge bg-warning ms-2">جدید</span>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="message-preview">
                                                            <?php echo nl2br(htmlspecialchars(substr($message['message'], 0, 200))); ?>
                                                            <?php if (strlen($message['message']) > 200): ?>...<?php endif; ?>
                                                        </div>
                                                        
                                                        <?php if (!empty($message['attachment_name'])): ?>
                                                            <div class="attachment-info mt-3">
                                                                <div class="d-flex align-items-center">
                                                                    <i class="fas fa-paperclip text-muted me-2"></i>
                                                                    <span class="text-muted me-2">فایل ضمیمه:</span>
                                                                    <a href="<?php echo htmlspecialchars($message['attachment_path']); ?>" 
                                                                       target="_blank" 
                                                                       class="text-primary text-decoration-none fw-bold">
                                                                        <i class="fas fa-download me-1"></i>
                                                                        <?php echo htmlspecialchars($message['attachment_name']); ?>
                                                                    </a>
                                                                    <span class="badge bg-secondary ms-2">
                                                                        <?php echo strtoupper($message['attachment_type'] ?? 'فایل'); ?>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div class="message-actions">
                                                            <?php if (!$message['is_read'] && $message['receiver_id'] == $_SESSION['user_id']): ?>
                                                                <form method="POST" style="display: inline;">
                                                                    <input type="hidden" name="action" value="mark_as_read">
                                                                    <input type="hidden" name="message_id" value="<?php echo $message['id']; ?>">
                                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                                    <button type="submit" class="btn btn-sm btn-success">
                                                                        <i class="fas fa-check me-1"></i>خوانده شد
                                                                    </button>
                                                                </form>
                                                            <?php endif; ?>
                                                            <form method="POST" style="display: inline;" onsubmit="return confirm('آیا مطمئن هستید؟')">
                                                                <input type="hidden" name="action" value="delete_message">
                                                                <input type="hidden" name="message_id" value="<?php echo $message['id']; ?>">
                                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                    <i class="fas fa-trash me-1"></i>حذف
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal ارسال پیام جدید -->
    <div class="modal fade" id="composeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form class="modal-content" method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-paper-plane me-2"></i>ارسال پیام جدید</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="send_message">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold">گیرنده <span class="text-danger">*</span></label>
                        <select class="form-select" name="recipient_id" required>
                            <option value="">انتخاب گیرنده...</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php 
                                    $display_name = $user['full_name'] ?: $user['username'];
                                    echo htmlspecialchars($display_name);
                                    ?>
                                    (<?php echo htmlspecialchars($user['username']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($users)): ?>
                            <div class="text-muted small mt-2">
                                <i class="fas fa-info-circle me-1"></i>هیچ کاربر فعالی برای ارسال پیام وجود ندارد
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold">موضوع <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="subject" required placeholder="موضوع پیام را وارد کنید...">
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold">متن پیام <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="message" rows="6" required placeholder="متن پیام خود را بنویسید..."></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold">فایل ضمیمه (اختیاری)</label>
                        <div class="file-upload-container">
                            <input type="file" class="form-control" name="attachment" id="attachmentInput" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.txt,.zip,.rar">
                            <div class="file-info mt-2" id="fileInfo" style="display: none;">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-file me-2 text-primary"></i>
                                    <span class="file-name"></span>
                                    <span class="file-size text-muted ms-2"></span>
                                    <button type="button" class="btn btn-sm btn-outline-danger ms-2" id="removeFile">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="text-muted small mt-2">
                                <i class="fas fa-info-circle me-1"></i>
                                فایل‌های مجاز: JPG, PNG, GIF, PDF, DOC, DOCX, TXT, ZIP, RAR (حداکثر 10 مگابایت)
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>انصراف
                    </button>
                    <button type="submit" class="btn btn-primary" <?php echo empty($users) ? 'disabled' : ''; ?>>
                        <i class="fas fa-paper-plane me-1"></i>ارسال پیام
                    </button>
                </div>
            </form>
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

        // Form validation
        document.getElementById('composeModal').addEventListener('show.bs.modal', function() {
            const form = this.querySelector('form');
            form.reset();
        });

        // Smooth scrolling
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });

        // File upload handling
        const attachmentInput = document.getElementById('attachmentInput');
        const fileInfo = document.getElementById('fileInfo');
        const fileName = fileInfo.querySelector('.file-name');
        const fileSize = fileInfo.querySelector('.file-size');
        const removeFileBtn = document.getElementById('removeFile');

        attachmentInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Check file size (10MB limit)
                if (file.size > 10 * 1024 * 1024) {
                    alert('حجم فایل نباید بیش از 10 مگابایت باشد');
                    this.value = '';
                    return;
                }

                // Check file type
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf', 
                                    'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                    'text/plain', 'application/zip', 'application/x-rar-compressed'];
                
                if (!allowedTypes.includes(file.type)) {
                    alert('نوع فایل مجاز نیست. فایل‌های مجاز: JPG, PNG, GIF, PDF, DOC, DOCX, TXT, ZIP, RAR');
                    this.value = '';
                    return;
                }

                // Show file info
                fileName.textContent = file.name;
                fileSize.textContent = formatFileSize(file.size);
                fileInfo.style.display = 'block';
            }
        });

        removeFileBtn.addEventListener('click', function() {
            attachmentInput.value = '';
            fileInfo.style.display = 'none';
        });

        // Format file size
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Drag and drop functionality
        const fileUploadContainer = document.querySelector('.file-upload-container');
        
        fileUploadContainer.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('drag-over');
        });

        fileUploadContainer.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('drag-over');
        });

        fileUploadContainer.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('drag-over');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                attachmentInput.files = files;
                attachmentInput.dispatchEvent(new Event('change'));
            }
        });
    </script>
</body>
</html>