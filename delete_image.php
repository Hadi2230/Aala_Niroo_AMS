<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// حذف عکس
if (isset($_GET['id']) && isset($_GET['asset_id'])) {
    $image_id = $_GET['id'];
    $asset_id = $_GET['asset_id'];
    
    // دریافت اطلاعات عکس
    $stmt = $pdo->prepare("SELECT * FROM asset_images WHERE id = ?");
    $stmt->execute([$image_id]);
    $image = $stmt->fetch();
    
    if ($image) {
        // حذف فایل فیزیکی
        if (file_exists($image['image_path'])) {
            unlink($image['image_path']);
        }
        
        // حذف رکورد از دیتابیس
        $stmt = $pdo->prepare("DELETE FROM asset_images WHERE id = ?");
        $stmt->execute([$image_id]);
        
        $success = "عکس با موفقیت حذف شد!";
    } else {
        $error = "عکس مورد نظر یافت نشد!";
    }
    
    header('Location: edit_asset.php?id=' . $asset_id . '&' . (isset($success) ? 'success=' . urlencode($success) : 'error=' . urlencode($error)));
    exit();
} else {
    header('Location: reports.php');
    exit();
}
?>