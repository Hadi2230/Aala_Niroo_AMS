diff --git a/database.sql b/database.sql
--- a/database.sql
+++ b/database.sql
@@ -1,63 +1,131 @@
--- ایجاد پایگاه داده
-CREATE DATABASE IF NOT EXISTS aala_niroo;
-USE aala_niroo;
-
--- جدول دارایی‌ها
-CREATE TABLE IF NOT EXISTS assets (
-    id INT AUTO_INCREMENT PRIMARY KEY,
-    name VARCHAR(255) NOT NULL,
-    serial_number VARCHAR(255) UNIQUE,
-    purchase_date DATE,
-    status ENUM('فعال', 'غیرفعال', 'در حال تعمیر') DEFAULT 'فعال',
-    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
-);
-
--- جدول مشتریان
-CREATE TABLE IF NOT EXISTS customers (
-    id INT AUTO_INCREMENT PRIMARY KEY,
-    full_name VARCHAR(255) NOT NULL,
-    phone VARCHAR(20),
-    company VARCHAR(255),
-    address TEXT,
-    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
-);
-
--- جدول کاربران سیستم
-CREATE TABLE IF NOT EXISTS users (
-    id INT AUTO_INCREMENT PRIMARY KEY,
-    username VARCHAR(50) UNIQUE NOT NULL,
-    password VARCHAR(255) NOT NULL,
-    role ENUM('ادمین', 'کاربر عادی') DEFAULT 'کاربر عادی',
-    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
-);
-
--- درج کاربر پیش‌فرض (admin)
--- رمز عبور: 123456 (به صورت hash شده)
-INSERT INTO users (username, password, role) VALUES
-('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ادمین');
--- ایجاد جدول جدید برای ذخیره اطلاعات کامل انتساب
-CREATE TABLE IF NOT EXISTS assignment_details (
-    id INT AUTO_INCREMENT PRIMARY KEY,
-    assignment_id INT NOT NULL,
-    installation_date DATE,
-    delivery_person VARCHAR(255),
-    installation_address TEXT,
-    warranty_start_date DATE,
-    warranty_conditions TEXT,
-    employer_name VARCHAR(255),
-    employer_phone VARCHAR(20),
-    recipient_name VARCHAR(255),
-    recipient_phone VARCHAR(20),
-    installer_name VARCHAR(255),
-    installation_start_date DATE,
-    installation_end_date DATE,
-    temporary_delivery_date DATE,
-    permanent_delivery_date DATE,
-    first_service_date DATE,
-    post_installation_commitments TEXT,
-    notes TEXT,
-    installation_photo VARCHAR(255),
-    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
-    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
-    FOREIGN KEY (assignment_id) REFERENCES asset_assignments(id) ON DELETE CASCADE
-);
+-- ایجاد پایگاه داده
+CREATE DATABASE IF NOT EXISTS aala_niroo;
+USE aala_niroo;
+
+-- جدول انواع دارایی‌ها
+CREATE TABLE IF NOT EXISTS asset_types (
+    id INT AUTO_INCREMENT PRIMARY KEY,
+    name VARCHAR(50) NOT NULL UNIQUE,
+    display_name VARCHAR(100) NOT NULL,
+    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
+    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
+) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;
+
+-- جدول فیلدهای سفارشی
+CREATE TABLE IF NOT EXISTS asset_fields (
+    id INT AUTO_INCREMENT PRIMARY KEY,
+    type_id INT,
+    field_name VARCHAR(100),
+    field_type ENUM('text','number','date','select','file'),
+    is_required BOOLEAN DEFAULT false,
+    options TEXT,
+    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
+    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
+    FOREIGN KEY (type_id) REFERENCES asset_types(id) ON DELETE CASCADE,
+    INDEX idx_type_id (type_id)
+) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;
+
+-- جدول دارایی‌ها (کامل)
+CREATE TABLE IF NOT EXISTS assets (
+    id INT AUTO_INCREMENT PRIMARY KEY,
+    name VARCHAR(255) NOT NULL,
+    type_id INT,
+    serial_number VARCHAR(255) UNIQUE,
+    purchase_date DATE,
+    status ENUM('فعال', 'غیرفعال', 'در حال تعمیر', 'آماده بهره‌برداری') DEFAULT 'فعال',
+    brand VARCHAR(255),
+    model VARCHAR(255),
+    power_capacity VARCHAR(100),
+    engine_type VARCHAR(100),
+    consumable_type VARCHAR(100),
+    engine_model VARCHAR(255),
+    engine_serial VARCHAR(255),
+    alternator_model VARCHAR(255),
+    alternator_serial VARCHAR(255),
+    device_model VARCHAR(255),
+    device_serial VARCHAR(255),
+    control_panel_model VARCHAR(255),
+    breaker_model VARCHAR(255),
+    fuel_tank_specs TEXT,
+    battery VARCHAR(255),
+    battery_charger VARCHAR(255),
+    heater VARCHAR(255),
+    oil_capacity VARCHAR(255),
+    radiator_capacity VARCHAR(255),
+    antifreeze VARCHAR(255),
+    other_items TEXT,
+    workshop_entry_date DATE,
+    workshop_exit_date DATE,
+    datasheet_link VARCHAR(500),
+    engine_manual_link VARCHAR(500),
+    alternator_manual_link VARCHAR(500),
+    control_panel_manual_link VARCHAR(500),
+    description TEXT,
+    oil_filter_part VARCHAR(100),
+    fuel_filter_part VARCHAR(100),
+    water_fuel_filter_part VARCHAR(100),
+    air_filter_part VARCHAR(100),
+    water_filter_part VARCHAR(100),
+    custom_data TEXT,
+    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
+    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
+    INDEX idx_type_id (type_id),
+    INDEX idx_status (status),
+    INDEX idx_serial (serial_number),
+    INDEX idx_name (name)
+) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;
+
+-- جدول مشتریان
+CREATE TABLE IF NOT EXISTS customers (
+    id INT AUTO_INCREMENT PRIMARY KEY,
+    full_name VARCHAR(255) NOT NULL,
+    phone VARCHAR(20),
+    company VARCHAR(255),
+    address TEXT,
+    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
+);
+
+-- جدول کاربران سیستم
+CREATE TABLE IF NOT EXISTS users (
+    id INT AUTO_INCREMENT PRIMARY KEY,
+    username VARCHAR(50) UNIQUE NOT NULL,
+    password VARCHAR(255) NOT NULL,
+    full_name VARCHAR(255),
+    role ENUM('ادمین', 'کاربر عادی', 'اپراتور') DEFAULT 'کاربر عادی',
+    is_active BOOLEAN DEFAULT true,
+    last_login TIMESTAMP NULL,
+    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
+    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
+) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;
+
+-- درج کاربر پیش‌فرض (admin/admin)
+INSERT INTO users (username, password, full_name, role)
+SELECT 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'مدیر سیستم', 'ادمین'
+WHERE NOT EXISTS (SELECT 1 FROM users WHERE username='admin');
+-- ایجاد جدول جدید برای ذخیره اطلاعات کامل انتساب
+CREATE TABLE IF NOT EXISTS assignment_details (
+    id INT AUTO_INCREMENT PRIMARY KEY,
+    assignment_id INT NOT NULL,
+    installation_date DATE,
+    delivery_person VARCHAR(255),
+    installation_address TEXT,
+    warranty_end_date DATE,
+    warranty_start_date DATE,
+    warranty_conditions TEXT,
+    employer_name VARCHAR(255),
+    employer_phone VARCHAR(20),
+    recipient_name VARCHAR(255),
+    recipient_phone VARCHAR(20),
+    installer_name VARCHAR(255),
+    installation_start_date DATE,
+    installation_end_date DATE,
+    temporary_delivery_date DATE,
+    permanent_delivery_date DATE,
+    first_service_date DATE,
+    post_installation_commitments TEXT,
+    notes TEXT,
+    installation_photo VARCHAR(500),
+    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
+    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
+    FOREIGN KEY (assignment_id) REFERENCES asset_assignments(id) ON DELETE CASCADE
+);