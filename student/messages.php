<?php
$pageTitle = 'Messages';
session_start(); // Add session start
require_once 'includes/header.php';
require_once '../config/database.php';

$db = (new Database())->getConnection();
$user_id = $_SESSION['user_id'] ?? null;

// Check if user is logged in
if (!$user_id) {
    header('Location: login.php');
    exit;
}

// Handle form submission
if (isset($_POST['action']) && $_POST['action'] === 'send') {
    $recipient_id = filter_var($_POST['recipient_id'], FILTER_VALIDATE_INT);
    $subject = trim($_POST['subject']);
    $content = trim($_POST['content']);
    
    // Validate input
    if (!$recipient_id || empty($subject) || empty($content)) {
        $error = "Please fill in all fields correctly.";
    } else {
        $stmt = $db->prepare("INSERT INTO messages (sender_id, receiver_id, subject, message_text) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$user_id, $recipient_id, $subject, $content])) {
            header('Location: messages.php?sent=1');
            exit;
        } else {
            $error = "Failed to send message. Please try again.";
        }
    }
}

// Get teachers and classmates for recipient dropdown
// Simplified query - get all teachers and active students
$stmt = $db->prepare("
    SELECT u.user_id, u.full_name, u.email, u.user_type as role 
    FROM users u 
    WHERE u.account_status = 'active' 
    AND u.user_id != ?
    AND (
        u.user_type = 'teacher' 
        OR (u.user_type = 'student' AND u.user_id IN (
            SELECT DISTINCT ea.user_id 
            FROM enrollment_applications ea
            JOIN enrollments e ON ea.application_id = e.application_id
            WHERE e.enrollment_status = 'active'
        ))
    )
    ORDER BY FIELD(u.user_type, 'teacher', 'student'), u.full_name
");
$stmt->execute([$user_id]);
$recipients = $stmt->fetchAll();

// Get messages with proper column names from database
$stmt = $db->prepare("
    SELECT 
        m.*,
        sender.full_name as sender_name,
        receiver.full_name as receiver_name
    FROM messages m 
    JOIN users sender ON m.sender_id = sender.user_id 
    JOIN users receiver ON m.receiver_id = receiver.user_id 
    WHERE m.sender_id = ? OR m.receiver_id = ? 
    ORDER BY m.sent_at DESC
");
$stmt->execute([$user_id, $user_id]);
$messages = $stmt->fetchAll();
?>

<div class="pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="fas fa-envelope me-2"></i>Messages</h1>
</div>

<?php if (isset($_GET['sent'])): ?>
<div class="alert alert-success alert-dismissible fade show">
    <h5><i class="fas fa-check-circle me-2"></i>Message Sent!</h5>
    <p>Your message has been sent successfully.</p>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($error)): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <h5><i class="fas fa-exclamation-circle me-2"></i>Error</h5>
    <p><?php echo htmlspecialchars($error); ?></p>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="fas fa-users me-2"></i>Recipients</h5>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <?php if (empty($recipients)): ?>
                        <div class="text-center py-3">
                            <i class="fas fa-user-friends fa-2x text-muted mb-2"></i>
                            <p class="text-muted small">No recipients available</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recipients as $recipient): ?>
                        <a href="#" class="list-group-item list-group-item-action recipient-item" data-userid="<?php echo $recipient['user_id']; ?>">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><?php echo htmlspecialchars($recipient['full_name']); ?></h6>
                                <small class="text-muted"><?php echo ucfirst($recipient['role']); ?></small>
                            </div>
                            <p class="mb-1 text-muted small"><?php echo htmlspecialchars($recipient['email']); ?></p>
                        </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-chart-bar me-2"></i>Message Stats</h5>
            </div>
            <div class="card-body">
                <?php
                $sent_count = 0;
                $received_count = 0;
                $unread_count = 0;
                
                foreach ($messages as $message) {
                    if ($message['sender_id'] == $user_id) {
                        $sent_count++;
                    } else {
                        $received_count++;
                        if (!$message['is_read']) {
                            $unread_count++;
                        }
                    }
                }
                ?>
                <div class="d-flex justify-content-between mb-2">
                    <span>Total Messages:</span>
                    <strong><?php echo count($messages); ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Sent:</span>
                    <strong><?php echo $sent_count; ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Received:</span>
                    <strong><?php echo $received_count; ?></strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Unread:</span>
                    <strong class="<?php echo $unread_count > 0 ? 'text-danger' : 'text-success'; ?>">
                        <?php echo $unread_count; ?>
                    </strong>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="fas fa-paper-plane me-2"></i>Send New Message</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="messageForm">
                    <input type="hidden" name="action" value="send">
                    
                    <div class="mb-3">
                        <label class="form-label">To</label>
                        <select name="recipient_id" id="recipientSelect" class="form-select" required>
                            <option value="">Select recipient...</option>
                            <?php 
                            $current_role = '';
                            foreach ($recipients as $recipient): 
                                if ($current_role !== $recipient['role']):
                                    if ($current_role !== '') echo '</optgroup>';
                                    echo '<optgroup label="' . ucfirst($recipient['role']) . 's">';
                                    $current_role = $recipient['role'];
                                endif;
                            ?>
                                <option value="<?php echo $recipient['user_id']; ?>">
                                    <?php echo htmlspecialchars($recipient['full_name']); ?> (<?php echo htmlspecialchars($recipient['email']); ?>)
                                </option>
                            <?php endforeach; ?>
                            <?php if ($current_role !== '') echo '</optgroup>'; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Subject</label>
                        <input type="text" name="subject" id="messageSubject" class="form-control" maxlength="200" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Message</label>
                        <textarea name="content" id="messageContent" class="form-control" rows="5" maxlength="5000" required></textarea>
                        <div class="form-text text-end">
                            <span id="charCount">0</span>/5000 characters
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <button type="reset" class="btn btn-secondary">Clear</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i>Send Message
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-history me-2"></i>Message History</h5>
                <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-outline-secondary filter-btn active" data-filter="all">All</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary filter-btn" data-filter="sent">Sent</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary filter-btn" data-filter="received">Received</button>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($messages)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No messages yet</h5>
                        <p class="text-muted small">Start a conversation by sending your first message.</p>
                    </div>
                <?php else: ?>
                    <div id="messagesContainer">
                        <?php foreach ($messages as $message): 
                            $is_sent = $message['sender_id'] == $user_id;
                            $is_unread = !$is_sent && !$message['is_read'];
                        ?>
                        <div class="message-item border-bottom pb-3 mb-3 <?php echo $is_sent ? 'sent' : 'received'; ?>" 
                             data-id="<?php echo $message['message_id']; ?>"
                             data-type="<?php echo $is_sent ? 'sent' : 'received'; ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1 <?php echo $is_unread ? 'fw-bold' : ''; ?>">
                                        <?php echo htmlspecialchars($message['subject']); ?>
                                        <?php if ($is_unread): ?>
                                            <span class="badge bg-danger ms-2">New</span>
                                        <?php endif; ?>
                                    </h6>
                                    <p class="mb-1 text-muted small">
                                        <?php if ($is_sent): ?>
                                            <span class="badge bg-primary me-2">Sent</span> 
                                            To: <?php echo htmlspecialchars($message['receiver_name']); ?>
                                        <?php else: ?>
                                            <span class="badge bg-success me-2">Received</span> 
                                            From: <?php echo htmlspecialchars($message['sender_name']); ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="text-end">
                                    <small class="text-muted d-block">
                                        <?php echo date('M j, Y g:i A', strtotime($message['sent_at'])); ?>
                                    </small>
                                    <?php if ($message['read_at'] && !$is_sent): ?>
                                        <small class="text-success d-block">
                                            Read: <?php echo date('g:i A', strtotime($message['read_at'])); ?>
                                        </small>
                                    <?php elseif ($is_sent): ?>
                                        <small class="<?php echo $message['read_at'] ? 'text-success' : 'text-muted'; ?> d-block">
                                            <?php echo $message['read_at'] ? 'Read' : 'Unread'; ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="mt-2">
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($message['message_text'])); ?></p>
                            </div>
                            <div class="mt-2">
                                <button class="btn btn-sm btn-outline-secondary reply-btn" 
                                        data-recipient="<?php echo $is_sent ? $message['receiver_id'] : $message['sender_id']; ?>"
                                        data-name="<?php echo htmlspecialchars($is_sent ? $message['receiver_name'] : $message['sender_name']); ?>"
                                        data-subject="Re: <?php echo htmlspecialchars($message['subject']); ?>">
                                    <i class="fas fa-reply me-1"></i>Reply
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
$(document).ready(function() {
    // Character count for message textarea
    $('#messageContent').on('input', function() {
        $('#charCount').text($(this).val().length);
    });
    
    // Filter messages
    $('.filter-btn').click(function() {
        $('.filter-btn').removeClass('active');
        $(this).addClass('active');
        
        var filter = $(this).data('filter');
        if (filter === 'all') {
            $('.message-item').show();
        } else {
            $('.message-item').hide();
            $('.message-item[data-type="' + filter + '"]').show();
        }
    });
    
    // Auto-select recipient from recipient list
    $('.recipient-item').click(function(e) {
        e.preventDefault();
        var userId = $(this).data('userid');
        $('#recipientSelect').val(userId);
        
        // Scroll to form and focus on subject
        $('html, body').animate({
            scrollTop: $('#messageForm').offset().top - 20
        }, 500);
        $('#messageSubject').focus();
    });
    
    // Reply button functionality
    $('.reply-btn').click(function() {
        var recipientId = $(this).data('recipient');
        var recipientName = $(this).data('name');
        var subject = $(this).data('subject');
        
        // Set form values
        $('#recipientSelect').val(recipientId);
        $('#messageSubject').val(subject);
        $('#messageContent').focus();
        
        // Scroll to form
        $('html, body').animate({
            scrollTop: $('#messageForm').offset().top - 20
        }, 500);
        
        // Show notification
        showToast('Reply to ' + recipientName, 'info');
    });
    
    // Mark message as read when viewing received messages
    $('.message-item.received').click(function() {
        var messageId = $(this).data('id');
        var $badge = $(this).find('.badge.bg-danger');
        
        if ($badge.length > 0) {
            // AJAX call to mark as read
            $.post('mark_read.php', { message_id: messageId })
                .done(function(response) {
                    if (response.success) {
                        $badge.remove();
                        $(this).find('h6').removeClass('fw-bold');
                        // Update unread count
                        updateUnreadCount(-1);
                    }
                }.bind(this));
        }
    });
    
    function updateUnreadCount(change) {
        var $unreadCount = $('.card:contains("Unread:") strong');
        var current = parseInt($unreadCount.text());
        $unreadCount.text(current + change);
        
        if (current + change > 0) {
            $unreadCount.removeClass('text-success').addClass('text-danger');
        } else {
            $unreadCount.removeClass('text-danger').addClass('text-success');
        }
    }
    
    function showToast(message, type = 'info') {
        // Simple toast notification
        var toast = $('<div class="toast-alert">' + message + '</div>');
        $('body').append(toast);
        setTimeout(function() {
            toast.remove();
        }, 3000);
    }
    
    // Initialize character count
    $('#charCount').text($('#messageContent').val().length);
});
</script>

<style>
.message-item {
    transition: all 0.3s ease;
    cursor: pointer;
}

.message-item:hover {
    background-color: #f8f9fa;
    padding-left: 10px;
    padding-right: 10px;
    margin-left: -10px;
    margin-right: -10px;
}

.message-item.sent {
    border-left: 3px solid #0d6efd;
}

.message-item.received {
    border-left: 3px solid #198754;
}

.recipient-item:hover {
    background-color: #e9ecef;
}

.toast-alert {
    position: fixed;
    bottom: 20px;
    right: 20px;
    background: #333;
    color: white;
    padding: 10px 20px;
    border-radius: 5px;
    z-index: 1000;
    animation: fadeInOut 3s ease;
}

@keyframes fadeInOut {
    0% { opacity: 0; transform: translateY(20px); }
    10% { opacity: 1; transform: translateY(0); }
    90% { opacity: 1; transform: translateY(0); }
    100% { opacity: 0; transform: translateY(20px); }
}
</style>