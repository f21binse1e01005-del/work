-- Patch to restore missing tables
-- Run this to fix the "Base table or view not found" and "Unknown column" errors

USE skills_way_vocational;

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

SELECT 'Patch applied successfully!' as Status;
