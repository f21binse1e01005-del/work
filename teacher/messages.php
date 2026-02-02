<?php
session_start();
$pageTitle = 'Messages';
require_once 'includes/header.php';
require_once '../config/database.php';

$db = (new Database())->getConnection();

// Handle form submission
if ($_POST['action'] ?? '' === 'send') {
    $stmt = $db->prepare("INSERT INTO messages (sender_id, recipient_id, subject, content) VALUES (?, ?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $_POST['recipient_id'], $_POST['subject'], $_POST['content']]);
    header('Location: messages.php');
    exit;
}

// Get students and teachers for recipient dropdown
$stmt = $db->prepare("SELECT user_id, full_name, email, user_type as role FROM users WHERE user_type IN ('student', 'teacher') AND user_id != ? ORDER BY user_type, full_name");
$stmt->execute([$_SESSION['user_id']]);
$recipients = $stmt->fetchAll();

// Get messages
$stmt = $db->prepare("SELECT m.*, 
    sender.full_name as sender_name, 
    recipient.full_name as recipient_name 
    FROM messages m 
    JOIN users sender ON m.sender_id = sender.user_id 
    JOIN users recipient ON m.recipient_id = recipient.user_id 
    WHERE m.sender_id = ? OR m.recipient_id = ? 
    ORDER BY m.sent_at DESC");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$messages = $stmt->fetchAll();
?>

<div class="pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="fas fa-envelope me-2"></i>Messages</h1>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h5>Send New Message</h5>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="send">
            <div class="mb-3">
                <label class="form-label">To</label>
                <select name="recipient_id" class="form-select" required>
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
                <input type="text" name="subject" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Message</label>
                <textarea name="content" class="form-control" rows="5" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Send Message</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5>Message History</h5>
    </div>
    <div class="card-body">
        <?php if (empty($messages)): ?>
            <div class="text-center py-4">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <p class="text-muted">No messages yet</p>
            </div>
        <?php else: ?>
            <?php foreach ($messages as $message): ?>
                <div class="border-bottom pb-3 mb-3">
                    <div class="d-flex justify-content-between">
                        <h6><?php echo htmlspecialchars($message['subject']); ?></h6>
                        <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($message['sent_at'])); ?></small>
                    </div>
                    <p class="mb-1 text-muted">
                        <?php if ($message['sender_id'] == $_SESSION['user_id']): ?>
                            To: <?php echo htmlspecialchars($message['recipient_name']); ?>
                        <?php else: ?>
                            From: <?php echo htmlspecialchars($message['sender_name']); ?>
                        <?php endif; ?>
                    </p>
                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($message['content'])); ?></p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>