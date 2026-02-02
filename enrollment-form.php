<?php
/**
 * Enrollment Application Form
 */

// Start output buffering to prevent header issues
ob_start();

require_once 'config/database.php';
require_once 'config/session.php';
require_once 'config/middleware.php';

$session = new SessionManager();
$middleware = new Middleware();
$isLoggedIn = $session->isLoggedIn();
$userType = $session->get('user_type');
$userId = $session->get('user_id');
$fullName = $session->get('full_name');

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
        'institute_name' => 'Skills Way Vocational Institute'
    ];
}

// CSRF token
$csrf_token = $session->setCSRFToken();

// Get course ID from query parameter if available
$courseId = $_GET['course'] ?? 0;
$batchId = $_GET['batch'] ?? 0;

// Fetch active courses for dropdown
$coursesStmt = $db->query("
    SELECT c.course_id, c.course_code, c.course_name, c.category_id, 
           cat.category_name, c.duration_months, c.fee_amount, c.is_free
    FROM courses c
    JOIN course_categories cat ON c.category_id = cat.category_id
    WHERE c.is_active = TRUE
    ORDER BY cat.category_name, c.course_name
");
$courses = $coursesStmt->fetchAll();

// Fetch batches for selected course
$batches = [];
if ($courseId) {
    $batchesStmt = $db->prepare("
        SELECT b.batch_id, b.batch_code, b.batch_name, b.start_date, b.end_date,
               b.schedule_details, b.classroom, b.status, u.full_name as teacher_name
        FROM batches b
        LEFT JOIN users u ON b.teacher_id = u.user_id
        WHERE b.course_id = ?
        ORDER BY b.start_date
    ");
    $batchesStmt->execute([$courseId]);
    $batches = $batchesStmt->fetchAll();
}

// Get user data if logged in
$userData = [];
if ($isLoggedIn) {
    $userStmt = $db->prepare("
        SELECT u.*, up.* 
        FROM users u 
        LEFT JOIN user_profiles up ON u.user_id = up.user_id 
        WHERE u.user_id = ?
    ");
    $userStmt->execute([$userId]);
    $userData = $userStmt->fetch();
}

// Form processing
$success = '';
$error = '';
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug CSRF token
    $submittedToken = $_POST['csrf_token'] ?? '';
    $sessionToken = $session->get('csrf_token');
    
    // Validate CSRF token
    if (!$session->validateCSRFToken($submittedToken)) {
        $error = 'Security token invalid. Please refresh the page and try again.';
        // Debug info (remove in production)
        error_log("CSRF Token Debug - Submitted: $submittedToken, Session: $sessionToken");
    } else {
        // Get form data
        $formData = [
            // Personal Information
            'full_name' => trim($_POST['full_name'] ?? ''),
            'father_name' => trim($_POST['father_name'] ?? ''),
            'cnic' => trim($_POST['cnic'] ?? ''),
            'date_of_birth' => $_POST['date_of_birth'] ?? '',
            'gender' => $_POST['gender'] ?? '',
            'marital_status' => $_POST['marital_status'] ?? '',
            
            // Contact Information
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'emergency_phone' => trim($_POST['emergency_phone'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'city' => trim($_POST['city'] ?? ''),
            'district' => trim($_POST['district'] ?? ''),
            
            // Educational Information
            'education_level' => $_POST['education_level'] ?? '',
            'institution_last' => trim($_POST['institution_last'] ?? ''),
            'year_passed' => $_POST['year_passed'] ?? '',
            
            // Course Information
            'course_id' => $_POST['course_id'] ?? 0,
            'batch_id' => $_POST['batch_id'] ?? 0,
            'preferred_timing' => $_POST['preferred_timing'] ?? '',
            
            // Additional Information
            'previous_experience' => trim($_POST['previous_experience'] ?? ''),
            'computer_knowledge' => $_POST['computer_knowledge'] ?? '',
            'purpose' => trim($_POST['purpose'] ?? ''),
            
            // Payment Information
            'payment_method' => $_POST['payment_method'] ?? '',
            'installment_plan' => $_POST['installment_plan'] ?? '',
            
            // Terms
            'terms_accepted' => isset($_POST['terms_accepted']),
            'data_consent' => isset($_POST['data_consent'])
        ];

        // Validation
        $requiredFields = [
            'full_name', 'father_name', 'cnic', 'date_of_birth', 'gender',
            'email', 'phone', 'address', 'city', 'district',
            'education_level', 'course_id', 'batch_id'
        ];
        
        foreach ($requiredFields as $field) {
            if (empty($formData[$field])) {
                $error = "Please fill in all required fields.";
                break;
            }
        }

        // Validate CNIC format
        if (!$error && !preg_match('/^\d{5}-\d{7}-\d{1}$/', $formData['cnic'])) {
            $error = 'Please enter a valid CNIC in format: 12345-1234567-1';
        }

        // Validate email
        if (!$error && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        }

        // Check age (minimum 16 years)
        if (!$error && $formData['date_of_birth']) {
            $birthDate = new DateTime($formData['date_of_birth']);
            $today = new DateTime();
            $age = $today->diff($birthDate)->y;
            if ($age < 16) {
                $error = 'You must be at least 16 years old to enroll.';
            }
        }

        // Check if user already applied for this batch
        if (!$error) {
            $checkStmt = $db->prepare("
                SELECT ea.application_id 
                FROM enrollment_applications ea
                JOIN users u ON ea.user_id = u.user_id
                WHERE u.cnic = ? AND ea.batch_id = ? 
                AND ea.application_status NOT IN ('rejected', 'cancelled')
            ");
            $checkStmt->execute([$formData['cnic'], $formData['batch_id']]);
            
            if ($checkStmt->fetch()) {
                $error = 'You have already applied for this batch.';
            }
        }

        // Check batch capacity (using course max_students)
        if (!$error) {
            $batchStmt = $db->prepare("
                SELECT c.max_students, 
                       COUNT(ea.application_id) as current_applications
                FROM batches b
                JOIN courses c ON b.course_id = c.course_id
                LEFT JOIN enrollment_applications ea ON b.batch_id = ea.batch_id 
                    AND ea.application_status IN ('submitted', 'under_review', 'approved')
                WHERE b.batch_id = ?
                GROUP BY b.batch_id, c.max_students
            ");
            $batchStmt->execute([$formData['batch_id']]);
            $batchData = $batchStmt->fetch();
            
            if ($batchData && $batchData['max_students'] > 0 && 
                $batchData['current_applications'] >= $batchData['max_students']) {
                $error = 'This batch has reached maximum capacity. Please select another batch.';
            }
        }

        if (!$error) {
            try {
                // Begin transaction
                $db->beginTransaction();

                // Check if user exists in database
                $userCheckStmt = $db->prepare("SELECT user_id FROM users WHERE cnic = ?");
                $userCheckStmt->execute([$formData['cnic']]);
                $existingUser = $userCheckStmt->fetch();

                if ($existingUser) {
                    $applicantUserId = $existingUser['user_id'];
                } else {
                    // Create new user account for applicant
                    $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $formData['cnic']));
                    $tempPassword = bin2hex(random_bytes(8));
                    
                    $userSql = "INSERT INTO users (username, email, phone, cnic, full_name, password_hash, user_type, account_status) 
                               VALUES (?, ?, ?, ?, ?, SHA2(CONCAT(?, 'skills_way_salt'), 256), 'student', 'pending_verification')";
                    
                    $userStmt = $db->prepare($userSql);
                    $userStmt->execute([
                        $username,
                        $formData['email'],
                        $formData['phone'],
                        $formData['cnic'],
                        $formData['full_name'],
                        $tempPassword
                    ]);
                    
                    $applicantUserId = $db->lastInsertId();
                    
                    // Create user profile
                    $profileSql = "INSERT INTO user_profiles (user_id, date_of_birth, gender, address, education_level, previous_experience) 
                                  VALUES (?, ?, ?, ?, ?, ?)";
                    
                    $profileStmt = $db->prepare($profileSql);
                    $profileStmt->execute([
                        $applicantUserId,
                        $formData['date_of_birth'],
                        $formData['gender'],
                        $formData['address'] . ', ' . $formData['city'] . ', ' . $formData['district'],
                        $formData['education_level'],
                        $formData['previous_experience']
                    ]);
                }

                // Get course fee
                $feeStmt = $db->prepare("SELECT fee_amount, is_free FROM courses WHERE course_id = ?");
                $feeStmt->execute([$formData['course_id']]);
                $feeData = $feeStmt->fetch();
                $feeAmount = $feeData['is_free'] ? 0 : $feeData['fee_amount'];

                // Insert enrollment application
                $applicationSql = "
                    INSERT INTO enrollment_applications (
                        user_id, course_id, batch_id, application_status, 
                        payment_status, payment_amount, application_date
                    ) VALUES (?, ?, ?, 'submitted', 'pending', ?, NOW())
                ";
                
                $applicationStmt = $db->prepare($applicationSql);
                $applicationStmt->execute([
                    $applicantUserId,
                    $formData['course_id'],
                    $formData['batch_id'],
                    $feeAmount
                ]);
                
                $applicationId = $db->lastInsertId();

                // Create application details table if not exists
                $checkDetailsTable = $db->query("SHOW TABLES LIKE 'application_details'")->fetch();
                if (!$checkDetailsTable) {
                    $createDetailsTable = "
                        CREATE TABLE application_details (
                            detail_id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
                            application_id INT UNSIGNED NOT NULL,
                            father_name VARCHAR(100),
                            emergency_phone VARCHAR(15),
                            city VARCHAR(50),
                            district VARCHAR(50),
                            institution_last VARCHAR(200),
                            year_passed YEAR,
                            computer_knowledge ENUM('none', 'basic', 'intermediate', 'advanced'),
                            purpose TEXT,
                            preferred_timing ENUM('morning', 'afternoon', 'evening', 'weekend'),
                            payment_method ENUM('cash', 'bank_transfer', 'easypaisa', 'jazzcash'),
                            installment_plan ENUM('full', 'two_installments', 'three_installments'),
                            additional_notes TEXT,
                            FOREIGN KEY (application_id) REFERENCES enrollment_applications(application_id) ON DELETE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                    ";
                    $db->exec($createDetailsTable);
                }

                // Insert additional application details
                $detailsSql = "
                    INSERT INTO application_details (
                        application_id, father_name, emergency_phone, city, district,
                        institution_last, year_passed, computer_knowledge, purpose,
                        preferred_timing, payment_method, installment_plan
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ";
                
                $detailsStmt = $db->prepare($detailsSql);
                $detailsStmt->execute([
                    $applicationId,
                    $formData['father_name'],
                    $formData['emergency_phone'],
                    $formData['city'],
                    $formData['district'],
                    $formData['institution_last'],
                    $formData['year_passed'],
                    $formData['computer_knowledge'],
                    $formData['purpose'],
                    $formData['preferred_timing'],
                    $formData['payment_method'],
                    $formData['installment_plan']
                ]);

                // Log activity
                $activitySql = "INSERT INTO user_activity_logs (user_id, activity_type, activity_details) 
                               VALUES (?, 'enrollment_application', ?)";
                $activityStmt = $db->prepare($activitySql);
                $activityStmt->execute([
                    $applicantUserId,
                    json_encode([
                        'application_id' => $applicationId,
                        'course_id' => $formData['course_id'],
                        'batch_id' => $formData['batch_id']
                    ])
                ]);

                // Commit transaction
                $db->commit();

                // Redirect to success page
                ob_end_clean(); // Clear any output buffer
                header("Location: application-success.php?id=" . $applicationId);
                exit;

            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = 'Application submission failed. Please try again. Error: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollment Application - <?php echo htmlspecialchars($settings['institute_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
        }
        
        .hero-enrollment {
            background: linear-gradient(rgba(44, 62, 80, 0.9), rgba(44, 62, 80, 0.9)), 
                        url('https://images.unsplash.com/photo-1523050854058-8df90110c9f1?ixlib=rb-4.0.3&auto=format&fit=crop&w=1350&q=80');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 80px 0;
        }
        
        .application-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-top: -50px;
            position: relative;
            z-index: 1;
        }
        
        .form-section {
            padding: 30px;
            border-bottom: 1px solid #eee;
        }
        
        .form-section:last-child {
            border-bottom: none;
        }
        
        .section-title {
            color: var(--primary-color);
            border-left: 4px solid var(--secondary-color);
            padding-left: 15px;
            margin-bottom: 25px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }
        
        .required::after {
            content: ' *';
            color: #dc3545;
        }
        
        .progress-container {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .progress-step {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .step-number {
            width: 40px;
            height: 40px;
            background: #dee2e6;
            color: #6c757d;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
        }
        
        .step-number.active {
            background: var(--secondary-color);
            color: white;
        }
        
        .step-content {
            flex: 1;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            border: none;
            padding: 15px 40px;
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .btn-submit:hover {
            background: linear-gradient(135deg, #2980b9 0%, #1c6ea4 100%);
        }
        
        .course-info-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .batch-selector {
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .batch-selector:hover {
            border-color: var(--secondary-color);
            background: #f8f9fa;
        }
        
        .batch-selector.selected {
            border-color: var(--secondary-color);
            background: rgba(52, 152, 219, 0.1);
        }
        
        .form-check-input:checked {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
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
                    <li class="nav-item"><a class="nav-link active" href="enrollment-form.php">Enroll Now</a></li>
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
                        <li class="nav-item"><a class="btn btn-primary ms-2" href="register.php">Register</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-enrollment">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="display-4 fw-bold mb-4">Enrollment Application</h1>
                    <p class="lead mb-4">Join Skills Way Vocational Institute. Fill out the form below to start your journey.</p>
                    <div class="d-flex gap-3">
                        <a href="courses.php" class="btn btn-outline-light btn-lg">Browse Courses</a>
                        <a href="contact.php" class="btn btn-light btn-lg">Need Help?</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Application Progress -->
    <div class="container">
        <div class="progress-container">
            <div class="row">
                <div class="col-md-3">
                    <div class="progress-step">
                        <div class="step-number active">1</div>
                        <div class="step-content">
                            <small>Step 1</small>
                            <h6 class="mb-0">Personal Info</h6>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="progress-step">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <small>Step 2</small>
                            <h6 class="mb-0">Course Selection</h6>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="progress-step">
                        <div class="step-number">3</div>
                        <div class="step-content">
                            <small>Step 3</small>
                            <h6 class="mb-0">Education & Experience</h6>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="progress-step">
                        <div class="step-number">4</div>
                        <div class="step-content">
                            <small>Step 4</small>
                            <h6 class="mb-0">Review & Submit</h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Application Form -->
    <div class="container">
        <div class="application-card">
            <form method="POST" action="" id="enrollmentForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                
                <!-- Error/Success Messages -->
                <?php if ($error): ?>
                    <div class="alert alert-danger m-4" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Section 1: Personal Information -->
                <div class="form-section">
                    <h3 class="section-title">Personal Information</h3>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="full_name" class="form-label required">Full Name</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="full_name" 
                                   name="full_name" 
                                   value="<?php echo htmlspecialchars($formData['full_name'] ?? ($userData['full_name'] ?? '')); ?>"
                                   required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="father_name" class="form-label required">Father's Name</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="father_name" 
                                   name="father_name" 
                                   value="<?php echo htmlspecialchars($formData['father_name'] ?? ($userData['parent_name'] ?? '')); ?>"
                                   required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="cnic" class="form-label required">CNIC Number</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="cnic" 
                                   name="cnic" 
                                   placeholder="12345-1234567-1"
                                   value="<?php echo htmlspecialchars($formData['cnic'] ?? ($userData['cnic'] ?? '')); ?>"
                                   required>
                            <small class="text-muted">Format: 12345-1234567-1</small>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="date_of_birth" class="form-label required">Date of Birth</label>
                            <input type="date" 
                                   class="form-control" 
                                   id="date_of_birth" 
                                   name="date_of_birth" 
                                   value="<?php echo htmlspecialchars($formData['date_of_birth'] ?? ($userData['date_of_birth'] ?? '')); ?>"
                                   required>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="gender" class="form-label required">Gender</label>
                            <select class="form-select" id="gender" name="gender" required>
                                <option value="">Select</option>
                                <option value="male" <?php echo ($formData['gender'] ?? ($userData['gender'] ?? '')) == 'male' ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo ($formData['gender'] ?? ($userData['gender'] ?? '')) == 'female' ? 'selected' : ''; ?>>Female</option>
                                <option value="other" <?php echo ($formData['gender'] ?? ($userData['gender'] ?? '')) == 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="marital_status" class="form-label">Marital Status</label>
                            <select class="form-select" id="marital_status" name="marital_status">
                                <option value="">Select</option>
                                <option value="single" <?php echo ($formData['marital_status'] ?? '') == 'single' ? 'selected' : ''; ?>>Single</option>
                                <option value="married" <?php echo ($formData['marital_status'] ?? '') == 'married' ? 'selected' : ''; ?>>Married</option>
                                <option value="divorced" <?php echo ($formData['marital_status'] ?? '') == 'divorced' ? 'selected' : ''; ?>>Divorced</option>
                                <option value="widowed" <?php echo ($formData['marital_status'] ?? '') == 'widowed' ? 'selected' : ''; ?>>Widowed</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Section 2: Contact Information -->
                <div class="form-section">
                    <h3 class="section-title">Contact Information</h3>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label required">Email Address</label>
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   name="email" 
                                   value="<?php echo htmlspecialchars($formData['email'] ?? ($userData['email'] ?? '')); ?>"
                                   required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label required">Phone Number</label>
                            <input type="tel" 
                                   class="form-control" 
                                   id="phone" 
                                   name="phone" 
                                   placeholder="0300-1234567"
                                   value="<?php echo htmlspecialchars($formData['phone'] ?? ($userData['phone'] ?? '')); ?>"
                                   required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="emergency_phone" class="form-label">Emergency Contact Number</label>
                            <input type="tel" 
                                   class="form-control" 
                                   id="emergency_phone" 
                                   name="emergency_phone" 
                                   placeholder="0300-1234567"
                                   value="<?php echo htmlspecialchars($formData['emergency_phone'] ?? ($userData['emergency_contact'] ?? '')); ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label required">Complete Address</label>
                        <textarea class="form-control" 
                                  id="address" 
                                  name="address" 
                                  rows="2"
                                  required><?php echo htmlspecialchars($formData['address'] ?? ($userData['address'] ?? '')); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="city" class="form-label required">City</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="city" 
                                   name="city" 
                                   value="<?php echo htmlspecialchars($formData['city'] ?? ''); ?>"
                                   required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="district" class="form-label required">District</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="district" 
                                   name="district" 
                                   value="<?php echo htmlspecialchars($formData['district'] ?? ''); ?>"
                                   required>
                        </div>
                    </div>
                </div>
                
                <!-- Section 3: Course Selection -->
                <div class="form-section">
                    <h3 class="section-title">Course Selection</h3>
                    
                    <div class="mb-4">
                        <label for="course_id" class="form-label required">Select Course</label>
                        <select class="form-select" id="course_id" name="course_id" required>
                            <option value="">Select a Course</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['course_id']; ?>" 
                                    <?php echo ($formData['course_id'] ?? $courseId) == $course['course_id'] ? 'selected' : ''; ?>
                                    data-fee="<?php echo $course['fee_amount']; ?>"
                                    data-free="<?php echo $course['is_free']; ?>">
                                    <?php echo htmlspecialchars($course['category_name'] . ' - ' . $course['course_name']); ?>
                                    (<?php echo $course['duration_months']; ?> months, 
                                    <?php echo $course['is_free'] ? 'FREE' : 'Rs. ' . number_format($course['fee_amount'], 2); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Batch Selection -->
                    <div id="batchSelection" class="<?php echo empty($batches) ? 'd-none' : ''; ?>">
                        <label class="form-label required">Select Batch</label>
                        
                        <div class="row" id="batchList">
                            <?php if (empty($batches)): ?>
                                <div class="col-12">
                                    <div class="alert alert-info">
                                        Please select a course first to see available batches.
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($batches as $batch): ?>
                                    <div class="col-md-6">
                                        <div class="batch-selector <?php echo ($formData['batch_id'] ?? $batchId) == $batch['batch_id'] ? 'selected' : ''; ?>"
                                             onclick="selectBatch(<?php echo $batch['batch_id']; ?>)">
                                            <div class="form-check">
                                                <input class="form-check-input" 
                                                       type="radio" 
                                                       name="batch_id" 
                                                       id="batch_<?php echo $batch['batch_id']; ?>" 
                                                       value="<?php echo $batch['batch_id']; ?>"
                                                       <?php echo ($formData['batch_id'] ?? $batchId) == $batch['batch_id'] ? 'checked' : ''; ?>
                                                       required>
                                                <label class="form-check-label w-100" for="batch_<?php echo $batch['batch_id']; ?>">
                                                    <strong><?php echo htmlspecialchars($batch['batch_name']); ?></strong>
                                                    <div class="mt-2">
                                                        <small class="text-muted">
                                                            <i class="fas fa-calendar me-1"></i>
                                                            <?php echo date('M d, Y', strtotime($batch['start_date'])); ?> - 
                                                            <?php echo date('M d, Y', strtotime($batch['end_date'])); ?>
                                                        </small>
                                                    </div>
                                                    <?php if ($batch['teacher_name']): ?>
                                                        <div>
                                                            <small class="text-muted">
                                                                <i class="fas fa-chalkboard-teacher me-1"></i>
                                                                <?php echo htmlspecialchars($batch['teacher_name']); ?>
                                                            </small>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($batch['schedule_details']): ?>
                                                        <div>
                                                            <small class="text-muted">
                                                                <i class="fas fa-clock me-1"></i>
                                                                <?php echo htmlspecialchars($batch['schedule_details']); ?>
                                                            </small>
                                                        </div>
                                                    <?php endif; ?>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Course Fee Information -->
                    <div id="feeInfo" class="course-info-box <?php echo !$courseId ? 'd-none' : ''; ?>">
                        <h5>Course Fee Information</h5>
                        <div id="feeDetails">
                            <!-- Fee details will be loaded via JavaScript -->
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-6 mb-3">
                            <label for="preferred_timing" class="form-label">Preferred Timing</label>
                            <select class="form-select" id="preferred_timing" name="preferred_timing">
                                <option value="">Any</option>
                                <option value="morning" <?php echo ($formData['preferred_timing'] ?? '') == 'morning' ? 'selected' : ''; ?>>Morning (8 AM - 12 PM)</option>
                                <option value="afternoon" <?php echo ($formData['preferred_timing'] ?? '') == 'afternoon' ? 'selected' : ''; ?>>Afternoon (2 PM - 5 PM)</option>
                                <option value="evening" <?php echo ($formData['preferred_timing'] ?? '') == 'evening' ? 'selected' : ''; ?>>Evening (6 PM - 9 PM)</option>
                                <option value="weekend" <?php echo ($formData['preferred_timing'] ?? '') == 'weekend' ? 'selected' : ''; ?>>Weekend Only</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Section 4: Educational Background -->
                <div class="form-section">
                    <h3 class="section-title">Educational Background</h3>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="education_level" class="form-label required">Highest Education Level</label>
                            <select class="form-select" id="education_level" name="education_level" required>
                                <option value="">Select</option>
                                <option value="below_matric" <?php echo ($formData['education_level'] ?? ($userData['education_level'] ?? '')) == 'below_matric' ? 'selected' : ''; ?>>Below Matric</option>
                                <option value="matric" <?php echo ($formData['education_level'] ?? ($userData['education_level'] ?? '')) == 'matric' ? 'selected' : ''; ?>>Matriculation</option>
                                <option value="intermediate" <?php echo ($formData['education_level'] ?? ($userData['education_level'] ?? '')) == 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                                <option value="bachelor" <?php echo ($formData['education_level'] ?? ($userData['education_level'] ?? '')) == 'bachelor' ? 'selected' : ''; ?>>Bachelor's Degree</option>
                                <option value="master" <?php echo ($formData['education_level'] ?? ($userData['education_level'] ?? '')) == 'master' ? 'selected' : ''; ?>>Master's Degree</option>
                                <option value="phd" <?php echo ($formData['education_level'] ?? ($userData['education_level'] ?? '')) == 'phd' ? 'selected' : ''; ?>>PhD</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="institution_last" class="form-label">Last Institution Attended</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="institution_last" 
                                   name="institution_last" 
                                   value="<?php echo htmlspecialchars($formData['institution_last'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="year_passed" class="form-label">Year Passed</label>
                            <select class="form-select" id="year_passed" name="year_passed">
                                <option value="">Select Year</option>
                                <?php for ($year = date('Y'); $year >= 1970; $year--): ?>
                                    <option value="<?php echo $year; ?>" <?php echo ($formData['year_passed'] ?? '') == $year ? 'selected' : ''; ?>>
                                        <?php echo $year; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="computer_knowledge" class="form-label">Computer Knowledge</label>
                            <select class="form-select" id="computer_knowledge" name="computer_knowledge">
                                <option value="">Select Level</option>
                                <option value="none" <?php echo ($formData['computer_knowledge'] ?? '') == 'none' ? 'selected' : ''; ?>>No Experience</option>
                                <option value="basic" <?php echo ($formData['computer_knowledge'] ?? '') == 'basic' ? 'selected' : ''; ?>>Basic (MS Office)</option>
                                <option value="intermediate" <?php echo ($formData['computer_knowledge'] ?? '') == 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                                <option value="advanced" <?php echo ($formData['computer_knowledge'] ?? '') == 'advanced' ? 'selected' : ''; ?>>Advanced</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="previous_experience" class="form-label">Previous Experience (if any)</label>
                        <textarea class="form-control" 
                                  id="previous_experience" 
                                  name="previous_experience" 
                                  rows="3"><?php echo htmlspecialchars($formData['previous_experience'] ?? ($userData['previous_experience'] ?? '')); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="purpose" class="form-label">Purpose of Joining this Course</label>
                        <textarea class="form-control" 
                                  id="purpose" 
                                  name="purpose" 
                                  rows="3"><?php echo htmlspecialchars($formData['purpose'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <!-- Section 5: Payment Information -->
                <div class="form-section">
                    <h3 class="section-title">Payment Information</h3>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="payment_method" class="form-label">Preferred Payment Method</label>
                            <select class="form-select" id="payment_method" name="payment_method">
                                <option value="">Select Method</option>
                                <option value="cash" <?php echo ($formData['payment_method'] ?? '') == 'cash' ? 'selected' : ''; ?>>Cash (at Institute)</option>
                                <option value="bank_transfer" <?php echo ($formData['payment_method'] ?? '') == 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                                <option value="easypaisa" <?php echo ($formData['payment_method'] ?? '') == 'easypaisa' ? 'selected' : ''; ?>>Easypaisa</option>
                                <option value="jazzcash" <?php echo ($formData['payment_method'] ?? '') == 'jazzcash' ? 'selected' : ''; ?>>JazzCash</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="installment_plan" class="form-label">Installment Plan</label>
                            <select class="form-select" id="installment_plan" name="installment_plan">
                                <option value="full" <?php echo ($formData['installment_plan'] ?? '') == 'full' ? 'selected' : ''; ?>>Full Payment</option>
                                <option value="two_installments" <?php echo ($formData['installment_plan'] ?? '') == 'two_installments' ? 'selected' : ''; ?>>Two Installments</option>
                                <option value="three_installments" <?php echo ($formData['installment_plan'] ?? '') == 'three_installments' ? 'selected' : ''; ?>>Three Installments</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> Payment details will be shared after application approval. 
                        You can pay the fee at the institute or through bank transfer.
                    </div>
                </div>
                
                <!-- Section 6: Declaration -->
                <div class="form-section">
                    <h3 class="section-title">Declaration</h3>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="terms_accepted" name="terms_accepted" required>
                            <label class="form-check-label" for="terms_accepted">
                                I hereby declare that the information provided in this application is true and correct to the best of my knowledge. 
                                I agree to abide by the rules and regulations of Skills Way Vocational Institute.
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="data_consent" name="data_consent" required>
                            <label class="form-check-label" for="data_consent">
                                I consent to the collection and processing of my personal data for enrollment and administrative purposes.
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- Submit Section -->
                <div class="form-section text-center">
                    <div class="row">
                        <div class="col-md-8 mx-auto">
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Important:</strong> Please review all information before submission. 
                                Once submitted, you cannot edit the application.
                            </div>
                            
                            <button type="submit" class="btn btn-submit btn-lg w-100 mb-3">
                                <i class="fas fa-paper-plane me-2"></i>Submit Application
                            </button>
                            
                            <p class="text-muted">
                                By submitting, you agree to our 
                                <a href="terms.php" target="_blank">Terms & Conditions</a> and 
                                <a href="privacy.php" target="_blank">Privacy Policy</a>
                            </p>
                            
                            <div class="mt-4">
                                <a href="courses.php" class="btn btn-outline-secondary me-2">Browse Courses</a>
                                <a href="contact.php" class="btn btn-outline-primary">Contact Support</a>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer bg-dark text-white py-5 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <h4 class="mb-3"><?php echo htmlspecialchars($settings['institute_name']); ?></h4>
                    <p>Model Town "B", Near Masjid Al-Farooq/Bank Al-Habib, Khanpur</p>
                    <div class="social-icons">
                        <a href="#" class="text-white me-3"><i class="fab fa-facebook"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="index.php" class="text-light">Home</a></li>
                        <li class="mb-2"><a href="about.php" class="text-light">About Us</a></li>
                        <li class="mb-2"><a href="courses.php" class="text-light">Courses</a></li>
                        <li class="mb-2"><a href="contact.php" class="text-light">Contact</a></li>
                    </ul>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <h5>Admission Help</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="fas fa-phone me-2"></i>0307-0237356, 0331-3307365</li>
                        <li class="mb-2"><i class="fas fa-envelope me-2"></i>admissions@skillsway.edu.pk</li>
                        <li class="mb-2"><i class="fas fa-clock me-2"></i>Mon-Sat: 8:00 AM - 9:00 PM</li>
                    </ul>
                </div>
            </div>
            <hr class="bg-light">
            <div class="text-center pt-3">
                <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($settings['institute_name']); ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // CNIC formatting
        document.getElementById('cnic').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            
            if (value.length > 13) {
                value = value.substring(0, 13);
            }
            
            if (value.length > 5) {
                value = value.substring(0, 5) + '-' + value.substring(5);
            }
            if (value.length > 13) {
                value = value.substring(0, 13) + '-' + value.substring(13);
            }
            
            e.target.value = value;
        });

        // Phone number formatting
        document.getElementById('phone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            
            if (value.length > 11) {
                value = value.substring(0, 11);
            }
            
            if (value.length > 4) {
                value = value.substring(0, 4) + '-' + value.substring(4);
            }
            
            e.target.value = value;
        });

        // Course selection change handler
        document.getElementById('course_id').addEventListener('change', function() {
            const courseId = this.value;
            const selectedOption = this.options[this.selectedIndex];
            const isFree = selectedOption.getAttribute('data-free') === '1';
            const fee = selectedOption.getAttribute('data-fee');
            
            // Update fee information
            const feeInfo = document.getElementById('feeInfo');
            const feeDetails = document.getElementById('feeDetails');
            
            if (courseId) {
                feeInfo.classList.remove('d-none');
                
                if (isFree) {
                    feeDetails.innerHTML = `
                        <div class="alert alert-success">
                            <i class="fas fa-gift me-2"></i>
                            <strong>This course is FREE!</strong> No tuition fee required.
                        </div>
                    `;
                } else {
                    feeDetails.innerHTML = `
                        <table class="table table-sm">
                            <tr>
                                <td>Course Fee:</td>
                                <td class="text-end"><strong>Rs. ${parseFloat(fee).toLocaleString('en-PK', {minimumFractionDigits: 2})}</strong></td>
                            </tr>
                            <tr>
                                <td>Registration Fee:</td>
                                <td class="text-end">Rs. 1,000</td>
                            </tr>
                            <tr>
                                <td>Total Payable:</td>
                                <td class="text-end"><strong>Rs. ${(parseFloat(fee) + 1000).toLocaleString('en-PK', {minimumFractionDigits: 2})}</strong></td>
                            </tr>
                        </table>
                        <p class="small text-muted mb-0">* Registration fee is non-refundable</p>
                    `;
                }
                
                // Load batches for selected course
                fetchBatches(courseId);
            } else {
                feeInfo.classList.add('d-none');
                document.getElementById('batchSelection').classList.add('d-none');
            }
        });

        // Fetch batches for selected course
        function fetchBatches(courseId) {
            const batchSelection = document.getElementById('batchSelection');
            const batchList = document.getElementById('batchList');
            
            if (!courseId) {
                batchSelection.classList.add('d-none');
                return;
            }
            
            // Show loading
            batchList.innerHTML = `
                <div class="col-12">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading available batches...</p>
                    </div>
                </div>
            `;
            batchSelection.classList.remove('d-none');
            
            // Fetch batches via AJAX
            fetch(`get-batches.php?course_id=${courseId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.batches.length > 0) {
                        let batchesHtml = '';
                        data.batches.forEach(batch => {
                            batchesHtml += `
                                <div class="col-md-6">
                                    <div class="batch-selector" onclick="selectBatch(${batch.batch_id})">
                                        <div class="form-check">
                                            <input class="form-check-input" 
                                                   type="radio" 
                                                   name="batch_id" 
                                                   id="batch_${batch.batch_id}" 
                                                   value="${batch.batch_id}"
                                                   required>
                                            <label class="form-check-label w-100" for="batch_${batch.batch_id}">
                                                <strong>${batch.batch_name}</strong>
                                                <div class="mt-2">
                                                    <small class="text-muted">
                                                        <i class="fas fa-calendar me-1"></i>
                                                        ${new Date(batch.start_date).toLocaleDateString('en-PK', { month: 'short', day: 'numeric', year: 'numeric' })} - 
                                                        ${new Date(batch.end_date).toLocaleDateString('en-PK', { month: 'short', day: 'numeric', year: 'numeric' })}
                                                    </small>
                                                </div>
                                                ${batch.teacher_name ? `
                                                    <div>
                                                        <small class="text-muted">
                                                            <i class="fas fa-chalkboard-teacher me-1"></i>
                                                            ${batch.teacher_name}
                                                        </small>
                                                    </div>
                                                ` : ''}
                                                ${batch.schedule_details ? `
                                                    <div>
                                                        <small class="text-muted">
                                                            <i class="fas fa-clock me-1"></i>
                                                            ${batch.schedule_details}
                                                        </small>
                                                    </div>
                                                ` : ''}
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            `;
                        });
                        batchList.innerHTML = batchesHtml;
                    } else {
                        batchList.innerHTML = `
                            <div class="col-12">
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    No batches available for this course at the moment. 
                                    Please <a href="contact.php" class="alert-link">contact us</a> for more information.
                                </div>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error fetching batches:', error);
                    batchList.innerHTML = `
                        <div class="col-12">
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                Unable to load batches. Please try again or contact support.
                            </div>
                        </div>
                    `;
                });
        }

        // Select batch function
        function selectBatch(batchId) {
            document.querySelectorAll('.batch-selector').forEach(selector => {
                selector.classList.remove('selected');
            });
            
            const selector = document.querySelector(`.batch-selector input[value="${batchId}"]`).closest('.batch-selector');
            selector.classList.add('selected');
            
            document.getElementById(`batch_${batchId}`).checked = true;
        }

        // Form validation
        document.getElementById('enrollmentForm').addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;
            let firstInvalidField = null;
            
            requiredFields.forEach(field => {
                let fieldValid = false;
                
                if (field.type === 'radio') {
                    // For radio buttons, check if any radio with the same name is checked
                    const radioGroup = this.querySelectorAll(`input[name="${field.name}"]`);
                    fieldValid = Array.from(radioGroup).some(radio => radio.checked);
                } else if (field.type === 'checkbox') {
                    fieldValid = field.checked;
                } else {
                    fieldValid = field.value && field.value.trim() !== '';
                }
                
                if (!fieldValid) {
                    isValid = false;
                    field.classList.add('is-invalid');
                    if (!firstInvalidField) {
                        firstInvalidField = field;
                    }
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields correctly.');
                if (firstInvalidField) {
                    firstInvalidField.focus();
                    firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                return false;
            }
            
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
            submitBtn.disabled = true;
            
            return true;
        });

        // Trigger course change on page load if course is pre-selected
        document.addEventListener('DOMContentLoaded', function() {
            const courseSelect = document.getElementById('course_id');
            if (courseSelect.value) {
                courseSelect.dispatchEvent(new Event('change'));
            }
        });
        
        <?php if ($courseId): ?>
            // Also trigger immediately for pre-selected course
            window.addEventListener('load', function() {
                const courseSelect = document.getElementById('course_id');
                if (courseSelect.value) {
                    courseSelect.dispatchEvent(new Event('change'));
                }
            });
        <?php endif; ?>
    </script>
</body>
</html>