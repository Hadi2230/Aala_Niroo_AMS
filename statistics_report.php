<div id="statistics-report" class="report-section">
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-box">
                <i class="fas fa-server fa-2x text-primary"></i>
                <div class="stat-number" id="total-assets">0</div>
                <div class="stat-title">تعداد دستگاه‌ها</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-box">
                <i class="fas fa-users fa-2x text-success"></i>
                <div class="stat-number" id="total-customers">0</div>
                <div class="stat-title">تعداد مشتریان</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-box">
                <i class="fas fa-link fa-2x text-info"></i>
                <div class="stat-number" id="total-assignments">0</div>
                <div class="stat-title">انتساب‌های فعال</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-box">
                <i class="fas fa-tools fa-2x text-warning"></i>
                <div class="stat-number" id="maintenance-count">0</div>
                <div class="stat-title">دستگاه‌های در حال تعمیر</div>
            </div>
        </div>
    </div>

    <div class="filter-box">
        <h4><i class="fas fa-filter"></i> فیلترهای آمار کلی</h4>
        <div class="row mt-3">
            <div class="col-md-4">
                <div class="mb-3">
                    <label class="form-label">بازه زمانی</label>
                    <select class="form-select filter-input" name="time_range">
                        <option value="7">هفته گذشته</option>
                        <option value="30" selected>ماه گذشته</option>
                        <option value="90">۳ ماه گذشته</option>
                        <option value="365">سال گذشته</option>
                        <option value="0">همه زمان‌ها</option>
                    </select>
                </div>
            </div>
            <div class="col-md-4">
                <div class="mb-3">
                    <label class="form-label">نوع گزارش</label>
                    <select class="form-select filter-input" name="stats_type">
                        <option value="overview">آمار کلی</option>
                        <option value="assets">آمار دستگاه‌ها</option>
                        <option value="customers">آمار مشتریان</option>
                        <option value="assignments">آمار انتساب‌ها</option>
                    </select>
                </div>
            </div>
            <div class="col-md-4">
                <div class="mb-3">
                    <label class="form-label">دسته‌بندی</label>
                    <select class="form-select filter-input" name="stats_category">
                        <option value="all">همه</option>
                        <option value="type">بر اساس نوع</option>
                        <option value="status">بر اساس وضعیت</option>
                        <option value="location">بر اساس موقعیت</option>
                    </select>
                </div>
            </div>
        </div>
        <button class="btn btn-primary" onclick="generateReport('statistics')">
            <i class="fas fa-play"></i> ایجاد گزارش آماری
        </button>
    </div>

    <div class="report-result" id="statistics-result">
        <p class="text-center text-muted">لطفاً فیلترهای مورد نظر را انتخاب و بر روی دکمه "ایجاد گزارش آماری" کلیک کنید.</p>
    </div>
</div>