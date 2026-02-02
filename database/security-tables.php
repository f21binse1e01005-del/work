<?php
/**
 * Security Tables Migration
 * Run this to create security-related tables
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = (new Database())->getConnection();
    
    if (!$db) {
        throw new Exception("Database connection failed");
    }
    
    echo "Creating security tables...\n";
    
    // Security logs table
    $sql = "CREATE TABLE IF NOT EXISTS security_logs (
        log_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        event_type VARCHAR(50) NOT NULL,
        user_id INT UNSIGNED NULL,
        ip_address VARCHAR(45) NOT NULL,
        user_agent TEXT,
        event_details JSON,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_event_type (event_type),
        INDEX idx_user_id (user_id),
        INDEX idx_created_at (created_at),
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql);
    echo "✓ Security logs table created\n";
    
    // Session management table
    $sql = "CREATE TABLE IF NOT EXISTS active_sessions (
        session_id VARCHAR(128) PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        user_agent TEXT,
        last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_last_activity (last_activity),
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql);
    echo "✓ Active sessions table created\n";
    
    // Failed login attempts tracking
    $sql = "CREATE TABLE IF NOT EXISTS failed_login_attempts (
        attempt_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_username (username),
        INDEX idx_ip_address (ip_address),
        INDEX idx_attempt_time (attempt_time)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql);
    echo "✓ Failed login attempts table created\n";
    
    // File upload logs
    $sql = "CREATE TABLE IF NOT EXISTS file_upload_logs (
        upload_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        original_filename VARCHAR(255) NOT NULL,
        stored_filename VARCHAR(255) NOT NULL,
        file_size INT UNSIGNED NOT NULL,
        file_type VARCHAR(50) NOT NULL,
        upload_path VARCHAR(500) NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_created_at (created_at),
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql);
    echo "✓ File upload logs table created\n";
    
    echo "\n✅ All security tables created successfully!\n";
    
} catch (PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>