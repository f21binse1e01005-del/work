<?php
// Start session for user authentication
session_start();

// Enhanced Database Connection with error handling
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "skills_way_vocational";

// Error reporting for development (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Create connection with proper error handling
try {
    $conn = new mysqli($servername, $username, $password);

    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Check if database exists, create if not
    $db_selected = mysqli_select_db($conn, $dbname);

    if (!$db_selected) {
        // Create database
        $sql = "CREATE DATABASE IF NOT EXISTS $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";

        if ($conn->query($sql) === TRUE) {
            $conn->select_db($dbname);

            // Run the setup script if tables don't exist
            if (!tableExists($conn, 'users')) {
                runDatabaseSetup($conn);
            }
        } else {
            throw new Exception("Error creating database: " . $conn->error);
        }
    } else {
        $conn->select_db($dbname);
    }

    // Set connection charset
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    // Log error but don't show sensitive info to users
    error_log("Database Error: " . $e->getMessage());
    $conn = null; // Set to null to prevent further database operations
}

// Helper function to check if table exists
function tableExists($connection, $table)
{
    if (!$connection) return false;

    $result = $connection->query("SHOW TABLES LIKE '$table'");
    return $result && $result->num_rows > 0;
}

// Helper function to run database setup
function runDatabaseSetup($connection)
{
    // Read and execute the SQL file
    $sqlFile = 'setup_xampp.sql'; // Your SQL setup file

    if (file_exists($sqlFile)) {
        $sql = file_get_contents($sqlFile);

        // Execute multiple queries
        $queries = array_filter(explode(';', $sql));

        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                $connection->query($query);
            }
        }
    }
}

// Function to check if image exists
function getImagePath($path, $fallback)
{
    if (file_exists($path) && is_file($path) && filesize($path) > 0) {
        return $path;
    }
    return $fallback;
}

// Function to get institute settings from database
function getInstituteSettings($conn, $key = null)
{
    if (!$conn || $conn->connect_error) {
        return $key ? null : [];
    }

    try {
        if ($key) {
            $stmt = $conn->prepare("SELECT setting_value FROM institute_settings WHERE setting_key = ?");
            if ($stmt) {
                $stmt->bind_param("s", $key);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $stmt->close();
                    return $row['setting_value'];
                }
                $stmt->close();
            }
        } else {
            $result = $conn->query("SELECT setting_key, setting_value FROM institute_settings");
            $settings = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $settings[$row['setting_key']] = $row['setting_value'];
                }
                return $settings;
            }
        }
    } catch (Exception $e) {
        error_log("Error getting settings: " . $e->getMessage());
    }

    return $key ? null : [];
}

// Function to get beauty services from database
function getBeautyServices($conn)
{
    if (!$conn || $conn->connect_error) {
        return [];
    }

    try {
        $services = [];
        $sql = "SELECT service_name, service_description, icon_class FROM beauty_services 
                WHERE is_active = 1 ORDER BY display_order ASC";
        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $services[] = [
                    'icon' => $row['icon_class'] ?: 'fas fa-spa',
                    'title' => $row['service_name'],
                    'items' => array_map('trim', explode(',', $row['service_description']))
                ];
            }
        }
        return $services;
    } catch (Exception $e) {
        error_log("Error getting beauty services: " . $e->getMessage());
        return [];
    }
}

// Get institute information
$instituteName = getInstituteSettings($conn, 'institute_name') ?: 'Skills Way Vocational Institute';
$instituteAddress = getInstituteSettings($conn, 'institute_address') ?: 'Model Town "B", Near Masjid Al-Farooq/Bank Al-Habib, Khanpur';
$contactPhone = getInstituteSettings($conn, 'contact_phone') ?: '0307-0237356, 0331-3307365';
$contactEmail = getInstituteSettings($conn, 'contact_email') ?: 'askillswaykpr@gmail.com';

// Get beauty services
$services = getBeautyServices($conn);

// If no services from database, use fallback
if (empty($services)) {
    $services = [
        [
            'icon' => 'fas fa-paint-brush',
            'title' => 'Professional Makeup',
            'items' => ['Bridal Makeup', 'Party Makeup', 'Day Makeup', 'Event Makeup', 'Creative Makeup']
        ],
        [
            'icon' => 'fas fa-spa',
            'title' => 'Skin Care',
            'items' => ['Facials & Cleanups', 'Skin Treatments', 'Threading & Waxing', 'Bleaching Services', 'Chemical Peels']
        ],
        [
            'icon' => 'fas fa-cut',
            'title' => 'Hair Services',
            'items' => ['Hair Cutting & Styling', 'Hair Treatments', 'Coloring & Highlights', 'Hair Spa & Therapy', 'Hair Extensions']
        ],
        [
            'icon' => 'fas fa-hand-paper',
            'title' => 'Hands & Feet Care',
            'items' => ['Manicure & Pedicure', 'Nail Art & Design', 'Nail Extensions', 'Gel Nails', 'Nail Treatments']
        ],
        [
            'icon' => 'fas fa-leaf',
            'title' => 'Mehndi Art',
            'items' => ['Bridal Mehndi Designs', 'Arabic Mehndi', 'Traditional Patterns', 'Party Mehndi', 'Custom Designs']
        ],
        [
            'icon' => 'fas fa-crown',
            'title' => 'Bridal Packages',
            'items' => ['Complete Bridal Makeover', 'Pre-Bridal Treatments', 'Family Packages', 'Wedding Day Services', 'Trial Sessions']
        ]
    ];
}

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$userType = $isLoggedIn ? ($_SESSION['user_type'] ?? null) : null;
$userName = $isLoggedIn ? ($_SESSION['full_name'] ?? 'User') : null;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo htmlspecialchars($instituteName); ?> Beauty Parlor & Training Center - Professional beauty services and beautician training">
    <meta name="keywords" content="beauty parlor, beautician training, makeup courses, skin care, hair styling, Khanpur">
    <meta name="author" content="<?php echo htmlspecialchars($instituteName); ?>">

    <title><?php echo htmlspecialchars($instituteName); ?> - Beauty Parlor & Training Center</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Animate CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <!-- AOS CSS -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <style>
        /* CSS remains the same as your original code */
        :root {
            --luxury-mocha: #53260F;
            --soft-ivory: #F4F4DA;
            --champagne-gold: #D4AF37;
            --charcoal-black: #1B1B1B;
            --light-color: #ffffff;
            --gradient-primary: linear-gradient(135deg, #53260F 0%, #6B3419 100%);
            --gradient-gold: linear-gradient(135deg, #D4AF37 0%, #F4D03F 100%);
            --gradient-luxury: linear-gradient(135deg, var(--luxury-mocha) 0%, #8B4513 50%, var(--champagne-gold) 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--soft-ivory);
            line-height: 1.8;
            color: var(--charcoal-black);
            overflow-x: hidden;
            scroll-behavior: smooth;
        }

        /* Section Title */
        .section-title {
            font-family: 'Playfair Display', serif;
            font-weight: 700;
            font-size: 2.5rem;
            color: var(--luxury-mocha);
            position: relative;
            padding-bottom: 1rem;
            margin-bottom: 2rem;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 4px;
            background: var(--gradient-gold);
            border-radius: 2px;
        }

        /* Navigation Bar */
        .navbar {
            background: rgba(83, 38, 15, 0.95);
            backdrop-filter: blur(10px);
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 9999;
            padding: 1rem 0;
            transition: all 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            border-bottom: 2px solid transparent;
        }

        .navbar.scrolled {
            padding: 0.5rem 0;
            background: rgba(83, 38, 15, 0.98);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            border-bottom: 2px solid var(--champagne-gold);
        }

        .navbar-brand {
            font-family: 'Playfair Display', serif;
            font-weight: 700;
            color: var(--soft-ivory) !important;
            font-size: 1.5rem;
            position: relative;
            padding: 0.5rem 1rem 0.5rem 0;
            margin-right: 2rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            white-space: nowrap;
        }

        .navbar-toggler {
            border: 1px solid var(--champagne-gold);
            padding: 0.5rem 0.75rem;
        }

        .nav-link {
            font-weight: 500;
            color: var(--soft-ivory) !important;
            margin: 0 0.5rem;
            position: relative;
            padding: 0.5rem 1rem;
            transition: all 0.4s ease;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 2px;
            background: var(--champagne-gold);
            transform: translateX(-50%);
            transition: all 0.4s ease;
        }

        .nav-link:hover::after,
        .nav-link.active::after {
            width: 80%;
        }

        .nav-link:hover {
            color: var(--champagne-gold) !important;
            transform: translateY(-3px);
        }

        /* User dropdown */
        .user-dropdown .dropdown-toggle {
            background: var(--gradient-gold);
            border: none;
            color: var(--luxury-mocha) !important;
            font-weight: 600;
        }

        /* Hero Section */
        .hero-section {
            background: var(--gradient-primary);
            color: var(--soft-ivory);
            padding: 180px 0 80px;
            position: relative;
            overflow: hidden;
        }

        .hero-cover-image {
            width: 100%;
            max-width: 1200px;
            height: auto;
            border-radius: 20px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.3);
            margin-bottom: 3rem;
        }

        .hero-title {
            font-family: 'Playfair Display', serif;
            font-weight: 800;
            font-size: 3.5rem;
            margin-bottom: 1.5rem;
            line-height: 1.1;
            background: var(--gradient-gold);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }

            .section-title {
                font-size: 2rem;
            }

            .hero-section {
                padding: 150px 0 60px;
            }
        }

        /* CTA Buttons */
        .cta-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 1rem 2.5rem;
            font-size: 1.1rem;
            font-weight: 600;
            text-decoration: none;
            border-radius: 60px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            z-index: 1;
            margin: 0.5rem;
            border: none;
        }

        .cta-button.primary {
            background: var(--gradient-gold);
            color: var(--luxury-mocha);
            box-shadow: 0 10px 30px rgba(212, 175, 55, 0.4);
        }

        .cta-button.secondary {
            background: transparent;
            color: var(--soft-ivory);
            border: 2px solid var(--champagne-gold);
        }

        .cta-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(212, 175, 55, 0.5);
        }

        /* Services Grid */
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
        }

        .service-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(212, 175, 55, 0.1);
        }

        .service-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .service-icon {
            font-size: 2.5rem;
            color: var(--champagne-gold);
            margin-bottom: 1.5rem;
        }

        /* Gallery Grid */
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-top: 3rem;
        }

        .gallery-item {
            position: relative;
            border-radius: 15px;
            overflow: hidden;
            height: 250px;
        }

        .gallery-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .gallery-item:hover .gallery-image {
            transform: scale(1.1);
        }

        /* Price Section */
        .price-section {
            background: var(--gradient-primary);
            color: white;
            padding: 5rem 0;
            position: relative;
            overflow: hidden;
            margin: 4rem 0;
            border-radius: 20px;
        }

        .price-tag {
            font-size: 4rem;
            font-weight: 900;
            color: transparent;
            background: var(--gradient-gold);
            -webkit-background-clip: text;
            background-clip: text;
            margin: 2rem 0;
            display: inline-block;
        }

        /* WhatsApp Button */
        .whatsapp-float {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 60px;
            height: 60px;
            background: #25D366;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            box-shadow: 0 10px 30px rgba(37, 211, 102, 0.4);
            z-index: 9999;
            transition: all 0.3s ease;
        }

        .whatsapp-float:hover {
            transform: scale(1.1);
            box-shadow: 0 15px 40px rgba(37, 211, 102, 0.6);
        }

        /* Loading Screen */
        .loader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--luxury-mocha);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 99999;
            transition: opacity 0.5s ease, visibility 0.5s ease;
        }

        .loader.hidden {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }

        /* Footer */
        .footer {
            background: var(--gradient-primary);
            color: white;
            padding: 3rem 0 1.5rem;
            margin-top: 4rem;
        }

        .social-links a {
            display: inline-block;
            transition: all 0.3s ease;
        }

        .social-links a:hover {
            color: var(--champagne-gold) !important;
            transform: translateY(-5px);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .navbar-brand {
                font-size: 1.2rem;
            }

            .hero-title {
                font-size: 2rem;
            }

            .services-grid,
            .gallery-grid {
                grid-template-columns: 1fr;
            }

            .price-tag {
                font-size: 2.5rem;
            }

            .cta-button {
                padding: 0.8rem 1.5rem;
                font-size: 1rem;
                margin: 0.3rem;
                display: block;
                width: 100%;
                margin-bottom: 1rem;
            }
        }

        /* Accessibility */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        a:focus,
        button:focus {
            outline: 2px solid var(--champagne-gold);
            outline-offset: 2px;
        }
    </style>
</head>

<body>
    <!-- Loading Screen -->
    <div class="loader" id="loader">
        <div class="loader-content text-center">
            <div class="spinner-border text-warning" style="width: 3rem; height: 3rem;" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <h3 class="mt-3" style="color: var(--champagne-gold);">Loading <?php echo htmlspecialchars($instituteName); ?></h3>
        </div>
    </div>

    <!-- WhatsApp Floating Button -->
    <a href="#" class="whatsapp-float" target="_blank" aria-label="Contact on WhatsApp" id="whatsappBtn">
        <i class="fab fa-whatsapp"></i>
    </a>

    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-spa me-2"></i>
                <?php echo htmlspecialchars($instituteName); ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="about.php">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="courses.php">Courses</a></li>
                    <li class="nav-item"><a class="nav-link active" href="BEAUTY_PARLOR.php">Beauty Parlor</a></li>
                    <li class="nav-item"><a class="nav-link" href="Co-Founder.php">Co-Founders</a></li>
                    <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
                    <li class="nav-item"><a class="nav-link" href="enrollment-form.php">Enroll Now</a></li>

                    <?php if ($isLoggedIn): ?>
                        <li class="nav-item dropdown user-dropdown ms-2">
                            <a class="nav-link dropdown-toggle btn btn-primary" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($userName); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
                                <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                                <li><a class="dropdown-item" href="messages.php"><i class="fas fa-envelope me-2"></i>Messages</a></li>
                                <li><a class="dropdown-item" href="my-courses.php"><i class="fas fa-book me-2"></i>My Courses</a></li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item ms-2">
                            <a class="btn btn-outline-light btn-sm" href="login.php">Login</a>
                            <a class="btn btn-light btn-sm ms-2" href="register.php" style="color: var(--luxury-mocha);">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section" id="hero">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-10 mx-auto text-center">
                    <!-- Main Cover Image -->
                    <?php
                    $coverImage = getImagePath(
                        "uploads/images/paylor/beauty paylorCover.jpg",
                        "https://images.unsplash.com/photo-1596462502278-27bfdc403348?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80"
                    );
                    ?>
                    <img src="<?php echo $coverImage; ?>"
                        alt="<?php echo htmlspecialchars($instituteName); ?> Beauty Parlor & Training Center"
                        class="hero-cover-image"
                        onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1596462502278-27bfdc403348?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80'">

                    <h1 class="hero-title">
                        <?php echo htmlspecialchars($instituteName); ?> Beauty Parlor & Training Center
                    </h1>
                    <p class="lead mb-4">
                        Experience professional beauty services and certified beautician training in Khanpur.
                        Transform your passion into a successful career with our expert-led programs.
                    </p>
                    <div class="mt-4">
                        <a href="#services" class="cta-button primary">
                            <i class="fas fa-spa me-2"></i>Explore Our Services
                        </a>
                        <a href="#enroll" class="cta-button secondary">
                            <i class="fas fa-graduation-cap me-2"></i>Start Training
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Logo Section -->
    <section class="py-5" id="logo">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8 col-md-10">
                    <div class="text-center">
                        <?php
                        $logoImage = getImagePath(
                            "uploads/images/paylor/Skills Way Circle Logo.svg",
                            "https://via.placeholder.com/400x400/D4AF37/53260F?text=" . urlencode($instituteName)
                        );
                        ?>
                        <img src="<?php echo $logoImage; ?>"
                            alt="<?php echo htmlspecialchars($instituteName); ?> Logo"
                            class="img-fluid"
                            style="max-height: 300px;"
                            onerror="this.onerror=null; this.src='https://via.placeholder.com/400x400/D4AF37/53260F?text=' + encodeURIComponent('<?php echo $instituteName; ?>')">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section class="py-5" id="services" style="background-color: var(--soft-ivory);">
        <div class="container">
            <h2 class="section-title text-center mb-4">
                Our Beauty Parlor Services
            </h2>
            <p class="text-center text-muted mb-5">
                Professional beauty services with quality products and expert techniques
            </p>

            <div class="services-grid">
                <?php foreach ($services as $index => $service): ?>
                    <div class="service-card" data-aos="fade-up" data-aos-delay="<?php echo $index * 100; ?>">
                        <div class="text-center">
                            <i class="<?php echo htmlspecialchars($service['icon']); ?> service-icon"></i>
                            <h5 class="mb-3"><?php echo htmlspecialchars($service['title']); ?></h5>
                        </div>
                        <ul class="list-unstyled">
                            <?php foreach ($service['items'] as $item): ?>
                                <?php if (!empty(trim($item))): ?>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        <?php echo htmlspecialchars(trim($item)); ?>
                                    </li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Gallery Section -->
    <section class="py-5">
        <div class="container">
            <h2 class="section-title text-center mb-4">
                Our Beauty Parlor Gallery
            </h2>
            <p class="text-center text-muted mb-5">
                Explore our professional beauty parlor facilities and services
            </p>

            <div class="gallery-grid">
                <?php
                // Gallery images
                $galleryImages = [
                    ['a.jpeg', 'Modern Parlor Interior', 'State-of-the-art beauty facility'],
                    ['b.jpeg', 'Professional Workstations', 'Equipped with premium tools'],
                    ['c.jpeg', 'Hair Styling Station', 'Modern hair care setup'],
                    ['d.jpeg', 'Premium Equipment', 'Latest beauty technology'],
                    ['e.jpeg', 'Client Waiting Area', 'Comfortable lounge space'],
                    ['f.jpeg', 'Makeup & Bridal Station', 'Special bridal setup'],
                    ['g.jpeg', 'Practical Training Area', 'Hands-on learning space'],
                    ['h.jpeg', 'Comprehensive Services Area', 'All-in-one beauty solutions'],
                    ['i.jpeg', 'Reception & Consultation', 'Professional consultation space']
                ];

                $fallbackImages = [
                    'https://images.unsplash.com/photo-1560066984-138dadb4c035?w=400&h=300&fit=crop',
                    'https://images.unsplash.com/photo-1596462502278-27bfdc403348?w=400&h=300&fit=crop',
                    'https://images.unsplash.com/photo-1562322140-8baeececf3df?w=400&h=300&fit=crop',
                    'https://images.unsplash.com/photo-1605497788044-5a32c7078486?w=400&h=300&fit=crop',
                    'https://images.unsplash.com/photo-1522338242990-ea05c0403b75?w=400&h=300&fit=crop',
                    'https://images.unsplash.com/photo-1515377905703-c4788e51af15?w=400&h=300&fit=crop',
                    'https://images.unsplash.com/photo-1596703923338-48f1c07e4f2e?w=400&h=300&fit=crop',
                    'https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?w=400&h=300&fit=crop',
                    'https://images.unsplash.com/photo-1560743641-3914f2c45636?w=400&h=300&fit=crop'
                ];

                foreach ($galleryImages as $index => $image):
                    $imagePath = "uploads/images/paylor/" . $image[0];
                    $alt = $image[1];
                    $caption = $image[2];
                    $fallback = $fallbackImages[$index] ?? 'https://via.placeholder.com/400x300/D4AF37/53260F?text=Beauty+Parlor';

                    $finalImage = getImagePath($imagePath, $fallback);
                ?>
                    <div class="gallery-item" data-aos="zoom-in" data-aos-delay="<?php echo $index * 50; ?>">
                        <img src="<?php echo $finalImage; ?>"
                            alt="<?php echo htmlspecialchars($alt); ?>"
                            class="gallery-image"
                            loading="lazy"
                            onerror="this.onerror=null; this.src='<?php echo htmlspecialchars($fallback); ?>'">
                        <div class="gallery-caption position-absolute bottom-0 start-0 end-0 text-white p-3" style="background: rgba(0,0,0,0.7);">
                            <h6 class="mb-1"><?php echo htmlspecialchars($alt); ?></h6>
                            <small><?php echo htmlspecialchars($caption); ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Price Section -->
    <section class="price-section">
        <div class="container">
            <div class="text-center">
                <h2 class="mb-4">Complete Beautician Course Fee</h2>
                <div class="price-tag">Rs 70,000/-</div>
                <p class="lead mb-5">
                    Complete 3-Month Professional Program with Certification
                </p>

                <div class="row justify-content-center">
                    <div class="col-md-3 col-sm-6 mb-4">
                        <div class="card h-100 border-0 bg-transparent text-white">
                            <div class="card-body">
                                <i class="fas fa-calendar-check fa-3x mb-3 text-warning"></i>
                                <h5>3 Months Duration</h5>
                                <p class="small">Comprehensive training program</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6 mb-4">
                        <div class="card h-100 border-0 bg-transparent text-white">
                            <div class="card-body">
                                <i class="fas fa-clock fa-3x mb-3 text-warning"></i>
                                <h5>Flexible Timings</h5>
                                <p class="small">Morning & Evening batches</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6 mb-4">
                        <div class="card h-100 border-0 bg-transparent text-white">
                            <div class="card-body">
                                <i class="fas fa-certificate fa-3x mb-3 text-warning"></i>
                                <h5>Certification</h5>
                                <p class="small">Industry recognized certificate</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6 mb-4">
                        <div class="card h-100 border-0 bg-transparent text-white">
                            <div class="card-body">
                                <i class="fas fa-user-graduate fa-3x mb-3 text-warning"></i>
                                <h5>Expert Training</h5>
                                <p class="small">Learn from professionals</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Enrollment CTA -->
    <section class="py-5" id="enroll">
        <div class="container">
            <div class="text-center">
                <h2 class="section-title mb-4">
                    Begin Your Beauty Career Today
                </h2>
                <p class="text-muted mb-5">
                    Join our professional beautician course and transform your passion into a successful career
                </p>

                <div class="mt-4">
                    <a href="enrollment-form.php" class="cta-button primary me-3">
                        <i class="fas fa-user-plus me-2"></i>Enroll Online Now
                    </a>
                    <a href="tel:<?php echo explode(',', $contactPhone)[0]; ?>" class="cta-button secondary">
                        <i class="fas fa-phone me-2"></i>Call Now: <?php echo htmlspecialchars(explode(',', $contactPhone)[0]); ?>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-12 text-center">
                    <h4 class="mb-3"><?php echo htmlspecialchars($instituteName); ?></h4>
                    <p class="mb-4"><?php echo htmlspecialchars($instituteAddress); ?></p>
                    <div class="social-links mb-4">
                        <a href="https://facebook.com/<?php echo htmlspecialchars($instituteName); ?>" class="text-white me-3" target="_blank"><i class="fab fa-facebook fa-2x"></i></a>
                        <a href="https://instagram.com/<?php echo htmlspecialchars($instituteName); ?>" class="text-white me-3" target="_blank"><i class="fab fa-instagram fa-2x"></i></a>
                        <a href="#" class="text-white me-3" id="footerWhatsapp"><i class="fab fa-whatsapp fa-2x"></i></a>
                        <a href="mailto:<?php echo htmlspecialchars($contactEmail); ?>" class="text-white"><i class="fas fa-envelope fa-2x"></i></a>
                    </div>
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($instituteName); ?>. All rights reserved.</p>
                    <p class="small mt-2">Phone: <?php echo htmlspecialchars($contactPhone); ?></p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <!-- AOS JS -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

    <!-- Enhanced JavaScript -->
    <script>
        // Initialize AOS
        AOS.init({
            duration: 800,
            once: true,
            offset: 100
        });

        $(document).ready(function() {
            console.log("Skills Way Beauty Parlor - Initialized");

            // Hide loader
            setTimeout(function() {
                $('#loader').addClass('hidden');
            }, 800);

            // Navbar scroll effect
            $(window).scroll(function() {
                if ($(this).scrollTop() > 50) {
                    $('.navbar').addClass('scrolled');
                } else {
                    $('.navbar').removeClass('scrolled');
                }
            });

            // Smooth scrolling for anchor links
            $('a[href^="#"]').on('click', function(e) {
                if (this.hash !== "" && $(this.hash).length) {
                    e.preventDefault();
                    const hash = this.hash;
                    $('html, body').animate({
                        scrollTop: $(hash).offset().top - 80
                    }, 800);
                }
            });

            // WhatsApp button function
            function setupWhatsAppButton() {
                const phoneNumber = "92307237356";
                const message = encodeURIComponent(
                    "Hello <?php echo htmlspecialchars($instituteName); ?> Beauty Parlor! 👋\n\n" +
                    "I'm interested in your Beauty Parlor services / Beautician Course. " +
                    "Could you please provide more information about:\n" +
                    "1. Course duration and timings\n" +
                    "2. Fee structure\n" +
                    "3. Services offered\n\n" +
                    "Thank you! 😊"
                );
                return `https://wa.me/${phoneNumber}?text=${message}`;
            }

            // Set WhatsApp button URLs
            $('#whatsappBtn, #footerWhatsapp').attr('href', setupWhatsAppButton());

            // Service card hover effect
            $('.service-card').hover(
                function() {
                    $(this).css('transform', 'translateY(-10px)');
                },
                function() {
                    $(this).css('transform', 'translateY(0)');
                }
            );

            // Gallery image error handling
            $('.gallery-image').on('error', function() {
                const fallback = 'https://via.placeholder.com/400x300/D4AF37/53260F?text=Beauty+Parlor';
                if (this.src !== fallback) {
                    this.src = fallback;
                }
            });

            // Add active class to current page in navbar
            const currentPage = window.location.pathname.split('/').pop() || 'index.php';
            $('.nav-link').each(function() {
                const linkPage = $(this).attr('href');
                if (linkPage === currentPage) {
                    $(this).addClass('active');
                    $(this).parent().addClass('active');
                }
            });

            // Mobile responsive adjustments
            function adjustMobileLayout() {
                if ($(window).width() < 768) {
                    $('.cta-button').css({
                        'width': '100%',
                        'margin-bottom': '10px'
                    });
                } else {
                    $('.cta-button').css({
                        'width': 'auto',
                        'margin-bottom': '0'
                    });
                }
            }

            // Call on load and resize
            adjustMobileLayout();
            $(window).resize(adjustMobileLayout);

            // Back to top button functionality
            $(window).scroll(function() {
                if ($(this).scrollTop() > 300) {
                    if (!$('#backToTop').length) {
                        $('body').append('<button id="backToTop" class="btn btn-warning" style="position:fixed;bottom:80px;right:20px;z-index:999;border-radius:50%;width:50px;height:50px;display:flex;align-items:center;justify-content:center;"><i class="fas fa-arrow-up"></i></button>');

                        $('#backToTop').click(function() {
                            $('html, body').animate({
                                scrollTop: 0
                            }, 800);
                        });
                    }
                } else {
                    $('#backToTop').remove();
                }
            });

            // Add animation to service cards on scroll
            $(window).scroll(function() {
                $('.service-card').each(function() {
                    const elementTop = $(this).offset().top;
                    const elementBottom = elementTop + $(this).outerHeight();
                    const viewportTop = $(window).scrollTop();
                    const viewportBottom = viewportTop + $(window).height();

                    if (elementBottom > viewportTop && elementTop < viewportBottom) {
                        $(this).addClass('animated');
                    }
                });
            });
        });

        // Handle page unloading
        window.addEventListener('beforeunload', function() {
            $('#loader').removeClass('hidden');
        });

        // Form submission handling (if forms are added later)
        $(document).on('submit', 'form', function(e) {
            const form = $(this);
            if (form.attr('data-ajax') === 'true') {
                e.preventDefault();
                $.ajax({
                    url: form.attr('action'),
                    method: form.attr('method'),
                    data: form.serialize(),
                    success: function(response) {
                        if (response.success) {
                            // Show success message
                            alert(response.message || 'Form submitted successfully!');
                            form[0].reset();
                        } else {
                            alert(response.message || 'Error submitting form.');
                        }
                    },
                    error: function() {
                        alert('Network error. Please try again.');
                    }
                });
            }
        });
    </script>
</body>

</html>

<?php
// Close database connection
if (isset($conn) && $conn && !$conn->connect_error) {
    $conn->close();
}
?>