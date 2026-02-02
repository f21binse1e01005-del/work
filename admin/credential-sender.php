<?php
/**
 * Credential Sender - Email/SMS Notification System
 * File: admin/credential-sender.php
 */

session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login-test.php');
    exit;
}

$db = (new Database())->getConnection();
$message = '';
$messageType = '';

// Handle sending credentials
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'send_email') {
        $userIds = $_POST['user_ids'] ?? [];
        $emailTemplate = $_POST['email_template'] ?? 'default';
        $customMessage = $_POST['custom_message'] ?? '';
        
        $sentCount = 0;
        foreach ($userIds as $userId) {
            if (sendCredentialEmail($userId, $emailTemplate, $customMessage)) {
                $sentCount++;
            }
        }
        
        $message = "Credentials sent to $sentCount users successfully!";
        $messageType = 'success';
    }
    
    if ($action === 'send_sms') {
        $userIds = $_POST['user_ids'] ?? [];
        $smsTemplate = $_POST['sms_template'] ?? 'default';
        
        $sentCount = 0;
        foreach ($userIds as $userId) {
            if (sendCredentialSMS($userId, $smsTemplate)) {
                $sentCount++;
            }
        }
        
        $message = "SMS sent to $sentCount users successfully!";
        $messageType = 'success';
    }
}

// Get users without sent credentials
$users = $db->query("
    SELECT u.*, up.parent_phone,
           (SELECT COUNT(*) FROM user_activity_logs ual 
            WHERE ual.user_id = u.user_id AND ual.activity_type = 'credentials_sent') as credentials_sent
    FROM users u 
    LEFT JOIN user_profiles up ON u.user_id = up.user_id 
    WHERE u.user_type IN ('student', 'teacher')
    ORDER BY u.created_at DESC
")->fetchAll();

function sendCredentialEmail($userId, $template, $customMessage) {
    global $db;
    
    // Get user data
    $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) return false;
    
    // Email templates
    $templates = [
        'default' => [
            'subject' => 'Your Skills Way LMS Credentials',
            'body' => "Dear {full_name},\n\nWelcome to Skills Way Vocational Institute!\n\nYour login credentials:\nUsername: {username}\nPassword: [Contact admin for password]\n\nLogin at: http://skillsway.edu.pk/login.php\n\nBest regards,\nSkills Way Team"
        ],
        'welcome' => [
            'subject' => 'Welcome to Skills Way!',
            'body' => "Dear {full_name},\n\nWelcome to our learning management system!\n\n{custom_message}\n\nYour credentials:\nUsername: {username}\n\nPlease contact administration for your password.\n\nBest regards,\nSkills Way Team"
        ]
    ];
    
    $emailData = $templates[$template] ?? $templates['default'];
    
    // Replace placeholders
    $subject = str_replace('{full_name}', $user['full_name'], $emailData['subject']);
    $body = str_replace(['{full_name}', '{username}', '{custom_message}'], 
                       [$user['full_name'], $user['username'], $customMessage], 
                       $emailData['body']);
    
    // Simulate email sending (replace with actual email service)
    $emailSent = true; // mail($user['email'], $subject, $body);
    
    if ($emailSent) {
        // Log activity
        $stmt = $db->prepare("INSERT INTO user_activity_logs (user_id, activity_type, activity_details, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $userId, 
            'credentials_sent', 
            json_encode(['method' => 'email', 'template' => $template]),
            $_SERVER['REMOTE_ADDR']
        ]);
    }
    
    return $emailSent;
}

function sendCredentialSMS($userId, $template) {
    global $db;
    
    // Get user data
    $stmt = $db->prepare("SELECT u.*, up.parent_phone FROM users u LEFT JOIN user_profiles up ON u.user_id = up.user_id WHERE u.user_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user || (!$user['phone'] && !$user['parent_phone'])) return false;
    
    $phone = $user['phone'] ?: $user['parent_phone'];
    
    // SMS templates
    $templates = [
        'default' => "Dear {full_name}, Your Skills Way LMS username is: {username}. Contact admin for password. Login: skillsway.edu.pk/login.php",
        'short' => "Skills Way LMS - Username: {username}. Contact admin for password."
    ];
    
    $smsText = $templates[$template] ?? $templates['default'];
    $smsText = str_replace(['{full_name}', '{username}'], [$user['full_name'], $user['username']], $smsText);
    
    // Simulate SMS sending (replace with actual SMS service)
    $smsSent = true; // sendSMS($phone, $smsText);
    
    if ($smsSent) {
        // Log activity
        $stmt = $db->prepare("INSERT INTO user_activity_logs (user_id, activity_type, activity_details, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $userId, 
            'credentials_sent', 
            json_encode(['method' => 'sms', 'template' => $template, 'phone' => $phone]),
            $_SERVER['REMOTE_ADDR']
        ]);
    }
    
    return $smsSent;
}

require_once 'includes/header.php';
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-paper-plane me-2"></i>Credential Sender</h1>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Send Options -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-envelope me-1"></i>Send via Email</h6>
                </div>
                <div class="card-body">
                    <form method="POST" id="emailForm">
                        <input type="hidden" name="action" value="send_email">
                        
                        <div class="mb-3">
                            <label class="form-label">Email Template</label>
                            <select name="email_template" class="form-select">
                                <option value="default">Default Template</option>
                                <option value="welcome">Welcome Template</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Custom Message (Optional)</label>
                            <textarea name="custom_message" class="form-control" rows="3" 
                                      placeholder="Add any additional message..."></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Select users below and click "Send Selected Emails"
                            </small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100" disabled id="sendEmailBtn">
                            <i class="fas fa-envelope me-1"></i>Send Selected Emails
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-sms me-1"></i>Send via SMS</h6>
                </div>
                <div class="card-body">
                    <form method="POST" id="smsForm">
                        <input type="hidden" name="action" value="send_sms">
                        
                        <div class="mb-3">
                            <label class="form-label">SMS Template</label>
                            <select name="sms_template" class="form-select">
                                <option value="default">Default Template</option>
                                <option value="short">Short Template</option>
                            </select>
                        </div>
                        
                        <div class="alert alert-info small">
                            <i class="fas fa-info-circle me-1"></i>
                            SMS will be sent to user's phone or parent's phone if available.
                        </div>
                        
                        <button type="submit" class="btn btn-success w-100" disabled id="sendSMSBtn">
                            <i class="fas fa-sms me-1"></i>Send Selected SMS
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Users List -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0">Users List (<?php echo count($users); ?> users)</h6>
            <div>
                <button class="btn btn-outline-primary btn-sm" onclick="selectAll()">Select All</button>
                <button class="btn btn-outline-secondary btn-sm" onclick="selectNone()">Select None</button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="50">
                                <input type="checkbox" id="selectAllCheckbox" onchange="toggleAll()">
                            </th>
                            <th>User Info</th>
                            <th>Contact</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Credentials Sent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="user_ids[]" value="<?php echo $user['user_id']; ?>" 
                                       class="user-checkbox" onchange="updateButtons()">
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar bg-primary text-white rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                        <?php echo strtoupper(substr($user['full_name'], 0, 2)); ?>
                                    </div>
                                    <div>
                                        <h6 class="mb-0"><?php echo htmlspecialchars($user['full_name']); ?></h6>
                                        <small class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div><?php echo htmlspecialchars($user['email']); ?></div>
                                <small class="text-muted">
                                    <?php if ($user['phone']): ?>
                                        <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($user['phone']); ?>
                                    <?php elseif ($user['parent_phone']): ?>
                                        <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($user['parent_phone']); ?> (Parent)
                                    <?php else: ?>
                                        <i class="fas fa-phone-slash me-1"></i>No phone
                                    <?php endif; ?>
                                </small>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $user['user_type'] === 'student' ? 'primary' : 'success'; ?>">
                                    <?php echo ucfirst($user['user_type']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $user['account_status'] === 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($user['account_status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($user['credentials_sent'] > 0): ?>
                                    <span class="badge bg-success">
                                        <i class="fas fa-check me-1"></i>Sent (<?php echo $user['credentials_sent']; ?>)
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-warning">
                                        <i class="fas fa-clock me-1"></i>Pending
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script>
function updateButtons() {
    const checkboxes = document.querySelectorAll('.user-checkbox:checked');
    const emailBtn = document.getElementById('sendEmailBtn');
    const smsBtn = document.getElementById('sendSMSBtn');
    
    const hasSelection = checkboxes.length > 0;
    emailBtn.disabled = !hasSelection;
    smsBtn.disabled = !hasSelection;
    
    if (hasSelection) {
        emailBtn.innerHTML = `<i class="fas fa-envelope me-1"></i>Send to ${checkboxes.length} Users`;
        smsBtn.innerHTML = `<i class="fas fa-sms me-1"></i>Send to ${checkboxes.length} Users`;
    } else {
        emailBtn.innerHTML = '<i class="fas fa-envelope me-1"></i>Send Selected Emails';
        smsBtn.innerHTML = '<i class="fas fa-sms me-1"></i>Send Selected SMS';
    }
}

function toggleAll() {
    const selectAll = document.getElementById('selectAllCheckbox');
    const checkboxes = document.querySelectorAll('.user-checkbox');
    
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
    updateButtons();
}

function selectAll() {
    document.getElementById('selectAllCheckbox').checked = true;
    toggleAll();
}

function selectNone() {
    document.getElementById('selectAllCheckbox').checked = false;
    toggleAll();
}

// Add selected user IDs to forms before submission
document.getElementById('emailForm').addEventListener('submit', function(e) {
    addSelectedUsers(this);
});

document.getElementById('smsForm').addEventListener('submit', function(e) {
    addSelectedUsers(this);
});

function addSelectedUsers(form) {
    // Remove existing user_ids inputs
    const existingInputs = form.querySelectorAll('input[name="user_ids[]"]');
    existingInputs.forEach(input => input.remove());
    
    // Add selected user IDs
    const selectedCheckboxes = document.querySelectorAll('.user-checkbox:checked');
    selectedCheckboxes.forEach(checkbox => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'user_ids[]';
        input.value = checkbox.value;
        form.appendChild(input);
    });
}

// Initialize
updateButtons();
</script>

<?php require_once 'includes/footer.php'; ?>