-- ایجاد پایگاه داده
CREATE DATABASE IF NOT EXISTS aala_niroo;
USE aala_niroo;

-- جدول دارایی‌ها
CREATE TABLE IF NOT EXISTS assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    serial_number VARCHAR(255) UNIQUE,
    purchase_date DATE,
    status ENUM('فعال', 'غیرفعال', 'در حال تعمیر') DEFAULT 'فعال',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- جدول مشتریان
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    company VARCHAR(255),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- جدول کاربران سیستم
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('ادمین', 'کاربر عادی') DEFAULT 'کاربر عادی',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- درج کاربر پیش‌فرض (admin)
-- رمز عبور: 123456 (به صورت hash شده)
INSERT INTO users (username, password, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ادمین');
-- ایجاد جدول جدید برای ذخیره اطلاعات کامل انتساب
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
    installation_photo VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assignment_id) REFERENCES asset_assignments(id) ON DELETE CASCADE
);