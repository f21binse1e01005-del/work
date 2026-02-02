<?php
/**
 * Contact Us Page - Enhanced with Beautiful Text Animations
 * Advanced Developer Edition
 */

require_once 'config/database.php';
require_once 'config/session.php';
require_once 'config/middleware.php';

$session = new SessionManager();
$middleware = new Middleware();
$isLoggedIn = $session->isLoggedIn();
$userType = $session->get('user_type');
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
        'institute_name' => 'Skills Way Vocational Institute',
        'institute_address' => 'Model Town "B", Near Masjid Al-Farooq/Bank Al-Habib, Khanpur',
        'contact_phone' => '0307-0237356, 0331-3307365',
        'contact_email' => 'askillswaykpr@gmail.com',
        'operating_hours' => 'Monday–Saturday, 8:00 AM – 9:00 PM'
    ];
}

// Set logo path - using Logo Circle.png from uploads/images
$logoPath = 'uploads/images/Logo Circle.png';

// Check if the file exists, otherwise use fallback
if (!file_exists($logoPath) || !is_file($logoPath)) {
    $logoPath = 'uploads/lab/logo.png';
    if (!file_exists($logoPath) || !is_file($logoPath)) {
        $logoPath = 'https://via.placeholder.com/150x150/2c3e50/ffffff?text=SWVI';
    }
}

// CSRF token
$csrf_token = $session->setCSRFToken();

// Contact form processing
$success = '';
$error = '';
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!$session->validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Security token invalid. Please try again.';
    } else {
        $formData = [
            'name' => trim($_POST['name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'subject' => trim($_POST['subject'] ?? ''),
            'message' => trim($_POST['message'] ?? ''),
            'purpose' => $_POST['purpose'] ?? 'general'
        ];

        // Validation
        if (empty($formData['name']) || empty($formData['email']) || empty($formData['message'])) {
            $error = 'Please fill in all required fields.';
        } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            try {
                // Check if contact_messages table exists
                $checkTable = $db->query("SHOW TABLES LIKE 'contact_messages'")->fetch();
                if (!$checkTable) {
                    // Create table if it doesn't exist
                    $createTable = "CREATE TABLE contact_messages (
                        message_id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
                        name VARCHAR(100) NOT NULL,
                        email VARCHAR(100) NOT NULL,
                        phone VARCHAR(15),
                        subject VARCHAR(200),
                        message TEXT NOT NULL,
                        purpose ENUM('general', 'admission', 'course_info', 'complaint', 'suggestion') DEFAULT 'general',
                        status ENUM('unread', 'read', 'replied', 'archived') DEFAULT 'unread',
                        ip_address VARCHAR(45),
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        replied_at TIMESTAMP NULL,
                        reply_message TEXT,
                        replied_by INT UNSIGNED,
                        FOREIGN KEY (replied_by) REFERENCES users(user_id) ON DELETE SET NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                    $db->exec($createTable);
                }
                
                // Save to database
                $sql = "INSERT INTO contact_messages (name, email, phone, subject, message, purpose, ip_address, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $formData['name'],
                    $formData['email'],
                    $formData['phone'],
                    $formData['subject'],
                    $formData['message'],
                    $formData['purpose'],
                    $_SERVER['REMOTE_ADDR']
                ]);

                // Send email notification
                $to = $settings['contact_email'] ?? 'admin@skillsway.edu.pk';
                $subject = "New Contact Form Message: " . $formData['subject'];
                $message = "New contact form submission from Skills Way website:\n\n" .
                          "Name: {$formData['name']}\n" .
                          "Email: {$formData['email']}\n" .
                          "Phone: {$formData['phone']}\n" .
                          "Purpose: {$formData['purpose']}\n" .
                          "Subject: {$formData['subject']}\n\n" .
                          "Message:\n{$formData['message']}\n\n" .
                          "IP Address: {$_SERVER['REMOTE_ADDR']}\n" .
                          "Time: " . date('Y-m-d H:i:s');
                
                $headers = "From: {$formData['email']}\r\n" .
                          "Reply-To: {$formData['email']}\r\n" .
                          "X-Mailer: PHP/" . phpversion();
                
                // Uncomment when email is configured
                // mail($to, $subject, $message, $headers);

                $success = 'Thank you for your message! We will get back to you within 24 hours.';
                $formData = []; // Clear form

                // Log activity if user is logged in
                if ($isLoggedIn) {
                    $middleware->logActivity('contact_form_submission', [
                        'email' => $formData['email'] ?? '',
                        'subject' => $formData['subject'] ?? ''
                    ]);
                }

            } catch (Exception $e) {
                $error = 'Unable to send your message. Please try again later.';
                error_log("Contact form error: " . $e->getMessage());
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
    <title>Contact Us - <?php echo htmlspecialchars($settings['institute_name']); ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Animate.css for animations -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <!-- AOS Animation Library -->
    <link rel="stylesheet" href="https://unpkg.com/aos@2.3.1/dist/aos.css">
    <!-- Leaflet CSS for Map -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --success-color: #2ecc71;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
            --animation-timing: cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html {
            scroll-behavior: smooth;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            overflow-x: hidden;
            line-height: 1.6;
        }
        
        /* Animated Background with Logo */
        .animated-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            opacity: 0.03;
            background: url('<?php echo htmlspecialchars($logoPath); ?>') center/contain no-repeat;
            animation: floatLogo 30s ease-in-out infinite;
        }
        
        @keyframes floatLogo {
            0%, 100% {
                transform: translate(0, 0) rotate(0deg);
                opacity: 0.03;
            }
            25% {
                transform: translate(20px, 20px) rotate(5deg);
                opacity: 0.05;
            }
            50% {
                transform: translate(0, 40px) rotate(0deg);
                opacity: 0.03;
            }
            75% {
                transform: translate(-20px, 20px) rotate(-5deg);
                opacity: 0.05;
            }
        }
        
        /* Navigation */
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }
        
        .navbar-brand {
            font-weight: 700;
            color: var(--primary-color) !important;
            font-family: 'Montserrat', sans-serif;
        }
        
        .navbar-brand img {
            transition: transform 0.3s ease;
        }
        
        .navbar-brand:hover img {
            transform: rotate(5deg);
        }
        
        .nav-link {
            position: relative;
            padding: 8px 15px !important;
            margin: 0 5px;
            transition: all 0.3s ease;
        }
        
        .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 3px;
            background: var(--secondary-color);
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }
        
        .nav-link:hover::after,
        .nav-link.active::after {
            width: 80%;
        }
        
        .nav-link:hover {
            color: var(--secondary-color) !important;
        }
        
        /* Hero Section with Typing Animation */
        .hero-contact {
            background: linear-gradient(135deg, rgba(44, 62, 80, 0.95) 0%, rgba(52, 152, 219, 0.92) 100%);
            color: white;
            padding: 150px 0 100px;
            position: relative;
            overflow: hidden;
            min-height: 60vh;
            display: flex;
            align-items: center;
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
        }
        
        .hero-title {
            font-size: 4rem;
            font-weight: 800;
            margin-bottom: 20px;
            background: linear-gradient(45deg, #fff, #3498db, #2ecc71);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            background-size: 300% 300%;
            animation: gradientShift 8s ease infinite, fadeInUp 1s ease forwards;
        }
        
        @keyframes gradientShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .typing-container {
            display: inline-block;
            min-height: 60px;
            margin-bottom: 30px;
        }
        
        .typing-text {
            border-right: 3px solid var(--secondary-color);
            white-space: nowrap;
            overflow: hidden;
            margin: 0 auto;
            letter-spacing: 1px;
            display: inline-block;
            font-size: 1.5rem;
            opacity: 0;
            animation: typing 3s steps(30, end) forwards 1s, blink-caret 0.75s step-end infinite 1s;
        }
        
        @keyframes typing {
            from {
                width: 0;
                opacity: 1;
            }
            to {
                width: 100%;
                opacity: 1;
            }
        }
        
        @keyframes blink-caret {
            from, to { border-color: transparent; }
            50% { border-color: var(--secondary-color); }
        }
        
        /* Enhanced Contact Cards */
        .contact-info-card {
            background: white;
            border-radius: 20px;
            padding: 40px 30px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            transition: all 0.6s var(--animation-timing);
            position: relative;
            overflow: hidden;
            border: 2px solid transparent;
            background-clip: padding-box;
            height: 100%;
            opacity: 0;
            transform: translateY(50px);
        }
        
        .contact-info-card.animated {
            opacity: 1;
            transform: translateY(0);
        }
        
        .contact-info-card::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, var(--secondary-color), var(--success-color), var(--accent-color));
            z-index: -1;
            border-radius: 22px;
            opacity: 0;
            transition: opacity 0.6s var(--animation-timing);
        }
        
        .contact-info-card:hover::before {
            opacity: 1;
        }
        
        .contact-info-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 25px 50px rgba(0,0,0,0.15);
        }
        
        .contact-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--secondary-color), var(--success-color));
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 25px;
            transition: all 0.4s ease;
        }
        
        .contact-info-card:hover .contact-icon {
            transform: rotate(15deg) scale(1.1);
            background: linear-gradient(135deg, var(--accent-color), var(--secondary-color));
        }
        
        /* Map Container */
        .map-container {
            position: relative;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            transform: translateY(30px);
            opacity: 0;
            transition: all 0.8s var(--animation-timing);
        }
        
        .map-container.animated {
            opacity: 1;
            transform: translateY(0);
        }
        
        #map {
            height: 400px;
            width: 100%;
        }
        
        /* Operating Hours */
        .operating-hours {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 20px;
            padding: 30px;
            margin-top: 30px;
            border: 2px solid transparent;
            background-clip: padding-box;
            position: relative;
        }
        
        .operating-hours::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, var(--secondary-color), var(--success-color));
            border-radius: 22px;
            z-index: -1;
            opacity: 0.1;
        }
        
        .hours-table td {
            padding: 12px 0;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }
        
        .hours-table tr:last-child td {
            border-bottom: none;
        }
        
        /* Enhanced Form */
        .form-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
            border: 2px solid transparent;
            background-clip: padding-box;
        }
        
        .form-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, var(--secondary-color), var(--success-color));
        }
        
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 12px 20px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
            transform: translateY(-2px);
        }
        
        .form-label {
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--primary-color);
        }
        
        /* Enhanced Submit Button */
        .btn-submit {
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--success-color) 100%);
            border: none;
            padding: 15px 40px;
            font-weight: 600;
            font-size: 1.1rem;
            border-radius: 50px;
            position: relative;
            overflow: hidden;
            transition: all 0.4s ease;
            width: 100%;
            margin-top: 20px;
        }
        
        .btn-submit span {
            position: relative;
            z-index: 1;
        }
        
        .btn-submit::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.7s;
        }
        
        .btn-submit:hover::before {
            left: 100%;
        }
        
        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(52, 152, 219, 0.3);
            background: linear-gradient(135deg, var(--success-color) 0%, var(--secondary-color) 100%);
        }
        
        /* Social Media Buttons */
        .social-buttons .btn {
            border-radius: 50px;
            padding: 10px 25px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: 2px solid;
        }
        
        .social-buttons .btn:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        
        /* FAQ Accordion */
        .accordion-item {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            margin-bottom: 15px;
            overflow: hidden;
        }
        
        .accordion-button {
            font-weight: 600;
            padding: 20px;
            background: white;
            color: var(--primary-color);
            border: none;
        }
        
        .accordion-button:not(.collapsed) {
            background: var(--secondary-color);
            color: white;
            box-shadow: none;
        }
        
        .accordion-button:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }
        
        .accordion-body {
            padding: 20px;
            background: #f8f9fa;
        }
        
        /* Footer */
        .footer {
            background: linear-gradient(135deg, var(--primary-color) 0%, #1a252f 100%);
            color: white;
            padding: 80px 0 30px;
            position: relative;
        }
        
        .footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--secondary-color), var(--success-color), var(--accent-color));
        }
        
        .footer-link {
            color: rgba(255,255,255,0.8) !important;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-block;
            margin-bottom: 10px;
        }
        
        .footer-link:hover {
            color: var(--secondary-color) !important;
            transform: translateX(5px);
        }
        
        .social-icons a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 45px;
            height: 45px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            color: white;
            margin-right: 12px;
            transition: all 0.3s ease;
        }
        
        .social-icons a:hover {
            background: var(--secondary-color);
            transform: translateY(-5px) rotate(5deg);
            box-shadow: 0 10px 20px rgba(52,152,219,0.3);
        }
        
        /* Text Animation Classes */
        .animate-text {
            opacity: 0;
            transform: translateY(30px);
            animation: textReveal 1s ease forwards var(--delay);
        }
        
        @keyframes textReveal {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .hero-contact {
                padding: 120px 0 80px;
                min-height: 50vh;
            }
            
            .hero-title {
                font-size: 2.5rem;
            }
            
            .typing-text {
                font-size: 1.2rem;
            }
            
            .contact-info-card {
                padding: 30px 20px;
            }
            
            .form-container {
                padding: 30px 20px;
            }
            
            .map-container {
                margin-bottom: 30px;
            }
            
            .social-buttons .btn {
                margin-bottom: 10px;
            }
        }
        
        @media (max-width: 576px) {
            .hero-contact {
                padding: 100px 0 60px;
            }
            
            .hero-title {
                font-size: 2rem;
            }
            
            .contact-icon {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }
            
            .btn-submit {
                padding: 12px 30px;
            }
        }
    </style>
</head>
<body>
    <!-- Animated Background -->
    <div class="animated-background"></div>
    
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="<?php echo htmlspecialchars($logoPath); ?>" 
                     alt="<?php echo htmlspecialchars($settings['institute_name']); ?> Logo" 
                     height="45" 
                     class="me-2"
                     onerror="this.onerror=null; this.src='https://via.placeholder.com/45x45/2c3e50/ffffff?text=SWVI'">
                <span class="fw-bold"><?php echo htmlspecialchars($settings['institute_name']); ?></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="about.php">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="courses.php">Courses</a></li>
                    <li class="nav-item"><a class="nav-link active" href="contact.php" aria-current="page">Contact</a></li>
                    <?php if ($isLoggedIn): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($fullName); ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="<?php echo htmlspecialchars($userType); ?>/dashboard.php">
                                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                                </a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item ms-2"><a class="btn btn-outline-primary" href="login.php">
                            <i class="fas fa-sign-in-alt me-2"></i>Login
                        </a></li>
                        <li class="nav-item ms-2"><a class="btn btn-primary" href="register.php">
                            <i class="fas fa-user-plus me-2"></i>Register
                        </a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-contact">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10 col-xl-8 hero-content text-center">
                    <h1 class="hero-title animate__animated animate__fadeInDown">Contact Us</h1>
                    <div class="typing-container">
                        <div class="typing-text">Get in touch with us for inquiries, admissions, or any other information</div>
                    </div>
                    <div class="d-flex flex-wrap justify-content-center gap-4 mt-4 animate__animated animate__fadeInUp animate__delay-1s">
                        <a href="#contact-form" class="btn btn-primary btn-lg px-5 py-3">
                            <i class="fas fa-paper-plane me-2"></i>Send Message
                        </a>
                        <a href="#map" class="btn btn-outline-light btn-lg px-5 py-3">
                            <i class="fas fa-map-marker-alt me-2"></i>View Location
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Information -->
    <section class="py-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="contact-info-card" data-aos="fade-up" data-aos-delay="100">
                        <div class="contact-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <h4 class="mb-3">Our Location</h4>
                        <p class="mb-2"><?php echo htmlspecialchars($settings['institute_address']); ?></p>
                        <p class="text-muted mb-0">Khanpur, Punjab, Pakistan</p>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="contact-info-card" data-aos="fade-up" data-aos-delay="200">
                        <div class="contact-icon">
                            <i class="fas fa-phone"></i>
                        </div>
                        <h4 class="mb-3">Phone Numbers</h4>
                        <p class="mb-2"><?php echo htmlspecialchars($settings['contact_phone']); ?></p>
                        <p class="text-muted mb-0">Call us during business hours</p>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="contact-info-card" data-aos="fade-up" data-aos-delay="300">
                        <div class="contact-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <h4 class="mb-3">Email Address</h4>
                        <p class="mb-2"><?php echo htmlspecialchars($settings['contact_email']); ?></p>
                        <p class="text-muted mb-0">We respond within 24 hours</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Map & Contact Form -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row g-5">
                <!-- Map Section -->
                <div class="col-lg-6">
                    <h3 class="mb-4 animate-text" style="--delay: 0.2s">Our Location</h3>
                    <div class="map-container" data-aos="zoom-in" data-aos-delay="100">
                        <div id="map"></div>
                    </div>
                    
                    <div class="operating-hours" data-aos="fade-up" data-aos-delay="200">
                        <h5 class="mb-4"><i class="fas fa-clock me-2 text-primary"></i>Operating Hours</h5>
                        <table class="hours-table w-100">
                            <tr>
                                <td><strong>Monday - Saturday</strong></td>
                                <td class="text-end">8:00 AM - 9:00 PM</td>
                            </tr>
                            <tr>
                                <td><strong>Sunday</strong></td>
                                <td class="text-end">Closed</td>
                            </tr>
                            <tr>
                                <td><strong>Office Hours</strong></td>
                                <td class="text-end">9:00 AM - 5:00 PM</td>
                            </tr>
                            <tr>
                                <td><strong>Admission Hours</strong></td>
                                <td class="text-end">10:00 AM - 4:00 PM</td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- Contact Form -->
                <div class="col-lg-6">
                    <div class="form-container" data-aos="fade-up" data-aos-delay="300">
                        <h3 class="mb-4" id="contact-form">Send us a Message</h3>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show animate__animated animate__shakeX" role="alert">
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show animate__animated animate__fadeIn" role="alert">
                                <?php echo htmlspecialchars($success); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" id="contactForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <label for="name" class="form-label">Your Name *</label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="name" 
                                           name="name" 
                                           value="<?php echo htmlspecialchars($formData['name'] ?? ''); ?>"
                                           required
                                           placeholder="Enter your full name">
                                </div>
                                
                                <div class="col-md-6 mb-4">
                                    <label for="email" class="form-label">Email Address *</label>
                                    <input type="email" 
                                           class="form-control" 
                                           id="email" 
                                           name="email" 
                                           value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>"
                                           required
                                           placeholder="your.email@example.com">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" 
                                           class="form-control" 
                                           id="phone" 
                                           name="phone" 
                                           placeholder="0300-1234567"
                                           value="<?php echo htmlspecialchars($formData['phone'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-6 mb-4">
                                    <label for="purpose" class="form-label">Purpose of Contact</label>
                                    <select class="form-control" id="purpose" name="purpose">
                                        <option value="general" <?php echo ($formData['purpose'] ?? '') == 'general' ? 'selected' : ''; ?>>General Inquiry</option>
                                        <option value="admission" <?php echo ($formData['purpose'] ?? '') == 'admission' ? 'selected' : ''; ?>>Admission Inquiry</option>
                                        <option value="course_info" <?php echo ($formData['purpose'] ?? '') == 'course_info' ? 'selected' : ''; ?>>Course Information</option>
                                        <option value="complaint" <?php echo ($formData['purpose'] ?? '') == 'complaint' ? 'selected' : ''; ?>>Complaint</option>
                                        <option value="suggestion" <?php echo ($formData['purpose'] ?? '') == 'suggestion' ? 'selected' : ''; ?>>Suggestion</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="subject" class="form-label">Subject</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="subject" 
                                       name="subject" 
                                       value="<?php echo htmlspecialchars($formData['subject'] ?? ''); ?>"
                                       placeholder="What is this regarding?">
                            </div>
                            
                            <div class="mb-4">
                                <label for="message" class="form-label">Your Message *</label>
                                <textarea class="form-control" 
                                          id="message" 
                                          name="message" 
                                          rows="5"
                                          required
                                          placeholder="Please write your message here..."><?php echo htmlspecialchars($formData['message'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="newsletter" name="newsletter" checked>
                                    <label class="form-check-label" for="newsletter">
                                        Subscribe to our newsletter for updates and announcements
                                    </label>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-submit">
                                <i class="fas fa-paper-plane me-2"></i><span>Send Message</span>
                            </button>
                        </form>
                    </div>
                    
                    <div class="mt-4" data-aos="fade-up" data-aos-delay="400">
                        <h5 class="mb-3">Other Ways to Connect</h5>
                        <div class="social-buttons d-flex flex-wrap gap-3">
                            <a href="#" class="btn btn-outline-primary">
                                <i class="fab fa-facebook-f me-2"></i>Facebook
                            </a>
                            <a href="#" class="btn btn-outline-info">
                                <i class="fab fa-twitter me-2"></i>Twitter
                            </a>
                            <a href="#" class="btn btn-outline-danger">
                                <i class="fab fa-instagram me-2"></i>Instagram
                            </a>
                            <a href="#" class="btn btn-outline-success">
                                <i class="fab fa-whatsapp me-2"></i>WhatsApp
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="py-5">
        <div class="container">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="fw-bold display-5 mb-3">Frequently Asked Questions</h2>
                <p class="lead text-muted">Find quick answers to common questions</p>
            </div>
            
            <div class="accordion" id="faqAccordion">
                <div class="accordion-item" data-aos="fade-up" data-aos-delay="100">
                    <h2 class="accordion-header">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                            <i class="fas fa-question-circle me-2 text-primary"></i>What are the admission requirements?
                        </button>
                    </h2>
                    <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            You need a valid CNIC, minimum matriculation certificate for most courses, and basic computer literacy for IT courses. Some advanced courses may have additional requirements.
                        </div>
                    </div>
                </div>
                
                <div class="accordion-item" data-aos="fade-up" data-aos-delay="200">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                            <i class="fas fa-question-circle me-2 text-primary"></i>What are the class timings?
                        </button>
                    </h2>
                    <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            We offer morning (8 AM - 12 PM), afternoon (2 PM - 5 PM), and evening (6 PM - 9 PM) batches. Weekend batches are also available for working professionals.
                        </div>
                    </div>
                </div>
                
                <div class="accordion-item" data-aos="fade-up" data-aos-delay="300">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                            <i class="fas fa-question-circle me-2 text-primary"></i>Do you provide certificates?
                        </button>
                    </h2>
                    <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Yes, we provide completion certificates that are recognized by NAVTTC and relevant industry bodies. Certificates are issued upon successful completion of the course with minimum 75% attendance and passing grades.
                        </div>
                    </div>
                </div>
                
                <div class="accordion-item" data-aos="fade-up" data-aos-delay="400">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                            <i class="fas fa-question-circle me-2 text-primary"></i>Is hostel accommodation available?
                        </button>
                    </h2>
                    <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Yes, we have separate hostel facilities for male and female students. Hostels include meals, Wi-Fi, and 24/7 security. Contact our administration office for availability and fee details.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4" data-aos="fade-up" data-aos-delay="100">
                    <h4 class="mb-4">
                        <img src="<?php echo htmlspecialchars($logoPath); ?>" 
                             alt="Logo" 
                             height="45" 
                             class="me-2"
                             onerror="this.onerror=null; this.src='https://via.placeholder.com/45x45/2c3e50/ffffff?text=SWVI'">
                        <span class="fw-bold"><?php echo htmlspecialchars($settings['institute_name']); ?></span>
                    </h4>
                    <p class="mb-4 opacity-75"><?php echo htmlspecialchars($settings['institute_address']); ?></p>
                    <div class="social-icons">
                        <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                        <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="200">
                    <h5 class="mb-3">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="index.php" class="footer-link">Home</a></li>
                        <li class="mb-2"><a href="about.php" class="footer-link">About Us</a></li>
                        <li class="mb-2"><a href="courses.php" class="footer-link">Courses</a></li>
                        <li class="mb-2"><a href="contact.php" class="footer-link">Contact</a></li>
                        <li class="mb-2"><a href="enrollment-form.php" class="footer-link">Enroll Now</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="300">
                    <h5 class="mb-3">Contact Info</h5>
                    <ul class="list-unstyled">
                        <li class="mb-3">
                            <i class="fas fa-phone me-2 opacity-75"></i>
                            <span class="opacity-75"><?php echo htmlspecialchars($settings['contact_phone']); ?></span>
                        </li>
                        <li class="mb-3">
                            <i class="fas fa-envelope me-2 opacity-75"></i>
                            <a href="mailto:<?php echo htmlspecialchars($settings['contact_email']); ?>" class="footer-link">
                                <?php echo htmlspecialchars($settings['contact_email']); ?>
                            </a>
                        </li>
                        <li class="mb-3">
                            <i class="fas fa-clock me-2 opacity-75"></i>
                            <span class="opacity-75"><?php echo htmlspecialchars($settings['operating_hours'] ?? 'Monday–Saturday, 8:00 AM – 9:00 PM'); ?></span>
                        </li>
                    </ul>
                </div>
                
                <div class="col-lg-3 mb-4" data-aos="fade-up" data-aos-delay="400">
                    <h5 class="mb-3">Emergency Contact</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="fas fa-map-marker-alt me-2 opacity-75"></i>Khanpur, Punjab, Pakistan</li>
                        <li class="mb-2"><i class="fas fa-exclamation-circle me-2 opacity-75"></i>24/7 Emergency Support Available</li>
                        <li class="mb-2"><i class="fas fa-headset me-2 opacity-75"></i>Admission Hotline: 0307-0237356</li>
                    </ul>
                </div>
            </div>
            
            <hr class="my-4 opacity-25">
            
            <div class="text-center pt-3" data-aos="fade-up" data-aos-delay="500">
                <p class="mb-0 opacity-75">
                    &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($settings['institute_name']); ?>. All rights reserved.
                </p>
                <p class="mb-0 mt-2 opacity-75">
                    <a href="privacy-policy.php" class="footer-link me-3">Privacy Policy</a> | 
                    <a href="terms.php" class="footer-link">Terms of Service</a>
                </p>
            </div>
        </div>
    </footer>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <!-- Leaflet JS for Map -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <script>
        // Initialize AOS
        AOS.init({
            duration: 1000,
            once: true,
            offset: 100
        });

        // Initialize map (Khanpur, Pakistan coordinates)
        const map = L.map('map').setView([28.6473, 70.6569], 15);
        
        // Add OpenStreetMap tiles
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);
        
        // Add custom marker
        const customIcon = L.icon({
            iconUrl: '<?php echo htmlspecialchars($logoPath); ?>',
            iconSize: [40, 40],
            iconAnchor: [20, 40],
            popupAnchor: [0, -40]
        });
        
        // Add marker for institute location
        L.marker([28.6473, 70.6569], {icon: customIcon}).addTo(map)
            .bindPopup(`
                <div class="text-center">
                    <h5 class="fw-bold mb-2">Skills Way Vocational Institute</h5>
                    <p class="mb-1"><?php echo htmlspecialchars($settings['institute_address']); ?></p>
                    <p class="mb-0">Khanpur, Punjab, Pakistan</p>
                </div>
            `)
            .openPopup();
        
        // Typing animation
        function startTypingAnimation() {
            const typingText = document.querySelector('.typing-text');
            const texts = [
                "Get in touch with us for inquiries, admissions, or any other information",
                "Reach out to us for course details, fees, or career guidance",
                "Contact our admission office for enrollment and queries",
                "We're here to help you transform your career"
            ];
            let currentText = 0;
            let charIndex = 0;
            let isDeleting = false;
            
            function type() {
                const currentString = texts[currentText];
                
                if (isDeleting) {
                    typingText.textContent = currentString.substring(0, charIndex - 1);
                    charIndex--;
                } else {
                    typingText.textContent = currentString.substring(0, charIndex + 1);
                    charIndex++;
                }
                
                if (!isDeleting && charIndex === currentString.length) {
                    setTimeout(() => isDeleting = true, 2000);
                } else if (isDeleting && charIndex === 0) {
                    isDeleting = false;
                    currentText = (currentText + 1) % texts.length;
                }
                
                const speed = isDeleting ? 50 : 100;
                setTimeout(type, speed);
            }
            
            // Start typing after initial animation
            setTimeout(type, 1500);
        }
        
        startTypingAnimation();
        
        // Form validation
        $('#contactForm').on('submit', function(e) {
            const name = $('#name').val().trim();
            const email = $('#email').val().trim();
            const message = $('#message').val().trim();
            const submitBtn = $(this).find('.btn-submit');
            const originalHTML = submitBtn.html();
            
            if (!name || !email || !message) {
                e.preventDefault();
                showNotification('Please fill in all required fields.', 'error');
                return false;
            }
            
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                showNotification('Please enter a valid email address.', 'error');
                return false;
            }
            
            // Add loading animation
            submitBtn.html('<i class="fas fa-spinner fa-spin me-2"></i>Sending...');
            submitBtn.prop('disabled', true);
            
            return true;
        });
        
        // Notification function
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show position-fixed`;
            notification.style.top = '20px';
            notification.style.right = '20px';
            notification.style.zIndex = '9999';
            notification.style.minWidth = '300px';
            notification.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
                    <span>${message}</span>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 5000);
        }
        
        // Animate contact cards on scroll
        function animateOnScroll() {
            const cards = document.querySelectorAll('.contact-info-card, .map-container');
            
            cards.forEach((card, index) => {
                const cardTop = card.getBoundingClientRect().top;
                const windowHeight = window.innerHeight;
                
                if (cardTop < windowHeight * 0.85) {
                    setTimeout(() => {
                        card.classList.add('animated');
                    }, index * 200);
                }
            });
        }
        
        // Initial check
        animateOnScroll();
        
        // Listen for scroll
        window.addEventListener('scroll', animateOnScroll);
        
        // Smooth scrolling for anchor links
        $('a[href^="#"]').on('click', function(e) {
            if (this.hash !== '' && $(this.hash).length) {
                e.preventDefault();
                const hash = this.hash;
                
                $('html, body').animate({
                    scrollTop: $(hash).offset().top - 80
                }, 800);
            }
        });
        
        // Navbar scroll effect
        $(window).on('scroll', function() {
            const navbar = $('.navbar');
            if ($(window).scrollTop() > 50) {
                navbar.addClass('shadow-lg');
            } else {
                navbar.removeClass('shadow-lg');
            }
        });
        
        // Image error handling
        $('img').on('error', function() {
            if (!$(this).attr('data-error-handled')) {
                $(this).attr('src', 'https://via.placeholder.com/150x150/2c3e50/ffffff?text=SWVI');
                $(this).attr('data-error-handled', 'true');
            }
        });
        
        // Form field animation on focus
        $('.form-control').on('focus', function() {
            $(this).parent().addClass('focused');
        }).on('blur', function() {
            if ($(this).val().trim() === '') {
                $(this).parent().removeClass('focused');
            }
        });
    </script>
</body>
</html>