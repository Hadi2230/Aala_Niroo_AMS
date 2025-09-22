-- =====================================================
-- Aala Niroo AMS - Complete Database Schema
-- سیستم مدیریت دارایی‌ها و انتساب دستگاه‌ها
-- =====================================================

-- ایجاد پایگاه داده
CREATE DATABASE IF NOT EXISTS aala_niroo_ams 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_persian_ci;

USE aala_niroo_ams;

-- =====================================================
-- 1. جدول کاربران سیستم
-- =====================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(255),
    email VARCHAR(255),
    phone VARCHAR(20),
    role ENUM('ادمین', 'کاربر عادی', 'مدیر', 'کارشناس') DEFAULT 'کاربر عادی',
    department VARCHAR(100),
    position VARCHAR(100),
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- =====================================================
-- 2. جدول انواع دارایی‌ها
-- =====================================================
CREATE TABLE IF NOT EXISTS asset_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    category VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- =====================================================
-- 3. جدول دارایی‌ها
-- =====================================================
CREATE TABLE IF NOT EXISTS assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    serial_number VARCHAR(255) UNIQUE,
    model VARCHAR(255),
    brand VARCHAR(100),
    asset_type_id INT,
    purchase_date DATE,
    purchase_price DECIMAL(12,2),
    supplier VARCHAR(255),
    warranty_start_date DATE,
    warranty_end_date DATE,
    warranty_conditions TEXT,
    status ENUM('فعال', 'غیرفعال', 'در حال تعمیر', 'از رده خارج', 'مفقود') DEFAULT 'فعال',
    location VARCHAR(255),
    current_assignee_id INT,
    notes TEXT,
    image_path VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (asset_type_id) REFERENCES asset_types(id) ON DELETE SET NULL,
    FOREIGN KEY (current_assignee_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- =====================================================
-- 4. جدول مشتریان
-- =====================================================
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    company VARCHAR(255),
    phone VARCHAR(20),
    mobile VARCHAR(20),
    email VARCHAR(255),
    address TEXT,
    city VARCHAR(100),
    postal_code VARCHAR(20),
    national_id VARCHAR(20),
    economic_code VARCHAR(20),
    customer_type ENUM('حقیقی', 'حقوقی') DEFAULT 'حقیقی',
    status ENUM('فعال', 'غیرفعال', 'معلق') DEFAULT 'فعال',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- =====================================================
-- 5. جدول انتساب دارایی‌ها
-- =====================================================
CREATE TABLE IF NOT EXISTS asset_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    customer_id INT NOT NULL,
    assigned_by INT NOT NULL,
    assignment_date DATE NOT NULL,
    status ENUM('فعال', 'بازگشت', 'تعمیر', 'از رده خارج') DEFAULT 'فعال',
    return_date DATE NULL,
    return_reason TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- =====================================================
-- 6. جدول جزئیات انتساب
-- =====================================================
CREATE TABLE IF NOT EXISTS assignment_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    installation_date DATE,
    delivery_person VARCHAR(255),
    installation_address TEXT,
    warranty_start_date DATE,
    warranty_conditions TEXT,
    employer_name VARCHAR(255),
    employer_phone VARCHAR(20),
    recipient_name VARCHAR(255),
    recipient_phone VARCHAR(20),
    installer_name VARCHAR(255),
    installation_start_date DATE,
    installation_end_date DATE,
    temporary_delivery_date DATE,
    permanent_delivery_date DATE,
    first_service_date DATE,
    post_installation_commitments TEXT,
    notes TEXT,
    installation_photo VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assignment_id) REFERENCES asset_assignments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- =====================================================
-- 7. جدول درخواست‌ها
-- =====================================================
CREATE TABLE IF NOT EXISTS requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_number VARCHAR(50) UNIQUE NOT NULL,
    requester_id INT,
    requester_name VARCHAR(255) NOT NULL,
    requester_phone VARCHAR(20),
    requester_email VARCHAR(255),
    item_name VARCHAR(255) NOT NULL,
    item_description TEXT,
    quantity INT DEFAULT 1,
    unit_price DECIMAL(12,2),
    total_price DECIMAL(12,2),
    priority ENUM('کم', 'متوسط', 'زیاد', 'فوری') DEFAULT 'متوسط',
    status ENUM('در انتظار تأیید', 'تأیید شده', 'در حال انجام', 'تکمیل شده', 'رد شده', 'لغو شده') DEFAULT 'در انتظار تأیید',
    request_type ENUM('خرید', 'تعمیر', 'نگهداری', 'سایر') DEFAULT 'خرید',
    department VARCHAR(100),
    budget_code VARCHAR(50),
    required_date DATE,
    approved_by INT,
    approved_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    rejection_reason TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- =====================================================
-- 8. جدول گردش کار درخواست‌ها
-- =====================================================
CREATE TABLE IF NOT EXISTS request_workflow (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    assigned_to INT NOT NULL,
    assigned_by INT NOT NULL,
    status ENUM('ارجاع شده', 'در انتظار', 'در حال بررسی', 'تأیید شده', 'رد شده', 'تکمیل شده') DEFAULT 'ارجاع شده',
    comments TEXT,
    action_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    due_date DATE,
    priority ENUM('کم', 'متوسط', 'زیاد', 'فوری') DEFAULT 'متوسط',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- =====================================================
-- 9. جدول فایل‌های ضمیمه درخواست‌ها
-- =====================================================
CREATE TABLE IF NOT EXISTS request_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT,
    file_type VARCHAR(100),
    uploaded_by INT NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- =====================================================
-- 10. جدول اعلان‌ها
-- =====================================================
CREATE TABLE IF NOT EXISTS request_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    request_id INT,
    type ENUM('new_request', 'assignment', 'status_change', 'comment', 'reminder', 'system') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- =====================================================
-- 11. جدول لاگ سیستم
-- =====================================================
CREATE TABLE IF NOT EXISTS system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- =====================================================
-- 12. جدول مدیریت بازدیدها
-- =====================================================
CREATE TABLE IF NOT EXISTS visit_management (
    id INT AUTO_INCREMENT PRIMARY KEY,
    visitor_name VARCHAR(255) NOT NULL,
    visitor_phone VARCHAR(20),
    visitor_company VARCHAR(255),
    visit_purpose TEXT,
    host_name VARCHAR(255),
    host_department VARCHAR(100),
    visit_date DATE NOT NULL,
    visit_time TIME NOT NULL,
    exit_time TIME,
    status ENUM('در حال بازدید', 'تکمیل شده', 'لغو شده') DEFAULT 'در حال بازدید',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- =====================================================
-- 13. جدول ابزارها
-- =====================================================
CREATE TABLE IF NOT EXISTS tools (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    tool_code VARCHAR(50) UNIQUE,
    category VARCHAR(100),
    location VARCHAR(255),
    status ENUM('موجود', 'در حال استفاده', 'تعمیر', 'مفقود') DEFAULT 'موجود',
    current_user_id INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (current_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- =====================================================
-- 14. جدول تأمین‌کنندگان
-- =====================================================
CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    contact_person VARCHAR(255),
    phone VARCHAR(20),
    email VARCHAR(255),
    address TEXT,
    city VARCHAR(100),
    website VARCHAR(255),
    tax_id VARCHAR(50),
    status ENUM('فعال', 'غیرفعال') DEFAULT 'فعال',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- =====================================================
-- 15. جدول نگهداری و تعمیرات
-- =====================================================
CREATE TABLE IF NOT EXISTS maintenance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    maintenance_type ENUM('پیشگیرانه', 'تعمیری', 'کالیبراسیون', 'سایر') NOT NULL,
    description TEXT,
    scheduled_date DATE,
    completed_date DATE,
    technician VARCHAR(255),
    cost DECIMAL(12,2),
    status ENUM('برنامه‌ریزی شده', 'در حال انجام', 'تکمیل شده', 'لغو شده') DEFAULT 'برنامه‌ریزی شده',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- =====================================================
-- 16. جدول موجودی انبار
-- =====================================================
CREATE TABLE IF NOT EXISTS inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(255) NOT NULL,
    item_code VARCHAR(50) UNIQUE,
    category VARCHAR(100),
    current_stock INT DEFAULT 0,
    min_stock INT DEFAULT 0,
    max_stock INT DEFAULT 0,
    unit VARCHAR(50),
    unit_price DECIMAL(12,2),
    supplier_id INT,
    location VARCHAR(255),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- =====================================================
-- 17. جدول تراکنش‌های انبار
-- =====================================================
CREATE TABLE IF NOT EXISTS inventory_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inventory_id INT NOT NULL,
    transaction_type ENUM('ورود', 'خروج', 'تعدیل', 'انتقال') NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(12,2),
    total_value DECIMAL(12,2),
    reference_number VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (inventory_id) REFERENCES inventory(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- =====================================================
-- 18. جدول تنظیمات سیستم
-- =====================================================
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('text', 'number', 'boolean', 'json') DEFAULT 'text',
    description TEXT,
    is_public BOOLEAN DEFAULT FALSE,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- =====================================================
-- ایندکس‌ها برای بهبود عملکرد
-- =====================================================

-- ایندکس‌های جدول users
CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_status ON users(status);

-- ایندکس‌های جدول assets
CREATE INDEX idx_assets_serial ON assets(serial_number);
CREATE INDEX idx_assets_status ON assets(status);
CREATE INDEX idx_assets_type ON assets(asset_type_id);

-- ایندکس‌های جدول customers
CREATE INDEX idx_customers_phone ON customers(phone);
CREATE INDEX idx_customers_company ON customers(company);
CREATE INDEX idx_customers_status ON customers(status);

-- ایندکس‌های جدول requests
CREATE INDEX idx_requests_number ON requests(request_number);
CREATE INDEX idx_requests_status ON requests(status);
CREATE INDEX idx_requests_priority ON requests(priority);
CREATE INDEX idx_requests_created_at ON requests(created_at);

-- ایندکس‌های جدول request_workflow
CREATE INDEX idx_workflow_assigned_to ON request_workflow(assigned_to);
CREATE INDEX idx_workflow_status ON request_workflow(status);
CREATE INDEX idx_workflow_due_date ON request_workflow(due_date);

-- ایندکس‌های جدول system_logs
CREATE INDEX idx_logs_user_id ON system_logs(user_id);
CREATE INDEX idx_logs_action ON system_logs(action);
CREATE INDEX idx_logs_created_at ON system_logs(created_at);

-- =====================================================
-- داده‌های اولیه
-- =====================================================

-- درج کاربر ادمین پیش‌فرض
INSERT INTO users (username, password, full_name, role, status) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'مدیر سیستم', 'ادمین', 'active');

-- درج انواع دارایی‌های پیش‌فرض
INSERT INTO asset_types (name, display_name, category) VALUES
('computer', 'کامپیوتر', 'IT'),
('printer', 'چاپگر', 'IT'),
('phone', 'تلفن', 'IT'),
('furniture', 'مبلمان', 'اداری'),
('vehicle', 'وسیله نقلیه', 'عملیاتی'),
('generator', 'ژنراتور', 'تجهیزات'),
('camera', 'دوربین', 'امنیتی'),
('other', 'سایر', 'عمومی');

-- درج تنظیمات پیش‌فرض سیستم
INSERT INTO system_settings (setting_key, setting_value, setting_type, description, is_public) VALUES
('site_name', 'Aala Niroo AMS', 'text', 'نام سایت', TRUE),
('site_description', 'سیستم مدیریت دارایی‌ها و انتساب دستگاه‌ها', 'text', 'توضیحات سایت', TRUE),
('timezone', 'Asia/Tehran', 'text', 'منطقه زمانی', TRUE),
('date_format', 'Y/m/d', 'text', 'فرمت تاریخ', TRUE),
('items_per_page', '20', 'number', 'تعداد آیتم در هر صفحه', FALSE),
('auto_backup', '1', 'boolean', 'پشتیبان‌گیری خودکار', FALSE),
('maintenance_mode', '0', 'boolean', 'حالت تعمیر', FALSE);

-- =====================================================
-- پایان فایل دیتابیس
-- =====================================================