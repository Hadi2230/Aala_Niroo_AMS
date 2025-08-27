<div id="customers-report" class="report-section">
    <div class="filter-box">
        <h4><i class="fas fa-filter"></i> فیلترهای گزارش مشتریان</h4>
        <div class="row mt-3">
            <div class="col-md-4">
                <div class="mb-3">
                    <label class="form-label">نام مشتری</label>
                    <input type="text" class="form-control filter-input" name="customer_name" placeholder="جستجو بر اساس نام">
                </div>
            </div>
            <div class="col-md-4">
                <div class="mb-3">
                    <label class="form-label">شرکت</label>
                    <input type="text" class="form-control filter-input" name="company" placeholder="جستجو بر اساس شرکت">
                </div>
            </div>
            <div class="col-md-4">
                <div class="mb-3">
                    <label class="form-label">شهر</label>
                    <input type="text" class="form-control filter-input" name="city" placeholder="جستجو بر اساس شهر">
                </div>
            </div>
        </div>
        <button class="btn btn-primary" onclick="generateReport('customers')">
            <i class="fas fa-play"></i> ایجاد گزارش
        </button>
    </div>

    <div class="report-result" id="customers-result">
        <p class="text-center text-muted">لطفاً فیلترهای مورد نظر را انتخاب و بر روی دکمه "ایجاد گزارش" کلیک کنید.</p>
    </div>
</div>