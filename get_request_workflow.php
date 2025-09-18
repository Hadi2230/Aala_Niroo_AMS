<?php
/**
 * get_request_workflow.php - دریافت گردش کار درخواست
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
    
    // دریافت گردش کار کامل
    $stmt = $pdo->prepare("
        SELECT rw.*, u.full_name as assigned_user_name, u.username
        FROM request_workflow rw
        LEFT JOIN users u ON rw.assigned_to = u.id
        WHERE rw.request_id = ?
        ORDER BY rw.step_order, rw.created_at
    ");
    $stmt->execute([$request_id]);
    $workflow = $stmt->fetchAll();
    
    ?>
    <div class="mb-4">
        <h5 class="text-primary">
            <i class="fas fa-route me-2"></i>
            گردش کار درخواست <?php echo htmlspecialchars($request['request_number']); ?>
        </h5>
        <p class="text-muted">
            درخواست‌دهنده: <?php echo htmlspecialchars($request['requester_full_name'] ?: $request['requester_name']); ?>
            | آیتم: <?php echo htmlspecialchars($request['item_name']); ?>
        </p>
    </div>
    
    <?php if ($workflow): ?>
        <div class="workflow-timeline">
            <?php foreach ($workflow as $index => $step): ?>
                <div class="timeline-item">
                    <div class="timeline-icon" style="background: <?php 
                        echo $step['status'] === 'تأیید شده' ? 'var(--success-color)' : 
                            ($step['status'] === 'رد شده' ? 'var(--accent-color)' : 'var(--secondary-color)'); 
                    ?>;">
                        <i class="fas fa-<?php 
                            echo $step['status'] === 'تأیید شده' ? 'check' : 
                                ($step['status'] === 'رد شده' ? 'times' : 
                                    ($step['status'] === 'در حال بررسی' ? 'clock' : 'hourglass-half')); 
                        ?>"></i>
                    </div>
                    <div class="timeline-content">
                        <div class="timeline-title">
                            <strong>مرحله <?php echo $step['step_order']; ?>:</strong>
                            <?php echo htmlspecialchars($step['assigned_user_name'] ?: $step['username'] ?: 'کاربر ناشناس'); ?>
                            <span class="badge status-<?php echo str_replace(' ', '-', strtolower($step['status'])); ?> ms-2">
                                <?php echo $step['status']; ?>
                            </span>
                        </div>
                        <div class="timeline-meta">
                            <?php if ($step['department']): ?>
                                <i class="fas fa-building me-1"></i>
                                واحد: <?php echo htmlspecialchars($step['department']); ?> |
                            <?php endif; ?>
                            <i class="fas fa-calendar me-1"></i>
                            تاریخ انتساب: <?php echo date('Y/m/d H:i', strtotime($step['created_at'])); ?>
                            <?php if ($step['action_date']): ?>
                                | <i class="fas fa-clock me-1"></i>
                                تاریخ اقدام: <?php echo date('Y/m/d H:i', strtotime($step['action_date'])); ?>
                            <?php endif; ?>
                        </div>
                        <?php if ($step['comments']): ?>
                            <div class="timeline-comments">
                                <strong><i class="fas fa-comment me-1"></i>توضیحات:</strong><br>
                                <?php echo nl2br(htmlspecialchars($step['comments'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            هنوز گردش کاری برای این درخواست تعریف نشده است.
        </div>
    <?php endif; ?>
    
    <style>
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-completed { background: #d1ecf1; color: #0c5460; }
        .status-in-progress { background: #cce5ff; color: #004085; }
        
        .workflow-timeline {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
        }
        
        .timeline-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 20px;
            padding: 15px;
            background: white;
            border-radius: 8px;
            border-left: 4px solid var(--secondary-color);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .timeline-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 15px;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        
        .timeline-content {
            flex: 1;
        }
        
        .timeline-title {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .timeline-meta {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }
        
        .timeline-comments {
            margin-top: 10px;
            padding: 12px;
            background: #e9ecef;
            border-radius: 6px;
            font-size: 0.9rem;
            border-right: 3px solid var(--secondary-color);
        }
    </style>
    <?php
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">خطا در بارگذاری گردش کار: ' . $e->getMessage() . '</div>';
}
?>