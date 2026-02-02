<?php
// Example Student Page with Security Middleware
require_once '../config/security.php';
require_once '../config/middleware.php';

// Initialize security
Security::init();

// Require student access
Middleware::student_access();

$pageTitle = 'Student Portal';
require_once 'includes/header.php';
?>

<div class="pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="fas fa-user-graduate me-2"></i>Secure Student Portal</h1>
</div>

<div class="alert alert-info">
    <i class="fas fa-info-circle me-2"></i>
    <strong>Welcome:</strong> You are accessing a secure student area.
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Security Features</h5>
                <ul class="list-unstyled">
                    <li>✓ Secure session management</li>
                    <li>✓ Input validation</li>
                    <li>✓ File upload protection</li>
                    <li>✓ Activity logging</li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5>Student Resources</h5>
            </div>
            <div class="card-body">
                <p>Access your courses, assignments, and materials securely.</p>
                <!-- Student content here -->
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>