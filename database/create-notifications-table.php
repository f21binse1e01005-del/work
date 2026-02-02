<?php
/**
 * Create Notifications Table Migration
 * Creates a dedicated table for in-app notifications
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = (new Database())->getConnection();
    
    if (!$db) {
        throw new Exception("Database connection failed");
    }
    
    echo "Creating notifications table...\n";
    
    // In-app notifications table
    $sql = "CREATE TABLE IF NOT EXISTS notifications (
        notification_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        notification_type ENUM('assignment', 'quiz', 'grade', 'message', 'announcement', 'course', 'system') NOT NULL DEFAULT 'system',
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        link VARCHAR(500) NULL,
        is_read BOOLEAN NOT NULL DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_type (notification_type),
        INDEX idx_is_read (is_read),
        INDEX idx_created_at (created_at),
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql);
    echo "✓ Notifications table created\n";
    
    // Insert sample notifications for testing
    echo "\nInserting sample notifications...\n";
    
    $sampleNotifications = [
        [
            'user_id' => 1,
            'notification_type' => 'assignment',
            'title' => 'New Assignment Posted',
            'message' => 'A new assignment has been posted in Web Development course.',
            'link' => 'assignments.php?assignment_id=1'
        ],
        [
            'user_id' => 1,
            'notification_type' => 'quiz',
            'title' => 'Quiz Available',
            'message' => 'A new quiz is now available for Database Management.',
            'link' => 'quizzes.php?quiz_id=1'
        ],
        [
            'user_id' => 1,
            'notification_type' => 'announcement',
            'title' => 'Important Announcement',
            'message' => 'Classes will resume on Monday. Please check your schedule.',
            'link' => 'announcements.php'
        ]
    ];
    
    foreach ($sampleNotifications as $notification) {
        try {
            $stmt = $db->prepare("
                INSERT INTO notifications (user_id, notification_type, title, message, link) 
                VALUES (:user_id, :notification_type, :title, :message, :link)
            ");
            $stmt->execute($notification);
        } catch (PDOException $e) {
            echo "Note: Sample notification skipped (user_id may not exist yet)\n";
        }
    }
    
    echo "✓ Sample notifications inserted\n";
    echo "\n✅ Notifications table created successfully!\n";
    
} catch (PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
