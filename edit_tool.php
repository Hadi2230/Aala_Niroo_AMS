<?php
session_start();
require_once 'config.php';

// بررسی دسترسی
if (!isset($_SESSION['user_id'])) {
    if (isset($_GET['embed']) && $_GET['embed'] == '1') {
        echo '<div class="alert alert-warning">لطفاً ابتدا وارد شوید</div>';
        exit;
    }
    header('Location: login.php');
    exit;
}

// پردازش ویرایش ابزار
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit_tool') {
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("UPDATE tools SET name = ?, category = ?, brand = ?, model = ?, serial_number = ?, purchase_date = ?, purchase_price = ?, supplier = ?, location = ?, condition_notes = ?, maintenance_date = ?, next_maintenance_date = ? WHERE id = ?");
        $stmt->execute([
            $_POST['name'],
            $_POST['category'],
            $_POST['brand'] ?? null,
            $_POST['model'] ?? null,
            $_POST['serial_number'] ?? null,
            $_POST['purchase_date'] ?: null,
            $_POST['purchase_price'] ?: null,
            $_POST['supplier'] ?? null,
            $_POST['location'] ?? null,
            $_POST['condition_notes'] ?? null,
            $_POST['maintenance_date'] ?: null,
            $_POST['next_maintenance_date'] ?: null,
            $_POST['tool_id']
        ]);
        
        $pdo->commit();
        $_SESSION['success'] = "ابزار با موفقیت ویرایش شد";
        logAction($pdo, 'EDIT_TOOL', "ویرایش ابزار: " . $_POST['name'] . " (ID: " . $_POST['tool_id'] . ")");
        
        // برگشت به صفحه tools.php
        header('Location: tools.php');
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "خطا در ویرایش ابزار: " . $e->getMessage();
        logAction($pdo, 'EDIT_TOOL_ERROR', "خطا در ویرایش ابزار: " . $e->getMessage());
    }
}

// اگر درخواست GET است، به tools.php برگرد
header('Location: tools.php');
exit;
?>