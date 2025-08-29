// توابع مدیریت گزارش‌گیری
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
    const resultDiv = document.getElementById(`${reportType}-result`);
    
    if (reportType === 'statistics') {
        resultDiv.innerHTML = generateStatisticsHTML(data);
    } else {
        resultDiv.innerHTML = generateTableHTML(data, reportType);
    }
}

function generateTableHTML(data, reportType) {
    if (data.length === 0) {
        return '<p class="text-center text-muted">هیچ داده‌ای برای نمایش وجود ندارد.</p>';
    }
    
    const columns = Object.keys(data[0]);
    let html = `
        <h4>${getReportTypeLabel(reportType)}</h4>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        ${columns.map(col => `<th>${formatColumnName(col)}</th>`).join('')}
                    </tr>
                </thead>
                <tbody>
    `;
    
    data.forEach(row => {
        html += '<tr>';
        columns.forEach(col => {
            html += `<td>${row[col] || '-'}</td>`;
        });
        html += '</tr>';
    });
    
    html += `
                </tbody>
            </table>
        </div>
        <div class="mt-3">
            <p>تعداد کل رکوردها: ${data.length} مورد | تاریخ ایجاد گزارش: ${new Date().toLocaleDateString('fa-IR')}</p>
        </div>
    `;
    
    return html;
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

function formatColumnName(name) {
    const names = {
        'id': 'ردیف',
        'name': 'نام',
        'type_name': 'نوع دستگاه',
        'serial_number': 'شماره سریال',
        'purchase_date': 'تاریخ خرید',
        'status': 'وضعیت',
        'full_name': 'نام کامل',
        'company': 'شرکت',
        'phone': 'تلفن',
        'asset_name': 'نام دستگاه',
        'customer_name': 'نام مشتری',
        'assignment_date': 'تاریخ انتساب',
        'installation_date': 'تاریخ نصب',
        'installer_name': 'نام نصاب',
        'installation_status': 'وضعیت نصب'
    };
    
    return names[name] || name;
}