<?php
// fix_db.php: Fix database schema issues for Skills Way

require_once 'config/database.php';

echo "<!DOCTYPE html><html><body><h1>Applying Database Fixes...</h1><pre>";

try {
    $db = (new Database())->getConnection();

    // 1. Add display_order to course_categories
    echo "Checking 'display_order' column in 'course_categories'...\n";
    try {
        $db->query("SELECT display_order FROM course_categories LIMIT 1");
        echo " - Column already exists.\n";
    } catch (PDOException $e) {
        $db->exec("ALTER TABLE course_categories ADD COLUMN display_order INT DEFAULT 0 AFTER category_name");
        echo " - Added 'display_order' column successfully.\n";
    }

    // 2. Create enrollments table if not exists (referencing enrollment_applications)
    echo "\nChecking 'enrollments' table...\n";
    $db->exec("CREATE TABLE IF NOT EXISTS enrollments (
        enrollment_id INT AUTO_INCREMENT PRIMARY KEY,
        application_id INT NOT NULL,
        student_id INT NOT NULL,
        course_id INT NOT NULL,
        batch_id INT NOT NULL,
        enrollment_date DATE NOT NULL,
        status ENUM('active', 'completed', 'dropped', 'suspended') DEFAULT 'active',
        final_grade DECIMAL(5,2) NULL,
        certificate_id VARCHAR(50) NULL,
        issued_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (application_id) REFERENCES enrollment_applications(application_id) ON DELETE CASCADE,
        FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
        FOREIGN KEY (batch_id) REFERENCES batches(batch_id) ON DELETE CASCADE,
        INDEX idx_student (student_id),
        INDEX idx_course (course_id),
        INDEX idx_batch (batch_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo " - Table 'enrollments' checked/created successfully.\n";

    // 3. Fix courses table if needed (display_order might be there too?)
    // The error was for course_categories display_order in courses.php:572 (which seems to fetch categories for dropdown)
    // "SELECT * FROM course_categories WHERE is_active = 1 ORDER BY display_order, category_name"

    echo "\n✅ All repairs completed successfully!";
} catch (PDOException $e) {
    echo "\n❌ Error: " . $e->getMessage();
}

echo "</pre></body></html>";
