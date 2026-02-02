<?php
/**
 * Additional Database Tables Setup
 * Creates tables for new LMS features
 */

require_once '../config/database.php';

try {
    $db = (new Database())->getConnection();
    
    echo "<h2>Setting up Additional LMS Tables...</h2>";
    
    // Messages table
    $db->exec("CREATE TABLE IF NOT EXISTS messages (
        message_id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        recipient_id INT NOT NULL,
        subject VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sender_id) REFERENCES users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (recipient_id) REFERENCES users(user_id) ON DELETE CASCADE
    )");
    echo "✓ Messages table created<br>";
    
    // Announcements table
    $db->exec("CREATE TABLE IF NOT EXISTS announcements (
        announcement_id INT AUTO_INCREMENT PRIMARY KEY,
        batch_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (batch_id) REFERENCES batches(batch_id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE CASCADE
    )");
    echo "✓ Announcements table created<br>";
    
    // Calendar events table
    $db->exec("CREATE TABLE IF NOT EXISTS calendar_events (
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
    )");
    echo "✓ Calendar events table created<br>";
    
    // Quizzes table
    $db->exec("CREATE TABLE IF NOT EXISTS quizzes (
        quiz_id INT AUTO_INCREMENT PRIMARY KEY,
        batch_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        questions JSON NOT NULL,
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (batch_id) REFERENCES batches(batch_id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE CASCADE
    )");
    echo "✓ Quizzes table created<br>";
    
    // Quiz attempts table
    $db->exec("CREATE TABLE IF NOT EXISTS quiz_attempts (
        attempt_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        quiz_id INT NOT NULL,
        score INT NOT NULL,
        total_questions INT NOT NULL,
        attempt_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (quiz_id) REFERENCES quizzes(quiz_id) ON DELETE CASCADE
    )");
    echo "✓ Quiz attempts table created<br>";
    
    // Assignments table
    $db->exec("CREATE TABLE IF NOT EXISTS assignments (
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
    )");
    echo "✓ Assignments table created<br>";
    
    // Assignment submissions table
    $db->exec("CREATE TABLE IF NOT EXISTS assignment_submissions (
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
    )");
    echo "✓ Assignment submissions table created<br>";
    
    // Course materials table
    $db->exec("CREATE TABLE IF NOT EXISTS course_materials (
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
    )");
    echo "✓ Course materials table created<br>";
    
    echo "<h3 style='color: green;'>✓ Additional tables setup completed!</h3>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>✗ Additional tables setup failed!</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>