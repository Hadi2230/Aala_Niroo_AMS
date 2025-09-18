<?php
/**
 * get_request_details.php - دریافت جزئیات درخواست
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

require_once 'config_simple.php';

$request_id = (int)$_GET['id'];

try {
    // دریافت جزئیات درخواست
    $stmt = $pdo->prepare("
        SELECT r.*, u.full_name as requester_full_name
        FROM requests r
        LEFT JOIN users u ON r.requester_id = u.id
        WHERE r.id = ?
    ");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch();
    
    if (!$request) {
        echo '<div class="alert alert-danger">درخواست یافت نشد</div>';
        exit;
    }
    
    // دریافت فایل‌های درخواست
    $stmt = $pdo->prepare("SELECT * FROM request_files WHERE request_id = ?");
    $stmt->execute([$request_id]);
    $files = $stmt->fetchAll();
    
    // دریافت گردش کار
    $stmt = $pdo->prepare("
        SELECT rw.*, u.full_name as assigned_user_name
        FROM request_workflow rw
        LEFT JOIN users u ON rw.assigned_to = u.id
        WHERE rw.request_id = ?
        ORDER BY rw.step_order, rw.created_at
    ");
    $stmt->execute([$request_id]);
    $workflow = $stmt->fetchAll();
    
    ?>
    <div class="row">
        <div class="col-md-6">
            <h6 class="text-primary mb-3">
                <i class="fas fa-info-circle me-2"></i>
                اطلاعات کلی
            </h6>
            <table class="table table-borderless">
                <tr>
                    <td><strong>شماره درخواست:</strong></td>
                    <td><?php echo htmlspecialchars($request['request_number']); ?></td>
                </tr>
                <tr>
                    <td><strong>درخواست‌دهنده:</strong></td>
                    <td><?php echo htmlspecialchars($request['requester_full_name'] ?: $request['requester_name']); ?></td>
                </tr>
                <tr>
                    <td><strong>نام آیتم:</strong></td>
                    <td><?php echo htmlspecialchars($request['item_name']); ?></td>
                </tr>
                <tr>
                    <td><strong>تعداد:</strong></td>
                    <td><?php echo $request['quantity']; ?></td>
                </tr>
                <tr>
                    <td><strong>قیمت:</strong></td>
                    <td><?php echo number_format($request['price']); ?> ریال</td>
                </tr>
                <tr>
                    <td><strong>اولویت:</strong></td>
                    <td>
                        <span class="badge priority-<?php echo strtolower($request['priority']); ?>">
                            <?php echo $request['priority']; ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <td><strong>وضعیت:</strong></td>
                    <td>
                        <span class="badge status-<?php echo str_replace(' ', '-', strtolower($request['status'])); ?>">
                            <?php echo $request['status']; ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <td><strong>تاریخ ایجاد:</strong></td>
                    <td><?php echo date('Y/m/d H:i', strtotime($request['created_at'])); ?></td>
                </tr>
            </table>
        </div>
        
        <div class="col-md-6">
            <h6 class="text-primary mb-3">
                <i class="fas fa-paperclip me-2"></i>
                فایل‌های ضمیمه
            </h6>
            <?php if ($files): ?>
                <div class="list-group">
                    <?php foreach ($files as $file): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-file me-2"></i>
                                <?php echo htmlspecialchars($file['file_name']); ?>
                                <small class="text-muted">(<?php echo round($file['file_size'] / 1024, 2); ?> KB)</small>
                            </div>
                            <a href="<?php echo $file['file_path']; ?>" class="btn btn-sm btn-outline-primary" download>
                                <i class="fas fa-download"></i>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-muted">هیچ فایلی ضمیمه نشده است</p>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if ($request['description']): ?>
        <div class="mt-4">
            <h6 class="text-primary mb-3">
                <i class="fas fa-align-right me-2"></i>
                توضیحات
            </h6>
            <div class="alert alert-light">
                <?php echo nl2br(htmlspecialchars($request['description'])); ?>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($workflow): ?>
        <div class="mt-4">
            <h6 class="text-primary mb-3">
                <i class="fas fa-route me-2"></i>
                گردش کار
            </h6>
            <div class="workflow-timeline">
                <?php foreach ($workflow as $step): ?>
                    <div class="timeline-item">
                        <div class="timeline-icon">
                            <i class="fas fa-<?php echo $step['status'] === 'تأیید شده' ? 'check' : ($step['status'] === 'رد شده' ? 'times' : 'clock'); ?>"></i>
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-title">
                                <?php echo htmlspecialchars($step['assigned_user_name'] ?: 'کاربر ناشناس'); ?>
                                - <?php echo $step['status']; ?>
                            </div>
                            <div class="timeline-meta">
                                <?php if ($step['department']): ?>
                                    واحد: <?php echo htmlspecialchars($step['department']); ?> |
                                <?php endif; ?>
                                تاریخ: <?php echo date('Y/m/d H:i', strtotime($step['created_at'])); ?>
                                <?php if ($step['action_date']): ?>
                                    | اقدام: <?php echo date('Y/m/d H:i', strtotime($step['action_date'])); ?>
                                <?php endif; ?>
                            </div>
                            <?php if ($step['comments']): ?>
                                <div class="timeline-comments">
                                    <strong>توضیحات:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($step['comments'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <style>
        .priority-low { background: #d4edda; color: #155724; }
        .priority-medium { background: #fff3cd; color: #856404; }
        .priority-high { background: #f8d7da; color: #721c24; }
        .priority-urgent { background: #f5c6cb; color: #721c24; }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-completed { background: #d1ecf1; color: #0c5460; }
        .status-in-progress { background: #cce5ff; color: #004085; }
    </style>
    <?php
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">خطا در بارگذاری جزئیات: ' . $e->getMessage() . '</div>';
}
?>