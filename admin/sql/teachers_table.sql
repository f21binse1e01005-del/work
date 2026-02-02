-- Teachers Table Structure for Skills Way Vocational Institute
-- Run this SQL to create the teachers table if it doesn't exist

-- Create teachers table for additional teacher-specific information
CREATE TABLE IF NOT EXISTS teachers (
    teacher_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    specialization VARCHAR(255) NOT NULL,
    experience_years INT DEFAULT 0,
    salary DECIMAL(10,2) DEFAULT 0.00,
    hire_date DATE NOT NULL,
    status ENUM('active', 'inactive', 'on_leave') DEFAULT 'active',
    department VARCHAR(100),
    qualification_details TEXT,
    teaching_subjects TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_specialization (specialization)
);

-- Ensure user_profiles table exists for additional user information
CREATE TABLE IF NOT EXISTS user_profiles (
    profile_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    education_level VARCHAR(100),
    address TEXT,
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other'),
    emergency_contact VARCHAR(20),
    profile_picture VARCHAR(255),
    bio TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_profile (user_id)
);

-- Sample data for testing (optional)
-- INSERT INTO teachers (user_id, specialization, experience_years, salary, hire_date, status) 
-- VALUES (1, 'Computer Science', 5, 50000.00, '2024-01-01', 'active');