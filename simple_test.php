<?php
echo "<h1>تست ساده</h1>";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    echo "<h2>POST دریافت شد!</h2>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    
    if (isset($_POST['add_asset'])) {
        echo "<h3 style='color: green;'>✅ دکمه add_asset فشرده شد!</h3>";
    } else {
        echo "<h3 style='color: red;'>❌ دکمه add_asset فشرده نشد!</h3>";
    }
} else {
    echo '<form method="POST">
        <input type="text" name="name" placeholder="نام" required>
        <button type="submit" name="add_asset">ثبت</button>
    </form>';
}
?>