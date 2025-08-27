<div id="assignments-report" class="report-section">
    <div class="filter-box">
        <h4><i class="fas fa-filter"></i> فیلترهای گزارش انتساب‌ها</h4>
        <div class="row mt-3">
            <div class="col-md-3">
                <div class="mb-3">
                    <label class="form-label">تاریخ انتساب از</label>
                    <input type="date" class="form-control filter-input" name="assignment_date_from">
                </div>
            </div>
            <div class="col-md-3">
                <div class="mb-3">
                    <label class="form-label">تاریخ انتساب تا</label>
                    <input type="date" class="form-control filter-input" name="assignment_date_to">
                </div>
            </div>
            <div class="col-md-3">
                <div class="mb-3">
                    <label class="form-label">وضعیت نصب</label>
                    <select class="form-select filter-input" name="installation_status">
                        <option value="">همه وضعیت‌ها</option>
                        <option value="نصب شده">نصب شده</option>
                        <option value="در حال نصب">در حال نصب</option>
                        <option value="لغو شده">لغو شده</option>
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <div class="mb-3">
                    <label class="form-label">نوع دستگاه</label>
                    <select class="form-select filter-input" name="asset_type">
                        <option value="">همه انواع</option>
                        <option value="generator">ژنراتور</option>
                        <option value="motor">موتور برق</option>
                    </select>
                </div>
            </div>
        </div>
        <button class="btn btn-primary" onclick="generateReport('assignments')">
            <i class="fas fa-play"></i> ایجاد گزارش
        </button>
    </div>

    <div class="report-result" id="assignments-result">
        <p class="text-center text-muted">لطفاً فیلترهای مورد نظر را انتخاب و بر روی دکمه "ایجاد گزارش" کلیک کنید.</p>
    </div>
</div>