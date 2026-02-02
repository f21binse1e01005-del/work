-- Skills Way Vocational Institute - Complete Database Setup for XAMPP
-- Run this in phpMyAdmin (http://localhost/phpmyadmin)
-- Or via command: mysql -u root < setup_xampp.sql

-- Create database
CREATE DATABASE IF NOT EXISTS skills_way_vocational CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE skills_way_vocational;

-- =====================================================
-- CLEANUP OLD TABLES (if any with incompatible schema)
-- =====================================================
SET FOREIGN_KEY_CHECKS = 0;

-- Drop old tables if they exist (in reverse dependency order)
DROP TABLE IF EXISTS quiz_attempts;
DROP TABLE IF EXISTS quizzes;
DROP TABLE IF EXISTS assignment_submissions;
DROP TABLE IF EXISTS assignments;
DROP TABLE IF EXISTS course_materials;
DROP TABLE IF EXISTS calendar_events;
DROP TABLE IF EXISTS announcements;
DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS remember_me_tokens;
DROP TABLE IF EXISTS user_activity_logs;
DROP TABLE IF EXISTS login_logs;
DROP TABLE IF EXISTS enrollment_applications;
DROP TABLE IF EXISTS enrollments;
DROP TABLE IF EXISTS batches;
DROP TABLE IF EXISTS courses;
DROP TABLE IF EXISTS course_categories;
DROP TABLE IF EXISTS user_profiles;
DROP TABLE IF EXISTS sessions;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS institute_settings;
DROP TABLE IF EXISTS beauty_services;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- CORE TABLES
-- =====================================================


-- Users table (main authentication table)
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    cnic VARCHAR(15) UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    user_type ENUM('admin', 'teacher', 'student') DEFAULT 'student',
    account_status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_type (user_type),
    INDEX idx_email (email),
    INDEX idx_account_status (account_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User profiles table (extended user information)
CREATE TABLE IF NOT EXISTS user_profiles (
    profile_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other'),
    address TEXT,
    education_level VARCHAR(50),
    parent_name VARCHAR(100),
    parent_phone VARCHAR(20),
    profile_picture VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Course categories table
CREATE TABLE IF NOT EXISTS course_categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Courses table
CREATE TABLE IF NOT EXISTS courses (
    course_id INT AUTO_INCREMENT PRIMARY KEY,
    course_name VARCHAR(100) NOT NULL,
    course_code VARCHAR(20) UNIQUE NOT NULL,
    description TEXT,
    duration_months INT DEFAULT 6,
    fee_amount DECIMAL(10,2) DEFAULT 0.00,
    category_id INT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES course_categories(category_id) ON DELETE SET NULL,
    INDEX idx_course_code (course_code),
    INDEX idx_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Batches table
CREATE TABLE IF NOT EXISTS batches (
    batch_id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    batch_name VARCHAR(100) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE,
    max_students INT DEFAULT 30,
    current_students INT DEFAULT 0,
    status ENUM('upcoming', 'ongoing', 'completed', 'cancelled') DEFAULT 'upcoming',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    INDEX idx_course_id (course_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Enrollment applications table
CREATE TABLE IF NOT EXISTS enrollment_applications (
    application_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    course_id INT NOT NULL,
    batch_id INT,
    application_status ENUM('submitted', 'under_review', 'approved', 'rejected', 'pending') DEFAULT 'submitted',
    payment_status ENUM('pending', 'paid', 'partial', 'refunded') DEFAULT 'pending',
    payment_amount DECIMAL(10,2) DEFAULT 0.00,
    application_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INT,
    reviewed_at TIMESTAMP NULL,
    notes TEXT,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    FOREIGN KEY (batch_id) REFERENCES batches(batch_id) ON DELETE SET NULL,
    FOREIGN KEY (reviewed_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_course_id (course_id),
    INDEX idx_status (application_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Enrollments table
CREATE TABLE IF NOT EXISTS enrollments (
    enrollment_id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    batch_id INT NOT NULL,
    enrollment_status ENUM('active', 'completed', 'dropped', 'suspended') DEFAULT 'active',
    enrollment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (application_id) REFERENCES enrollment_applications(application_id) ON DELETE CASCADE,
    FOREIGN KEY (batch_id) REFERENCES batches(batch_id) ON DELETE CASCADE,
    INDEX idx_status (enrollment_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User settings table
CREATE TABLE IF NOT EXISTS user_settings (
    user_id INT PRIMARY KEY,
    email_notifications BOOLEAN DEFAULT TRUE,
    sms_notifications BOOLEAN DEFAULT FALSE,
    assignment_reminders BOOLEAN DEFAULT TRUE,
    grade_notifications BOOLEAN DEFAULT TRUE,
    announcement_notifications BOOLEAN DEFAULT TRUE,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SECURITY & LOGGING TABLES
-- =====================================================

-- Login logs table
CREATE TABLE IF NOT EXISTS login_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    username VARCHAR(50),
    ip_address VARCHAR(45),
    user_agent TEXT,
    login_status ENUM('success', 'failed_password', 'failed_inactive', 'failed_locked') NOT NULL,
    failure_reason VARCHAR(255),
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_login_time (login_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User activity logs table
CREATE TABLE IF NOT EXISTS user_activity_logs (
    activity_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activity_type VARCHAR(50) NOT NULL,
    activity_details JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_activity_type (activity_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Remember me tokens table
CREATE TABLE IF NOT EXISTS remember_me_tokens (
    token_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    selector VARCHAR(32) NOT NULL,
    hashed_token VARCHAR(64) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_selector (selector),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Institute settings table
CREATE TABLE IF NOT EXISTS institute_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- LMS FEATURE TABLES
-- =====================================================

-- Messages table
CREATE TABLE IF NOT EXISTS messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    recipient_id INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Announcements table
CREATE TABLE IF NOT EXISTS announcements (
    announcement_id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (batch_id) REFERENCES batches(batch_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Course materials table
CREATE TABLE IF NOT EXISTS course_materials (
    material_id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    file_path VARCHAR(500),
    external_link VARCHAR(500),
    file_size BIGINT,
    uploaded_by INT NOT NULL,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (batch_id) REFERENCES batches(batch_id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Assignments table
CREATE TABLE IF NOT EXISTS assignments (
    assignment_id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    due_date DATETIME NOT NULL,
    max_points INT DEFAULT 100,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (batch_id) REFERENCES batches(batch_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Assignment submissions table
CREATE TABLE IF NOT EXISTS assignment_submissions (
    submission_id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    user_id INT NOT NULL,
    submission_text TEXT,
    file_path VARCHAR(500),
    grade INT NULL,
    feedback TEXT,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    graded_at TIMESTAMP NULL,
    graded_by INT NULL,
    FOREIGN KEY (assignment_id) REFERENCES assignments(assignment_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (graded_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Quizzes table
CREATE TABLE IF NOT EXISTS quizzes (
    quiz_id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    questions JSON NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (batch_id) REFERENCES batches(batch_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Quiz attempts table
CREATE TABLE IF NOT EXISTS quiz_attempts (
    attempt_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    quiz_id INT NOT NULL,
    score INT NOT NULL,
    total_questions INT NOT NULL,
    attempt_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(quiz_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notifications table
CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT,
    type VARCHAR(50) DEFAULT 'general',
    action_url VARCHAR(255),
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Calendar events table
CREATE TABLE IF NOT EXISTS calendar_events (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    event_date DATE NOT NULL,
    event_time TIME NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (batch_id) REFERENCES batches(batch_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- DEFAULT DATA
-- =====================================================

-- Insert default institute settings
INSERT INTO institute_settings (setting_key, setting_value) VALUES
('institute_name', 'Skills Way Vocational Institute'),
('institute_address', 'Model Town "B", Near Masjid Al-Farooq/Bank Al-Habib, Khanpur'),
('contact_phone', '0307-0237356, 0331-3307365'),
('contact_email', 'askillswaykpr@gmail.com'),
('operating_hours', 'Monday–Saturday, 8:00 AM – 9:00 PM')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

-- Insert default course categories
INSERT INTO course_categories (category_name, description) VALUES
('Technical Skills', 'Hands-on technical training courses'),
('IT & Programming', 'Computer science and software development courses'),
('Business Skills', 'Business management and entrepreneurship courses'),
('Creative Arts', 'Design, arts, and creative skills courses'),
('Language Training', 'Language learning and communication courses')
ON DUPLICATE KEY UPDATE description=VALUES(description);

-- Insert sample courses
INSERT INTO courses (course_name, course_code, description, duration_months, fee_amount, category_id, is_active) VALUES
('Web Development', 'WEB001', 'Complete web development course with HTML, CSS, JavaScript, PHP', 6, 25000.00, 2, TRUE),
('Python Programming', 'PY001', 'Learn Python programming from basics to advanced', 4, 20000.00, 2, TRUE),
('Digital Marketing', 'DM001', 'Complete digital marketing course including SEO, SEM, Social Media', 3, 15000.00, 3, TRUE),
('Graphic Design', 'GD001', 'Professional graphic design using Adobe Creative Suite', 4, 18000.00, 4, TRUE),
('Data Entry', 'DE001', 'Professional data entry and MS Office skills', 2, 12000.00, 3, TRUE),
('AutoCAD', 'CAD001', 'Complete AutoCAD 2D and 3D drafting course', 3, 22000.00, 1, TRUE),
('Electrician Course', 'ELEC01', 'Professional electrical installation and maintenance', 6, 28000.00, 1, TRUE),
('English Language', 'ENG001', 'Spoken English and communication skills', 3, 10000.00, 5, TRUE)
ON DUPLICATE KEY UPDATE description=VALUES(description);

-- =====================================================
-- DEFAULT USERS (Password: Admin123! for all)
-- Hash: SHA256 with salt 'skills_way_salt'
-- =====================================================

-- Admin User
-- Username: admin.skills | Password: Admin123!
INSERT INTO users (username, email, phone, cnic, full_name, password_hash, user_type, account_status) VALUES
('admin.skills', 'admin@skillsway.edu.pk', '0307-0237356', '36302-1234567-1', 'System Administrator', 
 '7eeac279d4cff4d622e5bf6b23efb888d50f69771ad56a750467909aa45751e6', 'admin', 'active')
ON DUPLICATE KEY UPDATE full_name=VALUES(full_name);

-- Teacher User
-- Username: teacher.demo | Password: Teacher123!
INSERT INTO users (username, email, phone, cnic, full_name, password_hash, user_type, account_status) VALUES
('teacher.demo', 'teacher@skillsway.edu.pk', '0331-3307365', '36302-7654321-2', 'Demo Teacher',
 'dfdff3e8b30cdcca469a8a4009964329fa6656959889af2075c245d16b6a5596', 'teacher', 'active')
ON DUPLICATE KEY UPDATE full_name=VALUES(full_name);

-- Student User
-- Username: student.demo | Password: Student123!
INSERT INTO users (username, email, phone, cnic, full_name, password_hash, user_type, account_status) VALUES
('student.demo', 'student@skillsway.edu.pk', '0300-1234567', '36302-9876543-3', 'Demo Student',
 '8e5048c698ddb7d7ab2ef4177bc4dbbbc9479ebf6bb9c45179fccfd40152eb13', 'student', 'active')
ON DUPLICATE KEY UPDATE full_name=VALUES(full_name);

-- Insert user profiles for demo users
INSERT INTO user_profiles (user_id, gender, address, education_level) 
SELECT user_id, 'male', 'Khanpur, Pakistan', 'Graduate' FROM users WHERE username = 'admin.skills'
ON DUPLICATE KEY UPDATE address=VALUES(address);

INSERT INTO user_profiles (user_id, gender, address, education_level) 
SELECT user_id, 'male', 'Khanpur, Pakistan', 'Masters' FROM users WHERE username = 'teacher.demo'
ON DUPLICATE KEY UPDATE address=VALUES(address);

INSERT INTO user_profiles (user_id, gender, address, education_level, parent_name, parent_phone) 
SELECT user_id, 'male', 'Khanpur, Pakistan', 'Intermediate', 'Parent Name', '0301-9876543' FROM users WHERE username = 'student.demo'
ON DUPLICATE KEY UPDATE address=VALUES(address);

-- Create a sample batch
INSERT INTO batches (course_id, batch_name, start_date, end_date, max_students, status) 
SELECT course_id, 'WEB-2026-01', '2026-02-01', '2026-07-31', 25, 'upcoming' 
FROM courses WHERE course_code = 'WEB001' LIMIT 1
ON DUPLICATE KEY UPDATE batch_name=VALUES(batch_name);

-- =====================================================
-- SUCCESS MESSAGE
-- =====================================================
SELECT 'Database setup completed successfully!' AS Status;
SELECT '=== LOGIN CREDENTIALS ===' AS Info;
SELECT 'Admin: admin.skills / Admin123!' AS Admin_Login;
SELECT 'Teacher: teacher.demo / Teacher123!' AS Teacher_Login;
SELECT 'Student: student.demo / Student123!' AS Student_Login;
