-- سیستم مدیریت ناوبری خودروهای شرکتی
-- Company Car Navigation Management System
-- نسخه نهایی و کامل

-- جدول خودروهای شرکتی
CREATE TABLE IF NOT EXISTS company_cars (
    id INT AUTO_INCREMENT PRIMARY KEY,
    -- اطلاعات شناسایی
    car_code VARCHAR(20) UNIQUE NOT NULL COMMENT 'کد یکتای خودرو',
    plate_number VARCHAR(20) UNIQUE NOT NULL COMMENT 'شماره پلاک',
    vin_number VARCHAR(50) UNIQUE COMMENT 'شماره شاسی',
    engine_number VARCHAR(50) COMMENT 'شماره موتور',
    
    -- مشخصات خودرو
    brand VARCHAR(100) NOT NULL COMMENT 'برند',
    model VARCHAR(100) NOT NULL COMMENT 'مدل',
    year INT COMMENT 'سال ساخت',
    color VARCHAR(50) COMMENT 'رنگ',
    fuel_type ENUM('بنزینی','دیزلی','دوگانه سوز','هیبریدی','برقی') DEFAULT 'بنزینی',
    transmission_type ENUM('دستی','اتوماتیک','CVT') DEFAULT 'دستی',
    body_type ENUM('سدان','هاچ‌بک','SUV','وانت','ون','کامیون') DEFAULT 'سدان',
    seats_count INT DEFAULT 5 COMMENT 'تعداد صندلی',
    
    -- وضعیت فعلی
    current_odometer DECIMAL(10,2) DEFAULT 0 COMMENT 'کیلومتر فعلی',
    current_fuel_level INT DEFAULT 0 COMMENT 'درصد سوخت فعلی',
    current_location VARCHAR(255) COMMENT 'موقعیت فعلی',
    current_status ENUM('در دسترس','در حال استفاده','در تعمیرگاه','رزرو شده','غیرفعال','فروخته شده') DEFAULT 'در دسترس',
    current_driver_id INT NULL COMMENT 'راننده فعلی',
    
    -- اطلاعات خرید و ارزش
    purchase_date DATE COMMENT 'تاریخ خرید',
    purchase_price DECIMAL(18,0) COMMENT 'قیمت خرید',
    current_value DECIMAL(18,0) COMMENT 'ارزش فعلی',
    
    -- بیمه و اسناد
    insurance_company VARCHAR(100) COMMENT 'شرکت بیمه',
    insurance_policy_number VARCHAR(50) COMMENT 'شماره بیمه‌نامه',
    insurance_expiry_date DATE COMMENT 'تاریخ انقضای بیمه',
    third_party_insurance_expiry DATE COMMENT 'انقضای بیمه شخص ثالث',
    body_insurance_expiry DATE COMMENT 'انقضای بیمه بدنه',
    technical_inspection_expiry DATE COMMENT 'انقضای معاینه فنی',
    ownership_document VARCHAR(255) COMMENT 'سند مالکیت',
    
    -- GPS و ردیابی
    has_gps BOOLEAN DEFAULT false COMMENT 'دارای GPS',
    gps_device_id VARCHAR(100) COMMENT 'شناسه دستگاه GPS',
    last_gps_latitude DECIMAL(10, 8) COMMENT 'آخرین موقعیت GPS - عرض جغرافیایی',
    last_gps_longitude DECIMAL(11, 8) COMMENT 'آخرین موقعیت GPS - طول جغرافیایی',
    last_gps_update TIMESTAMP NULL COMMENT 'آخرین به‌روزرسانی GPS',
    
    -- تصاویر و مستندات
    front_image VARCHAR(500) COMMENT 'تصویر جلو',
    back_image VARCHAR(500) COMMENT 'تصویر عقب',
    side_image VARCHAR(500) COMMENT 'تصویر کنار',
    interior_image VARCHAR(500) COMMENT 'تصویر داخل',
    documents_folder VARCHAR(500) COMMENT 'پوشه مستندات',
    
    -- اطلاعات اضافی
    notes TEXT COMMENT 'یادداشت‌ها',
    qr_code VARCHAR(100) COMMENT 'کد QR خودرو',
    is_active BOOLEAN DEFAULT true COMMENT 'وضعیت فعال',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (current_driver_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_plate (plate_number),
    INDEX idx_status (current_status),
    INDEX idx_driver (current_driver_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول رانندگان مجاز
CREATE TABLE IF NOT EXISTS car_drivers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL COMMENT 'کاربر سیستم',
    
    -- اطلاعات راننده
    full_name VARCHAR(255) NOT NULL COMMENT 'نام کامل',
    national_code VARCHAR(10) UNIQUE NOT NULL COMMENT 'کد ملی',
    phone VARCHAR(20) NOT NULL COMMENT 'تلفن همراه',
    emergency_phone VARCHAR(20) COMMENT 'تلفن اضطراری',
    address TEXT COMMENT 'آدرس',
    
    -- اطلاعات گواهینامه
    license_number VARCHAR(50) UNIQUE COMMENT 'شماره گواهینامه',
    license_type ENUM('موتور','پایه سوم','پایه دوم','پایه یک','ویژه') DEFAULT 'پایه دوم',
    license_issue_date DATE COMMENT 'تاریخ صدور گواهینامه',
    license_expiry_date DATE COMMENT 'تاریخ انقضای گواهینامه',
    license_image VARCHAR(500) COMMENT 'تصویر گواهینامه',
    
    -- وضعیت و امتیازات
    status ENUM('فعال','غیرفعال','تعلیق شده','بازنشسته') DEFAULT 'فعال',
    driver_score INT DEFAULT 100 COMMENT 'امتیاز رانندگی',
    total_violations INT DEFAULT 0 COMMENT 'تعداد تخلفات',
    total_accidents INT DEFAULT 0 COMMENT 'تعداد تصادفات',
    total_distance_km DECIMAL(10,2) DEFAULT 0 COMMENT 'مجموع مسافت طی شده',
    
    -- محدودیت‌ها
    can_drive_out_city BOOLEAN DEFAULT true COMMENT 'مجاز به رانندگی برون‌شهری',
    max_daily_km INT DEFAULT 500 COMMENT 'حداکثر کیلومتر روزانه',
    allowed_car_types TEXT COMMENT 'انواع خودروهای مجاز',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_national_code (national_code),
    INDEX idx_license (license_number),
    INDEX idx_status (status),
    INDEX idx_score (driver_score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول تحویل و عودت خودرو
CREATE TABLE IF NOT EXISTS car_handovers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    handover_code VARCHAR(20) UNIQUE NOT NULL COMMENT 'کد تحویل',
    car_id INT NOT NULL COMMENT 'خودرو',
    driver_id INT NOT NULL COMMENT 'راننده',
    
    -- اطلاعات تحویل
    handover_type ENUM('تحویل','عودت','انتقال') DEFAULT 'تحویل',
    handover_date DATE NOT NULL COMMENT 'تاریخ تحویل',
    handover_time TIME NOT NULL COMMENT 'ساعت تحویل',
    handover_by INT NOT NULL COMMENT 'تحویل دهنده',
    receiver_signature VARCHAR(500) COMMENT 'امضای گیرنده',
    
    -- وضعیت خودرو هنگام تحویل
    handover_odometer DECIMAL(10,2) NOT NULL COMMENT 'کیلومتر هنگام تحویل',
    handover_fuel_level INT NOT NULL COMMENT 'سطح سوخت هنگام تحویل',
    handover_location VARCHAR(255) COMMENT 'محل تحویل',
    
    -- بررسی وضعیت ظاهری
    exterior_condition ENUM('سالم','خراشیدگی جزئی','آسیب متوسط','آسیب شدید') DEFAULT 'سالم',
    interior_condition ENUM('تمیز','نیاز به نظافت','آسیب جزئی','آسیب شدید') DEFAULT 'تمیز',
    tires_condition ENUM('مناسب','نیاز به تعویض','پنچر','نامناسب') DEFAULT 'مناسب',
    
    -- چک‌لیست تحویل
    spare_tire_check BOOLEAN DEFAULT true COMMENT 'لاستیک زاپاس',
    jack_check BOOLEAN DEFAULT true COMMENT 'جک',
    tools_check BOOLEAN DEFAULT true COMMENT 'ابزار',
    documents_check BOOLEAN DEFAULT true COMMENT 'مدارک',
    first_aid_check BOOLEAN DEFAULT true COMMENT 'جعبه کمک‌های اولیه',
    fire_extinguisher_check BOOLEAN DEFAULT true COMMENT 'کپسول آتش‌نشانی',
    warning_triangle_check BOOLEAN DEFAULT true COMMENT 'مثلث خطر',
    
    -- اطلاعات عودت (در صورت عودت)
    return_date DATE COMMENT 'تاریخ عودت',
    return_time TIME COMMENT 'ساعت عودت',
    return_odometer DECIMAL(10,2) COMMENT 'کیلومتر هنگام عودت',
    return_fuel_level INT COMMENT 'سطح سوخت هنگام عودت',
    return_location VARCHAR(255) COMMENT 'محل عودت',
    return_received_by INT COMMENT 'دریافت‌کننده عودت',
    return_signature VARCHAR(500) COMMENT 'امضای تحویل‌دهنده عودت',
    
    -- محاسبات
    total_distance_km DECIMAL(10,2) COMMENT 'مسافت طی شده',
    fuel_consumed_liters DECIMAL(10,2) COMMENT 'سوخت مصرف شده',
    
    -- هدف استفاده
    purpose ENUM('ماموریت کاری','جلسه','بازدید مشتری','سرویس و تعمیر','شخصی','سایر') DEFAULT 'ماموریت کاری',
    destination VARCHAR(255) COMMENT 'مقصد',
    expected_return_date DATE COMMENT 'تاریخ بازگشت مورد انتظار',
    
    -- مشکلات و نواقص
    reported_issues TEXT COMMENT 'مشکلات گزارش شده',
    damages_description TEXT COMMENT 'توضیح خسارات',
    
    -- تصاویر
    handover_images TEXT COMMENT 'تصاویر هنگام تحویل (JSON)',
    return_images TEXT COMMENT 'تصاویر هنگام عودت (JSON)',
    damage_images TEXT COMMENT 'تصاویر خسارات (JSON)',
    
    -- وضعیت
    status ENUM('در حال استفاده','تکمیل شده','لغو شده') DEFAULT 'در حال استفاده',
    notes TEXT COMMENT 'یادداشت‌ها',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (car_id) REFERENCES company_cars(id) ON DELETE CASCADE,
    FOREIGN KEY (driver_id) REFERENCES car_drivers(id) ON DELETE CASCADE,
    FOREIGN KEY (handover_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (return_received_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_car (car_id),
    INDEX idx_driver (driver_id),
    INDEX idx_date (handover_date),
    INDEX idx_status (status),
    INDEX idx_type (handover_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول سفرها و مسیرها
CREATE TABLE IF NOT EXISTS car_trips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trip_code VARCHAR(20) UNIQUE NOT NULL COMMENT 'کد سفر',
    car_id INT NOT NULL,
    driver_id INT NOT NULL,
    handover_id INT NOT NULL COMMENT 'مرتبط با تحویل',
    
    -- زمان و مکان شروع
    start_datetime DATETIME NOT NULL COMMENT 'زمان شروع',
    start_location VARCHAR(255) COMMENT 'مکان شروع',
    start_latitude DECIMAL(10, 8) COMMENT 'عرض جغرافیایی شروع',
    start_longitude DECIMAL(11, 8) COMMENT 'طول جغرافیایی شروع',
    start_odometer DECIMAL(10,2) NOT NULL COMMENT 'کیلومتر شروع',
    
    -- زمان و مکان پایان
    end_datetime DATETIME COMMENT 'زمان پایان',
    end_location VARCHAR(255) COMMENT 'مکان پایان',
    end_latitude DECIMAL(10, 8) COMMENT 'عرض جغرافیایی پایان',
    end_longitude DECIMAL(11, 8) COMMENT 'طول جغرافیایی پایان',
    end_odometer DECIMAL(10,2) COMMENT 'کیلومتر پایان',
    
    -- اطلاعات سفر
    purpose VARCHAR(255) COMMENT 'هدف سفر',
    route_description TEXT COMMENT 'توضیح مسیر',
    total_distance_km DECIMAL(10,2) COMMENT 'مسافت کل',
    duration_minutes INT COMMENT 'مدت زمان (دقیقه)',
    max_speed_kmh INT COMMENT 'حداکثر سرعت',
    avg_speed_kmh DECIMAL(5,2) COMMENT 'سرعت متوسط',
    
    -- مسافران
    passengers_count INT DEFAULT 0 COMMENT 'تعداد مسافران',
    passengers_list TEXT COMMENT 'لیست مسافران',
    
    -- هزینه‌ها
    fuel_cost DECIMAL(15,0) DEFAULT 0 COMMENT 'هزینه سوخت',
    toll_cost DECIMAL(15,0) DEFAULT 0 COMMENT 'هزینه عوارض',
    parking_cost DECIMAL(15,0) DEFAULT 0 COMMENT 'هزینه پارکینگ',
    other_costs DECIMAL(15,0) DEFAULT 0 COMMENT 'سایر هزینه‌ها',
    total_cost DECIMAL(15,0) DEFAULT 0 COMMENT 'مجموع هزینه‌ها',
    
    -- وضعیت
    status ENUM('در حال انجام','تکمیل شده','لغو شده') DEFAULT 'در حال انجام',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (car_id) REFERENCES company_cars(id) ON DELETE CASCADE,
    FOREIGN KEY (driver_id) REFERENCES car_drivers(id) ON DELETE CASCADE,
    FOREIGN KEY (handover_id) REFERENCES car_handovers(id) ON DELETE CASCADE,
    INDEX idx_car (car_id),
    INDEX idx_driver (driver_id),
    INDEX idx_date (start_datetime),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول سوخت‌گیری
CREATE TABLE IF NOT EXISTS car_fuel_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    car_id INT NOT NULL,
    driver_id INT NOT NULL,
    trip_id INT NULL,
    
    -- اطلاعات سوخت‌گیری
    fuel_date DATE NOT NULL COMMENT 'تاریخ سوخت‌گیری',
    fuel_time TIME NOT NULL COMMENT 'ساعت سوخت‌گیری',
    station_name VARCHAR(255) COMMENT 'نام جایگاه',
    station_location VARCHAR(255) COMMENT 'آدرس جایگاه',
    
    -- مقادیر
    odometer_reading DECIMAL(10,2) NOT NULL COMMENT 'کیلومتر هنگام سوخت‌گیری',
    fuel_type ENUM('بنزین معمولی','بنزین سوپر','گازوئیل','CNG','LPG') NOT NULL,
    fuel_liters DECIMAL(10,2) NOT NULL COMMENT 'لیتر سوخت',
    fuel_price_per_liter DECIMAL(10,0) NOT NULL COMMENT 'قیمت هر لیتر',
    total_cost DECIMAL(15,0) NOT NULL COMMENT 'هزینه کل',
    
    -- کارت سوخت
    fuel_card_used BOOLEAN DEFAULT false COMMENT 'استفاده از کارت سوخت',
    fuel_card_number VARCHAR(50) COMMENT 'شماره کارت سوخت',
    
    -- اطلاعات اضافی
    tank_full BOOLEAN DEFAULT true COMMENT 'باک پر شده',
    receipt_number VARCHAR(50) COMMENT 'شماره رسید',
    receipt_image VARCHAR(500) COMMENT 'تصویر رسید',
    notes TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (car_id) REFERENCES company_cars(id) ON DELETE CASCADE,
    FOREIGN KEY (driver_id) REFERENCES car_drivers(id) ON DELETE CASCADE,
    FOREIGN KEY (trip_id) REFERENCES car_trips(id) ON DELETE SET NULL,
    INDEX idx_car (car_id),
    INDEX idx_date (fuel_date),
    INDEX idx_odometer (odometer_reading)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول تعمیرات و نگهداری
CREATE TABLE IF NOT EXISTS car_maintenance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    car_id INT NOT NULL,
    
    -- نوع سرویس
    maintenance_type ENUM('سرویس دوره‌ای','تعمیر','تعویض قطعه','معاینه فنی','شستشو','سایر') NOT NULL,
    service_interval_km INT COMMENT 'فاصله سرویس (کیلومتر)',
    
    -- زمان و مکان
    service_date DATE NOT NULL COMMENT 'تاریخ سرویس',
    workshop_name VARCHAR(255) COMMENT 'نام تعمیرگاه',
    workshop_phone VARCHAR(20) COMMENT 'تلفن تعمیرگاه',
    workshop_address TEXT COMMENT 'آدرس تعمیرگاه',
    
    -- جزئیات سرویس
    odometer_reading DECIMAL(10,2) NOT NULL COMMENT 'کیلومتر هنگام سرویس',
    next_service_odometer DECIMAL(10,2) COMMENT 'کیلومتر سرویس بعدی',
    next_service_date DATE COMMENT 'تاریخ سرویس بعدی',
    
    -- لیست کارها
    work_description TEXT COMMENT 'شرح کارهای انجام شده',
    parts_replaced TEXT COMMENT 'قطعات تعویض شده',
    
    -- تعویض روغن و فیلترها
    oil_changed BOOLEAN DEFAULT false COMMENT 'تعویض روغن موتور',
    oil_filter_changed BOOLEAN DEFAULT false COMMENT 'تعویض فیلتر روغن',
    air_filter_changed BOOLEAN DEFAULT false COMMENT 'تعویض فیلتر هوا',
    cabin_filter_changed BOOLEAN DEFAULT false COMMENT 'تعویض فیلتر کابین',
    fuel_filter_changed BOOLEAN DEFAULT false COMMENT 'تعویض فیلتر بنزین',
    
    -- هزینه‌ها
    parts_cost DECIMAL(15,0) DEFAULT 0 COMMENT 'هزینه قطعات',
    labor_cost DECIMAL(15,0) DEFAULT 0 COMMENT 'هزینه دستمزد',
    total_cost DECIMAL(15,0) DEFAULT 0 COMMENT 'هزینه کل',
    
    -- مستندات
    invoice_number VARCHAR(50) COMMENT 'شماره فاکتور',
    invoice_image VARCHAR(500) COMMENT 'تصویر فاکتور',
    warranty_months INT COMMENT 'گارانتی (ماه)',
    
    -- وضعیت
    status ENUM('برنامه‌ریزی شده','در حال انجام','تکمیل شده','لغو شده') DEFAULT 'تکمیل شده',
    performed_by INT COMMENT 'انجام‌دهنده',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (car_id) REFERENCES company_cars(id) ON DELETE CASCADE,
    FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_car (car_id),
    INDEX idx_date (service_date),
    INDEX idx_type (maintenance_type),
    INDEX idx_next_service (next_service_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول تخلفات رانندگی
CREATE TABLE IF NOT EXISTS car_violations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    car_id INT NOT NULL,
    driver_id INT NOT NULL,
    
    -- اطلاعات تخلف
    violation_date DATE NOT NULL COMMENT 'تاریخ تخلف',
    violation_time TIME COMMENT 'ساعت تخلف',
    violation_code VARCHAR(50) COMMENT 'کد تخلف',
    violation_type VARCHAR(255) NOT NULL COMMENT 'نوع تخلف',
    location VARCHAR(255) COMMENT 'محل تخلف',
    
    -- جریمه
    fine_amount DECIMAL(15,0) COMMENT 'مبلغ جریمه',
    points_deducted INT DEFAULT 0 COMMENT 'نمره منفی',
    
    -- پرداخت
    payment_status ENUM('پرداخت نشده','پرداخت شده','در حال بررسی','بخشوده شده') DEFAULT 'پرداخت نشده',
    payment_date DATE COMMENT 'تاریخ پرداخت',
    payment_reference VARCHAR(100) COMMENT 'شماره مرجع پرداخت',
    
    -- مستندات
    violation_notice_image VARCHAR(500) COMMENT 'تصویر برگه جریمه',
    payment_receipt_image VARCHAR(500) COMMENT 'تصویر رسید پرداخت',
    
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (car_id) REFERENCES company_cars(id) ON DELETE CASCADE,
    FOREIGN KEY (driver_id) REFERENCES car_drivers(id) ON DELETE CASCADE,
    INDEX idx_car (car_id),
    INDEX idx_driver (driver_id),
    INDEX idx_date (violation_date),
    INDEX idx_payment_status (payment_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول حوادث و تصادفات
CREATE TABLE IF NOT EXISTS car_accidents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    car_id INT NOT NULL,
    driver_id INT NOT NULL,
    
    -- اطلاعات حادثه
    accident_date DATE NOT NULL COMMENT 'تاریخ حادثه',
    accident_time TIME COMMENT 'ساعت حادثه',
    location VARCHAR(255) COMMENT 'محل حادثه',
    weather_condition VARCHAR(100) COMMENT 'شرایط آب و هوایی',
    road_condition VARCHAR(100) COMMENT 'شرایط جاده',
    
    -- نوع حادثه
    accident_type ENUM('تصادف','واژگونی','برخورد با مانع','سرقت','آتش‌سوزی','سایر') NOT NULL,
    severity ENUM('جزئی','متوسط','شدید','فوتی') NOT NULL,
    fault_party ENUM('راننده ما','طرف مقابل','مشترک','نامشخص') DEFAULT 'نامشخص',
    
    -- خسارات
    damage_description TEXT COMMENT 'شرح خسارات',
    estimated_repair_cost DECIMAL(15,0) COMMENT 'هزینه تخمینی تعمیر',
    actual_repair_cost DECIMAL(15,0) COMMENT 'هزینه واقعی تعمیر',
    
    -- اطلاعات طرف مقابل
    other_party_name VARCHAR(255) COMMENT 'نام طرف مقابل',
    other_party_phone VARCHAR(20) COMMENT 'تلفن طرف مقابل',
    other_party_insurance VARCHAR(100) COMMENT 'بیمه طرف مقابل',
    other_party_plate VARCHAR(20) COMMENT 'پلاک طرف مقابل',
    
    -- پیگیری
    police_report_number VARCHAR(50) COMMENT 'شماره گزارش پلیس',
    insurance_claim_number VARCHAR(50) COMMENT 'شماره پرونده بیمه',
    insurance_payment DECIMAL(15,0) COMMENT 'پرداخت بیمه',
    
    -- مستندات
    accident_images TEXT COMMENT 'تصاویر حادثه (JSON)',
    police_report_image VARCHAR(500) COMMENT 'تصویر گزارش پلیس',
    insurance_docs TEXT COMMENT 'مستندات بیمه (JSON)',
    
    -- وضعیت
    status ENUM('در حال بررسی','در حال تعمیر','تکمیل شده','بسته شده') DEFAULT 'در حال بررسی',
    notes TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (car_id) REFERENCES company_cars(id) ON DELETE CASCADE,
    FOREIGN KEY (driver_id) REFERENCES car_drivers(id) ON DELETE CASCADE,
    INDEX idx_car (car_id),
    INDEX idx_driver (driver_id),
    INDEX idx_date (accident_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول رزرو خودرو
CREATE TABLE IF NOT EXISTS car_reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_code VARCHAR(20) UNIQUE NOT NULL COMMENT 'کد رزرو',
    car_id INT NOT NULL,
    reserved_by INT NOT NULL COMMENT 'رزرو کننده',
    driver_id INT NULL COMMENT 'راننده',
    
    -- زمان رزرو
    reservation_from DATETIME NOT NULL COMMENT 'شروع رزرو',
    reservation_to DATETIME NOT NULL COMMENT 'پایان رزرو',
    
    -- جزئیات
    purpose VARCHAR(255) COMMENT 'هدف',
    destination VARCHAR(255) COMMENT 'مقصد',
    passengers_count INT DEFAULT 1 COMMENT 'تعداد مسافران',
    special_requirements TEXT COMMENT 'نیازهای خاص',
    
    -- تایید
    approved_by INT COMMENT 'تایید کننده',
    approval_date DATETIME COMMENT 'تاریخ تایید',
    rejection_reason TEXT COMMENT 'دلیل رد',
    
    -- وضعیت
    status ENUM('در انتظار تایید','تایید شده','رد شده','استفاده شده','لغو شده') DEFAULT 'در انتظار تایید',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (car_id) REFERENCES company_cars(id) ON DELETE CASCADE,
    FOREIGN KEY (reserved_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (driver_id) REFERENCES car_drivers(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_car (car_id),
    INDEX idx_reserved_by (reserved_by),
    INDEX idx_dates (reservation_from, reservation_to),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول هشدارها و یادآوری‌ها
CREATE TABLE IF NOT EXISTS car_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    car_id INT NOT NULL,
    
    -- نوع هشدار
    alert_type ENUM('بیمه','معاینه فنی','سرویس','تعویض روغن','تعویض لاستیک','گواهینامه','سایر') NOT NULL,
    alert_title VARCHAR(255) NOT NULL COMMENT 'عنوان هشدار',
    alert_description TEXT COMMENT 'توضیحات',
    
    -- زمان
    alert_date DATE NOT NULL COMMENT 'تاریخ هشدار',
    days_before INT DEFAULT 7 COMMENT 'چند روز قبل هشدار دهد',
    
    -- وضعیت
    is_active BOOLEAN DEFAULT true,
    is_sent BOOLEAN DEFAULT false COMMENT 'ارسال شده',
    sent_date DATETIME COMMENT 'تاریخ ارسال',
    
    -- دریافت‌کنندگان
    notify_driver BOOLEAN DEFAULT true COMMENT 'اطلاع به راننده',
    notify_admin BOOLEAN DEFAULT true COMMENT 'اطلاع به مدیر',
    additional_emails TEXT COMMENT 'ایمیل‌های اضافی',
    
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (car_id) REFERENCES company_cars(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_car (car_id),
    INDEX idx_date (alert_date),
    INDEX idx_type (alert_type),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول لاگ موقعیت GPS
CREATE TABLE IF NOT EXISTS car_gps_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    car_id INT NOT NULL,
    trip_id INT NULL,
    
    -- موقعیت
    latitude DECIMAL(10, 8) NOT NULL COMMENT 'عرض جغرافیایی',
    longitude DECIMAL(11, 8) NOT NULL COMMENT 'طول جغرافیایی',
    altitude DECIMAL(7,2) COMMENT 'ارتفاع',
    speed DECIMAL(5,2) COMMENT 'سرعت (km/h)',
    heading INT COMMENT 'جهت (درجه)',
    accuracy INT COMMENT 'دقت (متر)',
    
    -- زمان
    recorded_at TIMESTAMP NOT NULL,
    
    -- اطلاعات اضافی
    address VARCHAR(500) COMMENT 'آدرس تقریبی',
    is_moving BOOLEAN DEFAULT false COMMENT 'در حال حرکت',
    engine_on BOOLEAN COMMENT 'موتور روشن',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (car_id) REFERENCES company_cars(id) ON DELETE CASCADE,
    FOREIGN KEY (trip_id) REFERENCES car_trips(id) ON DELETE SET NULL,
    INDEX idx_car (car_id),
    INDEX idx_trip (trip_id),
    INDEX idx_time (recorded_at),
    INDEX idx_location (latitude, longitude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول امتیازات رانندگی
CREATE TABLE IF NOT EXISTS driver_scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    driver_id INT NOT NULL,
    
    -- امتیازات
    safe_driving_score INT DEFAULT 100 COMMENT 'امتیاز رانندگی ایمن',
    fuel_efficiency_score INT DEFAULT 100 COMMENT 'امتیاز مصرف سوخت',
    maintenance_score INT DEFAULT 100 COMMENT 'امتیاز نگهداری',
    punctuality_score INT DEFAULT 100 COMMENT 'امتیاز وقت‌شناسی',
    overall_score INT DEFAULT 100 COMMENT 'امتیاز کلی',
    
    -- آمار
    total_trips INT DEFAULT 0 COMMENT 'تعداد کل سفرها',
    total_distance DECIMAL(10,2) DEFAULT 0 COMMENT 'مجموع مسافت',
    total_violations INT DEFAULT 0 COMMENT 'تعداد تخلفات',
    total_accidents INT DEFAULT 0 COMMENT 'تعداد حوادث',
    on_time_returns INT DEFAULT 0 COMMENT 'تعداد بازگشت‌های به موقع',
    late_returns INT DEFAULT 0 COMMENT 'تعداد تاخیرها',
    
    -- محاسبه شده
    avg_fuel_consumption DECIMAL(5,2) COMMENT 'میانگین مصرف سوخت',
    avg_speed DECIMAL(5,2) COMMENT 'میانگین سرعت',
    
    last_calculated TIMESTAMP NULL COMMENT 'آخرین محاسبه',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (driver_id) REFERENCES car_drivers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_driver (driver_id),
    INDEX idx_overall_score (overall_score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول تنظیمات سیستم خودرو
CREATE TABLE IF NOT EXISTS car_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type VARCHAR(50),
    description TEXT,
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- درج تنظیمات پیش‌فرض
INSERT INTO car_settings (setting_key, setting_value, setting_type, description) VALUES
('default_fuel_tank_capacity', '60', 'number', 'ظرفیت پیش‌فرض باک سوخت (لیتر)'),
('service_interval_km', '5000', 'number', 'فاصله سرویس دوره‌ای (کیلومتر)'),
('oil_change_interval_km', '5000', 'number', 'فاصله تعویض روغن (کیلومتر)'),
('insurance_alert_days', '30', 'number', 'هشدار انقضای بیمه (روز قبل)'),
('technical_inspection_alert_days', '30', 'number', 'هشدار معاینه فنی (روز قبل)'),
('max_speed_limit_highway', '120', 'number', 'حداکثر سرعت مجاز بزرگراه'),
('max_speed_limit_city', '60', 'number', 'حداکثر سرعت مجاز شهری'),
('fuel_efficiency_threshold', '8', 'number', 'آستانه مصرف سوخت (لیتر/100کیلومتر)'),
('enable_gps_tracking', 'true', 'boolean', 'فعال‌سازی ردیابی GPS'),
('gps_update_interval_seconds', '30', 'number', 'فاصله به‌روزرسانی GPS (ثانیه)'),
('enable_speed_alerts', 'true', 'boolean', 'فعال‌سازی هشدار سرعت'),
('enable_geofence', 'false', 'boolean', 'فعال‌سازی محدوده جغرافیایی'),
('working_hours_start', '08:00', 'time', 'شروع ساعت کاری'),
('working_hours_end', '17:00', 'time', 'پایان ساعت کاری'),
('overtime_rate_per_hour', '50000', 'number', 'نرخ اضافه‌کاری (ریال/ساعت)')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- ایندکس‌های اضافی برای بهبود کارایی
CREATE INDEX idx_car_current_status ON company_cars(current_status, is_active);
CREATE INDEX idx_handover_active ON car_handovers(status, handover_date);
CREATE INDEX idx_maintenance_next ON car_maintenance(next_service_date, car_id);
CREATE INDEX idx_reservation_active ON car_reservations(status, reservation_from, reservation_to);
CREATE INDEX idx_driver_active ON car_drivers(status, driver_score);

-- ویوهای مفید
CREATE OR REPLACE VIEW v_car_current_status AS
SELECT 
    c.*,
    d.full_name as current_driver_name,
    h.handover_date as last_handover_date,
    h.handover_odometer as last_handover_odometer,
    m.next_service_date,
    m.next_service_odometer,
    DATEDIFF(c.insurance_expiry_date, CURDATE()) as insurance_days_remaining,
    DATEDIFF(c.technical_inspection_expiry, CURDATE()) as inspection_days_remaining
FROM company_cars c
LEFT JOIN car_drivers d ON c.current_driver_id = d.id
LEFT JOIN (
    SELECT car_id, MAX(handover_date) as handover_date, MAX(handover_odometer) as handover_odometer
    FROM car_handovers
    WHERE status = 'در حال استفاده'
    GROUP BY car_id
) h ON c.id = h.car_id
LEFT JOIN (
    SELECT car_id, MIN(next_service_date) as next_service_date, MIN(next_service_odometer) as next_service_odometer
    FROM car_maintenance
    WHERE next_service_date >= CURDATE()
    GROUP BY car_id
) m ON c.id = m.car_id;

CREATE OR REPLACE VIEW v_driver_performance AS
SELECT 
    d.*,
    ds.overall_score,
    ds.total_trips,
    ds.total_distance,
    ds.total_violations,
    ds.total_accidents,
    ds.avg_fuel_consumption,
    COUNT(DISTINCT h.id) as total_handovers,
    COUNT(DISTINCT v.id) as violation_count,
    COUNT(DISTINCT a.id) as accident_count
FROM car_drivers d
LEFT JOIN driver_scores ds ON d.id = ds.driver_id
LEFT JOIN car_handovers h ON d.id = h.driver_id
LEFT JOIN car_violations v ON d.id = v.driver_id AND v.violation_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
LEFT JOIN car_accidents a ON d.id = a.driver_id AND a.accident_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
GROUP BY d.id;