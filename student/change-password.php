<?php
$pageTitle = 'Change Password';
$pageIcon = 'fas fa-key';
require_once 'includes/header.php';
require_once '../config/database.php';

$db = (new Database())->getConnection();
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validation
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            throw new Exception('All fields are required.');
        }
        
        if ($new_password !== $confirm_password) {
            throw new Exception('New passwords do not match.');
        }
        
        if (strlen($new_password) < 8) {
            throw new Exception('Password must be at least 8 characters long.');
        }
        
        if (!preg_match('/[A-Z]/', $new_password)) {
            throw new Exception('Password must contain at least one uppercase letter.');
        }
        
        if (!preg_match('/[a-z]/', $new_password)) {
            throw new Exception('Password must contain at least one lowercase letter.');
        }
        
        if (!preg_match('/[0-9]/', $new_password)) {
            throw new Exception('Password must contain at least one number.');
        }
        
        // Verify current password
        $stmt = $db->prepare("SELECT password_hash FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || !password_verify($current_password, $user['password_hash'])) {
            throw new Exception('Current password is incorrect.');
        }
        
        // Update password
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE user_id = ?");
        $stmt->execute([$new_hash, $user_id]);
        
        // Log the password change
        $stmt = $db->prepare("
            INSERT INTO user_activity_logs (user_id, activity_type, ip_address, user_agent)
            VALUES (?, 'password_change', ?, ?)
        ");
        $stmt->execute([$user_id, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
        
        $_SESSION['success_message'] = 'Password changed successfully!';
        header('Location: change-password.php');
        exit;
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>

<div class="container-fluid px-4">
    <div class="row justify-content-center">
        <div class="col-lg-6">
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
            
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-key me-2"></i>Change Password</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">
                        <i class="fas fa-info-circle me-1"></i>
                        For your security, please choose a strong password that you haven't used elsewhere.
                    </p>
                    
                    <form method="POST" action="" id="changePasswordForm">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="current_password" 
                                       name="current_password" required placeholder="Enter current password">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('current_password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-key"></i></span>
                                <input type="password" class="form-control" id="new_password" 
                                       name="new_password" required placeholder="Enter new password">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new_password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">
                                <div id="passwordStrength" class="progress mt-2">
                                    <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                                </div>
                                <small id="passwordStrengthText"></small>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="confirm_password" class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-check"></i></span>
                                <input type="password" class="form-control" id="confirm_password" 
                                       name="confirm_password" required placeholder="Re-enter new password">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="form-text" id="passwordMatch"></div>
                        </div>
                        
                        <!-- Password Requirements -->
                        <div class="alert alert-info mb-4">
                            <strong>Password Requirements:</strong>
                            <ul class="mb-0 mt-2">
                                <li id="req-length">At least 8 characters</li>
                                <li id="req-upper">At least one uppercase letter</li>
                                <li id="req-lower">At least one lowercase letter</li>
                                <li id="req-number">At least one number</li>
                            </ul>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-primary" id="submitBtn">
                                <i class="fas fa-save me-1"></i> Change Password
                            </button>
                            <a href="settings.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Security Tips -->
            <div class="card shadow-sm mt-3">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Security Tips</h6>
                </div>
                <div class="card-body">
                    <ul class="small mb-0">
                        <li>Use a unique password for this account</li>
                        <li>Don't share your password with anyone</li>
                        <li>Change your password regularly</li>
                        <li>Use a password manager to generate and store strong passwords</li>
                        <li>Enable two-factor authentication when available</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle password visibility
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const btn = field.nextElementSibling;
    
    if (field.type === 'password') {
        field.type = 'text';
        btn.innerHTML = '<i class="fas fa-eye-slash"></i>';
    } else {
        field.type = 'password';
        btn.innerHTML = '<i class="fas fa-eye"></i>';
    }
}

// Password strength checker
document.getElementById('new_password').addEventListener('input', function() {
    const password = this.value;
    const strengthBar = document.querySelector('#passwordStrength .progress-bar');
    const strengthText = document.getElementById('passwordStrengthText');
    
    let strength = 0;
    if (password.length >= 8) strength += 25;
    if (/[A-Z]/.test(password)) strength += 25;
    if (/[a-z]/.test(password)) strength += 25;
    if (/[0-9]/.test(password)) strength += 25;
    
    strengthBar.style.width = strength + '%';
    
    if (strength === 0) {
        strengthBar.className = 'progress-bar';
        strengthText.textContent = '';
    } else if (strength <= 25) {
        strengthBar.className = 'progress-bar bg-danger';
        strengthText.textContent = 'Weak';
        strengthText.className = 'text-danger';
    } else if (strength <= 50) {
        strengthBar.className = 'progress-bar bg-warning';
        strengthText.textContent = 'Fair';
        strengthText.className = 'text-warning';
    } else if (strength <= 75) {
        strengthBar.className = 'progress-bar bg-info';
        strengthText.textContent = 'Good';
        strengthText.className = 'text-info';
    } else {
        strengthBar.className = 'progress-bar bg-success';
        strengthText.textContent = 'Strong';
        strengthText.className = 'text-success';
    }
    
    // Update requirements
    updateRequirement('req-length', password.length >= 8);
    updateRequirement('req-upper', /[A-Z]/.test(password));
    updateRequirement('req-lower', /[a-z]/.test(password));
    updateRequirement('req-number', /[0-9]/.test(password));
});

// Check password match
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = this.value;
    const matchText = document.getElementById('passwordMatch');
    
    if (confirmPassword === '') {
        matchText.textContent = '';
    } else if (newPassword === confirmPassword) {
        matchText.innerHTML = '<i class="fas fa-check text-success"></i> Passwords match';
        matchText.className = 'form-text text-success';
    } else {
        matchText.innerHTML = '<i class="fas fa-times text-danger"></i> Passwords do not match';
        matchText.className = 'form-text text-danger';
    }
});

function updateRequirement(id, met) {
    const element = document.getElementById(id);
    if (met) {
        element.innerHTML = '<i class="fas fa-check text-success"></i> ' + element.textContent.replace(/^.* /, '');
        element.className = 'text-success';
    } else {
        element.innerHTML = element.textContent.replace('<i class="fas fa-check text-success"></i> ', '');
        element.className = '';
    }
}

// Form validation
document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (newPassword !== confirmPassword) {
        e.preventDefault();
        alert('Passwords do not match!');
        return false;
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
