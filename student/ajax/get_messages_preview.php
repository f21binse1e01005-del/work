<?php
/**
 * Skills Way LMS - Get Messages Preview AJAX Endpoint
 * Returns recent messages preview in JSON format
 */

header('Content-Type: application/json');
require_once '../../config/session.php';
require_once '../../config/database.php';

// Start session
$session = new SessionManager();

// Check if user is logged in
if (!$session->isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Not authenticated',
        'count' => 0,
        'html' => '<div class="text-center py-3 text-muted">Please log in to view messages</div>'
    ]);
    exit;
}

try {
    $db = (new Database())->getConnection();
    $user_id = $session->get('user_id');
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    
    // Get recent messages
    $stmt = $db->prepare("
        SELECT 
            m.message_id,
            m.sender_id,
            m.subject,
            SUBSTRING(m.message_text, 1, 100) as message_preview,
            m.is_read,
            m.sent_at,
            u.full_name as sender_name,
            u.profile_image as sender_image,
            TIMESTAMPDIFF(SECOND, m.sent_at, NOW()) as seconds_ago
        FROM messages m
        JOIN users u ON m.sender_id = u.user_id
        WHERE m.receiver_id = ?
        ORDER BY m.sent_at DESC
        LIMIT ?
    ");
    
    $stmt->execute([$user_id, $limit]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get unread count
    $stmt = $db->prepare("
        SELECT COUNT(*) as count
        FROM messages
        WHERE receiver_id = ? AND is_read = FALSE
    ");
    $stmt->execute([$user_id]);
    $unread_count = $stmt->fetchColumn();
    
    // Generate HTML
    $html = '';
    
    if (empty($messages)) {
        $html = '
            <div class="text-center py-5">
                <i class="fas fa-envelope-open fa-3x text-muted mb-3"></i>
                <p class="text-muted">No messages yet</p>
            </div>
        ';
    } else {
        foreach ($messages as $message) {
            $unread_class = $message['is_read'] ? '' : 'bg-light';
            $unread_indicator = $message['is_read'] ? '' : '<span class="badge bg-primary ms-2">New</span>';
            
            // Format time ago
            $seconds = $message['seconds_ago'];
            $time_ago = formatTimeAgo($seconds);
            
            // Get sender image
            $sender_image = !empty($message['sender_image']) 
                ? '../uploads/profiles/' . htmlspecialchars($message['sender_image'])
                : '../assets/images/default-avatar.png';
            
            // Create preview (first 60 chars)
            $preview = $message['message_preview'];
            if (strlen($preview) > 60) {
                $preview = substr($preview, 0, 60) . '...';
            }
            
            $html .= '
                <a href="messages.php?message_id=' . $message['message_id'] . '" 
                   class="dropdown-item d-flex align-items-start py-3 ' . $unread_class . '" 
                   data-message-id="' . $message['message_id'] . '">
                    <div class="me-3">
                        <img src="' . $sender_image . '" 
                             class="rounded-circle" 
                             width="40" 
                             height="40" 
                             alt="' . htmlspecialchars($message['sender_name']) . '"
                             onerror="this.src=\'../assets/images/default-avatar.png\'">
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <strong class="d-block">' . htmlspecialchars($message['sender_name']) . '</strong>
                            ' . $unread_indicator . '
                        </div>
                        <p class="mb-1 fw-semibold text-dark">' . htmlspecialchars($message['subject']) . '</p>
                        <p class="mb-1 text-muted small">' . htmlspecialchars($preview) . '</p>
                        <small class="text-muted">
                            <i class="far fa-clock me-1"></i>' . $time_ago . '
                        </small>
                    </div>
                </a>
            ';
        }
    }
    
    echo json_encode([
        'success' => true,
        'count' => $unread_count,
        'total' => count($messages),
        'html' => $html
    ]);
    
} catch (PDOException $e) {
    error_log("Get Messages Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load messages',
        'count' => 0,
        'html' => '<div class="text-center py-3 text-danger">Error loading messages</div>'
    ]);
}

/**
 * Format seconds into human-readable time ago
 */
function formatTimeAgo($seconds) {
    if ($seconds < 60) {
        return 'just now';
    } elseif ($seconds < 3600) {
        $minutes = floor($seconds / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($seconds < 86400) {
        $hours = floor($seconds / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($seconds < 604800) {
        $days = floor($seconds / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        $weeks = floor($seconds / 604800);
        return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
    }
}
?>
