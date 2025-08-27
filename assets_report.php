<div id="assets-report" class="report-section active">
    <div class="filter-box">
        <h4><i class="fas fa-filter"></i> فیلترهای گزارش دستگاه‌ها</h4>
        <div class="row mt-3">
            <div class="col-md-3">
                <div class="mb-3">
                    <label class="form-label">نوع دستگاه</label>
                    <select class="form-select filter-input" name="asset_type">
                        <option value="">همه انواع</option>
                        <option value="generator">ژنراتور</option>
                        <option value="motor">موتور برق</option>
                        <option value="consumable">اقلام مصرفی</option>
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <div class="mb-3">
                    <label class="form-label">وضعیت</label>
                    <select class="form-select filter-input" name="status">
                        <option value="">همه وضعیت‌ها</option>
                        <option value="فعال">فعال</option>
                        <option value="غیرفعال">غیرفعال</option>
                        <option value="در حال تعمیر">در حال تعمیر</option>
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <div class="mb-3">
                    <label class="form-label">تاریخ خرید از</label>
                    <input type="date" class="form-control filter-input" name="purchase_date_from">
                </div>
            </div>
            <div class="col-md-3">
                <div class="mb-3">
                    <label class="form-label">تاریخ خرید تا</label>
                    <input type="date" class="form-control filter-input" name="purchase_date_to">
                </div>
            </div>
        </div>
        <button class="btn btn-primary" onclick="generateReport('assets')">
            <i class="fas fa-play"></i> ایجاد گزارش
        </button>
    </div>

    <div class="report-result" id="assets-result">
        <p class="text-center text-muted">لطفاً فیلترهای مورد نظر را انتخاب و بر روی دکمه "ایجاد گزارش" کلیک کنید.</p>
    </div>
</div>