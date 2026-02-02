<?php
/**
 * Database Setup Script
 * Creates all required tables for Skills Way LMS
 */

require_once '../config/database.php';

try {
    $db = (new Database())->getConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    echo "<h2>Setting up Skills Way Database...</h2>";
    
    // Users table
    $db->exec("CREATE TABLE IF NOT EXISTS users (
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
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    echo "✓ Users table created<br>";
    
    // User profiles table
    $db->exec("CREATE TABLE IF NOT EXISTS user_profiles (
        profile_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        date_of_birth DATE,
        gender ENUM('male', 'female', 'other'),
        address TEXT,
        education_level VARCHAR(50),
        parent_name VARCHAR(100),
        parent_phone VARCHAR(20),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    )");
    echo "✓ User profiles table created<br>";
    
    // Courses table
    $db->exec("CREATE TABLE IF NOT EXISTS courses (
        course_id INT AUTO_INCREMENT PRIMARY KEY,
        course_name VARCHAR(100) NOT NULL,
        course_code VARCHAR(20) UNIQUE NOT NULL,
        description TEXT,
        duration_months INT DEFAULT 6,
        fee_amount DECIMAL(10,2) DEFAULT 0.00,
        category_id INT DEFAULT 1,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    echo "✓ Courses table created<br>";
    
    // Course categories table
    $db->exec("CREATE TABLE IF NOT EXISTS course_categories (
        category_id INT AUTO_INCREMENT PRIMARY KEY,
        category_name VARCHAR(100) NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "✓ Course categories table created<br>";
    
    // Batches table
    $db->exec("CREATE TABLE IF NOT EXISTS batches (
        batch_id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        batch_name VARCHAR(100) NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE,
        max_students INT DEFAULT 30,
        current_students INT DEFAULT 0,
        status ENUM('upcoming', 'ongoing', 'completed', 'cancelled') DEFAULT 'upcoming',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE
    )");
    echo "✓ Batches table created<br>";
    
    // Enrollment applications table
    $db->exec("CREATE TABLE IF NOT EXISTS enrollment_applications (
        application_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        course_id INT NOT NULL,
        batch_id INT,
        application_status ENUM('submitted', 'approved', 'rejected', 'pending') DEFAULT 'submitted',
        payment_status ENUM('pending', 'paid', 'partial', 'refunded') DEFAULT 'pending',
        payment_amount DECIMAL(10,2) DEFAULT 0.00,
        application_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        reviewed_by INT,
        reviewed_at TIMESTAMP NULL,
        notes TEXT,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
        FOREIGN KEY (batch_id) REFERENCES batches(batch_id) ON DELETE SET NULL,
        FOREIGN KEY (reviewed_by) REFERENCES users(user_id) ON DELETE SET NULL
    )");
    echo "✓ Enrollment applications table created<br>";
    
    // Login logs table
    $db->exec("CREATE TABLE IF NOT EXISTS login_logs (
        log_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        username VARCHAR(50),
        ip_address VARCHAR(45),
        user_agent TEXT,
        login_status ENUM('success', 'failed_password', 'failed_inactive', 'failed_locked') NOT NULL,
        failure_reason VARCHAR(255),
        login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
    )");
    echo "✓ Login logs table created<br>";
    
    // User activity logs table
    $db->exec("CREATE TABLE IF NOT EXISTS user_activity_logs (
        activity_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        activity_type VARCHAR(50) NOT NULL,
        activity_details JSON,
        ip_address VARCHAR(45),
        user_agent TEXT,
        performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    )");
    echo "✓ User activity logs table created<br>";
    
    // Remember me tokens table
    $db->exec("CREATE TABLE IF NOT EXISTS remember_me_tokens (
        token_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        selector VARCHAR(32) NOT NULL,
        hashed_token VARCHAR(64) NOT NULL,
        expires_at TIMESTAMP NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        INDEX idx_selector (selector),
        INDEX idx_expires (expires_at)
    )");
    echo "✓ Remember me tokens table created<br>";
    
    // Insert default admin user if not exists
    $adminCheck = $db->prepare("SELECT COUNT(*) FROM users WHERE user_type = 'admin'");
    $adminCheck->execute();
    
    if ($adminCheck->fetchColumn() == 0) {
        $adminPassword = hash('sha256', 'Admin123!');
        $adminInsert = $db->prepare("INSERT INTO users (username, email, full_name, password_hash, user_type) VALUES (?, ?, ?, ?, ?)");
        $adminInsert->execute(['admin.skills', 'admin@skillsway.edu.pk', 'Institute Admin', $adminPassword, 'admin']);
        echo "✓ Default admin user created (admin.skills / Admin123!)<br>";
    }
    
    // Insert sample courses if none exist
    $courseCheck = $db->prepare("SELECT COUNT(*) FROM courses");
    $courseCheck->execute();
    
    if ($courseCheck->fetchColumn() == 0) {
        // Insert default categories first
        $categories = [
            'Technical Skills',
            'IT & Programming', 
            'Business Skills',
            'Creative Arts',
            'Language Training'
        ];
        
        $categoryInsert = $db->prepare("INSERT INTO course_categories (category_name) VALUES (?)");
        foreach ($categories as $category) {
            $categoryInsert->execute([$category]);
        }
        echo "✓ Default categories created<br>";
        
        $courses = [
            ['Web Development', 'WEB001', 'Complete web development course with HTML, CSS, JavaScript, PHP', 6, 25000.00, 2],
            ['Python Programming', 'PY001', 'Learn Python programming from basics to advanced', 4, 20000.00, 2],
            ['Digital Marketing', 'DM001', 'Complete digital marketing course including SEO, SEM, Social Media', 3, 15000.00, 3],
            ['Graphic Design', 'GD001', 'Professional graphic design using Adobe Creative Suite', 4, 18000.00, 4],
            ['Data Entry', 'DE001', 'Professional data entry and MS Office skills', 2, 12000.00, 3]
        ];
        
        $courseInsert = $db->prepare("INSERT INTO courses (course_name, course_code, description, duration_months, fee_amount, category_id) VALUES (?, ?, ?, ?, ?, ?)");
        
        foreach ($courses as $course) {
            $courseInsert->execute($course);
        }
        echo "✓ Sample courses created<br>";
    }
    
    echo "<h3 style='color: green;'>✓ Database setup completed successfully!</h3>";
    echo "<p><a href='../admin/login-test.php'>Go to Admin Login</a></p>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>✗ Database setup failed!</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>Please check your database configuration in config/database.php</p>";
}
?>