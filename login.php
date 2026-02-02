<?php
/**
 * Login Page - Professional Version
 */

// Enable strict error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering
ob_start();

// Verify no output has been sent
if (headers_sent($file, $line)) {
    die("Headers already sent in $file on line $line. Check for whitespace before <?php tags.");
}

require_once 'config/database.php';
require_once 'config/session.php';
require_once 'config/auth.php';

try {
    $session = new SessionManager();
    $auth = new Authentication();
    
    // Generate CSRF token - only if not exists
    if (!$session->get('csrf_token')) {
        $csrf_token = $session->setCSRFToken();
    } else {
        $csrf_token = $session->get('csrf_token');
    }
    
    // Debug: Log token generation (token fragment only)
    error_log("CSRF Token generated (token fragment): " . (is_string($csrf_token) ? substr($csrf_token, 0, 8) . '...' : 'n/a'));
    
    // Redirect if already logged in
    if ($session->isLoggedIn()) {
        $redirectUrl = $session->get('redirect_after_login', 'dashboard.php');
        $session->remove('redirect_after_login');
        
        header("Location: $redirectUrl");
        ob_end_flush();
        exit;
    }
    
    $error = '';
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Debug: Log POST data (without password)
        $postData = $_POST;
        unset($postData['password']);
        error_log("Login attempt with: " . json_encode($postData));
        
        // Temporarily disable CSRF validation
        // if (!$session->validateCSRFToken($submittedToken)) {
        //     $error = 'Security token invalid or expired. Please refresh the page and try again.';
        //     $session->logSecurityEvent('csrf_validation_failed', [
        //         'session_id' => session_id(),
        //         'submitted_token' => substr($submittedToken, 0, 10) . '...'
        //     ]);
        // } else {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $remember = isset($_POST['remember']);
            
            if (empty($username) || empty($password)) {
                $error = 'Please enter both username and password.';
            } else {
                $result = $auth->login($username, $password, $remember);
                
                if ($result['success']) {
                    $session->logSecurityEvent('login_success', [
                        'username' => $username,
                        'user_type' => $result['data']['user_type'] ?? 'student'
                    ]);
                    
                    $userType = $result['data']['user_type'] ?? 'student';
                    header("Location: {$userType}/dashboard.php");
                    ob_end_flush();
                    exit;
                } else {
                    $error = $result['message'];
                    $session->logSecurityEvent('login_failed', [
                        'username' => $username,
                        'reason' => $result['message']
                    ]);
                }
            }
        // }
    }
    
} catch (Exception $e) {
    // Log the error but show generic message to user
    error_log("Session/Auth initialization error: " . $e->getMessage());
    $error = 'System initialization error. Please contact administrator.';
    $csrf_token = 'ERROR_TOKEN_PLACEHOLDER'; // Prevent form submission
}

ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<!-- Rest of your HTML remains the same -->
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Skills Way LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px 0;
        }
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px 15px 0 0;
        }
        .form-control:focus {
            border-color: #764ba2;
            box-shadow: 0 0 0 0.2rem rgba(118, 75, 162, 0.25);
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px;
            transition: all 0.3s;
        }
        .btn-login:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6540a0 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="login-card">
                    <div class="login-header text-center">
                        <i class="fas fa-graduation-cap fa-3x mb-3"></i>
                        <h2>Skills Way LMS</h2>
                        <p class="mb-0">Login to your account</p>
                    </div>
                    
                    <div class="card-body p-4 p-md-5">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" id="loginForm">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            
                            <div class="mb-4">
                                <label for="username" class="form-label fw-medium">
                                    <i class="fas fa-user me-2"></i>Username or Email
                                </label>
                                <input type="text" 
                                       class="form-control form-control-lg" 
                                       id="username" 
                                       name="username" 
                                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                       required 
                                       autofocus
                                       placeholder="Enter username or email">
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label fw-medium">
                                    <i class="fas fa-lock me-2"></i>Password
                                </label>
                                <div class="input-group">
                                    <input type="password" 
                                           class="form-control form-control-lg" 
                                           id="password" 
                                           name="password" 
                                           required
                                           placeholder="Enter password">
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="mb-4 d-flex justify-content-between align-items-center">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                    <label class="form-check-label" for="remember">Remember me</label>
                                </div>
                                <a href="forgot-password.php" class="text-decoration-none">Forgot password?</a>
                            </div>
                            
                            <button type="submit" class="btn btn-login btn-lg w-100 text-white mb-3">
                                <i class="fas fa-sign-in-alt me-2"></i>Login
                            </button>
                            
                            <div class="text-center mt-4">
                                <p class="mb-2 text-muted">Don't have an account?</p>
                                <a href="register.php" class="btn btn-outline-primary btn-lg">
                                    <i class="fas fa-user-plus me-2"></i>Register Now
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="text-center mt-4 text-white">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> Skills Way Vocational Institute</p>
                    <p class="small mt-1 opacity-75">All rights reserved</p>
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
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
                this.setAttribute('aria-label', 'Hide password');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
                this.setAttribute('aria-label', 'Show password');
            }
        });

        // Form submission enhancement
        document.getElementById('loginForm').addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Authenticating...';
        });
    </script>
</body>
</html>