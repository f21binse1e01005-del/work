<?php
// admin/check-notifications.php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    $db = (new Database())->getConnection();
    
    if (isset($_SESSION['user_id'])) {
        $stmt = $db->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
        $unread = $stmt->fetchColumn() ?: 0;
        
        echo json_encode(['unread' => $unread, 'status' => 'success']);
    } else {
        echo json_encode(['unread' => 0, 'status' => 'not_logged_in']);
    }
} catch (Exception $e) {
    echo json_encode(['unread' => 0, 'status' => 'error', 'message' => $e->getMessage()]);
}
?>