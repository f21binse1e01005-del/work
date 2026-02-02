<?php
/**
 * Credential Sender Utility
 */

require_once '../config/security.php';
require_once '../config/middleware.php';
require_once '../config/notifications.php';

// Initialize security
Security::init();
Middleware::admin_access();

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = Middleware::sanitizeInput($_POST['user_id'], 'int');
    $sendEmail = isset($_POST['send_email']);
    $sendSMS = isset($_POST['send_sms']);
    
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Security token validation failed';
        $messageType = 'danger';
    } else {
        try {
            $db = (new Database())->getConnection();
            $stmt = $db->prepare("SELECT username, user_type FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Generate temporary password
                $tempPassword = bin2hex(random_bytes(4));
                $hashedPassword = hash('sha256', $tempPassword);
                
                // Update user password
                $stmt = $db->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE user_id = ?");
                $stmt->execute([$hashedPassword, $userId]);
                
                // Send credentials
                $notificationService = new NotificationService();
                $results = $notificationService->sendCredentials($userId, $user['username'], $tempPassword, $user['user_type']);
                
                $successMessages = [];
                if (isset($results['email']) && $results['email']['success']) {
                    $successMessages[] = 'Email sent';
                }
                if (isset($results['sms']) && $results['sms']['success']) {
                    $successMessages[] = 'SMS sent';
                }
                
                if (!empty($successMessages)) {
                    $message = 'Credentials sent successfully: ' . implode(', ', $successMessages);
                    $messageType = 'success';
                } else {
                    $message = 'Failed to send credentials';
                    $messageType = 'danger';
                }
            } else {
                $message = 'User not found';
                $messageType = 'danger';
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Get users for selection
$db = (new Database())->getConnection();
$users = $db->query("SELECT user_id, full_name, email, phone, user_type FROM users WHERE account_status = 'active' ORDER BY full_name")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Credentials - Skills Way</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-key me-2"></i>Send Login Credentials</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                                <?php echo htmlspecialchars($message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                            
                            <div class="mb-3">
                                <label class="form-label">Select User:</label>
                                <select name="user_id" class="form-select" required>
                                    <option value="">Choose a user...</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user['user_id']; ?>">
                                            <?php echo htmlspecialchars($user['full_name']); ?> 
                                            (<?php echo htmlspecialchars($user['email']); ?>) 
                                            - <?php echo ucfirst($user['user_type']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Send Via:</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="send_email" id="send_email" checked>
                                    <label class="form-check-label" for="send_email">
                                        <i class="fas fa-envelope me-1"></i>Email
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="send_sms" id="send_sms">
                                    <label class="form-check-label" for="send_sms">
                                        <i class="fas fa-sms me-1"></i>SMS
                                    </label>
                                </div>
                            </div>
                            
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Note:</strong> This will generate a new temporary password and send it to the user.
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i>Send Credentials
                            </button>
                            <a href="../admin/dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>