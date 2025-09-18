<?php
/**
 * get_request_workflow.php - دریافت گردش کار درخواست
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

require_once 'config_simple_fixed.php';

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
    
    // دریافت گردش کار
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
    <div class="row">
        <div class="col-12">
            <h6 class="text-primary mb-3">
                <i class="fas fa-info-circle me-2"></i>
                اطلاعات درخواست
            </h6>
            <div class="alert alert-info">
                <strong>شماره درخواست:</strong> <?php echo htmlspecialchars($request['request_number']); ?><br>
                <strong>درخواست‌دهنده:</strong> <?php echo htmlspecialchars($request['requester_full_name'] ?: $request['requester_name']); ?><br>
                <strong>نام آیتم:</strong> <?php echo htmlspecialchars($request['item_name']); ?><br>
                <strong>وضعیت فعلی:</strong> 
                <span class="badge status-<?php echo str_replace(' ', '-', strtolower($request['status'])); ?>">
                    <?php echo $request['status']; ?>
                </span>
            </div>
        </div>
    </div>
    
    <?php if ($workflow): ?>
        <div class="mt-4">
            <h6 class="text-primary mb-3">
                <i class="fas fa-route me-2"></i>
                گردش کار
            </h6>
            <div class="workflow-timeline">
                <?php foreach ($workflow as $index => $step): ?>
                    <div class="timeline-item">
                        <div class="timeline-icon">
                            <?php
                            $icon = 'clock';
                            if ($step['status'] === 'تأیید شده') $icon = 'check';
                            elseif ($step['status'] === 'رد شده') $icon = 'times';
                            elseif ($step['status'] === 'در حال بررسی') $icon = 'cog';
                            ?>
                            <i class="fas fa-<?php echo $icon; ?>"></i>
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-title">
                                مرحله <?php echo $step['step_order']; ?>: 
                                <?php echo htmlspecialchars($step['assigned_user_name'] ?: 'کاربر ناشناس'); ?>
                                - <?php echo $step['status']; ?>
                            </div>
                            <div class="timeline-meta">
                                <?php if ($step['department']): ?>
                                    <i class="fas fa-building me-1"></i>
                                    واحد: <?php echo htmlspecialchars($step['department']); ?> |
                                <?php endif; ?>
                                <i class="fas fa-calendar me-1"></i>
                                تاریخ ایجاد: <?php echo date('Y/m/d H:i', strtotime($step['created_at'])); ?>
                                <?php if ($step['action_date']): ?>
                                    | <i class="fas fa-clock me-1"></i>
                                    تاریخ اقدام: <?php echo date('Y/m/d H:i', strtotime($step['action_date'])); ?>
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
    <?php else: ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            هیچ گردش کاری برای این درخواست تعریف نشده است.
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
            margin-top: 15px;
        }

        .timeline-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding: 15px;
            background: white;
            border-radius: 8px;
            border-left: 4px solid #3498db;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .timeline-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #3498db;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 15px;
            font-size: 1.2rem;
        }

        .timeline-content {
            flex: 1;
        }

        .timeline-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .timeline-meta {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .timeline-comments {
            margin-top: 8px;
            padding: 10px;
            background: #e9ecef;
            border-radius: 6px;
            font-size: 0.9rem;
        }
    </style>
    <?php
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">خطا در بارگذاری گردش کار: ' . $e->getMessage() . '</div>';
}
?>