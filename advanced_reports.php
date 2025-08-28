<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// تابع برای دریافت گزارش دستگاه‌ها
function getAssetsReport($filters) {
    global $pdo;
    
    $query = "SELECT a.*, at.display_name as type_name 
              FROM assets a 
              LEFT JOIN asset_types at ON a.type_id = at.id 
              WHERE 1=1";
    
    $params = [];
    
    if (!empty($filters['asset_type'])) {
        $query .= " AND at.name = ?";
        $params[] = $filters['asset_type'];
    }
    
    if (!empty($filters['status'])) {
        $query .= " AND a.status = ?";
        $params[] = $filters['status'];
    }
    
    if (!empty($filters['purchase_date_from'])) {
        $query .= " AND a.purchase_date >= ?";
        $params[] = $filters['purchase_date_from'];
    }
    
    if (!empty($filters['purchase_date_to'])) {
        $query .= " AND a.purchase_date <= ?";
        $params[] = $filters['purchase_date_to'];
    }
    
    $query .= " ORDER BY a.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

// تابع برای دریافت گزارش مشتریان
function getCustomersReport($filters) {
    global $pdo;
    
    $query = "SELECT * FROM customers WHERE 1=1";
    $params = [];
    
    if (!empty($filters['customer_name'])) {
        $query .= " AND full_name LIKE ?";
        $params[] = '%' . $filters['customer_name'] . '%';
    }
    
    if (!empty($filters['company'])) {
        $query .= " AND company LIKE ?";
        $params[] = '%' . $filters['company'] . '%';
    }
    
    if (!empty($filters['city'])) {
        $query .= " AND address LIKE ?";
        $params[] = '%' . $filters['city'] . '%';
    }
    
    $query .= " ORDER BY created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

// تابع برای دریافت گزارش انتساب‌ها
function getAssignmentsReport($filters) {
    global $pdo;
    
    $query = "SELECT aa.*, a.name as asset_name, a.device_model, a.device_serial,
                     c.full_name as customer_name, ad.installation_date, ad.installer_name,
                     ad.installation_status, at.display_name as asset_type
              FROM asset_assignments aa
              JOIN assets a ON aa.asset_id = a.id
              JOIN customers c ON aa.customer_id = c.id
              LEFT JOIN asset_types at ON a.type_id = at.id
              LEFT JOIN assignment_details ad ON aa.id = ad.assignment_id
              WHERE 1=1";
    
    $params = [];
    
    if (!empty($filters['assignment_date_from'])) {
        $query .= " AND aa.assignment_date >= ?";
        $params[] = $filters['assignment_date_from'];
    }
    
    if (!empty($filters['assignment_date_to'])) {
        $query .= " AND aa.assignment_date <= ?";
        $params[] = $filters['assignment_date_to'];
    }
    
    if (!empty($filters['installation_status'])) {
        $query .= " AND ad.installation_status = ?";
        $params[] = $filters['installation_status'];
    }
    
    if (!empty($filters['asset_type'])) {
        $query .= " AND at.name = ?";
        $params[] = $filters['asset_type'];
    }
    
    $query .= " ORDER BY aa.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

// تابع برای دریافت آمار کلی
function getStatisticsReport($filters) {
    global $pdo;
    
    $stats = [];
    
    // آمار دستگاه‌ها
    $stmt = $pdo->query("
        SELECT at.display_name as type_name, COUNT(*) as count 
        FROM assets a 
        JOIN asset_types at ON a.type_id = at.id 
        GROUP BY a.type_id
    ");
    $stats['assets_by_type'] = $stmt->fetchAll();
    
    // آمار وضعیت دستگاه‌ها
    $stmt = $pdo->query("
        SELECT status, COUNT(*) as count 
        FROM assets 
        GROUP BY status
    ");
    $stats['assets_by_status'] = $stmt->fetchAll();
    
    // آمار مشتریان
    $stmt = $pdo->query("SELECT COUNT(*) as total_customers FROM customers");
    $stats['total_customers'] = $stmt->fetch()['total_customers'];
    
    // آمار انتساب‌ها
    $stmt = $pdo->query("SELECT COUNT(*) as total_assignments FROM asset_assignments");
    $stats['total_assignments'] = $stmt->fetch()['total_assignments'];
    
    // آمار انتساب‌های ماهانه
    $stmt = $pdo->query("
        SELECT DATE_FORMAT(assignment_date, '%Y-%m') as month, 
               COUNT(*) as count 
        FROM asset_assignments 
        GROUP BY DATE_FORMAT(assignment_date, '%Y-%m')
        ORDER BY month DESC
        LIMIT 6
    ");
    $stats['monthly_assignments'] = $stmt->fetchAll();
    
    return $stats;
}

// پردازش درخواست‌های گزارش‌گیری
$report_data = [];
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $report_type = $_POST['report_type'] ?? '';
    $filters = $_POST['filters'] ?? [];
    
    switch ($report_type) {
        case 'assets':
            $report_data = getAssetsReport($filters);
            break;
            
        case 'customers':
            $report_data = getCustomersReport($filters);
            break;
            
        case 'assignments':
            $report_data = getAssignmentsReport($filters);
            break;
            
        case 'statistics':
            $report_data = getStatisticsReport($filters);
            break;
    }
    
    // اگر درخواست AJAX باشد، داده‌ها را به صورت JSON برگردان
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($report_data);
        exit();
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سیستم گزارش‌گیری پیشرفته - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        /* استایل‌های اختصاصی گزارش‌گیری */
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --accent: #e74c3c;
            --light: #ecf0f1;
            --dark: #2c3e50;
        }
        
        .report-section {
            display: none;
        }
        
        .report-section.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .filter-box {
            background-color: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }
        
        .report-result {
            background-color: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-top: 25px;
        }
        
        .stat-box {
            text-align: center;
            padding: 20px;
            border-radius: 12px;
            background: white;
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
        }
        
        .stat-box:hover {
            transform: translateY(-5px);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--secondary);
            margin: 10px 0;
        }
        
        .print-container {
            display: none;
        }
        
        @media print {
            body * {
                visibility: hidden;
            }
            .print-container, .print-container * {
                visibility: visible;
            }
            .print-container {
                display: block;
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                padding: 20px;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <!-- افزودن نوار ناوبری -->
    <?php include 'navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <!-- منوی کناری -->
            <div class="col-md-2 sidebar no-print">
                <h4 class="text-center mb-4">گزارشات</h4>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="#" data-report="assets">
                            <i class="fas fa-server"></i> گزارش دستگاه‌ها
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-report="customers">
                            <i class="fas fa-users"></i> گزارش مشتریان
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-report="assignments">
                            <i class="fas fa-link"></i> گزارش انتساب‌ها
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-report="statistics">
                            <i class="fas fa-chart-pie"></i> آمار کلی
                        </a>
                    </li>
                </ul>
            </div>

            <!-- محتوای اصلی -->
            <div class="col-md-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-chart-bar"></i> سیستم گزارش‌گیری پیشرفته</h2>
                    <div class="report-actions no-print">
                        <button class="btn btn-primary" onclick="printReport()">
                            <i class="fas fa-print"></i> چاپ گزارش
                        </button>
                        <button class="btn btn-outline-primary" onclick="exportToExcel()">
                            <i class="fas fa-file-excel"></i> خروجی Excel
                        </button>
                    </div>
                </div>

                <!-- بخش‌های مختلف گزارش‌گیری -->
                <?php include 'assets_report.php'; ?>
                <?php include 'customers_report.php'; ?>
                <?php include 'assignments_report.php'; ?>
                <?php include 'statistics_report.php'; ?>
            </div>
        </div>
    </div>

    <!-- بخش چاپ -->
    <div class="print-container" id="print-section">
        <div class="report-header">
            <h2>گزارش سامانه مدیریت اعلا نیرو</h2>
            <p>تاریخ تهیه گزارش: <span id="report-date"></span></p>
            <p>نوع گزارش: <span id="report-type"></span></p>
        </div>
        <div id="print-content"></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script>
        // توابع JavaScript برای مدیریت گزارش‌گیری
        document.querySelectorAll('.sidebar .nav-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                document.querySelectorAll('.sidebar .nav-link').forEach(l => l.classList.remove('active'));
                this.classList.add('active');
                
                document.querySelectorAll('.report-section').forEach(section => {
                    section.classList.remove('active');
                });
                
                const reportType = this.getAttribute('data-report');
                document.getElementById(reportType + '-report').classList.add('active');
            });
        });

        // توابع برای تولید گزارش، چاپ و خروجی اکسل
        function generateReport(reportType) {
            const formData = new FormData();
            formData.append('report_type', reportType);
            
            // جمع‌آوری فیلترها
            const filters = {};
            document.querySelectorAll(`#${reportType}-report .filter-input`).forEach(input => {
                filters[input.name] = input.value;
            });
            formData.append('filters', JSON.stringify(filters));
            
            // ارسال درخواست AJAX
            fetch('advanced_reports.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                displayReportResults(reportType, data);
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }

        function displayReportResults(reportType, data) {
            // نمایش نتایج گزارش
            const resultDiv = document.getElementById(`${reportType}-result`);
            
            if (reportType === 'statistics') {
                resultDiv.innerHTML = generateStatisticsHTML(data);
            } else {
                resultDiv.innerHTML = generateTableHTML(data, reportType);
            }
        }

        function printReport() {
            // پیاده‌سازی چاپ گزارش
            const activeReport = document.querySelector('.report-section.active');
            const reportId = activeReport.id;
            const reportType = reportId.replace('-report', '');
            
            document.getElementById('report-type').textContent = getReportTypeLabel(reportType);
            document.getElementById('report-date').textContent = new Date().toLocaleDateString('fa-IR');
            document.getElementById('print-content').innerHTML = document.getElementById(`${reportType}-result`).innerHTML;
            
            window.print();
        }

        function exportToExcel() {
            // پیاده‌سازی خروجی اکسل
            const activeReport = document.querySelector('.report-section.active');
            const reportId = activeReport.id;
            const reportType = reportId.replace('-report', '');
            const table = document.querySelector(`#${reportType}-result table`);
            
            if (table) {
                const wb = XLSX.utils.table_to_book(table, {sheet: "Report"});
                XLSX.writeFile(wb, `${reportType}_report_${new Date().toISOString().split('T')[0]}.xlsx`);
            }
        }

        // توابع کمکی
        function getReportTypeLabel(type) {
            const labels = {
                'assets': 'گزارش دستگاه‌ها',
                'customers': 'گزارش مشتریان',
                'assignments': 'گزارش انتساب‌ها',
                'statistics': 'گزارش آمار کلی'
            };
            return labels[type] || 'گزارش';
        }
    </script>
</body>
</html>