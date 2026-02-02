<?php
/**
 * Application Submission Success Page
 */

require_once 'config/database.php';
require_once 'config/session.php';

$session = new SessionManager();
$isLoggedIn = $session->isLoggedIn();
$userType = $session->get('user_type');
$fullName = $session->get('full_name');

// Get application ID from URL
$applicationId = $_GET['id'] ?? 0;

if (!$applicationId) {
    header("Location: enrollment-form.php");
    exit;
}

// Get institute settings
try {
    $db = (new Database())->getConnection();
    $stmt = $db->query("SELECT setting_key, setting_value FROM institute_settings");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    $settings = [
        'institute_name' => 'Skills Way Vocational Institute',
        'contact_phone' => '0307-0237356, 0331-3307365',
        'contact_email' => 'askillswaykpr@gmail.com'
    ];
}

// Get application details
try {
    $query = "
        SELECT 
            ea.*,
            u.full_name, u.email, u.phone, u.cnic,
            c.course_name, c.course_code, c.fee_amount, c.is_free,
            b.batch_name, b.start_date, b.end_date,
            ad.*
        FROM enrollment_applications ea
        JOIN users u ON ea.user_id = u.user_id
        JOIN courses c ON ea.course_id = c.course_id
        JOIN batches b ON ea.batch_id = b.batch_id
        LEFT JOIN application_details ad ON ea.application_id = ad.application_id
        WHERE ea.application_id = ?
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$applicationId]);
    $application = $stmt->fetch();
    
    if (!$application) {
        header("Location: enrollment-form.php");
        exit;
    }
} catch (Exception $e) {
    // If table doesn't exist yet, show generic success
    $application = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Submitted - <?php echo htmlspecialchars($settings['institute_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #28a745;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        
        .success-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            margin-top: 50px;
            overflow: hidden;
        }
        
        .success-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        
        .success-icon {
            font-size: 5rem;
            margin-bottom: 20px;
            animation: bounce 1s infinite alternate;
        }
        
        @keyframes bounce {
            from { transform: translateY(0); }
            to { transform: translateY(-10px); }
        }
        
        .application-summary {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .summary-item:last-child {
            border-bottom: none;
        }
        
        .next-steps {
            background: #e8f4fd;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .step {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .step-number {
            width: 30px;
            height: 30px;
            background: var(--secondary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .btn-print {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            border: none;
            padding: 10px 25px;
        }
        
        .btn-print:hover {
            background: linear-gradient(135deg, #2980b9 0%, #1c6ea4 100%);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-graduation-cap me-2"></i>
                <?php echo htmlspecialchars($settings['institute_name']); ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="about.php">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="courses.php">Courses</a></li>
                    <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
                    <?php if ($isLoggedIn): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($fullName); ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="<?php echo $userType; ?>/dashboard.php">Dashboard</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item"><a class="btn btn-outline-primary ms-2" href="login.php">Login</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Success Content -->
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="success-card">
                    <!-- Success Header -->
                    <div class="success-header">
                        <div class="success-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h1 class="display-5 fw-bold mb-3">Application Submitted Successfully!</h1>
                        <p class="lead mb-0">Your enrollment application has been received.</p>
                    </div>
                    
                    <!-- Application Details -->
                    <div class="card-body p-5">
                        <?php if ($application): ?>
                            <div class="text-center mb-4">
                                <h3 class="mb-3">Application Details</h3>
                                <p class="text-muted">Application ID: <strong>SW<?php echo str_pad($application['application_id'], 6, '0', STR_PAD_LEFT); ?></strong></p>
                            </div>
                            
                            <div class="application-summary">
                                <div class="summary-item">
                                    <span>Applicant Name:</span>
                                    <strong><?php echo htmlspecialchars($application['full_name']); ?></strong>
                                </div>
                                <div class="summary-item">
                                    <span>CNIC:</span>
                                    <strong><?php echo htmlspecialchars($application['cnic']); ?></strong>
                                </div>
                                <div class="summary-item">
                                    <span>Course:</span>
                                    <strong><?php echo htmlspecialchars($application['course_name']); ?></strong>
                                </div>
                                <div class="summary-item">
                                    <span>Batch:</span>
                                    <strong><?php echo htmlspecialchars($application['batch_name']); ?></strong>
                                </div>
                                <div class="summary-item">
                                    <span>Application Date:</span>
                                    <strong><?php echo date('F d, Y', strtotime($application['application_date'])); ?></strong>
                                </div>
                                <div class="summary-item">
                                    <span>Application Status:</span>
                                    <span class="badge bg-warning">Under Review</span>
                                </div>
                                <div class="summary-item">
                                    <span>Total Fee:</span>
                                    <strong>
                                        <?php if ($application['is_free']): ?>
                                            FREE
                                        <?php else: ?>
                                            Rs. <?php echo number_format($application['fee_amount'], 2); ?>
                                        <?php endif; ?>
                                    </strong>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- What Happens Next -->
                        <div class="next-steps">
                            <h4 class="mb-4"><i class="fas fa-forward me-2"></i>What Happens Next?</h4>
                            
                            <div class="step">
                                <div class="step-number">1</div>
                                <div class="step-content">
                                    <h6>Application Review</h6>
                                    <p class="mb-0">Our admission team will review your application within 2-3 working days.</p>
                                </div>
                            </div>
                            
                            <div class="step">
                                <div class="step-number">2</div>
                                <div class="step-content">
                                    <h6>Document Verification</h6>
                                    <p class="mb-0">You may be contacted for document verification (CNIC, educational certificates).</p>
                                </div>
                            </div>
                            
                            <div class="step">
                                <div class="step-number">3</div>
                                <div class="step-content">
                                    <h6>Admission Decision</h6>
                                    <p class="mb-0">You will receive an email/SMS with the admission decision.</p>
                                </div>
                            </div>
                            
                            <div class="step">
                                <div class="step-number">4</div>
                                <div class="step-content">
                                    <h6>Fee Payment</h6>
                                    <p class="mb-0">If approved, you'll receive payment instructions to complete enrollment.</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Important Notes -->
                        <div class="alert alert-info">
                            <h5><i class="fas fa-info-circle me-2"></i>Important Notes</h5>
                            <ul class="mb-0">
                                <li>Keep your Application ID for future reference.</li>
                                <li>Check your email regularly for updates.</li>
                                <li>You can track your application status by contacting our admission office.</li>
                                <li>Bring original documents when visiting the institute.</li>
                            </ul>
                        </div>
                        
                        <!-- Contact Information -->
                        <div class="alert alert-light border">
                            <h5><i class="fas fa-headset me-2"></i>Need Help?</h5>
                            <p class="mb-2">Contact our admission office for any queries:</p>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-1"><i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($settings['contact_phone']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($settings['contact_email']); ?></p>
                                </div>
                            </div>
                            <p class="mt-2 mb-0"><i class="fas fa-clock me-2"></i>Monday–Saturday, 8:00 AM – 9:00 PM</p>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="text-center mt-4">
                            <div class="d-flex flex-column flex-md-row justify-content-center gap-3">
                                <a href="index.php" class="btn btn-primary btn-lg">
                                    <i class="fas fa-home me-2"></i>Return to Home
                                </a>
                                <a href="courses.php" class="btn btn-outline-primary btn-lg">
                                    <i class="fas fa-book me-2"></i>Browse More Courses
                                </a>
                                <button onclick="window.print()" class="btn btn-print btn-lg">
                                    <i class="fas fa-print me-2"></i>Print This Page
                                </button>
                            </div>
                            
                            <div class="mt-4">
                                <a href="contact.php" class="btn btn-link">
                                    <i class="fas fa-question-circle me-2"></i>Contact Support
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Additional Information -->
                <div class="mt-4 text-center text-muted">
                    <p class="mb-2">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        This is not an admission confirmation. Final admission is subject to verification and approval.
                    </p>
                    <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($settings['institute_name']); ?>. All rights reserved.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Print Styles -->
    <style media="print">
        .navbar, .btn, .next-steps, .alert-info, .alert-light {
            display: none !important;
        }
        
        .success-card {
            box-shadow: none !important;
            margin: 0 !important;
        }
        
        .success-header {
            padding: 20px !important;
        }
        
        body {
            background: white !important;
        }
        
        .container {
            max-width: 100% !important;
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-scroll to top
        window.onload = function() {
            window.scrollTo(0, 0);
        };
        
        // Print function
        function printApplication() {
            window.print();
        }
        
        // Generate PDF (optional)
        function generatePDF() {
            alert('PDF generation feature will be available soon.');
        }
    </script>
</body>
</html>