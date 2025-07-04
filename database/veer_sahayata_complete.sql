-- ======================================================
-- VEER SAHAYATA - COMPLETE DATABASE SCHEMA v2.0
-- Comprehensive and consolidated database structure 
-- for the Veer Sahayata Portal
-- ======================================================
-- This script consolidates all database requirements into
-- a single, optimized schema with all necessary tables,
-- indexes, foreign keys, and sample data.
-- ======================================================

-- Create database
CREATE DATABASE IF NOT EXISTS veer_sahayata 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE veer_sahayata;

-- ======================================================
-- CORE USER MANAGEMENT TABLES
-- ======================================================

-- Users table (Enhanced with settings and security features)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    service_number VARCHAR(50) UNIQUE NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    full_name VARCHAR(100) NOT NULL,
    rank_designation VARCHAR(50),
    service_type ENUM('Army', 'Navy', 'Air Force') NOT NULL,
    date_of_birth DATE,
    date_of_joining DATE,
    date_of_retirement DATE,
    phone VARCHAR(15),
    email VARCHAR(100),
    address TEXT,
    photo VARCHAR(255),
    status ENUM('active', 'inactive', 'deceased') DEFAULT 'active',
    
    -- Settings and Security columns
    email_notifications TINYINT(1) DEFAULT 1,
    sms_notifications TINYINT(1) DEFAULT 1,
    push_notifications TINYINT(1) DEFAULT 1,
    profile_visibility ENUM('private', 'restricted', 'public') DEFAULT 'restricted',
    data_sharing TINYINT(1) DEFAULT 0,
    sms_2fa TINYINT(1) DEFAULT 0,
    email_2fa TINYINT(1) DEFAULT 0,
    last_login TIMESTAMP NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_service_number (service_number),
    INDEX idx_username (username),
    INDEX idx_status (status),
    INDEX idx_service_type (service_type),
    INDEX idx_last_login (last_login)
);

-- Dependents table
CREATE TABLE dependents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    relationship ENUM('spouse', 'child', 'parent', 'other') NOT NULL,
    date_of_birth DATE,
    aadhar_number VARCHAR(12),
    photo VARCHAR(255),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_relationship (relationship)
);

-- User preferences table for additional settings
CREATE TABLE user_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    preference_key VARCHAR(100) NOT NULL,
    preference_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_preference (user_id, preference_key),
    INDEX idx_preference_key (preference_key)
);

-- Login activity tracking
CREATE TABLE login_activity (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    device_type VARCHAR(50),
    location VARCHAR(100),
    status ENUM('success', 'failed', 'blocked') DEFAULT 'success',
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_login (user_id, login_time),
    INDEX idx_status (status)
);

-- ======================================================
-- DOCUMENT & VERIFICATION MANAGEMENT TABLES
-- ======================================================

-- Documents table (Enhanced)
CREATE TABLE documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    document_type ENUM('aadhar', 'pan', 'driving_license', 'electric_bill', 'service_record', 
                      'service_certificate', 'identity_proof', 'address_proof', 'bank_details', 
                      'medical_certificate', 'nok_details', 'pension_order', 'photograph', 'live_photo', 'other') NOT NULL,
    document_name VARCHAR(100),
    file_path VARCHAR(255) NOT NULL,
    file_size INT DEFAULT 0,
    file_type VARCHAR(100),
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    reviewed_by INT NULL,
    admin_remarks TEXT,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id),
    INDEX idx_user_id (user_id),
    INDEX idx_document_type (document_type),
    INDEX idx_status (status),
    INDEX idx_uploaded_at (uploaded_at)
);

-- User Verifications table (Enhanced workflow)
CREATE TABLE user_verifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    verification_type ENUM('new_registration', 'document_update', 'profile_verification', 
                          'service_verification', 'pension_verification', 'live_verification') NOT NULL,
    supporting_details TEXT,
    declaration_accepted BOOLEAN DEFAULT FALSE,
    status ENUM('pending', 'under_review', 'approved', 'rejected') DEFAULT 'pending',
    admin_remarks TEXT,
    verified_at TIMESTAMP NULL,
    verified_by INT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resubmission_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_verification_type (verification_type),
    INDEX idx_status (status),
    INDEX idx_submitted_at (submitted_at)
);

-- ======================================================
-- PENSION MANAGEMENT TABLES
-- ======================================================

-- Pension table
CREATE TABLE pension (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    pension_type VARCHAR(50),
    monthly_amount DECIMAL(10,2),
    last_disbursed_amount DECIMAL(10,2),
    disbursement_date DATE,
    cgst DECIMAL(8,2) DEFAULT 0,
    sgst DECIMAL(8,2) DEFAULT 0,
    loan_deduction DECIMAL(8,2) DEFAULT 0,
    net_amount DECIMAL(10,2),
    status ENUM('approved', 'pending', 'disbursed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_disbursement_date (disbursement_date)
);

-- Pension history table
CREATE TABLE pension_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pension_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    old_status VARCHAR(20),
    new_status VARCHAR(20),
    old_amount DECIMAL(10,2),
    new_amount DECIMAL(10,2),
    comments TEXT,
    admin_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (pension_id) REFERENCES pension(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_pension_id (pension_id),
    INDEX idx_admin_id (admin_id),
    INDEX idx_created_at (created_at)
);

-- ======================================================
-- LOAN MANAGEMENT TABLES
-- ======================================================

-- Loans table
CREATE TABLE loans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    loan_type VARCHAR(50) NOT NULL,
    loan_amount DECIMAL(12,2) NOT NULL,
    outstanding_balance DECIMAL(12,2) NOT NULL,
    monthly_emi DECIMAL(10,2),
    due_date DATE,
    interest_rate DECIMAL(5,2),
    status ENUM('active', 'closed', 'pending_approval') DEFAULT 'pending_approval',
    purpose TEXT,
    applied_date DATE,
    approved_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_due_date (due_date)
);

-- ======================================================
-- HEALTH & WELFARE MANAGEMENT TABLES
-- ======================================================

-- Health records table
CREATE TABLE health_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    visit_date DATE NOT NULL,
    hospital_name VARCHAR(100),
    doctor_name VARCHAR(100),
    diagnosis TEXT,
    treatment TEXT,
    prescription TEXT,
    echs_center VARCHAR(100),
    next_appointment DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_visit_date (visit_date)
);

-- ======================================================
-- SUPPORTING DATA TABLES
-- ======================================================

-- Colleges table (Enhanced)
CREATE TABLE colleges (
    id INT PRIMARY KEY AUTO_INCREMENT,
    college_name VARCHAR(200) NOT NULL,
    college_code VARCHAR(20) UNIQUE NOT NULL,
    college_type ENUM('Government', 'Private', 'Aided') NOT NULL,
    university_name VARCHAR(200),
    state VARCHAR(50),
    district VARCHAR(50),
    address TEXT,
    contact_number VARCHAR(15),
    email VARCHAR(100),
    website VARCHAR(200),
    principal_name VARCHAR(100),
    established_year YEAR,
    courses_offered TEXT,
    defence_quota_seats INT,
    scholarship_info TEXT,
    admission_process TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_college_code (college_code),
    INDEX idx_state (state),
    INDEX idx_type (college_type),
    INDEX idx_status (status)
);

-- Hospitals table (Enhanced)
CREATE TABLE hospitals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hospital_name VARCHAR(200) NOT NULL,
    hospital_code VARCHAR(20) UNIQUE NOT NULL,
    hospital_type ENUM('Government', 'Private', 'Armed Forces') NOT NULL,
    state VARCHAR(50),
    district VARCHAR(50),
    address TEXT,
    contact_number VARCHAR(15),
    email VARCHAR(100),
    website VARCHAR(200),
    medical_director VARCHAR(100),
    established_year YEAR,
    bed_capacity INT,
    specialties TEXT,
    emergency_services BOOLEAN DEFAULT FALSE,
    veterans_facility BOOLEAN DEFAULT FALSE,
    insurance_accepted VARCHAR(255),
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_hospital_code (hospital_code),
    INDEX idx_state (state),
    INDEX idx_type (hospital_type),
    INDEX idx_status (status)
);

-- News table (Enhanced)
CREATE TABLE news (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    excerpt TEXT,
    image VARCHAR(255),
    category ENUM('general', 'pension', 'health', 'schemes', 'education') DEFAULT 'general',
    author VARCHAR(100),
    publish_date DATETIME,
    status ENUM('published', 'draft') DEFAULT 'draft',
    featured BOOLEAN DEFAULT FALSE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_featured (featured),
    INDEX idx_category (category),
    INDEX idx_publish_date (publish_date)
);

-- Schemes table (Enhanced)
CREATE TABLE schemes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    scheme_name VARCHAR(100) NOT NULL,
    scheme_type ENUM('Central', 'State') NOT NULL,
    description TEXT,
    eligibility_criteria TEXT,
    application_process TEXT,
    documents_required TEXT,
    benefits TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_scheme_type (scheme_type),
    INDEX idx_status (status)
);

-- Business suggestions table
CREATE TABLE business_suggestions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    loan_amount_min DECIMAL(10,2),
    loan_amount_max DECIMAL(10,2),
    business_idea VARCHAR(200) NOT NULL,
    description TEXT,
    investment_required DECIMAL(10,2),
    expected_returns TEXT,
    risk_level ENUM('low', 'medium', 'high'),
    category VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_risk_level (risk_level),
    INDEX idx_category (category)
);

-- ======================================================
-- SYSTEM MANAGEMENT TABLES
-- ======================================================

-- Settings table for system configuration
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_setting_key (setting_key)
);

-- Backup logs table for tracking backup operations
CREATE TABLE backup_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    backup_type ENUM('full', 'partial', 'restore') NOT NULL,
    action ENUM('create', 'restore', 'delete') DEFAULT 'create',
    file_size BIGINT DEFAULT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    INDEX idx_created_at (created_at),
    INDEX idx_created_by (created_by),
    INDEX idx_backup_type (backup_type),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- ======================================================
-- SAMPLE DATA INSERTION
-- ======================================================

-- Insert default admin user
INSERT INTO users (service_number, username, password, role, full_name, service_type, phone, email) 
VALUES ('ADMIN001', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'System Administrator', 'Army', '9999999999', 'admin@veersahayata.gov.in');

-- Insert system settings
INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES
('site_title', 'Veer Sahayata Portal', 'string', 'Main site title'),
('site_description', 'Defence Personnel Welfare Portal', 'string', 'Site description'),
('admin_email', 'admin@veersahayata.gov.in', 'string', 'Administrator email address'),
('backup_schedule_enabled', '0', 'boolean', 'Enable automatic backup scheduling'),
('backup_schedule_type', 'weekly', 'string', 'Backup schedule frequency'),
('backup_schedule_backup_type', 'full', 'string', 'Type of scheduled backup'),
('backup_retention_days', '30', 'number', 'Days to retain backup files'),
('backup_max_files', '10', 'number', 'Maximum number of backup files to keep'),
('maintenance_mode', '0', 'boolean', 'Enable maintenance mode'),
('user_registration_enabled', '1', 'boolean', 'Allow new user registrations'),
('email_verification_required', '1', 'boolean', 'Require email verification for new users'),
('max_file_upload_size', '10485760', 'number', 'Maximum file upload size in bytes (10MB)'),
('session_timeout', '3600', 'number', 'User session timeout in seconds');

-- Insert default user preferences for the admin user
INSERT INTO user_preferences (user_id, preference_key, preference_value) VALUES
(1, 'pension_updates', '1'),
(1, 'document_updates', '1'),
(1, 'verification_updates', '1'),
(1, 'news_updates', '1'),
(1, 'scheme_updates', '1'),
(1, 'reminder_updates', '1');

-- Insert sample schemes
INSERT INTO schemes (scheme_name, scheme_type, description, eligibility_criteria, application_process, documents_required, benefits) VALUES
('Ex-Servicemen Contributory Health Scheme (ECHS)', 'Central', 'Comprehensive healthcare scheme for ex-servicemen and dependents', 'Ex-servicemen, their dependents, and widows', 'Apply through nearest ECHS polyclinic', 'Service records, identity proof, dependent proof', 'Free medical treatment at ECHS facilities'),
('Sainik Welfare Scheme', 'State', 'State-specific welfare scheme for ex-servicemen', 'Domicile of respective state, ex-servicemen status', 'Apply through district collectorate', 'Discharge certificate, domicile certificate', 'Financial assistance, employment opportunities'),
('Armed Forces Flag Day Fund', 'Central', 'Welfare fund for disabled ex-servicemen and their dependents', 'Disabled ex-servicemen and war widows', 'Apply through Kendriya Sainik Board', 'Disability certificate, service records', 'Financial assistance for medical treatment and rehabilitation');

-- Insert sample colleges
INSERT INTO colleges (college_name, college_code, college_type, university_name, state, district, address, contact_number, courses_offered, defence_quota_seats, scholarship_info) VALUES
('Indian Institute of Technology Delhi', 'IITD001', 'Government', 'Indian Institute of Technology Delhi', 'Delhi', 'New Delhi', 'Hauz Khas, New Delhi - 110016', '011-26591999', 'Engineering, Technology, Computer Science', 50, 'Fee waiver and scholarship for defence quota students'),
('Jawaharlal Nehru University', 'JNU001', 'Government', 'Jawaharlal Nehru University', 'Delhi', 'New Delhi', 'New Mehrauli Road, New Delhi - 110067', '011-26704077', 'Arts, Science, Social Sciences, Languages', 30, 'Merit-based scholarship and hostel facilities'),
('Army Institute of Technology', 'AIT001', 'Government', 'Army Institute of Technology', 'Maharashtra', 'Pune', 'Dighi Hills, Pune - 411015', '020-24386000', 'Engineering, Computer Applications', 100, 'Special quota and fee concession for army personnel children');

-- Insert sample hospitals
INSERT INTO hospitals (hospital_name, hospital_code, hospital_type, state, district, address, contact_number, specialties, veterans_facility) VALUES
('Command Hospital (Southern Command)', 'CHSC001', 'Armed Forces', 'Maharashtra', 'Pune', 'Wanowrie, Pune - 411040', '020-26063000', 'General Medicine, Surgery, Cardiology, Orthopedics, Neurology', TRUE),
('ECHS Polyclinic Delhi Cantt', 'ECHSDC001', 'Armed Forces', 'Delhi', 'New Delhi', 'Delhi Cantonment, New Delhi - 110010', '011-25692000', 'General Medicine, Orthopedics, Ophthalmology, Dermatology', TRUE),
('Naval Hospital Mumbai', 'NHBOM001', 'Armed Forces', 'Maharashtra', 'Mumbai', 'Colaba, Mumbai - 400005', '022-22161000', 'General Medicine, Surgery, Emergency Care, Pediatrics', TRUE);

-- Insert sample news articles
INSERT INTO news (title, content, excerpt, category, author, publish_date, status, featured) VALUES
('Welcome to Veer Sahayata Portal', 'We are pleased to announce the launch of the Veer Sahayata portal, a comprehensive welfare platform designed specifically for Indian defence personnel and their families. This portal aims to provide easy access to various government schemes, pension information, healthcare facilities, and educational opportunities. The platform has been developed keeping in mind the unique needs of our brave soldiers and their families who have served the nation with dedication and valor.', 'Launch announcement for the new welfare portal for defence personnel', 'general', 'Admin Team', NOW(), 'published', TRUE),
('Pension Calculation Updates', 'Important updates have been made to the pension calculation system. All retired personnel are advised to check their pension details and update any missing information. The new system provides more accurate calculations based on rank, years of service, and applicable allowances. The updated formula takes into account the latest pay commission recommendations and ensures that all eligible personnel receive their rightful pension amounts.', 'Latest updates to pension calculation methods', 'pension', 'Pension Department', NOW(), 'published', FALSE),
('Healthcare Facility Expansion', 'New healthcare partnerships have been established to provide better medical facilities for defence families. The network now includes over 500 hospitals across the country, offering specialized treatment and emergency care services. These partnerships ensure that our veterans and their families have access to quality healthcare services at affordable rates, with many treatments covered under the ECHS scheme.', 'Expansion of healthcare network for defence personnel', 'health', 'Health Department', NOW(), 'published', TRUE),
('Educational Scholarships for Defence Children', 'The Ministry of Defence has announced new scholarship schemes for children of serving and retired defence personnel. These scholarships cover various streams including engineering, medical, and liberal arts. The initiative aims to support the educational aspirations of defence families and ensure that financial constraints do not hinder the academic progress of deserving students.', 'New scholarship opportunities for children of defence personnel', 'education', 'Education Cell', NOW(), 'published', FALSE);

-- Insert sample business suggestions
INSERT INTO business_suggestions (loan_amount_min, loan_amount_max, business_idea, description, investment_required, expected_returns, risk_level, category) VALUES
(50000, 200000, 'Food Truck Business', 'Mobile food service targeting office complexes, educational institutions, and events. Leverage discipline and time management skills from military experience.', 150000, '15-20% monthly profit after initial setup period', 'medium', 'Food & Beverage'),
(100000, 500000, 'Security Services', 'Private security services for residential complexes, commercial establishments, and events. Utilize military training and experience in security operations.', 300000, '20-25% annual profit with steady client base', 'low', 'Services'),
(200000, 1000000, 'Driving Training Institute', 'Professional driving training institute with emphasis on defensive driving techniques. Military experience provides credibility and expertise.', 500000, '18-22% annual profit with good reputation', 'low', 'Education'),
(75000, 300000, 'Organic Farming', 'Small-scale organic farming focusing on vegetables and fruits for local markets. Suitable for rural areas with land availability.', 200000, '25-30% annual returns with proper planning', 'medium', 'Agriculture');

-- ======================================================
-- COMPLETION MESSAGE
-- ======================================================

SELECT 'Veer Sahayata Database Setup Completed Successfully!' as message,
       'Database: veer_sahayata' as database_name,
       (SELECT COUNT(*) FROM users) as total_users,
       (SELECT COUNT(*) FROM news) as total_news,
       (SELECT COUNT(*) FROM colleges) as total_colleges,
       (SELECT COUNT(*) FROM hospitals) as total_hospitals,
       (SELECT COUNT(*) FROM schemes) as total_schemes,
       (SELECT COUNT(*) FROM settings) as total_settings;
