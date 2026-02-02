<?php
/**
 * Notification Tables Migration
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = (new Database())->getConnection();
    
    if (!$db) {
        throw new Exception("Database connection failed");
    }
    
    echo "Creating notification tables...\n";
    
    // Notification logs table
    $sql = "CREATE TABLE IF NOT EXISTS notification_logs (
        log_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        notification_type ENUM('email', 'sms', 'both') NOT NULL,
        purpose VARCHAR(100) NOT NULL,
        success BOOLEAN NOT NULL DEFAULT FALSE,
        error_message TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_type (notification_type),
        INDEX idx_purpose (purpose),
        INDEX idx_created_at (created_at),
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql);
    echo "✓ Notification logs table created\n";
    
    // Email templates table
    $sql = "CREATE TABLE IF NOT EXISTS email_templates (
        template_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        template_name VARCHAR(100) NOT NULL UNIQUE,
        subject VARCHAR(255) NOT NULL,
        body_html TEXT NOT NULL,
        body_text TEXT NULL,
        variables JSON NULL,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_name (template_name),
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql);
    echo "✓ Email templates table created\n";
    
    // SMS templates table
    $sql = "CREATE TABLE IF NOT EXISTS sms_templates (
        template_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        template_name VARCHAR(100) NOT NULL UNIQUE,
        message_text VARCHAR(160) NOT NULL,
        variables JSON NULL,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_name (template_name),
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql);
    echo "✓ SMS templates table created\n";
    
    // Insert default templates
    echo "\nInserting default templates...\n";
    
    // Default email templates
    $emailTemplates = [
        [
            'credentials',
            'Your Skills Way Login Credentials',
            '<h2>Welcome to Skills Way!</h2><p>Username: {{username}}<br>Password: {{password}}</p>',
            'Welcome to Skills Way! Username: {{username}}, Password: {{password}}',
            '["username", "password"]'
        ],
        [
            'welcome',
            'Welcome to Skills Way Institute',
            '<h2>Welcome {{name}}!</h2><p>We are excited to have you join our learning community.</p>',
            'Welcome {{name}}! We are excited to have you join our learning community.',
            '["name"]'
        ]
    ];
    
    foreach ($emailTemplates as $template) {
        $stmt = $db->prepare("INSERT IGNORE INTO email_templates (template_name, subject, body_html, body_text, variables) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute($template);
    }
    echo "✓ Default email templates inserted\n";
    
    // Default SMS templates
    $smsTemplates = [
        [
            'credentials',
            'Skills Way Login - Username: {{username}}, Password: {{password}}',
            '["username", "password"]'
        ],
        [
            'otp',
            'Skills Way OTP: {{otp}}. Valid for 5 minutes.',
            '["otp"]'
        ],
        [
            'welcome',
            'Welcome to Skills Way Institute! We are excited to have you.',
            '[]'
        ]
    ];
    
    foreach ($smsTemplates as $template) {
        $stmt = $db->prepare("INSERT IGNORE INTO sms_templates (template_name, message_text, variables) VALUES (?, ?, ?)");
        $stmt->execute($template);
    }
    echo "✓ Default SMS templates inserted\n";
    
    echo "\n✅ All notification tables and templates created successfully!\n";
    
} catch (PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>