<?php
$pageTitle = 'Account Settings';
$pageIcon = 'fas fa-cog';
require_once 'includes/header.php';
require_once '../config/database.php';

$db = (new Database())->getConnection();
$user_id = $_SESSION['user_id'];

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
        $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
        $assignment_reminders = isset($_POST['assignment_reminders']) ? 1 : 0;
        $grade_notifications = isset($_POST['grade_notifications']) ? 1 : 0;
        $announcement_notifications = isset($_POST['announcement_notifications']) ? 1 : 0;
        
        // Update or insert settings
        $stmt = $db->prepare("
            INSERT INTO user_settings (user_id, email_notifications, sms_notifications, 
                                      assignment_reminders, grade_notifications, announcement_notifications)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                email_notifications = VALUES(email_notifications),
                sms_notifications = VALUES(sms_notifications),
                assignment_reminders = VALUES(assignment_reminders),
                grade_notifications = VALUES(grade_notifications),
                announcement_notifications = VALUES(announcement_notifications)
        ");
        
        $stmt->execute([$user_id, $email_notifications, $sms_notifications, 
                       $assignment_reminders, $grade_notifications, $announcement_notifications]);
        
        $_SESSION['success_message'] = 'Settings updated successfully!';
        header('Location: settings.php');
        exit;
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Fetch current settings
$stmt = $db->prepare("SELECT * FROM user_settings WHERE user_id = ?");
$stmt->execute([$user_id]);
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

// Default settings if none exist
if (!$settings) {
    $settings = [
        'email_notifications' => 1,
        'sms_notifications' => 0,
        'assignment_reminders' => 1,
        'grade_notifications' => 1,
        'announcement_notifications' => 1
    ];
}
?>

<div class="container-fluid px-4">
    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle me-2"></i>
        <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle me-2"></i>
        <?php echo htmlspecialchars($error_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-3 mb-4">
            <!-- Settings Navigation -->
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0"><i class="fas fa-sliders-h me-2"></i>Settings</h6>
                </div>
                <div class="list-group list-group-flush settings-nav">
                    <a href="#notifications" class="list-group-item list-group-item-action active">
                        <i class="fas fa-bell me-2"></i>Notifications
                    </a>
                    <a href="profile.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-user me-2"></i>Profile
                    </a>
                    <a href="change-password.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-key me-2"></i>Change Password
                    </a>
                    <a href="#privacy" class="list-group-item list-group-item-action">
                        <i class="fas fa-shield-alt me-2"></i>Privacy
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-lg-9">
            <!-- Notification Settings -->
            <div class="card shadow-sm mb-4" id="notifications">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-bell me-2"></i>Notification Preferences</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="mb-4">
                            <h6 class="text-muted mb-3">Communication Channels</h6>
                            
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="email_notifications" 
                                       name="email_notifications" <?php echo $settings['email_notifications'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="email_notifications">
                                    <strong>Email Notifications</strong>
                                    <br><small class="text-muted">Receive notifications via email</small>
                                </label>
                            </div>
                            
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="sms_notifications" 
                                       name="sms_notifications" <?php echo $settings['sms_notifications'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="sms_notifications">
                                    <strong>SMS Notifications</strong>
                                    <br><small class="text-muted">Receive notifications via SMS</small>
                                </label>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="mb-4">
                            <h6 class="text-muted mb-3">Notification Types</h6>
                            
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="assignment_reminders" 
                                       name="assignment_reminders" <?php echo $settings['assignment_reminders'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="assignment_reminders">
                                    <strong>Assignment Reminders</strong>
                                    <br><small class="text-muted">Get reminded about upcoming assignment deadlines</small>
                                </label>
                            </div>
                            
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="grade_notifications" 
                                       name="grade_notifications" <?php echo $settings['grade_notifications'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="grade_notifications">
                                    <strong>Grade Updates</strong>
                                    <br><small class="text-muted">Receive notifications when grades are posted</small>
                                </label>
                            </div>
                            
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="announcement_notifications" 
                                       name="announcement_notifications" <?php echo $settings['announcement_notifications'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="announcement_notifications">
                                    <strong>Announcements</strong>
                                    <br><small class="text-muted">Receive important announcements from instructors</small>
                                </label>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Save Settings
                            </button>
                            <a href="dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Privacy Settings -->
            <div class="card shadow-sm" id="privacy">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Privacy & Security</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6>Profile Visibility</h6>
                        <p class="text-muted small">Control who can see your profile information</p>
                        <select class="form-select">
                            <option value="public">Public - Visible to everyone</option>
                            <option value="students" selected>Students Only - Visible to other students</option>
                            <option value="private">Private - Only visible to instructors</option>
                        </select>
                    </div>
                    
                    <hr>
                    
                    <div class="mb-3">
                        <h6>Two-Factor Authentication</h6>
                        <p class="text-muted small">Add an extra layer of security to your account</p>
                        <button class="btn btn-outline-primary btn-sm" disabled>
                            <i class="fas fa-lock me-1"></i> Enable 2FA (Coming Soon)
                        </button>
                    </div>
                    
                    <hr>
                    
                    <div class="mb-0">
                        <h6 class="text-danger">Danger Zone</h6>
                        <p class="text-muted small">Irreversible actions</p>
                        <button class="btn btn-outline-danger btn-sm" onclick="alert('Please contact support to delete your account.')">
                            <i class="fas fa-trash me-1"></i> Delete Account
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
