<?php
/**
 * Simple Admin Login for Testing
 * File: admin/login-test.php
 */

session_start();

// Simple hardcoded login for testing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Test credentials
    if ($username === 'admin.skills' && $password === 'Admin123!') {
        $_SESSION['user_id'] = 1;
        $_SESSION['user_type'] = 'admin';
        $_SESSION['full_name'] = 'Institute Admin';
        $_SESSION['username'] = 'admin.skills';
        $_SESSION['email'] = 'admin@skillsway.edu.pk';
        
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid credentials';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Skills Way</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card mt-5">
                    <div class="card-header text-center">
                        <h4><i class="fas fa-graduation-cap me-2"></i>Admin Login</h4>
                        <p class="text-muted">Skills Way Vocational Institute</p>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" class="form-control" value="admin.skills" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" value="Admin123!" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-sign-in-alt me-1"></i>Login
                            </button>
                        </form>
                        
                        <div class="mt-3 text-center">
                            <small class="text-muted">
                                Test Credentials:<br>
                                Username: admin.skills<br>
                                Password: Admin123!
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>