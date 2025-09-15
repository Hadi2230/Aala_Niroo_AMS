<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once __DIR__ . '/config.php';

echo "<h2>تست INSERT تامین‌کننده</h2>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // تولید کد تامین‌کننده
        $supplier_code = 'SUP' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        $stmt = $pdo->prepare("INSERT INTO suppliers (
            supplier_code, company_name, contact_person, supplier_type, business_category, logo_path,
            address, city, state, country, postal_code, phone, mobile, fax, email, website,
            linkedin, whatsapp, instagram, contact_person_name, contact_person_position, contact_person_phone,
            bank_account, iban, bank_name, bank_branch, economic_code, national_id, registration_number,
            vat_number, payment_terms, main_products, brands, moq, lead_time, shipping_terms,
            quality_score, cooperation_start_date, satisfaction_level, importance_level, internal_notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $params = [
            $supplier_code,
            $_POST['company_name'] ?? 'تست شرکت',
            $_POST['contact_person'] ?? '',
            $_POST['supplier_type'] ?? 'حقوقی',
            $_POST['business_category'] ?? '',
            null, // logo_path
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
        
        echo "<h3>پارامترها:</h3>";
        echo "<pre>";
        print_r($params);
        echo "</pre>";
        
        echo "<h3>تعداد پارامترها: " . count($params) . "</h3>";
        
        $stmt->execute($params);
        echo "<p style='color: green; font-size: 18px;'>✅ <strong>موفق! تامین‌کننده با کد $supplier_code ذخیره شد!</strong></p>";
        
        // نمایش آخرین تامین‌کننده‌ها
        $recent = $pdo->query("SELECT supplier_code, company_name, created_at FROM suppliers ORDER BY created_at DESC LIMIT 5")->fetchAll();
        echo "<h3>آخرین تامین‌کننده‌ها:</h3>";
        echo "<ul>";
        foreach ($recent as $s) {
            echo "<li>{$s['supplier_code']} - {$s['company_name']} - {$s['created_at']}</li>";
        }
        echo "</ul>";
        
    } catch (Exception $e) {
        echo "<p style='color: red; font-size: 18px;'>❌ <strong>خطا:</strong> " . $e->getMessage() . "</p>";
    }
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <title>تست INSERT تامین‌کننده</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <form method="POST">
            <div class="row">
                <div class="col-md-6">
                    <label class="form-label">نام شرکت *</label>
                    <input type="text" class="form-control" name="company_name" value="تست شرکت" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">نوع *</label>
                    <select class="form-select" name="supplier_type" required>
                        <option value="حقوقی">حقوقی</option>
                        <option value="حقیقی">حقیقی</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">زمینه فعالیت</label>
                    <input type="text" class="form-control" name="business_category" value="تجهیزات الکترونیکی">
                </div>
                <div class="col-md-6">
                    <label class="form-label">شهر</label>
                    <input type="text" class="form-control" name="city" value="تهران">
                </div>
            </div>
            <button type="submit" class="btn btn-primary mt-3">تست INSERT</button>
        </form>
        
        <div class="mt-4">
            <a href="suppliers.php" class="btn btn-success">برو به صفحه تامین‌کنندگان</a>
        </div>
    </div>
</body>
</html>