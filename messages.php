<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// بررسی دسترسی
checkPermission('کاربر عادی');

// دریافت اعلان‌های خوانده نشده
$unread_notifications = getUnreadNotifications($pdo, $_SESSION['user_id']);
$unread_messages = getUnreadMessages($pdo, $_SESSION['user_id']);

// پردازش فرم‌ها
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'send_message':
                $receiver_id = sanitizeInput($_POST['receiver_id']);
                $subject = sanitizeInput($_POST['subject']);
                $message = sanitizeInput($_POST['message']);
                $related_ticket_id = sanitizeInput($_POST['related_ticket_id']);
                $related_maintenance_id = sanitizeInput($_POST['related_maintenance_id']);
                
                if ($receiver_id && $message) {
                    $message_id = sendInternalMessage($pdo, $_SESSION['user_id'], $receiver_id, $subject, $message, $related_ticket_id, $related_maintenance_id);
                    logAction($pdo, 'send_message', "پیام جدید با شناسه {$message_id} ارسال شد");
                    $success_message = "پیام با موفقیت ارسال شد";
                } else {
                    $error_message = "لطفاً گیرنده و متن پیام را مشخص کنید";
                }
                break;
                
            case 'mark_as_read':
                $message_id = sanitizeInput($_POST['message_id']);
                
                $stmt = $pdo->prepare("UPDATE messages SET is_read = true, read_at = CURRENT_TIMESTAMP WHERE id = ? AND receiver_id = ?");
                if ($stmt->execute([$message_id, $_SESSION['user_id']])) {
                    logAction($pdo, 'mark_message_read', "پیام {$message_id} به عنوان خوانده شده علامت‌گذاری شد");
                    $success_message = "پیام به عنوان خوانده شده علامت‌گذاری شد";
                } else {
                    $error_message = "خطا در به‌روزرسانی پیام";
                }
                break;
        }
    }
}

// دریافت پیام‌ها
$filter = isset($_GET['filter']) ? sanitizeInput($_GET['filter']) : 'all';
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

$where_conditions = [];
$params = [];

if ($filter === 'sent') {
    $where_conditions[] = "m.sender_id = ?";
    $params[] = $_SESSION['user_id'];
} elseif ($filter === 'received') {
    $where_conditions[] = "m.receiver_id = ?";
    $params[] = $_SESSION['user_id'];
} else {
    $where_conditions[] = "(m.sender_id = ? OR m.receiver_id = ?)";
    $params[] = $_SESSION['user_id'];
    $params[] = $_SESSION['user_id'];
}

if ($search) {
    $where_conditions[] = "(m.subject LIKE ? OR m.message LIKE ? OR u1.full_name LIKE ? OR u2.full_name LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";

$messages_query = "
    SELECT m.*, 
           u1.full_name as sender_name, 
           u2.full_name as receiver_name,
           t.ticket_number,
           ms.maintenance_type
    FROM messages m
    LEFT JOIN users u1 ON m.sender_id = u1.id
    LEFT JOIN users u2 ON m.receiver_id = u2.id
    LEFT JOIN tickets t ON m.related_ticket_id = t.id
    LEFT JOIN maintenance_schedules ms ON m.related_maintenance_id = ms.id
    {$where_clause}
    ORDER BY m.created_at DESC
";

$stmt = $pdo->prepare($messages_query);
$stmt->execute($params);
$messages = $stmt->fetchAll();

// دریافت کاربران برای فرم
$users = $pdo->query("SELECT id, full_name FROM users WHERE is_active = 1 AND id != ? ORDER BY full_name")->fetchAll([$_SESSION['user_id']]);

// دریافت تیکت‌ها و تعمیرات برای فرم
$tickets = $pdo->query("SELECT id, ticket_number, title FROM tickets ORDER BY created_at DESC LIMIT 20")->fetchAll();
$maintenance = $pdo->query("SELECT id, maintenance_type, schedule_date FROM maintenance_schedules ORDER BY created_at DESC LIMIT 20")->fetchAll();
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
        body { font-family: Vazirmatn, sans-serif; background-color: #f8f9fa; padding-top: 80px; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .card-header { background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); color: white; border-radius: 12px 12px 0 0 !important; font-weight: 600; }
        .btn-primary { background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); border: none; }
        .message-item { border-left: 4px solid #3498db; }
        .message-item.unread { background-color: #f8f9ff; border-left-color: #8b5cf6; }
        .message-item.sent { border-left-color: #10b981; }
        .message-item.received { border-left-color: #3498db; }
        .message-preview { max-height: 60px; overflow: hidden; text-overflow: ellipsis; }
        .related-badge { font-size: 0.7rem; padding: 2px 6px; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fa fa-envelope"></i> پیام‌های داخلی</h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#sendMessageModal">
                        <i class="fa fa-plus"></i> پیام جدید
                    </button>
                </div>

                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- فیلترها -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <input type="text" class="form-control" name="search" placeholder="جستجو در پیام‌ها..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="filter">
                                    <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>همه پیام‌ها</option>
                                    <option value="received" <?php echo $filter === 'received' ? 'selected' : ''; ?>>دریافتی</option>
                                    <option value="sent" <?php echo $filter === 'sent' ? 'selected' : ''; ?>>ارسالی</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-outline-primary w-100">فیلتر</button>
                            </div>
                            <div class="col-md-3">
                                <div class="d-flex gap-2">
                                    <span class="badge bg-info"><?php echo count($messages); ?> پیام</span>
                                    <span class="badge bg-warning"><?php echo count($unread_messages); ?> خوانده نشده</span>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- لیست پیام‌ها -->
                <div class="card">
                    <div class="card-header">
                        <i class="fa fa-list"></i> لیست پیام‌ها
                    </div>
                    <div class="card-body">
                        <?php if ($messages): ?>
                            <div class="list-group">
                                <?php foreach ($messages as $msg): ?>
                                    <?php
                                    $is_sent = $msg['sender_id'] == $_SESSION['user_id'];
                                    $is_unread = !$msg['is_read'] && !$is_sent;
                                    $item_class = 'message-item ' . ($is_sent ? 'sent' : 'received') . ($is_unread ? ' unread' : '');
                                    ?>
                                    <div class="list-group-item <?php echo $item_class; ?>">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <h6 class="mb-0">
                                                        <?php if ($is_sent): ?>
                                                            <i class="fa fa-paper-plane text-success me-1"></i>
                                                            به: <?php echo htmlspecialchars($msg['receiver_name']); ?>
                                                        <?php else: ?>
                                                            <i class="fa fa-inbox text-primary me-1"></i>
                                                            از: <?php echo htmlspecialchars($msg['sender_name']); ?>
                                                            <?php if ($is_unread): ?>
                                                                <span class="badge bg-danger ms-2">جدید</span>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </h6>
                                                    <div>
                                                        <?php if ($msg['related_ticket_id']): ?>
                                                            <span class="badge related-badge bg-info">
                                                                <i class="fa fa-ticket-alt"></i> <?php echo htmlspecialchars($msg['ticket_number']); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                        <?php if ($msg['related_maintenance_id']): ?>
                                                            <span class="badge related-badge bg-warning">
                                                                <i class="fa fa-tools"></i> <?php echo htmlspecialchars($msg['maintenance_type']); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                
                                                <?php if ($msg['subject']): ?>
                                                    <h6 class="mb-1 text-primary"><?php echo htmlspecialchars($msg['subject']); ?></h6>
                                                <?php endif; ?>
                                                
                                                <p class="message-preview text-muted mb-2"><?php echo htmlspecialchars($msg['message']); ?></p>
                                                
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <small class="text-muted">
                                                        <i class="fa fa-clock"></i> <?php echo jalaliDate($msg['created_at']); ?>
                                                        <?php if ($msg['read_at']): ?>
                                                            | <i class="fa fa-check-circle text-success"></i> خوانده شده: <?php echo jalaliDate($msg['read_at']); ?>
                                                        <?php endif; ?>
                                                    </small>
                                                    
                                                    <div class="btn-group btn-group-sm">
                                                        <?php if (!$is_sent && !$msg['is_read']): ?>
                                                            <form method="post" style="display: inline;">
                                                                <input type="hidden" name="action" value="mark_as_read">
                                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                                <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                                                <button type="submit" class="btn btn-outline-success btn-sm">
                                                                    <i class="fa fa-check"></i> خوانده شد
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fa fa-envelope fa-3x text-muted mb-3"></i>
                                <p class="text-muted">هیچ پیامی یافت نشد.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal ارسال پیام جدید -->
    <div class="modal fade" id="sendMessageModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form class="modal-content" method="post">
                <div class="modal-header">
                    <h5 class="modal-title">ارسال پیام جدید</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="send_message">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">گیرنده *</label>
                            <select class="form-select" name="receiver_id" required>
                                <option value="">انتخاب گیرنده</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['full_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">موضوع</label>
                            <input type="text" class="form-control" name="subject" placeholder="موضوع پیام (اختیاری)">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">مرتبط با تیکت</label>
                            <select class="form-select" name="related_ticket_id">
                                <option value="">انتخاب تیکت (اختیاری)</option>
                                <?php foreach ($tickets as $ticket): ?>
                                    <option value="<?php echo $ticket['id']; ?>"><?php echo htmlspecialchars($ticket['ticket_number'] . ' - ' . $ticket['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">مرتبط با تعمیرات</label>
                            <select class="form-select" name="related_maintenance_id">
                                <option value="">انتخاب تعمیرات (اختیاری)</option>
                                <?php foreach ($maintenance as $maint): ?>
                                    <option value="<?php echo $maint['id']; ?>"><?php echo htmlspecialchars($maint['maintenance_type'] . ' - ' . jalaliDate($maint['schedule_date'])); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">متن پیام *</label>
                        <textarea class="form-control" name="message" rows="5" required placeholder="متن پیام خود را بنویسید..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" class="btn btn-primary">ارسال پیام</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>