<?php
/**
 * Create User Settings Table Migration
 * Creates table for storing user notification and privacy settings
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = (new Database())->getConnection();
    
    if (!$db) {
        throw new Exception("Database connection failed");
    }
    
    echo "Creating user_settings table...\n";
    
    // User settings table
    $sql = "CREATE TABLE IF NOT EXISTS user_settings (
        setting_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        email_notifications BOOLEAN DEFAULT TRUE,
        sms_notifications BOOLEAN DEFAULT FALSE,
        assignment_reminders BOOLEAN DEFAULT TRUE,
        grade_notifications BOOLEAN DEFAULT TRUE,
        announcement_notifications BOOLEAN DEFAULT TRUE,
        profile_visibility ENUM('public', 'students', 'private') DEFAULT 'students',
        two_factor_enabled BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user (user_id),
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql);
    echo "✓ User settings table created\n";
    
    echo "\n✅ User settings table created successfully!\n";
    
} catch (PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
