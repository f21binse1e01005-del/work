<?php
/**
 * About Us Page - Optimized Version
 * Clean, efficient code with better performance and maintainability
 */

require_once 'config/database.php';
require_once 'config/session.php';

// Initialize session
$session = new SessionManager();
$isLoggedIn = $session->isLoggedIn();
$userType = $session->get('user_type');
$fullName = $session->get('full_name');

// Fetch institute settings with optimized query
$settings = [];
try {
    $db = (new Database())->getConnection();
    
    // Single optimized query to check and fetch settings
    $stmt = $db->prepare("
        SELECT setting_key, setting_value 
        FROM institute_settings 
        WHERE EXISTS (
            SELECT 1 FROM information_schema.tables 
            WHERE table_schema = DATABASE() 
            AND table_name = 'institute_settings'
        )
    ");
    
    if ($stmt && $stmt->execute()) {
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($result as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
} catch (Exception $e) {
    error_log("About page settings error: " . $e->getMessage());
    $settings = []; // Ensure array is defined
}

// Default settings with fallback
$defaultSettings = [
    'institute_name' => 'Skills Way Vocational Institute',
    'institute_address' => 'Model Town "B", Near Masjid Al-Farooq/Bank Al-Habib, Khanpur',
    'contact_phone' => '0307-0237356, 0331-3307365',
    'contact_email' => 'askillswaykpr@gmail.com'
];

$settings = array_merge($defaultSettings, $settings);

// Optimized logo path resolution
$logoPaths = [
    'uploads/images/Logo Circle.png',
    'uploads/lab/logo.png',
    'assets/images/default-logo.png' // Added default local fallback
];

$mainLogo = '';
foreach ($logoPaths as $path) {
    if (file_exists($path) && is_file($path)) {
        $mainLogo = $path;
        break;
    }
}

// Fallback to CDN if no local logo found
if (empty($mainLogo)) {
    $mainLogo = 'https://images.unsplash.com/photo-1523050854058-8df90110c9f1?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80';
}

// Optimized image handler class
class ImageHandler {
    private static $fallbackImages = [
        'team member' => 'https://images.unsplash.com/photo-1582750433449-648ed127bb54',
        'ahmad mahmood' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d',
        'muhammad danish' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d',
        'mirza asad tahir' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d',
        'rafay' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d',
        'umar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d',
        'default' => 'https://images.unsplash.com/photo-1494790108755-2616b612b786'
    ];
    
    public static function getImage($path, $memberName) {
        if (file_exists($path) && is_file($path) && getimagesize($path)) {
            return [
                'path' => $path,
                'exists' => true,
                'fallback' => false
            ];
        }
        
        $nameLower = strtolower($memberName);
        $fallbackKey = 'default';
        
        foreach (self::$fallbackImages as $key => $url) {
            if (str_contains($nameLower, $key)) {
                $fallbackKey = $key;
                break;
            }
        }
        
        return [
            'path' => self::$fallbackImages[$fallbackKey] . '?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
            'exists' => false,
            'fallback' => true
        ];
    }
}

// Team members data - using constants for better maintainability
define('TEAM_FULL_FRAME', [
    [
        'image' => './uploads/profiles/ist.jpeg',
        'name' => 'Team Member 1',
        'position' => 'Professional Staff',
        'description' => 'Dedicated professional contributing to our institute\'s success',
        'social' => ['linkedin' => '#', 'twitter' => '#']
    ],
    [
        'image' => './uploads/profiles/2nd.jpeg',
        'name' => 'Team Member 2',
        'position' => 'Professional Staff',
        'description' => 'Experienced professional with industry expertise',
        'social' => ['linkedin' => '#', 'twitter' => '#']
    ]
]);

define('TEAM_REGULAR', [
    [
        'image' => './uploads/profiles/Ahmad Mahmood.jpeg',
        'name' => 'Ahmad Mahmood',
        'position' => 'Computer Application Instructor',
        'description' => 'Expert in computer applications with years of teaching experience',
        'social' => ['linkedin' => '#', 'github' => '#']
    ],
    [
        'image' => './uploads/profiles/Danish.png',
        'name' => 'Muhammad Danish',
        'position' => 'BS Cyber Security',
        'description' => 'Skills: Python, Full Stack Developer, Ethical Hacker. Expert in cybersecurity and full-stack development',
        'social' => ['linkedin' => '#', 'github' => '#']
    ],
    [
        'image' => './uploads/profiles/Passport Size Photo Mirza Asad Tahir.jpg',
        'name' => 'Mirza Asad Tahir',
        'position' => 'Graphic Design Expert',
        'description' => 'Adobe Creative Suite: Photoshop, Illustrator, InDesign. Digital Product Tools: Figma, Sketch. Creative Hardware: Wacom tablets, Procreate',
        'social' => ['behance' => '#', 'dribbble' => '#']
    ],
    [
        'image' => './uploads/profiles/Rafay .jpeg',
        'name' => 'Rafay',
        'position' => 'Web Development Expert',
        'description' => 'Specialized in modern web development technologies and frameworks',
        'social' => ['linkedin' => '#', 'github' => '#']
    ],
    [
        'image' => './uploads/profiles/Umar.png',
        'name' => 'Umar',
        'position' => 'Student Representative',
        'description' => 'Active student and YouTube content creator, representing student community',
        'social' => ['youtube' => '#', 'instagram' => '#']
    ]
]);

// Timeline data
$timelineData = [
    ['year' => '2021', 'title' => 'Digital Transformation Begins', 'icon' => 'fas fa-laptop-code'],
    ['year' => '2022', 'title' => 'Industry Partnerships Expanded', 'icon' => 'fas fa-handshake'],
    ['year' => '2023', 'title' => 'Campus Modernization', 'icon' => 'fas fa-building'],
    ['year' => '2024', 'title' => 'International Recognition', 'icon' => 'fas fa-globe-asia'],
    ['year' => '2025', 'title' => 'Future Ready Institute', 'icon' => 'fas fa-rocket']
];

// Generate current URL for Open Graph
$currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Learn about Skills Way Vocational Institute - Empowering youth with industry-relevant skills since 2020.">
    <meta name="keywords" content="vocational training, skills development, technical education, Pakistan, Khanpur">
    <meta name="author" content="<?php echo htmlspecialchars($settings['institute_name']); ?>">
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="About Skills Way Vocational Institute">
    <meta property="og:description" content="Empowering Pakistani youth with industry-relevant skills for a prosperous future since 2020.">
    <meta property="og:image" content="<?php echo htmlspecialchars($mainLogo); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($currentUrl); ?>">
    <meta property="og:type" content="website">
    
    <!-- Favicon -->
    <link rel="icon" href="<?php echo htmlspecialchars($mainLogo); ?>" type="image/png">
    
    <title>About Us - <?php echo htmlspecialchars($settings['institute_name']); ?></title>
    
    <!-- Optimized CSS Imports -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <!-- Preload critical resources -->
    <link rel="preload" href="<?php echo htmlspecialchars($mainLogo); ?>" as="image">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --success-color: #2ecc71;
            --light-color: #ecf0f1;
            --shadow-light: 0 10px 30px rgba(0, 0, 0, 0.1);
            --shadow-medium: 0 20px 40px rgba(0, 0, 0, 0.15);
            --transition-speed: 0.3s;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html {
            scroll-behavior: smooth;
            scroll-padding-top: 80px;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            line-height: 1.6;
            color: #333;
            overflow-x: hidden;
        }
        
        /* Particle Background - Optimized */
        #particles-js {
            position: fixed;
            width: 100%;
            height: 100%;
            z-index: -1;
            opacity: 0.3;
            pointer-events: none;
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            line-height: 1.3;
            margin-bottom: 1rem;
        }
        
        /* Optimized Navbar */
        .navbar {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95) !important;
            transition: all var(--transition-speed) ease;
            padding: 1rem 0;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1030;
        }
        
        .navbar.scrolled {
            padding: 0.5rem 0;
            box-shadow: var(--shadow-light);
        }
        
        .navbar-brand {
            font-weight: 700;
            color: var(--primary-color) !important;
        }
        
        .navbar-brand img {
            transition: transform var(--transition-speed) ease;
        }
        
        .navbar-brand:hover img {
            transform: translateY(-2px);
        }
        
        /* Hero Section - Optimized */
        .hero-about {
            background: linear-gradient(135deg, 
                rgba(44, 62, 80, 0.95) 0%, 
                rgba(52, 152, 219, 0.92) 100%);
            color: white;
            padding: 180px 0 120px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
        }
        
        .hero-about::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 20% 80%, rgba(52, 152, 219, 0.15) 0%, transparent 50%);
            animation: pulse 8s ease-in-out infinite alternate;
        }
        
        @keyframes pulse {
            0% { opacity: 0.5; transform: scale(1); }
            100% { opacity: 1; transform: scale(1.1); }
        }
        
        /* Text Effects - Optimized */
        .text-gradient {
            background: linear-gradient(45deg, #3498db, #2ecc71, #e74c3c);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            background-size: 300% 300%;
            animation: gradient-shift 8s ease infinite;
        }
        
        @keyframes gradient-shift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
        
        /* Image Frames - Optimized */
        .image-frame {
            position: relative;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow-medium);
            background: linear-gradient(45deg, 
                rgba(52, 152, 219, 0.1) 0%, 
                rgba(46, 204, 113, 0.1) 50%);
        }
        
        .image-frame img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform var(--transition-speed) ease;
        }
        
        .image-frame:hover img {
            transform: scale(1.05);
        }
        
        /* Team Section - Optimized */
        .team-section {
            background: linear-gradient(135deg, 
                rgba(52, 152, 219, 0.05) 0%, 
                rgba(46, 204, 113, 0.05) 100%);
            padding: 100px 0;
        }
        
        .team-member-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: var(--shadow-light);
            transition: all var(--transition-speed) ease;
            height: 100%;
            border: 2px solid transparent;
        }
        
        .team-member-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-medium);
            border-color: var(--secondary-color);
        }
        
        .team-frame-full {
            height: 400px;
            margin-bottom: 30px;
        }
        
        .team-frame {
            height: 300px;
            margin-bottom: 30px;
        }
        
        /* Timeline Section - Optimized */
        .timeline-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 80px 0;
        }
        
        .timeline {
            position: relative;
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 0;
        }
        
        .timeline::after {
            content: '';
            position: absolute;
            width: 6px;
            background: linear-gradient(to bottom, #667eea, #764ba2);
            top: 0;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            border-radius: 3px;
        }
        
        .timeline-item {
            padding: 10px 40px;
            position: relative;
            width: 50%;
        }
        
        .timeline-item.left {
            left: 0;
            text-align: right;
        }
        
        .timeline-item.right {
            left: 50%;
            text-align: left;
        }
        
        .timeline-content {
            padding: 30px;
            background: white;
            border-radius: 15px;
            box-shadow: var(--shadow-light);
            transition: all var(--transition-speed) ease;
        }
        
        .timeline-content:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-medium);
        }
        
        .year-badge {
            display: inline-block;
            padding: 8px 20px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 25px;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .timeline-dot {
            position: absolute;
            width: 20px;
            height: 20px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            top: 20px;
            right: -10px;
            box-shadow: 0 0 10px rgba(102, 126, 234, 0.5);
        }
        
        .timeline-item.right .timeline-dot {
            left: -10px;
            right: auto;
        }
        
        /* Loading Bar - Optimized */
        .loading-bar {
            position: fixed;
            top: 0;
            left: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--secondary-color), var(--success-color));
            z-index: 9999;
            width: 0%;
            transition: width 0.3s ease;
        }
        
        /* Back to Top Button - Optimized */
        .back-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background: linear-gradient(45deg, var(--secondary-color), var(--success-color));
            color: white;
            border-radius: 50%;
            display: none;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 1000;
            transition: all var(--transition-speed) ease;
            border: none;
            opacity: 0;
            transform: translateY(20px);
        }
        
        .back-to-top.show {
            display: flex;
            opacity: 1;
            transform: translateY(0);
        }
        
        .back-to-top:hover {
            transform: translateY(-5px) scale(1.1);
        }
        
        /* Footer - Optimized */
        footer {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
        }
        
        .social-icons a {
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all var(--transition-speed) ease;
        }
        
        .social-icons a:hover {
            transform: translateY(-3px);
            background: rgba(255, 255, 255, 0.1);
        }
        
        /* Responsive Design - Optimized */
        @media (max-width: 992px) {
            .timeline::after {
                left: 50px;
            }
            
            .timeline-item {
                width: 100%;
                padding-left: 70px;
                padding-right: 25px;
                text-align: left !important;
                left: 0 !important;
            }
            
            .timeline-dot {
                left: 50px !important;
                right: auto !important;
            }
            
            .team-frame-full {
                height: 350px;
            }
        }
        
        @media (max-width: 768px) {
            .hero-about {
                padding: 150px 0 80px;
                min-height: 90vh;
            }
            
            .display-3 {
                font-size: 2.5rem;
            }
            
            .display-4 {
                font-size: 2rem;
            }
            
            .image-frame,
            .team-frame-full,
            .team-frame {
                height: 300px;
                margin: 20px 0;
            }
            
            .team-member-card {
                padding: 20px;
            }
        }
        
        @media (max-width: 576px) {
            .hero-about {
                padding: 120px 0 60px;
                min-height: 80vh;
            }
            
            .image-frame,
            .team-frame-full,
            .team-frame {
                height: 250px;
            }
            
            .timeline-item {
                padding: 10px 20px;
            }
            
            .timeline-content {
                padding: 20px;
            }
        }
        
        /* Performance Optimizations */
        .lazy-load {
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .lazy-loaded {
            opacity: 1;
        }
        
        /* Remove unused animations */
        .text-3d,
        .text-wave,
        .text-animate {
            display: none;
        }
    </style>
</head>
<body>
    <!-- Particle Background -->
    <div id="particles-js" aria-hidden="true"></div>
    
    <!-- Loading Bar -->
    <div class="loading-bar" id="loadingBar"></div>
    
    <!-- Back to Top Button -->
    <button class="back-to-top" id="backToTop" aria-label="Back to top">
        <i class="fas fa-chevron-up"></i>
    </button>
    
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="<?php echo htmlspecialchars($mainLogo); ?>" 
                     alt="<?php echo htmlspecialchars($settings['institute_name']); ?> Logo" 
                     height="40" 
                     class="me-2"
                     loading="lazy"
                     onerror="this.src='https://via.placeholder.com/40x40/2c3e50/ffffff?text=SW'">
                <span class="text-gradient fw-bold d-none d-md-inline">
                    <?php echo htmlspecialchars($settings['institute_name']); ?>
                </span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link active" href="about.php">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="courses.php">Courses</a></li>
                    <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
                    <li class="nav-item"><a class="nav-link" href="enrollment-form.php">Enroll Now</a></li>
                    <?php if ($isLoggedIn): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($fullName); ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="<?php echo htmlspecialchars($userType); ?>/dashboard.php">Dashboard</a></li>
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
    <section class="hero-about">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="display-3 fw-bold mb-4">
                        <span class="text-gradient">About Skills Way</span>
                    </h1>
                    <p class="lead mb-5">
                        Empowering Pakistani youth with industry-relevant skills for a prosperous future since 2020.
                        We bridge the gap between education and employment through practical, hands-on training.
                    </p>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="courses.php" class="btn btn-primary btn-lg px-4 py-3">
                            <i class="fas fa-book me-2"></i>Explore Courses
                        </a>
                        <a href="#team" class="btn btn-outline-light btn-lg px-4 py-3">
                            <i class="fas fa-users me-2"></i>Meet Our Team
                        </a>
                    </div>
                </div>
                <div class="col-lg-4 mt-5 mt-lg-0">
                    <div class="image-frame team-frame-full">
                        <img src="<?php echo htmlspecialchars($mainLogo); ?>" 
                             alt="<?php echo htmlspecialchars($settings['institute_name']); ?>" 
                             class="lazy-load"
                             loading="lazy">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Team Section -->
    <section class="team-section" id="team">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-4 fw-bold mb-3">
                    <span class="text-gradient">Meet Our Team</span>
                </h2>
                <p class="lead text-muted">The dedicated professionals who make it all possible</p>
            </div>
            
            <!-- Professional Staff -->
            <div class="row g-4 mb-5">
                <h3 class="text-center mb-4 text-gradient">Professional Staff</h3>
                <?php foreach (TEAM_FULL_FRAME as $member): 
                    $imageInfo = ImageHandler::getImage($member['image'], $member['name']);
                ?>
                <div class="col-lg-6 col-md-6">
                    <div class="team-member-card">
                        <div class="image-frame team-frame-full">
                            <img src="<?php echo htmlspecialchars($imageInfo['path']); ?>" 
                                 alt="<?php echo htmlspecialchars($member['name']); ?>" 
                                 class="lazy-load"
                                 loading="lazy">
                        </div>
                        <h3 class="h4 fw-bold mt-3"><?php echo htmlspecialchars($member['name']); ?></h3>
                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-2 mb-3">
                            <?php echo htmlspecialchars($member['position']); ?>
                        </span>
                        <p class="mb-3"><?php echo htmlspecialchars($member['description']); ?></p>
                        <?php if (!empty($member['social'])): ?>
                        <div class="mt-3">
                            <?php foreach ($member['social'] as $platform => $link): ?>
                            <a href="<?php echo $link; ?>" class="btn btn-sm btn-outline-primary me-2">
                                <i class="fab fa-<?php echo $platform; ?>"></i>
                            </a>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Expert Faculty -->
            <div class="row g-4">
                <h3 class="text-center mb-4 text-gradient">Expert Faculty & Student Representatives</h3>
                <?php foreach (TEAM_REGULAR as $member): 
                    $imageInfo = ImageHandler::getImage($member['image'], $member['name']);
                ?>
                <div class="col-lg-4 col-md-6">
                    <div class="team-member-card">
                        <div class="image-frame team-frame">
                            <img src="<?php echo htmlspecialchars($imageInfo['path']); ?>" 
                                 alt="<?php echo htmlspecialchars($member['name']); ?>" 
                                 class="lazy-load"
                                 loading="lazy">
                        </div>
                        <h3 class="h5 fw-bold mt-3"><?php echo htmlspecialchars($member['name']); ?></h3>
                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-2 mb-3">
                            <?php echo htmlspecialchars($member['position']); ?>
                        </span>
                        <p class="mb-3 small"><?php echo htmlspecialchars($member['description']); ?></p>
                        <?php if (!empty($member['social'])): ?>
                        <div class="mt-3">
                            <?php foreach ($member['social'] as $platform => $link): ?>
                            <a href="<?php echo $link; ?>" class="btn btn-sm btn-outline-primary me-2">
                                <i class="fab fa-<?php echo $platform; ?>"></i>
                            </a>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Journey Timeline -->
    <section class="timeline-section">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-4 fw-bold mb-3">
                    <span class="text-gradient">Our Journey</span>
                </h2>
                <p class="lead text-muted">Started in 2021 and continuing through 2025</p>
            </div>
            
            <div class="timeline">
                <?php foreach ($timelineData as $index => $item): ?>
                <div class="timeline-item <?php echo $index % 2 == 0 ? 'left' : 'right'; ?>">
                    <div class="timeline-content">
                        <span class="year-badge"><?php echo $item['year']; ?></span>
                        <div class="d-flex align-items-center mb-3">
                            <i class="<?php echo $item['icon']; ?> me-3 fa-lg text-primary"></i>
                            <h4 class="fw-bold mb-0"><?php echo $item['title']; ?></h4>
                        </div>
                    </div>
                    <div class="timeline-dot"></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <h4 class="mb-3">
                        <img src="<?php echo htmlspecialchars($mainLogo); ?>" 
                             alt="Logo" 
                             height="45" 
                             class="me-2"
                             loading="lazy">
                        <span class="text-gradient"><?php echo htmlspecialchars($settings['institute_name']); ?></span>
                    </h4>
                    <p class="mb-4 opacity-75"><?php echo htmlspecialchars($settings['institute_address']); ?></p>
                    <div class="social-icons">
                        <a href="#" class="btn btn-outline-light btn-sm rounded-circle me-2">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="btn btn-outline-light btn-sm rounded-circle me-2">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#" class="btn btn-outline-light btn-sm rounded-circle me-2">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                        <a href="#" class="btn btn-outline-light btn-sm rounded-circle">
                            <i class="fab fa-youtube"></i>
                        </a>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6 mb-4">
                    <h5 class="mb-3 text-gradient">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="index.php" class="text-light text-decoration-none opacity-75">Home</a></li>
                        <li class="mb-2"><a href="about.php" class="text-light text-decoration-none opacity-75">About</a></li>
                        <li class="mb-2"><a href="courses.php" class="text-light text-decoration-none opacity-75">Courses</a></li>
                        <li class="mb-2"><a href="contact.php" class="text-light text-decoration-none opacity-75">Contact</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <h5 class="mb-3 text-gradient">Contact Info</h5>
                    <ul class="list-unstyled">
                        <li class="mb-3">
                            <i class="fas fa-phone me-2 opacity-75"></i>
                            <span class="opacity-75"><?php echo htmlspecialchars($settings['contact_phone']); ?></span>
                        </li>
                        <li class="mb-3">
                            <i class="fas fa-envelope me-2 opacity-75"></i>
                            <a href="mailto:<?php echo htmlspecialchars($settings['contact_email']); ?>" 
                               class="text-light text-decoration-none opacity-75">
                                <?php echo htmlspecialchars($settings['contact_email']); ?>
                            </a>
                        </li>
                        <li class="mb-3">
                            <i class="fas fa-clock me-2 opacity-75"></i>
                            <span class="opacity-75">Mon-Sat: 8:00 AM - 9:00 PM</span>
                        </li>
                    </ul>
                </div>
                <div class="col-lg-3 mb-4">
                    <h5 class="mb-3 text-gradient">Newsletter</h5>
                    <p class="mb-3 opacity-75">Subscribe for updates</p>
                    <form class="newsletter-form" id="newsletterForm">
                        <div class="input-group">
                            <input type="email" class="form-control" placeholder="Your email" required>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <hr class="bg-light opacity-25 my-4">
            <div class="text-center pt-3">
                <p class="mb-0 opacity-75">
                    &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($settings['institute_name']); ?>. All rights reserved.
                </p>
            </div>
        </div>
    </footer>

    <!-- Optimized JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    
    <script>
    // Optimized JavaScript with better performance
    (function() {
        'use strict';
        
        class AboutPage {
            constructor() {
                this.init();
            }
            
            init() {
                this.initParticles();
                this.initEventListeners();
                this.initLazyLoading();
                this.initLoadingBar();
            }
            
            initParticles() {
                if (typeof particlesJS !== 'undefined') {
                    particlesJS('particles-js', {
                        particles: {
                            number: { value: 50, density: { enable: true, value_area: 800 } },
                            color: { value: "#3498db" },
                            shape: { type: "circle" },
                            opacity: { value: 0.3 },
                            size: { value: 3 },
                            line_linked: { enable: true, distance: 150, color: "#2ecc71", opacity: 0.2, width: 1 },
                            move: { enable: true, speed: 3, direction: "none" }
                        }
                    });
                }
            }
            
            initEventListeners() {
                // Back to top button
                const backToTop = document.getElementById('backToTop');
                if (backToTop) {
                    window.addEventListener('scroll', () => {
                        backToTop.classList.toggle('show', window.scrollY > 300);
                    });
                    
                    backToTop.addEventListener('click', () => {
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    });
                }
                
                // Navbar scroll effect
                window.addEventListener('scroll', () => {
                    document.querySelector('.navbar').classList.toggle('scrolled', window.scrollY > 50);
                });
                
                // Smooth scrolling
                document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                    anchor.addEventListener('click', function(e) {
                        const href = this.getAttribute('href');
                        if (href !== '#') {
                            e.preventDefault();
                            const target = document.querySelector(href);
                            if (target) {
                                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                            }
                        }
                    });
                });
                
                // Newsletter form
                document.getElementById('newsletterForm')?.addEventListener('submit', (e) => {
                    e.preventDefault();
                    const email = e.target.querySelector('input[type="email"]').value;
                    if (this.validateEmail(email)) {
                        e.target.querySelector('button').innerHTML = '<i class="fas fa-check"></i>';
                        setTimeout(() => {
                            e.target.reset();
                            e.target.querySelector('button').innerHTML = '<i class="fas fa-paper-plane"></i>';
                        }, 2000);
                    }
                });
            }
            
            initLazyLoading() {
                const lazyImages = document.querySelectorAll('.lazy-load');
                const imageObserver = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            img.src = img.dataset.src || img.src;
                            img.classList.add('lazy-loaded');
                            observer.unobserve(img);
                        }
                    });
                });
                
                lazyImages.forEach(img => imageObserver.observe(img));
            }
            
            initLoadingBar() {
                const loadingBar = document.getElementById('loadingBar');
                if (loadingBar) {
                    let progress = 0;
                    const interval = setInterval(() => {
                        progress += Math.random() * 30;
                        loadingBar.style.width = Math.min(progress, 100) + '%';
                        if (progress >= 100) {
                            clearInterval(interval);
                            setTimeout(() => {
                                loadingBar.style.opacity = '0';
                                setTimeout(() => loadingBar.remove(), 500);
                            }, 300);
                        }
                    }, 50);
                }
            }
            
            validateEmail(email) {
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
            }
        }
        
        // Initialize when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => new AboutPage());
        } else {
            new AboutPage();
        }
        
        // Remove unused GSAP scripts (not loaded in optimized version)
        // They were causing unnecessary network requests
    })();
    </script>
</body>
</html>