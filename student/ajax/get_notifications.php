<?php
/**
 * Skills Way LMS - Get Notifications AJAX Endpoint
 * Returns user notifications in JSON format
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
        'html' => '<div class="text-center py-3 text-muted">Please log in to view notifications</div>'
    ]);
    exit;
}

try {
    $db = (new Database())->getConnection();
    $user_id = $session->get('user_id');
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    
    // Get notifications
    $stmt = $db->prepare("
        SELECT 
            notification_id,
            notification_type,
            title,
            message,
            related_id,
            is_read,
            created_at,
            TIMESTAMPDIFF(SECOND, created_at, NOW()) as seconds_ago
        FROM notifications
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT ?
    ");
    
    $stmt->execute([$user_id, $limit]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get unread count
    $stmt = $db->prepare("
        SELECT COUNT(*) as count
        FROM notifications
        WHERE user_id = ? AND is_read = FALSE
    ");
    $stmt->execute([$user_id]);
    $unread_count = $stmt->fetchColumn();
    
    // Generate HTML
    $html = '';
    
    if (empty($notifications)) {
        $html = '
            <div class="text-center py-5">
                <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                <p class="text-muted">No notifications yet</p>
            </div>
        ';
    } else {
        foreach ($notifications as $notification) {
            $icon_map = [
                'assignment' => 'fa-tasks text-primary',
                'quiz' => 'fa-question-circle text-info',
                'grade' => 'fa-star text-warning',
                'message' => 'fa-envelope text-success',
                'announcement' => 'fa-bullhorn text-danger',
                'course' => 'fa-book text-primary',
                'default' => 'fa-bell text-secondary'
            ];
            
            $icon = $icon_map[$notification['notification_type']] ?? $icon_map['default'];
            $unread_class = $notification['is_read'] ? '' : 'bg-light';
            $unread_dot = $notification['is_read'] ? '' : '<span class="badge bg-primary rounded-pill">New</span>';
            
            // Format time ago
            $seconds = $notification['seconds_ago'];
            $time_ago = formatTimeAgo($seconds);
            
            // Build link based on notification type and related_id
            $link = '#';
            if (!empty($notification['related_id'])) {
                switch ($notification['notification_type']) {
                    case 'assignment':
                    case 'deadline':
                        $link = 'assignments.php?assignment_id=' . $notification['related_id'];
                        break;
                    case 'grade':
                        $link = 'progress.php';
                        break;
                    case 'message':
                        $link = 'messages.php?message_id=' . $notification['related_id'];
                        break;
                    case 'announcement':
                        $link = 'announcements.php';
                        break;
                    case 'enrollment':
                        $link = 'dashboard.php';
                        break;
                }
            }
            
            $html .= '
                <a href="' . htmlspecialchars($link) . '" 
                   class="dropdown-item d-flex align-items-start py-3 ' . $unread_class . '" 
                   data-notification-id="' . $notification['notification_id'] . '">
                    <div class="me-3">
                        <i class="fas ' . $icon . ' fa-lg"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <strong class="d-block">' . htmlspecialchars($notification['title']) . '</strong>
                            ' . $unread_dot . '
                        </div>
                        <p class="mb-1 text-muted small">' . htmlspecialchars($notification['message']) . '</p>
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
        'total' => count($notifications),
        'html' => $html
    ]);
    
} catch (PDOException $e) {
    error_log("Get Notifications Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load notifications',
        'count' => 0,
        'html' => '<div class="text-center py-3 text-danger">Error loading notifications</div>'
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
