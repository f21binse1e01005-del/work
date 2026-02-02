<?php
// Example Admin Page with Security Middleware
require_once '../config/security.php';
require_once '../config/middleware.php';

// Initialize security
Security::init();

// Require admin access
Middleware::admin_access();

$pageTitle = 'Admin Dashboard';
require_once 'includes/header.php';
?>

<div class="pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="fas fa-shield-alt me-2"></i>Secure Admin Dashboard</h1>
</div>

<div class="alert alert-success">
    <i class="fas fa-check-circle me-2"></i>
    <strong>Security Active:</strong> This page is protected by admin-level access control.
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Security Status</h5>
                <p class="text-success"><i class="fas fa-lock"></i> All security measures active</p>
                <ul class="list-unstyled">
                    <li>✓ Role-based access control</li>
                    <li>✓ Session timeout protection</li>
                    <li>✓ CSRF token validation</li>
                    <li>✓ Security headers set</li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5>Admin Functions</h5>
            </div>
            <div class="card-body">
                <p>This is a secure admin area. All actions are logged and monitored.</p>
                <!-- Admin content here -->
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>