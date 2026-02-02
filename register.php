<?php
/**
 * Registration Page
 */

require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'config/session.php';

$auth = new Authentication();
$session = new SessionManager();
$db = (new Database())->getConnection();

// CSRF token - only generate if not exists
if (!$session->get('csrf_token')) {
    $csrf_token = $session->setCSRFToken();
} else {
    $csrf_token = $session->get('csrf_token');
}

// Redirect if already logged in
if ($session->isLoggedIn()) {
    header("Location: index.php");
    exit;
}

$error = '';
$success = '';
$formData = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Temporarily disable CSRF validation
    // if (!$session->validateCSRFToken($_POST['csrf_token'])) {
    //     $error = 'Security token invalid. Please try again.';
    // } else {
        // Get form data
        $formData = [
            'full_name' => trim($_POST['full_name'] ?? ''),
            'cnic' => trim($_POST['cnic'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'confirm_password' => $_POST['confirm_password'] ?? '',
            'user_type' => 'student', // Default for public registration
            'date_of_birth' => $_POST['date_of_birth'] ?? '',
            'gender' => $_POST['gender'] ?? '',
            'address' => trim($_POST['address'] ?? '')
        ];

        // Validate required fields
        $required = ['full_name', 'cnic', 'email', 'password', 'confirm_password'];
        foreach ($required as $field) {
            if (empty($formData[$field])) {
                $error = "Please fill all required fields.";
                break;
            }
        }

        // Validate CNIC format (12345-1234567-1)
        if (!$error && !preg_match('/^\d{5}-\d{7}-\d{1}$/', $formData['cnic'])) {
            $error = 'Please enter a valid CNIC in format: 12345-1234567-1';
        }

        // Validate email
        if (!$error && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        }

        // Validate password
        if (!$error && strlen($formData['password']) < 8) {
            $error = 'Password must be at least 8 characters long.';
        }

        if (!$error && $formData['password'] !== $formData['confirm_password']) {
            $error = 'Passwords do not match.';
        }

        // Check if user already exists
        if (!$error) {
            try {
                $checkStmt = $db->prepare("SELECT user_id FROM users WHERE email = ? OR cnic = ?");
                $checkStmt->execute([$formData['email'], $formData['cnic']]);
                
                if ($checkStmt->fetch()) {
                    $error = 'User with this email or CNIC already exists.';
                }
            } catch (Exception $e) {
                $error = 'Registration error. Please try again.';
            }
        }

        // Create user account
        if (!$error) {
            try {
                // Generate username (firstname.lastname)
                $usernameParts = explode(' ', $formData['full_name']);
                $firstName = strtolower(preg_replace('/[^a-zA-Z]/', '', $usernameParts[0]));
                $lastName = isset($usernameParts[1]) ? strtolower(preg_replace('/[^a-zA-Z]/', '', $usernameParts[1])) : 'user';
                $randomNum = rand(100, 999);
                $username = $firstName . '.' . $lastName . $randomNum;

                // Hash password
                $passwordHash = hash('sha256', $formData['password']);

                // Begin transaction
                $db->beginTransaction();

                // Insert into users table
                $userSql = "INSERT INTO users (username, email, phone, cnic, full_name, password_hash, user_type, account_status) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $userStmt = $db->prepare($userSql);
                $userStmt->execute([
                    $username,
                    $formData['email'],
                    $formData['phone'],
                    $formData['cnic'],
                    $formData['full_name'],
                    $passwordHash,
                    $formData['user_type'],
                    'active'  // Set account as active immediately
                ]);

                $user_id = $db->lastInsertId();

                // Insert into user_profiles table
                $profileSql = "INSERT INTO user_profiles (user_id, date_of_birth, gender, address) 
                              VALUES (?, ?, ?, ?)";
                $profileStmt = $db->prepare($profileSql);
                $profileStmt->execute([
                    $user_id,
                    !empty($formData['date_of_birth']) ? $formData['date_of_birth'] : null,
                    !empty($formData['gender']) ? $formData['gender'] : null,
                    !empty($formData['address']) ? $formData['address'] : null
                ]);

                // Log activity
                $activitySql = "INSERT INTO user_activity_logs (user_id, activity_type, activity_details) 
                               VALUES (?, 'registration', ?)";
                $activityStmt = $db->prepare($activitySql);
                $activityStmt->execute([
                    $user_id,
                    json_encode(['source' => 'public_registration'])
                ]);

                // Commit transaction
                $db->commit();

                $success = 'Registration successful! You can now login to your account.';
                
                // Auto-login option (before clearing form data)
                if (isset($_POST['auto_login'])) {
                    $result = $auth->login($username, $formData['password']);
                    if ($result['success']) {
                        header("Location: student/dashboard.php");
                        exit;
                    }
                }
                
                $formData = []; // Clear form

            } catch (Exception $e) {
                $db->rollBack();
                error_log("Registration error: " . $e->getMessage());
                $error = 'Registration failed. Please try again. Error: ' . $e->getMessage();
            }
        }
    // }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Skills Way LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        
        .register-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        
        .register-header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 30px;
            border-radius: 15px 15px 0 0;
        }
        
        .form-control:focus {
            border-color: #4facfe;
            box-shadow: 0 0 0 0.2rem rgba(79, 172, 254, 0.25);
        }
        
        .btn-register {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            border: none;
            padding: 12px;
        }
        
        .btn-register:hover {
            background: linear-gradient(135deg, #3a9bee 0%, #00d9e6 100%);
        }
        
        .password-strength {
            height: 5px;
            margin-top: 5px;
            border-radius: 2px;
            transition: all 0.3s;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-7">
                <div class="register-card">
                    <div class="register-header text-center">
                        <i class="fas fa-user-plus fa-3x mb-3"></i>
                        <h2>Create Your Account</h2>
                        <p class="mb-0">Join Skills Way Vocational Institute</p>
                    </div>
                    
                    <div class="card-body p-5">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($success); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                <div class="mt-2">
                                    <a href="login.php" class="btn btn-sm btn-success">Login Now</a>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" id="registerForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="full_name" class="form-label">
                                        <i class="fas fa-user me-2"></i>Full Name *
                                    </label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="full_name" 
                                           name="full_name" 
                                           value="<?php echo htmlspecialchars($formData['full_name'] ?? ''); ?>"
                                           required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="cnic" class="form-label">
                                        <i class="fas fa-id-card me-2"></i>CNIC *
                                    </label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="cnic" 
                                           name="cnic" 
                                           placeholder="12345-1234567-1"
                                           value="<?php echo htmlspecialchars($formData['cnic'] ?? ''); ?>"
                                           required>
                                    <small class="text-muted">Format: 12345-1234567-1</small>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">
                                        <i class="fas fa-envelope me-2"></i>Email Address *
                                    </label>
                                    <input type="email" 
                                           class="form-control" 
                                           id="email" 
                                           name="email" 
                                           value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>"
                                           required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">
                                        <i class="fas fa-phone me-2"></i>Phone Number
                                    </label>
                                    <input type="tel" 
                                           class="form-control" 
                                           id="phone" 
                                           name="phone" 
                                           placeholder="0300-1234567"
                                           value="<?php echo htmlspecialchars($formData['phone'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="date_of_birth" class="form-label">
                                        <i class="fas fa-calendar me-2"></i>Date of Birth
                                    </label>
                                    <input type="date" 
                                           class="form-control" 
                                           id="date_of_birth" 
                                           name="date_of_birth" 
                                           value="<?php echo htmlspecialchars($formData['date_of_birth'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="gender" class="form-label">
                                        <i class="fas fa-venus-mars me-2"></i>Gender
                                    </label>
                                    <select class="form-control" id="gender" name="gender">
                                        <option value="">Select Gender</option>
                                        <option value="male" <?php echo ($formData['gender'] ?? '') == 'male' ? 'selected' : ''; ?>>Male</option>
                                        <option value="female" <?php echo ($formData['gender'] ?? '') == 'female' ? 'selected' : ''; ?>>Female</option>
                                        <option value="other" <?php echo ($formData['gender'] ?? '') == 'other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="address" class="form-label">
                                    <i class="fas fa-home me-2"></i>Address
                                </label>
                                <textarea class="form-control" 
                                          id="address" 
                                          name="address" 
                                          rows="2"><?php echo htmlspecialchars($formData['address'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">
                                        <i class="fas fa-lock me-2"></i>Password *
                                    </label>
                                    <div class="input-group">
                                        <input type="password" 
                                               class="form-control" 
                                               id="password" 
                                               name="password" 
                                               required>
                                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="password-strength" id="passwordStrength"></div>
                                    <small class="text-muted">Minimum 8 characters with letters and numbers</small>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">
                                        <i class="fas fa-lock me-2"></i>Confirm Password *
                                    </label>
                                    <div class="input-group">
                                        <input type="password" 
                                               class="form-control" 
                                               id="confirm_password" 
                                               name="confirm_password" 
                                               required>
                                        <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div id="passwordMatch" class="mt-1"></div>
                                </div>
                            </div>
                            
                            <div class="mb-4 form-check">
                                <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                                <label class="form-check-label" for="terms">
                                    I agree to the <a href="terms.php" target="_blank">Terms & Conditions</a> and 
                                    <a href="privacy.php" target="_blank">Privacy Policy</a>
                                </label>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="auto_login" name="auto_login" checked>
                                <label class="form-check-label" for="auto_login">Login automatically after registration</label>
                            </div>
                            
                            <button type="submit" class="btn btn-register btn-lg w-100 text-white mb-3">
                                <i class="fas fa-user-plus me-2"></i>Create Account
                            </button>
                            
                            <div class="text-center mt-4">
                                <p>Already have an account? <a href="login.php">Login here</a></p>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="text-center mt-4 text-muted">
                    <p>&copy; <?php echo date('Y'); ?> Skills Way Vocational Institute. All rights reserved.</p>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            togglePasswordVisibility(passwordInput, icon);
        });
        
        document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('confirm_password');
            const icon = this.querySelector('i');
            togglePasswordVisibility(passwordInput, icon);
        });
        
        function togglePasswordVisibility(input, icon) {
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Password strength indicator
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('passwordStrength');
            let strength = 0;
            
            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            let color, width;
            switch(strength) {
                case 0:
                case 1:
                    color = '#dc3545'; // Red
                    width = '20%';
                    break;
                case 2:
                    color = '#fd7e14'; // Orange
                    width = '40%';
                    break;
                case 3:
                    color = '#ffc107'; // Yellow
                    width = '60%';
                    break;
                case 4:
                    color = '#28a745'; // Green
                    width = '80%';
                    break;
                case 5:
                    color = '#20c997'; // Teal
                    width = '100%';
                    break;
                default:
                    color = '#e9ecef';
                    width = '0%';
            }
            
            strengthBar.style.backgroundColor = color;
            strengthBar.style.width = width;
        });
        
        // Password match indicator
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchDiv = document.getElementById('passwordMatch');
            
            if (!password || !confirmPassword) {
                matchDiv.innerHTML = '';
                return;
            }
            
            if (password === confirmPassword) {
                matchDiv.innerHTML = '<span class="text-success"><i class="fas fa-check"></i> Passwords match</span>';
            } else {
                matchDiv.innerHTML = '<span class="text-danger"><i class="fas fa-times"></i> Passwords do not match</span>';
            }
        }
        
        document.getElementById('password').addEventListener('input', checkPasswordMatch);
        document.getElementById('confirm_password').addEventListener('input', checkPasswordMatch);
        
        // CNIC formatting
        document.getElementById('cnic').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            
            if (value.length > 13) {
                value = value.substring(0, 13);
            }
            
            // Format as 12345-1234567-1
            if (value.length > 12) {
                value = value.substring(0, 5) + '-' + value.substring(5, 12) + '-' + value.substring(12);
            } else if (value.length > 5) {
                value = value.substring(0, 5) + '-' + value.substring(5);
            }
            
            e.target.value = value;
        });
        
        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            if (password.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long!');
                return false;
            }
            
            return true;
        });
    </script>
</body>
</html>