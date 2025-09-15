<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once __DIR__ . '/config.php';

echo "<h2>Debug Suppliers - بررسی تامین‌کنندگان</h2>";

// بررسی وجود جدول
try {
    $tables = $pdo->query("SHOW TABLES LIKE 'suppliers'")->fetchAll();
    if (empty($tables)) {
        echo "<p style='color: red;'>❌ جدول suppliers وجود ندارد!</p>";
    } else {
        echo "<p style='color: green;'>✅ جدول suppliers موجود است</p>";
        
        // بررسی ساختار جدول
        $columns = $pdo->query("DESCRIBE suppliers")->fetchAll();
        echo "<h3>ستون‌های جدول:</h3><ul>";
        foreach ($columns as $col) {
            echo "<li>{$col['Field']} - {$col['Type']} - {$col['Null']} - {$col['Default']}</li>";
        }
        echo "</ul>";
        
        // تست INSERT ساده
        echo "<h3>تست INSERT ساده:</h3>";
        try {
            $stmt = $pdo->prepare("INSERT INTO suppliers (supplier_code, company_name, supplier_type) VALUES (?, ?, ?)");
            $test_code = 'TEST' . time();
            $stmt->execute([$test_code, 'تست شرکت', 'حقوقی']);
            echo "<p style='color: green;'>✅ INSERT ساده موفق بود</p>";
            
            // حذف رکورد تست
            $pdo->prepare("DELETE FROM suppliers WHERE supplier_code = ?")->execute([$test_code]);
            echo "<p style='color: blue;'>ℹ️ رکورد تست حذف شد</p>";
            
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ خطا در INSERT ساده: " . $e->getMessage() . "</p>";
        }
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ خطا در بررسی جدول: " . $e->getMessage() . "</p>";
}

// بررسی POST data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h3>POST Data:</h3>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    
    // تست INSERT کامل
    if (isset($_POST['test_insert'])) {
        try {
            $supplier_code = 'SUP' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            $stmt = $pdo->prepare("INSERT INTO suppliers (
                supplier_code, company_name, contact_person, supplier_type, business_category,
                address, city, state, country, postal_code, phone, mobile, fax, email, website,
                linkedin, whatsapp, instagram, contact_person_name, contact_person_position, contact_person_phone,
                bank_account, iban, bank_name, bank_branch, economic_code, national_id, registration_number,
                vat_number, payment_terms, main_products, brands, moq, lead_time, shipping_terms,
                quality_score, cooperation_start_date, satisfaction_level, importance_level, internal_notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $params = [
                $supplier_code,
                $_POST['company_name'] ?? 'تست شرکت',
                $_POST['contact_person'] ?? '',
                $_POST['supplier_type'] ?? 'حقوقی',
                $_POST['business_category'] ?? '',
                $_POST['address'] ?? '',
                $_POST['city'] ?? '',
                $_POST['state'] ?? '',
                $_POST['country'] ?? 'ایران',
                $_POST['postal_code'] ?? '',
                $_POST['phone'] ?? '',
                $_POST['mobile'] ?? '',
                $_POST['fax'] ?? '',
                $_POST['email'] ?? '',
                $_POST['website'] ?? '',
                $_POST['linkedin'] ?? '',
                $_POST['whatsapp'] ?? '',
                $_POST['instagram'] ?? '',
                $_POST['contact_person_name'] ?? '',
                $_POST['contact_person_position'] ?? '',
                $_POST['contact_person_phone'] ?? '',
                $_POST['bank_account'] ?? '',
                $_POST['iban'] ?? '',
                $_POST['bank_name'] ?? '',
                $_POST['bank_branch'] ?? '',
                $_POST['economic_code'] ?? '',
                $_POST['national_id'] ?? '',
                $_POST['registration_number'] ?? '',
                $_POST['vat_number'] ?? '',
                $_POST['payment_terms'] ?? 'نقدی',
                $_POST['main_products'] ?? '',
                $_POST['brands'] ?? '',
                $_POST['moq'] ?? '',
                $_POST['lead_time'] ?? '',
                $_POST['shipping_terms'] ?? '',
                $_POST['quality_score'] ?? 0,
                $_POST['cooperation_start_date'] ?? null,
                $_POST['satisfaction_level'] ?? 'متوسط',
                $_POST['importance_level'] ?? 'Normal',
                $_POST['internal_notes'] ?? ''
            ];
            
            echo "<h4>پارامترها:</h4>";
            echo "<pre>";
            print_r($params);
            echo "</pre>";
            
            $stmt->execute($params);
            echo "<p style='color: green;'>✅ INSERT کامل موفق بود! کد: $supplier_code</p>";
            
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ خطا در INSERT کامل: " . $e->getMessage() . "</p>";
        }
    }
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <title>Debug Suppliers</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <form method="POST">
            <div class="row">
                <div class="col-md-6">
                    <label class="form-label">نام شرکت</label>
                    <input type="text" class="form-control" name="company_name" value="تست شرکت">
                </div>
                <div class="col-md-6">
                    <label class="form-label">نوع</label>
                    <select class="form-select" name="supplier_type">
                        <option value="حقوقی">حقوقی</option>
                        <option value="حقیقی">حقیقی</option>
                    </select>
                </div>
            </div>
            <button type="submit" name="test_insert" class="btn btn-primary mt-3">تست INSERT</button>
        </form>
    </div>
</body>
</html>