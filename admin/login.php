<?php

/**
 * Skills Way Vocational Institute - Admin/User Login
 * File: login.php
 * Description: Secure authentication system with rate limiting
 */

session_start();
require_once '../config/database.php';

// Redirect if already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
    header('Location: ' . ($_SESSION['user_type'] === 'admin' ? 'admin/dashboard.php' : 'index.php'));
    exit();
}

$error = '';
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    // Validate inputs
    if (empty($username) || empty($password)) {
        $error = 'Please enter username and password';
    } else {
        try {
            $db = (new Database())->getConnection();

            if (!$db) {
                throw new Exception('Database connection failed');
            }

            // Check login attempts in last 15 minutes
            $ip = $_SERVER['REMOTE_ADDR'];
            $timeThreshold = date('Y-m-d H:i:s', strtotime('-15 minutes'));

            $attemptQuery = "SELECT COUNT(*) as attempts FROM login_logs 
                            WHERE ip_address = ? AND login_time > ? 
                            AND login_status IN ('failed_password', 'failed_inactive')";
            $stmt = $db->prepare($attemptQuery);
            $stmt->execute([$ip, $timeThreshold]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row['attempts'] >= 5) {
                $error = 'Too many failed attempts. Please try again in 15 minutes.';

                // Log blocked attempt
                $logStmt = $db->prepare("INSERT INTO login_logs (username, ip_address, login_status, failure_reason) 
                                         VALUES (?, ?, 'failed_locked', 'Rate limit exceeded')");
                $logStmt->execute([$username, $ip]);
            } else {
                // Get user with active status
                $userQuery = "SELECT user_id, username, email, full_name, user_type, account_status, password_hash 
                             FROM users WHERE (username = ? OR email = ?) LIMIT 1";
                $stmt = $db->prepare($userQuery);
                $stmt->execute([$username, $username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {

                    // Check account status
                    if ($user['account_status'] !== 'active') {
                        $error = 'Account is not active. Please contact administrator.';
                        $logStatus = 'failed_inactive';
                        $logReason = 'Account inactive: ' . $user['account_status'];
                    } else {
                        // Verify password (with salt)
                        // Verify password using Salted SHA256 (matching auth.php)
                        $hashedInput = hash('sha256', $password . 'skills_way_salt');
                        $hashedInputUnsalted = hash('sha256', $password);

                        if (hash_equals($user['password_hash'], $hashedInput) || hash_equals($user['password_hash'], $hashedInputUnsalted)) {
                            // Successful login
                            $_SESSION['user_id'] = $user['user_id'];
                            $_SESSION['username'] = $user['username'];
                            $_SESSION['full_name'] = $user['full_name'];
                            $_SESSION['user_type'] = $user['user_type'];
                            $_SESSION['email'] = $user['email'];
                            $_SESSION['login_time'] = time();

                            // Update last login
                            $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                            $updateStmt->execute([$user['user_id']]);

                            // Log successful login
                            $logStmt = $db->prepare("INSERT INTO login_logs (user_id, username, ip_address, login_status) 
                                                     VALUES (?, ?, ?, 'success')");
                            $logStmt->execute([$user['user_id'], $user['username'], $ip]);

                            // Log activity
                            $activityStmt = $db->prepare("INSERT INTO user_activity_logs (user_id, activity_type, ip_address) 
                                                          VALUES (?, 'user_login', ?)");
                            $activityStmt->execute([$user['user_id'], $ip]);

                            // Set remember me cookie (30 days)
                            if ($remember) {
                                $token = bin2hex(random_bytes(32));
                                $expiry = time() + (30 * 24 * 60 * 60);
                                setcookie('remember_token', $token, $expiry, '/', '', true, true);

                                // Note: remember_token column not in current schema, skip for now
                                // Future: store token in remember_me_tokens table
                            }

                            // Redirect based on user type
                            $redirect = $user['user_type'] === 'admin' ? 'admin/dashboard.php' : 'index.php';
                            header("Location: $redirect");
                            exit();
                        } else {
                            $error = 'Invalid username or password';
                            $logStatus = 'failed_password';
                            $logReason = 'Incorrect password';
                        }
                    }
                } else {
                    $error = 'Invalid username or password';
                    $logStatus = 'failed_password';
                    $logReason = 'User not found';
                }

                // Log failed attempt
                if (!empty($logStatus)) {
                    $logStmt = $db->prepare("INSERT INTO login_logs (username, ip_address, login_status, failure_reason) 
                                             VALUES (?, ?, ?, ?)");
                    $logStmt->execute([$username, $ip, $logStatus, $logReason]);
                }
            }
        } catch (Exception $e) {
            $error = 'System error. Please try again later.';
            error_log('Login error: ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Skills Way Vocational Institute</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .login-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        .institute-logo {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 10px 30px;
            font-weight: 600;
        }

        .alert {
            border-radius: 10px;
            border: none;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-5">
                    <div class="card login-card">
                        <div class="card-body p-5">
                            <!-- Logo -->
                            <div class="text-center mb-4">
                                <div class="institute-logo">
                                    <i class="fas fa-graduation-cap fa-3x text-primary"></i>
                                </div>
                                <h2 class="mb-2">Skills Way Vocational</h2>
                                <p class="text-muted">Institute Management System</p>
                            </div>

                            <!-- Error/Success Messages -->
                            <?php if ($error): ?>
                                <div class="alert alert-danger alert-dismissible fade show">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    <?php echo htmlspecialchars($error); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <?php if ($success): ?>
                                <div class="alert alert-success alert-dismissible fade show">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <?php echo htmlspecialchars($success); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <!-- Login Form -->
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="username" class="form-label">
                                        <i class="fas fa-user me-2"></i>Username or Email
                                    </label>
                                    <input type="text" class="form-control form-control-lg"
                                        id="username" name="username"
                                        value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                        required autofocus>
                                </div>

                                <div class="mb-3">
                                    <label for="password" class="form-label">
                                        <i class="fas fa-lock me-2"></i>Password
                                    </label>
                                    <div class="input-group">
                                        <input type="password" class="form-control form-control-lg"
                                            id="password" name="password" required>
                                        <button type="button" class="btn btn-outline-secondary"
                                            onclick="togglePassword()">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input"
                                        id="remember" name="remember">
                                    <label class="form-check-label" for="remember">
                                        Remember me for 30 days
                                    </label>
                                </div>

                                <div class="d-grid gap-2 mb-4">
                                    <button type="submit" class="btn btn-login btn-lg text-white">
                                        <i class="fas fa-sign-in-alt me-2"></i>Login
                                    </button>
                                </div>

                                <div class="text-center">
                                    <a href="forgot-password.php" class="text-decoration-none">
                                        <i class="fas fa-key me-1"></i>Forgot Password?
                                    </a>
                                    <div class="mt-3">
                                        <p class="text-muted mb-1">New to the institute?</p>
                                        <a href="enrollment-form.php" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-user-plus me-1"></i>Apply for Enrollment
                                        </a>
                                    </div>
                                </div>
                            </form>

                            <!-- Quick Links -->
                            <div class="mt-4 pt-4 border-top text-center">
                                <small class="text-muted">
                                    <i class="fas fa-shield-alt me-1"></i>Secure login •
                                    <i class="fas fa-clock me-1"></i>24/7 Access •
                                    <i class="fas fa-user-shield me-1"></i>Role-based access
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Admin Demo Credentials -->
                    <div class="card mt-3 border-primary">
                        <div class="card-body p-3">
                            <small class="text-muted d-flex align-items-center">
                                <i class="fas fa-info-circle me-2 text-primary"></i>
                                <span>
                                    <strong>Admin Demo:</strong> admin.skills / Admin123!
                                </span>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = passwordInput.nextElementSibling.querySelector('i');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>

</html>